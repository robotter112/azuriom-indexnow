<?php

namespace Azuriom\Plugin\Sitemap\Providers;

use Azuriom\Extensions\Plugin\BasePluginServiceProvider;
use Azuriom\Models\Permission;
use Azuriom\Plugin\Sitemap\Commands\CheckSitemapCommand;

class SitemapServiceProvider extends BasePluginServiceProvider
{
    public function boot(): void
    {
        $this->loadViews();

        $this->loadTranslations();

        $this->registerAdminNavigation();

        Permission::registerPermissions([
            'sitemap.admin' => 'sitemap::admin.permission',
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([CheckSitemapCommand::class]);
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function adminNavigation(): array
    {
        return [
            'sitemap' => [
                'name' => trans('sitemap::admin.title'),
                'icon' => 'bi bi-diagram-3',
                'route' => 'sitemap.admin.index',
                'permission' => 'sitemap.admin',
            ],
        ];
    }
}
