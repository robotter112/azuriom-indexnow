<?php

namespace Azuriom\Plugin\Indexnow\Providers;

use Azuriom\Extensions\Plugin\BasePluginServiceProvider;
use Azuriom\Models\Permission;
use Azuriom\Plugin\Indexnow\Reporter;
use Azuriom\Support\Optimizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Throwable;

class IndexnowServiceProvider extends BasePluginServiceProvider
{
    public function boot(): void
    {
        $this->loadViews();

        $this->loadTranslations();

        $this->registerAdminNavigation();

        Permission::registerPermissions([
            'indexnow.admin' => 'indexnow::admin.permission',
        ]);

        $this->watchForChanges();
    }

    /** Report every saved or deleted model after the response (see Reporter). */
    protected function watchForChanges(): void
    {
        Event::listen(['eloquent.saved: *', 'eloquent.deleted: *'], function (string $event, array $payload) {
            if (($payload[0] ?? null) instanceof Model) {
                Reporter::changed($payload[0]);
            }
        });

        // Before the roles of a page are deleted with it (see Reporter::deleting()).
        Event::listen('eloquent.deleting: *', function (string $event, array $payload) {
            if (($payload[0] ?? null) instanceof Model) {
                Reporter::deleting($payload[0]);
            }
        });

        // Other plugins report addresses with event('indexnow.submit', [[$url, ...]]).
        Event::listen('indexnow.submit', function ($urls) {
            // and list addresses with the event indexnow.urls.
            Cache::forget('indexnow.sitemap');

            if (is_array($urls)) {
                Reporter::queue($urls, handed: true);
            }
        });
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function adminNavigation(): array
    {
        // A stale route cache may lack this route: no link until it is repaired.
        if (! Route::has('indexnow.admin.index')) {
            $this->repairRouteCache();

            return [];
        }

        return [
            'indexnow' => [
                'name' => trans('indexnow::admin.title'),
                'icon' => 'bi bi-lightning-charge',
                'route' => 'indexnow.admin.index',
                'permission' => 'indexnow.admin',
            ],
        ];
    }

    /** Rebuild a route cache that lacks this plugin, as "Reload AzBoost" does. */
    protected function repairRouteCache(): void
    {
        // At most every five minutes.
        if (! $this->app->routesAreCached() || ! Cache::add('indexnow.route_repair', true, 300)) {
            return;
        }

        // After the response.
        $this->app->terminating(function () {
            try {
                $this->app->make(Optimizer::class)->reloadRoutesCache();
            } catch (Throwable $e) {
                // route:cache deleted the old file first, so routes load uncached from here on.
                report($e);
            }
        });
    }
}
