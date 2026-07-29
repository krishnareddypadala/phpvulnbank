# Vulnerability Catalogue

The lesson catalogue for the Laravel port. Every entry is a **deliberate**
flaw with a marker comment at the point of use and an exploitability test that
asserts it still works.

- **Inventory of the legacy app:** [`legacy-mapping.md`](legacy-mapping.md)
- **Guardrails and how to run this safely:** [`../SECURITY.md`](../SECURITY.md)
- **Tests:** `laravel/tests/Feature/Exploits/`

> A failing exploitability test means a lesson has been **repaired**, not that
> the application is broken. Find what closed it and restore the opt-out.

Base URL below is the app root; the API lives under `/api/v2`.

---

## Status

| Implemented | Designed, not yet built |
|---|---|
| VULN-01 … 25 (legacy port), 35, 66, 67 | VULN-26 … 51 ([`proposed-lessons.md`](proposed-lessons.md)) |
| VULN-46, 47 (audit log) | VULN-52 … 65 ([`proposed-lessons-jwt.md`](proposed-lessons-jwt.md)) |
| VULN-73, 75, 77, 81, 82, 87, 88, 90, 91, 92 (MCP) | VULN-72, 74, 76, 78 … 80, 83 … 86, 89 |

### MCP layer

Two servers, registered in `laravel/routes/ai.php` and both failing closed
without `PHPVULNBANK_LAB=1` (see `App\Support\McpGuard`).

| Lesson | Where |
|---|---|
| `VULN-75` **Tool poisoning** — the `#[Description]` on `AccountSummaryTool` carries instructions the model reads as trusted guidance and the user never sees. Tool metadata is executable context. | `app/Mcp/Tools/AccountSummaryTool.php` |
| `VULN-77` **Stored prompt injection** — the same `feedback` column as VULN-13. Same sink, same chain, different interpreter. | `ListFeedbackTool` |
| `VULN-81` **Confused deputy** — no per-user identity, so the *server's* authority is applied to the *user's* request. This is why VULN-82 cannot be fixed inside the tools. | `ApiServer` |
| `VULN-82` **No object-level authorisation** | `GetBalanceTool` |
| `VULN-90` **Unrestricted text-to-SQL** — moots the whole injection curriculum; the surface moved from the parameter to the prompt. | `Db\RunQueryTool` |
| `VULN-91` **Over-privileged connection** — "read-only" is a description, not a control. Runs on `groot` (VULN-22). | `Db\RunQueryTool` |
| `VULN-87` **Unmasked PII** — masking in the presentation layer is not masking, and here the data crosses an organisational boundary. | `Db\GetCustomerTool` |
| `VULN-88` **Masking bypassed on the error path** — the success path redacts; the failure path does not. | `Db\GetCustomerMaskedTool` |
| `VULN-73` **Secrets in error output** — raw DB exceptions returned to the model. | `Db\RunQueryTool` |
| `VULN-92` **Application controls bypassed** — diff `audit_logs` after the same action through each server. | `DbServer` |
| `VULN-46/47` **Inadequate logging, log injection** — records money movement and nothing else; no IP or user agent. | `AuditLog`, migration |

**Guarded twins** (the input/output control lesson): `ListFeedbackSanitisedTool`,
`RunQuerySafeTool`, `GetCustomerMaskedTool`, `RunTransferConfirmedTool`.

> Output sanitisation **reduces** prompt injection; it does not eliminate it.
> There is no escaping scheme for natural language the way `htmlspecialchars()`
> works for HTML, because the interpreter has no grammar separating data from
> instruction. The control that actually bounds the damage is human-in-the-loop
> confirmation — `RunTransferConfirmedTool` cannot move money by design.

---

## VULN-01 · SQL injection → authentication bypass
**CWE-89 · OWASP A03 · Critical**

- **Where:** `app/Http/Controllers/Api/V2/AuthController.php` — `login()`
- **Route:** `POST /api/v2/auth/login`, parameters `uname`, `pwd`
- **Protection removed:** Eloquent and the query builder bind every parameter.
  Both values are interpolated into a raw string instead, routed through
  `App\Support\LegacyQuery`. The password stays inside MySQL's `MD5()` call —
  hashing it in PHP first would sanitise it and kill half the lesson.
- **Reach it:** `uname=' or '1'='1' -- ` with any password. The comment marker
  also defeats the trailing `and active='1'`, so a disabled account logs in.
  The session stores the *submitted* string, so you authenticate as whatever
  you typed.
- **Fix:**
  ```php
  DB::select('select * from banktable where username = ? and password = ? and active = 1',
             [$username, md5($password)]);
  ```

