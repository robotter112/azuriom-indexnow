# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Variable names, comments and test output are English throughout. The Azuriom
  market requires everything outside the `lang` folder to be in English, and
  parts of the code still carried German identifiers from development.

## [1.4.0] - 2026-09-01

### Added

- Translations for all 19 languages Azuriom supports: Catalan, Czech, Spanish,
  Finnish, Hungarian, Indonesian, Korean, Lithuanian, Dutch, Polish, Brazilian
  Portuguese, Russian, Swedish, Turkish, Ukrainian and Simplified Chinese, on
  top of the existing English, French and German. Key parity, placeholder parity
  and plural forms are verified against the English reference.

### Changed

- The hint texts on the admin page are about half as long and say what to do
  rather than how it works, in all 19 languages. The one warning that stays is
  the `page` parameter, because removing it silently drops paginated pages from
  the index.
- Buttons that wrap in a narrow card no longer sit flush against each other.
- The admin menu entry is called "SEO" instead of "Sitemap" and carries a
  magnifying glass instead of the sitemap tree - the plugin covers more than the
  sitemap now.

## [1.3.1] - 2026-09-01

### Fixed

- The plugin link in the admin plugin list pointed at a private repository,
  where visitors only get a 404. It points at the public repository now.
- The buttons of the IndexNow and sitemap cards sat flush against each other
  with no gap.

## [1.3.0] - 2026-09-01

### Added

- IndexNow support. One button generates the key, writes the key file and
  verifies over HTTP that it is reachable and returns the key before saving
  anything — an unverifiable key would make every later submission fail with
  `403`. A second button submits all sitemap URLs and reports the answer in
  plain words. Bing, Yandex, Seznam and Naver participate; Google does not.
- Self-check for the IndexNow key handling and status interpretation
  (`php tests/IndexNowTest.php`).

### Changed

- **The plugin is now called `seo`, not `sitemap`.** It long since covered more
  than the sitemap. The public address stays `/sitemap.xml`, so submitted
  sitemaps keep working, but the plugin folder, its id, the admin route and the
  console command changed (`php artisan seo:check`). Settings are carried over.

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
- **Check URLs** button and `php artisan seo:check`, which fetch every
  listed URL as a guest and report anything that does not answer with `200`.
- Excluded paths setting for pages whose plugin redirects guests from inside its
  own controller, which cannot be detected automatically.
- `SitemapBuilding` event so other plugins can contribute their own URLs.
- English, French and German translations for the admin page.

[Unreleased]: https://github.com/Robotter112/azuriom-sitemap/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/Robotter112/azuriom-sitemap/releases/tag/v1.2.0
[1.1.0]: https://github.com/Robotter112/azuriom-sitemap/releases/tag/v1.1.0
[1.0.0]: https://github.com/Robotter112/azuriom-sitemap/releases/tag/v1.0.0
