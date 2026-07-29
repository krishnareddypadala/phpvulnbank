# Proposed Lessons — Filling A02, A04, A05, A06, A08, A09

**Companion to:** [`legacy-mapping.md`](legacy-mapping.md) (Phase 1 inventory) and the migration plan's §6/§7.
**Status:** design proposal. No code written. Needs sign-off before Phase 5.

The Phase 1 inventory found the legacy app strong on injection (A03) and access control (A01), and empty or near-empty on six other OWASP 2021 categories. This document designs those six in.

---

## 1. Why some of these need new functionality

Three of the six categories have **no surface in the app to hang a lesson on**, so a vulnerability cannot simply be "left in":

- **A06 Vulnerable Components** — the legacy app has zero dependencies. There is nothing to be outdated. Moving to Composer creates the surface for free.
- **A08 Data Integrity Failures** — nothing is ever serialized, signed, or verified. There is no integrity boundary to break.
- **A09 Logging Failures** — nothing is logged at all. You cannot demonstrate inadequate logging without first having logging.

The other three (**A02**, **A04**, **A05**) have a token presence — unsalted MD5, negative-amount transfers, an over-privileged database user — but nowhere near enough to teach the category.

**The design rule for everything below:** new functionality must be something a real bank application would genuinely have. A feature invented purely to host a bug teaches nothing, because the learner correctly reads it as artificial. Every feature proposed here exists in real retail banking, and the vulnerability is then a realistic mistake within it — not the reason the feature exists.

The plan's §6 discipline still applies: **one documented opt-out per lesson, at the narrowest possible scope, with a marker comment.** Never disable a protection globally.

---

## 2. Summary

| Category | Current state | New functionality required | New lessons |
|---|---|---|---|
| **A02** Cryptographic Failures | MD5 only | F3 Card vault, F4 Remember-me, F5 Password reset | VULN-26 … 30 |
| **A04** Insecure Design | Business-logic bugs only | F1 Beneficiaries, F2 Transfer OTP | VULN-31 … 35 |
| **A05** Security Misconfiguration | Debug warnings, over-privileged DB user | None — configuration only | VULN-36 … 40 |
| **A06** Vulnerable Components | **Nothing** | None — dependency pinning only | VULN-41, 42 |
| **A08** Data Integrity Failures | **Nothing** | F6 Transfer templates | VULN-43 … 45 |
| **A09** Logging & Monitoring | **Nothing** | F7 Audit log + viewer | VULN-46 … 49 |
| *(bonus)* | | F8 Statement export | VULN-50, 51 |

Eight new features, twenty-six new lessons. §9 tiers them by value so this can land incrementally rather than all at once.

**JWT and API-token lessons are proposed separately** in [`proposed-lessons-jwt.md`](proposed-lessons-jwt.md) (features F9–F10, `VULN-52` … `VULN-65`). They are split out because they add an entire token-authenticated API surface rather than flaws to an existing one, and are therefore a separate go/no-go decision. That document's capstone chain depends on VULN-37 and VULN-46 from this one.

---

## 3. Proposed functionality

| ID | Feature | Route(s) | Why a real bank has it | Carries |
|---|---|---|---|---|
| **F1** | Beneficiary management — add, list, delete payees | `/beneficiaries` | You cannot transfer to a stranger's account without registering them first | A04 |
| **F2** | Transfer OTP / step-up authentication | `/transfer/confirm` | Regulatory strong-customer-authentication requirement | A04 |
| **F3** | Debit card vault — store and display a masked card | `/cards` | Every retail bank app shows your card | A02 |
| **F4** | "Remember this device" persistent login | login checkbox | Standard convenience feature | A02 |
| **F5** | Password reset by email link | `/password/forgot`, `/password/reset` | Mandatory; nothing else recovers an account | A02, A07 |
| **F6** | Saved transfer templates | `/transfer/templates` | "Repeat last month's rent payment" | A08 |
| **F7** | Audit log + admin log viewer | `/admin/logs` | Non-negotiable for banking compliance | A09 |
| **F8** | Statement export to CSV | `/statement/export` | Every bank offers a downloadable statement | A02, A04 |

F1 and F7 also close gaps the migration plan already assumed existed — its §3 proposes `Beneficiary` and `AuditLog` models that have no counterpart in the legacy schema.

---

## 4. A02 — Cryptographic Failures

### VULN-26 · Reversible card storage with a static IV
**CWE-329 / CWE-327 · Feature F3**

