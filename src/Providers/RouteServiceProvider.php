<?php

namespace Azuriom\Plugin\Indexnow\Providers;

use Azuriom\Extensions\Plugin\BaseRouteServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends BaseRouteServiceProvider
{
    /**
     * Marks the optional sitemap as switched on. Written by the admin page.
     */
    public static function flagPath(): string
    {
        return storage_path('app/indexnow-serve-sitemap');
    }

    public function loadRoutes(): void
    {
        $this->mapPluginsRoutes();

        $this->mapAdminRoutes();
    }

    protected function mapPluginsRoutes(): void
    {
        // Registered only when switched on, so it never takes /sitemap.xml from another plugin. A file,
        // because settings are not loaded yet.
        if (! file_exists(self::flagPath())) {
            return;
        }

        // At the document root, without a prefix.
        Route::middleware('web')
            ->name($this->plugin->id.'.')
            ->group(plugin_path($this->plugin->id.'/routes/web.php'));
    }

    protected function mapAdminRoutes(): void
    {
        Route::prefix('admin/'.$this->plugin->id)
            ->middleware('admin-access')
            ->name($this->plugin->id.'.admin.')
            ->group(plugin_path($this->plugin->id.'/routes/admin.php'));
    }
}
