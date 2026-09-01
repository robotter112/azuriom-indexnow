<?php

namespace Azuriom\Plugin\Indexnow\Providers;

use Azuriom\Extensions\Plugin\BasePluginServiceProvider;
use Azuriom\Models\Page;
use Azuriom\Models\Permission;
use Azuriom\Models\Post;
use Azuriom\Plugin\Indexnow\Client;
use Illuminate\Database\Eloquent\Model;

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
        if (! setting('indexnow.auto')) {
            return;
        }

        foreach (self::WATCHED as $model) {
            $model::saved(function (Model $record) {
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
