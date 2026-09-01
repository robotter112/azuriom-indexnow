<?php

namespace Azuriom\Plugin\Seo\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\Setting;
use Azuriom\Plugin\Seo\Controllers\SitemapController;
use Azuriom\Plugin\Seo\IndexNow;
use Azuriom\Plugin\Seo\SeoCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AdminController extends Controller
{
    /**
     * Checking every URL costs one request each, so the button is capped.
     *
     * ponytail: synchronous with a cap instead of a queued job. A site with
     * more URLs than this uses "php artisan sitemap:check", which has no cap.
     */
    private const CHECK_LIMIT = 60;

    public function index()
    {
        return view('seo::admin.index', [
            'sitemapUrl' => route('seo.index'),
            'urls' => $this->urls(),
            'cached' => Cache::has('seo.urls'),
            'exclude' => implode("\n", SitemapController::excludePatterns()),
            'cacheMinutes' => SitemapController::cacheMinutes(),
            'canonical' => SitemapController::canonicalEnabled(),
            'canonicalKeep' => implode(', ', SitemapController::canonicalKeptParameters()),
            'robots' => $this->robotsState(),
            'indexNow' => $this->indexNowState(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'exclude' => ['nullable', 'string', 'max:10000'],
            'cache_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'canonical' => ['nullable', 'boolean'],
            'canonical_keep' => ['nullable', 'string', 'max:500'],
        ]);

        // One pattern per line, blank lines dropped.
        $patterns = collect(preg_split('/\R/', $validated['exclude'] ?? ''))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        $keep = collect(explode(',', $validated['canonical_keep'] ?? ''))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->values()
            ->all();

        Setting::updateSettings([
            'seo.exclude' => json_encode($patterns),
            'seo.cache_minutes' => $validated['cache_minutes'],
            'seo.canonical' => $request->boolean('canonical') ? '1' : '0',
            'seo.canonical_keep' => json_encode($keep),
        ]);

        Cache::forget('seo.urls');

        return to_route('seo.admin.index')
            ->with('success', trans('seo::admin.saved'));
    }

    public function refresh()
    {
        Cache::forget('seo.urls');

        return to_route('seo.admin.index')
            ->with('success', trans('seo::admin.refreshed', ['count' => count($this->urls())]));
    }

    public function check()
    {
        $urls = collect($this->urls())->pluck('loc')->take(self::CHECK_LIMIT);
        $bad = [];

        foreach ($urls as $url) {
            try {
                $response = Http::withoutRedirecting()->timeout(10)->get($url);
                $status = $response->status();
                // The body is already here, so the on-page checks cost nothing.
                $issues = $status === 200 ? SeoCheck::issues($response->body()) : [];
            } catch (\Throwable $e) {
                $status = 0;
                $issues = [];
            }

            if ($status !== 200 || $issues !== []) {
                $bad[] = ['url' => $url, 'status' => $status, 'issues' => $issues];
            }
        }

        return to_route('seo.admin.index')
            ->with('checked', [
                'total' => $urls->count(),
                'capped' => count($this->urls()) > self::CHECK_LIMIT,
                'bad' => $bad,
            ]);
    }

    /**
     * Write the Sitemap line into public/robots.txt.
     *
     * Crawlers look for the sitemap there; without the line they only find it
     * if somebody submitted it by hand in a webmaster console.
     */
    public function robots()
    {
        $path = public_path('robots.txt');
        $line = 'Sitemap: '.route('seo.index');

        if (! is_writable($path) && file_exists($path)) {
            return to_route('seo.admin.index')
                ->with('error', trans('seo::admin.robots-not-writable', ['path' => $path]));
        }

        $inhalt = file_exists($path) ? rtrim(file_get_contents($path), "\r\n") : "User-agent: *\nDisallow:";

        if (! str_contains($inhalt, $line)) {
            $inhalt .= "\n\n".$line;
        }

        file_put_contents($path, $inhalt."\n");

        return to_route('seo.admin.index')
            ->with('success', trans('seo::admin.robots-written'));
    }

    /**
     * @return array{exists: bool, hasSitemap: bool, writable: bool, path: string}
     */
    protected function robotsState(): array
    {
        $path = public_path('robots.txt');
        $exists = file_exists($path);

        return [
            'exists' => $exists,
            'hasSitemap' => $exists
                && str_contains(file_get_contents($path), 'Sitemap: '.route('seo.index')),
            'writable' => $exists ? is_writable($path) : is_writable(dirname($path)),
            'path' => $path,
        ];
    }

    /**
     * Turn IndexNow on: make a key, put the key file in place, and only save
     * anything once the file has been confirmed reachable over HTTP. A key that
     * cannot be verified would make every later submission fail with 403, so it
     * is better to fail here, visibly, than silently later.
     */
    public function indexNowEnable()
    {
        $key = IndexNow::generateKey();
        $file = public_path(IndexNow::keyFileName($key));

        if (! is_writable(dirname($file))) {
            return to_route('seo.admin.index')
                ->with('error', trans('seo::admin.indexnow-not-writable', ['path' => dirname($file)]));
        }

        file_put_contents($file, $key);

        $url = url(IndexNow::keyFileName($key));
        $verification = IndexNow::verifyKeyFile($key, $url);

        if (! $verification['ok']) {
            // Do not leave a stray file behind for a setup that did not work.
            @unlink($file);

            return to_route('seo.admin.index')->with('error', trans(
                'seo::admin.indexnow-verify-'.$verification['reason'],
                ['url' => $url, 'status' => $verification['status'] ?? 0]
            ));
        }

        Setting::updateSettings(['seo.indexnow_key' => $key]);

        return to_route('seo.admin.index')->with('success', trans('seo::admin.indexnow-enabled'));
    }

    /**
     * Turn it off again and remove the key file.
     */
    public function indexNowDisable()
    {
        $key = setting('seo.indexnow_key');

        if ($key) {
            @unlink(public_path(IndexNow::keyFileName($key)));
        }

        Setting::updateSettings(['seo.indexnow_key' => null]);

        return to_route('seo.admin.index')->with('success', trans('seo::admin.indexnow-disabled'));
    }

    /**
     * Submit every URL of the sitemap.
     */
    public function indexNowSubmit()
    {
        $key = setting('seo.indexnow_key');

        if (! $key) {
            return to_route('seo.admin.index')->with('error', trans('seo::admin.indexnow-not-enabled'));
        }

        $sitemapUrl = route('seo.index');
        $result = IndexNow::submit(
            IndexNow::hostFor($sitemapUrl),
            $key,
            url(IndexNow::keyFileName($key)),
            collect($this->urls())->pluck('loc')->all()
        );

        $text = trans('seo::admin.indexnow-result-'.$result['reason'], [
            'count' => $result['count'],
            'status' => $result['status'],
        ]);

        return to_route('seo.admin.index')
            ->with($result['ok'] ? 'success' : 'error', $text);
    }

    /**
     * @return array{enabled: bool, key: ?string, keyUrl: ?string}
     */
    protected function indexNowState(): array
    {
        $key = setting('seo.indexnow_key');

        return [
            'enabled' => (bool) $key,
            'key' => $key,
            'keyUrl' => $key ? url(IndexNow::keyFileName($key)) : null,
        ];
    }

    /**
     * The current URL list, built if it is not cached yet.
     */
    protected function urls(): array
    {
        return app(SitemapController::class)->urls();
    }
}
