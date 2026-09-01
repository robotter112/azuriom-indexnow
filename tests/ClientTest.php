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

require __DIR__.'/../src/Client.php';

use Azuriom\Plugin\Indexnow\Client;

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

$key = Client::generateKey();

check('key is hexadecimal', 1, preg_match('/^[a-f0-9]+$/', $key));
check('key length is within the allowed 8-128', true,
    strlen($key) >= 8 && strlen($key) <= 128);
check('two keys differ', true, Client::generateKey() !== Client::generateKey());
check('file name appends .txt', $key.'.txt', Client::keyFileName($key));

check('host without scheme and path', 'example.com',
    Client::hostFor('https://example.com/sitemap.xml'));
check('host is lowercased', 'example.com',
    Client::hostFor('https://EXAMPLE.com/'));
check('host with subdomain', 'www.example.com',
    Client::hostFor('https://www.example.com/a/b?c=d'));
check('broken URL yields an empty host', '', Client::hostFor('not-a-url'));

check('200 means accepted', 'accepted', Client::reasonFor(200));
check('202 means validation pending', 'pending', Client::reasonFor(202));
check('403 means key invalid', 'key-invalid', Client::reasonFor(403));
check('422 means URLs do not match the host', 'mismatch', Client::reasonFor(422));
check('429 means too many requests', 'too-many', Client::reasonFor(429));
check('unknown code falls back to unknown', 'unknown', Client::reasonFor(500));

check('an empty list is not sent at all',
    ['ok' => false, 'reason' => 'empty', 'status' => 0, 'count' => 0],
    Client::submit('example.com', $key, 'https://example.com/k.txt', []));

echo "\n";

if ($failures > 0) {
    echo "{$failures} check(s) failed.\n";
    exit(1);
}

echo "All checks passed.\n";
