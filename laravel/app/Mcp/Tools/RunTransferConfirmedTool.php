<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Prepare a fund transfer for human approval. Does NOT move money; returns a summary a person must confirm out of band.')]
class RunTransferConfirmedTool extends Tool
{
    /**
     * GUARDED TWIN of RunTransferTool -- and the most important control in
     * the whole MCP layer.
     *
     * Output sanitisation (ListFeedbackSanitisedTool) reduces prompt
     * injection but cannot eliminate it. What actually bounds the damage is
     * refusing to let a model complete a state change on its own.
     *
     * This tool deliberately CANNOT move money. It validates, then returns a
     * description of what would happen and a confirmation reference. Executing
     * it requires a separate human action outside the model's reach, so an
     * injected instruction gets as far as producing a proposal a person then
     * declines.
     *
     * Note also what the unguarded twin lacks and this one has: a positive
     * amount check and a balance check. Those are the ordinary business rules
     * whose absence is VULN-16.
     */
    public function handle(Request $request): Response
    {
        $from = (string) $request->get('from_username');
        $toAcno = (string) $request->get('to_acno');
        $amount = $request->get('amount');

        if (! is_numeric($amount) || (int) $amount <= 0) {
            return Response::text('Refused: amount must be a positive number.');
        }

        if (! ctype_digit((string) $toAcno)) {
            return Response::text('Refused: destination account number must be numeric.');
        }

        $sender = \App\Models\User::where('username', $from)->first();

        if ($sender === null) {
            return Response::text('Refused: no such sender.');
        }

        if ((int) $sender->balance < (int) $amount) {
            return Response::text('Refused: insufficient balance.');
        }

        return Response::json([
            'status' => 'awaiting_human_approval',
            'summary' => "Transfer $amount from {$sender->username} (acno {$sender->acno}) to account $toAcno",
            'note' => 'No money has moved. A human must approve this out of band before it executes. '
                .'This tool cannot complete the transfer, by design.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'from_username' => $schema->string()->description('Sending account username.')->required(),
            'to_acno' => $schema->integer()->description('Destination account number.')->required(),
            'amount' => $schema->integer()->description('Amount to transfer. Must be positive.')->required(),
        ];
    }
}
