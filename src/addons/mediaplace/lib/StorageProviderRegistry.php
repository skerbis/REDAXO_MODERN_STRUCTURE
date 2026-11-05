<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Registry fuer Cloud-Provider-Addons (siehe StorageProviderInterface).
 * Mirrort exakt das Registry-Muster von MetainfoWidget::getRegisteredTypes()
 * (MEDIAPLACE_WIDGET_TYPES) -- Erweiterungspunkt statt hartkodierter
 * Addon-Liste, damit MediaPlace neue Provider nie selbst kennen muss.
 *
 * Andere Addons registrieren einen Provider z.B. so (siehe nextcloud/boot.php
 * fuer das erste reale Beispiel):
 *
 *   rex_extension::register('MEDIAPLACE_STORAGE_PROVIDERS', function (rex_extension_point $ep) {
 *       $providers = $ep->getSubject();
 *       $providers['my_provider'] = [
 *           'label' => 'Mein Cloud-Speicher',
 *           'icon' => 'fa-solid fa-cloud',
 *           'perm' => 'my_addon[mediaplace_browse]', // EIGENES Recht, siehe getAvailableProviders()
 *           'class' => MyAddon\MyStorageProvider::class, // implements StorageProviderInterface
 *       ];
 *       return $providers;
 *   });
 */
class StorageProviderRegistry
{
    /**
     * @return array<string, array{label: string, icon: string, perm: string, class: class-string<StorageProviderInterface>}>
     */
    public static function getAllProviders(): array
    {
        $result = \rex_extension::registerPoint(new \rex_extension_point('MEDIAPLACE_STORAGE_PROVIDERS', []));

        return is_array($result) ? $result : [];
    }

    /**
     * Nur Provider, die der aktuelle User laut seinem EIGENEN, bei der
     * Registrierung deklarierten `perm` nutzen darf -- kein globaler
     * MediaPlace-weiter Schalter, jeder Provider bringt sein eigenes Recht mit
     * (bei nextcloud z.B. ein dediziertes nextcloud[mediaplace_browse], nicht
     * das breitere nextcloud[] der eigenstaendigen Nextcloud-Verwaltungsseite).
     *
     * @return array<string, array{label: string, icon: string, perm: string, class: class-string<StorageProviderInterface>}>
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

    public static function getInstance(string $providerId): ?StorageProviderInterface
    {
        $providers = self::getAvailableProviders();
        if (!isset($providers[$providerId])) {
            return null;
        }

        $class = $providers[$providerId]['class'];
        if (!class_exists($class)) {
            return null;
        }

        $instance = new $class();

        return $instance instanceof StorageProviderInterface ? $instance : null;
    }
}
