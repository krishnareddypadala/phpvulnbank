<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    /**
     * [VULN-47: Log injection] Intentional.
     *
     * The actor and detail are written verbatim. Usernames are never validated
     * at registration, so a newline in a username lets an attacker inject
     * fabricated log lines and frame another user:
     *
     *     attacker\n[INFO] admin approved transfer
     *
     * The fix is to strip control characters, or to log structured JSON where
     * a newline cannot be mistaken for a record boundary.
     */
    public static function record(string $action, ?string $actor, ?string $detail = null): void
    {
        static::create([
            'actor' => $actor,
            'action' => $action,
            'detail' => $detail,
            'created_at' => now(),
        ]);
    }
}
