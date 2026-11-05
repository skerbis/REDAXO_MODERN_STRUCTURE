<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use FriendsOfRedaxo\Mediaplace\MediaRename;
use rex_api_function;
use rex_api_result;
use rex_csrf_token;
use rex_i18n;
use rex_media;
use rex_response;
use RuntimeException;

/**
 * Dateien umbenennen (siehe MediaRename).
 *
 * func=check  Name prüfen, liefert den endgültigen Namen
 * func=start  (POST, CSRF) umbenennen, Antwort sofort, danach Verweise im selben Prozess ersetzen
 * func=status Fortschritt eines Jobs, arbeitet vorher kurz weiter
 * func=retry  (POST, CSRF) fehlgeschlagenen Job fortsetzen
 * func=tick   offene Jobs kurz weiterbearbeiten (jede Backend-Seite, solange etwas offen ist)
 */
class Rename extends rex_api_function
{
    protected $published = true;

    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();
        $user = \rex::getUser();
        if (!$user) {
            $this->json(['error' => 'Unauthorized'], rex_response::HTTP_UNAUTHORIZED);
        }

        $func = rex_request('func', 'string', '');
        if ('tick' === $func) {
            if (MediaRename::hasPending()) {
                if (PHP_SESSION_ACTIVE === session_status()) {
                    session_write_close();
                }
                MediaRename::work(4);
            }
            $this->json(['pending' => MediaRename::hasPending()]);
        }

        if (!MediaRename::canRename()) {
            $this->json(['error' => rex_i18n::msg('mediaplace_rename_error_perm')], rex_response::HTTP_FORBIDDEN);
        }

        if ('status' === $func) {
            $id = rex_request('id', 'int', 0);
            if (PHP_SESSION_ACTIVE === session_status()) {
                session_write_close();
            }
            MediaRename::work(3);
            $status = MediaRename::status($id);
            null === $status
                ? $this->json(['error' => 'Job not found'], rex_response::HTTP_NOT_FOUND)
                : $this->json($status);
        }

        $filename = rex_request('file', 'string', '');
        $media = '' !== $filename ? rex_media::get($filename) : null;

        if ('retry' === $func) {
            $this->requirePostWithToken();
            $id = rex_request('id', 'int', 0);
            MediaRename::retry($id);
            MediaRename::respondAndContinue(MediaRename::status($id) ?? ['error' => 'Job not found'], 60);
        }

        if (null === $media) {
            $this->json(['error' => rex_i18n::msg('mediaplace_rename_error_missing')], rex_response::HTTP_NOT_FOUND);
        }
        if (!MediaRename::canRename($media)) {
            $this->json(['error' => rex_i18n::msg('mediaplace_rename_error_perm')], rex_response::HTTP_FORBIDDEN);
        }

        $target = MediaRename::targetName($filename, rex_request('name', 'string', ''));
        $error = MediaRename::validate($filename, $target);

        if ('check' === $func) {
            $this->json(['target' => $target, 'error' => null !== $error ? rex_i18n::msg($error, $target) : null]);
        }

        if ('start' === $func) {
            $this->requirePostWithToken();
            if (null !== $error) {
                $this->json(['error' => rex_i18n::msg($error, $target)], rex_response::HTTP_BAD_REQUEST);
            }
            try {
                $id = MediaRename::start($filename, $target, $user);
            } catch (RuntimeException $e) {
                $this->json(['error' => rex_i18n::msg($e->getMessage(), $target)], rex_response::HTTP_BAD_REQUEST);
            }
            MediaRename::respondAndContinue(MediaRename::status($id) ?? [], 60);
        }

        $this->json(['error' => 'Unknown func'], rex_response::HTTP_BAD_REQUEST);
    }

    private function requirePostWithToken(): void
    {
        if ('post' !== rex_request_method() || !rex_csrf_token::factory(MediaRename::CSRF)->isValid()) {
            $this->json(['error' => rex_i18n::msg('csrf_token_invalid')], rex_response::HTTP_FORBIDDEN);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data, string $status = rex_response::HTTP_OK): never
    {
        rex_response::setStatus($status);
        rex_response::setHeader('Cache-Control', 'no-store');
        rex_response::sendJson($data);
        exit;
    }
}
