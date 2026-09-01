<?php

namespace Azuriom\Plugin\Seo;

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
class IndexNow
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
            $antwort = Http::timeout(15)->get($url);
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => 'unreachable'];
        }

        if ($antwort->status() !== 200) {
            return ['ok' => false, 'reason' => 'status', 'status' => $antwort->status()];
        }

        return trim($antwort->body()) === $key
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
            $antwort = Http::timeout(30)->post(self::ENDPOINT, [
                'host' => $host,
                'key' => $key,
                'keyLocation' => $keyLocation,
                'urlList' => $urls,
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => 'unreachable', 'status' => 0, 'count' => count($urls)];
        }

        return [
            'ok' => in_array($antwort->status(), [200, 202], true),
            'reason' => self::reasonFor($antwort->status()),
            'status' => $antwort->status(),
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
     * The host an IndexNow submission is for - no scheme, no path.
     */
    public static function hostFor(string $url): string
    {
        return (string) Str::of(parse_url($url, PHP_URL_HOST) ?? '')->lower();
    }
}
