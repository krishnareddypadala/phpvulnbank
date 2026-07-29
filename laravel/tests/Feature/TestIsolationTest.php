<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Safety net: the suite must NEVER be pointed at a real database.
 *
 * Most tests here use RefreshDatabase, which drops and rebuilds every table.
 * If the connection resolves to anything other than in-memory SQLite, running
 * the suite DESTROYS whatever it is connected to -- and reports success while
 * doing it, because from the suite's point of view nothing went wrong.
 *
 * That is not hypothetical. `php artisan test` inside the deployed container
 * wiped the seeded bank data on the lab VM, because phpunit.xml declared
 * DB_CONNECTION=sqlite without force="true" and the container exports
 * DB_CONNECTION=mysql. PHPUnit left the real variable alone.
 *
 * This test fails loudly instead, before any damage is done.
 */
class TestIsolationTest extends TestCase
{
    public function test_suite_runs_against_in_memory_sqlite_only(): void
    {
        $driver = DB::connection()->getDriverName();
        $database = DB::connection()->getDatabaseName();

        $this->assertSame('sqlite', $driver, implode("\n", [
            'REFUSING TO TRUST THIS RUN: the test suite is connected to a '.$driver.' database.',
            'RefreshDatabase would drop every table in it.',
            'Check that phpunit.xml sets DB_CONNECTION with force="true" -- without it,',
            'an exported DB_CONNECTION (as docker-compose sets) wins.',
        ]));

        $this->assertSame(':memory:', $database,
            'The suite must use an in-memory database, not the file at '.$database.'.');
    }

    public function test_no_real_database_host_is_configured(): void
    {
        $this->assertEmpty(
            config('database.connections.mysql.host'),
            'A MySQL host is still configured during tests; phpunit.xml should force DB_HOST empty.'
        );
    }
}
