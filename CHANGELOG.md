# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/Robotter112/azuriom-indexnow/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/Robotter112/azuriom-indexnow/releases/tag/v2.0.0
