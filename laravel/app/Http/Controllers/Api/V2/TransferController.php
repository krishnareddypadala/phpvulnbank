<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Support\LegacyQuery;
use Illuminate\Http\Request;

/**
 * Ported from src/transfer.php and src/transfer_csrftoken.php.
 *
 * Lessons: VULN-06, VULN-10, VULN-11, VULN-16, VULN-17, VULN-18, VULN-35.
 */
class TransferController extends Controller
{
    /**
     * POST /api/v2/transfers   (from src/transfer.php)
     *
     * [VULN-10: CSRF] Intentional. Do not "fix".
     *
     * This endpoint is reachable with a plain HTML form because:
     *   1. the api middleware group has no VerifyCsrfToken (bootstrap/app.php);
     *   2. it authenticates from the session COOKIE, giving ambient authority;
     *   3. it accepts application/x-www-form-urlencoded, so a <form> can
     *      actually forge the request.
     *
     * All three are required. Remove any one and the lesson is unreachable
     * while still looking unprotected -- see docs/api-refactor.md §5.1. The
     * PoC lives at payload/csrf/offer.html.
     *
     * [VULN-16: Business logic -- negative amounts] Intentional.
     * There is no check that amount > 0. A negative transfer moves money
     * from the destination to the sender: it is a withdrawal from any
     * account in the bank.
     *
     * [VULN-11: IDOR] Intentional. Any destination account is accepted with
     * no relationship to the sender -- no beneficiary registration, nothing.
     *
     * [VULN-17: Race condition] Intentional.
     * Read balance, compute, write back -- with no transaction, no row lock
     * and no atomic decrement. Concurrent requests interleave and money is
     * created. The ledger below makes the double-spend visible.
     *
     * [VULN-35: Replay] Intentional. No idempotency key.
     *
     * [VULN-18: Client-side-only validation] The legacy page validated the
     * account number with JavaScript isNaN(). That check lives in the browser
     * client and is, self-evidently, irrelevant to this endpoint.
     *
     * There is also no overdraft check: the sender's balance may go negative.
     */
    public function store(Request $request)
    {
        if (! $request->session()->has('uname')) {
            return response()->json(['error' => 'not authenticated'], 401);
        }

        $fuser = $request->session()->get('uname');
        $tacno = (string) $request->input('tacno', '');
        $tamount = (string) $request->input('tamount', '0');

        // [VULN-06: SQL injection] Intentional -- $tacno is interpolated into
        // both the SELECT and the UPDATE below.
        $from = LegacyQuery::first("select * from banktable where username='$fuser'");

        if ($from === null) {
            return response()->json(['error' => 'sender not found'], 404);
        }

        // No cast, no validation, no positivity check.
        $fbalance = $from->balance - $tamount;

        LegacyQuery::run("update banktable set balance='$fbalance' where username='$fuser'");

        $to = LegacyQuery::first("select * from banktable where acno='$tacno'");

        // The legacy app did not check this either: an unknown destination
        // yields a null row and the arithmetic below silently corrupts a
        // balance. Preserved.
        $tbalance = ($to->balance ?? 0) + $tamount;

        LegacyQuery::run("update banktable set balance='$tbalance' where acno='$tacno'");

        // The ledger is new in the port. It records the movement so the race
        // condition is observable; it does not prevent anything.
        Transaction::create([
            'from_acno' => $from->acno,
            'to_acno' => is_numeric($tacno) ? (int) $tacno : null,
            'amount' => is_numeric($tamount) ? (int) $tamount : 0,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'ok',
            'message' => "Transfer Completed...!! your savings account balance : $fbalance",
            'balance' => $fbalance,
        ]);
    }

    /**
     * POST /api/v2/transfers/protected   (from src/transfer_csrftoken.php)
     *
     * The remediated twin -- and, like the account lookup pair, remediated
     * for exactly one of its problems.
     *
     * CSRF IS genuinely fixed here: the request must carry Laravel's CSRF
     * token, which a cross-site form cannot obtain.
     *
     * Everything else survives untouched. SQL injection through tacno,
     * the IDOR, negative amounts, the race and the missing overdraft check
     * are all still present. Adding a CSRF token to a transfer endpoint is
     * a common real-world "fix" that leaves the money-moving logic exactly
     * as broken as it was.
     */
    public function storeProtected(Request $request)
    {
        if (! $request->session()->has('uname')) {
            return response()->json(['error' => 'not authenticated'], 401);
        }

        // The one control this twin adds. Note it is a timing-unsafe
        // comparison, matching the legacy `==` on the md5(uniqid(rand()))
        // token -- the token check itself is not the lesson, its presence is.
        if ($request->input('csrftoken') !== $request->session()->token()) {
            return response()->json(['error' => 'invalid csrf token'], 419);
        }

        return $this->store($request);
    }
}
