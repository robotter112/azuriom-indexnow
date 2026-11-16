<?php

namespace Azuriom\Plugin\Indexnow;

use Azuriom\Models\Page;
use Azuriom\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reports what changed, for every part of the site:
 *
 * - pages and news posts by their own address - also when one is deleted, taken
 *   offline or renamed, so search engines forget the old address;
 * - entries of any other plugin by the public pages of that plugin (/games,
 *   /forum, /wiki ...), found in the route table, so plugins this one has never
 *   heard of are covered too. Such a page changes with every entry saved, so it
 *   is reported at most once per PLUGIN_PAGE_PAUSE;
 * - exact addresses a plugin hands over itself with event('indexnow.submit').
 *
 * Everything that changes during one request goes out together after the
 * response: one request to IndexNow, and never a delay for the person saving.
 *
 * Before it goes out, every address is opened the way a visitor who is not
 * logged in opens it (visible()): only what such a visitor gets as a page, or
 * as gone, is reported - never what needs a login or a role, and nothing under
 * maintenance.
 */
class Reporter
{
    public const PLUGIN_PAGE_PAUSE = 3600;

    /** Answers worth reporting: a page for everyone (200), or gone for good (404, 410). */
    public const REPORTABLE = [200, 404, 410];

    /** Addresses opened per report; beyond that (a big import) the rules alone decide. */
    public const MAX_CHECKS = 25;

    /** @var array<string, true> plugin pages, reported at most once per PLUGIN_PAGE_PAUSE */
    private static array $paused = [];

    /** @var array<string, true> */
    private static array $queue = [];

    private static bool $flushing = false;

    /** @var array<int, string|null> changed pages => their former address, checked when sending */
    private static array $pages = [];

    /** @var array<int, string|null> pages being deleted => their address if visitors could open them */
    private static array $deletedPages = [];

    /** @var array<string, string>|null namespace prefix => plugin id */
    private static ?array $namespaces = null;

    public static function changed(Model $record): void
    {
        if (! self::enabled()) {
            return;
        }

        if ($record instanceof Post) {
            self::queue([Client::urlFor($record), self::formerUrl($record)]);

            return;
        }

        if ($record instanceof Page) {
            if (! $record->exists) {
                // Deleted: what was known before the database dropped its roles (deleting()).
                self::queue([self::$deletedPages[$record->getKey()] ?? null]);
                unset(self::$deletedPages[$record->getKey()]);

                return;
            }

            // Azuriom assigns the roles of a page only after saving it, so whether
            // visitors may see it is decided when the report goes out (flush()).
            self::$pages[$record->getKey()] ??= self::formerUrl($record);
            self::scheduleFlush();

            return;
        }

        $plugin = self::pluginOf($record);

        if ($plugin !== null && $plugin !== 'indexnow') {
            self::queueOncePerPause(SiteUrls::pluginPages($plugin));
        }
    }

    /**
     * @param  array<int, mixed>  $urls
     */
    public static function queue(array $urls): void
    {
        if (! self::enabled()) {
            return;
        }

        foreach ($urls as $url) {
            if (is_string($url) && $url !== '') {
                self::$queue[$url] = true;
            }
        }

        if (self::$queue !== []) {
            self::scheduleFlush();
        }
    }

    /**
     * A page is about to be deleted: note now whether visitors could open it. The
     * database removes its roles together with it, so afterwards it would look public.
     */
    public static function deleting(Model $record): void
    {
        if ($record instanceof Page && self::enabled()) {
            try {
                self::$deletedPages[$record->getKey()] = $record->is_enabled && ! $record->roles()->exists()
                    ? route('pages.show', $record->slug)
                    : null;
            } catch (Throwable) {
                self::$deletedPages[$record->getKey()] = null;
            }
        }
    }

    private static function scheduleFlush(): void
    {
        if (! self::$flushing) {
            self::$flushing = true;
            app()->terminating(fn () => self::flush());
        }
    }

    /**
     * @param  array<int, string>  $urls
     */
    public static function queueOncePerPause(array $urls): void
    {
        // The pause starts when a page is really reported (flush()), so a page that
        // was not public yet is checked again with the next change.
        $urls = array_values(array_filter($urls, fn (string $url) => ! Cache::has(self::pauseKey($url))));

        foreach ($urls as $url) {
            self::$paused[$url] = true;
        }

        self::queue($urls);
    }

    private static function pauseKey(string $url): string
    {
        return 'indexnow.reported.'.sha1($url);
    }

