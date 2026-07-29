# Proposed Lessons — JWT and the Mobile Banking API

**Companion to:** [`legacy-mapping.md`](legacy-mapping.md) and [`proposed-lessons.md`](proposed-lessons.md).
**Status:** design proposal. No code written. This is a **separate go/no-go decision** from the other proposed lessons — it adds a whole API surface, not just flaws to an existing one.

---

## 1. Why this needs new functionality

There is no JWT anywhere in the legacy app, and no token-authenticated surface at all. Sessions are cookie-based `$_SESSION` throughout. The only API endpoints (`/api/register*`) are unauthenticated by design, since registration precedes having an account.

So JWT lessons cannot be "left in" — the surface has to exist first. The good news is that the natural surface is one every real bank has and the repo already gestures at: **a mobile banking API**.

Laravel's own default is not JWT. Sanctum issues *opaque* tokens, which have none of these weaknesses. Adopting JWT is therefore itself the first opt-out, and should be documented as one.

---

## 2. Proposed functionality

| ID | Feature | Purpose | Carries |
|---|---|---|---|
| **F9** | Mobile banking API `v2` — JWT bearer auth | The "current" mobile app back end | Most JWT lessons |
| **F9b** | Deprecated `v1` API, still reachable | The old mobile app that was never switched off | Weakest JWT flaws + API9 |
| **F10** | Open Banking partner API | A fintech partner reads balances with its own signed tokens | Key-resolution lessons |

### Endpoints

```
POST   /api/v2/auth/login            → { access_token, refresh_token }
POST   /api/v2/auth/refresh
POST   /api/v2/auth/logout
GET    /api/v2/accounts/me
GET    /api/v2/accounts/{acno}/balance
POST   /api/v2/transfers
GET    /api/v2/admin/users           (admin-scoped)

POST   /api/v1/auth/login            deprecated, still live
GET    /api/v1/accounts/{acno}/balance

GET    /api/partner/v1/balances      partner-signed tokens (RS256 + JWKS)
```

### The versioning device

Running `v1` and `v2` side by side is not a contrivance — banks genuinely leave old mobile API versions running for years because switching them off breaks customers who never updated the app. It buys three things at once:

1. A legitimate in-universe reason to have **several different token verifiers**, which §3 shows is structurally necessary.
2. A lesson in its own right (VULN-64, improper inventory management) that is one of the most commonly exploited real-world API problems.
3. A realistic discovery exercise — the learner has to *find* `v1`, rather than being handed it.

---

## 3. Design constraint: the weakest verifier masks every other lesson

This is the single most important structural point in this document, and it is easy to get wrong.

If one endpoint accepts `alg: none`, then **every other JWT lesson becomes unreachable as a distinct exercise** — a learner will simply strip the signature and never encounter the weak-secret, algorithm-confusion, or `kid`-injection lessons at all. The strongest attack collapses the whole category into one trick.

So the JWT flaws must be **partitioned across separate verifiers**, each independently reachable:

| Verifier | Used by | Deliberate weakness |
|---|---|---|
| `LegacyJwt::verifyV1()` | `/api/v1/*` | Accepts `alg: none`; ignores `exp` |
| `LegacyJwt::verifyV2()` | `/api/v2/*` | HS256 with a weak, guessable secret |
| `LegacyJwt::verifyPartner()` | `/api/partner/*` | RS256, but honours attacker-supplied `kid` and `jku`, and is algorithm-confusion vulnerable |

This is the plan's §6 rule — *one documented opt-out per lesson, at the narrowest possible scope* — applied to a category where ignoring it silently destroys most of the teaching value.

### `App\Support\LegacyJwt`

A third audited chokepoint alongside the plan's `LegacyQuery` and the `LegacyCrypto` proposed in the other document. All deliberately-weak token handling lives here, so the flaws can be enumerated by reading one file.

There is a second, better reason to hand-roll it: **most real-world JWT vulnerabilities come from hand-written or misconfigured verification, not from library defaults.** Maintained libraries such as `firebase/php-jwt` require an explicit algorithm list and reject `none` outright, so several of these lessons cannot be produced through library configuration at all. A hand-rolled verifier is both the only way to stage them and the more realistic depiction of how these bugs actually reach production.

