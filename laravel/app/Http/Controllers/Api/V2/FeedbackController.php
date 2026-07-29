<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Concerns\ChecksLegacyAdmin;
use App\Http\Controllers\Controller;
use App\Support\LegacyQuery;
use Illuminate\Http\Request;

/**
 * Ported from src/feedback.php, src/feedback_user.php and src/feedback_admin.php.
 *
 * Lessons: VULN-06, VULN-12, VULN-13.
 *
 * This pair is the app's stored-XSS chain, and the same `feedback` column is
 * the designated stored prompt-injection sink for the MCP layer -- see
 * docs/mcp-design.md §6.3. One field, two eras of the same bug class.
 */
class FeedbackController extends Controller
{
    use ChecksLegacyAdmin;

    /**
     * PUT /api/v2/feedback/me   (from src/feedback_user.php)
     *
     * [VULN-12: Missing authentication] Intentional.
     * The legacy page read $_SESSION['uname'] without ever checking that it
     * existed. That is preserved: there is no authentication guard on this
     * endpoint, so an unauthenticated caller reaches the query below with a
     * null username. The write then fails silently rather than being refused.
     *
     * [VULN-06: SQL injection] Intentional.
     * The feedback text is interpolated into an UPDATE. Because this is an
     * UPDATE rather than a SELECT, it is a good demonstration that injection
     * is not only about reading data -- a stacked or crafted payload here
     * rewrites other people's rows.
     *
     * [VULN-13: Stored XSS -- the store half] Intentional.
     * Nothing is validated, escaped or length-checked on the way in. The
     * payload sits in the database until an administrator views it.
     *
     * The fix: authenticate the request, bind the parameter, and validate.
     */
    public function update(Request $request)
    {
        $feedback = (string) $request->input('fb', '');
        $username = $request->session()->get('uname');

        LegacyQuery::run("update banktable set feedback='$feedback' where username='$username'");

        return response()->json(['status' => 'ok', 'message' => 'Feedback updated']);
    }

    /**
     * GET /api/v2/feedback   (from src/feedback_admin.php)
     *
     * [VULN-13: Stored XSS -- the render half] Intentional.
     * Every user's feedback is returned raw to an administrator. The browser
     * client renders it with innerHTML, so a payload planted by any customer
     * executes in an admin's session -- privilege escalation by way of the
     * one field customers control.
     *
     * The legacy loop started at $i=1 and therefore never displayed the first
     * user's feedback. That was an ordinary off-by-one rather than a lesson,
     * and it made the XSS demonstration unreliable -- whether the payload
     * fired depended on where the attacker's row happened to sort. Fixed
     * deliberately; see docs/legacy-mapping.md §7.4.
     */
    public function index(Request $request)
    {
        if (! $this->isLegacyAdmin($request)) {
            return response()->json(['error' => 'You are Not Admin..!!!'], 403);
        }

        $rows = LegacyQuery::select('select username, feedback from banktable');

        return response()->json(['feedback' => $rows]);
    }
}
