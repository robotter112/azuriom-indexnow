<?php

namespace Azuriom\Plugin\Sitemap\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\Setting;
use Azuriom\Plugin\Sitemap\Controllers\SitemapController;
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
        return view('sitemap::admin.index', [
            'sitemapUrl' => route('sitemap.index'),
            'urls' => $this->urls(),
            'cached' => Cache::has('sitemap.urls'),
            'exclude' => implode("\n", SitemapController::excludePatterns()),
            'cacheMinutes' => SitemapController::cacheMinutes(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'exclude' => ['nullable', 'string', 'max:10000'],
            'cache_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
        ]);

        // One pattern per line, blank lines dropped.
        $patterns = collect(preg_split('/\R/', $validated['exclude'] ?? ''))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        Setting::updateSettings([
            'sitemap.exclude' => json_encode($patterns),
            'sitemap.cache_minutes' => $validated['cache_minutes'],
        ]);

        Cache::forget('sitemap.urls');

        return to_route('sitemap.admin.index')
            ->with('success', trans('sitemap::admin.saved'));
    }

    public function refresh()
    {
        Cache::forget('sitemap.urls');

        return to_route('sitemap.admin.index')
            ->with('success', trans('sitemap::admin.refreshed', ['count' => count($this->urls())]));
    }

    public function check()
    {
        $urls = collect($this->urls())->pluck('loc')->take(self::CHECK_LIMIT);
        $bad = [];

        foreach ($urls as $url) {
            try {
                $status = Http::withoutRedirecting()->timeout(10)->get($url)->status();
            } catch (\Throwable $e) {
                $status = 0;
            }

            if ($status !== 200) {
                $bad[] = ['url' => $url, 'status' => $status];
            }
        }

        return to_route('sitemap.admin.index')
            ->with('checked', [
                'total' => $urls->count(),
                'capped' => count($this->urls()) > self::CHECK_LIMIT,
                'bad' => $bad,
            ]);
    }

    /**
     * The current URL list, built if it is not cached yet.
     */
    protected function urls(): array
    {
        return app(SitemapController::class)->urls();
    }
}