The card vault stores a card number encrypted with AES-CBC using a **hardcoded key and a fixed all-zero IV**, so identical card numbers produce identical ciphertext. The CVV is stored in plaintext, which no payment system may ever do.

- **Opt-out:** bypass Laravel's `Crypt` facade (which handles IV generation and MAC correctly) with a hand-rolled `openssl_encrypt(..., OPENSSL_RAW_DATA, $staticIv)` in `App\Support\LegacyCrypto`.
- **Reach it:** register two cards with the same number on different accounts and compare the stored ciphertext — they match. Ciphertext is also malleable, so flipping bits in the first block corrupts predictably.
- **Fix:** `Crypt::encryptString()`, and never store the CVV at all.

Keeping all deliberately weak crypto in one `LegacyCrypto` helper mirrors the plan's `LegacyQuery` chokepoint — one auditable place, easy to enumerate.

### VULN-27 · Predictable "remember me" token
**CWE-330 · Feature F4**

The persistent-login cookie is `md5($username . date('Y-m-d'))` — derived entirely from public data, so any user's token can be computed offline. It is stored unhashed in the database, never expires, and is not invalidated on logout or password change.

- **Opt-out:** do not use Laravel's built-in `remember_token` plumbing; write the cookie by hand.
- **Reach it:** compute the token for `admin`, set the cookie, browse as admin.
- **Fix:** `Str::random(60)`, hashed at rest, rotated on use, invalidated on logout and password change.

### VULN-28 · Predictable password reset token
**CWE-340 · Feature F5**

The reset token is `uniqid()`, which is derived from the current time in microseconds. Tokens are guessable within a narrow window, never expire, and are reusable.

- **Opt-out:** hand-rolled reset flow instead of Laravel's `Password` broker.
- **Fix:** Laravel's broker — cryptographically random, hashed, single-use, expiring.

### VULN-29 · Reset token leaked via the Referer header
**CWE-598 · Feature F5**

The reset page loads an external image and puts the token in the **query string**, so the token is transmitted in the `Referer` header to a third party. This is a genuinely common real-world bug and pairs well with the existing SSRF lesson.

- **Fix:** POST the token, add `Referrer-Policy: no-referrer`, and invalidate on first use.

### VULN-30 · Insecure session and cookie configuration
**CWE-614 / CWE-1004**

Session and remember-me cookies are issued without `Secure`, `HttpOnly` or `SameSite`, over plain HTTP.

- **Opt-out:** `config/session.php` with `'secure' => false, 'http_only' => false, 'same_site' => null`, commented as intentional.
- **Reach it:** the existing stored XSS (VULN-13) can now read `document.cookie` and exfiltrate a live session — this **upgrades an existing lesson** by chaining into it, at zero cost.

---

## 5. A04 — Insecure Design

A04 is about controls that were never designed, not code that was written wrong. The lessons must therefore be *absences*, and the learner's job is to notice what is missing.

### VULN-31 · OTP disclosed in the API response
**CWE-200 · Feature F2**

`POST /transfer/confirm` returns the generated OTP in its JSON response body so the front end can "pre-fill it for convenience". The client never displays it, so the flaw is invisible in the browser and visible immediately in an intercepting proxy.

- **Reach it:** initiate a transfer, read the OTP from the response, submit it.
- **Fix:** never return the OTP; deliver it out of band.

This is one of the most frequently found real flaws in banking APIs and is an excellent argument for testing beyond the UI.

### VULN-32 · OTP with no attempt limit and no binding
**CWE-307 / CWE-799 · Feature F2**

The OTP is four digits, has unlimited retries, does not expire, is reused across attempts, and — most importantly — is **not bound to the transaction it authorises**. An OTP issued for a ₹10 transfer authorises a ₹100,000 transfer.

- **Fix:** six digits, five attempts, five-minute expiry, single use, and an HMAC binding the OTP to the amount and destination account.

### VULN-33 · No transfer limits or velocity controls
**CWE-770 · Feature F2 / existing transfer**

No per-transaction cap, no daily aggregate cap, no unusual-destination check. Combined with the existing negative-amount flaw (VULN-16), an account can be drained in one request.

- **Fix:** a policy object enforcing per-transaction and rolling-24-hour limits.

### VULN-34 · New beneficiary is immediately usable
**CWE-841 · Feature F1**

