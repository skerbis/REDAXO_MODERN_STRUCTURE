<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Konsumiert das separate "ffmpeg"-Addon (FriendsOfRedaxo/ffmpeg) fuer zwei
 * unabhaengige Faehigkeiten:
 *
 * 1. Video-Vorschau im Grid (ensureVideoThumbTypes()): registriert bei Bedarf
 *    ZWEI eigene Media-Manager-Typen, die ffmpeg's rex_effect_video_to_webp
 *    nutzen -- VIDEO_THUMB_TYPE (animiertes WebP) und VIDEO_THUMB_TYPE_STATIC
 *    (einzelnes Standbild, deutlich billiger in Erzeugung/Groesse). Welcher
 *    davon aktiv genutzt wird (oder gar keiner), steuert die Einstellung
 *    "video_thumb_mode" (aus/static/animated, siehe getVideoThumbMode()/
 *    getActiveVideoThumbType()) -- gleiches Muster wie der eigene
 *    "mediaplace_thumb"-Typ fuer Bilder (siehe install.php), nur lazy zur
 *    Boot-Zeit statt beim Addon-Install, weil ffmpeg unabhaengig von
 *    MediaPlace jederzeit (de)installiert werden kann.
 * 2. "Video optimieren"-Button im Detail-Panel (canOptimize()): nutzt
 *    ffmpeg's Job-Engine (Converter::startOverwriteJob()/pollJob()) direkt,
 *    siehe Api\VideoOptimize.php.
 *
 * Alle ffmpeg-Klassen werden nur vollqualifiziert INNERHALB von
 * isAvailable()-abgesicherten Methoden referenziert (kein "use" am
 * Dateikopf) -- die Datei muss auch fehlerfrei ladbar sein, wenn das
 * ffmpeg-Addon nicht installiert ist.
 */
class FfmpegIntegration
{
    public const VIDEO_THUMB_TYPE = 'mediaplace_video_thumb';
    public const VIDEO_THUMB_TYPE_STATIC = 'mediaplace_video_thumb_static';

    /** Deckungsgleich mit ffmpeg's eigener VIDEO_TYPES-Liste (rex_effect_video_to_webp). */
    private const SUPPORTED_EXTENSIONS = ['mp4', 'm4v', 'avi', 'mov', 'webm'];

    /** Cache-Dauer fuer isFfmpegBinaryAvailable() -- siehe dortiger Kommentar. */
    private const BINARY_CHECK_TTL = 3600;

    /**
     * Nicht nur "Addon installiert", sondern auch "ffmpeg-Binary funktioniert
     * tatsaechlich auf diesem Server". Viele (v.a. gehostete/Shared-)Webspaces
     * haben kein ffmpeg installiert, selbst wenn das Addon aktiviert ist --
     * ohne diese Pruefung wuerde das Grid <img src="...mediaplace_video_thumb...">
     * anfordern, der Effekt wuerde serverseitig fehlschlagen, und im
     * Browser bliebe ein kaputtes Bild statt des bisherigen, funktionierenden
     * Datei-Icons. isAvailable() ist damit die EINZIGE Quelle der Wahrheit
     * fuer alle drei Faehigkeiten (Vorschau/Optimieren/Technische Details) --
     * ist ffmpeg nicht wirklich lauffaehig, bleibt konsequent ueberall der
     * bisherige Fallback (Icon / Button ausgeblendet).
     */
    public static function isAvailable(): bool
    {
        if (!\rex_addon::get('ffmpeg')->isAvailable()) {
            return false;
        }

        return self::isFfmpegBinaryAvailable();
    }

