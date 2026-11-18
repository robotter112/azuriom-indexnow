<?php

namespace Azuriom\Plugin\Indexnow\Controllers;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\Indexnow\UrlSource;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;

/**
 * An optional sitemap, for sites that have no other plugin producing one.
 *
 * This exists for a single reason: Google does not take part in IndexNow, so on
 * a site with no sitemap at all there is no way to reach it. It is off by
 * default and stays off wherever a sitemap or SEO plugin is installed - that
 * plugin does the job better, and two files disagreeing about what is public
 * helps nobody.
 */
class SitemapController extends Controller
{
    private const CACHE_MINUTES = 60;

    public function index()
    {
        abort_unless(setting('indexnow.serve_sitemap'), 404);

        $urls = self::urls();

        // Only before the very first list could be built, and while that build runs.
        abort_if($urls === null, 503);

        return response()
            ->view('indexnow::sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * The addresses of the sitemap, built at most once per CACHE_MINUTES for everyone.
     *
     * Visitors trigger the build, so it must not be theirs to repeat: one build at a
     * time (the others get the last list), and a build that died - a memory limit
     * or a time limit is no exception any catch could see - is not tried again for
     * ten minutes. The mark is set before building and only removed when it worked.
     *
     * @return array<int, string>|null
     */
    public static function urls(): ?array
    {
        if (($urls = Cache::get('indexnow.sitemap')) !== null) {
            return $urls;
        }

        if (Cache::has('indexnow.sitemap.failed')) {
            return Cache::get('indexnow.sitemap.last');
        }

        $build = function () {
            Cache::put('indexnow.sitemap.failed', true, now()->addMinutes(10));
            $urls = UrlSource::fromCore();
            Cache::put('indexnow.sitemap', $urls, now()->addMinutes(self::CACHE_MINUTES));
            Cache::forever('indexnow.sitemap.last', $urls);
            Cache::forget('indexnow.sitemap.failed');

            return $urls;
        };

        if (! Cache::getStore() instanceof LockProvider) {
            return $build();
        }

        $urls = Cache::lock('indexnow.sitemap.build', 120)->get($build);

        return $urls === false ? Cache::get('indexnow.sitemap.last') : $urls;
    }

    /**
     * Which route actually answers /sitemap.xml - ours, another plugin's, or
     * none. The admin page needs this to tell the truth rather than a guess:
     * when two plugins register the same URI, the load order decides.
     */
    public static function servedBy(): string
    {
        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            if ($route->uri() !== 'sitemap.xml' || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            return str_starts_with($route->getName() ?? '', 'indexnow.') ? 'self' : 'other';
        }

        return 'none';
    }
}
