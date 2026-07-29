<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\LegacyQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ported from src/login.php and src/logout.php.
 *
 * Structure is idiomatic Laravel; the credential check inside it is not, and
 * deliberately so. See docs/vulnerabilities.md for VULN-01, 02, 14, 24 and 25.
 */
class AuthController extends Controller
{
    /**
     * POST /api/v2/auth/login
     *
     * NOTE THE ABSENCE OF RATE LIMITING.
     *
     * [VULN-25: No rate limiting] Intentional. Do not "fix".
     * Laravel ships a `throttle` middleware and the api route group commonly
     * carries `throttle:api`. This route is registered without it, so the
     * password guessing and MD5 cracking exercises have no lockout to work
     * around. The fix is to add ->middleware('throttle:5,1') in routes/api.php.
     */
    public function login(Request $request)
    {
        // Accepts BOTH form-encoded and JSON bodies. That is load-bearing for
        // the CSRF lesson on transfers (see TransferController) and is kept
        // consistent across the API surface.
        $username = (string) $request->input('uname', '');
        $password = (string) $request->input('pwd', '');

        // ------------------------------------------------------------------
        // [VULN-01: SQL injection -> authentication bypass] Intentional.
        //
        // Both parameters are interpolated straight into the statement. The
        // password sits inside MySQL's MD5() call, so it is injectable too --
        // hashing it in PHP first would sanitise it and kill half the lesson.
        //
        // Reach it with:   uname = ' or '1'='1' --
        // The trailing `and active='1'` is what the comment marker defeats,
        // which is why an inactive account can be logged into this way.
        //
        // The fix is a bound statement:
        //     DB::select('select * from banktable where username = ? and password = ? and active = 1',
        //                [$username, md5($password)]);
        // ------------------------------------------------------------------
        $sql = "select * from banktable where username='$username' and password=MD5('$password') and active='1'";

        $row = LegacyQuery::first($sql);

        if ($row !== null) {
            // Session fixation IS handled here, and the ordering matters --
            // regenerate first, then write the identity, so the pre-auth
            // session ID never holds an authenticated payload. The legacy app
            // got this right and the port keeps it right. Not a lesson.
            $request->session()->regenerate();

            // The legacy app stored the SUBMITTED username in the session, not
            // the one the database returned. That is preserved exactly: an
            // injection bypass logs you in as whatever string you typed.
            $request->session()->put('uname', $username);

            // Laravel's session guard is used for the plumbing so the app
            // behaves like a real Laravel app. It is only wired up when the
            // username resolves to a genuine row; a forged UNION row still
            // authenticates through the session key above, as in the original.
            if ($user = User::where('username', $username)->first()) {
                Auth::login($user);
            }

            return response()->json([
                'status' => 'ok',
                'username' => $username,
            ]);
        }

        // ------------------------------------------------------------------
        // [VULN-02: Command injection backdoor] Intentional. DO NOT REMOVE.
        //
        // This is the single most dangerous line in the application and the
        // easiest to lose in a port, which is why it is commented this loudly.
        //
        // Ported verbatim from src/login.php:74-78. When the failed password
        // is exactly "troy", the submitted USERNAME is executed as a shell
        // command and its output returned. No credentials required.
        //
        //     POST /api/v2/auth/login   uname=id&pwd=troy
        //
        // The name is the Trojan-horse companion to tools/odysseus. It is an
        // unauthenticated remote code execution path reachable from the login
        // endpoint, and it is the main reason this lab must never be bound to
        // anything but localhost. See SECURITY.md.
        // ------------------------------------------------------------------
        if ($password === 'troy') {
            $output = shell_exec($username);

            return response($output ?? '', 200)
                ->header('Content-Type', 'text/plain');
        }

        // ------------------------------------------------------------------
        // [VULN-14: Reflected XSS] and [VULN-24: User enumeration] Intentional.
        //
        // Two things are deliberate here.
        //
        // First, the response is rendered as text/html rather than JSON. A
        // JSON API is not normally an XSS sink -- application/json does not
        // execute -- so serving an attacker-controlled string as text/html is
        // what keeps a genuine server-side reflected XSS reachable through an
        // API. Content type is a security control, not a formatting detail.
        //
        // Second, the message distinguishes "no such user" from "wrong
        // password", which is the user enumeration lesson.
        //
        // The fix for both: return a JSON error with a single generic message.
        // ------------------------------------------------------------------
        $exists = User::where('username', $username)->exists();

        $message = $exists
            ? "you are not $username"                       // right user, wrong password
            : "no such customer: $username";                // account does not exist

        return response($message, 401)
            ->header('Content-Type', 'text/html');
    }

    /**
     * POST /api/v2/auth/logout
     *
     * Ported from src/logout.php, which destroyed the session but had its
     * session_regenerate_id() commented out. Laravel's invalidate() +
     * regenerateToken() is the correct behaviour and is kept -- the legacy
     * omission was sloppiness rather than a catalogued lesson.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['status' => 'ok']);
    }
}