> **Verify at implementation time** which of these states the chosen library can be coerced into, and hand-roll only what it refuses. Do not assume a library can be configured into an unsafe state just because the vulnerability class exists.

### Reference token payload

```json
{
  "iss": "phpvulnbank",
  "sub": "krishna",
  "acno": 1,
  "role": "user",
  "balance": 2840,
  "card_last4": "4242",
  "pwd_hash": "6b1628…",
  "iat": 1753776000,
  "exp": 1753779600
}
```

Deliberately overstuffed — see VULN-60. `role` in the claims, trusted without a database check, is what makes VULN-61 work.

---

## 4. Lesson catalogue

### 4.1 Signature trust

#### VULN-52 · `alg: none` accepted
**CWE-347 · `/api/v1/*`**

The v1 verifier reads the algorithm from the token header and, when it is `none`, skips verification entirely.

- **Reach it:** take any valid token, set `"alg":"none"`, change `sub` to `admin`, drop the signature, keep the trailing dot.
- **Fix:** never read the algorithm from the token; pin it server-side and reject anything else.

#### VULN-53 · Weak HMAC secret
**CWE-326 / CWE-521 · `/api/v2/*`**

Signed HS256 with the secret `bankdb` — thematically consistent with the hardcoded database credentials already in the app (VULN-19), and recoverable offline in seconds with `hashcat` or `jwt_tool`.

- **Reach it:** capture any token, crack the secret offline, then mint tokens for any user with any role.
- **Fix:** a 256-bit random secret from `APP_KEY`-grade entropy, kept out of source control.

Note this lesson also demonstrates that a *correctly implemented* HS256 verifier is still worthless if the secret is guessable — the implementation is fine; the key management is not.

#### VULN-54 · Algorithm confusion, RS256 → HS256
**CWE-347 · `/api/partner/*`**

The partner verifier is designed for RS256 but honours the header's `alg`. Submitting an HS256 token causes it to use the **RSA public key** as the HMAC secret — and the public key is, by definition, public.

- **Reach it:** fetch the public key from the JWKS endpoint, sign a forged token with it using HS256, submit.
- **Fix:** pin the algorithm per key; never let key material be reinterpreted as a different type.

The most instructive JWT lesson available, because the flaw is in the *design* of the verification step rather than in any single wrong line.

#### VULN-55 · Decode without verify on a secondary path
**CWE-347 · `/api/v2/*`**

Authentication middleware verifies the token correctly — but a separate rate-limiting middleware, running earlier, calls a `decode()` helper that only base64-decodes in order to read `sub` for its bucket key. Anything that trusts the output of that earlier decode is unauthenticated.

- **Reach it:** forge a token with an arbitrary `sub` to poison or bypass another user's rate-limit bucket.
- **Fix:** one verification point; never let unverified claims reach application logic.

This is deliberately subtle. It represents the most common real JWT bug — the codebase verifies *somewhere*, so it looks correct, but a second reader of the same token does not.

### 4.2 Key resolution

#### VULN-56 · `kid` header path traversal
**CWE-22 · `/api/partner/*`**

The `kid` header names a key file, concatenated into a path with no validation.

- **Reach it:** set `kid` to a traversal path pointing at a file whose contents are known or empty, then sign with that value. The uploaded-KYC directory (VULN-04) supplies attacker-controlled file contents, so this **chains with the existing upload flaw**.
- **Fix:** treat `kid` as an opaque lookup key against an allow-list, never as a path.

If keys are stored in the database instead, the same parameter yields SQL injection — worth documenting as the variant, since it reuses the app's central theme.

#### VULN-57 · `jku` header SSRF
**CWE-918 + CWE-347 · `/api/partner/*`**

The verifier fetches the JWKS from whatever URL the token's `jku` header specifies.

- **Reach it:** host a JWKS containing your own public key, point `jku` at it, sign with the matching private key. The server fetches it and validates successfully.
- **Fix:** a hardcoded JWKS URL allow-list; never fetch key material at a token's direction.

Doubly valuable: it is simultaneously a full authentication bypass and an SSRF primitive reaching internal services, chaining with the existing VULN-08.

### 4.3 Lifecycle

