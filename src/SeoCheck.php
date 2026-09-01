<?php

namespace Azuriom\Plugin\Seo;

/**
 * Looks for the handful of on-page problems search engines actually complain
 * about, in HTML that has already been fetched for the URL check.
 *
 * ponytail: regular expressions instead of a DOM parser. These four checks only
 * need to count tags and measure a string; pulling in a parser for that would
 * cost more than it returns. If this ever needs to understand nesting, swap in
 * DOMDocument.
 */
class SeoCheck
{
    /**
     * Google and Bing cut descriptions off around here.
     */
    private const DESCRIPTION_MIN = 50;

    private const DESCRIPTION_MAX = 160;

    /**
     * A title longer than this gets truncated in the result list.
     */
    private const TITLE_MAX = 60;

    /**
     * @return array<int, array{key: string, count?: int}> issue keys with a
     *                                                    translation-ready payload
     */
    public static function issues(string $html): array
    {
        $issues = [];

        $headings = preg_match_all('/<h1[\s>]/i', $html);

        if ($headings === 0) {
            $issues[] = ['key' => 'h1-missing'];
        } elseif ($headings > 1) {
            $issues[] = ['key' => 'h1-multiple', 'count' => $headings];
        }

        $description = self::attribute($html, 'description');

        if ($description === null || trim($description) === '') {
            $issues[] = ['key' => 'description-missing'];
        } else {
            $length = mb_strlen(html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($length < self::DESCRIPTION_MIN) {
                $issues[] = ['key' => 'description-short', 'count' => $length];
            } elseif ($length > self::DESCRIPTION_MAX) {
                $issues[] = ['key' => 'description-long', 'count' => $length];
            }
        }

        // Not a regex of its own: title() already strips tags and trims, so a
        // <title>   </title> comes back as an empty string rather than matching
        // the closing tag.
        $title = self::title($html);

        if ($title === null || $title === '') {
            $issues[] = ['key' => 'title-missing'];
        }

        $withoutAlt = 0;

        foreach (self::matchAll('/<img\b[^>]*>/i', $html) as $tag) {
            // A missing alt and an empty one both count: alt="" tells a screen
            // reader "decorative", which is wrong for a logo that is the only
            // content of a link.
            $hasAlt = preg_match('/\balt\s*=\s*(["\'])(.*?)\1/is', $tag, $matches)
                && trim($matches[2]) !== '';

            if (! $hasAlt) {
                $withoutAlt++;
            }
        }

        if ($withoutAlt > 0) {
            $issues[] = ['key' => 'images-without-alt', 'count' => $withoutAlt];
        }

        return $issues;
    }

    /**
     * The page title, for showing next to a result.
     */
    public static function title(string $html): ?string
    {
        if (! preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return null;
        }

        $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return mb_strlen($title) > self::TITLE_MAX
            ? mb_substr($title, 0, self::TITLE_MAX).'…'
            : $title;
    }

    protected static function attribute(string $html, string $name): ?string
    {
        $pattern = '/<meta[^>]*name=(["\'])'.preg_quote($name, '/').'\1[^>]*content=(["\'])(.*?)\2/is';

        return preg_match($pattern, $html, $matches) ? $matches[3] : null;
    }

    /**
     * @return array<int, string>
     */
    protected static function matchAll(string $pattern, string $subject): array
    {
        preg_match_all($pattern, $subject, $matches);

        return $matches[0] ?? [];
    }
}
