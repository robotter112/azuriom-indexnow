# Azuriom Market Rules

Binding for every extension in this repository, at every stage of work, not only before an upload.
They take precedence over our own rules: where an internal rule conflicts with them, the market rule wins
and the internal rule is changed. This file is kept identical in the theme, Connect, Game Library and IndexNow repositories.

## Market guidelines (verbatim, as of 2026-11-23)

Source: Azuriom Market guidelines. The Azuriom team reserves the right to update an extension if necessary
(the developer is contacted first). The rules are subject to change at any time: re-read them before every upload.

### General

1. An extension may be translated and must include at least the English translation. All content outside of the `lang` folder must be in English.
2. The name and description of an extension must be in English.
3. An extension must be compatible with all games, except in special cases (plugin tailored for a specific game).
4. No code obfuscation or license verification on an external server is allowed.
5. If an extension with the same goal already exists, please consider contributing to it rather than posting a new one.

### Themes

1. Themes must not directly modify any files other than the home and layout files (`home.blade.php`, `layouts/*`, `elements/*`). Of course, all pages can be modified using CSS.
2. The themes must use Bootstrap 5 (without using jQuery) and Bootstrap Icons (included in Azuriom).
3. If custom fonts are imported using external providers, use Bunny Fonts instead of Google Fonts.

Reference theme: https://github.com/AzuriomCommunity/Theme-DarkWave

### Paid extensions

1. Basic paid extensions are not accepted (in general, a plugin less complete than the Stats plugin is rejected). Plugins that add a payment method to the store plugin cannot be paid.
2. Paid extensions are priced between $1 and $5.
3. Relatively active support is required for paid extensions.
4. An inactive paid extension with problems will be removed if there is no update.
5. To post paid extensions, an author must have at least one approved free extension. An author can have up to three paid extensions for every one free extension.

## Reviewer guidance (Azuriom team, 2026-11-23)

- Plugins should avoid custom CSS as much as possible and use Bootstrap classes instead, so they integrate with existing and future themes.
- Migrations adding columns to an existing table can be merged as long as no site has installed the plugin from the market. Once published, new columns go into new migrations.
- Every uploaded update puts the extension back at the end of the review queue.

## How we apply them

### Review queue

- While an extension waits for approval, upload nothing. Collect changes and upload only what the reviewer asks for.
- After approval, bundle changes into fewer, larger updates instead of many small ones.

### Plugins

- Markup uses Bootstrap 5 components and utilities (`card`, `list-group`, `table`, `badge`, `btn`, `form-*`, grid, `d-flex`, `gap-*`, `ratio`, `text-body-secondary` …) and colours only through `--bs-*` variables, so any theme restyles the plugin.
- Own CSS only where Bootstrap has no means at all. It stays small, is scoped to the plugin's prefix, uses `--bs-*` variables and sets no colours, fonts, shadows or radii of its own. No shared custom admin stylesheet, no `<style>` blocks in views.
- Admin pages look like the rest of the Azuriom admin: its Bootstrap components, nothing on top.
- Migrations: until the first approved release, one migration per table holding all its columns; changes go into that migration. After publication a published migration is never edited; every change is a new migration.
- Merging on a site that already runs the plugin: keep the file name of the original `create` migration (it is already recorded as run), fold the columns into it, delete the `add_*` files. Data-fixing migrations for earlier development states are dropped.

### Themes

- Views only in `home.blade.php`, `layouts/*` and `elements/*`. Every other Azuriom or plugin page (posts, pages, auth, profile, notifications, errors, maintenance, plugin views) is styled through CSS only.
- Anything that needs to change content or markup of those pages belongs in a plugin, not in the theme.
- Bootstrap 5 without jQuery; icons from Bootstrap Icons as included in Azuriom.
- Fonts are served locally; if an external provider is ever needed, Bunny Fonts only.

### All extensions

- Name, description and every file outside `lang/` in English; `lang/en` is mandatory.
- Works with every game Azuriom supports; nothing game-specific is assumed.
- No obfuscation, no licence check against any server.
- Paid: plugins between $1 and $5; themes at the price level the market shows for themes. Active support, regular updates while problems are open. At least one approved free extension per three paid ones.

### Before every market upload

- [ ] Re-read the current market guidelines and update this file if they changed.
- [ ] Every point under "How we apply them" holds for the version being uploaded.
- [ ] The extension is not waiting in the review queue, or the upload is exactly what the reviewer asked for.
