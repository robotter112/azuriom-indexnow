<?php

namespace Azuriom\Plugin\Indexnow;

use Azuriom\Models\Page;
use Azuriom\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
 */
class Reporter
{
    public const PLUGIN_PAGE_PAUSE = 3600;

    /** @var array<string, true> */
    private static array $queue = [];

    private static bool $flushing = false;

    /** @var array<string, string>|null namespace prefix => plugin id */
    private static ?array $namespaces = null;

    public static function changed(Model $record): void
    {
        if (! self::enabled()) {
            return;
        }

        if ($record instanceof Post || $record instanceof Page) {
            self::queue([Client::urlFor($record), self::formerUrl($record)]);

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

        if (self::$queue !== [] && ! self::$flushing) {
            self::$flushing = true;
            app()->terminating(fn () => self::flush());
        }
    }

    /**
     * @param  array<int, string>  $urls
     */
    public static function queueOncePerPause(array $urls): void
    {
        self::queue(array_values(array_filter($urls, fn (string $url) => Cache::add('indexnow.reported.'.sha1($url), true, self::PLUGIN_PAGE_PAUSE))));
    }

    /**
     * Send what was collected. Never throws: a failure must not disturb anything.
     *
     * @return array{ok: bool, reason: string, status: int, count: int}|null
     */
    public static function flush(): ?array
    {
        $urls = array_keys(self::$queue);
        self::$queue = [];
        self::$flushing = false;

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
