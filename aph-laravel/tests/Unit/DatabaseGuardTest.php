<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\DatabaseGuard;

class DatabaseGuardTest extends TestCase
{
    public function test_accepts_only_the_test_database(): void
    {
        DatabaseGuard::check(false, ['database' => 'aph_test', 'url' => '']);

        $this->addToAssertionCount(1);
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    #[DataProvider('unsafeConnections')]
    public function test_rejects_unsafe_connections(bool $configurationIsCached, array $connection): void
    {
        $this->expectException(RuntimeException::class);

        DatabaseGuard::check($configurationIsCached, $connection);
    }

    /**
     * @return array<string, array{bool, array<string, mixed>}>
     */
    public static function unsafeConnections(): array
    {
        return [
            'development database' => [false, ['database' => 'aph']],
            'missing database' => [false, []],
            'cached configuration' => [true, ['database' => 'aph_test']],
            'url override' => [false, ['database' => 'aph_test', 'url' => 'mysql://localhost/aph']],
            'read override' => [false, ['database' => 'aph_test', 'read' => ['database' => 'aph']]],
            'write override' => [false, ['database' => 'aph_test', 'write' => ['database' => 'aph']]],
        ];
    }
}
