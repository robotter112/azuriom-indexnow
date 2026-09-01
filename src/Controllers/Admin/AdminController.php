<?php

namespace Azuriom\Plugin\Indexnow\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\Setting;
use Azuriom\Plugin\Indexnow\Client;
use Azuriom\Plugin\Indexnow\UrlSource;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index()
    {
        $key = setting('indexnow.key');
        $sitemapUrl = setting('indexnow.sitemap') ?: UrlSource::defaultSitemapUrl();
        $collected = UrlSource::collect($sitemapUrl);

        return view('indexnow::admin.index', [
            'enabled' => (bool) $key,
            'keyUrl' => $key ? url(Client::keyFileName($key)) : null,
            'sitemapUrl' => $sitemapUrl,
            'sitemapCount' => count($collected['urls']),
            'urlSource' => $collected['source'],
            'auto' => (bool) setting('indexnow.auto', true),
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

        $url = url(Client::keyFileName($key));
        $verification = Client::verifyKeyFile($key, $url);

        if (! $verification['ok']) {
            @unlink($file);

            return to_route('indexnow.admin.index')->with('error', trans(
                'indexnow::admin.verify-'.$verification['reason'],
                ['url' => $url, 'status' => $verification['status'] ?? 0]
            ));
        }

        Setting::updateSettings(['indexnow.key' => $key]);

        return to_route('indexnow.admin.index')->with('success', trans('indexnow::admin.enabled'));
    }

    public function disable()
    {
        $key = setting('indexnow.key');

        if ($key) {
            @unlink(public_path(Client::keyFileName($key)));
        }

        Setting::updateSettings(['indexnow.key' => null]);

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
            Client::hostFor($sitemapUrl),
            $key,
            url(Client::keyFileName($key)),
            $urls
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
        $validated = $request->validate([
            'sitemap' => ['nullable', 'string', 'max:500', 'url', function ($attribute, $value, $fail) {
                if ($value && ! Client::isOwnHost($value)) {
                    $fail(trans('indexnow::admin.sitemap-foreign'));
                }
            }],
            'auto' => ['nullable', 'boolean'],
        ]);

        Setting::updateSettings([
            'indexnow.sitemap' => $validated['sitemap'] ?: null,
            'indexnow.auto' => $request->boolean('auto') ? '1' : '0',
        ]);

        return to_route('indexnow.admin.index')->with('success', trans('indexnow::admin.saved'));
    }
}
