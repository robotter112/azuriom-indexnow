# IndexNow

An [Azuriom](https://azuriom.com) plugin that tells search engines a page changed
**the moment it changes**, instead of waiting for them to come around.

**Bing, Yandex, Seznam and Naver take part. Google does not** — there a sitemap
remains the way, and this plugin is a good companion to whichever SEO or sitemap
plugin you already run.

Repository: <https://github.com/Robotter112/azuriom-indexnow>

## Setup is one button

*Admin → IndexNow → Enable IndexNow* generates a key, writes the key file to your
document root, and then **fetches that file over HTTP to confirm it is really
reachable and really returns the key**. Only if that works is anything saved.
Otherwise the stray file is removed again and you are told exactly why —
unreachable, wrong status code, or altered content.

That check matters: a key that cannot be verified makes every later submission
fail with `403`, far away from its cause.

## Automatic reporting

With *Report changes automatically* on, saving a page or a news post reports it
right away. That is the point of IndexNow — waiting for someone to press a button
defeats it.

Only pages a visitor can actually open are reported: a post scheduled for
tomorrow, a disabled page or one restricted to a role is skipped. The request
goes out **after** the response, so saving in the admin panel is never slowed
down, and a failure there can never disturb the save that triggered it.

## Submitting everything at once

*Submit all URLs* reads your existing **`sitemap.xml`** and submits what it finds.
The address is configurable, so it works with any plugin that produces one —
this plugin deliberately does not build a second sitemap of its own.

The answer comes back in plain words rather than as a status code: accepted,
still validating, key rejected, host mismatch, rate limited.

## Requirements

- Azuriom **1.2.0** or newer
- A `sitemap.xml` for the bulk submission — the per-page reporting works without one
- No migrations, no dependencies

## Translations

Available in **all 19 languages Azuriom supports**, following the language
configured there and falling back to English. Adding one is text work, no code:
copy `resources/lang/en/admin.php` and translate the 33 strings. Corrections from
native speakers are welcome — everything outside English, French and German was
not reviewed by one.

## Tests

```bash
php tests/ClientTest.php
php tests/UrlSourceTest.php
```

Both run without a test framework. The key handling and the sitemap parsing work
with string manipulation, which breaks quietly.

## License

MIT — see [LICENSE](LICENSE).
