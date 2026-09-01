<?php

namespace Azuriom\Plugin\Indexnow\Controllers;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\Indexnow\UrlSource;
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

        $urls = Cache::remember('indexnow.sitemap', now()->addMinutes(self::CACHE_MINUTES),
            fn () => UrlSource::fromCore());

        return response()
            ->view('indexnow::sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
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
