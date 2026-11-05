<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use rex_api_function;
use rex_api_result;

/**
 * MediaPlace – "Nur unbenutzte Medien"-Filter.
 *
 * Prueft fuer eine gegebene Liste von Dateinamen (typischerweise eine
 * bereits geladene Seite der normalen Medienliste), welche davon aktuell
 * NICHT verwendet werden (rex_mediapool::mediaIsInUse()). Bewusst kein
 * Vorab-Scan des gesamten Medienpools: mediaIsInUse() ist pro Datei ein
 * eigener Table-Scan auf rex_article_slice, ein Massen-Check ueber den
 * kompletten Bestand waere fuer groessere Installationen zu teuer. Der
 * Client (loadFiles() in mediaplace.js) ruft diesen Endpunkt deshalb nur
 * pro bereits geladener Seite auf, nicht fuer den ganzen Pool auf einmal.
 *
 * Zusaetzlich zur normalen Medien-Berechtigung greift hier ein eigenes,
 * granulares Recht (MediaPermission::hasUnusedFilterAccess(),
 * rex_perm::register() in boot.php) -- der Filter soll nicht automatisch
 * jedem mit allgemeinem Medienzugriff zur Verfuegung stehen.
 *
 * GET /api/backend/mediaplace_unused?filenames=a.jpg,b.png,...
 */
class Unused extends rex_api_function
{
    /** Schutz gegen versehentlich/absichtlich ueberlange Anfragen -- der
     * Client schickt normalerweise nur eine einzelne Seitengroesse (siehe
     * mediaPerPage in mediaplace.js, max. 250). */
    private const MAX_FILENAMES = 300;

    public function execute(): rex_api_result
    {
        \rex_response::cleanOutputBuffers();

        if (!\rex::getUser()) {
            \rex_response::setStatus(\rex_response::HTTP_UNAUTHORIZED);
            \rex_response::sendJson(['error' => 'Unauthorized']);
            exit;
        }

        if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasUnusedFilterAccess()) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

        $filenamesRaw = rex_request('filenames', 'string', '');
        $filenames = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $filenamesRaw)),
            static fn (string $v): bool => '' !== $v,
        )));

        if (count($filenames) > self::MAX_FILENAMES) {
            $filenames = array_slice($filenames, 0, self::MAX_FILENAMES);
        }

        $unused = [];
        foreach ($filenames as $filename) {
            $media = \rex_media::get($filename);
            if (!$media) {
                continue;
            }
            // Nur Dateien aus Kategorien pruefen, auf die der Nutzer auch
            // sonst Zugriff haette -- gleiche Absicherung wie bei den
            // anderen eigenen Endpunkten.
            if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasCategoryAccess($media->getCategoryId())) {
                continue;
            }
            if (false === \rex_mediapool::mediaIsInUse($filename)) {
                $unused[] = $filename;
            }
        }

        \rex_response::sendJson([
            'success' => true,
            'unused' => $unused,
        ]);
        exit;
    }
}