#### VULN-58 · `exp` never validated
**CWE-613 · `/api/v1/*`**

The claim is issued but never checked, so tokens are valid forever. A token captured from a log or a proxy history remains usable indefinitely.

#### VULN-59 · Revocation table written but never read
**CWE-613 · `/api/v2/auth/logout`**

Logout dutifully inserts the token's `jti` into a `revoked_tokens` table. **No verifier ever consults that table.** Logout returns 200 and changes nothing; refresh tokens never rotate and never expire.

- **Reach it:** log in, capture the token, log out, keep using the token.
- **Fix:** check `jti` against the revocation list on every request, or use short-lived access tokens with rotating refresh tokens.

The most realistic lesson in this document. The control exists, has a table, has code, passes code review — and does nothing. It teaches that the presence of a security mechanism is not evidence that it works, which is exactly what an auditor needs to learn.

### 4.4 Claims

#### VULN-60 · Sensitive data in the token payload
**CWE-312 · all versions**

A JWT is signed, not encrypted — the payload is base64, readable by anyone holding the token. This one carries balance, card last-four, and the MD5 password hash, feeding the existing offline-cracking lesson (VULN-15).

- **Reach it:** paste any token into a decoder.
- **Fix:** claims carry identity and authorisation only; look everything else up server-side.

#### VULN-61 · Privilege escalation via an unverified `role` claim
**CWE-863 · `/api/v2/admin/*`**

Admin authorisation reads `role` from the token instead of from the database. Combined with any signature flaw above, `"role":"admin"` grants the admin API. Even without one, it means a role revoked in the database stays live until the token expires — which, per VULN-58, may be never.

- **Fix:** authorise against current database state; treat claims as identity assertions, not authorisation grants.

#### VULN-62 · `aud` and `iss` not validated
**CWE-345 · `/api/partner/*`**

The partner API accepts any correctly-signed token regardless of audience or issuer, so a token minted for one partner works against another's data, and a customer token works on the partner endpoint.

#### VULN-65 · Claim injection via a hand-built payload
**CWE-74 · `/api/v2/auth/login`**

The payload JSON is assembled by string concatenation rather than `json_encode`. A username containing `","role":"admin` injects a claim at signing time — so the server issues a **legitimately signed** admin token, and every signature check passes.

- **Reach it:** register a username containing the injection (registration already accepts unvalidated input), then log in.
- **Fix:** `json_encode` the claim array; validate usernames.

Included because it is the JWT-shaped expression of the injection theme running through the whole app, and because it defeats learners who assume a valid signature means a trustworthy token.

### 4.5 Surface

#### VULN-63 · Token accepted in a URL query string
**CWE-598 · `/api/v1/*`**

`?token=` is accepted as an alternative to the `Authorization` header, so tokens land in web server logs, browser history, and `Referer` headers on any outbound link.

- **Chains with:** VULN-37 (logs readable from the web root) and VULN-48 (sensitive data written to logs) from the other proposal.
- **Fix:** `Authorization: Bearer` only.

#### VULN-64 · Deprecated API version still live
**CWE-1059 · OWASP API9**

`/api/v1/*` is undocumented, unmonitored, unmaintained — and reachable. It is where the weakest verifier lives, and nothing in the audit log distinguishes v1 traffic from v2.

- **Reach it:** guess or discover `v1` from a stale mobile-app build, JS bundle, or documentation reference.
- **Fix:** inventory every deployed route; decommission on a schedule; monitor deprecated versions.

---

## 5. Capstone chain

Like the chain in the other proposal, these compose into one realistic compromise:

1. **VULN-64** — discover `/api/v1/` is still live.
2. **VULN-52** — v1 accepts `alg: none`.
3. **VULN-61** — forge `"role":"admin"`.
4. **VULN-58** — the forged token never expires.
5. **VULN-60** — decoded payloads from other captured tokens reveal balances and password hashes.
6. **VULN-46** *(other proposal)* — none of it is logged.

An attacker who never learns a single password holds permanent administrative access to the mobile API. Every step is a small, individually defensible decision — which is the point.

---

## 6. Schema additions

