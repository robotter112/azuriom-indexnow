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

use Azuriom\Plugin\Sitemap\CanonicalUrl;

$fehler = 0;

function pruefe(string $name, $erwartet, $tatsaechlich): void
{
    global $fehler;

    if ($erwartet === $tatsaechlich) {
        echo "  ok    {$name}\n";

        return;
    }

    $fehler++;
    echo "  FAIL  {$name}\n";
    echo "        erwartet:     ".var_export($erwartet, true)."\n";
    echo "        tatsaechlich: ".var_export($tatsaechlich, true)."\n";
}

pruefe('URL ohne Parameter bleibt',
    'https://example.com/news',
    CanonicalUrl::build('https://example.com/news'));

pruefe('Startseite behaelt ihren Schraegstrich',
    'https://example.com/',
    CanonicalUrl::build('https://example.com/'));

pruefe('Tracking-Parameter faellt weg',
    'https://example.com/',
    CanonicalUrl::build('https://example.com/?ref=abc123'));

pruefe('Cache-Buster faellt weg',
    'https://example.com/news',
    CanonicalUrl::build('https://example.com/news?cb=99'));

pruefe('utm-Parameter fallen weg',
    'https://example.com/wiki/faq',
    CanonicalUrl::build('https://example.com/wiki/faq?utm_source=x&utm_medium=y'));

pruefe('Seitenzahl bleibt erhalten',
    'https://example.com/forum?page=2',
    CanonicalUrl::build('https://example.com/forum?page=2'));

pruefe('Seitenzahl bleibt, Rest faellt weg',
    'https://example.com/forum?page=3',
    CanonicalUrl::build('https://example.com/forum?page=3&ref=abc&utm_source=x'));

pruefe('Reihenfolge der Parameter ist egal',
    CanonicalUrl::build('https://example.com/a?page=2&sort=x', ['page', 'sort']),
    CanonicalUrl::build('https://example.com/a?sort=x&page=2', ['page', 'sort']));

pruefe('Schraegstrich am Ende eines Unterpfads faellt weg',
    'https://example.com/news',
    CanonicalUrl::build('https://example.com/news/'));

pruefe('Port bleibt erhalten',
    'https://example.com:8443/news',
    CanonicalUrl::build('https://example.com:8443/news?ref=a'));

pruefe('http bleibt http',
    'http://example.com/news',
    CanonicalUrl::build('http://example.com/news'));

pruefe('leere Whitelist wirft alle Parameter weg',
    'https://example.com/forum',
    CanonicalUrl::build('https://example.com/forum?page=2', []));

pruefe('Unsinn wird unveraendert durchgereicht',
    'kein-url',
    CanonicalUrl::build('kein-url'));

echo "\n";

if ($fehler > 0) {
    echo "{$fehler} Pruefung(en) fehlgeschlagen.\n";
    exit(1);
}

echo "Alle Pruefungen bestanden.\n";
