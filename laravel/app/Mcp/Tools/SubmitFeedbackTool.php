<?php

namespace App\Mcp\Tools;

use App\Support\LegacyQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Submit or update feedback for a customer account.')]
class SubmitFeedbackTool extends Tool
{
    /**
     * The injection SINK for VULN-77 (and VULN-13 -- it is the same column).
     *
     * Nothing is validated, escaped or length-checked, and the username is
     * caller-supplied rather than derived from an authenticated identity, so
     * a payload can be planted against any account.
     */
    public function handle(Request $request): Response
    {
        $username = (string) $request->get('username');
        $feedback = (string) $request->get('feedback');

        LegacyQuery::run("update banktable set feedback='$feedback' where username='$username'");

        return Response::text('Feedback updated for '.$username);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'username' => $schema->string()->description('Account username.')->required(),
            'feedback' => $schema->string()->description('Feedback text.')->required(),
        ];
    }
}
