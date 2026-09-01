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
        // Only registered when the option is on. Registering it unconditionally
        // and answering 404 looked harmless but was not: two plugins claiming
        // /sitemap.xml means the first one registered wins, and "indexnow"
        // sorts before "seo" - so a dormant route took the address away from
        // the plugin actually serving it.
        //
        // A file rather than the setting, because settings are not loaded yet
        // when routes are registered - setting() returns null here even when
        // the value is stored, which would leave the option permanently off.
        if (! file_exists(self::flagPath())) {
            return;
        }

        // No prefix: a sitemap is only looked for at the document root. The
        // core fallback route is registered with ->fallback() and never shadows
        // this one.
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
