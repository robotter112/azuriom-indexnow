# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- The admin page is built from the Bootstrap components of the Azuriom admin only, without a stylesheet of its own, so it follows the admin theme.

## [2.4.0] - 2026-11-22

### Added

- The sitemap says when each page last changed (`lastmod`): posts, pages, the home page and the list
  of posts, and what plugins state through the event `indexnow.dates`. A page without a known date
  goes without one, since Google only trusts dates that are true.
- An unchanged sitemap answers with `304` to a crawler that already has it (ETag).
- A published or changed post also reports the list of posts and the home page, at most once an hour.
- A button beside the sitemap address copies it, like the one of the key file.

### Changed

- The comments in the code are short.

### Fixed

- A page that is not always there, such as a draft of another plugin, can stay out of the sitemap: a
  route with the action `'sitemap' => false` is only listed while its plugin hands it over through
  `indexnow.urls`.
- Switching between light and dark mode in the admin panel no longer lets buttons and fields fade
  over from the other mode for a moment.

## [2.3.3] - 2026-11-20

### Fixed

- A request that reached the site under an IPv6 address in brackets could leave the posts and
  pages out of the optional sitemap for up to an hour. Their addresses are recognised now.

## [2.3.2] - 2026-11-19

A security and robustness update from the second audit of November 2026. Recommended for every site.

### Fixed

- The site's own address is the configured one (APP_URL). The address IndexNow was switched on
  at only refines it (scheme and folder, same host and port), opening the admin page no longer
  stores anything, and an address that names the front controller in any spelling is not used.
  Before, a staff member with the IndexNow permission, or a link they were sent, could point the
  sitemap, the checks and the reports at another address.
- The sitemap leaves out every address that is not on the site's own host, whatever spelling of
  the host a request arrives with.
- Changing the sitemap switch rebuilds AzBoost's cached routes instead of dropping them.
- The requests the site makes to itself (key file, visibility checks, an own sitemap) keep
  their limits on every server: the answer is cut off at its size limit while it arrives, a
  packed answer is unpacked up to that limit only, and the time limit covers the whole
  request. Before, a server without allow_url_fopen buffered the whole unpacked answer first.
  A very large sitemap is read address by address, and one that cannot be read is not read
  again on every view of the admin page.
- The optional sitemap is built again as soon as a post or page changes, or a plugin hands
  over a changed address. A post that was unpublished, or a page that was disabled or given
  roles, no longer stays listed for up to an hour.
- A plugin whose list for the sitemap fails (for example after a stale route cache) is left
  out; the addresses of the site and of all other plugins stay.

### Security

- Pages that the visibility check opens no longer start another report, even when they save
  something of their plugin while being viewed.
- One report spends at most ten seconds on visibility checks; after that the rules decide.
  The check reads only the status of a page and never downloads the page itself.
- The key file check and the sitemap reader stop reading at a size limit (1 KB for the key
  file, 20 MB for a sitemap), also for compressed answers.

## [2.3.1] - 2026-11-18

A security and robustness update. Recommended for every site.

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
- A plugin page that refuses visitors was opened again with every change of its plugin.
  Each page is now checked at most once in ten minutes, whatever it answered.
- On big sites the optional sitemap could run out of memory and then failed on every
  request, the IndexNow admin page included. It now reads only the addresses, builds one
  list at a time, keeps serving the last list while a new one is built, and does not
  retry for ten minutes after a build that failed.
- `/sitemap.xml` answered with an error on servers where PHP's `short_open_tag` is on.
- Switching IndexNow on again left the old key file in place, so the old key stayed valid.
- The release package no longer contains the tests.

### Security

- **The site's own address no longer comes from the request.** Addresses that IndexNow
  caches for everyone, checks or reports are now always built on the address the key
  file was verified at when IndexNow was switched on (sites switched on earlier take it
  from the next visit of the IndexNow admin page, APP_URL until then). Before, a
  crafted request could decide which address the optional sitemap listed for an hour,
  and where checks and reports went.
- **The sitemap address must really be this site.** The check now compares the port as
  well, and a redirect is only followed while it stays on the site.
- **The key file check follows no redirects.**
- **Addresses handed over by other plugins that could not be checked are no longer
  reported.**

### Upgrading

No migrations. Open *Admin → IndexNow* once after the update: that visit keeps the
site's address for everything IndexNow builds (until then APP_URL from your `.env` is
used). Clearing the cache once (*Settings → Performance → Clear cache*) drops a sitemap
list built before the update.

Azuriom 1.2.0 or newer, tested against 1.2.13.

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
