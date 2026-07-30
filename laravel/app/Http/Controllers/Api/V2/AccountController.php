<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Support\LegacyQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from src/profile.php, src/displaydata.php and src/displaydata_safe.php.
 *
 * Lessons: VULN-05, VULN-06, VULN-11, VULN-13, VULN-21.
 */
class AccountController extends Controller
{
    /**
     * GET /api/v2/accounts/me   (from src/profile.php)
     *
     * [VULN-06: Second-order SQL injection] Intentional.
     * The username comes from the session, but it was placed there from user
     * input at login and originally from unvalidated registration -- so a
     * username containing SQL reaches this interpolated query on every page
     * load. Second-order injection is the subtle variant: the payload is
     * stored first and fires later, far from where it entered.
     */
    public function me(Request $request)
    {
        if (! $request->session()->has('uname')) {
            return response()->json(['error' => 'not authenticated'], 401);
        }

        $user = $request->session()->get('uname');

        $row = LegacyQuery::first("select * from banktable where username='$user'");

        if ($row === null) {
            return response()->json(['error' => 'account not found'], 404);
        }

        // The whole row is returned, including the password hash and the admin
        // flag -- see the note on VULN-21 in App\Models\User. The client hides
        // fields it does not want to show, which is not the same as the server
        // not sending them.
        return response()->json([
            'acno' => $row->acno,
            'username' => $row->username,
            'email' => $row->email,
            'mobile' => $row->mobile,
            'balance' => $row->balance,
            // [VULN-13: Stored XSS] Intentional. Returned raw. The browser
            // client renders this field with innerHTML (see the render() helper
            // in resources/views/layouts/app.blade.php),
            // so a payload stored through the feedback endpoint executes in
            // whoever views the profile. The fix is to render with
            // textContent, or escape here.
            'feedback' => $row->feedback,
            'password_hash' => $row->password,
            'admin' => $row->admin,
        ]);
    }

    /**
     * GET /api/v2/accounts/{acno}   (from src/displaydata.php, the "Show Password" link)
     *
     * Three lessons in six lines.
     *
     * [VULN-05: SQL injection, numeric context] Intentional.
     * `acno=$acno` is unquoted, so no quote-breaking is needed. This is the
     * cleanest UNION SELECT target in the app -- the table has nine columns,
     * which is what a payload has to match. See docs/legacy-mapping.md §2.
     *
     *     /api/v2/accounts/0 union select 1,2,3,4,5,6,7,8,9
     *
     * [VULN-11: IDOR / broken object level authorisation] Intentional.
     * There is NO ownership check. Any authenticated user -- in fact any
     * caller at all, since the session check the legacy page skipped is
     * skipped here too -- can read any account by number. Laravel would
     * normally solve this with a policy; deliberately none is registered.
     *
     * [VULN-21: Sensitive data exposure] Intentional.
     * The response includes the MD5 password hash, feeding the offline
     * cracking exercise (VULN-15).
     *
     * The fix for all three: bind the parameter, add a Gate/policy check that
     * the acno belongs to the caller, and stop selecting the password column.
     */
    public function lookup(string $acno)
    {
        $row = LegacyQuery::first("select * from banktable where acno=$acno");

        if ($row === null) {
            return response()->json(['error' => 'no such account'], 404);
        }

        return response()->json([
            'username' => $row->username,
            'password_hash' => $row->password,
            'balance' => $row->balance,
        ]);
    }

    /**
     * GET /api/v2/accounts/{acno}/safe   (from src/displaydata_safe.php)
     *
     * The remediated twin -- and the most valuable teaching pair in the app.
     *
     * The SQL injection IS genuinely fixed: this uses a bound parameter, and
     * no injection payload will work against it.
     *
     * VULN-11 and VULN-21 are NOT fixed. There is still no ownership check
     * and it still returns the password hash. Patching the injection did not
     * patch the bug -- it patched one of three, and the two that remain are
     * the ones that actually leak the data.
     *
     * Learners should diff this against lookup() above and notice what the
     * "safe" version did not make safe.
     */
    public function lookupSafe(string $acno)
    {
        $row = DB::selectOne('select username, password, balance from banktable where acno = ?', [$acno]);

        if ($row === null) {
            return response()->json(['error' => 'no such account'], 404);
        }

        return response()->json([
            'username' => $row->username,
            'password_hash' => $row->password,
            'balance' => $row->balance,
        ]);
    }
}
