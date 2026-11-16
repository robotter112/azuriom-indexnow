<?php

namespace Azuriom\Plugin\IndexnowDemo\Providers;

use Azuriom\Extensions\Plugin\BaseRouteServiceProvider;
use Azuriom\Plugin\IndexnowDemo\Controllers\DemoController;
use Illuminate\Support\Facades\Route;

/**
 * A public list, a page per entry and an admin page, as most plugins have them.
 */
class RouteServiceProvider extends BaseRouteServiceProvider
{
    public function loadRoutes(): void
    {
        Route::prefix('indexnow-demo')->middleware('web')->name('indexnow-demo.')->group(function () {
            Route::get('/', [DemoController::class, 'index'])->name('index');
            Route::get('/{item}', [DemoController::class, 'show'])->name('show');
        });

        Route::prefix('admin/indexnow-demo')->middleware('admin-access')->name('indexnow-demo.admin.')->group(function () {
            Route::get('/', [DemoController::class, 'index'])->name('index');
        });
    }
}
