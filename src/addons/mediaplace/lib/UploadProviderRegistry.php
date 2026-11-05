<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Registry fuer Upload-Provider-Addons (z.B. filepond_uploader), die
 * MediaPlace's eingebauten Upload-Button/Drag&Drop durch ihren eigenen
 * Dialog ersetzen wollen. Mirrort StorageProviderRegistry, aber rein
 * clientseitig -- kein PHP-Interface/-Klasse noetig, die eigentliche
 * Uebernahme passiert komplett in JS (siehe MP3.registerUploadProvider()
 * in core.js). Anders als Storage-Provider (koexistieren alle gleichzeitig
 * als eigene Sidebar-Baeume) ist immer nur EIN Upload-Provider aktiv --
 * welcher, waehlt die eigene Einstellung "Upload-Anbieter" (pages/settings.php,
 * rex_config-Key upload_provider).
 *
 * Andere Addons registrieren einen Provider z.B. so (siehe filepond_uploader
 * fuer das erste reale Beispiel):
 *
 *   rex_extension::register('MEDIAPLACE_UPLOAD_PROVIDERS', function (rex_extension_point $ep) {
 *       $providers = $ep->getSubject();
 *       $providers['filepond'] = [
 *           'label' => 'FilePond',
 *           'perm' => 'filepond_uploader[mediaplace_upload]', // EIGENES Recht
 *       ];
 *       return $providers;
 *   });
 *
 * Auf der JS-Seite ruft dasselbe Addon dann MP3.registerUploadProvider('filepond', handler)
 * auf -- erst wenn BEIDES vorhanden ist (hier registriert UND als aktiver
 * Provider eingestellt UND clientseitig tatsaechlich registriert), delegiert
 * MediaPlace an den Handler.
 */
class UploadProviderRegistry
{
    /**
     * @return array<string, array{label: string, perm: string}>
     */
    public static function getAllProviders(): array
    {
        $result = \rex_extension::registerPoint(new \rex_extension_point('MEDIAPLACE_UPLOAD_PROVIDERS', []));

        return is_array($result) ? $result : [];
    }

    /**
     * Nur Provider, die der aktuelle User laut seinem EIGENEN, bei der
     * Registrierung deklarierten `perm` nutzen darf -- gleiches Muster wie
     * StorageProviderRegistry::getAvailableProviders().
     *
     * @return array<string, array{label: string, perm: string}>
     */
    public static function getAvailableProviders(): array
    {
        $user = \rex::getUser();
        if (!$user) {
            return [];
        }

        return array_filter(
            self::getAllProviders(),
            static fn (array $provider): bool => $user->hasPerm((string) ($provider['perm'] ?? '')),
        );
    }
}
