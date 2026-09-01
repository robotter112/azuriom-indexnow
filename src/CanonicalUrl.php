<?php

namespace Azuriom\Plugin\Sitemap;

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
        $teile = parse_url($url);

        if ($teile === false || ! isset($teile['host'])) {
            return $url;
        }

        $basis = ($teile['scheme'] ?? 'https').'://'.$teile['host']
            .(isset($teile['port']) ? ':'.$teile['port'] : '')
            .($teile['path'] ?? '/');

        // A trailing slash on a sub path makes the same page look like a second
        // address; the root keeps its slash because "https://example.com" alone
        // is not a path.
        $pfad = parse_url($basis, PHP_URL_PATH);

        if ($pfad !== null && $pfad !== '/' && str_ends_with($basis, '/')) {
            $basis = rtrim($basis, '/');
        }

        if (! isset($teile['query']) || $kept === []) {
            return $basis;
        }

        parse_str($teile['query'], $parameter);

        $behalten = array_intersect_key($parameter, array_flip($kept));

        if ($behalten === []) {
            return $basis;
        }

        // Sorted, so ?page=2&sort=x and ?sort=x&page=2 do not become two
        // different canonicals.
        ksort($behalten);

        return $basis.'?'.http_build_query($behalten);
    }
}
