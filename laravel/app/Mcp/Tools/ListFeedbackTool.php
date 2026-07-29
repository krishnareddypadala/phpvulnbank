<?php

namespace App\Mcp\Tools;

use App\Support\LegacyQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List all customer feedback submitted to the bank.')]
class ListFeedbackTool extends Tool
{
    /**
     * [VULN-77: Stored prompt injection] Intentional. Do not sanitise here.
     *
     * This is the direct analogue of the app's stored XSS (VULN-13), on the
     * SAME `feedback` column. In both cases attacker-controlled content is
     * written to a field and later interpreted by something that trusts it.
     * Only the interpreter changed: a browser then, a language model now.
     *
     * The chain:
     *   1. a customer submits feedback containing instructions
     *      (submit_feedback, or PUT /api/v2/feedback/me)
     *   2. an administrator asks the assistant to summarise customer feedback
     *   3. this tool returns it verbatim
     *   4. the model treats it as instruction and calls activate_user or
     *      run_transfer -- tools it has, and which nothing stops it using
     *
     * Teaching VULN-13 and VULN-77 side by side is the clearest way to show
     * that prompt injection is an old bug class with a new consumer.
     *
     * The guarded twin is ListFeedbackSanitisedTool. Note honestly that the
     * twin REDUCES this and does not eliminate it -- see docs/mcp-design.md §8.
     */
    public function handle(Request $request): Response
    {
        $rows = LegacyQuery::select('select username, feedback from banktable');

        return Response::json(['feedback' => $rows]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
