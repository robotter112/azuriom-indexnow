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
 * Reports changes after the response: pages and posts by their address (old ones included), entries of
 * other plugins by the public pages of that plugin, and addresses handed over with indexnow.submit. Only
 * what a visitor without login can open, or what is gone, is reported.
 */
class Reporter
{
    public const PLUGIN_PAGE_PAUSE = 3600;

    /** Answers worth reporting: a page for everyone (200), or gone for good (404, 410). */
    public const REPORTABLE = [200, 404, 410];

    /** Addresses opened per report; beyond that only the rules decide. */
    public const MAX_CHECKS = 25;

    /** A plugin page is opened for the check at most once per CHECK_PAUSE, whatever it answered. */
    public const CHECK_PAUSE = 600;

    /** Seconds a report may spend opening addresses. */
    public const CHECK_BUDGET = 10;

    /** User agent of the visibility check, whose requests never start a report. */
    public const PROBE_AGENT = 'IndexNow for Azuriom (visibility check)';

    /** @var array<string, true> plugin pages, reported at most once per PLUGIN_PAGE_PAUSE */
    private static array $paused = [];

    /** @var array<string, true> */
    private static array $queue = [];

    /** @var array<string, true> addresses handed over by other plugins (indexnow.submit) */
    private static array $handed = [];

    private static bool $flushing = false;

    /** @var array<int, string|null> changed pages => their former address, checked when sending */
    private static array $pages = [];

    /** @var array<int, string|null> pages being deleted => their address if visitors could open them */
    private static array $deletedPages = [];

    /** @var array<string, string>|null namespace prefix => plugin id */
    private static ?array $namespaces = null;

    public static function changed(Model $record): void
    {
        // The cached sitemap must not keep a page that was just hidden.
        if ($record instanceof Post || $record instanceof Page) {
            Cache::forget('indexnow.sitemap');
        }

        if (! self::enabled()) {
            return;
        }

        if ($record instanceof Post) {
            self::queue([Client::urlFor($record), self::formerUrl($record)]);

            return;
        }

        if ($record instanceof Page) {
            if (! $record->exists) {
                // Deleted: use what deleting() noted.
                self::queue([self::$deletedPages[$record->getKey()] ?? null]);
                unset(self::$deletedPages[$record->getKey()]);

                return;
            }

            // Roles are assigned after saving: visibility is decided in flush().
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
     * @param  bool  $handed  handed over by another plugin, which IndexNow cannot check by rules
     */
    public static function queue(array $urls, bool $handed = false): void
    {
        if (! self::enabled()) {
            return;
        }

        foreach ($urls as $url) {
            if (is_string($url) && $url !== '') {
                $url = Client::onSiteRoot($url);
                self::$queue[$url] = true;

                if ($handed) {
                    self::$handed[$url] = true;
                }
            }
        }

        if (self::$queue !== []) {
            self::scheduleFlush();
        }
    }

    /** Note whether visitors could open a page before its roles are deleted with it. */
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
        // The pause starts when the page is reported. Cache::add claims the check across requests and
        // prevents loops.
        $urls = array_map(fn (string $url) => Client::onSiteRoot($url), $urls);
        $urls = array_values(array_filter($urls, fn (string $url) => ! Cache::has(self::pauseKey($url))
            && Cache::add(self::pauseKey($url).'.checked', true, self::CHECK_PAUSE)));

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
     * The addresses visitors without login get as a page (200) or as gone (404, 410). If the site cannot
     * reach itself, the rules decide; handed-over addresses then stay out.
     *
     * @param  array<int, string>  $urls
     * @param  array<string, true>  $handed
     * @return array<int, string>
     */
    public static function visible(array $urls, array $handed = []): array
    {
        $kept = [];
        $deadline = microtime(true) + self::CHECK_BUDGET;

        foreach (array_values(array_filter($urls, fn (string $url) => Client::isOwnHost($url))) as $index => $url) {
            $status = $index < self::MAX_CHECKS && microtime(true) < $deadline ? self::guestStatus($url) : null;

            $keep = $status === null
                ? ! isset($handed[$url]) && ! self::underMaintenance($url)
                : in_array($status, self::REPORTABLE, true);

            if ($keep) {
                $kept[] = $url;
            }
        }

        return $kept;
    }

    private static function guestStatus(string $url): ?int
    {
        // Only the status counts, a few kilobytes are enough.
        return Client::fetch($url, 16384, 8, ['User-Agent' => self::PROBE_AGENT, 'Accept' => 'text/html'])['status'] ?? null;
    }

    /** Whether maintenance hides this address, by the rules of Azuriom's middleware. */
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
        $handed = self::$handed;
        self::$handed = [];
        self::$flushing = false;

        // Pages as they are now. A page restricted to roles is never reported, not even its former
        // address.
        foreach ($pages as $id => $former) {
            try {
                $page = Page::find($id);

                if ($page !== null && ! $page->roles()->exists()) {
                    array_push($urls, ...array_map(fn (string $url) => Client::onSiteRoot($url), array_filter([Client::urlFor($page), $former])));
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        $paused = self::$paused;
        self::$paused = [];

        $urls = self::visible(array_values(array_unique($urls)), $handed);

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

    /** Not read at boot, where settings are not loaded yet. Default: on. */
    private static function enabled(): bool
    {
        // A visibility check must not start another one.
        return (bool) setting('indexnow.key') && (bool) setting('indexnow.auto', true)
            && request()->userAgent() !== self::PROBE_AGENT;
    }

    /** The former public address of a changed page or post. */
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
