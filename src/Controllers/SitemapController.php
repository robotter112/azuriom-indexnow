<?php

namespace Azuriom\Plugin\Indexnow\Controllers;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\Indexnow\UrlSource;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;

/** Optional sitemap for sites without a sitemap plugin, off by default. */
class SitemapController extends Controller
{
    private const CACHE_MINUTES = 60;

    public function index()
    {
        abort_unless(setting('indexnow.serve_sitemap'), 404);

        $urls = self::urls();

        // Only until the first list is built.
        abort_if($urls === null, 503);

        return response()
            ->view('indexnow::sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * The addresses of the sitemap, built by one request at most once per CACHE_MINUTES. A failed build is
     * retried after ten minutes.
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

    /** Which route answers /sitemap.xml: this plugin, another one or none. */
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
