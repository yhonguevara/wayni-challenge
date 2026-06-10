<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        $app->afterResolving('migrator', function ($migrator) {
            $migrator->path(__DIR__ . '/database/migrations');
        });

        return $app;
    }
}
