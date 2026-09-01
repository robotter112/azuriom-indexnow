<?php

namespace Azuriom\Plugin\Sitemap\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\Setting;
use Azuriom\Plugin\Sitemap\Controllers\SitemapController;
use Azuriom\Plugin\Sitemap\SeoCheck;
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
            'canonical' => SitemapController::canonicalEnabled(),
            'canonicalKeep' => implode(', ', SitemapController::canonicalKeptParameters()),
            'robots' => $this->robotsState(),
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
            'sitemap.exclude' => json_encode($patterns),
            'sitemap.cache_minutes' => $validated['cache_minutes'],
            'sitemap.canonical' => $request->boolean('canonical') ? '1' : '0',
            'sitemap.canonical_keep' => json_encode($keep),
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
                $antwort = Http::withoutRedirecting()->timeout(10)->get($url);
                $status = $antwort->status();
                // The body is already here, so the on-page checks cost nothing.
                $issues = $status === 200 ? SeoCheck::issues($antwort->body()) : [];
            } catch (\Throwable $e) {
                $status = 0;
                $issues = [];
            }

            if ($status !== 200 || $issues !== []) {
                $bad[] = ['url' => $url, 'status' => $status, 'issues' => $issues];
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
     * Write the Sitemap line into public/robots.txt.
     *
     * Crawlers look for the sitemap there; without the line they only find it
     * if somebody submitted it by hand in a webmaster console.
     */
    public function robots()
    {
        $pfad = public_path('robots.txt');
        $zeile = 'Sitemap: '.route('sitemap.index');

        if (! is_writable($pfad) && file_exists($pfad)) {
            return to_route('sitemap.admin.index')
                ->with('error', trans('sitemap::admin.robots-not-writable', ['path' => $pfad]));
        }

        $inhalt = file_exists($pfad) ? rtrim(file_get_contents($pfad), "\r\n") : "User-agent: *\nDisallow:";

        if (! str_contains($inhalt, $zeile)) {
            $inhalt .= "\n\n".$zeile;
        }

        file_put_contents($pfad, $inhalt."\n");

        return to_route('sitemap.admin.index')
            ->with('success', trans('sitemap::admin.robots-written'));
    }

    /**
     * @return array{exists: bool, hasSitemap: bool, writable: bool, path: string}
     */
    protected function robotsState(): array
    {
        $pfad = public_path('robots.txt');
        $vorhanden = file_exists($pfad);

        return [
            'exists' => $vorhanden,
            'hasSitemap' => $vorhanden
                && str_contains(file_get_contents($pfad), 'Sitemap: '.route('sitemap.index')),
            'writable' => $vorhanden ? is_writable($pfad) : is_writable(dirname($pfad)),
            'path' => $pfad,
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
