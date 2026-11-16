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

    /**
     * Report what changed as soon as it is saved or deleted, which is what IndexNow
     * is for - waiting for someone to press a button defeats the purpose. Every
     * model is looked at, so pages, posts and the entries of every plugin are
     * covered (see Reporter); the report goes out after the response.
     */
    protected function watchForChanges(): void
    {
        Event::listen(['eloquent.saved: *', 'eloquent.deleted: *'], function (string $event, array $payload) {
            if (($payload[0] ?? null) instanceof Model) {
                Reporter::changed($payload[0]);
            }
        });

        // Other plugins hand over exact addresses without depending on this one:
        // event('indexnow.submit', [[$url, ...]]). Without IndexNow nobody listens.
        Event::listen('indexnow.submit', function ($urls) {
            if (is_array($urls)) {
                Reporter::queue($urls);
            }
        });
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function adminNavigation(): array
    {
        // Azuriom can build its route cache from an outdated plugin list (AzBoost
        // on, opcache_invalidate() disabled by the host), which leaves this route
        // out. Linking to it anyway would break every admin page, so the entry
        // waits until the repair below has taken effect.
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

    /**
     * Rebuild a route cache that lacks this plugin, as "Reload AzBoost" does.
     * What failed while enabling works now: this request already runs with the
     * plugin, so the rebuild reads a plugin list that contains it.
     */
    protected function repairRouteCache(): void
    {
        // At most every five minutes: the host may keep serving the old file
        // from OPcache for a moment, and a rebuild takes a second or two.
        if (! $this->app->routesAreCached() || ! Cache::add('indexnow.route_repair', true, 300)) {
            return;
        }

        // After the response, so the page that noticed it is not slowed down.
        $this->app->terminating(function () {
            try {
                $this->app->make(Optimizer::class)->reloadRoutesCache();
            } catch (Throwable $e) {
                // route:cache deletes the old file first, so the routes load
                // uncached from now on, this plugin's included.
                report($e);
            }
        });
    }
}
