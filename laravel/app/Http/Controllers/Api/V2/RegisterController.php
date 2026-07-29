<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\LegacyQuery;
use Illuminate\Http\Request;

/**
 * Ported from src/api/register.php (XML) and src/api/regapi.php (JSON).
 *
 * These two were already APIs in the legacy app -- the only part of the whole
 * port that is not new work.
 *
 * Lessons: VULN-06, VULN-09, VULN-66, VULN-67.
 */
class RegisterController extends Controller
{
    /**
     * POST /api/v2/register/xml
     *
     * [VULN-09: XXE] Intentional.
     *
     * The legacy code called libxml_disable_entity_loader(true) and then
     * loaded with LIBXML_NOENT | LIBXML_DTDLOAD. On PHP 8 that function is a
     * deprecated no-op and external entity loading is off by default in
     * libxml 2.9+, which means THE LEGACY XXE PROBABLY NO LONGER FIRED on a
     * modern runtime -- see docs/legacy-mapping.md §7.6.
     *
     * So the lesson is re-enabled here explicitly, which is the honest thing
     * to do: the vulnerability is now a visible, deliberate opt-out rather
     * than an accident of an old libxml. The custom entity loader below is
     * what makes it work.
     *
     *     <?xml version="1.0"?>
     *     <!DOCTYPE root [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
     *     <root><name>&xxe;</name>...</root>
     *
     * The fix is to delete the loader registration and pass neither
     * LIBXML_NOENT nor LIBXML_DTDLOAD.
     */
    public function registerXml(Request $request)
    {
        $xml = $request->getContent();

        libxml_set_external_entity_loader(
            // [VULN-09] Deliberate: resolve external entities that libxml
            // would otherwise refuse. This single closure IS the vulnerability.
            static fn ($public, $system, $context) => $system ? fopen($system, 'r') : null
        );

        try {
            $dom = new \DOMDocument();
            $dom->loadXML($xml, LIBXML_NOENT | LIBXML_DTDLOAD);
            $info = simplexml_import_dom($dom);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } finally {
            // Reset to the default (blocking) loader. PHP does not accept the
            // value returned by the setter back as an argument, so restoring
            // $previous directly throws -- null is the documented reset.
            libxml_set_external_entity_loader(null);
        }

        return $this->createAccount(
            (string) ($info->name ?? ''),
            (string) ($info->password ?? ''),
            (string) ($info->email ?? ''),
            (string) ($info->tel ?? ''),
            []
        );
    }

    /**
     * POST /api/v2/register/json
     *
     * [VULN-67: Content type confusion] Intentional.
     * The body is read raw and JSON-decoded regardless of the declared
     * Content-Type. The legacy client already did exactly this -- regjson.php
     * posted a JSON body while declaring application/x-www-form-urlencoded --
     * so this promotes an existing quirk to a first-class lesson. It also
     * means the endpoint parses input the framework's validation layer never
     * saw.
     *
     * [VULN-66: Mass assignment] Intentional.
     * The decoded body is passed to the model wholesale. With $guarded = []
     * on User and no #[Fillable] attribute, a caller can set the columns that
     * matter:
     *
     *     {"name":"mallory","pwd":"x","admin":1,"active":1}
     *
     * That is a self-service administrator account, and it is reachable
     * without authentication.
     */
    public function registerJson(Request $request)
    {
        $decoded = json_decode($request->getContent(), true);

        if (! is_array($decoded)) {
            return response()->json(['error' => 'params missing in api request'], 400);
        }

        return $this->createAccount(
            (string) ($decoded['name'] ?? ''),
            (string) ($decoded['pwd'] ?? ''),
            (string) ($decoded['email'] ?? ''),
            (string) ($decoded['tel'] ?? ''),
            $decoded
        );
    }

    /**
     * Shared registration path.
     *
     * [VULN-06: SQL injection] Intentional -- the username is interpolated
     * into the duplicate check.
     *
     * [VULN-15: Weak hashing] Intentional -- md5(), not Hash::make().
     */
    private function createAccount(string $name, string $password, string $email, string $tel, array $raw)
    {
        if ($name === '') {
            return response()->json(['error' => 'params missing in api request'], 400);
        }

        $existing = LegacyQuery::first("select * from banktable where username='$name'");

        if ($existing !== null) {
            return response("Already registered with username <b> $name </b> or email id <b> $email </b> ..!!", 409)
                ->header('Content-Type', 'text/html');
        }

        // Accounts are created inactive and non-admin BY DEFAULT -- which is
        // why the mass assignment below matters, and why it chains with the
        // ungated activation endpoint (VULN-12).
        $attributes = [
            'username' => $name,
            'password' => md5($password),
            'balance' => 0,
            'feedback' => 'nofeedback',
            'mobile' => $tel,
            'email' => $email,
            'active' => 0,
            'admin' => 0,
        ];

        // [VULN-66] Raw request keys are merged over the defaults. Anything
        // the caller sends wins, including `admin` and `active`.
        foreach ($raw as $key => $value) {
            if (in_array($key, ['name', 'pwd', 'tel'], true)) {
                continue;
            }
            $attributes[$key] = $value;
        }

        $user = User::create($attributes);

        return response()->json([
            'status' => 'ok',
            'message' => 'Registration completed , Activation is pending',
            'user' => $user,
        ], 201);
    }
}
