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
    /** The largest sitemap read, in bytes (decoded). */
    public const MAX_BYTES = 20 * 1024 * 1024;

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

        // Redirects only while they stay on the site: the check above covers the first
        // address alone. A sitemap may hold at most 50,000 addresses; an answer beyond
        // MAX_BYTES is not one worth reading.
        $answer = Client::fetch($sitemapUrl, self::MAX_BYTES, 20, [], [
            'max' => 3,
            'protocols' => ['http', 'https'],
            'on_redirect' => function ($request, $response, $uri) {
                if (! Client::isOwnHost((string) $uri)) {
                    throw new \RuntimeException('The sitemap redirects off this site.');
                }
            },
        ]);

        return $answer !== null && $answer['status'] === 200 && $answer['body'] !== null ? self::parse($answer['body']) : [];
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

        // One address at a time, up to the 50,000 a sitemap may hold: matching the whole file
        // at once keeps every match in memory beside it.
        $urls = [];
        $offset = 0;

        while (count($urls) < 50000 && preg_match('/<loc>\s*([^<\s]+)\s*<\/loc>/i', $xml, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $urls[html_entity_decode($match[1][0], ENT_QUOTES | ENT_XML1, 'UTF-8')] = true;
            $offset = $match[0][1] + strlen($match[0][0]);
        }

        return array_map('strval', array_keys($urls));
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
            return [Client::siteRoot().'/'];
        }
    }

    /**
     * The address the sitemap is expected at.
     */
    public static function defaultSitemapUrl(): string
    {
        return Client::siteRoot().'/sitemap.xml';
    }
}
