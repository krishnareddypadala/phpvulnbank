<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Ported from src/odysseus.php, src/shell.php and src/ssrf_getcontents.php.
 *
 * Lessons: VULN-03, VULN-08.
 *
 * These are the app's deliberate webshells. They were checked into the legacy
 * repository as-is and are not disguised as anything else.
 */
class UtilityController extends Controller
{
    /**
     * POST|GET /api/v2/tools/exec   (from src/odysseus.php and src/shell.php)
     *
     * [VULN-03: Command injection] Intentional. DO NOT ADD AUTHENTICATION.
     *
     * An unauthenticated webshell. The `cmd` parameter is executed by the
     * system shell and its output returned. There is no session check, no
     * allow-list, no escaping.
     *
     * The legacy app shipped three copies of this: odysseus.php (backticks),
     * shell.php (shell_exec) and images/shell.php, the last pre-planted in
     * the upload directory to prove that directory executes PHP. They are
     * consolidated here; the pre-planted copy is seeded separately by the
     * upload lesson.
     *
     * Together with the `troy` backdoor in AuthController this gives the lab
     * two unauthenticated RCE paths. See SECURITY.md.
     */
    public function exec(Request $request)
    {
        $cmd = (string) $request->input('cmd', $request->query('c', ''));

        if ($cmd === '') {
            return response('', 200)->header('Content-Type', 'text/plain');
        }

        $output = shell_exec($cmd);

        return response($output ?? '', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * GET /api/v2/tools/fetch?url=   (from src/ssrf_getcontents.php)
     *
     * [VULN-08: SSRF] Intentional.
     * file_get_contents() on an unvalidated parameter accepts every stream
     * wrapper PHP has enabled, so this reaches internal services and local
     * files alike:
     *
     *     ?url=http://169.254.169.254/          cloud metadata
     *     ?url=http://127.0.0.1:3306/           internal port probing
     *     ?url=file:///etc/passwd               local file read
     *
     * The legacy version installed an error handler that converted warnings
     * into exceptions and echoed the message. That is preserved, and it is
     * what makes this an information-disclosure oracle as well as an SSRF --
     * failed fetches report exactly WHY they failed, which is how an attacker
     * distinguishes a closed port from a filtered one.
     *
     * The fix is an allow-list of schemes and hosts, plus DNS re-resolution
     * checks against private ranges.
     */
    public function fetch(Request $request)
    {
        $url = trim((string) $request->query('url', $request->query('file', '')));

        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, $severity, $severity);
        });

        try {
            $body = file_get_contents($url);

            return response($body === false ? '' : $body, 200)
                ->header('Content-Type', 'text/plain');
        } catch (\Throwable $e) {
            return response($e->getMessage(), 200)
                ->header('Content-Type', 'text/plain');
        } finally {
            restore_error_handler();
        }
    }
}
