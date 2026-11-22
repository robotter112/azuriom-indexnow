<?php

namespace Azuriom\Plugin\Indexnow\Controllers;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\Indexnow\SiteUrls;
use Azuriom\Plugin\Indexnow\UrlSource;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** Optional sitemap for sites without a sitemap plugin, off by default. */
class SitemapController extends Controller
{
    private const CACHE_MINUTES = 60;

    public function index(Request $request)
    {
        abort_unless(setting('indexnow.serve_sitemap'), 404);

        $urls = self::urls();

        // Only until the first list is built.
        abort_if($urls === null, 503);

        $body = view('indexnow::sitemap', ['urls' => $urls, 'dates' => (array) Cache::get('indexnow.sitemap.dates', [])])->render();

        // A crawler that has this very sitemap gets 304 instead of the list.
        $response = response($body)->header('Content-Type', 'application/xml; charset=UTF-8')->setEtag(sha1($body), true);
        $response->isNotModified($request);

        return $response;
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
            Cache::forever('indexnow.sitemap.dates', SiteUrls::dates($urls));
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
