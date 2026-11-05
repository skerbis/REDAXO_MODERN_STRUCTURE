<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Optionale KI-Auto-Tagging-Vorschlaege -- wie AiAltTextService ein duenner
 * Wrapper um das separate ai_platform-Addon (rein soft-optional, siehe
 * isAvailable()). Liefert nur Vorschlaege zurueck, schreibt selbst NICHT
 * (Review-vor-Speichern-Prinzip, siehe Api\AiAutoTag.php).
 *
 * GESCHLOSSENES Vokabular, bewusst kein freies Generieren: die KI waehlt
 * ausschliesslich aus den Tags, die in der Tag-Verwaltung explizit als
 * "Für KI-Vorschläge freigeben" markiert sind (SystemTagManager::
 * getAiAllowedTagNames()) -- sie erfindet nie neue Tags. Das Modell bekommt
 * die erlaubte Liste im Prompt; die Antwort wird zusaetzlich serverseitig
 * gegen genau diese Liste gefiltert (parseResponse()) -- der Prompt ist eine
 * Bitte, kein Zwang, ein Modell kann trotzdem einen nicht gelisteten oder
 * leicht abgewandelten Namen liefern, und nur das serverseitige Whitelisting
 * verhindert zuverlaessig, dass so ein Name je als Tag-Vorschlag beim Nutzer
 * ankommt.
 */
class AiAutoTagService
{
    public static function isAvailable(): bool
    {
        return \rex_config::get('mediaplace', 'enable_ai_auto_tag', false)
            && \rex_addon::exists('ai_platform')
            && \rex_addon::get('ai_platform')->isAvailable()
            && [] !== SystemTagManager::getAiAllowedTagNames();
    }

    /**
     * @param string|null $rasterizedImageData siehe AiAltTextService::generateAltText()
     * @return list<string> Teilmenge von SystemTagManager::getAiAllowedTagNames()
     */
    public function suggestTags(\rex_media $media, ?string $rasterizedImageData = null): array
    {
        $allowed = SystemTagManager::getAiAllowedTagNames();
        if ([] === $allowed) {
            return [];
        }

        $prepared = AiImagePreparer::resolve($media, $rasterizedImageData);

        try {
            $prompt = $this->buildPrompt($allowed);
            $service = \FriendsOfRedaxo\AiPlatform\Service::getInstance();
            $raw = $service->understandImage($prompt, $prepared['path'], $this->resolveProfileId());

            return $this->parseResponse($raw, $allowed);
        } finally {
            AiImagePreparer::cleanup($prepared);
        }
    }

    private function resolveProfileId(): ?int
    {
        // Teilt sich das Bildverstaendnis-Profil mit AiAltTextService --
        // ein Konfigurationspunkt fuer alle KI-Bild-Features dieses Addons,
        // siehe Hinweistext auf der Einstellungsseite.
        $override = (int) \rex_config::get('mediaplace', 'ai_alt_platform_profile_id', 0);

        return $override > 0 ? $override : null;
    }

    private function maxTags(): int
    {
        $value = (int) \rex_config::get('mediaplace', 'ai_auto_tag_max', 3);

        return max(1, min(10, $value));
    }

    /**
     * @param list<string> $allowed
     */
    private function buildPrompt(array $allowed): string
    {
        $max = $this->maxTags();
        $list = implode(', ', array_map(
            static fn (string $tag): string => '"' . str_replace('"', '\\"', $tag) . '"',
            $allowed,
        ));

        return 'Wähle aus der folgenden Liste bestehender Tags ausschließlich diejenigen aus, die den Inhalt dieses Bildes treffend beschreiben. '
            . 'Erlaubte Tags (NUR aus dieser Liste wählen, keine neuen erfinden, Schreibweise exakt übernehmen): ' . $list . '. '
            . 'Wähle maximal ' . $max . ' Tags, nur wirklich zutreffende -- im Zweifel weniger statt mehr. '
            . 'Antworte NUR mit einem reinen JSON-Array der ausgewählten Tag-Namen (exakte Schreibweise aus der Liste), ohne Einleitung, ohne Code-Block-Markierung. '
            . 'Trifft kein Tag zu, antworte mit [].';
    }

    /**
     * @param list<string> $allowed
     * @return list<string>
     */
    private function parseResponse(string $raw, array $allowed): array
    {
        $clean = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($raw)));
        $decoded = json_decode($clean, true);
        if (!is_array($decoded)) {
            return [];
        }

        // Serverseitiger Whitelist-Filter -- siehe Klassen-Docblock, NIE der
        // Modellantwort blind vertrauen.
        $allowedLookup = array_flip($allowed);
        $result = [];
        foreach ($decoded as $value) {
            if (!is_string($value)) {
                continue;
            }
            $value = trim($value);
            if (isset($allowedLookup[$value]) && !in_array($value, $result, true)) {
                $result[] = $value;
            }
        }

        return array_slice($result, 0, $this->maxTags());
    }
}
