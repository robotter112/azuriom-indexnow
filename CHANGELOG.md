# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- The admin page as designed: the state as a badge beside the title, a line on who reads
  along (Bing, Yandex, Seznam and Naver right away, Google through the sitemap), the key
  file with “Copy”, “Report now” for all addresses, and “Turn off IndexNow” at the foot of
  the status card. The sitemap field says where its addresses come from (own sitemap,
  sitemap found, none found), and today shows the addresses reported and known. In all
  19 languages.

### Fixed

- The admin page fetched the sitemap over HTTP on every view, which made it load half a
  second and more slower than the other admin pages. Its own sitemap now comes from the
  cache that serves `/sitemap.xml`, another one is read at most every ten minutes.

## [2.3.0] - 2026-11-17

The admin page got a new look, and it tells you how your last report went.

### Added

- **The last report at a glance.** When it went out, how many addresses it held and
  what IndexNow answered, and how many addresses were reported today.

### Changed

- **A clearer admin page.** Status, settings and figures as cards in the look of the
  OnAir plugins, in the light and the dark mode of Azuriom and on a phone. The save
  button sits at the top, the texts are short, in all 19 languages.

### Upgrading

Nothing to do: no migrations, no settings to change. Azuriom links the new
stylesheet of the admin page when it installs the update.

Azuriom 1.2.0 or newer, tested against 1.2.13.

## [2.2.0] - 2026-11-16

IndexNow now covers every plugin and reports only what everyone can see.

### Added

- Every plugin is covered by the automatic reporting, without changing it: when
  a plugin saves or deletes an entry (a game, a forum discussion, a wiki page …),
  its public pages such as `/games` or `/forum` are reported, found in the route
  table. Such a page is reported at most once an hour, and quiet saves of
  scheduled refreshes report nothing.
- Plugins that know their exact addresses can hand them over without depending
  on IndexNow: the event `indexnow.submit` reports a list of addresses, and an
  answer to the event `indexnow.urls` adds them to *Submit all URLs* and the
  optional sitemap. Only addresses of the site itself are taken. OnAir Connect
  uses this for its collections.
- Only what a visitor who is not logged in can open is reported: before a report
  goes out, every address is opened without cookies, and only pages (200) and
  addresses that are gone (404, 410) are kept. Login pages, 401, 403 and pages
  under maintenance (503) are never reported, whatever plugin they belong to.
  Where the site cannot reach itself, the rules decide and maintenance stays out.
- A feature test in a fresh Azuriom (`tests/ci.sh`), next to a plugin that knows
  nothing about IndexNow, with and without AzBoost.

### Changed

- Deleting, taking offline or renaming a page or news post now reports its old
  address too, so search engines forget it.
- Everything that changes during one request goes out as one report instead of
  one request per saved item.

### Fixed

- A new page restricted to roles was reported: Azuriom assigns the roles of a
  page only after saving it, so at that moment it looked public. Whether a page
  is public is now decided when the report goes out, and a page with roles is
  never reported, also when renamed, disabled or deleted.

## [2.1.2] - 2026-11-14

### Fixed

- The plugin now repairs a route cache that Azuriom built without it, as
  happens on hosts where AzBoost is on and `opcache_invalidate()` is disabled.
  2.1.1 kept the admin panel working in that case, but IndexNow stayed
  unreachable until someone clicked *Reload AzBoost*. Now the first admin page
  that notices the gap rebuilds the cache right after it is delivered, the same
  way that button does, and the menu entry appears a moment later. AzBoost stays
  on.

## [2.1.1] - 2026-11-14

### Fixed

- Enabling the plugin no longer breaks the admin panel on hosts where AzBoost,
  Azuriom's route and config cache, is on and `opcache_invalidate()` is
  disabled, as is common on shared hosting. Azuriom rebuilds its route cache
  from an outdated plugin list there, so this plugin's routes were missing while
  its menu entry was not, and every admin page failed with
  `Route [indexnow.admin.index] not defined`. The entry now stays hidden until
  the routes exist; *Reload AzBoost* under *Settings → Performance* brings it
  back.
- Automatic reporting works. The setting was read while the plugin booted,
  before Azuriom loads its settings, so it always looked switched off and saving
  a page or post reported nothing, whatever the switch showed. It is now read
  when something is saved, and is on unless switched off — as the admin page has
  always shown it.

## [2.1.0] - 2026-09-01

### Added

- Bulk submission works without a sitemap. If none is found, the plugin falls
  back to Azuriom's own pages and published posts, gathered internally and handed
  straight to IndexNow — it is never served as a `sitemap.xml`, because producing
  that file belongs to a sitemap or SEO plugin. The admin page shows which source
  is in use.
- Optional sitemap at `/sitemap.xml`, off by default. The route is only
  registered while the option is on — a dormant route would take the address away
  from another plugin serving it, since the first registration wins. Exists only because
  Google ignores IndexNow, so a site without any sitemap has nothing for it. Any
  sitemap or SEO plugin does this better and should be preferred; if one already
  answers that address, the admin page says so instead of failing silently.
- Both the fallback list and that sitemap now cover everything reachable: wiki
  pages, public forums and their discussions, changelog categories, suggestions,
  and the index page of every enabled plugin, found through the route table
  rather than a fixed list. Routes serving a file rather than a page — another
  plugin's `sitemap.xml`, an `llms.txt` — are excluded.

## [2.0.1] - 2026-09-01

### Security

- The IndexNow key is validated before it is used as a file name. It ends up in
  `public_path()` and from there in a write and a delete, and a stored setting is
  not a trustworthy source for a file path. Only generated keys could reach it in
  practice, but the method is public API.
- The sitemap address must now be on your own site. IndexNow only accepts URLs of
  the submitting host anyway, and an arbitrary address would have let someone
  holding just this permission make the server fetch internal services.

## [2.0.0] - 2026-09-01

### Added

- IndexNow support for Azuriom. Setup is one button: it generates the key, writes
  the key file and verifies over HTTP that it is reachable and returns the key
  before saving anything.
- Automatic reporting when a page or news post is saved, skipping anything a
  visitor cannot open. The request is sent after the response, so saving is never
  slowed down.
- Bulk submission that reads the existing `sitemap.xml`, with a configurable
  address so it works alongside any sitemap or SEO plugin.
- Answers in plain words instead of status codes: accepted, still validating, key
  rejected, host mismatch, rate limited.
- Available in all 19 languages Azuriom supports.
- Self-checks for the key handling and the sitemap parsing, no test framework
  required.

### Note

This plugin grew out of a broader SEO plugin. Since
[SEO](https://market.azuriom.com/resources/226) already covers sitemaps,
canonical URLs, robots.txt and structured data — but not IndexNow — everything
overlapping was removed and only the missing piece kept.

[Unreleased]: https://github.com/Robotter112/azuriom-indexnow/compare/v2.1.2...HEAD
[2.1.2]: https://github.com/Robotter112/azuriom-indexnow/releases/tag/v2.1.2
[2.1.1]: https://github.com/Robotter112/azuriom-indexnow/releases/tag/v2.1.1
[2.1.0]: https://github.com/Robotter112/azuriom-indexnow/releases/tag/v2.1.0
[2.0.1]: https://github.com/Robotter112/azuriom-indexnow/releases/tag/v2.0.1
[2.0.0]: https://github.com/Robotter112/azuriom-indexnow/releases/tag/v2.0.0