Real banks impose a cooling-off period and a separate confirmation before a newly added payee can receive money. Here, add-payee and pay-payee are a single uninterrupted flow, so the existing CSRF flaw (VULN-10) escalates: one forged request can add an attacker's account *and* pay it.

- **Fix:** activation delay plus independent confirmation on the add-beneficiary step.

### VULN-35 · Non-idempotent transfers (replay)
**CWE-837 · existing transfer**

There is no idempotency key, so replaying a captured request executes the transfer again. This makes the existing race-condition lesson (VULN-17) concrete and demonstrable rather than theoretical.

- **Fix:** a client-supplied idempotency key, unique-indexed on the transactions table.

---

## 6. A05 — Security Misconfiguration

No new functionality needed — these are all configuration decisions, which is precisely the category's point.

### VULN-36 · Debug mode enabled in a production-shaped deployment
**CWE-489**

`APP_DEBUG=true` with Ignition. Richer than the legacy `display_errors`: stack traces, environment variables, executed queries, and loaded config. Already anticipated by the migration plan's §6.

### VULN-37 · `.env` and application logs readable from the web root
**CWE-540 / CWE-552**

The Docker vhost serves the project root rather than `public/`, exposing `.env` (and therefore `APP_KEY`) and `storage/logs/laravel.log`.

- **Opt-out:** the Apache `DocumentRoot` in the Docker config, commented as intentional.
- **This is the entry point to the capstone chain in §8** and must be present for VULN-44 to be reachable.

### VULN-38 · Laravel Telescope exposed without authentication
**CWE-1188**

Telescope installed with its authorisation gate returning `true` unconditionally, so `/telescope` exposes every request, query, session payload and exception to anonymous users.

- **Reach it:** browse `/telescope/requests` and read another user's login request, including the submitted password.
- **Fix:** restrict the `viewTelescope` gate to admins and never install it in production.

A very realistic Laravel misconfiguration and a frequent real-world breach source.

### VULN-39 · Permissive CORS with credentials
**CWE-942**

`config/cors.php` set to `'allowed_origins' => ['*']` with `'supports_credentials' => true` on the `/api/*` routes, so any origin can make authenticated cross-origin calls.

- **Fix:** an explicit origin allow-list; never pair a wildcard with credentials.

### VULN-40 · Missing security headers → clickjacking
**CWE-1021 / CWE-693**

No `Content-Security-Policy`, `X-Frame-Options`, `Strict-Transport-Security` or `Referrer-Policy`. The transfer form can be framed and UI-redressed.

- **Reach it:** extend `payload/csrf/offer.html` — which §7.5 of the inventory already flags as needing repair — into a clickjacking PoC. One payload file, two lessons.
- **Fix:** a header middleware; frame-ancestors in CSP.

---

## 7. A06 and A08

### VULN-41 · Dependency with a known published advisory
**CWE-1035 · A06**

Pin one Composer package to a version carrying a published advisory, and pin an old front-end library (an outdated jQuery served from `public/`) so the lesson covers both back-end and front-end supply chains.

> **Select the exact package and version at implementation time** by running `composer audit` against candidates, rather than hardcoding an advisory ID from memory here. The version must be genuinely flagged by the tooling — the lesson is worthless if the scanner stays silent.

- **Opt-out:** a `composer.json` constraint pinning the old version, with a marker comment and a `docs/vulnerabilities.md` entry.
- **CI angle:** the pipeline runs `composer audit` and **reports without gating**, which is the real lesson — most organisations discover the finding and ship anyway. Make the pipeline's non-gating behaviour explicit and commented.

### VULN-42 · Unpinned Docker base and unverified artefacts
**CWE-494 · A06/A08**

The build pulls an unpinned tag with no digest and no signature verification.

- **Fix:** pin by digest; verify signatures; keep an SBOM.

> Note this is the one lesson in tension with the migration plan's §1 guardrail requiring a pinned, non-`latest` Docker tag. **Resolve it in favour of the guardrail:** teach the lesson in `docs/vulnerabilities.md` and in a clearly-marked `Dockerfile.unpinned` that is never the default build. Shipping an actually-unpinned image is an operational risk, not a lesson.

### VULN-43 · PHP object injection via a saved transfer template
**CWE-502 · A08 · Feature F6**

"Save this transfer as a template" serializes a `TransferTemplate` object into a cookie and `unserialize()`s it on load, with no signing and no `allowed_classes` restriction.

