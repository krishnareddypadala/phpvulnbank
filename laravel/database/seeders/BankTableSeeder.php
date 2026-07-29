<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The four demo accounts from dbscript/banktable.sql, reproduced exactly.
 *
 * [VULN-15: Weak password hashing] Intentional. Do not "fix".
 * Passwords are stored as unsalted MD5, which is the whole point of the
 * offline-cracking lesson. Laravel's Hash::make() (bcrypt) is deliberately
 * bypassed here and in every write path. If a future contributor "upgrades"
 * this to bcrypt, the cracking exercise silently disappears.
 *
 * `srikanth` is inactive on purpose -- he is the subject of the account
 * activation flow, and the login query gates on active='1'.
 */
class BankTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('banktable')->insert([
            [
                'acno' => 1, 'username' => 'krishna', 'password' => md5('happy123$'),
                'balance' => 2840, 'email' => 'test@test.com', 'mobile' => '9876543210',
                'feedback' => 'krishnarp', 'active' => 1, 'admin' => 0,
            ],
            [
                'acno' => 2, 'username' => 'admin', 'password' => md5('krishna1$'),
                'balance' => 4528, 'email' => 'admin@test.com', 'mobile' => '9876543201',
                'feedback' => 'hello', 'active' => 1, 'admin' => 1,
            ],
            [
                'acno' => 3, 'username' => 'murali', 'password' => md5('happy1$'),
                'balance' => 1030, 'email' => 'tes@test.com', 'mobile' => '8765432190',
                'feedback' => 'hello', 'active' => 1, 'admin' => 0,
            ],
            [
                'acno' => 4, 'username' => 'srikanth', 'password' => md5('test123$'),
                'balance' => 1020, 'email' => 'test@test.com', 'mobile' => '7654321098',
                'feedback' => 'hello', 'active' => 0, 'admin' => 0,
            ],
        ]);
    }
}