## VULN-02 · Command injection backdoor
**CWE-78 · OWASP A03 · Critical**

- **Where:** `AuthController::login()`, the `$password === 'troy'` branch
- **Route:** `POST /api/v2/auth/login`
- **Protection removed:** none — this is a deliberate backdoor ported verbatim
  from `src/login.php:74`.
- **Reach it:** `uname=id&pwd=troy`. Unauthenticated RCE.
- **Fix:** delete the branch.
- **Note:** the single most dangerous line in the repository and the easiest to
  lose in a port. See [`../SECURITY.md`](../SECURITY.md).

## VULN-03 · Command injection (webshell)
**CWE-78 · OWASP A03 · Critical**

- **Where:** `UtilityController::exec()` · **Route:** `GET|POST /api/v2/tools/exec`
- **Protection removed:** no authentication, no allow-list, no escaping.
- **Reach it:** `?cmd=whoami`
- **Fix:** delete the endpoint. There is no safe version of it.

## VULN-04 · Unrestricted file upload → RCE
**CWE-434 · OWASP A04 · Critical**

- **Where:** `KycController::store()` · **Route:** `POST /api/v2/kyc`
- **Protection removed:** no `validate()` rules, no extension or MIME check, no
  size limit. The client-supplied filename is used verbatim and the file is
  written into `public/images` — inside the document root.
- **Reach it:** upload `shell.php`, then request `/images/shell.php`. The
  filename is also a traversal sink (`../../routes/web.php`).
- **Fix:** `$request->validate(['image' => 'required|image|max:2048'])` and
  store outside the web root with a generated name.
- **Depends on:** the web server executing PHP in that directory. Under the
  lab's Apache + mod_php container it does; on stock nginx the RCE half of the
  lesson quietly disappears.

## VULN-05 · SQL injection, numeric context
**CWE-89 · OWASP A03 · High**

- **Where:** `AccountController::lookup()` · **Route:** `GET /api/v2/accounts/{acno}`
- **Protection removed:** unquoted interpolation, and the route's `{acno}`
  pattern is widened to `.*` so Laravel's implicit parameter constraint does not
  reject the payload first.
- **Reach it:** `/api/v2/accounts/0 or 1=1`, or
  `/api/v2/accounts/0 union select 1,2,3,4,5,6,7,8,9` — the table has nine
  columns, which is what a UNION payload must match.
- **Fix:** bind the parameter and restore `->where('acno', '[0-9]+')`.

## VULN-06 · SQL injection, string context (multiple sites)
**CWE-89 · OWASP A03 · High**

- **Where:** `TransferController`, `FeedbackController`, `AdminController`,
  `RegisterController`, and `Concerns\ChecksLegacyAdmin`
- **Protection removed:** interpolation through `LegacyQuery`.
- **Second-order variant:** `ChecksLegacyAdmin` interpolates the *session*
  username on every admin check. The payload enters at registration, is stored,
  and fires later somewhere else entirely.
- **Fix:** bindings everywhere; validate usernames at registration.

## VULN-07 · Path traversal / LFI
**CWE-22 · OWASP A01 · High**

- **Where:** `AdminController::kycDownload()` · **Route:** `GET /api/v2/admin/kyc/download?file=`
- **Reach it:** `?file=../../../../etc/passwd`, or `?file=../.env` to take the
  `APP_KEY`.
- **Fix:** accept an opaque document ID, resolve server-side, and confirm with
  `realpath()` that the result is inside the uploads directory.

## VULN-08 · SSRF
**CWE-918 · OWASP A10 · High**

- **Where:** `UtilityController::fetch()` · **Route:** `GET /api/v2/tools/fetch?url=`
- **Reach it:** `?url=http://169.254.169.254/`, `?url=file:///etc/passwd`
- **Extra:** the legacy error handler is preserved, so failed fetches report
  *why* they failed — an information-disclosure oracle that distinguishes a
  closed port from a filtered one.
- **Fix:** allow-list schemes and hosts; re-resolve DNS and reject private ranges.

## VULN-09 · XXE
**CWE-611 · OWASP A05 · High**

- **Where:** `RegisterController::registerXml()` · **Route:** `POST /api/v2/register/xml`
- **Protection removed:** a custom `libxml_set_external_entity_loader()` that
  resolves external entities. **This is a re-enablement, not a port** — the
  legacy code relied on libxml behaviour PHP 8 no longer has, so the original
  lesson had probably not fired in years (see `legacy-mapping.md` §7.6).
