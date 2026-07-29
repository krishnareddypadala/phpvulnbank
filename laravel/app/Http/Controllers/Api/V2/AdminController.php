<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Concerns\ChecksLegacyAdmin;
use App\Http\Controllers\Controller;
use App\Support\LegacyQuery;
use Illuminate\Http\Request;

/**
 * Ported from src/activateform.php, src/activate.php, src/validatekyc.php
 * and src/kycdownload_ssrf.php.
 *
 * Lessons: VULN-06, VULN-07, VULN-12, VULN-13, VULN-14.
 */
class AdminController extends Controller
{
    use ChecksLegacyAdmin;

    /**
     * GET /api/v2/admin/pending-activations   (from src/activateform.php)
     *
     * Correctly gated -- this one DOES check. Contrast with activate() below.
     *
     * [VULN-13: Stored XSS] Intentional. Usernames are returned raw and the
     * client renders them into an option list with innerHTML.
     */
    public function pendingActivations(Request $request)
    {
        if (! $this->isLegacyAdmin($request)) {
            return response()->json(['error' => 'You are not admin..!!'], 403);
        }

        $rows = LegacyQuery::select("select acno, username from banktable where active='0'");

        return response()->json(['pending' => $rows, 'count' => count($rows)]);
    }

    /**
     * POST /api/v2/admin/activate   (from src/activate.php)
     *
     * [VULN-12: Missing function level access control] Intentional.
     * DO NOT ADD AN ADMIN CHECK HERE.
     *
     * This is the cleanest forced-browsing lesson in the application. The
     * FORM that reaches this action is admin-gated (above). The ACTION is
     * not gated at all -- it does not call isLegacyAdmin(), and it does not
     * even require a session. Any caller can activate any account:
     *
     *     POST /api/v2/admin/activate   user=srikanth
     *
     * Gating the UI is not gating the endpoint. That is the entire lesson,
     * and it is why the API port makes it sharper than the legacy version --
     * there is no page to hide behind.
     *
     * It also completes an attack chain: registration creates accounts with
     * active=0, so an attacker registers and then activates themselves here.
     *
     * [VULN-06: SQL injection] and [VULN-14: Reflected XSS] Intentional --
     * the username is interpolated into the UPDATE and reflected into an
     * HTML response.
     */
    public function activate(Request $request)
    {
        $user = (string) $request->input('user', '');

        if ($user === '') {
            return response('User not selected', 400)->header('Content-Type', 'text/html');
        }

        LegacyQuery::run("update banktable set active='1' where username='$user'");

        return response("<p>User $user is Activated .. </p>", 200)
            ->header('Content-Type', 'text/html');
    }

    /**
     * GET /api/v2/admin/kyc   (from src/validatekyc.php)
     *
     * [VULN-12: Missing function level access control] Intentional.
     * The legacy page was reachable only by an admin-only link, and had no
     * check of its own. Preserved -- the link placement was the only control.
     * It lists every KYC document every customer has uploaded.
     */
    public function kycIndex()
    {
        $dir = public_path('images');

        if (! is_dir($dir)) {
            return response()->json(['documents' => []]);
        }

        $files = array_values(array_diff(scandir($dir) ?: [], ['.', '..']));

        return response()->json([
            'documents' => array_map(fn ($f) => [
                'name' => $f,
                'download' => '/api/v2/admin/kyc/download?file=./images/'.$f,
            ], $files),
        ]);
    }

    /**
     * GET /api/v2/admin/kyc/download?file=   (from src/kycdownload_ssrf.php)
     *
     * [VULN-07: Path traversal / LFI] Intentional.
     * The `file` parameter is passed to the filesystem with no normalisation,
     * no allow-list and no confinement to the uploads directory:
     *
     *     ?file=../../../../etc/passwd
     *     ?file=../.env                     <- leaks APP_KEY
     *
     * The fix is to accept an opaque document ID, resolve it to a path
     * server-side, and verify with realpath() that the result is inside the
     * intended directory.
     *
     * [VULN-14: Reflected XSS] The path is echoed back on failure.
     */
    public function kycDownload(Request $request)
    {
        $file = trim((string) $request->query('file', ''));

        if (! is_file($file)) {
            return response("file not found: $file", 404)
                ->header('Content-Type', 'text/html');
        }

        return response()->file($file);
    }
}
