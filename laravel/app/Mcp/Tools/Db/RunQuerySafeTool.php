<?php

namespace App\Mcp\Tools\Db;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Run one of a fixed set of named reports. Does not accept SQL.')]
class RunQuerySafeTool extends Tool
{
    /**
     * GUARDED TWIN of RunQueryTool -- the input-control lesson.
     *
     * The control is not "validate the SQL". Attempting to sanitise
     * model-generated SQL is a losing game: you would be writing a SQL parser
     * to decide whether a query is safe, and any gap is a bypass.
     *
     * The control is to NOT ACCEPT SQL AT ALL. The model chooses from named
     * reports and supplies typed parameters; the SQL is written by a human,
     * bound, and never composed at runtime. The tool's capability is now a
     * fixed, reviewable list rather than "whatever the database can do".
     *
     * This is the same shape as the fix for classic injection -- move from
     * composing a query to selecting one -- applied one layer out.
     */
    private const REPORTS = [
        'total_balance' => 'select sum(balance) as total_balance from banktable',
        'account_count' => 'select count(*) as account_count from banktable',
        'pending_activations' => 'select count(*) as pending from banktable where active = 0',
        'balance_by_account' => 'select acno, balance from banktable where acno = ?',
    ];

    public function handle(Request $request): Response
    {
        $name = (string) $request->get('report');

        if (! array_key_exists($name, self::REPORTS)) {
            return Response::text(
                'Unknown report. Available: '.implode(', ', array_keys(self::REPORTS))
            );
        }

        $sql = self::REPORTS[$name];
        $bindings = [];

        if (str_contains($sql, '?')) {
            $acno = $request->get('acno');

            if (! is_numeric($acno)) {
                return Response::text('This report requires a numeric acno.');
            }

            $bindings[] = (int) $acno;
        }

        try {
            $rows = DB::select($sql, $bindings);

            return Response::json(['report' => $name, 'rows' => $rows]);
        } catch (\Throwable) {
            // Opaque by design -- the detail is logged server-side, not
            // returned to the model. Contrast VULN-73 in the unguarded twin.
            return Response::text('Report failed. See server logs.');
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'report' => $schema->string()
                ->description('Report name: total_balance, account_count, pending_activations, balance_by_account.')
                ->required(),
            'acno' => $schema->integer()->description('Account number, for balance_by_account only.'),
        ];
    }
}
