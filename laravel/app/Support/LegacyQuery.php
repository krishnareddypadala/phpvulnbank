<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 * [VULN: SQLi] INTENTIONAL. DO NOT "FIX" -- see docs/vulnerabilities.md
 * ============================================================================
 *
 * The single deliberate SQL injection chokepoint for the whole application.
 *
 * Laravel's query builder and Eloquent bind every parameter, which would kill
 * all of this lab's injection lessons outright. Rather than scatter unsafe
 * queries through the controllers, every deliberately-injectable statement in
 * the app routes through this one class. That means:
 *
 *   - you can enumerate every SQLi in the codebase by finding callers of
 *     LegacyQuery, which is exactly one grep;
 *   - a reviewer has one file to audit rather than twenty;
 *   - nothing can drift into unsafe SQL by accident, because the safe path
 *     (Eloquent, DB::table, bound DB::select) is still the default everywhere
 *     else in the app.
 *
 * Every method here takes an already-interpolated SQL string and executes it
 * verbatim. That is the vulnerability. It is not an oversight, and adding
 * parameter binding here would silently disable roughly a third of the lab.
 *
 * The correct implementation, for reference, is to accept bindings:
 *
 *     DB::select('select * from banktable where acno = ?', [$acno]);
 *
 * ============================================================================
 */
class LegacyQuery
{
    /**
     * Run a raw SELECT and return every row.
     */
    public static function select(string $sql): array
    {
        return DB::select($sql);
    }

    /**
     * Run a raw SELECT and return the first row, or null.
     *
     * Returns stdClass, matching what the legacy mysqli_fetch_row() calls
     * produced. Callers that read positionally can cast with array_values().
     */
    public static function first(string $sql): ?object
    {
        return DB::select($sql)[0] ?? null;
    }

    /**
     * Run a raw INSERT / UPDATE / DELETE.
     */
    public static function run(string $sql): bool
    {
        return DB::statement($sql);
    }
}
