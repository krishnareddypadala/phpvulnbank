<?php

namespace App\Mcp\Tools;

use App\Models\AuditLog;
use App\Models\Transaction;
use App\Support\LegacyQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Transfer funds between two accounts.')]
class RunTransferTool extends Tool
{
    /**
     * [VULN-83: Privilege escalation via scope creep] Intentional.
     *
     * A money-moving tool sitting in the same server, under the same
     * credential, as read-only lookups. No single commit adding it looks
     * wrong -- the diff is always "+1 tool" -- but any caller who can reach
     * ANY tool on this server can reach this one.
     *
     * [VULN-89: Agentic workflow] This is the tool the KYC-review capstone
     * ends at. A model following instructions it read out of customer
     * feedback (VULN-77) calls this, and nothing between the injection and
     * the money movement asks a human anything.
     *
     * All the underlying transfer flaws come along unchanged: negative
     * amounts (VULN-16), no overdraft check, no ownership check (VULN-11),
     * and no idempotency (VULN-35).
     *
     * The guarded twin is RunTransferConfirmedTool.
     */
    public function handle(Request $request): Response
    {
        $from = (string) $request->get('from_username');
        $toAcno = (string) $request->get('to_acno');
        $amount = (string) $request->get('amount');

        $sender = LegacyQuery::first("select * from banktable where username='$from'");

        if ($sender === null) {
            return Response::text('No such sender: '.$from);
        }

        $newFrom = $sender->balance - $amount;
        LegacyQuery::run("update banktable set balance='$newFrom' where username='$from'");

        $target = LegacyQuery::first("select * from banktable where acno='$toAcno'");
        $newTo = ($target->balance ?? 0) + $amount;
        LegacyQuery::run("update banktable set balance='$newTo' where acno='$toAcno'");

        Transaction::create([
            'from_acno' => $sender->acno,
            'to_acno' => is_numeric($toAcno) ? (int) $toAcno : null,
            'amount' => is_numeric($amount) ? (int) $amount : 0,
            'created_at' => now(),
        ]);

        AuditLog::record('mcp.run_transfer', 'mcp-service', "from=$from to=$toAcno amount=$amount");

        return Response::text("Transfer completed. $from balance is now $newFrom");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'from_username' => $schema->string()->description('Sending account username.')->required(),
            'to_acno' => $schema->string()->description('Destination account number.')->required(),
            'amount' => $schema->string()->description('Amount to transfer.')->required(),
        ];
    }
}