| Table | Purpose | Deliberate detail |
|---|---|---|
| `api_clients` | F10 partner registry | `jwks_url` stored but not enforced as an allow-list (VULN-57) |
| `refresh_tokens` | F9 | never rotated, no expiry column (VULN-59) |
| `revoked_tokens` | F9 | written on logout, **never read** (VULN-59) |
| `jwt_keys` | F10 | keyed by `kid`, looked up by concatenation (VULN-56) |

All additive; none touches `banktable`, so the schema decision in the inventory's §7.1 stays open.

---

## 7. OWASP API Security Top 10 (2023) coverage

A useful side effect: this feature set takes the repo from no API-specific coverage to substantial coverage of a framework the web Top 10 does not reach.

| API risk | Covered by |
|---|---|
| API1 Broken Object Level Authorization | `/api/v2/accounts/{acno}/balance` with no ownership check |
| API2 Broken Authentication | VULN-52 … 59, 65 |
| API3 Broken Object Property Level Authorization | VULN-60 |
| API5 Broken Function Level Authorization | VULN-61 |
| API8 Security Misconfiguration | VULN-63; CORS (VULN-39) |
| API9 Improper Inventory Management | VULN-64 |
| API10 Unsafe Consumption of APIs | VULN-57 |

---

## 8. Impact on the migration plan

- **Scaffold (§4 Phase 2):** this is the case for running `php artisan install:api`, which the plan lists as optional.
- **Structure (§3):** add `Api\V1`, `Api\V2` and `Api\Partner` controller namespaces, `App\Support\LegacyJwt`, and per-version auth middleware.
- **Phasing:** a **Phase 5c**, after both the faithful port and the other proposed lessons. It depends on the audit log (VULN-46) for its capstone and on the log exposure (VULN-37) for VULN-63.
- **Testing (§10):** JWT lessons are unusually prone to being silently fixed by a dependency upgrade — a library bump can start rejecting `alg: none` and quietly kill VULN-52. Exploitability regression tests matter more here than anywhere else in the app.
- **CI/DAST (§9):** most DAST tools will not find these without authenticated scanning and a token-manipulation harness. That gap is itself worth demonstrating: it shows why API security testing needs different tooling than web DAST, which is strong DevSecOps material for the repo's existing pipeline story.
- **Guardrails (§1):** VULN-52 plus VULN-61 is unauthenticated administrative access over HTTP with no session cookie required — trivially scriptable and far easier to exploit at scale than any existing flaw. This raises the stakes on the localhost-only rule again.

---

## 9. Priority

| Tier | Lessons | Rationale |
|---|---|---|
| **1 — core** | 52, 53, 58, 60, 61, 64 | The six JWT flaws that appear in nearly every real assessment. Needs only F9 and F9b, no partner API, and delivers the §5 capstone |
| **2 — depth** | 54, 55, 59, 63, 65 | Algorithm confusion and the never-read revocation table are the most instructive lessons here; 55 and 65 are the ones that defeat experienced learners |
| **3 — partner API** | 56, 57, 62 | Requires F10 and JWKS infrastructure. Highest effort; add only if key-resolution attacks are a training goal |

Tier 1 is a meaningful JWT curriculum on its own and does not require the partner API at all.

---

## 10. Open questions

1. **Does a JWT API belong in this app at all?** It is the largest single addition proposed anywhere in these documents — a new authentication model alongside the existing session one. The alternative is a separate companion repo focused on API security, leaving PHPVulnBank as the web-app lab it currently is.
2. **Two auth models, or migrate?** Running JWT alongside `$_SESSION` is realistic (banks do exactly this: cookies for web, tokens for mobile) but roughly doubles the auth surface to explain. Migrating the web app to JWT would be neither realistic nor good practice.
3. **How much of the mobile "app" is needed?** The lessons are all reachable with `curl`, and no client is strictly required. A minimal JS client would make the surface more discoverable but is real work — and note that a JS client leaking the `v1` base URL is a *nice* way to stage the VULN-64 discovery exercise.
4. **Which library, and how much hand-rolling?** Per §3, several lessons are unreachable through configuration of a maintained library. Confirm you are comfortable with a hand-rolled `LegacyJwt` verifier, which is more realistic but means the lessons teach against custom code rather than a recognisable library API.
