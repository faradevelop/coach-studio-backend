<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $this->assertSafeTestingEnvironment($app);

        return $app;
    }

    /**
     * Refuses to run when the tests could touch a non-test database.
     * Runs BEFORE RefreshDatabase, so nothing is ever wiped by mistake.
     */
    private function assertSafeTestingEnvironment($app): void
    {
        $config = $app['config'];
        $connection = $config->get('database.default');
        $db = (array) $config->get("database.connections.{$connection}", []);
        $problems = [];

        if ($app['env'] !== 'testing') {
            $problems[] = "APP_ENV is '{$app['env']}', expected 'testing'";
        }
        if (! in_array($db['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $problems[] = 'database driver must be mysql/mariadb';
        }
        if (! in_array($db['host'] ?? null, ['127.0.0.1', 'localhost'], true)) {
            $problems[] = 'database host must be local (127.0.0.1 or localhost)';
        }
        if (! str_ends_with((string) ($db['database'] ?? ''), '_test')) {
            $problems[] = "database name must end with '_test'";
        }
        if (! empty($db['url'])) {
            $problems[] = 'DB_URL must be empty during tests';
        }

        if ($problems !== []) {
            throw new RuntimeException(
                "Unsafe test environment, aborting:\n- " . implode("\n- ", $problems)
            );
        }
    }
}
