<?php

namespace Azuriom\Plugin\Indexnow\Providers;

use Azuriom\Extensions\Plugin\BasePluginServiceProvider;
use Azuriom\Models\Page;
use Azuriom\Models\Permission;
use Azuriom\Models\Post;
use Azuriom\Plugin\Indexnow\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

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
        // Azuriom can rebuild its route cache from an outdated plugin list
        // (advanced cache on, opcache_invalidate() disabled by the host), which
        // leaves this route out. Linking to it anyway would break every admin
        // page, so the entry stays hidden until the cache is rebuilt.
        if (! Route::has('indexnow.admin.index')) {
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
}
