<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use rex_api_function;
use rex_api_result;

/**
 * Gesamt-Speicherverbrauch des KOMPLETTEN Medienpools (Zahnrad-Menue) --
 * bewusst NICHT nach Kategorie-Rechten gefiltert, "kompletter
 * Speicherverbrauch des Medienpools" ist eine globale Zahl, keine
 * "was ich sehen darf"-Teilsumme. Reine Aggregation der bereits in
 * rex_media gepflegten filesize-Spalte -- kein Dateisystem-Scan noetig,
 * REDAXO haelt diese Spalte ohnehin synchron (siehe rex_media_service).
 */
class StorageUsage extends rex_api_function
{
    public function execute(): rex_api_result
    {
        \rex_response::cleanOutputBuffers();

        if (!\rex::getUser()) {
            \rex_response::setStatus(\rex_response::HTTP_UNAUTHORIZED);
            \rex_response::sendJson(['error' => 'Unauthorized']);
            exit;
        }

        if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasMediaAccess()) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

        $sql = \rex_sql::factory();
        $row = $sql->getArray('SELECT SUM(filesize) AS total_size, COUNT(*) AS total_count FROM ' . \rex::getTable('media'));

        \rex_response::sendJson([
            'success' => true,
            'total_size' => (int) ($row[0]['total_size'] ?? 0),
            'total_count' => (int) ($row[0]['total_count'] ?? 0),
        ]);
        exit;
    }
}
