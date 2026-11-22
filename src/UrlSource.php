<?php

namespace Azuriom\Plugin\Indexnow;

use Illuminate\Support\Facades\Http;

/** The addresses to submit, taken from the sitemap of the site. */
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
        // Checked here too, where the request goes out.
        if (! Client::isOwnHost($sitemapUrl)) {
            return [];
        }

        // Redirects only within the site, size limited to MAX_BYTES.
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
        // A sitemap index is not followed.
        if (preg_match('/<sitemapindex[\s>]/i', $xml)) {
            return [];
        }

        // One match at a time, to save memory.
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
     * Every public address of this site, for sites without a sitemap.
     *
     * @return array<int, string>
     */
    public static function fromCore(): array
    {
        try {
            return SiteUrls::all();
        } catch (\Throwable $e) {
            // The home page alone is still worth sending.
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
