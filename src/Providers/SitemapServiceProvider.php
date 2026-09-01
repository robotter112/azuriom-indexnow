<?php

namespace Azuriom\Plugin\Sitemap\Providers;

use Azuriom\Extensions\Plugin\BasePluginServiceProvider;
use Azuriom\Models\Permission;
use Azuriom\Plugin\Sitemap\Commands\CheckSitemapCommand;
use Azuriom\Plugin\Sitemap\Middleware\AddCanonicalUrl;

class SitemapServiceProvider extends BasePluginServiceProvider
{
    /**
     * The plugin's global HTTP middleware stack.
     *
     * @var array<int, class-string>
     */
    protected array $middleware = [
        AddCanonicalUrl::class,
    ];

    public function register(): void
    {
        $this->registerMiddlewares();
    }

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
