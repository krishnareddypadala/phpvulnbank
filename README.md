# PHPVulnBank

PHPVulnBank is an open-source web application aimed at demonstrating common vulnerabilities found in PHP-based web applications. It is hosted on GitHub as a repository, providing developers and security enthusiasts with a valuable resource to learn about and understand various security issues that can arise in PHP programs.

## Objective

The primary objective of PHPVulnBank is to educate developers and security engineers on secure coding practices and help them identify and mitigate vulnerabilities in their PHP projects. By exploring the vulnerable code and vulnerabilities present in PHPVulnBank, developers can gain a deep understanding of how these security issues are introduced in PHP applications and the potential risks associated with them.

## Features

- Demonstrates multiple vulnerabilities commonly found in PHP web applications.
- Covers vulnerabilities such as SQL Injection, Cross-Site Scripting (XSS), Cross-Site Request Forgery (CSRF), and Remote Code Execution (RCE), among others.
- Provides solutions or patches to fix the vulnerabilities, promoting secure coding practices.

## Getting Started

Fastest path — pull the published image and run it:

```
docker run -it -p 8090:80 krishnapadala55/phpvulnbank:laravel-bundled-vulnerable-1.0
```

Or build from source:

```
git clone https://github.com/krishnareddypadala/phpvulnbank.git
cd phpvulnbank/laravel && docker compose up -d
```

Then open **http://localhost:8090** and log in as `krishna` / `happy123$`.

The old manual setup (configure Apache by hand, import a `.sql` dump) is gone —
`php artisan migrate:fresh --seed` rebuilds the entire lab from an empty
database, which is the single biggest thing the Laravel port bought over the
dump-based workflow.

## Two versions live in this repository

| | |
|---|---|
| `src/` | The **original** flat-PHP application. Do not delete: the `SAST` workflow scans it and `laravel/app/` separately on every push, and reports the difference. Same 28 vulnerabilities, far fewer findings — because Semgrep recognises Eloquent and Blade as safe. That contrast is the point. |
| `laravel/` | The **current** application: a Laravel 13 port with the same vulnerabilities, an API-first design, and a deliberately vulnerable MCP layer. |

Full catalogue of what is intentional: [`docs/vulnerabilities.md`](docs/vulnerabilities.md).
Read [`SECURITY.md`](SECURITY.md) before running either.

## Running the Laravel version on Docker

**Single container** (Apache + PHP + MariaDB together — one command, nothing else needed):

```
docker build -f laravel/Dockerfile.bundled -t phpvulnbank-laravel:bundled-vulnerable-1.0 laravel/
docker run -it -p 8090:80 phpvulnbank-laravel:bundled-vulnerable-1.0
```

**Compose** (database in its own service — the better architecture, and the primary path):

```
cd laravel && docker compose up -d
```

Then open **http://localhost:8090**.

| | |
|---|---|
| Web app | `/` |
| API docs (Swagger UI) | `/docs` |
| OpenAPI spec | `/api/v2/openapi.json` |
| MCP over HTTP | `POST /mcp/api`, `POST /mcp/db` |
| MCP over stdio | `php artisan mcp:start phpvulnbank-api` |

Logins: `krishna` / `happy123$` (user), `admin` / `krishna1$` (administrator).

Rebuild the lab data at any time — including after a student drops the database
through the intentionally unrestricted SQL tool:

```
docker compose exec app php artisan migrate:fresh --seed --force
```

### Running the legacy version

The pre-built images remain on Docker Hub and still work:

```
docker run -it -p 8090:80 -p 22:22 krishnapadala55/phpvulnbank:25.04
```

Its build scaffolding (`Dockerfile`, `dock/`, `dbscript/`) was removed from this
repository in July 2026 — the published tags are self-contained, so nothing
needs rebuilding from source. The legacy application code itself stays in
`src/`, because CI scans it (see below).

## Where it is safe to run this

This application contains **unauthenticated remote code execution**. Reaching its
port is equivalent to shell access on the container.

Fine on an isolated lab VLAN, a classroom network, or a LAN you control. **Never**
on a public IP, a cloud VM with an open security group, a corporate network, a
port-forwarded router, or through a tunnelling service. See [`SECURITY.md`](SECURITY.md).


## Contributing

We welcome contributions to PHPVulnBank! If you have any bug reports, suggestions, or improvements, please feel free to open an issue in the repository's issue tracker. We also encourage you to submit pull requests with new features, fixes, or additional vulnerabilities.

Please ensure that any pull requests or contributions adhere to our guidelines to maintain the quality of PHPVulnBank.

## Community

PHPVulnBank has a thriving community of developers and security professionals. You can join the conversation, get help, or share your findings by participating in discussions and exploring the issues in the repository.

## License

PHPVulnBank is released under the [MIT License](https://opensource.org/licenses/MIT), which allows you to modify and distribute the application. However, please be mindful of the implications when using PHPVulnBank or its code in production environments.

## Conclusion

PHPVulnBank GitHub repository serves as an educational platform for developers and security enthusiasts to learn about and understand common vulnerabilities in PHP web applications. It provides a comprehensive range of vulnerabilities, solutions, and challenges to help you improve your understanding of PHP security and reinforce secure coding practices.

We hope you find PHPVulnBank useful in enhancing your knowledge of PHP security and strengthening the security of your own PHP projects.
