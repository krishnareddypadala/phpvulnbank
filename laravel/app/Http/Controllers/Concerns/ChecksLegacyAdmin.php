<?php

namespace App\Http\Controllers\Concerns;

use App\Support\LegacyQuery;
use Illuminate\Http\Request;

/**
 * Ported from src/admincheck.php.
 *
 * [VULN-06: Second-order SQL injection] Intentional.
 * The username is read from the session and interpolated. It got into the
 * session from login input, and into the database from unvalidated
 * registration, so a crafted username reaches this query on every admin check.
 *
 * Note this is a helper the controllers CHOOSE to call. It is not middleware
 * and not a policy, which is precisely why VULN-12 exists: some endpoints
 * simply forget to call it, and nothing in the framework notices.
 */
trait ChecksLegacyAdmin
{
    protected function isLegacyAdmin(Request $request): bool
    {
        if (! $request->session()->has('uname')) {
            return false;
        }

        $user = $request->session()->get('uname');
        $row = LegacyQuery::first("select * from banktable where username='$user'");

        return $row !== null && (int) $row->admin > 0;
    }
}
