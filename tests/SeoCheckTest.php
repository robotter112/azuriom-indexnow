<?php

/**
 * Self-check for SeoCheck, runnable without a test framework:
 *
 *     php tests/SeoCheckTest.php
 *
 * The class works with regular expressions, and regular expressions break
 * quietly - this catches that.
 */

require __DIR__.'/../src/SeoCheck.php';

use Azuriom\Plugin\Seo\SeoCheck;

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
    echo '        erwartet:     '.json_encode($erwartet)."\n";
    echo '        tatsaechlich: '.json_encode($tatsaechlich)."\n";
}

/** @return array<int, string> */
function keys(string $html): array
{
    return array_column(SeoCheck::issues($html), 'key');
}

$gut = '<html><head><title>Eine Seite</title>'
    .'<meta name="description" content="'.str_repeat('a', 120).'"></head>'
    .'<body><h1>Titel</h1><img src="a.png" alt="Ein Bild"></body></html>';

pruefe('saubere Seite meldet nichts', [], keys($gut));

pruefe('kein h1', ['h1-missing'], keys(
    '<title>T</title><meta name="description" content="'.str_repeat('a', 120).'">'
));

pruefe('mehrere h1', ['h1-multiple'], keys(
    '<title>T</title><meta name="description" content="'.str_repeat('a', 120).'">'
    .'<h1>A</h1><h1 class="x">B</h1>'
));

pruefe('h1-Anzahl wird mitgeliefert', 3, SeoCheck::issues(
    '<title>T</title><meta name="description" content="'.str_repeat('a', 120).'">'
    .'<h1>A</h1><h1>B</h1><h1>C</h1>'
)[0]['count']);

pruefe('Beschreibung fehlt', ['description-missing'], keys('<title>T</title><h1>A</h1>'));

pruefe('Beschreibung zu kurz', ['description-short'], keys(
    '<title>T</title><meta name="description" content="Zu kurz"><h1>A</h1>'
));

pruefe('Beschreibung zu lang', ['description-long'], keys(
    '<title>T</title><meta name="description" content="'.str_repeat('a', 200).'"><h1>A</h1>'
));

pruefe('Titel fehlt', ['title-missing'], keys(
    '<meta name="description" content="'.str_repeat('a', 120).'"><h1>A</h1>'
));

pruefe('leerer Titel zaehlt als fehlend', ['title-missing'], keys(
    '<title>   </title><meta name="description" content="'.str_repeat('a', 120).'"><h1>A</h1>'
));

$ohneAlt = '<title>T</title><meta name="description" content="'.str_repeat('a', 120).'"><h1>A</h1>'
    .'<img src="1.png">'                 // alt fehlt ganz
    .'<img src="2.png" alt="">'          // alt leer
    .'<img src="3.png" alt="   ">'       // alt nur Leerzeichen
    .'<img src="4.png" alt="Gut">';      // in Ordnung

pruefe('Bilder ohne brauchbares alt', ['images-without-alt'], keys($ohneAlt));
pruefe('davon genau drei', 3, SeoCheck::issues($ohneAlt)[0]['count']);

pruefe('einfache Anfuehrungszeichen im alt', [], keys(
    "<title>T</title><meta name='description' content='".str_repeat('a', 120)."'><h1>A</h1>"
    ."<img src='1.png' alt='Gut'>"
));

pruefe('Titel wird gekuerzt geliefert', 'Eine Seite', SeoCheck::title($gut));
pruefe('kein Titel gibt null', null, SeoCheck::title('<h1>A</h1>'));

echo "\n";

if ($fehler > 0) {
    echo "{$fehler} Pruefung(en) fehlgeschlagen.\n";
    exit(1);
}

echo "Alle Pruefungen bestanden.\n";