- **Opt-out:** raw `serialize`/`unserialize` instead of JSON, in `App\Support\LegacyQuery`'s sibling helper.
- **Reach it:** craft a serialized payload invoking a gadget chain reachable through the installed dependency tree (the logging library is the classic source), then load the template.
- **Fix:** `json_encode`/`json_decode`, or `unserialize($data, ['allowed_classes' => false])` plus a signed payload.

This is the single most PHP-characteristic vulnerability the app currently lacks.

### VULN-44 · Forged encrypted cookie via a leaked `APP_KEY`
**CWE-347 · A08**

Laravel encrypts cookies with `APP_KEY` and, in current versions, deliberately does **not** serialize their payloads. Re-enabling serialization on the cookie encrypter restores the classic Laravel deserialization RCE pattern (the shape of CVE-2018-15133) for anyone holding the key — which VULN-37 hands them.

- **Opt-out:** construct the cookie `Encrypter` with serialization enabled, narrowly and with a marker comment.
- **Fix:** leave serialization off, keep `APP_KEY` out of the web root, and rotate it if ever exposed.

### VULN-45 · Unsigned webhook / callback trust
**CWE-345 · A08**

The transaction-notification callback accepts any POST body as authentic with no HMAC signature and no replay window, so balances can be adjusted by an unauthenticated caller.

- **Fix:** HMAC-signed payloads with timestamp validation.

---

## 8. The capstone chain

The highest-value thing this proposal adds is not any single lesson — it is that four of them **compose into one realistic breach**, which is how real incidents actually unfold and something the current app cannot demonstrate at all:

1. **VULN-37 (A05)** — the vhost serves the project root, so `.env` is fetched over HTTP.
2. `APP_KEY` is read from it.
3. **VULN-44 (A08)** — a Laravel session cookie is forged and, with serialization re-enabled, becomes a deserialization sink.
4. **VULN-41 (A06)** — the pinned vulnerable dependency supplies the gadget chain.
5. **VULN-43 (A08)** — the payload executes; the attacker has RCE.
6. **VULN-46 (A09)** — nothing in the audit log records any of it.

Each step is individually a modest misconfiguration. Chained, they are a full compromise with no forensic trail. That progression is the argument for defence in depth, and it is worth writing up as a guided walkthrough in `docs/vulnerabilities.md` rather than leaving learners to assemble it themselves.

---

## 9. A09 — Logging and Monitoring Failures

### VULN-46 · Security-relevant events are not logged
**CWE-778 · Feature F7**

The audit log records *successful* transfers and nothing else. Not recorded: failed logins, password changes, privilege changes, beneficiary additions, admin actions, or any failed authorisation. The full RCE chain in §8 leaves no trace.

- **Fix:** log authentication outcomes, authorisation failures, and every privilege or money-movement event.

### VULN-47 · Log injection / entry forgery
**CWE-117 · Feature F7**

Usernames are written to the log unescaped, so a CRLF in a registered username (which registration already accepts unvalidated) lets an attacker inject fabricated log lines and frame another user.

- **Reach it:** register `attacker\n[INFO] admin logged in successfully`, then view `/admin/logs`.
- **Fix:** strip or encode newlines; prefer structured JSON logging.

A clean second-order lesson that reuses the existing unvalidated-registration flaw.

### VULN-48 · Sensitive data written to logs
**CWE-532 · Feature F7**

The audit logger writes full request payloads, so passwords, OTPs and card numbers land in `laravel.log` — which VULN-37 already exposes to the web. Another two-lesson chain.

- **Fix:** redact via `$dontFlash`-style filtering before logging.

### VULN-49 · No alerting, and logs are mutable by their subject
**CWE-778 / CWE-732 · Feature F7**

A hundred failed logins in ten seconds produce no alert, and `/admin/logs` offers a "clear log" action reachable through the same missing-function-level-access-control flaw as `activate.php` (VULN-12) — so an attacker deletes the evidence.

- **Fix:** threshold alerting; append-only storage; separate the ability to read logs from the ability to erase them.

---

## 10. Bonus — Statement export (F8)

### VULN-50 · CSV formula injection
**CWE-1236**

The feedback field is exported to CSV unescaped. A value beginning with `=`, `+`, `-` or `@` is interpreted as a formula when the statement is opened in a spreadsheet, enabling command execution on the *recipient's* workstation.

- **Reach it:** set feedback to `=cmd|' /C calc'!A1`, have an admin export statements.
- **Fix:** prefix risky leading characters with a single quote, or quote every field.

