# Sitemap

An [Azuriom](https://azuriom.com) plugin that serves a `sitemap.xml` at the
document root of your site, listing every URL a logged-out visitor can open, so
search engines can find and index all of your content instead of only the pages
they stumble upon through links.

Repository: <https://git.fastm.de/Max/azuriom-sitemap>

## What ends up in the sitemap

* The home page and the index page of every enabled plugin, discovered
  automatically from the route list — no hardcoded list of known plugins, so it
  keeps working with plugins this one has never heard of.
* Every enabled page that is not restricted to a role.
* Every published news post.
* Wiki pages of enabled, unrestricted categories.
* Public (non-private, unrestricted) forums and their discussions.
* Enabled changelog categories.
* Suggestions.

Anything behind `auth`, `guest`, `verified` or `admin-access` middleware is
skipped, as are search forms, redirects and machine endpoints under `/api`.
Each entry carries a `<lastmod>` taken from the record's `updated_at`.

Wiki category URLs are deliberately left out: that route always redirects to the
category's first page, and search engines report redirects in a sitemap as
errors.

## Requirements

* Azuriom **1.2.0** or newer (developed and tested against 1.2.9)
* No database migrations, no extra dependencies

## Installation

1. Copy this folder to `plugins/sitemap` in your Azuriom installation.
2. Enable it in the admin panel under *Plugins*, or run
   `php artisan plugin:enable sitemap`
3. Open **Admin → Sitemap**. It shows the address of your sitemap, how many URLs
   it currently holds, and the settings described below.
4. Point crawlers at it by adding this line to `public/robots.txt`:
   ```
   Sitemap: https://your-site.example/sitemap.xml
   ```
5. Submit that same address in Google Search Console and Bing Webmaster Tools.

## The admin page

**Admin → Sitemap** gives you:

* the sitemap address, ready to copy, plus a direct link
* how many URLs it currently contains and whether the list is cached
* **Rebuild now** — drops the cache and builds the list again
* **Check URLs** — fetches every listed URL as a logged-out visitor and reports
  everything that does not answer with `200`, with its status code
* the two settings below

## Configuration

* **Cache lifetime** — how long the URL list is kept before it is rebuilt,
  default 60 minutes. Crawlers fetch far less often than that.
* **Excluded paths** — one `Str::is()` pattern per line, without the domain, so
  `shop/*` works.

These live in Azuriom's settings table, not in `config/sitemap.php`. That file
only supplies the defaults for a fresh install — a plugin update replaces it and
would otherwise throw your list away.

The file is read directly rather than through `config()`, because a plugin
config is not part of a cached core config and `mergeConfigFrom()` silently does
nothing on sites that ran `config:cache`.

## Checking the sitemap

A sitemap should only contain pages that really exist and really are public.
Some plugins redirect guests from inside their controller instead of using the
`auth` middleware, which cannot be detected without running the page. **Check
URLs** in the admin panel finds those; add their path to *Excluded paths* and
save, which also clears the cache.

The button stops after 60 URLs so the request cannot time out. For a full run on
a large site use the command, which has no cap:

```bash
php artisan sitemap:check
```

### On-page checks

The pages are fetched anyway, so the same run also reports problems search
engines complain about, at no extra request:

* no `h1` heading, or more than one
* missing meta description, or one that is very short or gets truncated
* missing page title
* images without a usable `alt` attribute (an empty `alt` counts, since it tells
  a screen reader "decorative")

These pages still belong in the sitemap — the report tells you where to improve
them, it does not exclude anything.

## Canonical URLs

The same page reached with a tracking parameter (`?ref=…`), a campaign tag
(`?utm_source=…`) or a cache buster looks like a separate address to a search
engine, which splits its ranking between them. The plugin adds
`<link rel="canonical">` with the clean address to pages that do not already
have one.

Parameters that genuinely change the content survive — by default `page`.
**Do not remove `page` from that list**: it would tell search engines that page 2
is a copy of page 1, and page 2 would drop out of the index.

A canonical set by your theme or another plugin is never overwritten. The feature
can be switched off in the admin page. Technically it rewrites the finished HTML
response, because Azuriom's core layout offers no `@stack('meta')` a plugin could
push into; it only touches successful HTML responses to GET requests that contain
a `</head>`.

## robots.txt

Crawlers look for the sitemap in `robots.txt`. The admin page reports whether the
line is there and can write it for you if the file is writable.

## Tests

The HTML analysis works with regular expressions, which break quietly, so it
carries a self-check that needs no test framework:

```bash
php tests/SeoCheckTest.php
php tests/CanonicalUrlTest.php
```

A wrong canonical is worse than none — it can remove a page from the index — so
that part carries its own checks.

## Adding your own URLs

Other plugins can contribute URLs by listening to the `SitemapBuilding` event:

```php
use Azuriom\Plugin\Sitemap\Events\SitemapBuilding;
use Illuminate\Support\Facades\Event;

Event::listen(SitemapBuilding::class, function (SitemapBuilding $event) {
    foreach (MyModel::all() as $model) {
        $event->add(route('myplugin.show', $model), $model->updated_at);
    }
});
```

Only add URLs a logged-out visitor can open.

## Translations

The admin page ships in **English, French and German** and follows the language
configured in Azuriom, falling back to English for any other locale. The public
`sitemap.xml` contains no text at all and is language-neutral.

Adding a language is text work, no code: copy `resources/lang/en/admin.php` to
`resources/lang/<locale>/admin.php` and translate the 21 strings. Pull requests
for further locales are welcome — Azuriom itself ships 19.

Console output of `php artisan sitemap:check` is English only, matching
Azuriom's own commands.

## Limits

The sitemap protocol allows 50 000 URLs per file; above that a `<sitemapindex>`
with paginated children is needed. This plugin cuts off at 50 000 instead. If
your site is that big, please open an issue.

## Contributing

Issues and pull requests are welcome at
<https://git.fastm.de/Max/azuriom-sitemap>. Please keep the code in the style of
the surrounding files: comments in English, no new dependencies, and a note in
`CHANGELOG.md` for anything users can notice.

## License

MIT — see [LICENSE](LICENSE).
