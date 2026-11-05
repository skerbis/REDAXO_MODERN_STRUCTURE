<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Vertrag fuer "Cloud-Provider"-Addons (z.B. nextcloud), die sich als
 * zusaetzlicher, eigener Baum in der MediaPlace-Sidebar einklinken wollen --
 * rein lesendes Browsen/Suchen einer entfernten Quelle plus Import einzelner
 * Dateien in den lokalen Medienpool. Kein Sync: nach dem Import ist die Datei
 * eine ganz normale lokale rex_media-Zeile, keine fortlaufende Verknuepfung
 * zur Quelle.
 *
 * Registrierung ueber den Erweiterungspunkt MEDIAPLACE_STORAGE_PROVIDERS
 * (siehe StorageProviderRegistry), Instanziierung nur ueber
 * StorageProviderRegistry::getInstance() (Rechte-Check inklusive) -- $path
 * ist provider-intern (bei Nextcloud der WebDAV-Pfad), MediaPlace behandelt
 * ihn als opaken String und reicht ihn nur durch.
 */
interface StorageProviderInterface
{
    /**
     * Ordner + Dateien eines Pfads gemischt (type unterscheidet), oder
     * Suchtreffer wenn $search gesetzt ist (nur wenn hasSearch() true liefert).
     *
     * @return list<array{path: string, name: string, type: 'folder'|'file', filesize: int|null, filetype: string|null, modified: string|null, hasThumbnail: bool}>
     */
    public function listEntries(string $path, ?string $search = null): array;

    public function hasSearch(): bool;

    /**
     * null = kein Thumbnail verfuegbar -- Client zeigt stattdessen das
     * etablierte Datei-Icon-Fallback (gleiches Muster wie bei lokalen
     * Video-/Bild-Vorschaubildern).
     *
     * @return array{content: string, contentType: string}|null
     */
    public function getThumbnail(string $path): ?array;

    /**
     * Laedt die Datei von der Quelle und importiert sie (z.B. via
     * rex_media_service::addMedia()) in die lokale Kategorie $categoryId.
     * Wirft bei Fehlschlag eine Exception mit einer fuer den Client
     * verwertbaren Fehlermeldung.
     *
     * @return string Neuer lokaler Dateiname
     */
    public function importToMediaPool(string $path, int $categoryId): string;
}