- **Reach it:**
  ```xml
  <!DOCTYPE root [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
  <root><name>&xxe;</name></root>
  ```
- **Fix:** delete the loader registration; drop `LIBXML_NOENT|LIBXML_DTDLOAD`.

## VULN-10 · CSRF
**CWE-352 · OWASP A01 · High**

- **Where:** `bootstrap/app.php` (middleware) + `TransferController::store()`
- **Route:** `POST /api/v2/transfers`
- **Protection removed:** the `api` group gets cookies and session but **not**
  `VerifyCsrfToken`, and the endpoint accepts
  `application/x-www-form-urlencoded`.
- **Both halves are required.** A JSON-only API is not CSRF-able: a form cannot
  send a JSON content type, and a cross-origin `fetch` that does triggers a
  preflight. Remove either half and the endpoint looks unprotected while being
  unattackable — see [`api-refactor.md`](api-refactor.md) §5.1.
- **Reach it:** `payload/csrf/offer.html`
- **Fix:** add `VerifyCsrfToken` to the group, as `/transfers/protected` shows.

## VULN-11 · IDOR / BOLA
**CWE-639 · OWASP A01 · High**

- **Where:** `AccountController::lookup()` and `::lookupSafe()`, `TransferController::store()`
- **Protection removed:** no ownership check and no policy — Laravel enforces
  nothing here by default, so the lesson is an *absence*.
- **Reach it:** `GET /api/v2/accounts/2` with no session at all.
- **Fix:** a Gate or policy asserting the account belongs to the caller.

## VULN-12 · Missing function-level access control
**CWE-862 · OWASP A01 · High**

- **Where:** `AdminController::activate()`, `::kycIndex()`, `KycController::store()`,
  `FeedbackController::update()`
- **Reach it:** `POST /api/v2/admin/activate` with `user=srikanth`, entirely
  unauthenticated. The *listing* endpoint is admin-gated; the *action* is not.
- **Why it matters:** gating the UI is not gating the endpoint. Chains with
  registration — create an account, then activate it yourself.
- **Fix:** an `admin` middleware on the route group.

## VULN-13 · Stored XSS (now DOM-based)
**CWE-79 · OWASP A03 · Medium**

- **Store:** `PUT /api/v2/feedback/me` — no validation or escaping
- **Render:** `resources/views/layouts/app.blade.php` — the `render()` helper
  writes API data with `innerHTML`; also `feedback/index.blade.php` and
  `account/profile.blade.php`
- **Class changed:** the API refactor removed the server-side sink
  (`application/json` does not execute), so this is now **DOM-based** XSS.
- **Reach it:** store `<img src=x onerror=alert(document.cookie)>`, then have an
  admin load the feedback list.
- **Fix:** `textContent` instead of `innerHTML`.
- **⚠ The most fragile lesson in the app** — the fix looks obviously correct and
  silently deletes VULN-13, VULN-14 and the session-theft chain at once.

## VULN-14 · Reflected XSS
**CWE-79 · OWASP A03 · Medium**

- **Where:** `AuthController::login()` failure path, `AdminController::activate()`,
  `::kycDownload()`
- **Protection removed:** the response is served as `text/html` rather than
  JSON. **Content type is the control here** — this is what keeps a genuine
  server-side reflected XSS reachable through an API.
- **Reach it:** `POST /api/v2/auth/login` with `uname=<script>alert(1)</script>`
- **Fix:** return JSON with a generic message.

## VULN-15 · Weak password hashing
**CWE-916 · OWASP A02 · Medium**

- **Where:** `database/seeders/BankTableSeeder.php`, `RegisterController::createAccount()`,
  `app/Models/User.php`
- **Protection removed:** `md5()` instead of `Hash::make()`, and the model's
  `casts()` deliberately omits `'password' => 'hashed'` — the scaffold ships
  that cast, and keeping it would silently bcrypt-upgrade the stored hashes.
- **Reach it:** get a hash via VULN-21, crack it offline.
- **Fix:** `Hash::make()` and restore the cast.

## VULN-16 · Business logic: negative-amount transfer
**CWE-840 · OWASP A04 · High**

- **Where:** `TransferController::store()`
- **Reach it:** `tacno=2&tamount=-1000` — money moves *from* the destination.
  There is also no overdraft check, so a large positive amount drives the
  sender's balance negative.
- **Fix:** validate `numeric|min:1` and assert sufficient balance.

## VULN-17 · Race condition / non-atomic transfer
**CWE-362 · OWASP A04 · Medium**

- **Where:** `TransferController::store()` — read, compute, write, with no
  transaction and no row lock.
