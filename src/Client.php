<?php

namespace Azuriom\Plugin\Indexnow;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * IndexNow tells participating search engines that URLs changed, instead of
 * waiting for them to come around. Bing, Yandex, Seznam and Naver take part;
 * Google does not.
 *
 * The protocol is a shared secret in reverse: a key file has to sit at the
 * document root and answer with the key itself. If it does not, submissions are
 * refused - which is why setup verifies the file over HTTP before writing
 * anything into the settings.
 */
class Client
{
    public const ENDPOINT = 'https://api.indexnow.org/indexnow';

    /**
     * The protocol allows 10 000 URLs per submission.
     */
    public const MAX_URLS = 10000;

    /**
     * Key charset and length are fixed by the specification: 8-128 hexadecimal
     * characters.
     */
    public static function generateKey(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function keyFileName(string $key): string
    {
        // The key ends up in public_path() and from there in file_put_contents()
        // and unlink(). Today it can only come from generateKey(), but this is
        // public API that other code may call, and a stored setting is not a
        // trustworthy source of a file path - "../../.env" would otherwise become
        // a write and a delete outside the document root.
        if (! preg_match('/^[a-f0-9]{8,128}$/i', $key)) {
            throw new \InvalidArgumentException('Invalid IndexNow key.');
        }

        return $key.'.txt';
    }

    /**
     * Check that the key file is reachable and contains exactly the key.
     *
     * @return array{ok: bool, reason: string, status?: int}
     */
    public static function verifyKeyFile(string $key, string $url): array
    {
        try {
            $response = Http::timeout(15)->get($url);
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => 'unreachable'];
        }

        if ($response->status() !== 200) {
            return ['ok' => false, 'reason' => 'status', 'status' => $response->status()];
        }

        return trim($response->body()) === $key
            ? ['ok' => true, 'reason' => 'ok']
            : ['ok' => false, 'reason' => 'content'];
    }

    /**
     * Submit URLs.
     *
     * @param  array<int, string>  $urls
     * @return array{ok: bool, reason: string, status: int, count: int}
     */
    public static function submit(string $host, string $key, string $keyLocation, array $urls): array
    {
        $urls = array_slice(array_values($urls), 0, self::MAX_URLS);

        if ($urls === []) {
            return ['ok' => false, 'reason' => 'empty', 'status' => 0, 'count' => 0];
        }

        try {
            $response = Http::timeout(30)->post(self::ENDPOINT, [
                'host' => $host,
                'key' => $key,
                'keyLocation' => $keyLocation,
                'urlList' => $urls,
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => 'unreachable', 'status' => 0, 'count' => count($urls)];
        }

        return [
            'ok' => in_array($response->status(), [200, 202], true),
            'reason' => self::reasonFor($response->status()),
            'status' => $response->status(),
            'count' => count($urls),
        ];
    }

    /**
     * Translate the status codes the specification defines into something a
     * site owner can act on.
     */
    public static function reasonFor(int $status): string
    {
        return match ($status) {
            200 => 'accepted',
            202 => 'pending',      // key not validated yet
            400 => 'invalid',      // malformed request
            403 => 'key-invalid',  // key file missing or wrong
            422 => 'mismatch',     // URLs do not belong to the host
            429 => 'too-many',
            default => 'unknown',
        };
    }

    /**
     * The public address of a saved record, or null if it has none.
     */
    public static function urlFor(object $record): ?string
    {
        try {
            if ($record instanceof \Azuriom\Models\Post) {
                return $record->published_at !== null && $record->published_at->isPast()
                    ? route('posts.show', $record->slug)
                    : null;
            }

            if ($record instanceof \Azuriom\Models\Page) {
                return $record->is_enabled && $record->roles()->count() === 0
                    ? route('pages.show', $record->slug)
                    : null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * Report a single address. Used by the automatic reporting, where failure
     * must never disturb the save that triggered it.
     *
     * @return array{ok: bool, reason: string, status: int, count: int}
     */
    public static function submitOne(string $url): array
    {
        $key = setting('indexnow.key');

        if (! $key) {
            return ['ok' => false, 'reason' => 'not-enabled', 'status' => 0, 'count' => 0];
        }

        return self::submit(
            self::hostFor($url),
            $key,
            url(self::keyFileName($key)),
            [$url]
        );
    }

    /**
     * The host an IndexNow submission is for - no scheme, no path.
     */
    public static function hostFor(string $url): string
    {
        return (string) Str::of(parse_url($url, PHP_URL_HOST) ?? '')->lower();
    }

    /**
     * Does this address belong to the site itself?
     *
     * IndexNow only accepts URLs of the submitting host and answers 422
     * otherwise, so a sitemap elsewhere is useless - and fetching an arbitrary
     * address on request would turn the setting into a way of making the server
     * call internal services on someone's behalf.
     */
    public static function isOwnHost(string $url, ?string $siteUrl = null): bool
    {
        $host = self::hostFor($url);

        return $host !== '' && $host === self::hostFor($siteUrl ?? url('/'));
    }
}
