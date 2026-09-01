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

$key = IndexNow::generateKey();

check('key is hexadecimal', 1, preg_match('/^[a-f0-9]+$/', $key));
check('key length is within the allowed 8-128', true,
    strlen($key) >= 8 && strlen($key) <= 128);
check('two keys differ', true, IndexNow::generateKey() !== IndexNow::generateKey());
check('file name appends .txt', $key.'.txt', IndexNow::keyFileName($key));

check('host without scheme and path', 'example.com',
    IndexNow::hostFor('https://example.com/sitemap.xml'));
check('host is lowercased', 'example.com',
    IndexNow::hostFor('https://EXAMPLE.com/'));
check('host with subdomain', 'www.example.com',
    IndexNow::hostFor('https://www.example.com/a/b?c=d'));
check('broken URL yields an empty host', '', IndexNow::hostFor('not-a-url'));

check('200 means accepted', 'accepted', IndexNow::reasonFor(200));
check('202 means validation pending', 'pending', IndexNow::reasonFor(202));
check('403 means key invalid', 'key-invalid', IndexNow::reasonFor(403));
check('422 means URLs do not match the host', 'mismatch', IndexNow::reasonFor(422));
check('429 means too many requests', 'too-many', IndexNow::reasonFor(429));
check('unknown code falls back to unknown', 'unknown', IndexNow::reasonFor(500));

check('an empty list is not sent at all',
    ['ok' => false, 'reason' => 'empty', 'status' => 0, 'count' => 0],
    IndexNow::submit('example.com', $key, 'https://example.com/k.txt', []));

echo "\n";

if ($failures > 0) {
    echo "{$failures} check(s) failed.\n";
    exit(1);
}

echo "All checks passed.\n";
