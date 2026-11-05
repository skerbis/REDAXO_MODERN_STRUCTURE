<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use rex_api_function;
use rex_api_result;

/**
 * MediaPlace - system tags API.
 */
class Tags extends rex_api_function
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

        $method = \rex_request::server('REQUEST_METHOD', 'string', 'GET');
        $filename = \rex_request('filename', 'string', '');
        $filenamesRaw = \rex_request('filenames', 'string', '');

        try {
            if ('GET' === $method) {
                $catalog = \FriendsOfRedaxo\Mediaplace\SystemTagManager::getCatalog();
                $tags = [];
                $fileTags = [];

                if ('' !== $filenamesRaw) {
                    $filenames = array_values(array_filter(array_map('trim', explode(',', $filenamesRaw)), static fn (string $v): bool => '' !== $v));
                    if ([] !== $filenames) {
                        $fileTags = \FriendsOfRedaxo\Mediaplace\SystemTagManager::getTagsForFilenames($filenames);
                    }
                }

                if ('' !== $filename) {
                    $media = \rex_media::get($filename);
                    if (!$media) {
                        \rex_response::setStatus(\rex_response::HTTP_NOT_FOUND);
                        \rex_response::sendJson(['error' => 'Media not found']);
                        exit;
                    }

                    if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasCategoryAccess($media->getCategoryId())) {
                        \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
                        \rex_response::sendJson(['error' => 'Permission denied']);
                        exit;
                    }

                    $tags = \FriendsOfRedaxo\Mediaplace\SystemTagManager::getTagsForFilename($filename);
                }

                \rex_response::sendJson([
                    'success' => true,
                    'catalog' => $catalog,
                    'tags' => $tags,
                    'file_tags' => $fileTags,
                    // Global (nicht auf die aktuell geladene Kategorie beschraenkt),
                    // respektiert aber die Kategorie-Rechte des Users -- siehe
                    // SystemTagManager::getCollectionCounts()/getTagCounts().
                    'collection_counts' => \FriendsOfRedaxo\Mediaplace\SystemTagManager::getCollectionCounts(
                        \FriendsOfRedaxo\Mediaplace\MediaPermission::getAccessibleCategoryIds(),
                    ),
                    // Nur tatsaechlich zugewiesene Tags sollen im Sidebar-Filter
                    // erscheinen, siehe updateTagFilterOptions() im Client.
                    'tag_counts' => \FriendsOfRedaxo\Mediaplace\SystemTagManager::getTagCounts(
                        \FriendsOfRedaxo\Mediaplace\MediaPermission::getAccessibleCategoryIds(),
                    ),
                ]);
                exit;
            }

            if ('POST' === $method || 'PATCH' === $method) {
                $input = $this->getJsonInput();

                $action = trim((string) ($input['action'] ?? ''));
                if ('' !== $action) {
                    // Sammlungs-Katalog ist kategorieuebergreifend -> volles Medienrecht noetig.
                    if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasFullAccess()) {
                        \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
                        \rex_response::sendJson(['error' => 'Permission denied']);
                        exit;
                    }

                    if ('collection_create' === $action) {
                        $name = trim((string) ($input['name'] ?? ''));
                        $color = trim((string) ($input['color'] ?? '#4a90d9'));
                        if ('' === $name) {
                            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
                            \rex_response::sendJson(['error' => 'Missing collection name']);
                            exit;
                        }

                        \FriendsOfRedaxo\Mediaplace\SystemTagManager::ensureCatalogTag($name, $color);

                        \rex_response::sendJson([
                            'success' => true,
                            'catalog' => \FriendsOfRedaxo\Mediaplace\SystemTagManager::getCatalog(),
                            'affected_files' => 0,
                        ]);
                        exit;
                    }

                    if ('collection_rename' === $action) {
                        $oldName = trim((string) ($input['old_name'] ?? ''));
                        $newName = trim((string) ($input['new_name'] ?? ''));
                        if ('' === $oldName || '' === $newName) {
                            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
                            \rex_response::sendJson(['error' => 'Missing old_name/new_name']);
                            exit;
                        }

                        $affected = \FriendsOfRedaxo\Mediaplace\SystemTagManager::renameCatalogTag($oldName, $newName);

                        \rex_response::sendJson([
                            'success' => true,
                            'catalog' => \FriendsOfRedaxo\Mediaplace\SystemTagManager::getCatalog(),
                            'affected_files' => $affected,
                        ]);
                        exit;
                    }

                    if ('collection_delete' === $action) {
                        $name = trim((string) ($input['name'] ?? ''));
                        if ('' === $name) {
                            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
                            \rex_response::sendJson(['error' => 'Missing name']);
                            exit;
                        }

                        $affected = \FriendsOfRedaxo\Mediaplace\SystemTagManager::deleteCatalogTag($name);

                        \rex_response::sendJson([
                            'success' => true,
                            'catalog' => \FriendsOfRedaxo\Mediaplace\SystemTagManager::getCatalog(),
                            'affected_files' => $affected,
                        ]);
                        exit;
                    }

                    \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
                    \rex_response::sendJson(['error' => 'Unknown action']);
                    exit;
                }

                if ('' === $filename) {
                    \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
                    \rex_response::sendJson(['error' => 'Missing filename']);
                    exit;
                }

                $media = \rex_media::get($filename);
                if (!$media) {
                    \rex_response::setStatus(\rex_response::HTTP_NOT_FOUND);
                    \rex_response::sendJson(['error' => 'Media not found']);
                    exit;
                }

                if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasCategoryAccess($media->getCategoryId())) {
                    \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
                    \rex_response::sendJson(['error' => 'Permission denied']);
                    exit;
                }

                $tags = is_array($input['tags'] ?? null) ? $input['tags'] : [];

                \FriendsOfRedaxo\Mediaplace\SystemTagManager::saveTagsForFilename($filename, $tags);

                \rex_response::sendJson([
                    'success' => true,
                    'catalog' => \FriendsOfRedaxo\Mediaplace\SystemTagManager::getCatalog(),
                    'tags' => \FriendsOfRedaxo\Mediaplace\SystemTagManager::getTagsForFilename($filename),
                ]);
                exit;
            }

            \rex_response::setStatus(405);
            \rex_response::sendJson(['error' => 'Method not allowed']);
            exit;
        } catch (\Throwable $e) {
            \rex_response::setStatus(\rex_response::HTTP_INTERNAL_ERROR);
            \rex_response::sendJson(['error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $decoded = json_decode($input, true);
        return is_array($decoded) ? $decoded : [];
    }
}
