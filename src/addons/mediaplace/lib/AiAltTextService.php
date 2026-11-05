<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Optionale KI-Alt-Text-Generierung -- duenner Wrapper um das separate
 * ai_platform-Addon (rein soft-optional, siehe isAvailable()). Liefert nur
 * generierten Text zurueck, schreibt selbst NICHT in die Datenbank -- weder
 * der Einzeldatei-Button (Api\AiAltText.php) noch die Massengenerierung
 * (Api\AiAltBulk.php) schreiben direkt aus der Generierung heraus; beide
 * folgen dem Review-vor-Speichern-Prinzip, das Schreiben passiert erst in
 * einem separaten, expliziten Schritt (ueber AiAltTextWriter).
 */
class AiAltTextService
{
    private const PROMPT_TEMPLATES = [
        'accessibility' => 'Beschreibe dieses Bild in maximal 125 Zeichen als barrierefreien ALT-Text für Screenreader-Nutzer. Beschreibe den wesentlichen Bildinhalt sachlich und knapp, ohne "Bild von" oder "Foto von" einzuleiten. Keine Interpretation, keine Meinung.',
        'neutral' => 'Beschreibe den Inhalt dieses Bildes in einem kurzen, sachlichen Satz (maximal 125 Zeichen).',
        'seo' => 'Beschreibe dieses Bild in maximal 125 Zeichen so, dass relevante Suchbegriffe für Bildersuchmaschinen enthalten sind, ohne dabei unnatürlich oder wie eine Keyword-Liste zu klingen.',
    ];

    public static function isAvailable(): bool
    {
        return \rex_config::get('mediaplace', 'enable_ai_alt_text', false)
            && \rex_addon::exists('ai_platform')
            && \rex_addon::get('ai_platform')->isAvailable();
    }

    /**
     * @param list<int> $clangIds
     * @param string|null $rasterizedImageData Client-seitig auf Canvas gerendertes
     *   PNG als "data:image/png;base64,..."-URL -- einziger Weg, SVGs (Vektorformat,
     *   kein serverseitiger Rasterizer vorhanden) trotzdem der KI zu zeigen. Der
     *   Browser rendert das SVG (er kann es, das ist sein Job), das Ergebnis ist
     *   ein normales Pixelbild. Kommt aktuell nur vom Einzeldatei-Button, siehe
     *   Api\AiAltText.php -- die Massengenerierung ueberspringt SVGs weiterhin
     *   (kein Client-Rendering im serverseitigen Batch-Loop verfuegbar).
     * @return array<string, string> clang-id (als String-Key) => generierter Text
     */
    public function generateAltText(\rex_media $media, array $clangIds, ?string $rasterizedImageData = null): array
    {
        if ([] === $clangIds) {
            return [];
        }

        $prepared = AiImagePreparer::resolve($media, $rasterizedImageData);

        try {
            $prompt = $this->buildPrompt($clangIds);
            $service = \FriendsOfRedaxo\AiPlatform\Service::getInstance();
            $raw = $service->understandImage($prompt, $prepared['path'], $this->resolveProfileId());

            return $this->parseResponse($raw, $clangIds);
        } finally {
            AiImagePreparer::cleanup($prepared);
        }
    }

    private function resolveProfileId(): ?int
    {
        $override = (int) \rex_config::get('mediaplace', 'ai_alt_platform_profile_id', 0);

        // 0 = ai_platform's eigenes konfiguriertes Default-Profil verwenden --
        // Service::understandImage() macht das selbst bei null.
        return $override > 0 ? $override : null;
    }

    /**
     * @param list<int> $clangIds
     */
    private function buildPrompt(array $clangIds): string
    {
        $custom = trim((string) \rex_config::get('mediaplace', 'ai_alt_custom_prompt', ''));
        $profile = (string) \rex_config::get('mediaplace', 'ai_alt_prompt_profile', 'accessibility');
        $base = '' !== $custom ? $custom : (self::PROMPT_TEMPLATES[$profile] ?? self::PROMPT_TEMPLATES['accessibility']);

        if (count($clangIds) <= 1) {
            return $base;
        }

        $codes = array_map(static function (int $clangId): string {
            $clang = \rex_clang::get($clangId);

            return $clang ? $clang->getCode() : (string) $clangId;
        }, $clangIds);

        return $base . "\n\nGib das Ergebnis als reines JSON-Objekt zurück, mit den Sprachcodes "
            . implode(', ', $codes) . ' als Schlüssel und je einer Übersetzung als Wert. '
            . 'Beispiel: {"' . $codes[0] . '": "..."}. Antworte NUR mit dem JSON-Objekt, ohne Einleitung, ohne Code-Block-Markierung.';
    }

    /**
     * @param list<int> $clangIds
     * @return array<string, string>
     */
    private function parseResponse(string $raw, array $clangIds): array
    {
        if (count($clangIds) <= 1) {
            $text = trim($raw);

            return '' !== $text ? [(string) $clangIds[0] => $text] : [];
        }

        $clean = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($raw)));
        $decoded = json_decode($clean, true);
        if (!is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach ($clangIds as $clangId) {
            $clang = \rex_clang::get($clangId);
            $code = $clang ? $clang->getCode() : (string) $clangId;
            $value = $decoded[$code] ?? null;
            if (is_string($value) && '' !== trim($value)) {
                $result[(string) $clangId] = trim($value);
            }
        }

        return $result;
    }
}
