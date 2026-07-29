<?php

namespace App\Mcp\Tools\Db;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Fetch a customer record with personal data masked.')]
class GetCustomerMaskedTool extends Tool
{
    /**
     * The DLP twin of GetCustomerTool -- and DELIBERATELY INCOMPLETE, in the
     * same way displaydata_safe fixes the injection but not the IDOR.
     *
     * [VULN-88: Masking bypassed via the error path] Intentional.
     *
     * The success path masks correctly: email, phone and hash are redacted
     * before anything leaves the tool. The ERROR path does not -- it echoes
     * the raw row it was working on for "debuggability".
     *
     * That is how DLP controls actually fail in production. Nobody disables
     * them; they are simply implemented on the happy path only, and any input
     * that triggers a failure walks straight past. Here, asking for an account
     * whose record is incomplete returns everything unmasked.
     *
     * The lesson to draw: a masking control has to be applied at the
     * SERIALISATION BOUNDARY -- one place every response passes through --
     * not at each return statement, because someone will always add a return
     * statement.
     */
    public function handle(Request $request): Response
    {
        $acno = (int) $request->get('acno');

        $row = DB::selectOne('select * from banktable where acno = ?', [$acno]);

        if ($row === null) {
            return Response::text('No such account.');
        }

        try {
            // A realistic data-quality guard. Any record that trips it takes
            // the unmasked path below -- which is the whole point of VULN-88.
            if (! str_contains((string) $row->email, '@') || strlen((string) $row->mobile) < 4) {
                throw new \RuntimeException('malformed customer record');
            }

            return Response::json([
                'acno' => $row->acno,
                'username' => $row->username,
                'email' => $this->maskEmail((string) $row->email),
                'mobile' => $this->maskPhone((string) $row->mobile),
                'balance' => $row->balance,
                // password hash simply never selected into the response
            ]);
        } catch (\Throwable $e) {
            // [VULN-88] The masking is skipped entirely on this path.
            return Response::json([
                'error' => $e->getMessage(),
                'debug_record' => (array) $row,
            ]);
        }
    }

    private function maskEmail(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($user, 0, 1).str_repeat('*', max(strlen($user) - 1, 1)).'@'.$domain;
    }

    private function maskPhone(string $phone): string
    {
        return str_repeat('*', max(strlen($phone) - 4, 0)).substr($phone, -4);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'acno' => $schema->integer()->description('Account number.')->required(),
        ];
    }
}
