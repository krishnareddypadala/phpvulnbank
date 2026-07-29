<?php

namespace App\Mcp\Tools\Db;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Fetch a customer record from the bank database by account number.')]
class GetCustomerTool extends Tool
{
    /**
     * [VULN-87: Unmasked PII in tool output] Intentional.
     *
     * Returns the whole row -- email, phone, balance and the password hash.
     * The web UI masks some of these; this does not, and that difference is
     * the lesson: MASKING IN THE PRESENTATION LAYER IS NOT MASKING.
     *
     * The consequence is worse here than in the browser version of the same
     * mistake (VULN-69). Everything a tool returns enters the model's context,
     * and with a hosted model that means customer data crosses an
     * organisational boundary and may be retained in provider logs. A
     * screenshot leaks to one person; this leaks to an API.
     *
     * [VULN-92] No AuditLog::record() -- nothing records that customer data
     * was read.
     *
     * The (partial) twin is GetCustomerMaskedTool.
     */
    public function handle(Request $request): Response
    {
        $acno = (int) $request->get('acno');

        $row = DB::selectOne('select * from banktable where acno = ?', [$acno]);

        if ($row === null) {
            return Response::text('No such account.');
        }

        return Response::json((array) $row);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'acno' => $schema->integer()->description('Account number.')->required(),
        ];
    }
}
