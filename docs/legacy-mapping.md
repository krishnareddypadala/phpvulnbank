# PHPVulnBank — Phase 1 Legacy Inventory

**Purpose:** the contract for the Laravel migration. Nothing gets ported until it is on this list.
**Source:** commit `30cda67` (`master`), cloned 29 July 2026.
**Scope covered:** `src/` (31 PHP files), `dbscript/`, `dock/`, `payload/`, plus `Dockerfile`, `DevSecOpS/`, and the CI files.
**Status:** inventory only. No Laravel code written.

> ⚠️ This application is intentionally vulnerable. Everything catalogued below is a lesson to be **preserved**, not a defect to be fixed.

---

## 1. Headline findings

Five things that materially change the migration plan. Details in the sections referenced.

1. **There is an unauthenticated RCE backdoor hidden in `login.php`** that is easy to miss on a read-through and trivial to lose in a port. Posting `pwd=troy` with any shell command as the username executes it. See `VULN-02` in §4. This is the single most important line in the repo.
2. **The migration plan's §5 mapping table is mostly hypothetical.** There is no `index.php`, no `statement.php`, no `search.php`, and no `admin*.php`. The real dashboard is `profile.php`. A corrected mapping is in §6.
3. **The whole app is one flat table, `banktable`** — user, account, balance and feedback in nine columns, no transactions ledger, no beneficiaries, no audit log. Every SQLi payload and every `$row[N]` index depends on that exact shape. Normalising it into the plan's `User`/`Account`/`Transaction` models will silently break every existing exploit. This is the biggest open decision — see §7.1.
4. **Two files do not parse at all** (`src/ssrf_download.php`, `src/api/regapi1.php`) and one PoC is stale (`payload/csrf/offer.html` posts the wrong parameter name at a dead domain). See §7.
5. **Laravel will accidentally *repair* several lessons.** The legacy app's `header('Location: …')` redirects all fire after HTML has already been sent, so they never actually redirect. In Laravel a returned redirect works correctly. Several "broken access control" behaviours therefore depend on a PHP quirk that Laravel does not reproduce. See §7.2.

---

## 2. Database schema

`dbscript/banktable.sql` creates one database (`bankdb`), one user (`groot` / `bose123$`, granted `ALL PRIVILEGES ON *.*` — itself a lesson), and one table.

| # | Column | Type | Notes |
|---|---|---|---|
| 0 | `acno` | `int(11)` PK, AUTO_INCREMENT from 5 | account number |
| 1 | `username` | `varchar(100)` | no unique constraint |
| 2 | `password` | `varchar(100)` | **unsalted MD5** |
| 3 | `balance` | `int(11)` | no ledger; mutated in place |
| 4 | `email` | `varchar(100)` | |
| 5 | `mobile` | `varchar(14)` | |
| 6 | `feedback` | `varchar(1000)` | stored-XSS sink |
| 7 | `active` | `TINYINT` default 0 | gate in the login query |
| 8 | `admin` | `TINYINT` default 0 | the only role marker |

**The numeric indices matter.** Code reads `$row[3]` for balance, `$row[4]` for email, `$row[6]` for feedback and `$row[8]` for admin off `mysqli_fetch_row`. Any column reordering breaks the app and every `UNION SELECT` payload written against it.

Seed data:

| acno | username | password (plaintext) | balance | active | admin |
|---|---|---|---|---|---|
| 1 | `krishna` | `happy123$` | 2840 | 1 | 0 |
| 2 | `admin` | `krishna1$` | 4528 | 1 | 1 |
| 3 | `murali` | `happy1$` | 1030 | 1 | 0 |
| 4 | `srikanth` | `test123$` | 1020 | 0 | 0 |

`srikanth` is inactive on purpose — he is the subject of the account-activation flow.

> **Discrepancy:** `src/ui/footer2.php` advertises krishna's password as `happay123$`. The seed data is `happy123$`. The documented credential is wrong and has presumably been confusing users. Fix in the port.

