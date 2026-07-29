<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against anything but an in-memory SQLite database.
     *
     * WHY THIS IS HERE, and why phpunit.xml alone is not enough:
     *
     * Most tests in this suite use RefreshDatabase, which drops and rebuilds
     * every table. Laravel resolves configuration from the REAL process
     * environment before anything phpunit.xml declares, and PHPUnit's
     * force="true" does not reliably beat an exported variable. So in the
     * docker-compose container, which exports DB_CONNECTION=mysql,
     * `php artisan test` ran RefreshDatabase against the LIVE lab database.
     *
     * It dropped every table, wiped the seeded bank data, and still reported
     * 64 passing tests -- because from the suite's point of view nothing had
     * gone wrong. That is the worst possible failure shape: destructive and
     * silent.
     *
     * This check runs before parent::setUp(), so it fires before RefreshDatabase
     * can touch anything. It fails closed: the run aborts instead of trusting
     * the environment.
     *
     * Not a lesson. A safety interlock. See also TestIsolationTest.
     */
    protected function setUp(): void
    {
        $this->refuseNonSqliteDatabase();

        parent::setUp();
    }

    private function refuseNonSqliteDatabase(): void
    {
        $connection = $_SERVER['DB_CONNECTION']
            ?? $_ENV['DB_CONNECTION']
            ?? (getenv('DB_CONNECTION') ?: 'sqlite');

        if (in_array($connection, ['sqlite', 'testing'], true)) {
            return;
        }

        throw new RuntimeException(implode("\n", [
            '',
            '=========================================================================',
            ' TEST RUN ABORTED -- this suite would have destroyed a real database.',
            '=========================================================================',
            '',
            " DB_CONNECTION is \"{$connection}\", not sqlite.",
            '',
            ' Most tests here use RefreshDatabase, which DROPS EVERY TABLE on the',
            ' connection it is given. Running them now would wipe that database and',
            ' still report success.',
            '',
            ' You are probably running inside the docker-compose container, which',
            ' exports DB_CONNECTION=mysql for the application. Do not run the suite',
            ' there against the live lab data. Either:',
            '',
            '   * run it on a checkout outside the container, or',
            '   * override explicitly:  DB_CONNECTION=sqlite DB_DATABASE=:memory: \\',
            '                             php artisan test',
            '',
            ' If you do wipe the lab, rebuild it with:',
            '   docker compose exec app php artisan migrate:fresh --seed --force',
            '',
        ]));
    }
}
