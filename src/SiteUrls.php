<?php

namespace Azuriom\Plugin\Indexnow;

use Azuriom\Models\Page;
use Azuriom\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

/** Every public address of this site, for sites without a sitemap. */
class SiteUrls
{
    /** Limit of the sitemap protocol per file. */
    private const MAX_URLS = 50000;

    /**
     * Middleware aliases that mark a route as not publicly reachable.
     */
    private const PRIVATE_MIDDLEWARE = ['auth', 'guest', 'verified', 'admin-access', 'password.confirm', 'can:'];

    /** URL segments that are never indexed. */
    private const SKIPPED_SEGMENTS = ['admin', 'api', 'install', 'user', 'profile', 'notifications', 'search', 'cart'];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        $urls = new Collection();

        self::addStaticRoutes($urls);
        self::addPages($urls);
        self::addPosts($urls);
        self::addWiki($urls);
        self::addForum($urls);
        self::addChangelog($urls);
        self::addSuggestions($urls);
        self::addFromPlugins($urls);

        $excluded = self::excluded();

        // On the site's own address, since the list is cached for everyone. Others are left out.
        return $urls->map(fn (string $url) => Client::onSiteRoot($url))->filter(fn (string $url) => Client::isOwnHost($url))
            ->reject(fn (string $url) => isset($excluded[rtrim($url, '/')]))
            ->unique()->take(self::MAX_URLS)->values()->all();
    }

    /**
     * Addresses other plugins return for the event indexnow.exclude: pages that tell search engines
     * not to index them (noindex), left out of the sitemap and of reports.
     *
     * @return array<string, true> address without trailing slash => true
     */
    public static function excluded(): array
    {
        $excluded = [];

        foreach (Event::getListeners('indexnow.exclude') as $listener) {
            try {
                foreach ((array) $listener('indexnow.exclude', []) as $url) {
                    if (is_string($url) && $url !== '') {
                        $excluded[rtrim(Client::onSiteRoot($url), '/')] = true;
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $excluded;
    }

    /**
     * When the listed addresses last changed, as far as that is known: posts, pages, the lists of posts
     * and what plugins return for the event indexnow.dates. An unknown date is left out, never guessed.
     *
     * @param  array<int, string>  $urls
     * @return array<string, string> address => W3C date
     */
    public static function dates(array $urls): array
    {
        $now = now()->getTimestamp();
        $known = [];

        $note = function (string $url, mixed $date) use (&$known, $now) {
            if ($date instanceof \DateTimeInterface && ($time = $date->getTimestamp()) <= $now) {
                $key = rtrim(Client::onSiteRoot($url), '/');
                $known[$key] = max($known[$key] ?? 0, $time);
            }
        };

        try {
            $newest = null;

            foreach (Post::published()->select(['id', 'slug', 'published_at', 'updated_at'])->latest('published_at')->limit(self::MAX_URLS)->toBase()->get() as $post) {
                $changed = Carbon::parse(max((string) $post->published_at, (string) $post->updated_at));
                $note(route('posts.show', $post->slug), $changed);
                $newest = $newest === null || $changed > $newest ? $changed : $newest;
            }

            $note(route('posts.index'), $newest);
            $note(url('/'), $newest);

            foreach (Page::enabled()->doesntHave('roles')->select(['id', 'slug', 'updated_at'])->limit(self::MAX_URLS)->toBase()->get() as $page) {
                $note(route('pages.show', $page->slug), $page->updated_at !== null ? Carbon::parse($page->updated_at) : null);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        foreach (Event::getListeners('indexnow.dates') as $listener) {
            try {
                foreach ((array) $listener('indexnow.dates', []) as $url => $date) {
                    if (is_string($url)) {
                        $note($url, $date);
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $dates = [];

        foreach ($urls as $url) {
            if (($known[rtrim($url, '/')] ?? 0) > 0) {
                $dates[$url] = Carbon::createFromTimestamp($known[rtrim($url, '/')], config('app.timezone'))->toAtomString();
            }
        }

        return $dates;
    }

    /** Addresses other plugins return for the event indexnow.urls. */
    protected static function addFromPlugins(Collection $urls): void
    {
        // A failing plugin costs only its own addresses.
        foreach (Event::getListeners('indexnow.urls') as $listener) {
            try {
                $answer = $listener('indexnow.urls', []);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            foreach ((array) $answer as $url) {
                if (is_string($url) && Client::isOwnHost($url = Client::onSiteRoot($url))) {
                    $urls->push($url);
                }
            }
        }
    }

    /** Every public GET route without parameters. */
    protected static function addStaticRoutes(Collection $urls): void
    {
        foreach (self::publicRoutes() as $route) {
            $urls->push($route['url']);
        }
    }

    /**
     * The public pages of a plugin, found by route name or first URL segment.
     *
     * @return array<int, string>
     */
    public static function pluginPages(string $plugin): array
    {
        static $pages = [];

        return $pages[$plugin] ??= collect(self::publicRoutes())
            ->filter(fn (array $route) => Str::startsWith((string) $route['name'], $plugin.'.')
                || explode('/', trim($route['uri'], '/'))[0] === $plugin)
            ->pluck('url')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name: ?string, uri: string, url: string}>
     */
    protected static function publicRoutes(): array
    {
        $public = [];

        foreach (RouteFacade::getRoutes() as $route) {
            if ($route->isFallback || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            // Files such as sitemap.xml or llms.txt are no pages.
            if (preg_match('/\.(xml|txt|json|rss|atom)$/i', $route->uri())) {
                continue;
            }

            // A redirect is not a page.
            if (Str::contains($route->getActionName(), 'RedirectController')) {
                continue;
            }

            // Not always there: listed through indexnow.urls while it is.
            if ($route->getAction('sitemap') === false) {
                continue;
            }

            if (Str::contains($route->uri(), '{')) {
                continue; // needs a parameter, handled by the sources below
            }

            $isPrivate = collect($route->gatherMiddleware())
                ->contains(fn ($middleware) => is_string($middleware)
                    && Str::startsWith($middleware, self::PRIVATE_MIDDLEWARE));

            if ($isPrivate) {
                continue;
            }

            if (array_intersect(explode('/', trim($route->uri(), '/')), self::SKIPPED_SEGMENTS) !== []) {
                continue;
            }

            // url('/') drops the slash of the home page.
            $uri = $route->uri();

            $public[] = ['name' => $route->getName(), 'uri' => $uri, 'url' => $uri === '/' ? url('/').'/' : url($uri)];
        }

        return $public;
    }

    protected static function addPages(Collection $urls): void
    {
        // Without pages restricted to roles. Slugs only, to save memory.
        foreach (Page::enabled()->doesntHave('roles')->limit(self::MAX_URLS)->pluck('slug') as $slug) {
            $urls->push(route('pages.show', $slug));
        }
    }

    protected static function addPosts(Collection $urls): void
    {
        foreach (Post::published()->latest('published_at')->limit(self::MAX_URLS)->pluck('slug') as $slug) {
            $urls->push(route('posts.show', $slug));
        }
    }

    protected static function addWiki(Collection $urls): void
    {
        if (! plugins()->isEnabled('wiki')) {
            return;
        }

        $categories = \Azuriom\Plugin\Wiki\Models\Category::enabled()
            ->with('pages')
            ->get()
            ->filter(fn ($category) => empty($category->roles));

        foreach ($categories as $category) {
            // A category redirects to its first page.
            foreach ($category->pages as $page) {
                $urls->push(route('wiki.pages.show', [$category->slug, $page->slug]));
            }
        }
    }

    protected static function addForum(Collection $urls): void
    {
        if (! plugins()->isEnabled('forum')) {
            return;
        }

        $forums = \Azuriom\Plugin\Forum\Models\Forum::where('is_private', false)
            ->get()
            ->filter(fn ($forum) => empty($forum->roles));

        foreach ($forums as $forum) {
            $urls->push(route('forum.show', $forum->slug));
        }

        $discussions = \Azuriom\Plugin\Forum\Models\Discussion::whereIn('forum_id', $forums->modelKeys())
            ->latest('updated_at')
            ->limit(self::MAX_URLS)
            ->get();

        foreach ($discussions as $discussion) {
            $urls->push(route('forum.discussions.show', $discussion));
        }
    }

    protected static function addChangelog(Collection $urls): void
    {
        if (! plugins()->isEnabled('changelog')) {
            return;
        }

        foreach (\Azuriom\Plugin\Changelog\Models\Category::enabled()->get() as $category) {
            $urls->push(route('changelog.categories.show', $category));
        }
    }

    protected static function addSuggestions(Collection $urls): void
    {
        if (! plugins()->isEnabled('suggest')) {
            return;
        }

        $suggestions = \Azuriom\Plugin\Suggest\Models\Suggestion::latest('updated_at')
            ->limit(self::MAX_URLS)
            ->get();

        foreach ($suggestions as $suggestion) {
            $urls->push(route('suggest.show', $suggestion));
        }
    }
}
