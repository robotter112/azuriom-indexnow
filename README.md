<img width="2000" height="1000" alt="cover" src="https://github.com/user-attachments/assets/08f78091-8a6c-40c1-a289-e41e053504dd" />

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

The address the key file was confirmed at is kept as the site's address. Everything
IndexNow builds afterwards — the optional sitemap, the checks and the reports — uses
that address, never the one a request happens to arrive with (its Host header, a
`/index.php` in the path or an `X-Forwarded-Proto` header).

## Automatic reporting

With *Report changes automatically* on, every change is reported right away.
That is the point of IndexNow — waiting for someone to press a button defeats it.

- **Pages and news posts** are reported by their own address when saved — and
  also when deleted, taken offline or given a new slug, so search engines forget
  the old address. Only what a visitor can open counts: a post scheduled for
  tomorrow, a disabled page or one restricted to a role is skipped.
- **Every other plugin is covered too, without changing it.** When a plugin saves
  or deletes an entry (a game, a forum discussion, a wiki page, a shop item …),
  its public pages are reported: the parameterless pages found in the route table
  under its name, such as `/games` or `/forum`, never its admin pages. Search
  engines find the entry itself from there. Such a page changes with every entry,
  so it is reported at most once an hour; changes made quietly (`saveQuietly()`,
  as scheduled refreshes do) report nothing.
- **Exact addresses** come from plugins that hand them over (see below).

Everything that changes during one request goes out as **one** report, **after**
the response, so saving in the admin panel is never slowed down, and a failure
there can never disturb the save that triggered it.

### Only what everyone can see

Before a report goes out, the plugin opens every address the way a visitor who is
**not logged in** does — no cookies, no redirects followed — and keeps only:

- addresses that answer with a page (200), and
- addresses that are gone (404, 410), so search engines forget them.

Anything that sends visitors to the login form, answers 401 or 403, or needs a
role is **never** reported, whatever plugin it belongs to. Pages under
**maintenance** (503) are not reported either — neither as changed nor as gone —
so rebuilding something never costs its place in the search results. Pages
restricted to roles are also left out by the rules before any check.

The check needs the site to reach itself, as the key verification does. Where it
cannot, the rules decide, and addresses under maintenance stay out.

### Pages of other plugins

A plugin that knows the exact addresses of its pages can hand them over, without
requiring IndexNow — without it, nobody listens and nothing happens:

```php
// When one of your pages changes:
event('indexnow.submit', [[route('myplugin.show', $item), route('myplugin.index')]]);

// For "Submit all URLs" and the optional sitemap:
Event::listen('indexnow.urls', fn () => MyPlugin::publicUrls());

// When they last changed, for <lastmod> (address => DateTimeInterface):
Event::listen('indexnow.dates', fn () => MyPlugin::changeDates());

// Pages that carry noindex: left out of the sitemap and of reports.
Event::listen('indexnow.exclude', fn () => [route('posts.index')]);

// A page that is not always there: listed only while indexnow.urls returns it.
Route::get('/draft', ['uses' => MyController::class.'@show', 'sitemap' => false]);
```

State a date only if it is true. Google stops trusting the dates of a site whose
dates move without a change, so a page without a known date goes without one.

Only addresses of your own site are taken, and *Report changes automatically*
decides as it does for pages and posts.

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

Each address carries the date of its last change (`lastmod`) where that is
known: posts, pages, the home page and the list of posts, and what plugins
state. There is no `changefreq` and no `priority`, Google ignores both. An
unchanged sitemap answers a crawler that already has it with `304`.

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
- The site must be able to reach its own address (for the key check, and for the
  check that keeps non-public addresses out of the reports)

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
sh tests/ci.sh            # also the feature test in a fresh Azuriom
```

The first two run without a test framework. The key handling and the sitemap
parsing work with string manipulation, which breaks quietly. `tests/ci.sh` adds
`tests/feature.php`, which runs in a fresh Azuriom next to a second plugin that
knows nothing about IndexNow (`tests/stubs/indexnow-demo`), with and without
AzBoost.

## License

MIT — see [LICENSE](LICENSE).
