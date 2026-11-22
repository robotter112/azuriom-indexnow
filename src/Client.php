<?php

namespace Azuriom\Plugin\Indexnow;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Client of the IndexNow protocol: submits changed addresses. A key file at the document root proves the
 * site.
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
        // The key becomes a file name in public_path(): only the specified format, so no path can be
        // injected.
        if (! preg_match('/^[a-f0-9]{8,128}\z/i', $key)) {
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
        // A redirect is a wrong answer.
        $answer = self::fetch($url, 1024, 15);

        if ($answer === null) {
            return ['ok' => false, 'reason' => 'unreachable'];
        }

        if ($answer['status'] !== 200) {
            return ['ok' => false, 'reason' => 'status', 'status' => $answer['status']];
        }

        // A key file holds the key and at most a line break.
        return trim((string) $answer['body']) === $key
            ? ['ok' => true, 'reason' => 'ok']
            : ['ok' => false, 'reason' => 'content'];
    }

    /**
     * GET with a limit on size and time. cURL cuts off while receiving, since a stream without
     * allow_url_fopen would buffer the whole answer.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>|false  $redirects  Guzzle's allow_redirects
     * @return array{status: int, body: ?string}|null  null when there was no answer; body null when it was too long
     */
    public static function fetch(string $url, int $max, int $seconds, array $headers = [], array|false $redirects = false): ?array
    {
        $curl = extension_loaded('curl');
        $status = 0;
        $long = false;
        $options = ['allow_redirects' => $redirects, 'on_headers' => function ($response) use (&$status) {
            $status = $response->getStatusCode();
        }];

        if ($curl) {
            $headers += ['Accept-Encoding' => 'gzip'];
            $options['decode_content'] = false;
            $options['curl'] = [CURLOPT_NOPROGRESS => false, CURLOPT_XFERINFOFUNCTION => function ($handle, $total, $now) use ($max, &$long) {
                return ($long = $long || $now > $max) ? 1 : 0;
            }];
        } else {
            $options['stream'] = true;
        }

        try {
            $response = Http::timeout($seconds)->withHeaders($headers)->withOptions($options)->get($url);
        } catch (\Throwable $e) {
            // Too long: the status is kept, the body is not.
            return $long && $status > 0 ? ['status' => $status, 'body' => null] : null;
        }

        if (! $curl) {
            return ['status' => $response->status(), 'body' => self::bodyUpTo($response, $max)];
        }

        $body = $response->body();
        $encoding = strtolower(trim($response->header('Content-Encoding')));

        if (strlen($body) > $max || ! in_array($encoding, ['', 'identity', 'gzip'], true)) {
            $body = null;
        } elseif ($encoding === 'gzip') {
            $body = @gzdecode($body, $max);
            $body = $body === false ? null : $body;
        }

        return ['status' => $response->status(), 'body' => $body];
    }

    /**
     * The body of a streamed answer (see fetch()), or null when it is longer than $max bytes.
     * Reading stops at the limit.
     */
    public static function bodyUpTo(Response $response, int $max): ?string
    {
        $stream = $response->toPsrResponse()->getBody();
        $body = '';

        while (! $stream->eof() && strlen($body) <= $max) {
            $chunk = $stream->read(65536);

            if ($chunk === '') {
                break;
            }

            $body .= $chunk;
        }

        return strlen($body) <= $max ? $body : null;
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
            return self::remember(['ok' => false, 'reason' => 'unreachable', 'status' => 0, 'count' => count($urls)]);
        }

        return self::remember([
            'ok' => in_array($response->status(), [200, 202], true),
            'reason' => self::reasonFor($response->status()),
            'status' => $response->status(),
            'count' => count($urls),
        ]);
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
     * Report a single address. Never throws.
     *
     * @return array{ok: bool, reason: string, status: int, count: int}
     */
    public static function submitOne(string $url): array
    {
        return self::submitOwn([$url]);
    }

    /**
     * Report addresses handed over by other plugins, only those of this site.
     *
     * @param  array<int, mixed>  $urls
     * @return array{ok: bool, reason: string, status: int, count: int}
     */
    public static function submitOwn(array $urls): array
    {
        $key = setting('indexnow.key');

        if (! $key) {
            return ['ok' => false, 'reason' => 'not-enabled', 'status' => 0, 'count' => 0];
        }

        $urls = array_values(array_unique(array_filter(
            array_map(fn ($url) => is_string($url) ? self::onSiteRoot($url) : $url, $urls),
            fn ($url) => is_string($url) && self::isOwnHost($url)
        )));

        if ($urls === []) {
            return ['ok' => false, 'reason' => 'no-urls', 'status' => 0, 'count' => 0];
        }

        return self::submit(self::hostFor($urls[0]), $key, self::keyLocation($key), $urls);
    }

    /**
     * Where the key file is published: always on the site's own address.
     */
    public static function keyLocation(string $key): string
    {
        return self::siteRoot().'/'.self::keyFileName($key);
    }

    /**
     * The site's own address, from APP_URL and never from the request. The stored address may only refine
     * scheme and folder on the same host and port.
     */
    public static function siteRoot(): string
    {
        $configured = rtrim((string) config('app.url'), '/');
        $stored = rtrim((string) setting('indexnow.site'), '/');

        return $stored !== '' && self::refines($stored, $configured) ? $stored : $configured;
    }

    /** The root of this request if it is a plain spelling of the configured address, else null. */
    public static function requestRoot(): ?string
    {
        $root = rtrim(url('/'), '/');

        return request()->getBaseUrl() === request()->getBasePath() && self::refines($root, (string) config('app.url')) ? $root : null;
    }

    /**
     * Whether an address differs from the configured one in scheme or folder only, without encoding or a
     * script in its path.
     */
    private static function refines(string $root, string $configured): bool
    {
        $a = parse_url($root);
        $b = parse_url($configured);

        return isset($a['host'], $b['host'])
            && strtolower($a['host']) === strtolower($b['host'])
            && ($a['port'] ?? null) === ($b['port'] ?? null)
            && in_array(strtolower($a['scheme'] ?? ''), ['http', 'https'], true)
            && ! preg_match('/%|\.php/i', $a['path'] ?? '')
            && ! isset($a['query']) && ! isset($a['fragment']) && ! isset($a['user']);
    }

    /**
     * An address built with url() or route() in this request, moved onto siteRoot().
     */
    public static function onSiteRoot(string $url): string
    {
        // route() percent-encodes the brackets of an IPv6 host, url() does not.
        $url = (string) preg_replace('#^(https?://)%5B([0-9a-f:.]+)%5D#i', '$1[$2]', $url);
        $root = rtrim(url('/'), '/');
        $site = self::siteRoot();
        $under = fn (string $base) => $url === $base || str_starts_with($url, $base.'/') || str_starts_with($url, $base.'?');

        // Already moved: do not add the folder twice.
        if ($root === $site || ! $under($root) || (str_starts_with($site, $root.'/') && $under($site))) {
            return $url;
        }

        return $site.substr($url, strlen($root));
    }

    /**
     * The last submission and today's count for the admin page. A failing cache does not stop a
     * submission.
     */
    private static function remember(array $result): array
    {
        rescue(function () use ($result) {
            Cache::forever('indexnow.last', ['at' => time(), 'ok' => $result['ok'], 'reason' => $result['reason'], 'status' => $result['status'], 'count' => $result['count']]);

            if ($result['ok']) {
                $today = 'indexnow.today.'.now()->toDateString();
                Cache::add($today, 0, now()->addDays(2));
                Cache::increment($today, $result['count']);
            }
        }, null, false);

        return $result;
    }

    /**
     * @return array{at: int, ok: bool, reason: string, status: int, count: int}|null
     */
    public static function last(): ?array
    {
        return Cache::get('indexnow.last');
    }

    public static function today(): int
    {
        return (int) Cache::get('indexnow.today.'.now()->toDateString(), 0);
    }

    /**
     * The host an IndexNow submission is for - no scheme, no path.
     */
    public static function hostFor(string $url): string
    {
        return (string) Str::of(parse_url($url, PHP_URL_HOST) ?? '')->lower();
    }

    /**
     * Whether an address is on this site, port included. Also keeps the server from fetching internal
     * services.
     */
    public static function isOwnHost(string $url, ?string $siteUrl = null): bool
    {
        $siteUrl ??= self::siteRoot();
        $host = self::hostFor($url);

        return $host !== ''
            && $host === self::hostFor($siteUrl)
            && parse_url($url, PHP_URL_PORT) === parse_url($siteUrl, PHP_URL_PORT)
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
