<?php

/**
 * Self-check for the sitemap reader:
 *
 *     php tests/UrlSourceTest.php
 */

if (! class_exists('Illuminate\Support\Facades\Http')) {
    eval('namespace Illuminate\Support\Facades; class Http {}');
}
if (! class_exists('Illuminate\Support\Str')) {
    eval('namespace Illuminate\Support; class Str { public static function of($v) { return new StrProxy($v); } }
    class StrProxy { public function __construct(private $v) {} public function lower() { return strtolower($this->v); } public function __toString() { return (string) $this->v; } }');
}

require __DIR__.'/../src/Client.php';
require __DIR__.'/../src/UrlSource.php';

use Azuriom\Plugin\Indexnow\UrlSource;

if (! function_exists('url')) {
    function url(string $path = '/'): string
    {
        return 'https://example.com'.$path;
    }
}

$failures = 0;

function check(string $name, $expected, $actual): void
{
    global $failures;

    if ($expected === $actual) {
        echo "  ok    {$name}\n";

        return;
    }

    $failures++;
    echo "  FAIL  {$name}\n";
    echo '        expected: '.var_export($expected, true)."\n";
    echo '        actual:   '.var_export($actual, true)."\n";
}

$sitemap = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>https://example.com/</loc><lastmod>2026-01-01T00:00:00+00:00</lastmod></url>
    <url><loc>https://example.com/news</loc></url>
    <url>
        <loc>
            https://example.com/wiki/page
        </loc>
    </url>
</urlset>';

check('reads every loc', [
    'https://example.com/',
    'https://example.com/news',
    'https://example.com/wiki/page',
], UrlSource::parse($sitemap));

check('whitespace around a loc is trimmed', true,
    in_array('https://example.com/wiki/page', UrlSource::parse($sitemap), true));

check('duplicates are removed', 1, count(UrlSource::parse(
    '<urlset><url><loc>https://example.com/a</loc></url><url><loc>https://example.com/a</loc></url></urlset>'
)));

check('entities are decoded', ['https://example.com/a?b=1&c=2'], UrlSource::parse(
    '<urlset><url><loc>https://example.com/a?b=1&amp;c=2</loc></url></urlset>'
));

// A sitemap index lists further sitemaps, not pages. Submitting those files as
// if they were pages would be wrong, so it yields nothing instead.
check('a sitemap index yields nothing', [], UrlSource::parse(
    '<sitemapindex><sitemap><loc>https://example.com/sitemap-1.xml</loc></sitemap></sitemapindex>'
));

check('empty input yields nothing', [], UrlSource::parse(''));
check('nonsense yields nothing', [], UrlSource::parse('not xml at all'));

// Security: a foreign address must not even be requested. fromSitemap() would
// otherwise need the network, so this checks the guard that runs before it.
check('a foreign sitemap is not fetched', [],
    UrlSource::fromSitemap('https://evil.example/sitemap.xml'));
check('a loopback sitemap is not fetched', [],
    UrlSource::fromSitemap('http://127.0.0.1:8123/sitemap.xml'));

echo "\n";

if ($failures > 0) {
    echo "{$failures} check(s) failed.\n";
    exit(1);
}

echo "All checks passed.\n";
