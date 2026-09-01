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
        // Checked here as well as in the form: this is the point where the
        // request actually goes out, and a setting written by anything other
        // than that form would otherwise slip past.
        if (! Client::isOwnHost($sitemapUrl)) {
            return [];
        }

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
     * The URLs to submit: the sitemap if there is one, the site's own pages
     * otherwise.
     *
     * @return array{urls: array<int, string>, source: string}
     */
    public static function collect(string $sitemapUrl): array
    {
        $urls = self::fromSitemap($sitemapUrl);

        if ($urls !== []) {
            return ['urls' => $urls, 'source' => 'sitemap'];
        }

        return ['urls' => self::fromCore(), 'source' => 'core'];
    }

    /**
     * Every publicly reachable address of this site, for sites without a
     * sitemap.
     *
     * Gathered internally and handed straight to IndexNow, or served as the
     * optional sitemap. Never a second sitemap.xml alongside another plugin's.
     *
     * @return array<int, string>
     */
    public static function fromCore(): array
    {
        try {
            return SiteUrls::all();
        } catch (\Throwable $e) {
            // A missing table or a renamed route must not break the submission;
            // the home page alone is still worth sending.
            return [url('/')];
        }
    }

    /**
     * The address the sitemap is expected at.
     */
    public static function defaultSitemapUrl(): string
    {
        return url('/sitemap.xml');
    }
}
