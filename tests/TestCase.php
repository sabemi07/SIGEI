<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $this->assertSafeTestDatabase($app);

        return $app;
    }

    protected function assertSafeTestDatabase(Application $app): void
    {
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if ($app['config']->get("database.connections.{$connection}.url")) {
            throw new RuntimeException('DB_URL debe estar vacío durante las pruebas; configura explícitamente la base dedicada.');
        }

        if ($connection === 'sqlite' && $database === ':memory:') {
            return;
        }

        if ($connection === 'mysql' && is_string($database)
            && preg_match('/^sigei_testing(?:_[a-z0-9]+)?$/', $database) === 1) {
            return;
        }

        throw new RuntimeException('Las pruebas requieren SQLite :memory: o una base MySQL dedicada llamada sigei_testing (o sigei_testing_sufijo).');
    }
}
