<?php

namespace Azuriom\Plugin\Indexnow;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
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
        try {
            // A key file is a plain file at the document root: a redirect only
            // ever leads somewhere else, so it counts as a wrong answer.
            $response = Http::withoutRedirecting()->timeout(15)->withOptions(['stream' => true])->get($url);
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => 'unreachable'];
        }

        if ($response->status() !== 200) {
            return ['ok' => false, 'reason' => 'status', 'status' => $response->status()];
        }

        // A key file holds the key and perhaps a line break; anything much longer is not one.
        return trim((string) self::bodyUpTo($response, 1024)) === $key
            ? ['ok' => true, 'reason' => 'ok']
            : ['ok' => false, 'reason' => 'content'];
    }

    /**
     * The body of an answer requested with ['stream' => true], or null when it is longer than
     * $max bytes. Reading stops at the limit, so neither a huge file nor a small compressed
     * "bomb" can fill the memory before the size is known.
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
     * Report a single address. Used by the automatic reporting, where failure
     * must never disturb the save that triggered it.
     *
     * @return array{ok: bool, reason: string, status: int, count: int}
     */
    public static function submitOne(string $url): array
    {
        return self::submitOwn([$url]);
    }

    /**
     * Report addresses handed over by other plugins. Only addresses of this
     * site go out: IndexNow rejects a list for a host the key does not prove.
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
     * The site's own address (scheme, host, port and the folder Azuriom lives in), never
     * taken from the request that happens to run: its Host header, a /index.php in the
     * path or an X-Forwarded-Proto header are chosen by whoever sends it, and what is
     * built from them here ends up in caches for everyone and in reports to IndexNow.
     *
     * It is APP_URL. The address the key file was verified at when IndexNow was switched
     * on (an admin's own request) only refines it: the same host and port, with the scheme
     * and folder the site really answers under. A stored address on another host, or one
     * that names a script, is not used: whoever may switch IndexNow on must not be able
     * to point the sitemap, the checks and the reports somewhere else.
     */
    public static function siteRoot(): string
    {
        $configured = rtrim((string) config('app.url'), '/');
        $stored = rtrim((string) setting('indexnow.site'), '/');

        return $stored !== '' && self::refines($stored, $configured) ? $stored : $configured;
    }

    /**
     * The address this request runs under, when it is a plain spelling of the site's own
     * address: no script in the path (/index.php, however it is encoded) and the host and
     * port of APP_URL. Null otherwise.
     */
    public static function requestRoot(): ?string
    {
        $root = rtrim(url('/'), '/');

        return request()->getBaseUrl() === request()->getBasePath() && self::refines($root, (string) config('app.url')) ? $root : null;
    }

    /**
     * Whether an address is the configured one with at most another scheme or folder:
     * same host, same port (or none on both), nothing encoded and no script in its path.
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
        $root = rtrim(url('/'), '/');
        $site = self::siteRoot();
        $under = fn (string $base) => $url === $base || str_starts_with($url, $base.'/') || str_starts_with($url, $base.'?');

        // Already there (this is applied more than once to an address): where the site lives in a
        // folder of the request's root, moving it again would add the folder twice.
        if ($root === $site || ! $under($root) || (str_starts_with($site, $root.'/') && $under($site))) {
            return $url;
        }

        return $site.substr($url, strlen($root));
    }

    /**
     * The last submission and the addresses accepted today, for the admin page. A cache that
     * can't be written must not stop the submission it describes.
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
     * Does this address belong to the site itself?
     *
     * IndexNow only accepts URLs of the submitting host and answers 422
     * otherwise, so a sitemap elsewhere is useless - and fetching an arbitrary
     * address on request would turn the setting into a way of making the server
     * call internal services on someone's behalf. Hence the port too: another
     * port of the site's own name is another service.
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
