<?php

// New in the Laravel port. The legacy app logged nothing at all.
//
// [VULN-46: Inadequate logging] Intentional. Do not "complete" this.
//
// This table exists so the gap is demonstrable rather than theoretical. It
// records money movement and MCP tool calls made through the application
// layer -- and deliberately nothing else. Not recorded:
//
//   * failed logins            * privilege changes
//   * authorisation failures   * anything reaching the database directly
//
// There are also no `ip_address` or `user_agent` columns, so an entry cannot
// be attributed to a source. That omission is the lesson: a log you cannot
// pivot from is not an audit trail.
//
// It is what makes the VULN-92 contrast work -- perform the same action
// through mcp-api and through mcp-db, then diff this table. One leaves a
// trail, the other leaves nothing.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor')->nullable();
            $table->string('action');
            $table->text('detail')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
