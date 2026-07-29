<?php

namespace App\Support;

/**
 * The OpenAPI 3.1 description of the v2 API.
 *
 * Hand-written rather than generated, because the useful part is not the shape
 * of the requests -- it is the `x-vuln` annotation on each operation naming the
 * lesson it carries and the authentication it deliberately does NOT enforce.
 * A generator would produce the former and miss the latter entirely.
 *
 * Served unauthenticated at /api/v2/openapi.json. That is itself worth
 * noticing: a complete, machine-readable inventory of every endpoint --
 * including the webshell and the ungated admin actions -- handed to anyone who
 * asks. Real deployments leak exactly this (OWASP API9, Improper Inventory
 * Management). Here it is deliberate, because students need the map.
 */
class OpenApiSpec
{
    public static function toArray(): array
    {
        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'PHPVulnBank API v2',
                'version' => '1.0.0',
                'summary' => 'Intentionally vulnerable retail banking API for security training.',
                'description' => implode("\n", [
                    '**WARNING: every endpoint here is deliberately flawed.**',
                    '',
                    'This API is a training lab. It contains unauthenticated remote code',
                    'execution, arbitrary file read, SSRF and SQL injection. Never run it',
                    'on a public address. See `SECURITY.md`.',
                    '',
                    'Each operation carries an `x-vuln` field listing the lessons it hosts.',
                    'The full catalogue, including the correct fix for each, is in',
                    '`docs/vulnerabilities.md`.',
                    '',
                    '### Authentication',
                    '',
                    'Session cookie. POST to `/api/v2/auth/login`, then send the cookie back.',
                    'Endpoints accept **both** `application/json` and',
                    '`application/x-www-form-urlencoded` -- that dual acceptance is itself',
                    'VULN-10, since it is what makes the transfer endpoint forgeable from a',
                    'cross-site form.',
                    '',
                    '### Note on `x-auth-enforced`',
                    '',
                    'Where this is `false`, the endpoint requires no authentication **and',
                    'should**. Those are the broken-access-control lessons, not gaps in this',
                    'document.',
                ]),
                'license' => ['name' => 'MIT', 'identifier' => 'MIT'],
            ],
            'servers' => [
                ['url' => '/', 'description' => 'This instance'],
            ],
            'tags' => [
                ['name' => 'Authentication'],
                ['name' => 'Registration'],
                ['name' => 'Accounts'],
                ['name' => 'Transfers'],
                ['name' => 'Feedback'],
                ['name' => 'KYC'],
                ['name' => 'Admin'],
                ['name' => 'Tools', 'description' => 'Deliberate webshells. Unauthenticated by design.'],
            ],
            'components' => [
                'securitySchemes' => [
                    'sessionCookie' => [
                        'type' => 'apiKey',
                        'in' => 'cookie',
                        'name' => 'laravel_session',
                    ],
                ],
                'schemas' => [
                    'Account' => [
                        'type' => 'object',
                        'properties' => [
                            'acno' => ['type' => 'integer'],
                            'username' => ['type' => 'string'],
                            'balance' => ['type' => 'integer'],
                            'email' => ['type' => 'string'],
                            'mobile' => ['type' => 'string'],
                            'feedback' => ['type' => 'string'],
                            'password_hash' => [
                                'type' => 'string',
                                'description' => 'Unsalted MD5. Returned deliberately (VULN-21).',
                            ],
                            'admin' => ['type' => 'integer'],
                        ],
                    ],
                    'Error' => [
                        'type' => 'object',
                        'properties' => ['error' => ['type' => 'string']],
                    ],
                ],
            ],
            'paths' => self::paths(),
        ];
    }

    private static function paths(): array
    {
        return [
            '/api/v2/auth/login' => [
                'post' => self::op(
                    'Authentication', 'Log in',
                    'Credentials are checked with an interpolated raw SQL statement. The password sits inside MySQL\'s MD5() call, so it is injectable too.',
                    ['VULN-01 SQLi auth bypass', 'VULN-02 command-injection backdoor', 'VULN-14 reflected XSS', 'VULN-24 user enumeration', 'VULN-25 no rate limiting'],
                    false,
                    body: ['uname' => 'string', 'pwd' => 'string'],
                    responses: [
                        '200' => 'Authenticated, or on the backdoor path the output of a shell command.',
                        '401' => 'Failure. Served as text/html with the submitted username reflected unescaped.',
                    ],
                    notes: "Try `uname=' or '1'='1' -- ` with any password.\nTry `uname=id&pwd=troy` for unauthenticated command execution."
                ),
            ],
            '/api/v2/auth/logout' => [
                'post' => self::op('Authentication', 'Log out', 'Invalidates the session.', [], true,
                    responses: ['200' => 'Logged out.']),
            ],
            '/api/v2/register/json' => [
                'post' => self::op(
                    'Registration', 'Register (JSON body)',
                    'The body is read raw and JSON-decoded regardless of the declared Content-Type, then passed to the model wholesale.',
                    ['VULN-66 mass assignment', 'VULN-67 content-type confusion', 'VULN-06 SQLi', 'VULN-15 MD5 storage'],
                    false,
                    body: ['name' => 'string', 'pwd' => 'string', 'email' => 'string', 'tel' => 'string', 'admin' => 'integer', 'active' => 'integer'],
                    responses: ['201' => 'Created.', '409' => 'Username taken (reflected as HTML).'],
                    notes: 'Sending `"admin":1,"active":1` creates an activated administrator.'
                ),
            ],
            '/api/v2/register/xml' => [
                'post' => self::op(
                    'Registration', 'Register (XML body)',
                    'Parses the body with external entity resolution deliberately re-enabled.',
                    ['VULN-09 XXE', 'VULN-06 SQLi'],
                    false,
                    rawBody: 'application/xml',
                    responses: ['201' => 'Created.'],
                    notes: 'A DOCTYPE with a SYSTEM entity pointing at file:///etc/passwd will be resolved and echoed back.'
                ),
            ],
            '/api/v2/accounts/me' => [
                'get' => self::op(
                    'Accounts', 'Own account',
                    'Looks the caller up by the username held in the session, interpolated into raw SQL.',
                    ['VULN-06 second-order SQLi', 'VULN-13 stored XSS', 'VULN-21 hash disclosure'],
                    true,
                    responses: ['200' => 'Account, including the password hash and admin flag.', '401' => 'Not authenticated.']
                ),
            ],
            '/api/v2/accounts/{acno}' => [
                'get' => self::op(
                    'Accounts', 'Look up any account',
                    'No ownership check and no authentication. The account number is interpolated unquoted, so injection needs no quote breaking.',
                    ['VULN-05 numeric SQLi', 'VULN-11 IDOR / BOLA', 'VULN-21 hash disclosure'],
                    false,
                    params: ['acno' => 'Account number. Route pattern is deliberately widened to `.*` so payloads reach the query.'],
                    responses: ['200' => 'Username, password hash and balance.', '404' => 'No such account.'],
                    notes: 'The table has nine columns: `0 union select 1,2,3,4,5,6,7,8,9`.'
                ),
            ],
            '/api/v2/accounts/{acno}/safe' => [
                'get' => self::op(
                    'Accounts', 'Look up any account (remediated twin)',
                    'Uses a bound parameter, so the injection is genuinely fixed. The missing ownership check and the password disclosure are NOT fixed -- patching one of three bugs is the lesson.',
                    ['VULN-11 IDOR (still present)', 'VULN-21 hash disclosure (still present)'],
                    false,
                    params: ['acno' => 'Account number.'],
                    responses: ['200' => 'Username, password hash and balance.', '404' => 'No such account.']
                ),
            ],
            '/api/v2/transfers' => [
                'post' => self::op(
                    'Transfers', 'Transfer funds',
                    'Accepts form-encoded bodies and authenticates from the session cookie, with no CSRF token required. Reads the balance, computes, and writes back without a transaction.',
                    ['VULN-10 CSRF', 'VULN-16 negative amounts', 'VULN-17 race condition', 'VULN-11 IDOR', 'VULN-35 replay', 'VULN-06 SQLi'],
                    true,
                    body: ['tacno' => 'string', 'tamount' => 'string'],
                    responses: ['200' => 'Transfer completed.', '401' => 'Not authenticated.'],
                    notes: 'A negative amount reverses the direction and drains the destination. There is no overdraft check.'
                ),
            ],
            '/api/v2/transfers/protected' => [
                'post' => self::op(
                    'Transfers', 'Transfer funds (remediated twin)',
                    'Requires a CSRF token. Every other flaw -- injection, IDOR, negative amounts, the race -- survives untouched.',
                    ['VULN-16', 'VULN-17', 'VULN-11', 'VULN-06'],
                    true,
                    body: ['tacno' => 'string', 'tamount' => 'string', 'csrftoken' => 'string'],
                    responses: ['200' => 'Transfer completed.', '419' => 'Invalid CSRF token.']
                ),
            ],
            '/api/v2/feedback/me' => [
                'put' => self::op(
                    'Feedback', 'Submit feedback',
                    'Stores the text unvalidated and unescaped. No authentication guard: the session username is read without checking it exists.',
                    ['VULN-13 stored XSS (store half)', 'VULN-12 missing authentication', 'VULN-06 SQLi'],
                    false,
                    body: ['fb' => 'string'],
                    responses: ['200' => 'Updated.'],
                    notes: 'This column is also the stored prompt-injection sink for the MCP layer (VULN-77).'
                ),
            ],
            '/api/v2/feedback' => [
                'get' => self::op(
                    'Feedback', 'All customer feedback',
                    'Admin-gated. Returns every customer\'s feedback raw; the browser client renders it with innerHTML.',
                    ['VULN-13 stored XSS (render half)'],
                    true,
                    responses: ['200' => 'Feedback for all users.', '403' => 'Not admin.']
                ),
            ],
            '/api/v2/kyc' => [
                'post' => self::op(
                    'KYC', 'Upload a KYC document',
                    'No validation rules, no extension or MIME check, no size limit. The client-supplied filename is used verbatim and the file is written inside the document root.',
                    ['VULN-04 unrestricted upload to RCE', 'VULN-12 missing authentication'],
                    false,
                    rawBody: 'multipart/form-data',
                    responses: ['200' => 'Stored, with the public URL.'],
                    notes: 'Upload a .php file and request it under /images/. The filename is also a traversal sink.'
                ),
            ],
            '/api/v2/admin/pending-activations' => [
                'get' => self::op(
                    'Admin', 'List accounts awaiting activation',
                    'Correctly admin-gated. Contrast with the activate action below -- that contrast is the lesson.',
                    ['VULN-13 stored XSS'],
                    true,
                    responses: ['200' => 'Pending accounts.', '403' => 'Not admin.']
                ),
            ],
            '/api/v2/admin/activate' => [
                'post' => self::op(
                    'Admin', 'Activate an account',
                    'The FORM that reaches this is admin-gated. The ACTION is not gated at all, and needs no session. Gating the UI is not gating the endpoint.',
                    ['VULN-12 missing function-level access control', 'VULN-06 SQLi', 'VULN-14 reflected XSS'],
                    false,
                    body: ['user' => 'string'],
                    responses: ['200' => 'Activated (HTML, username reflected).'],
                    notes: 'Chains with registration: create an account, then activate it yourself.'
                ),
            ],
            '/api/v2/admin/kyc' => [
                'get' => self::op(
                    'Admin', 'List uploaded KYC documents',
                    'Lists every document every customer has uploaded. Reachable without authentication -- the admin-only link was the only control.',
                    ['VULN-12 missing access control'],
                    false,
                    responses: ['200' => 'Document names and download URLs.']
                ),
            ],
            '/api/v2/admin/kyc/download' => [
                'get' => self::op(
                    'Admin', 'Download a KYC document',
                    'The path is passed to the filesystem with no normalisation, allow-list or confinement.',
                    ['VULN-07 path traversal / LFI', 'VULN-14 reflected XSS'],
                    false,
                    query: ['file' => 'Path to read. Not confined to the uploads directory.'],
                    responses: ['200' => 'File contents.', '404' => 'Not found (path reflected).'],
                    notes: 'Try `?file=../.env` to take the APP_KEY.'
                ),
            ],
            '/api/v2/tools/exec' => [
                'get' => self::op(
                    'Tools', 'Execute a shell command',
                    'An unauthenticated webshell. The command is passed to the system shell and its output returned. There is no safe version of this endpoint.',
                    ['VULN-03 command injection'],
                    false,
                    query: ['cmd' => 'Command to execute.'],
                    responses: ['200' => 'Command output as text/plain.']
                ),
                'post' => self::op(
                    'Tools', 'Execute a shell command (POST)',
                    'As above. Responding to both verbs is deliberate.',
                    ['VULN-03 command injection', 'VULN-68 verb tampering'],
                    false,
                    body: ['cmd' => 'string'],
                    responses: ['200' => 'Command output as text/plain.']
                ),
            ],
            '/api/v2/tools/fetch' => [
                'get' => self::op(
                    'Tools', 'Fetch a URL server-side',
                    'file_get_contents() on an unvalidated parameter, so every enabled stream wrapper is reachable. Errors are returned verbatim, making this an information-disclosure oracle as well.',
                    ['VULN-08 SSRF'],
                    false,
                    query: ['url' => 'URL or path to fetch.'],
                    responses: ['200' => 'Response body, or the error message.'],
                    notes: 'Try `?url=http://169.254.169.254/` or `?url=file:///etc/passwd`.'
                ),
            ],
            '/api/v2/openapi.json' => [
                'get' => self::op(
                    'Tools', 'This document',
                    'A complete machine-readable inventory of every endpoint, served without authentication.',
                    ['VULN-69 excessive information exposure (deliberate here)'],
                    false,
                    responses: ['200' => 'This OpenAPI document.']
                ),
            ],
        ];
    }

    /**
     * Build one operation object.
     */
    private static function op(
        string $tag,
        string $summary,
        string $description,
        array $vulns,
        bool $authEnforced,
        array $params = [],
        array $query = [],
        array $body = [],
        ?string $rawBody = null,
        array $responses = [],
        ?string $notes = null,
    ): array {
        $op = [
            'tags' => [$tag],
            'summary' => $summary,
            'description' => $description.($notes ? "\n\n**How to reach it:**\n\n".$notes : ''),
            'x-vuln' => $vulns,
            'x-auth-enforced' => $authEnforced,
        ];

        if (! $authEnforced) {
            $op['description'] .= "\n\n> **No authentication is enforced on this endpoint.** "
                .'Where that is wrong, it is the lesson rather than an omission in this document.';
        }

        $parameters = [];

        foreach ($params as $name => $desc) {
            $parameters[] = [
                'name' => $name, 'in' => 'path', 'required' => true,
                'schema' => ['type' => 'string'], 'description' => $desc,
            ];
        }

        foreach ($query as $name => $desc) {
            $parameters[] = [
                'name' => $name, 'in' => 'query', 'required' => true,
                'schema' => ['type' => 'string'], 'description' => $desc,
            ];
        }

        if ($parameters !== []) {
            $op['parameters'] = $parameters;
        }

        if ($rawBody !== null) {
            $op['requestBody'] = [
                'required' => true,
                'content' => [$rawBody => ['schema' => ['type' => 'string']]],
            ];
        } elseif ($body !== []) {
            $props = [];
            foreach ($body as $name => $type) {
                $props[$name] = ['type' => $type];
            }
            $schema = ['type' => 'object', 'properties' => $props];

            $op['requestBody'] = [
                'required' => true,
                'content' => [
                    // Both are accepted. See VULN-10.
                    'application/x-www-form-urlencoded' => ['schema' => $schema],
                    'application/json' => ['schema' => $schema],
                ],
            ];
        }

        $op['responses'] = [];

        foreach ($responses as $code => $desc) {
            $op['responses'][(string) $code] = ['description' => $desc];
        }

        if ($op['responses'] === []) {
            $op['responses']['200'] = ['description' => 'OK'];
        }

        if ($authEnforced) {
            $op['security'] = [['sessionCookie' => []]];
        }

        return $op;
    }
}
