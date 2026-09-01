<?php

/**
 * Self-check for CanonicalUrl:
 *
 *     php tests/CanonicalUrlTest.php
 *
 * A wrong canonical is worse than none: it tells a search engine that a page is
 * a copy of another one, and the page drops out of the index.
 */

require __DIR__.'/../src/CanonicalUrl.php';

use Azuriom\Plugin\Seo\CanonicalUrl;

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
    echo "        expected: ".var_export($expected, true)."\n";
    echo "        actual:   ".var_export($actual, true)."\n";
}

check('URL without parameters is unchanged',
    'https://example.com/news',
    CanonicalUrl::build('https://example.com/news'));

check('home page keeps its trailing slash',
    'https://example.com/',
    CanonicalUrl::build('https://example.com/'));

check('tracking parameter is dropped',
    'https://example.com/',
    CanonicalUrl::build('https://example.com/?ref=abc123'));

check('cache buster is dropped',
    'https://example.com/news',
    CanonicalUrl::build('https://example.com/news?cb=99'));

check('utm parameters are dropped',
    'https://example.com/wiki/faq',
    CanonicalUrl::build('https://example.com/wiki/faq?utm_source=x&utm_medium=y'));

check('page number is kept',
    'https://example.com/forum?page=2',
    CanonicalUrl::build('https://example.com/forum?page=2'));

check('page number kept, rest dropped',
    'https://example.com/forum?page=3',
    CanonicalUrl::build('https://example.com/forum?page=3&ref=abc&utm_source=x'));

check('parameter order does not matter',
    CanonicalUrl::build('https://example.com/a?page=2&sort=x', ['page', 'sort']),
    CanonicalUrl::build('https://example.com/a?sort=x&page=2', ['page', 'sort']));

check('trailing slash on a sub path is dropped',
    'https://example.com/news',
    CanonicalUrl::build('https://example.com/news/'));

check('port is kept',
    'https://example.com:8443/news',
    CanonicalUrl::build('https://example.com:8443/news?ref=a'));

check('http stays http',
    'http://example.com/news',
    CanonicalUrl::build('http://example.com/news'));

check('empty allow list drops every parameter',
    'https://example.com/forum',
    CanonicalUrl::build('https://example.com/forum?page=2', []));

check('nonsense is passed through unchanged',
    'not-a-url',
    CanonicalUrl::build('not-a-url'));

echo "\n";

if ($failures > 0) {
    echo "{$failures} check(s) failed.\n";
    exit(1);
}

echo "All checks passed.\n";
