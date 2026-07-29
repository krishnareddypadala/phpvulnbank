<?php

namespace App\Mcp\Tools;

use App\Models\AuditLog;
use App\Support\LegacyQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Look up the balance and details of a customer account by account number.')]
class GetBalanceTool extends Tool
{
    /**
     * [VULN-82: No object-level authorisation at the tool layer] Intentional.
     *
     * VULN-11 reproduced one layer out. There is no check that the caller owns
     * the account, because there is no caller identity to check -- see
     * VULN-81 in ApiServer. `get_balance(2)` returns the administrator's row
     * to anyone who can reach the tool.
     *
     * [VULN-87: Unmasked sensitive data] Intentional.
     * The password hash is returned. Everything a tool returns enters the
     * model's context and, with a hosted model, leaves the environment
     * entirely -- a consequence the web version of this bug does not have.
     */
    public function handle(Request $request): Response
    {
        $acno = (string) $request->get('acno');

        // [VULN-05 at the tool layer] Interpolated, exactly as the HTTP
        // endpoint does. Reachable only because the schema below is
        // deliberately permissive.
        $row = LegacyQuery::first("select * from banktable where acno=$acno");

        if ($row === null) {
            return Response::text('No such account: '.$acno);
        }

        // The API-backed server DOES leave a trail. The direct-database server
        // does not -- that contrast is VULN-92.
        AuditLog::record('mcp.get_balance', 'mcp-service', 'acno='.$acno);

        return Response::json([
            'acno' => $row->acno,
            'username' => $row->username,
            'balance' => $row->balance,
            'email' => $row->email,
            'mobile' => $row->mobile,
            'password_hash' => $row->password,
            'admin' => $row->admin,
        ]);
    }

    /**
     * [VULN: SCHEMA OPT-OUT] Intentional. Do not tighten this type.
     *
     * `acno` is an account number and the honest type is integer. Typed
     * honestly, the MCP framework would VALIDATE the argument and reject an
     * injection payload before handle() ever ran -- the lesson would die at
     * the schema layer, before reaching LegacyQuery, and nobody would notice.
     *
     * This is the same pattern as Blade auto-escaping and Eloquent binding,
     * one layer further out: the framework is safe by default and the lesson
     * requires an explicit opt-out. It also means there are now TWO places a
     * lesson can be silently repaired -- the code and the schema.
     *
     * See docs/mcp-design.md §5.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'acno' => $schema->string()
                ->description('Account number to look up.')
                ->required(),
        ];
    }
}