---

## 3. Page inventory

Auth column: **Session** = checks `$_SESSION['uname']`; **Admin** = calls `admincheck()`; **None** = no check whatsoever.

### 3.1 Authentication

| File | URL / methods | Inputs | SQL | Auth | Output sinks | Lessons |
|---|---|---|---|---|---|---|
| `src/login.php` | `GET,POST /login.php` | `POST uname`, `POST pwd` | `select * from banktable where username='$username' and password=MD5('$password') and active='1'` — both params interpolated | None (this is the gate) | `uname` echoed into an **unquoted** `value=` attribute (L8); `"you are not $username"` (L79) | SQLi → auth bypass, reflected XSS, **`troy` command-injection backdoor**, user enumeration, no rate limiting, weak MD5 |
| `src/logout.php` | `GET /logout.php` | — | — | None | — | `session_regenerate_id()` is commented out; redirect works here (no prior output) |
| `src/admincheck.php` | include only, not routable | `$_SESSION['uname']` | `select * from banktable where username='$user'` | Session | — | Second-order SQLi via stored username; calls `session_start()` inside the function, causing duplicate-start warnings on every caller |

`login.php` calls `session_regenerate_id()` on success, so **session fixation is already mitigated** — contrary to the migration plan's §4 assumption. Do not invent a fixation lesson that the original does not have unless you deliberately add one.

### 3.2 Account

| File | URL / methods | Inputs | SQL | Auth | Output sinks | Lessons |
|---|---|---|---|---|---|---|
| `src/profile.php` | `GET /profile.php` | session only | `select * from banktable where username='$user'` | Session | `$row[4]` email, `$row[5]` mobile, `$row[3]` balance echoed raw; `$row[6]` feedback echoed **inside an HTML comment** | Second-order SQLi; stored XSS (needs `-->` to break out of the comment); **`htmlspecialchars()` is applied to `$row[0]` (`acno`, an integer) and nothing else** — a nice example of sanitising the one field that cannot be attacker-controlled |
| `src/displaydata.php` | `GET /displaydata.php?aid=` | `GET aid` | `select * from banktable where acno=$aid` — **unquoted**, numeric context | **None** (calls `session_start()`, never checks it) | `$row[1]` username, `$row[2]` **MD5 password hash**, `$row[3]` balance | SQLi (clean `UNION` target), IDOR, credential disclosure. Linked from the dashboard as "Show Password" |
| `src/displaydata_safe.php` | `GET /displaydata_safe.php?aid=` | `GET aid` | `select username,password FROM banktable where acno=?` — **prepared** | **None** | username + password hash | The remediated twin of the above. **SQLi is fixed but the IDOR and the credential disclosure are not** — an excellent "the patch was not the whole bug" pair. Binds `"s"` against an integer column |

### 3.3 Transfers

| File | URL / methods | Inputs | SQL | Auth | Output sinks | Lessons |
|---|---|---|---|---|---|---|
| `src/transfer.php` | `GET,POST /transfer.php` | `POST tacno`, `POST tamount` | 4 statements, all interpolated: `select … where username='$fuser'`, `UPDATE … SET balance='$fbalance' where username='$fuser'`, `select … where acno='$tacno'`, `UPDATE … SET balance='$tbalance' where acno='$tacno'` | Session | resulting balance echoed | **CSRF** (no token), SQLi via `tacno`, IDOR (transfer to any account), **negative-amount transfer steals funds**, no balance/overdraft check, no DB transaction → race condition, validation is client-side JS only (`isNaN`, trivially bypassed), unknown `tacno` yields a null row and corrupts a balance |
| `src/transfer_csrftoken.php` | `GET,POST /transfer_csrftoken.php` | `POST tacno`, `POST tamount`, `POST csrftoken` | identical to above | Session + token | same | The remediated twin. Token is `md5(uniqid(rand(), TRUE))`. **Every other flaw survives** — SQLi, IDOR, negative amounts and the race are all still present. Token compare is `==`, not `hash_equals` |

