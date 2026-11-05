<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Optionale Zusatz-Schnittstelle zu StorageProviderInterface: Provider, die
 * rohe, volle Datei-Bytes liefern koennen (nicht nur ein Thumbnail und nicht
 * nur "importiere als neue lokale Datei"), implementieren zusaetzlich dieses
 * Interface. Bewusst NICHT Teil von StorageProviderInterface selbst -- eine
 * neue Pflichtmethode dort waere ein Breaking Change fuer jeden bestehenden
 * Implementierer (auch das aeltere, eigenstaendige nextcloud-Addon, das
 * keine unabhaengige "nur Bytes"-Methode hat). MediaPlace prueft
 * `instanceof StorageProviderContentInterface`, bevor es eine Aktion
 * anbietet, die volle Datei-Bytes braucht (aktuell: "Datei aus Cloud
 * ersetzen", siehe Api\Provider::handleReplace()) -- Provider ohne dieses
 * Interface bieten diese Aktion einfach nicht an, kein Fehlerzustand.
 */
interface StorageProviderContentInterface
{
    /**
     * Rohe, volle Datei-Bytes der Quelldatei unter $path (kein Thumbnail).
     * Wirft bei Fehlschlag eine Exception mit einer fuer den Client
     * verwertbaren Fehlermeldung.
     */
    public function getContent(string $path): string;
}
