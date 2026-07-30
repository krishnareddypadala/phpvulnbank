# Security Policy

## This repository is intentionally vulnerable

PHPVulnBank is a **security-education artifact**. Every vulnerability in it is
deliberate, documented and load-bearing. It is not supported software, it has
no security patches, and it must never run in production or on any network you
do not fully control.

**Please do not file vulnerability reports for this repository.** Findings from
automated scanners, bug bounty automation and dependency bots are expected and
will be closed. If you believe you have found something that is *not* in
[`docs/vulnerabilities.md`](docs/vulnerabilities.md), that is genuinely
interesting — open a normal issue describing it, and it will likely become a
new lesson.

## What is dangerous here

Two paths give **unauthenticated remote code execution**, with no credentials
and no session required:

| Path | Reach |
|---|---|
| `POST /api/v2/auth/login` with `pwd=troy` | The submitted username is executed as a shell command (`VULN-02`) |
| `GET\|POST /api/v2/tools/exec` | A webshell with no authentication at all (`VULN-03`) |

There is also an unrestricted file upload that writes attacker-named files into
a PHP-executable directory (`VULN-04`), arbitrary file read via path traversal
(`VULN-07`), and SSRF that reaches internal services (`VULN-08`).

Anyone who can reach this application over the network can run commands on the
host that serves it. Treat a running instance as already compromised.

## Running it safely

The app port is published on **all interfaces**, so the lab is reachable from
other machines on your network. That is deliberate — a classroom lab that only
answers on loopback is not much use.

It also means the section above is not theoretical. **Reaching port 8090 is
equivalent to shell access on the container.** Choose the network accordingly:

| | |
|---|---|
| **OK** | An isolated lab VLAN, a classroom or workshop network, a home LAN you control |
| **NEVER** | A public IP address |
| **NEVER** | A cloud VM with an open security group |
| **NEVER** | A corporate or shared office network |
| **NEVER** | Behind a port-forwarded router |
| **NEVER** | Through ngrok, Cloudflare Tunnel, or any similar service |

The container prints this warning, and what an attacker gains, every time it
starts. Bring it down when the session ends — `docker compose down`. MySQL stays
bound to loopback and is not widened alongside the app.
- **Use disposable data.** Never point it at a database containing anything
  real. `php artisan migrate:fresh --seed` rebuilds the entire lab from empty.
- **Never expose port 80/8090 through a tunnel or reverse proxy**, including
  ngrok-style services for "quick sharing".
- **Do not push the container image to a registry** without `vulnerable` in the
  tag name, and never tag it `latest`.
- `APP_ENV=local` and `APP_DEBUG=true` are **lab settings**. They are
  intentional here (`VULN-23`) and must never be copied into a real project.

## The MCP servers need isolation, not just localhost

Two MCP servers ship with this lab (`phpvulnbank-api`, `phpvulnbank-db`). They
change the threat model in a way the web app does not, and **"bind to
localhost" does not address it** — the exposure is not network reachability, it
is *client configuration*.

MCP servers are normally registered in a developer's personal assistant client,
alongside their real tools: filesystem, git, mail, ticketing. The stored
prompt-injection payloads in this lab are written specifically to make a model
take actions it was not asked to take, and they do not care which tools it has.
Connect this server to a session that also holds real ones and the lab's data
can reach them.

- **Run it in a dedicated client profile with no other MCP servers connected.**
  This is the requirement that matters, and it is unaffected by transport.
- Both servers **fail closed**: they refuse to start unless `PHPVULNBANK_LAB=1`
  is set deliberately. The guard lives in `Server::start()`, which both
  transports call.
- Both are registered on **both transports**:
  - **stdio** (`Mcp::local`) — for a student running the whole lab locally.
    Isolated, and how MCP is normally deployed.
  - **HTTP** (`POST /mcp/api`, `POST /mcp/db`) — so a shared classroom instance
    is usable at all. A student's client cannot launch a stdio subprocess on
    someone else's machine, so without this the MCP exercises could not be run
    against a central lab.
- The HTTP endpoints are **unauthenticated** (`VULN-80`). Note this adds little
  on top of what is already published on the same port: `tools/exec` is an
  unauthenticated webshell, so the host is already fully compromisable by
  anyone who can reach it.
- On a **shared** instance, remember `run_query` accepts free-form SQL on a
  connection that can drop tables — one student can take the lab down for the
  class. Rebuild it with:
  `docker compose exec app php artisan migrate:fresh --seed --force`
- `run_query` executes **model-composed SQL** on a connection that can drop
  tables. Never point it at a database holding anything real.
- **Assume every tool result reaches the model provider.** Seed data is
  synthetic and must stay synthetic.

## Why Dependabot and scanners are noisy

The lab deliberately keeps weak cryptography (unsalted MD5), disabled framework
protections and — in the planned A06 lesson — a dependency with a known
advisory. Dependency and code scanners will flag these. **Do not merge
automated "fixes".** Each one silently deletes a lesson, which is the primary
failure mode this project guards against.

Every deliberate flaw carries a `[VULN-nn: ...] Intentional. Do not "fix"`
marker comment at the point of use, and a matching entry in
[`docs/vulnerabilities.md`](docs/vulnerabilities.md). The
`tests/Feature/Exploits/` suite asserts that vulnerabilities **still work** — a
failure there means a lesson has been repaired, not that the app is broken.

## Reporting a problem with the project itself

For issues unrelated to the intentional vulnerabilities — a broken build, a
lesson that no longer reproduces, an error in the documentation — open a
regular GitHub issue.
