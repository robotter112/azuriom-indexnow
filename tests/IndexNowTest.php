<?php

/**
 * Self-check for the parts of IndexNow that work without network access:
 *
 *     php tests/IndexNowTest.php
 */

// Minimal stand-ins so the class can be loaded without booting Laravel.
if (! class_exists('Illuminate\Support\Str')) {
    eval('namespace Illuminate\Support; class Str {
        public static function of($v) { return new StrProxy($v); }
    }
    class StrProxy {
        public function __construct(private $v) {}
        public function lower() { return strtolower($this->v); }
        public function __toString() { return (string) $this->v; }
    }');
}
if (! class_exists('Illuminate\Support\Facades\Http')) {
    eval('namespace Illuminate\Support\Facades; class Http {}');
}

require __DIR__.'/../src/IndexNow.php';

use Azuriom\Plugin\Seo\IndexNow;

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
    echo '        erwartet:     '.var_export($erwartet, true)."\n";
    echo '        tatsaechlich: '.var_export($tatsaechlich, true)."\n";
}

$key = IndexNow::generateKey();

pruefe('Schluessel ist hexadezimal', 1, preg_match('/^[a-f0-9]+$/', $key));
pruefe('Schluessellaenge liegt im erlaubten Bereich 8-128', true,
    strlen($key) >= 8 && strlen($key) <= 128);
pruefe('zwei Schluessel sind verschieden', true, IndexNow::generateKey() !== IndexNow::generateKey());
pruefe('Dateiname haengt .txt an', $key.'.txt', IndexNow::keyFileName($key));

pruefe('Host ohne Schema und Pfad', 'example.com',
    IndexNow::hostFor('https://example.com/sitemap.xml'));
pruefe('Host wird kleingeschrieben', 'example.com',
    IndexNow::hostFor('https://EXAMPLE.com/'));
pruefe('Host mit Subdomain', 'www.example.com',
    IndexNow::hostFor('https://www.example.com/a/b?c=d'));
pruefe('kaputte URL gibt leeren Host', '', IndexNow::hostFor('kein-url'));

pruefe('200 heisst angenommen', 'accepted', IndexNow::reasonFor(200));
pruefe('202 heisst Pruefung ausstehend', 'pending', IndexNow::reasonFor(202));
pruefe('403 heisst Schluessel ungueltig', 'key-invalid', IndexNow::reasonFor(403));
pruefe('422 heisst Adressen passen nicht zum Host', 'mismatch', IndexNow::reasonFor(422));
pruefe('429 heisst zu viele Anfragen', 'too-many', IndexNow::reasonFor(429));
pruefe('unbekannter Code faellt auf unknown', 'unknown', IndexNow::reasonFor(500));

pruefe('leere Liste wird gar nicht erst gesendet',
    ['ok' => false, 'reason' => 'empty', 'status' => 0, 'count' => 0],
    IndexNow::submit('example.com', $key, 'https://example.com/k.txt', []));

echo "\n";

if ($fehler > 0) {
    echo "{$fehler} Pruefung(en) fehlgeschlagen.\n";
    exit(1);
}

echo "Alle Pruefungen bestanden.\n";
