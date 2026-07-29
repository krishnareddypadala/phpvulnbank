<?php

// New in the Laravel port -- the legacy app had no ledger at all. A transfer
// simply mutated two `balance` integers and left no record that it happened.
//
// Adding this table does not fix any lesson. It makes two of them visible:
//
//   VULN-17  race condition / non-atomic transfer -- with a ledger you can
//            actually see the double-spend, which was previously unprovable
//   VULN-46  absent audit trail -- money movement is recorded here, but
//            authentication and authorisation events are not recorded anywhere
//
// [VULN: A04] Deliberately no idempotency key and no unique constraint on any
// request identifier, so a replayed transfer executes twice (VULN-35).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->integer('from_acno')->nullable();
            $table->integer('to_acno')->nullable();
            $table->integer('amount');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