- **Reach it:** fire concurrent transfers; the `transactions` ledger makes the
  double-spend visible, which it was not in the legacy app.
- **Fix:** `DB::transaction()` with `lockForUpdate()`, or an atomic decrement.

## VULN-18 · Client-side-only validation
**CWE-602 · OWASP A04 · Low**

- **Where:** `resources/views/transfer/form.blade.php` — the legacy `isNaN()` check
- **Reach it:** call the endpoint directly. The control is visible and protects
  nothing, which makes the point better than removing it would.
- **Fix:** validate server-side.

## VULN-19 · Hardcoded credentials · **VULN-22** · Over-privileged database account
**CWE-798 / CWE-250 · OWASP A07 / A01 · Medium**

- **Where:** `dbscript/banktable.sql` — `groot` / `bose123$` with
  `GRANT ALL PRIVILEGES ON *.* WITH GRANT OPTION`
- **Note:** this credential is the foundation of the planned direct-database
  MCP lesson (`mcp-design.md` §6.9).
- **Fix:** per-application least-privilege grants; credentials from environment.

## VULN-20 · Credential disclosure in the UI
**CWE-200 · OWASP A01 · Low**

- **Where:** `layouts/app.blade.php` footer — demo credentials on every page.
- **Note:** the legacy footer's `happay123$` typo is corrected to the seeded
  `happy123$`.

## VULN-21 · Sensitive data exposure
**CWE-200 · OWASP A02 · High**

- **Where:** `AccountController::lookup()`, `::lookupSafe()`, `::me()`, and
  `app/Models/User.php` (no `#[Hidden]` attribute)
- **Reach it:** `GET /api/v2/accounts/2` returns the MD5 hash. Feeds VULN-15.
- **Fix:** `#[Hidden(['password'])]` and stop selecting the column.

## VULN-23 · Information disclosure via debug mode
**CWE-209 · OWASP A05 · Low**

- **Where:** `APP_DEBUG=true` + `bootstrap/app.php` JSON exception rendering
- **Reach it:** trigger any exception; the JSON carries message, file, line and
  full stack trace. Richer than the legacy HTML warnings and easier to harvest.
- **Fix:** `APP_DEBUG=false`.

## VULN-24 · User enumeration · **VULN-25** · No rate limiting
**CWE-204 / CWE-307 · OWASP A07 · Low / Medium**

- **Where:** `AuthController::login()`; `routes/api.php` (no `throttle`)
- **Reach it:** compare responses for a real and a fake username; then guess
  passwords without any lockout.
- **Fix:** one generic error message; `->middleware('throttle:5,1')`.

## VULN-35 · Non-idempotent transfers (replay)
**CWE-837 · OWASP A04 · Medium**

- **Where:** `TransferController::store()`, `database/migrations/…_create_transactions_table.php`
- **Reach it:** replay a captured request; it executes again.
- **Fix:** a client-supplied idempotency key with a unique index.

## VULN-66 · Mass assignment
**CWE-915 · OWASP A04 · High**

- **Where:** `app/Models/User.php` (`$guarded = []`, no `#[Fillable]`) and
  `RegisterController::registerJson()`
- **Reach it:**
  ```json
  {"name":"mallory","pwd":"x","email":"m@e.vil","tel":"1","admin":1,"active":1}
  ```
  → a self-service administrator account, unauthenticated.
- **Fix:** `#[Fillable(['username','password','email','mobile'])]`.

## VULN-67 · Content type confusion
**CWE-436 · OWASP A08 · Medium**

- **Where:** `RegisterController::registerJson()` — the body is read raw and
  JSON-decoded regardless of the declared `Content-Type`.
- **Note:** the legacy client already did this — `regjson.php` posted JSON while
  declaring `application/x-www-form-urlencoded`. An existing quirk promoted to a
  lesson. It also means input reaches the app without the validation layer ever
  seeing it.
- **Fix:** parse according to the declared type and reject mismatches.

---

## Framework defaults that had to be opted out of

Two are worth calling out because they are *not* security controls, and their
opt-outs are documented in `bootstrap/app.php`:

- **`TrimStrings`** strips the trailing space from `' or '1'='1' -- `. MySQL
  only treats `--` as a comment when followed by whitespace, so this default
  breaks published payloads for a reason unrelated to any lesson.
- **`ConvertEmptyStringsToNull`** rewrites `param=` to `NULL`, changing what
  reaches the interpolated SQL.

And two that *are* controls, widened deliberately: the `{acno}` route parameter
pattern (VULN-05) and libxml's external entity blocking (VULN-09).
