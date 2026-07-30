<?php

use App\Mcp\Servers\ApiServer;
use App\Mcp\Servers\DbServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Servers
|--------------------------------------------------------------------------
|
| Two deliberately vulnerable MCP servers, each registered on BOTH transports.
| See docs/mcp-design.md.
|
|   phpvulnbank-api / POST /mcp/api   routes through the application layer,
|                                     so it inherits whatever controls it has
|   phpvulnbank-db  / POST /mcp/db    talks to MySQL directly, so it inherits
|                                     none of them
|
| The A/B contrast between the two is worth more than either alone: run the
| same action through each, then diff the audit_logs table.
|
| --------------------------------------------------------------------------
| WHY BOTH TRANSPORTS
| --------------------------------------------------------------------------
|
| stdio (Mcp::local) is how MCP is normally deployed: the client launches the
| server as a child process on the same machine. Correct, isolated, and the
| right choice for a student running the whole lab on their own laptop.
|
| It is also useless for a shared classroom instance, because a student's
| client cannot launch a process on someone else's VM. Without HTTP the MCP
| exercises could not be done against a central lab at all.
|
| The earlier objection to HTTP -- that it would expose arbitrary SQL and a
| money-moving tool on a network port -- does not hold here, and it is worth
| being precise about why. This application ALREADY publishes an
| unauthenticated webshell (VULN-03) and an RCE backdoor (VULN-02) on the same
| port. `tools/exec` is strictly more dangerous than `run_query`. Adding an
| HTTP MCP endpoint to a host that is already fully compromisable by anyone who
| can reach it does not move the needle.
|
| The risk that DOES remain is unchanged by transport: a student who connects
| their personal assistant client -- the one holding their filesystem, git and
| mail tools -- to this server can have lab content influence a session that
| touches real systems. That is client configuration, not transport, and it is
| covered in SECURITY.md.
|
| --------------------------------------------------------------------------
| [VULN-80: Unauthenticated MCP endpoint] Intentional.
| --------------------------------------------------------------------------
|
| The HTTP endpoints below carry NO authentication and NO authorisation. Any
| caller who can reach the port gets the full tool set, including the ungated
| admin actions and free-form SQL. That is a lesson in its own right, and one
| of the most common real MCP misconfigurations.
|
| The fix would be ->middleware(['auth:sanctum']) on the routes plus per-user
| token pass-through in the servers, which would also make VULN-81 (the
| confused deputy) fixable rather than structural.
|
| Both transports still fail closed without PHPVULNBANK_LAB=1 -- the guard
| lives in Server::start(), which the HTTP path also calls. See McpGuard.
|
| Start stdio with:   php artisan mcp:start phpvulnbank-api
| Inspect with:       php artisan mcp:inspector phpvulnbank-api
| HTTP endpoints:     POST http://<host>:8090/mcp/api
|                     POST http://<host>:8090/mcp/db
|
*/

// stdio -- for a student running the lab locally.
Mcp::local('phpvulnbank-api', ApiServer::class);
Mcp::local('phpvulnbank-db', DbServer::class);

// HTTP -- for a shared classroom instance. Unauthenticated: see VULN-80 above.
Mcp::web('mcp/api', ApiServer::class);
Mcp::web('mcp/db', DbServer::class);
