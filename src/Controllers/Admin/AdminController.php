<?php

namespace Azuriom\Plugin\Indexnow\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\Setting;
use Azuriom\Plugin\Indexnow\Client;
use Azuriom\Plugin\Indexnow\Controllers\SitemapController;
use Azuriom\Plugin\Indexnow\Providers\RouteServiceProvider;
use Azuriom\Plugin\Indexnow\UrlSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class AdminController extends Controller
{
    public function index()
    {
        $key = setting('indexnow.key');

        // Switched on before 2.3.1, when the site's address was not kept yet: the
        // admin's own request is the one to trust, not the next visitor's.
        if ($key && ! setting('indexnow.site') && ($root = Client::requestRoot()) !== null) {
            Setting::updateSettings(['indexnow.site' => $root]);
        }

        $sitemapUrl = setting('indexnow.sitemap') ?: UrlSource::defaultSitemapUrl();
        $collected = self::collected($sitemapUrl);

        return view('indexnow::admin.index', [
            'enabled' => (bool) $key,
            'keyUrl' => $key ? Client::keyLocation($key) : null,
            'sitemapUrl' => $sitemapUrl,
            'sitemapCount' => count($collected['urls']),
            'urlSource' => $collected['source'],
            'auto' => (bool) setting('indexnow.auto', true),
            'serveSitemap' => (bool) setting('indexnow.serve_sitemap'),
            'sitemapServedBy' => SitemapController::servedBy(),
            'ownSitemapUrl' => Client::siteRoot().'/sitemap.xml',
            'last' => Client::last(),
            'today' => Client::today(),
        ]);
    }

    /**
     * Turn it on: make a key, put the key file in place, and only save anything
     * once the file has been confirmed reachable over HTTP. A key that cannot be
     * verified would make every later submission fail with 403, so it is better
     * to fail here, visibly, than silently later.
     */
    public function enable()
    {
        $key = Client::generateKey();
        $file = public_path(Client::keyFileName($key));

        if (! is_writable(dirname($file))) {
            return to_route('indexnow.admin.index')
                ->with('error', trans('indexnow::admin.not-writable', ['path' => dirname($file)]));
        }

        file_put_contents($file, $key);

        // The address the key file is verified at becomes the site's address for
        // everything IndexNow builds later (Client::siteRoot()).
        $root = Client::requestRoot() ?? Client::siteRoot();
        $url = $root.'/'.Client::keyFileName($key);
        $verification = Client::verifyKeyFile($key, $url);

        if (! $verification['ok']) {
            @unlink($file);

            return to_route('indexnow.admin.index')->with('error', trans(
                'indexnow::admin.verify-'.$verification['reason'],
                ['url' => $url, 'status' => $verification['status'] ?? 0]
            ));
        }

        // Switched on again: the old key must not stay valid.
        $old = setting('indexnow.key');

        if ($old && $old !== $key) {
            @unlink(public_path(Client::keyFileName($old)));
        }

        Setting::updateSettings(['indexnow.key' => $key, 'indexnow.site' => $root]);

        return to_route('indexnow.admin.index')->with('success', trans('indexnow::admin.enabled'));
    }

    public function disable()
    {
        $key = setting('indexnow.key');

        if ($key) {
            @unlink(public_path(Client::keyFileName($key)));
        }

        Setting::updateSettings(['indexnow.key' => null, 'indexnow.site' => null]);

        return to_route('indexnow.admin.index')->with('success', trans('indexnow::admin.disabled'));
    }

    /**
     * Submit every URL of the sitemap.
     */
    public function submit()
    {
        $key = setting('indexnow.key');

        if (! $key) {
            return to_route('indexnow.admin.index')->with('error', trans('indexnow::admin.not-enabled'));
        }

        $sitemapUrl = setting('indexnow.sitemap') ?: UrlSource::defaultSitemapUrl();
        $urls = UrlSource::collect($sitemapUrl)['urls'];

        if ($urls === []) {
            return to_route('indexnow.admin.index')
                ->with('error', trans('indexnow::admin.no-urls'));
        }

        $result = Client::submit(
            Client::hostFor(Client::siteRoot()),
            $key,
            Client::keyLocation($key),
            array_values(array_filter($urls, fn (string $url) => Client::isOwnHost($url)))
        );

        return to_route('indexnow.admin.index')->with(
            $result['ok'] ? 'success' : 'error',
            trans('indexnow::admin.result-'.$result['reason'], [
                'count' => $result['count'],
                'status' => $result['status'],
            ])
        );
    }

    public function update(Request $request)
    {
        $wasServing = (bool) setting('indexnow.serve_sitemap');

        $validated = $request->validate([
            'sitemap' => ['nullable', 'string', 'max:500', 'url', function ($attribute, $value, $fail) {
                if ($value && ! Client::isOwnHost($value)) {
                    $fail(trans('indexnow::admin.sitemap-foreign'));
                }
            }],
            'auto' => ['nullable', 'boolean'],
            'serve_sitemap' => ['nullable', 'boolean'],
        ]);

        $sitemapBefore = setting('indexnow.sitemap') ?: UrlSource::defaultSitemapUrl();

        Setting::updateSettings([
            'indexnow.sitemap' => $validated['sitemap'] ?: null,
            'indexnow.auto' => $request->boolean('auto') ? '1' : '0',
            'indexnow.serve_sitemap' => $request->boolean('serve_sitemap') ? '1' : '0',
        ]);

        Cache::forget('indexnow.sitemap');
        Cache::forget(self::collectedKey($sitemapBefore));
        Cache::forget(self::collectedKey(setting('indexnow.sitemap') ?: UrlSource::defaultSitemapUrl()));

        // The route is registered from a file rather than this setting, because
        // settings are not loaded yet at that point. Keep the two in step and
        // drop the cached route table, which was built for the old state.
        if ($request->boolean('serve_sitemap') !== $wasServing) {
            $flag = RouteServiceProvider::flagPath();

            if ($request->boolean('serve_sitemap')) {
                @file_put_contents($flag, '');
            } else {
                @unlink($flag);
            }

            Artisan::call('route:clear');
        }

        return to_route('indexnow.admin.index')->with('success', trans('indexnow::admin.saved'));
    }

    /**
     * The addresses the admin page counts. The page used to fetch the sitemap over HTTP on
     * every view: the site asking itself through its proxies took half a second and more.
     * The sitemap this plugin serves comes from the cache that serves /sitemap.xml, any
     * other one is read at most every ten minutes.
     *
     * @return array{urls: array<int, string>, source: string}
     */
    private static function collected(string $sitemapUrl): array
    {
        if (setting('indexnow.serve_sitemap') && SitemapController::servedBy() === 'self' && $sitemapUrl === UrlSource::defaultSitemapUrl()) {
            return ['urls' => SitemapController::urls() ?? [], 'source' => 'sitemap'];
        }

        return Cache::remember(self::collectedKey($sitemapUrl), now()->addMinutes(10), fn () => UrlSource::collect($sitemapUrl));
    }

    private static function collectedKey(string $sitemapUrl): string
    {
        return 'indexnow.collected.'.md5($sitemapUrl);
    }
}
