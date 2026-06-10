<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Safety net: refuse to run against any database that is not a test DB.
     *
     * The container injects DB_DATABASE=wayni (production) as an OS env var,
     * which can win over phpunit.xml. If a misconfiguration ever points the
     * suite at a non-"_test" database, abort before a single migration or
     * truncation can touch real data.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) DB::connection()->getDatabaseName();

        if (! str_ends_with($database, '_test')) {
            $this->fail(sprintf(
                'Refusing to run tests against non-test database "%s". '
                . 'Run tests via "composer test" (or APP_ENV=testing DB_DATABASE=wayni_test php artisan test).',
                $database,
            ));
        }
    }
}
