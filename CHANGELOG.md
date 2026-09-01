# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] - 2026-09-01

### Added

- Canonical URLs: pages without one get `<link rel="canonical">` with the clean
  address, so the same page reached with tracking or cache-busting parameters is
  no longer treated as a separate address. Parameters that change the content
  (by default `page`) are kept, and a canonical already set by the theme is
  never overwritten. Can be switched off.
- robots.txt: the admin page reports whether it points crawlers at the sitemap
  and can write the `Sitemap:` line itself.
- Self-check for the canonical URL building (`php tests/CanonicalUrlTest.php`).

## [1.1.0] - 2026-09-01

### Added

- On-page checks during the URL check: missing or duplicate `h1`, missing meta
  description or one that is too short or too long, missing page title, and
  images without a usable `alt` attribute. The pages are fetched for the status
  check anyway, so this costs no extra requests.
- Self-check for the HTML analysis, runnable with `php tests/SeoCheckTest.php`.

### Changed

- The check result now separates URLs that fail to answer `200` from URLs that
  answer fine but have on-page issues — only the former belong in the excluded
  paths.

## [1.0.0] - 2026-09-01

### Added

- `sitemap.xml` at the document root, listing every publicly reachable URL of
  the site with a `<lastmod>` date.
- Automatic discovery of parameterless public routes, so index pages of plugins
  this one does not know about are included as well.
- URL sources for core pages, news posts, wiki pages, public forums and their
  discussions, changelog categories and suggestions — each source only
  contributes what a logged-out visitor can open.
- Admin page under **Admin → Sitemap** with the sitemap address, the current URL
  count, a rebuild button and both settings.
- **Check URLs** button and `php artisan sitemap:check`, which fetch every
  listed URL as a guest and report anything that does not answer with `200`.
- Excluded paths setting for pages whose plugin redirects guests from inside its
  own controller, which cannot be detected automatically.
- `SitemapBuilding` event so other plugins can contribute their own URLs.
- English, French and German translations for the admin page.

[Unreleased]: https://git.fastm.de/Max/azuriom-sitemap/compare/v1.2.0...HEAD
[1.2.0]: https://git.fastm.de/Max/azuriom-sitemap/releases/tag/v1.2.0
[1.1.0]: https://git.fastm.de/Max/azuriom-sitemap/releases/tag/v1.1.0
[1.0.0]: https://git.fastm.de/Max/azuriom-sitemap/releases/tag/v1.0.0
