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
    echo '        expected: '.json_encode($expected)."\n";
    echo '        actual:   '.json_encode($actual)."\n";
}

/** @return array<int, string> */
function keys(string $html): array
{
    return array_column(SeoCheck::issues($html), 'key');
}

$good = '<html><head><title>A page</title>'
    .'<meta name="description" content="'.str_repeat('a', 120).'"></head>'
    .'<body><h1>Titel</h1><img src="a.png" alt="Ein Bild"></body></html>';

check('clean page reports nothing', [], keys($good));

check('no h1', ['h1-missing'], keys(
    '<title>T</title><meta name="description" content="'.str_repeat('a', 120).'">'
));

check('multiple h1', ['h1-multiple'], keys(
    '<title>T</title><meta name="description" content="'.str_repeat('a', 120).'">'
    .'<h1>A</h1><h1 class="x">B</h1>'
));

check('h1 count is included', 3, SeoCheck::issues(
    '<title>T</title><meta name="description" content="'.str_repeat('a', 120).'">'
    .'<h1>A</h1><h1>B</h1><h1>C</h1>'
)[0]['count']);

check('description missing', ['description-missing'], keys('<title>T</title><h1>A</h1>'));

check('description too short', ['description-short'], keys(
    '<title>T</title><meta name="description" content="Zu kurz"><h1>A</h1>'
));

check('description too long', ['description-long'], keys(
    '<title>T</title><meta name="description" content="'.str_repeat('a', 200).'"><h1>A</h1>'
));

check('title missing', ['title-missing'], keys(
    '<meta name="description" content="'.str_repeat('a', 120).'"><h1>A</h1>'
));

check('blank title counts as missing', ['title-missing'], keys(
    '<title>   </title><meta name="description" content="'.str_repeat('a', 120).'"><h1>A</h1>'
));

$ohneAlt = '<title>T</title><meta name="description" content="'.str_repeat('a', 120).'"><h1>A</h1>'
    .'<img src="1.png">'                 // alt missing entirely
    .'<img src="2.png" alt="">'          // alt empty
    .'<img src="3.png" alt="   ">'       // alt is whitespace only
    .'<img src="4.png" alt="Gut">';      // fine

check('images without a usable alt', ['images-without-alt'], keys($ohneAlt));
check('exactly three of them', 3, SeoCheck::issues($ohneAlt)[0]['count']);

check('single quotes around alt', [], keys(
    "<title>T</title><meta name='description' content='".str_repeat('a', 120)."'><h1>A</h1>"
    ."<img src='1.png' alt='Gut'>"
));

check('title comes back trimmed', 'A page', SeoCheck::title($good));
check('no title returns null', null, SeoCheck::title('<h1>A</h1>'));

echo "\n";

if ($failures > 0) {
    echo "{$failures} check(s) failed.\n";
    exit(1);
}

echo "All checks passed.\n";
