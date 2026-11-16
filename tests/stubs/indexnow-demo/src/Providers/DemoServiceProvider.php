<?php

namespace Azuriom\Plugin\IndexnowDemo\Providers;

use Azuriom\Extensions\Plugin\BasePluginServiceProvider;

class DemoServiceProvider extends BasePluginServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrations();
    }
}