    /**
     * exec() ist teuer genug, um es nicht bei jedem Grid-/Detail-Panel-Aufruf
     * neu auszufuehren -- Ergebnis wird ueber rex_config fuer BINARY_CHECK_TTL
     * Sekunden gecacht. Wird ffmpeg nachtraeglich auf dem Server installiert
     * (oder entfernt), zieht das spaetestens nach Ablauf der Cache-Zeit nach,
     * kein manueller Cache-Clear noetig.
     */
    private static function isFfmpegBinaryAvailable(): bool
    {
        $cached = \rex_config::get('mediaplace', 'ffmpeg_binary_check');
        if (is_array($cached) && isset($cached['checked_at'], $cached['available']) && (time() - (int) $cached['checked_at']) < self::BINARY_CHECK_TTL) {
            return (bool) $cached['available'];
        }

        $available = false;
        if (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            @exec('ffmpeg -version 2>&1', $output, $returnCode);
            $available = 0 === $returnCode;
        }

        \rex_config::set('mediaplace', 'ffmpeg_binary_check', ['checked_at' => time(), 'available' => $available]);

        return $available;
    }

    public static function isSupportedVideo(string $filename): bool
    {
        if ('' === $filename) {
            return false;
        }
        $extension = strtolower(\rex_file::extension($filename));

        return \in_array($extension, self::SUPPORTED_EXTENSIONS, true);
    }

    /**
     * @return list<string>
     */
    public static function supportedExtensions(): array
    {
        return self::SUPPORTED_EXTENSIONS;
    }

    /**
     * Steuert die Sichtbarkeit des "Video optimieren"-Buttons im Detail-Panel.
     */
    public static function canOptimize(string $filename): bool
    {
        if (!self::isAvailable() || !self::isSupportedVideo($filename) || !self::hasJobEngineV2()) {
            return false;
        }
        $user = \rex::getUser();

        return $user instanceof \rex_user && $user->hasPerm('mediaplace[optimize_video]');
    }

    /**
     * Converter::startOverwriteJob()/pollJob()/getActiveJobInfo() sowie
     * Job\OptimizedVideoRegistry gibt es erst seit der globalen Job-Engine-
     * Neufassung in ffmpeg 4.5.0/4.5.1 (siehe dortiges CHANGELOG) -- aeltere,
     * weiterhin lauffaehige ffmpeg-Installationen (Vorschau funktioniert dort
     * unveraendert weiter, siehe isAvailable()) wuerden bei jedem dieser
     * Aufrufe mit "Call to undefined method" abstuerzen. Deshalb eigener,
     * gezielter Versions-Check statt eines Ausbaus von isAvailable() selbst
     * -- die Vorschau-Faehigkeit soll auf alten ffmpeg-Versionen weiterhin
     * funktionieren, nur das "Optimieren"-Feature muss dort ausgeblendet
     * bleiben.
     */
    private static function hasJobEngineV2(): bool
    {
        return version_compare((string) (\rex_addon::get('ffmpeg')->getVersion() ?: '0'), '4.5.0', '>=');
    }

    /**
     * Video-Vorschau ist optional (Einstellungen) und wahlweise animiert oder
     * ein einzelnes Standbild -- "off" zeigt konsequent nur das Datei-Icon
     * (kein Media-Manager-Typ wird dafuer angefordert). Default "animated"
     * erhaelt das bisherige Verhalten bestehender Installationen.
     */
    public static function getVideoThumbMode(): string
    {
        $mode = (string) \rex_addon::get('mediaplace')->getConfig('video_thumb_mode', 'animated');

        return \in_array($mode, ['off', 'static', 'animated'], true) ? $mode : 'animated';
    }

    /**
     * Media-Manager-Typ-Name, der fuer die aktuell eingestellte Vorschau-Art
     * (getVideoThumbMode()) verwendet werden soll -- null bei "off" (dann
     * wird client-seitig gar kein Typ angefordert, siehe previewHtml() in
     * mediaplace.js).
     */
    public static function getActiveVideoThumbType(): ?string
    {
        return match (self::getVideoThumbMode()) {
            'off' => null,
            'static' => self::VIDEO_THUMB_TYPE_STATIC,
            default => self::VIDEO_THUMB_TYPE,
        };
    }

