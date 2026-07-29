<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * `php artisan migrate:fresh --seed` must rebuild the entire lab from an
     * empty database. That is the single biggest thing the Laravel port buys
     * over the legacy SQL-dump workflow.
     */
    public function run(): void
    {
        $this->call([
            BankTableSeeder::class,
        ]);
    }
}
