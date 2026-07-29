<?php

namespace App\Mcp\Tools\Db;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * ============================================================================
 * [VULN-90: Unrestricted text-to-SQL] and [VULN-91: Over-privileged
 * connection] INTENTIONAL. This is the headline lesson of the direct-database
 * server.
 * ============================================================================
 *
 * "Just give the assistant a read-only database connection" is one of the most
 * common patterns in production MCP deployments, and it is where the
 * interesting failures are.
 *
 * Two things make this instructive:
 *
 * 1. IT MOOTS THE ENTIRE SQL INJECTION CURRICULUM. There is no injection when
 *    the model is simply handed the query language. The attack surface moved
 *    from the parameter to the prompt -- so every control the app built
 *    against injection is irrelevant here, and the only remaining question is
 *    whether the model can be talked into writing a hostile query.
 *
 * 2. "READ-ONLY" IS A LIE. This tool is described, named and documented as
 *    analytical. Nothing enforces that. It runs on `groot`, which the legacy
 *    schema grants ALL PRIVILEGES ON *.* WITH GRANT OPTION (VULN-22) -- so it
 *    can write, drop tables, read other schemas and create users. A
 *    description is not a control.
 *
 * [VULN-92] Note what is ABSENT: no AuditLog::record() call. Every control
 * the application layer implements -- authorisation, validation, masking,
 * rate limiting, the audit trail -- is bypassed wholesale by talking to the
 * database directly. Perform the same action through mcp-api and mcp-db, then
 * diff audit_logs: one leaves a trail, the other leaves nothing.
 *
 * The guarded twin is RunQuerySafeTool.
 */
#[Description('Run an analytical SQL query against the bank database and return the rows. Read-only.')]
class RunQueryTool extends Tool
{
    public function handle(Request $request): Response
    {
        $sql = (string) $request->get('sql');

        try {
            // No allow-list, no statement-type check, no row limit, no
            // read-only enforcement, no schema restriction.
            $rows = DB::select($sql);

            return Response::json(['rows' => $rows, 'count' => count($rows)]);
        } catch (\Throwable $e) {
            // [VULN-73: Secret exposure through errors] Intentional.
            // The raw exception goes back to the model, and Laravel's database
            // exceptions carry the full SQL and connection details. That lands
            // in the conversation transcript, the client's history, and the
            // model provider's logs.
            //
            // The fix is to map exceptions to opaque errors at the tool
            // boundary and log the detail server-side only.
            return Response::text('Query failed: '.$e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sql' => $schema->string()->description('The SQL query to execute.')->required(),
        ];
    }
}
