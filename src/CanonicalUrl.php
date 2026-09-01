<?php

namespace Azuriom\Plugin\Seo;

/**
 * Builds the canonical address for a request URL.
 *
 * The whole point is to fold away query parameters that produce the same page
 * under a different address - tracking parameters, session leftovers, cache
 * busters. Parameters that genuinely change the content must survive, or the
 * canonical would tell a search engine that page 2 is page 1 and page 2 would
 * drop out of the index entirely.
 */
class CanonicalUrl
{
    /**
     * Query parameters that change what the page shows and must be kept.
     */
    public const DEFAULT_KEPT = ['page'];

    /**
     * @param  array<int, string>  $kept  query parameters to preserve
     */
    public static function build(string $url, array $kept = self::DEFAULT_KEPT): string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return $url;
        }

        $base = ($parts['scheme'] ?? 'https').'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '/');

        // A trailing slash on a sub path makes the same page look like a second
        // address; the root keeps its slash because "https://example.com" alone
        // is not a path.
        $path = parse_url($base, PHP_URL_PATH);

        if ($path !== null && $path !== '/' && str_ends_with($base, '/')) {
            $base = rtrim($base, '/');
        }

        if (! isset($parts['query']) || $kept === []) {
            return $base;
        }

        parse_str($parts['query'], $parameter);

        $keptParams = array_intersect_key($parameter, array_flip($kept));

        if ($keptParams === []) {
            return $base;
        }

        // Sorted, so ?page=2&sort=x and ?sort=x&page=2 do not become two
        // different canonicals.
        ksort($keptParams);

        return $base.'?'.http_build_query($keptParams);
    }
}
