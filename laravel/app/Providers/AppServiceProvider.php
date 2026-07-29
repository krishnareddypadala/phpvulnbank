<?php

namespace App\Providers;

use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerSqliteMd5Shim();
    }

    /**
     * Teach SQLite the MySQL MD5() function.
     *
     * This is a TEST HARNESS accommodation, not a lesson and not a change to
     * any lesson. The lab's real target is MySQL, and the login query keeps
     * the legacy shape:
     *
     *     ... and password=MD5('$password') ...
     *
     * The raw password has to stay inside the SQL string, because hashing it
     * in PHP first would sanitise it and destroy injection through the
     * password parameter. That leaves the query MySQL-specific, which would
     * mean the exploitability regression tests could only run against a live
     * MySQL container.
     *
     * Registering MD5() on SQLite lets those tests run in CI with no database
     * server, against a byte-for-byte identical SQL string and an identical
     * injection surface. Nothing about the vulnerability changes.
     */
    private function registerSqliteMd5Shim(): void
    {
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            if ($event->connection->getDriverName() !== 'sqlite') {
                return;
            }

            $pdo = $event->connection->getPdo();

            if (method_exists($pdo, 'sqliteCreateFunction')) {
                $pdo->sqliteCreateFunction('MD5', fn ($value) => md5((string) $value), 1);
            }
        });
    }
}