    /**
     * The addresses a visitor who is not logged in gets as a page (200) or as gone
     * (404, 410). A redirect (to the login form), 401, 403 and every other answer,
     * maintenance (503) included, keep an address out. Only addresses of this site
     * are opened, without cookies and without following redirects. If the site
     * cannot reach itself, the rules that chose the address decide, and addresses
     * under maintenance stay out.
     *
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    public static function visible(array $urls): array
    {
        $kept = [];

        foreach (array_values(array_filter($urls, fn (string $url) => Client::isOwnHost($url))) as $index => $url) {
            $status = $index < self::MAX_CHECKS ? self::guestStatus($url) : null;

            if ($status === null ? ! self::underMaintenance($url) : in_array($status, self::REPORTABLE, true)) {
                $kept[] = $url;
            }
        }

        return $kept;
    }

    private static function guestStatus(string $url): ?int
    {
        try {
            return Http::withoutRedirecting()
                ->timeout(8)
                ->withHeaders(['User-Agent' => 'IndexNow for Azuriom (visibility check)', 'Accept' => 'text/html'])
                ->get($url)
                ->status();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Whether Azuriom shows its maintenance page at this address to visitors, by the
     * same rules as its middleware: the whole site, or the listed paths.
     */
    private static function underMaintenance(string $url): bool
    {
        if (! setting('maintenance.enabled', false)) {
            return false;
        }

        $paths = array_map(function (string $path) {
            return trim(Str::endsWith($path, '/*') ? Str::replaceLast('/*', '*', $path) : $path, '/');
        }, (array) setting('maintenance.paths', []));

        if ($paths === []) {
            return true;
        }

        $path = trim(rawurldecode((string) parse_url($url, PHP_URL_PATH)), '/');

        return Str::is($paths, $path === '' ? '/' : $path);
    }

    /**
     * Send what was collected. Never throws: a failure must not disturb anything.
     *
     * @return array{ok: bool, reason: string, status: int, count: int}|null
     */
    public static function flush(): ?array
    {
        $pages = self::$pages;
        self::$pages = [];
        $urls = array_keys(self::$queue);
        self::$queue = [];
        self::$flushing = false;

        // Pages as they are now, roles included. A page restricted to roles is never
        // reported, not even its former address: that would make it known.
        foreach ($pages as $id => $former) {
            try {
                $page = Page::find($id);

                if ($page !== null && ! $page->roles()->exists()) {
                    array_push($urls, ...array_filter([Client::urlFor($page), $former]));
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        $paused = self::$paused;
        self::$paused = [];

        $urls = self::visible(array_values(array_unique($urls)));

        // A plugin page that goes out now starts its pause.
        $urls = array_values(array_filter($urls, fn (string $url) => ! isset($paused[$url])
            || Cache::add(self::pauseKey($url), true, self::PLUGIN_PAGE_PAUSE)));

        if ($urls === []) {
            return null;
        }

        try {
            return Client::submitOwn($urls);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Read on use, not at boot: Azuriom loads the settings only after the plugins
     * have booted. On unless switched off, like the switch on the admin page shows it.
     */
    private static function enabled(): bool
    {
        return (bool) setting('indexnow.key') && (bool) setting('indexnow.auto', true);
    }

    /**
     * The address a page or post had until this change, when visitors could open it:
     * after unpublishing, disabling or a new slug, search engines should learn it is gone.
     */
    private static function formerUrl(Model $record): ?string
    {
        try {
            $slug = $record->getOriginal('slug') ?? $record->slug;

            if ($record instanceof Post) {
                $published = $record->getOriginal('published_at');

                return $published !== null && Carbon::parse($published)->isPast() ? route('posts.show', $slug) : null;
            }

            if ($record instanceof Page) {
                return $record->getOriginal('is_enabled') ? route('pages.show', $slug) : null;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * The enabled plugin a model belongs to, by the namespace in its composer.json.
     */
    private static function pluginOf(Model $record): ?string
    {
        $class = $record::class;

        if (! str_starts_with($class, 'Azuriom\\Plugin\\')) {
            return null;
        }

        if (self::$namespaces === null) {
            self::$namespaces = [];

            foreach (plugins()->plugins() as $id => $description) {
                $autoload = (array) (((array) ($description->composer ?? []))['autoload']['psr-4'] ?? []);

                foreach (array_keys($autoload) as $namespace) {
                    self::$namespaces[Str::finish($namespace, '\\')] = $id;
                }
            }
        }

        foreach (self::$namespaces as $namespace => $id) {
            if (str_starts_with($class, $namespace)) {
                return $id;
            }
        }

        return null;
    }
}
