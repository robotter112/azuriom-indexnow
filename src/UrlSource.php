<?php

namespace Azuriom\Plugin\Indexnow;

use Illuminate\Support\Facades\Http;

/**
 * Where the URLs to submit come from.
 *
 * Deliberately the site's own sitemap.xml rather than a second crawl of the
 * database: whichever plugin produces that file has already decided what is
 * public and worth indexing, and duplicating that decision here would mean two
 * answers to the same question.
 */
class UrlSource
{
    /**
     * Read the sitemap and return its URLs.
     *
     * @return array<int, string>
     */
    public static function fromSitemap(string $sitemapUrl): array
    {
        try {
            $response = Http::timeout(20)->get($sitemapUrl);
        } catch (\Throwable $e) {
            return [];
        }

        if ($response->status() !== 200) {
            return [];
        }

        return self::parse($response->body());
    }

    /**
     * @return array<int, string>
     */
    public static function parse(string $xml): array
    {
        // A sitemap index points at further sitemaps rather than at pages;
        // following those is out of scope, so it yields nothing rather than
        // submitting the sitemap files themselves as if they were pages.
        if (preg_match('/<sitemapindex[\s>]/i', $xml)) {
            return [];
        }

        preg_match_all('/<loc>\s*([^<\s]+)\s*<\/loc>/i', $xml, $matches);

        return array_values(array_unique(array_map(
            fn ($url) => html_entity_decode($url, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            $matches[1] ?? []
        )));
    }

    /**
     * The address the sitemap is expected at.
     */
    public static function defaultSitemapUrl(): string
    {
        return url('/sitemap.xml');
    }
}
