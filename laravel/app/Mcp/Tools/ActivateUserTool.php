<?php

namespace App\Mcp\Tools;

use App\Models\AuditLog;
use App\Support\LegacyQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Activate a pending customer account so the customer can log in.')]
class ActivateUserTool extends Tool
{
    /**
     * [VULN-12 / VULN-82: Broken function-level access control] Intentional.
     *
     * The HTTP endpoint this mirrors is ungated, and so is this. There is no
     * admin check because there is no caller identity to check against --
     * see VULN-81 in ApiServer.
     *
     * This is step 3 of the VULN-89 capstone: a model that read instructions
     * out of a KYC document or a feedback record calls this on the attacker's
     * own account, and nothing refuses.
     */
    public function handle(Request $request): Response
    {
        $username = (string) $request->get('username');

        LegacyQuery::run("update banktable set active='1' where username='$username'");

        AuditLog::record('mcp.activate_user', 'mcp-service', 'username='.$username);

        return Response::text('User '.$username.' is now active.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'username' => $schema->string()->description('Username to activate.')->required(),
        ];
    }
}
