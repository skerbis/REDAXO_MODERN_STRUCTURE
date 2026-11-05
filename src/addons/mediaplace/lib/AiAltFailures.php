<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Merkliste der Dateien, bei denen die KI-Alt-Text-Generierung gescheitert ist (nicht lesbar, Format nicht umwandelbar,
 * Serverfehler, keine verwertbare Antwort). Massengenerierung, Cronjob und Konsole überspringen sie, bis jemand
 * „erneut versuchen“ wählt oder die Datei ersetzt bzw. einen ALT-Text bekommt. Gespeichert in rex_config
 * (mediaplace/ai_alt_failed: Dateiname => [message, time, size]); eine geänderte Datei (andere Größe) gilt als neu.
 */
final class AiAltFailures
{
    private const KEY = 'ai_alt_failed';
    private const MAX_ENTRIES = 2000;

    /** @return array<string, array{message: string, time: int, size: int}> */
    public static function all(): array
    {
        $list = \rex_config::get('mediaplace', self::KEY, []);

        return is_array($list) ? $list : [];
    }

    public static function mark(string $filename, string $message): void
    {
        $list = self::all();
        $media = \rex_media::get($filename);
        $list[$filename] = ['message' => mb_substr(trim($message), 0, 300), 'time' => time(), 'size' => $media ? (int) $media->getSize() : 0];
        if (count($list) > self::MAX_ENTRIES) {
            uasort($list, static fn (array $a, array $b): int => $b['time'] <=> $a['time']);
            $list = array_slice($list, 0, self::MAX_ENTRIES, true);
        }
        \rex_config::set('mediaplace', self::KEY, $list);
    }

    public static function clear(string $filename): void
    {
        $list = self::all();
        if (isset($list[$filename])) {
            unset($list[$filename]);
            \rex_config::set('mediaplace', self::KEY, $list);
        }
    }

    public static function clearAll(): void
    {
        \rex_config::set('mediaplace', self::KEY, []);
    }

    /** Ist die Datei als fehlerhaft gemerkt (und seitdem nicht ersetzt worden)? */
    public static function isMarked(string $filename): bool
    {
        $entry = self::all()[$filename] ?? null;
        if (null === $entry) {
            return false;
        }
        $media = \rex_media::get($filename);

        return !$media || (int) $media->getSize() === (int) ($entry['size'] ?? 0);
    }

    /**
     * @param list<string> $filenames
     * @return list<string>
     */
    public static function withoutMarked(array $filenames): array
    {
        if ([] === self::all()) {
            return $filenames;
        }

        return array_values(array_filter($filenames, static fn (string $f): bool => !self::isMarked($f)));
    }
}
