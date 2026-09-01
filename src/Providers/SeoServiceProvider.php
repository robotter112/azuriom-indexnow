<?php

namespace Azuriom\Plugin\Seo\Providers;

use Azuriom\Extensions\Plugin\BasePluginServiceProvider;
use Azuriom\Models\Permission;
use Azuriom\Plugin\Seo\Commands\CheckSitemapCommand;
use Azuriom\Plugin\Seo\Middleware\AddCanonicalUrl;

class SeoServiceProvider extends BasePluginServiceProvider
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
            'seo.admin' => 'seo::admin.permission',
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
            'seo' => [
                'name' => trans('seo::admin.title'),
                'icon' => 'bi bi-diagram-3',
                'route' => 'seo.admin.index',
                'permission' => 'seo.admin',
            ],
        ];
    }
}
