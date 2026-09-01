<?php

namespace Azuriom\Plugin\Sitemap\Controllers;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\Page;
use Azuriom\Models\Post;
use Azuriom\Plugin\Sitemap\CanonicalUrl;
use Azuriom\Plugin\Sitemap\Events\SitemapBuilding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

class SitemapController extends Controller
{
    /**
     * The sitemap protocol allows 50 000 URLs per file.
     *
     * ponytail: hard cut instead of a sitemap index. A site above 50 000 public
     * URLs needs a <sitemapindex> with paginated children — build that when
     * somebody actually hits the ceiling.
     */
    private const MAX_URLS = 50000;

    /**
     * Middleware aliases that mark a route as not publicly reachable.
     */
    private const PRIVATE_MIDDLEWARE = ['auth', 'guest', 'verified', 'admin-access', 'password.confirm', 'can:'];

    /**
     * URL segments that are never worth indexing, even when reachable without
     * an account: search forms, auth screens and machine endpoints.
     */
    private const SKIPPED_SEGMENTS = ['admin', 'api', 'install', 'user', 'profile', 'notifications', 'search', 'cart'];

    /**
     * The exclude patterns for the run currently building, so the settings are
     * read once per build instead of once per URL.
     */
    protected array $exclude = [];

    public function index()
    {
        return response()
            ->view('sitemap::sitemap', ['urls' => $this->urls()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * The cached URL list, built on the first call after a change.
     */
    public function urls(): array
    {
        return Cache::remember('sitemap.urls', now()->addMinutes(self::cacheMinutes()), function () {
            return $this->build();
        });
    }

    /**
     * Settings live in the core settings table, not in the config file: a
     * plugin update replaces the file and would throw the admin's list away.
     * The file only supplies the defaults for a fresh install.
     */
    public static function cacheMinutes(): int
    {
        $wert = setting('sitemap.cache_minutes');

        return $wert !== null ? (int) $wert : (self::fileConfig()['cache_minutes'] ?? 60);
    }

    /**
     * @return array<int, string>
     */
    public static function excludePatterns(): array
    {
        $gespeichert = setting('sitemap.exclude');

        if ($gespeichert !== null) {
            return json_decode($gespeichert, true) ?: [];
        }

        return self::fileConfig()['exclude'] ?? [];
    }

    public static function canonicalEnabled(): bool
    {
        $wert = setting('sitemap.canonical');

        return $wert !== null
            ? (bool) $wert
            : (self::fileConfig()['canonical'] ?? true);
    }

    /**
     * Query parameters a canonical URL keeps, because they change the content.
     *
     * @return array<int, string>
     */
    public static function canonicalKeptParameters(): array
    {
        $gespeichert = setting('sitemap.canonical_keep');

        if ($gespeichert !== null) {
            return json_decode($gespeichert, true) ?: [];
        }

        return self::fileConfig()['canonical_keep'] ?? CanonicalUrl::DEFAULT_KEPT;
    }

    /**
     * Read straight from the file instead of through config(): a plugin config
     * is not part of a cached core config, so mergeConfigFrom() silently does
     * nothing on sites that ran config:cache.
     */
    protected static function fileConfig(): array
    {
        static $config;

        return $config ??= require __DIR__.'/../../config/sitemap.php';
    }

    /**
     * Collect every publicly visible URL of this site.
     */
    protected function build(): array
    {
        $urls = new Collection();
        $this->exclude = self::excludePatterns();

        $this->addStaticRoutes($urls);
        $this->addPages($urls);
        $this->addPosts($urls);
        $this->addWiki($urls);
        $this->addForum($urls);
        $this->addChangelog($urls);
        $this->addSuggestions($urls);

        event(new SitemapBuilding($urls));

        return $urls->unique('loc')->take(self::MAX_URLS)->values()->all();
    }

    /**
     * Every registered GET route without parameters that a logged-out visitor
     * can open — the home page plus the index page of every enabled plugin.
     *
     * This is deliberately generic: a hardcoded list of known plugins would go
     * stale with every plugin somebody else installs.
     */
    protected function addStaticRoutes(Collection $urls): void
    {
        foreach (RouteFacade::getRoutes() as $route) {
            if ($route->isFallback || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if ($route->getName() === 'sitemap.index') {
                continue; // no point in listing the sitemap in itself
            }

            // A redirect is not a page; search engines report those as errors.
            if (Str::contains($route->getActionName(), 'RedirectController')) {
                continue;
            }

            if (Str::contains($route->uri(), '{')) {
                continue; // needs a parameter, handled by the model sources below
            }

            $isPrivate = collect($route->gatherMiddleware())
                ->contains(fn ($middleware) => is_string($middleware)
                    && Str::startsWith($middleware, self::PRIVATE_MIDDLEWARE));

            if ($isPrivate) {
                continue;
            }

            $segments = explode('/', trim($route->uri(), '/'));

            if (array_intersect($segments, self::SKIPPED_SEGMENTS) !== []) {
                continue;
            }

            // url('/') drops the trailing slash, which makes the home page look
            // like a different URL than the one linked everywhere else.
            $uri = $route->uri();

            $this->push($urls, $uri === '/' ? url('/').'/' : url($uri));
        }
    }

    protected function addPages(Collection $urls): void
    {
        // doesntHave('roles') keeps role-restricted pages out: they would only
        // answer with a redirect to the login form anyway.
        foreach (Page::enabled()->doesntHave('roles')->get() as $page) {
            $this->push($urls, route('pages.show', $page->slug), $page->updated_at);
        }
    }

    protected function addPosts(Collection $urls): void
    {
        foreach (Post::published()->get() as $post) {
            $this->push($urls, route('posts.show', $post->slug), $post->updated_at);
        }
    }

    protected function addWiki(Collection $urls): void
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
            // the pages themselves are real URLs.
            foreach ($category->pages as $page) {
                $this->push($urls, route('wiki.pages.show', [$category->slug, $page->slug]), $page->updated_at);
            }
        }
    }

    protected function addForum(Collection $urls): void
    {
        if (! plugins()->isEnabled('forum')) {
            return;
        }

        $forums = \Azuriom\Plugin\Forum\Models\Forum::where('is_private', false)
            ->get()
            ->filter(fn ($forum) => empty($forum->roles));

        foreach ($forums as $forum) {
            $this->push($urls, route('forum.show', $forum->slug), $forum->updated_at);
        }

        $discussions = \Azuriom\Plugin\Forum\Models\Discussion::whereIn('forum_id', $forums->modelKeys())
            ->latest('updated_at')
            ->limit(self::MAX_URLS)
            ->get();

        foreach ($discussions as $discussion) {
            $this->push($urls, route('forum.discussions.show', $discussion), $discussion->updated_at);
        }
    }

    protected function addChangelog(Collection $urls): void
    {
        if (! plugins()->isEnabled('changelog')) {
            return;
        }

        foreach (\Azuriom\Plugin\Changelog\Models\Category::enabled()->get() as $category) {
            $this->push($urls, route('changelog.categories.show', $category), $category->updated_at);
        }
    }

    protected function addSuggestions(Collection $urls): void
    {
        if (! plugins()->isEnabled('suggest')) {
            return;
        }

        $suggestions = \Azuriom\Plugin\Suggest\Models\Suggestion::latest('updated_at')
            ->limit(self::MAX_URLS)
            ->get();

        foreach ($suggestions as $suggestion) {
            $this->push($urls, route('suggest.show', $suggestion), $suggestion->updated_at);
        }
    }

    /**
     * @param  \DateTimeInterface|null  $lastModified
     */
    protected function push(Collection $urls, string $url, $lastModified = null): void
    {
        $path = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');

        if (Str::is($this->exclude, $path)) {
            return;
        }

        $urls->push([
            'loc' => $url,
            'lastmod' => $lastModified?->format(\DateTimeInterface::ATOM),
        ]);
    }
}
