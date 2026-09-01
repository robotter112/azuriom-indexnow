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
The address is configurable, so it works with any plugin that produces one.

**No sitemap on your site?** Then the plugin gathers the addresses itself — the
same complete list described under *Optionally serving a sitemap* below — and
hands it straight to IndexNow. The admin page tells you which of the two sources
is in use.

## Optionally serving a sitemap — off by default

IndexNow itself never needs a sitemap. There is exactly one reason this option
exists: **Google does not take part in IndexNow**, so on a site with no sitemap
at all, Google has nothing to go on.

Switching it on serves a sitemap at `/sitemap.xml` covering **everything a
logged-out visitor can reach**: pages, news posts, wiki pages, public forums and
their discussions, changelog categories, suggestions — plus the index page of
every enabled plugin, discovered from the route table rather than a hardcoded
list, so plugins this one has never heard of are included too.

Left out on purpose: redirects, role-restricted pages, private forums, search
forms, and routes serving a file rather than a page such as another plugin's
`sitemap.xml` or `llms.txt`.

It is **off by default**. If a sitemap or SEO plugin is installed, prefer that
one — it will keep pace with its own features, and two files disagreeing about
what is public helps nobody.

If another plugin already answers `/sitemap.xml`, that plugin wins and the admin
page says so plainly, rather than leaving you to wonder why nothing changed.

The answer comes back in plain words rather than as a status code: accepted,
still validating, key rejected, host mismatch, rate limited.

## Requirements

- Azuriom **1.2.0** or newer
- **No other plugin required.** A `sitemap.xml` is used when present; without one
  the bulk submission falls back to Azuriom's pages and posts, and the per-page
  reporting never needed it in the first place
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
