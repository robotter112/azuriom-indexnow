# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.1.1] - 2026-09-01

### Fixed

- The optional sitemap route was registered even while the option was off,
  answering `404`. Where another plugin serves `/sitemap.xml`, the first
  registration wins and `indexnow` sorts before `seo` — so a dormant route took
  the address away from the plugin actually serving it, and the site lost its
  sitemap. The route is now only registered when the option is on.
- That switch is read from a file rather than the setting, because settings are
  not loaded yet when routes are registered: `setting()` returned `null` there
  even with the value stored, which would have left the option permanently off.

## [2.1.0] - 2026-09-01

### Added

- Bulk submission works without a sitemap. If none is found, the plugin falls
  back to Azuriom's own pages and published posts, gathered internally and handed
  straight to IndexNow — it is never served as a `sitemap.xml`, because producing
  that file belongs to a sitemap or SEO plugin. The admin page shows which source
  is in use.
- Optional sitemap at `/sitemap.xml`, off by default. Exists only because
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

[Unreleased]: https://github.com/Robotter112/azuriom-indexnow/compare/v2.1.1...HEAD
[2.1.1]: https://github.com/Robotter112/azuriom-indexnow/releases/tag/v2.1.1
[2.1.0]: https://github.com/Robotter112/azuriom-indexnow/releases/tag/v2.1.0
[2.0.1]: https://github.com/Robotter112/azuriom-indexnow/releases/tag/v2.0.1
[2.0.0]: https://github.com/Robotter112/azuriom-indexnow/releases/tag/v2.0.0
