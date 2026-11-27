<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class TestDatabaseGuardTest extends TestCase
{
    /** @return array<string, array{string, string, ?string}> */
    public static function unsafeConnections(): array
    {
        return [
            'base de trabajo MySQL' => ['mysql', 'sigei', null],
            'archivo SQLite existente' => ['sqlite', '/existing/database.sqlite', null],
            'URL desvía MySQL' => ['mysql', 'sigei_testing', 'mysql://example.test/sigei'],
            'URL desvía SQLite' => ['sqlite', ':memory:', 'sqlite:///existing/database.sqlite'],
        ];
    }

    #[DataProvider('unsafeConnections')]
    public function test_unsafe_database_targets_are_rejected_before_migrations(string $connection, string $database, ?string $url): void
    {
        config([
            'database.default' => $connection,
            "database.connections.{$connection}.database" => $database,
            "database.connections.{$connection}.url" => $url,
        ]);

        $this->expectException(RuntimeException::class);
        $this->assertSafeTestDatabase($this->app);
    }
}