    /**
     * Legt BEIDE Video-Thumbnail-Typen an, falls sie noch fehlen (z.B. weil
     * ffmpeg erst nach MediaPlace installiert wurde, oder der Modus in den
     * Einstellungen erst spaeter umgestellt wird) -- idempotent, billige
     * Existenzpruefung, aufrufbar bei jedem Boot ohne spuerbare Kosten. Immer
     * beide anlegen (unabhaengig vom aktuell gewaehlten Modus), damit ein
     * Umschalten der Einstellung sofort wirkt statt erst beim naechsten Boot.
     */
    public static function ensureVideoThumbTypes(): void
    {
        if (!self::isAvailable()) {
            return;
        }

        self::ensureVideoThumbTypeVariant(
            self::VIDEO_THUMB_TYPE,
            'MediaPlace – animierte Video-Vorschau im Grid (ffmpeg)',
            true,
        );
        self::ensureVideoThumbTypeVariant(
            self::VIDEO_THUMB_TYPE_STATIC,
            'MediaPlace – Video-Vorschau im Grid, einzelnes Standbild (ffmpeg)',
            false,
        );
    }

    private static function ensureVideoThumbTypeVariant(string $typeName, string $description, bool $animated): int
    {
        $typeSql = \rex_sql::factory();
        $existingType = $typeSql->getArray(
            'SELECT id FROM ' . \rex::getTable('media_manager_type') . ' WHERE name = :name',
            [':name' => $typeName],
        );
        $now = date('Y-m-d H:i:s');

        if (empty($existingType)) {
            $typeSql->setTable(\rex::getTable('media_manager_type'));
            $typeSql->setValue('status', 1);
            $typeSql->setValue('name', $typeName);
            $typeSql->setValue('description', $description);
            $typeSql->setValue('createdate', $now);
            $typeSql->setValue('createuser', 'mediaplace');
            $typeSql->setValue('updatedate', $now);
            $typeSql->setValue('updateuser', 'mediaplace');
            $typeSql->insert();
            $typeId = (int) $typeSql->getLastId();
        } else {
            $typeId = (int) $existingType[0]['id'];
        }

        $effectSql = \rex_sql::factory();
        $existingEffect = $effectSql->getArray(
            'SELECT id FROM ' . \rex::getTable('media_manager_type_effect') . ' WHERE type_id = :type_id AND effect = :effect',
            [':type_id' => $typeId, ':effect' => 'video_to_webp'],
        );
        if (empty($existingEffect)) {
            $effectSql->setTable(\rex::getTable('media_manager_type_effect'));
            $effectSql->setValue('type_id', $typeId);
            $effectSql->setValue('effect', 'video_to_webp');
            $effectSql->setValue('parameters', json_encode([
                'rex_effect_video_to_webp' => [
                    'rex_effect_video_to_webp_animated' => $animated ? '1' : '0',
                    'rex_effect_video_to_webp_position' => 'middle',
                    'rex_effect_video_to_webp_width' => '300',
                    'rex_effect_video_to_webp_compression_level' => '4',
                    'rex_effect_video_to_webp_fps' => '8',
                    'rex_effect_video_to_webp_snippet_length' => '2',
                ],
            ]));
            $effectSql->setValue('priority', 1);
            $effectSql->setValue('createdate', $now);
            $effectSql->setValue('createuser', 'mediaplace');
            $effectSql->setValue('updatedate', $now);
            $effectSql->setValue('updateuser', 'mediaplace');
            $effectSql->insert();
        }

        return $typeId;
    }

    /**
     * @return array<string, mixed>
     */
    public static function startOptimizeJob(string $filename, \rex_user $user): array
    {
        return \FriendsOfRedaxo\FFmpeg\Api\Converter::startOverwriteJob($filename, $user);
    }

    /**
     * @return array<string, mixed>
     */
    public static function pollOptimizeJob(string $jobId): array
    {
        return \FriendsOfRedaxo\FFmpeg\Api\Converter::pollJob($jobId);
    }