Worth including because it moves the attack off the server and onto a staff workstation — a category of risk the app does not otherwise teach, and one that static analysers flag.

### VULN-51 · Forgeable export link
**CWE-639 · A02/A01**

Export URLs are `?ref=` + base64 of `acno|timestamp`, with no signature — decode, increment the account number, re-encode, download another customer's statement. Base64 read as a security control is a persistent real-world misconception.

- **Fix:** Laravel signed URLs (`URL::signedRoute`) plus an ownership check.

---

## 11. Schema additions

New tables, all additive. None modifies `banktable`, so the §7.1 decision in the inventory stays open and every existing SQL-injection payload keeps working.

| Table | Purpose | Notable columns |
|---|---|---|
| `beneficiaries` | F1 | `user_id`, `payee_acno`, `nickname`, `activated_at` (nullable — the missing cooling-off control) |
| `transfer_otps` | F2 | `user_id`, `otp` (plaintext — deliberate), `attempts`, `expires_at` (unused) |
| `cards` | F3 | `card_number_encrypted`, `cvv` (plaintext — deliberate), `expiry` |
| `remember_tokens` | F4 | `user_id`, `token` (unhashed — deliberate) |
| `password_resets` | F5 | `email`, `token` (unhashed), `used_at` (never set) |
| `transfer_templates` | F6 | `user_id`, `payload` (serialized blob) |
| `audit_logs` | F7 | `actor`, `action`, `detail`, `created_at` — deliberately **no** `ip_address` or `user_agent` |
| `transactions` | ledger | recommended in inventory §7.1; makes VULN-17 and VULN-35 demonstrable |

---

## 12. Impact on the migration plan

- **Structure (§3):** add `BeneficiaryController`, `CardController`, `PasswordResetController`, `TemplateController`, `AuditLogController`, `StatementController`; add `App\Support\LegacyCrypto` alongside `LegacyQuery` as the second audited chokepoint.
- **Phases (§4):** this is **new scope, not porting**, and must not be interleaved with Phase 5. Add a **Phase 5b** after the faithful port is complete and verified. A learner should be able to check out the tag where the Laravel app is behaviourally identical to the legacy one, before new lessons land.
- **Verification (§10):** every lesson here needs the same exploitability regression test the plan demands, and the §8 chain needs an end-to-end test — it spans four lessons and is exactly the kind of thing a framework upgrade silently breaks.
- **CI (§9):** `composer audit` becomes a real, deliberately non-gating stage. Expect the SAST signal to *rise* again after this work, partially offsetting the drop the plan predicts from framework adoption — worth measuring, since the before/after comparison is itself DevSecOps material.
- **Guardrails (§1):** this proposal adds unauthenticated RCE paths (§8) to an app that already has three. The localhost-only rule becomes correspondingly more important, and `SECURITY.md` should describe the chain explicitly.

---

## 13. Priority

| Tier | Lessons | Rationale |
|---|---|---|
| **1 — do first** | VULN-36, 37, 41, 43, 46, 47 | Closes A05/A06/A08/A09 from zero. Low effort: two are config, one is a `composer.json` line. Delivers the §8 chain almost immediately |
| **2 — high value** | VULN-26, 28, 31, 32, 44, 48, 50 | Needs features F2, F3, F5. The most realistic banking lessons in the set |
| **3 — completes coverage** | VULN-27, 29, 30, 33, 34, 35, 38, 39, 40, 42, 45, 49, 51 | Rounds out each category; several are single-line config opt-outs once the features exist |

Tier 1 alone takes all six categories from absent to represented, and is achievable without building any new feature except the audit log.

---

## 14. Open questions

1. **Does new scope belong in this repo at all,** or in a `phpvulnbank-laravel-extended` branch? Keeping the Laravel port a faithful translation has real value for before/after scanner comparison, which new lessons destroy.
2. **How far should the OTP flow go?** A realistic implementation needs mail delivery (the plan's §9 already suggests MailHog). If that is too heavy, VULN-31 alone still teaches most of the lesson without any delivery mechanism.
3. **Is a deliberately vulnerable dependency acceptable to your scanners?** It will generate genuine Dependabot alerts and may trip organisational policy on the GitHub repo. Confirm before pinning, and note it prominently in `SECURITY.md`.
4. **Card data, even fake, changes the repo's risk profile.** Confirm you are comfortable with a training app that models PAN and CVV storage; the alternative is to use obviously-fake tokenised values only.
