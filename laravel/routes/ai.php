<?php

use App\Mcp\Servers\ApiServer;
use App\Mcp\Servers\DbServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Servers
|--------------------------------------------------------------------------
|
| Two deliberately vulnerable MCP servers. See docs/mcp-design.md.
|
|   phpvulnbank-api   routes through the application layer -- inherits its
|                     logic, and whatever controls it has
|   phpvulnbank-db    talks to MySQL directly -- inherits none of them
|
| The A/B contrast between them is worth more than either alone: run the same
| action through each, then diff the audit_logs table.
|
| ---------------------------------------------------------------------------
| BOTH ARE REGISTERED WITH Mcp::local(), i.e. STDIO TRANSPORT, DELIBERATELY.
| ---------------------------------------------------------------------------
|
| Mcp::web() would publish these over HTTP, which for this lab means exposing
| unauthenticated arbitrary SQL and a money-moving tool on a network port.
| stdio confines them to a process the operator explicitly launched.
|
| Both refuse to start unless PHPVULNBANK_LAB=1 is set -- see App\Support\McpGuard
| and SECURITY.md. Run them in a DEDICATED client profile with no other MCP
| servers connected: the stored prompt-injection payloads in these tools are
| written to make a model take actions it was not asked to take, and they do
| not care which tools it has.
|
| Start with:  php artisan mcp:start phpvulnbank-api
|              php artisan mcp:start phpvulnbank-db
| Inspect with: php artisan mcp:inspector phpvulnbank-api
|
*/

Mcp::local('phpvulnbank-api', ApiServer::class);
Mcp::local('phpvulnbank-db', DbServer::class);