    /**
     * Laeuft GERADE ein Job (egal ob ueber MediaPlace oder ffmpeg's eigene
     * Video-Tools-Seite gestartet) fuer GENAU diese Datei? Ohne das zeigte das
     * Detail-Panel beim (Wieder-)Oeffnen keinen Status, solange der Job nicht
     * in DERSELBEN Browser-Session gestartet wurde -- der Job ist seit dem
     * Redesign zwar global (siehe Converter::getActiveJobInfo()), aber ohne
     * diesen Abgleich beim Rendern wusste das Detail-Panel selbst nichts
     * davon, bis erneut auf "optimieren" geklickt wurde.
     *
     * @return array{id: string, progress: int, status: string}|null
     */
    public static function getActiveJobForFile(string $filename): ?array
    {
        if (!self::isAvailable() || !self::hasJobEngineV2()) {
            return null;
        }
        $job = \FriendsOfRedaxo\FFmpeg\Api\Converter::getActiveJobInfo();
        if (!$job || $job['video'] !== $filename) {
            return null;
        }

        return ['id' => $job['id'], 'progress' => $job['progress'], 'status' => $job['status']];
    }

    /**
     * Wurde diese Datei bereits per 'overwrite'-Modus optimiert (und seitdem
     * nicht erneut veraendert)? Nutzt ffmpeg's OptimizedVideoRegistry direkt
     * (Fingerabdruck aus Dateigroesse+updatedate, siehe dortiger
     * Klassenkommentar) -- ohne diese Pruefung zeigte der "Video optimieren"-
     * Button im Detail-Panel nie an, dass eine Datei bereits optimiert wurde
     * (anders als ffmpeg's eigene Video-Tools-Seite, die das ueber dieselbe
     * Registry bereits anzeigt).
     *
     * @return array{originalSize: int, compressionRate: int}|null
     */
    public static function getOptimizedStatus(string $filename): ?array
    {
        if (!self::isAvailable() || !self::hasJobEngineV2()) {
            return null;
        }
        $media = \rex_media::get($filename);
        if (!$media) {
            return null;
        }

        $fingerprint = \FriendsOfRedaxo\FFmpeg\Job\OptimizedVideoRegistry::getIfCurrent($filename, $media->getSize(), $media->getUpdateDate());
        if (!$fingerprint) {
            return null;
        }

        $compressionRate = $fingerprint['originalSize'] > 0
            ? (int) round(100 - (($media->getSize() / $fingerprint['originalSize']) * 100))
            : 0;

        return ['originalSize' => $fingerprint['originalSize'], 'compressionRate' => $compressionRate];
    }

    /**
     * Aufbereitete Video-Technikdaten (Aufloesung/Dauer/Codec/Bitrate/...)
     * ueber ffmpeg's bereits vorhandene VideoInfo-Klasse -- siehe
     * Api\VideoInfo.php. Keine eigene ffprobe-Logik hier,
     * nur Uebersetzung ins fuer den Client passende Format.
     *
     * @return array<string, mixed>|null
     */
    public static function getVideoDetails(string $filename): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }
        $info = \FriendsOfRedaxo\FFmpeg\VideoInfo::getInfo($filename);
        if (!$info) {
            return null;
        }

        return [
            'duration' => $info['duration_formatted'] ?? '',
            'format' => (string) ($info['format']['format_long_name'] ?? $info['format']['format_name'] ?? ''),
            'filesize' => $info['filesize_formatted'] ?? '',
            'bitrate' => $info['bitrate_formatted'] ?? '',
            'width' => (int) ($info['video']['width'] ?? 0),
            'height' => (int) ($info['video']['height'] ?? 0),
            'aspect_ratio' => $info['aspect_ratio'] ?? '',
            'framerate' => $info['framerate'] ?? 0,
            'video_codec' => (string) ($info['video']['codec_name'] ?? ''),
            'video_profile' => (string) ($info['video']['profile'] ?? ''),
            'audio_codec' => (string) ($info['audio']['codec_name'] ?? ''),
            'audio_samplerate' => (string) ($info['audio']['sample_rate'] ?? ''),
            'audio_channels' => (int) ($info['audio']['channels'] ?? 0),
        ];
    }
}
