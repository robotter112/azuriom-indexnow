<?php

namespace Azuriom\Plugin\Indexnow;

use Azuriom\Models\Page;
use Azuriom\Models\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

/**
 * Every publicly reachable address of this site.
 *
 * Used where there is no sitemap to read: as the list handed to IndexNow, and
 * as the content of the optional sitemap this plugin can serve. Both only come
 * into play on a site running no sitemap or SEO plugin - so when this runs at
 * all it has to be complete, or it offers nothing over the plugin it stands in
 * for.
 */
class SiteUrls
{
    /**
     * The sitemap protocol allows 50 000 URLs per file. A site past that needs
     * a <sitemapindex> with paginated children, which this does not build.
     */
    private const MAX_URLS = 50000;

    /**
     * Middleware aliases that mark a route as not publicly reachable.
     */
    private const PRIVATE_MIDDLEWARE = ['auth', 'guest', 'verified', 'admin-access', 'password.confirm', 'can:'];

    /**
     * URL segments never worth indexing even when reachable without an account:
     * search forms, auth screens and machine endpoints.
     */
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

        // On the site's own address, whichever request builds the list: it is cached
        // for everyone (see Client::siteRoot()).
        return $urls->map(fn (string $url) => Client::onSiteRoot($url))->unique()->take(self::MAX_URLS)->values()->all();
    }

    /**
     * Pages of plugins this list cannot know, such as the collections of OnAir
     * Connect: a plugin answers the event "indexnow.urls" with its addresses.
     * Addresses of other sites are left out.
     */
    protected static function addFromPlugins(Collection $urls): void
    {
        // Each plugin on its own: one that fails (a stale route cache, a missing table) is left
        // out, instead of taking the addresses of the site and of every other plugin with it.
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

    /**
     * Every registered parameterless GET route a logged-out visitor can open -
     * the home page plus the index page of every enabled plugin.
     *
     * Deliberately generic: a hardcoded list of known plugins goes stale with
     * every plugin somebody else installs.
     */
    protected static function addStaticRoutes(Collection $urls): void
    {
        foreach (self::publicRoutes() as $route) {
            $urls->push($route['url']);
        }
    }

    /**
     * The public pages of one plugin: its routes by name ("games.index") or by
     * the first part of the address ("/games"). Reported when an entry of the
     * plugin changes, since those pages list its entries.
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

            // Routes ending in a file extension serve a file, not a page:
            // sitemap.xml, llms.txt and the like. Checking the extension rather
            // than the route name also covers the ones another plugin registers
            // - a sitemap listing another plugin's sitemap helps nobody.
            if (preg_match('/\.(xml|txt|json|rss|atom)$/i', $route->uri())) {
                continue;
            }

            // A redirect is not a page; search engines report those as errors.
            if (Str::contains($route->getActionName(), 'RedirectController')) {
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

            // url('/') drops the trailing slash, which makes the home page look
            // like a different address than the one linked everywhere else.
            $uri = $route->uri();

            $public[] = ['name' => $route->getName(), 'uri' => $uri, 'url' => $uri === '/' ? url('/').'/' : url($uri)];
        }

        return $public;
    }

    protected static function addPages(Collection $urls): void
    {
        // doesntHave('roles') keeps role-restricted pages out: they would only
        // answer with a redirect to the login form anyway. Only the slugs are read,
        // and no more than fit: whole pages of a big site do not fit into memory.
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
            // The category route always redirects to its first page, so only
            // the pages themselves are real addresses.
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
