<?php

namespace Azuriom\Plugin\Indexnow\Providers;

use Azuriom\Extensions\Plugin\BasePluginServiceProvider;
use Azuriom\Models\Page;
use Azuriom\Models\Permission;
use Azuriom\Models\Post;
use Azuriom\Plugin\Indexnow\Client;
use Azuriom\Support\Optimizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Throwable;

class IndexnowServiceProvider extends BasePluginServiceProvider
{
    /**
     * Models whose changes are worth reporting. Anything saved here is a page a
     * visitor can open, which is the whole point of telling a search engine.
     *
     * @var array<int, class-string<Model>>
     */
    private const WATCHED = [
        Page::class,
        Post::class,
    ];

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
     * Report a page as soon as it is saved, which is what IndexNow is for -
     * waiting for someone to press a button defeats the purpose.
     */
    protected function watchForChanges(): void
    {
        foreach (self::WATCHED as $model) {
            $model::saved(function (Model $record) {
                // Read on save, not at boot: Azuriom loads the settings only
                // after the plugins have booted, so setting() would return the
                // default there whatever is stored. On unless switched off,
                // like the switch on the admin page shows it.
                if (! setting('indexnow.auto', true)) {
                    return;
                }

                $url = Client::urlFor($record);

                if ($url === null) {
                    return;
                }

                // After the response, so saving a post in the admin panel is
                // never slowed down by an outgoing HTTP request.
                app()->terminating(fn () => Client::submitOne($url));
            });
        }
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