### 3.4 Feedback

| File | URL / methods | Inputs | SQL | Auth | Output sinks | Lessons |
|---|---|---|---|---|---|---|
| `src/feedback.php` | `GET /feedback.php` | — | — | Admin (router) | — | Pure dispatcher: admins → `feedback_admin.php`, others → `feedback_user.php`. Its `header()` calls fire after output and therefore never redirect (§7.2) |
| `src/feedback_user.php` | `GET,POST /feedback_user.php` | `POST fb` | `UPDATE banktable SET feedback='$feedback' WHERE username='$username'` | **None** — reads `$_SESSION["uname"]` without checking it exists | "Feedback updated" | Stored XSS (the store half), SQLi via `fb`, missing auth check |
| `src/feedback_admin.php` | `GET /feedback_admin.php` | — | `select * from banktable` (all rows) | Admin | `"$user : $fback"` echoed **raw** for every user | Stored XSS (the render half — this is where a payload planted by any user fires in an admin's browser). Loop starts at `$i=1`, so **the first user's feedback is never shown** — a real off-by-one worth deciding on (§7.4) |

### 3.5 Admin — KYC and activation

| File | URL / methods | Inputs | SQL | Auth | Output sinks | Lessons |
|---|---|---|---|---|---|---|
| `src/activateform.php` | `GET /activateform.php` | — | `select * from banktable where active='0'` | Admin | usernames into `<option value='…'>` raw | Stored XSS into an attribute; correctly gated |
| `src/activate.php` | `POST /activate.php` | `POST user` | `UPDATE banktable SET active='1' WHERE username='$usertoactivate'` | **None** | `"User $usertoactivate is Activated"` reflected raw | **Missing function-level access control** — the form is admin-gated but the action is not. Any user (or anonymous request) can activate any account. Plus SQLi and reflected XSS. This is the cleanest forced-browsing lesson in the app |
| `src/validatekyc.php` | `GET /validatekyc.php` | — | — | **None** | filenames from `readdir('images')` echoed raw into links | Directory listing of every uploaded KYC document; admin-only by link placement, not by any check. Feeds the traversal sink below |
| `src/kycdownload_ssrf.php` | `GET /kycdownload_ssrf.php?file=` | `GET file` | — | **None** | `readfile($download)` + `echo "$file"` | **Path traversal / LFI** — `?file=../../../../etc/passwd` reads arbitrary files. Reflected XSS on the echoed path |

### 3.6 File upload

| File | URL / methods | Inputs | SQL | Auth | Output sinks | Lessons |
|---|---|---|---|---|---|---|
| `src/fileupload.php` | `GET,POST /fileupload.php` | `POST $_FILES['image']` | — | **None** | `print_r($errors)` | **Unrestricted upload → RCE.** The extension blocklist (`php`, `jsp`, `asp`) and the 2 MB size check are both commented out, so `$errors` is always empty. Writes to `images/` — web-accessible and PHP-executable under the Apache config — then attempts a second `move_uploaded_file` to the web root, which always fails because the temp file is already gone. `src/images/shell.php` is a pre-planted webshell demonstrating the payoff |

### 3.7 Deliberate shells and fetch tools

| File | URL / methods | Inputs | Auth | Lessons |
|---|---|---|---|---|
| `src/odysseus.php` | `GET /odysseus.php?cmd=` | `GET cmd` | **None** | Unauthenticated command injection via backticks. The name is the Trojan-horse companion to the `troy` backdoor in `login.php` |
| `src/shell.php` | `GET /shell.php?c=&submit=` | `REQUEST c` | **None** | Unauthenticated `shell_exec()` |
| `src/images/shell.php` | `GET /images/shell.php` | `REQUEST c` | **None** | Identical shell, pre-planted in the upload directory to prove `images/` executes PHP |
| `src/ssrf_getcontents.php` | `GET /ssrf_getcontents.php?file=` | `GET file` | **None** | **True SSRF** — `file_get_contents()` on an unvalidated parameter accepts `http://`, `file://` and other stream wrappers, so it reaches internal services and local files alike. Installs a custom error handler that converts warnings to exceptions and echoes `$e->getMessage()`, which turns failed fetches into an information-disclosure oracle |

### 3.8 Registration API

| File | URL / methods | Inputs | SQL | Auth | Lessons |
|---|---|---|---|---|---|
| `src/api/regxml.php` | `GET /api/regxml.php` | — | — | None | Client page. Builds XML in JS, POSTs raw to `register.php` |
| `src/api/register.php` | `POST /api/register.php` | XML body via `php://input` | `SELECT … where username='$name'` and `INSERT …` — all interpolated | None | **XXE** (`LIBXML_NOENT | LIBXML_DTDLOAD`), SQLi, unauthenticated account creation. See §7.3 — the XXE may no longer fire on modern PHP |
| `src/api/regjson.php` | `GET /api/regjson.php` | — | — | None | Client page. POSTs a JSON body to `regapi.php` while declaring `Content-Type: application/x-www-form-urlencoded` — a content-type-confusion lesson in its own right |
| `src/api/regapi.php` | `POST /api/regapi.php` | JSON body via `php://input` | same two statements, interpolated | None | SQLi via any JSON field, unauthenticated account creation, no input validation, MD5 password storage |
| `src/api/regapi1.php` | — | — | — | — | **Does not parse.** Abandoned mass-assignment experiment. See §7.3 |

New accounts are created with `active='0'` and `admin='0'`, so registration alone grants nothing — it must be paired with the `activate.php` access-control flaw to become useful. That chain (register → self-activate → log in) is worth documenting as a scenario.

---

## 4. Vulnerability catalogue

Proposed IDs for `docs/vulnerabilities.md`. Severity is the training severity, not a CVSS score.

| ID | Class | CWE | OWASP 2021 | Primary location | Sev |
|---|---|---|---|---|---|
| VULN-01 | SQL injection → authentication bypass | CWE-89 | A03 | `login.php:49` | Critical |
| VULN-02 | **Hidden command-injection backdoor** | CWE-78 | A03 | `login.php:74-78` | Critical |
| VULN-03 | Command injection (webshells) | CWE-78 | A03 | `odysseus.php:5`, `shell.php:16`, `images/shell.php:14` | Critical |
| VULN-04 | Unrestricted file upload → RCE | CWE-434 | A04 | `fileupload.php:37-49` | Critical |
| VULN-05 | SQL injection (numeric context) | CWE-89 | A03 | `displaydata.php:29` | High |
| VULN-06 | SQL injection (string context, many sites) | CWE-89 | A03 | `transfer.php`, `feedback_user.php`, `activate.php`, `api/*` | High |
| VULN-07 | Path traversal / LFI | CWE-22 | A01 | `kycdownload_ssrf.php:8` | High |
| VULN-08 | SSRF | CWE-918 | A10 | `ssrf_getcontents.php:13` | High |
| VULN-09 | XXE | CWE-611 | A05 | `api/register.php:6` | High |
| VULN-10 | CSRF | CWE-352 | A01 | `transfer.php` (absent token) | High |
| VULN-11 | IDOR / broken object-level authz | CWE-639 | A01 | `displaydata.php`, `displaydata_safe.php`, `transfer.php` | High |
| VULN-12 | Missing function-level access control | CWE-862 | A01 | `activate.php`, `validatekyc.php`, `feedback_user.php`, `fileupload.php` | High |
| VULN-13 | Stored XSS | CWE-79 | A03 | `feedback_user.php` → `feedback_admin.php`, `profile.php`, `activateform.php` | Medium |
| VULN-14 | Reflected XSS | CWE-79 | A03 | `login.php:8,79`, `activate.php:9`, `kycdownload_ssrf.php:6` | Medium |
| VULN-15 | Weak password hashing (unsalted MD5) | CWE-916 | A02 | schema + every write path | Medium |
| VULN-16 | Business-logic flaw: negative-amount transfer | CWE-840 | A04 | `transfer.php:52,64` | High |
| VULN-17 | Race condition / non-atomic balance update | CWE-362 | A04 | `transfer.php:48-69` | Medium |
| VULN-18 | Client-side-only validation | CWE-602 | A04 | `transfer.php:15-27` | Low |
| VULN-19 | Hardcoded DB credentials | CWE-798 | A07 | every file that connects | Medium |
| VULN-20 | Credential disclosure in UI | CWE-200 | A01 | `ui/footer2.php:5` | Low |
| VULN-21 | Sensitive data exposure (password hashes) | CWE-200 | A02 | `displaydata.php`, `displaydata_safe.php` | High |
| VULN-22 | Over-privileged DB account | CWE-250 | A01 | `banktable.sql:5-6` | Medium |
| VULN-23 | Info disclosure via PHP warnings | CWE-209 | A05 | every broken `header()` call (§7.2) | Low |
| VULN-24 | User enumeration | CWE-204 | A07 | `login.php` | Low |
| VULN-25 | No rate limiting on login | CWE-307 | A07 | `login.php` | Medium |

**Candidate additions** — Laravel-native lessons the plan suggests in §6, all of which fit the existing domain: mass assignment via `$guarded = []` on the role column (revives the abandoned `regapi1.php` idea), and route-model binding without an authorisation check (a natural fit for `displaydata`).

---

## 5. Non-lesson files

These port straight across with no deliberate opt-outs.

| File | Disposition |
|---|---|
| `src/ui/header.php` | → `layouts/app.blade.php` (login-page variant) |
| `src/ui/header2.php` | → same layout; differs only in emitting a stray unopened `</main>` |
| `src/ui/footer.php` | → layout footer |
| `src/ui/footer2.php` | → layout footer **with the demo-credentials block** (VULN-20 — keep it, fix the typo) |
| `src/ui/header copy.php` | **Dead.** Orphaned, never included, references a `css.css` that does not exist. Do not port |
| `src/images/img.jpg` | Sample KYC document. Keep as a seeded fixture |
| `Media/docker_phpvulnbank.gif` | Unchanged |
| `DAST_PenTest_Run_by_Claude by Anthropic.pdf` | Unchanged. Note the filename contains spaces |

---

## 6. Corrected legacy → Laravel mapping

This replaces §5 of the migration plan, which was written against guessed filenames. Six of its eleven rows referenced files that do not exist.

| Legacy file | Proposed route | Controller@method | View | Lessons |
|---|---|---|---|---|
| `src/login.php` | `GET,POST /login` | `Auth\LoginController@show`, `@authenticate` | `auth.login` | VULN-01, 02, 14, 24, 25 |
| `src/logout.php` | `POST /logout` | `Auth\LoginController@logout` | — | — |
| `src/profile.php` | `GET /profile` | `AccountController@show` | `account.profile` | VULN-06, 13 |
| `src/displaydata.php` | `GET /account/lookup` | `AccountController@lookup` | `account.lookup` | VULN-05, 11, 21 |
| `src/displaydata_safe.php` | `GET /account/lookup-safe` | `AccountController@lookupSafe` | `account.lookup` | VULN-11, 21 (SQLi fixed) |
| `src/transfer.php` | `GET,POST /transfer` | `TransferController@form`, `@submit` | `transfer.form` | VULN-06, 10, 11, 16, 17, 18 |
| `src/transfer_csrftoken.php` | `GET,POST /transfer/protected` | `TransferController@protectedForm`, `@protectedSubmit` | `transfer.form` | VULN-06, 11, 16, 17 |
| `src/feedback.php` | `GET /feedback` | `FeedbackController@index` | — | — |
| `src/feedback_user.php` | `GET,POST /feedback/mine` | `FeedbackController@edit`, `@update` | `feedback.user` | VULN-06, 12, 13 |
| `src/feedback_admin.php` | `GET /feedback/all` | `FeedbackController@all` | `feedback.admin` | VULN-13 |
| `src/activateform.php` | `GET /admin/activate` | `AdminController@activateForm` | `admin.activate` | VULN-13 |
| `src/activate.php` | `POST /admin/activate` | `AdminController@activate` | — | VULN-06, 12, 14 |
| `src/validatekyc.php` | `GET /admin/kyc` | `AdminController@kycIndex` | `admin.kyc` | VULN-12 |
| `src/kycdownload_ssrf.php` | `GET /admin/kyc/download` | `AdminController@kycDownload` | — | VULN-07, 14 |
| `src/fileupload.php` | `GET,POST /kyc/upload` | `ProfileController@uploadForm`, `@upload` | `profile.upload` | VULN-04, 12 |
| `src/ssrf_getcontents.php` | `GET /tools/fetch` | `UtilityController@fetch` | `tools.fetch` | VULN-08 |
| `src/odysseus.php` | `GET /tools/odysseus` | `UtilityController@odysseus` | `tools.shell` | VULN-03 |
| `src/shell.php` | `GET /tools/shell` | `UtilityController@shell` | `tools.shell` | VULN-03 |
| `src/api/regxml.php` | `GET /api/register/xml` | `Api\RegisterController@xmlForm` | `api.regxml` | — |
| `src/api/register.php` | `POST /api/register/xml` | `Api\RegisterController@registerXml` | — | VULN-06, 09 |
| `src/api/regjson.php` | `GET /api/register/json` | `Api\RegisterController@jsonForm` | `api.regjson` | — |
| `src/api/regapi.php` | `POST /api/register/json` | `Api\RegisterController@registerJson` | — | VULN-06 |
| `src/admincheck.php` | — | `AdminCheck` middleware or a `User::isAdmin()` helper | — | VULN-06 (second-order) |
| `src/images/shell.php` | seeded fixture in the upload dir | — | — | VULN-03 |
| `src/ssrf_download.php` | **do not port** — see §7.3 | — | — | — |
| `src/api/regapi1.php` | **do not port as-is** — see §7.3 | — | — | — |
| `src/ui/header copy.php` | **do not port** — dead file | — | — | — |
| `dbscript/banktable.sql` | → one migration + `BankTableSeeder` | — | — | VULN-15, 22 |
| `payload/csrf/offer.html` | keep as exercise material, **repair it** — §7.5 | — | — | — |

The plan's suggestion to keep legacy URLs as aliases is worth doing: `Route::redirect('/login.php', '/login')` and so on. The archived DAST report and any existing write-ups reference the `.php` URLs directly.

---

## 7. Flagged ambiguities — decisions needed before Phase 3

Per the kickoff instruction, these are surfaced rather than guessed.

### 7.1 Schema normalisation — the big one

The plan's §3 proposes `User`, `Account`, `Transaction`, `Beneficiary`, `Feedback` and `AuditLog` models. **Only `banktable` exists.** There are no transactions, no beneficiaries and no audit log; a transfer just mutates two `balance` integers with no record that it happened.

The trade-off:

- **Normalising** gives an idiomatic Laravel app and makes `Transaction` a real ledger — which would also make the race-condition lesson (VULN-17) much more visible. But every `UNION SELECT` payload written against the flat nine-column table stops working, and the positional `$row[3]` reads have no equivalent.
- **Keeping one flat table** preserves every existing exploit, write-up and DAST baseline verbatim, at the cost of a schema no Laravel developer would recognise as normal.

**Recommendation:** keep `banktable` as the single source of truth for the SQLi lessons, mapped to one `User` model with `$table = 'banktable'`, and add a genuinely new `transactions` ledger table alongside it for the transfer lessons. That preserves the payloads while giving the transfer flow somewhere real to write. It needs your sign-off because it changes what the app teaches about transfers.

### 7.2 The redirects do not currently work

Every page starts by including a header that emits HTML, and only then calls `header('Location: …')` for its auth check. Headers are already sent by that point, so **none of those redirects fire** — PHP emits a warning and execution continues.

The practical impact is smaller than it looks: the sensitive logic is inside `if` blocks, so an anonymous visitor to `profile.php` sees the page shell and a warning, not another user's balance. The real leak is the warning itself, which discloses full filesystem paths (VULN-23).

This matters for the port because **Laravel's `return redirect()` works correctly**, so a faithful-looking port silently changes behaviour on every one of these pages. Options: reproduce the broken behaviour deliberately (echo before redirecting), or let Laravel redirect properly and drop VULN-23. **Recommendation:** let the redirects work, and keep VULN-23 alive through `APP_DEBUG=true` instead, which the plan already calls for in §6.

Two of these redirects also point at **`login.html`, which does not exist** (`transfer_csrftoken.php:82`, `feedback_admin.php:37`). Even with output buffering they would 404. Presumed typo for `login.php` — confirm.

### 7.3 Files that do not parse

> Determined by reading the source; no PHP runtime was available in this environment to confirm. Verify with:
> ```bash
> docker run --rm -v "$PWD/src:/src" php:8.3-cli sh -c 'php -l /src/ssrf_download.php; php -l /src/api/regapi1.php'
> ```

- **`src/ssrf_download.php`** — line 12 contains a stray `<?php include "ui/header2.php";?>` in the middle of an already-open PHP block. A nested `<?php` is a fatal parse error, so the file cannot ever have run. (The call to `file_download()` before its definition is fine on its own — PHP hoists top-level functions — so the stray tag is the only defect.) `kycdownload_ssrf.php` is the same code without the stray line and is the working version. **Recommendation:** port only `kycdownload_ssrf.php`; drop this one. It contributes no lesson that the working file does not.
- **`src/api/regapi1.php`** — opens `class User {`, never closes it, and then continues with procedural code inside the class body. It also calls `$this->isAdminAllowed()`, which is never defined. This is clearly an abandoned experiment in a mass-assignment lesson (`isAdmin` guarded by an authorisation check that was never written). **Recommendation:** do not port the broken file; instead implement the idea it was reaching for as the Laravel-native mass-assignment lesson the plan suggests in §6, with `$guarded = []` on the `admin` column. That turns dead code into the cleanest new lesson available.

### 7.4 Does the `feedback_admin.php` off-by-one stay?

The display loop is `for($i=1; $i<$num; $i++)`, so the first row is never rendered. This looks like an ordinary bug rather than a lesson. **Recommendation:** fix it in the port and note the change, since a hidden first row makes the stored-XSS lesson (VULN-13) unreliable to demonstrate — whether the payload fires depends on where the attacker's row happens to sort.

### 7.5 The CSRF PoC is stale

`payload/csrf/offer.html` posts `bacno` and `tamount` to `http://krishnarp.guru/transfer.php`. The parameter `transfer.php` actually reads is **`tacno`**, so the PoC would not work even against the live legacy app, and the domain is not the local lab. It needs both the parameter name and the target URL corrected during the port. Confirm the intended target is the local instance.

### 7.6 Is the XXE lesson still alive?

`api/register.php` calls `libxml_disable_entity_loader(true)` and then loads with `LIBXML_NOENT | LIBXML_DTDLOAD`. On PHP 8.0+ that function is deprecated and a no-op, and external entity loading is off by default in libxml 2.9+. **The XXE may already be dead in the legacy app on the PHP version the Docker image ships.** This needs an empirical check before it is catalogued as a working lesson — if it does not fire, the port should use `libxml_set_external_entity_loader()` to re-enable it deliberately and document that as the opt-out.

### 7.7 The `troy` backdoor is unauthenticated RCE

Flagging explicitly for the guardrails discussion, not as a question about whether to port it. `POST uname=<any shell command>&pwd=troy` executes that command as the web user, with no credentials. Combined with the plan's §1 warning about how easy a Laravel app is to deploy, this is the strongest argument for the localhost-only binding rule. It should be `VULN-02` and get the most prominent warning in `SECURITY.md`.

---

## 8. Infrastructure notes for Phase 6

- **`Dockerfile`** — based on `ubuntu:24.04` (already pinned, good). Installs Apache + PHP + MySQL + **openssh-server** in one image and exposes port 22. The README instructs `docker run -it -p 8090:80 -p 22:22`, which publishes SSH on the host. Per the plan's §9, decide whether SSH is a lesson or a convenience; it currently reads as convenience and should probably go.
- **`dock/dock.sh`** — the container entrypoint **prompts interactively** (`read ch`) for SSH account creation, so the container only starts under `-it`. Any CI that runs it non-interactively will hang. This must change for a docker-compose workflow.
- MySQL is bootstrapped with `mysql -u root` and no password; the SQL script grants `groot` `ALL PRIVILEGES ON *.*` (VULN-22).
- **Image tag mismatch** — the README says `krishnapadala55/phpvulnbank:25.04` while the Dockerfile base is `ubuntu:24.04`. Harmless but confusing; align them.
- **`DevSecOpS/DAST_Scan_Zap.ps1`** targets `http://127.0.0.1:8090/phpvulnbank/`, but `dock.sh` prints `http://localhost:8090/login.php`. The scan path looks wrong for the container layout — it would find nothing at that URL. Worth verifying before treating the archived DAST results as a baseline.
- **`DevSecOpS/fortifyscan.ps`** is a duplicate of `fortifyscan.ps1` left behind by a rename (commit `b523854`). Delete it.
- **`Jenkinsfile`** — every Fortify parameter is an empty string, so the pipeline is a skeleton rather than a working scan. It begins with `#!groovy` followed by `#`-prefixed lines, which are not valid Groovy comments.
- **`azure-pipelines-1.yml`** is the untouched Azure starter template (`echo Hello, world!`). Delete or implement.
- **`.gitattributes`** sets `* text=auto`, which means `dock.sh` can be checked out with CRLF on Windows — exactly the failure the comment at the top of that script warns about. Add `*.sh text eol=lf`.

Expect the SAST signal to drop sharply after the port, as the plan predicts in §9. Concretely: Fortify recognises Eloquent and Blade, so the interpolated-string SQLi that currently lights up across a dozen `mysqli_query()` calls will collapse to whatever the `LegacyQuery` helper exposes. That contrast is itself good DevSecOps material — but it means the before/after scan numbers are not comparable, and the old repo should stay tagged and reachable.

---

## 9. Coverage check

All 31 PHP files under `src/` are accounted for: 24 ported with lessons (§3), 4 non-lesson UI includes plus 1 dead file (§5), 2 unparseable (§7.3). `dbscript/`, `dock/`, `payload/`, `DevSecOpS/` and the CI files are covered in §2, §7.5 and §8.

**Next step:** Phase 2 (scaffold) is blocked on the §7.1 schema decision and unblocked for everything else. The §7.2 redirect decision is needed before Phase 5.

**Coverage gaps:** this inventory catalogues what the legacy app *has*. Six OWASP 2021 categories (A02, A04, A05, A06, A08, A09) are absent or barely present; [`proposed-lessons.md`](proposed-lessons.md) designs them in as a separate Phase 5b, after the faithful port is complete. [`proposed-lessons-jwt.md`](proposed-lessons-jwt.md) covers JWT and API-token flaws as a further Phase 5c.

**Target architecture:** [`api-refactor.md`](api-refactor.md) proposes moving the application to an API-first design. It must be decided **before Phase 2**, because six of the lessons catalogued above break silently under a naive REST conversion.

**MCP layer:** [`mcp-design.md`](mcp-design.md) designs two vulnerable MCP servers (API-backed and direct-database) as Phase 6, and maps them against the intended training curriculum.
