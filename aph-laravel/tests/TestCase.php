<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Se ejecuta antes que los traits de prueba, incluido RefreshDatabase.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $config = $app->make('config');
        $connection = $config->get('database.connections.'.$config->get('database.default'), []);

        DatabaseGuard::check($app->configurationIsCached(), $connection);

        return $app;
    }
}
