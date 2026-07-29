<?php

// [VULN: SCHEMA] Intentional. Do not "fix" -- see docs/vulnerabilities.md
//
// Faithful reproduction of the legacy `banktable` from dbscript/banktable.sql:
// one flat table holding user, account, balance and feedback together.
//
// THE COLUMN ORDER IS LOAD-BEARING. Every SQL injection payload published
// against this lab, and every positional `$row[N]` read in the legacy PHP it
// was ported from, depends on exactly this ordering:
//
//     0 acno   1 username   2 password   3 balance
//     4 email  5 mobile     6 feedback   7 active    8 admin
//
// Normalising this into users/accounts/profiles -- the obvious "fix" -- would
// silently break every existing exploit and write-up. See the analysis in
// docs/legacy-mapping.md §7.1.
//
// There are deliberately NO timestamp columns. `UNION SELECT` payloads have to
// match the column count, and appending created_at/updated_at would turn every
// nine-column payload into a ten-column error.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banktable', function (Blueprint $table) {
            $table->integer('acno', true);              // 0 - PK, auto-increment
            $table->string('username', 100);            // 1 - no unique constraint, as in the legacy schema
            $table->string('password', 100);            // 2 - unsalted MD5 (VULN-15)
            $table->integer('balance');                 // 3
            $table->string('email', 100);               // 4
            $table->string('mobile', 14);               // 5
            $table->string('feedback', 1000);           // 6 - stored XSS sink (VULN-13)
            $table->tinyInteger('active')->default(0);  // 7 - gate in the login query
            $table->tinyInteger('admin')->default(0);   // 8 - the only role marker
        });

        // Laravel plumbing. Not part of the legacy schema and not a lesson.
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->integer('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('banktable');
    }
};
