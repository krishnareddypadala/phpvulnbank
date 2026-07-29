# API-First Refactor — Target Architecture

**Companion to:** [`legacy-mapping.md`](legacy-mapping.md), [`proposed-lessons.md`](proposed-lessons.md), [`proposed-lessons-jwt.md`](proposed-lessons-jwt.md).
**Status:** architecture proposal. No code written.
**Decide before:** Phase 2 (scaffold). This changes the shape of the port, so it is much cheaper to settle now than after the controllers exist.

---

## 1. The tension, stated plainly

"Move to APIs wherever possible" and "don't lose the vulnerabilities" are **in direct conflict for a specific, enumerable set of lessons** — and the conflict is not a matter of care or discipline. It is structural.

A JSON API that requires a JSON content type is **not CSRF-able**. An HTML form can only send `application/x-www-form-urlencoded`, `multipart/form-data`, or `text/plain`, and a cross-origin `fetch()` with `Content-Type: application/json` triggers a CORS preflight that a silent attack cannot satisfy. Likewise, a response served as `application/json` does not execute script, so reflected and stored XSS simply stop working when the HTML rendering layer goes away.

So a naive "convert everything to REST" would silently delete **six** of the twenty-five catalogued lessons, and would be indistinguishable from a careful port right up until someone tried to reproduce them. That is precisely the failure mode the migration plan's §12 names as the number-one risk.

The good news: **twenty of twenty-five survive untouched**, several get *sharper* as APIs, and the losses are all recoverable through deliberate, narrowly-scoped design decisions set out in §5. The refactor is worth doing. It just cannot be done blindly.

---

## 2. Lesson survival analysis

### 2.1 Survive unchanged — server-side logic, client-agnostic (20)

Injection, authorisation and file-handling flaws live in the controller, not the rendering layer, so the transport makes no difference to them.

`VULN-01` `02` `03` `04` `05` `06` `07` `08` `09` `11` `12` `15` `16` `19` `21` `22` `24` `25`, plus the deliberate webshells.

### 2.2 Improve as APIs (4)

| Lesson | Why it gets better |
|---|---|
| `VULN-17` Race condition | Concurrent API calls make a double-spend trivially demonstrable. Today the lesson is real but effectively unprovable through a browser |
| `VULN-18` Client-side-only validation | The point becomes self-evident: the JS `isNaN` check is obviously irrelevant when the endpoint is callable directly |
| `VULN-11` IDOR | Becomes textbook BOLA — `GET /accounts/{acno}` with no ownership check is the canonical example of OWASP API1 |
| `VULN-23` Info disclosure | JSON stack traces in an error response are richer and easier to harvest than rendered HTML warnings |

### 2.3 Break unless deliberately preserved (6)

**This is the section that matters.**

| Lesson | Why it breaks | Preserved by |
|---|---|---|
| `VULN-10` CSRF | JSON content type is not forgeable from a form; Laravel's `api` middleware group has no `VerifyCsrfToken` to *opt out of* in the first place | §5.1 |
| `VULN-13` Stored XSS | No server-rendered HTML sink | §5.2 |
| `VULN-14` Reflected XSS | `application/json` does not execute | §5.2 / §5.3 |
| `VULN-20` Credentials in the UI | Footer disappears with the Blade layout | Keep in the client shell — trivial |
| `VULN-30` Cookie flags *(proposed)* | Bearer tokens have no cookie to misconfigure | §5.4 |
| `VULN-40` Clickjacking *(proposed)* | Nothing to frame without an HTML UI | §5.4 |

Note the irony in the CSRF row: moving `/transfer` onto Laravel's `api` routes removes CSRF protection **automatically**, which sounds like it preserves the lesson. It does the opposite — it removes the *protection* while the JSON content type removes the *exploitability*. The result is an endpoint that is theoretically unprotected and practically unattackable, which teaches nothing and, worse, looks correct to a scanner.

---

## 3. Proposed architecture

A **hybrid**, not a pure API. Three layers, each with a job.

```
┌─────────────────────────────────────────────────────────┐
│  Thin browser client  (Blade shell + fetch + innerHTML) │  ← keeps the browser attack surface
├─────────────────────────────────────────────────────────┤
│  JSON API  /api/v2/*   — the system of record           │  ← all business logic, all server-side flaws
├─────────────────────────────────────────────────────────┤
│  Legacy compatibility routes  /login.php → /api/v2/…    │  ← keeps old write-ups and DAST baselines alive
└─────────────────────────────────────────────────────────┘
```

**All business logic moves to the API.** Every flaw in §2.1 and §2.2 lives there and nowhere else — one implementation, one place to audit.

**The client stays deliberately thin and deliberately unsafe.** It is not a real SPA and should not become one. Its only job is to issue `fetch` calls and render responses in a way that preserves the browser-dependent lessons. This is the key architectural decision: the browser surface is retained *on purpose*, as a documented lesson host, not as an accident of not-yet-finished migration.

**Legacy `.php` URLs stay routable**, as the migration plan already recommends. The archived DAST report and any existing exploit write-ups reference them directly.

---

## 4. Authentication model

Dual, and both are needed:

| Mechanism | Used by | Preserves |
|---|---|---|
| Session cookie | Browser client | CSRF, cookie flags, clickjacking, session lessons |
| JWT bearer | "Mobile" API, partner API | Everything in [`proposed-lessons-jwt.md`](proposed-lessons-jwt.md) |

This is realistic — banks genuinely run cookies for web and tokens for mobile against the same back end — and here it is also *necessary*, because dropping cookie auth deletes three lessons outright. Both mechanisms must be accepted on the same endpoints, which is itself a worthwhile lesson in inconsistent authentication surfaces.

---

## 5. Preserving the at-risk lessons

Each of these is a narrowly-scoped, marker-commented opt-out, per the plan's §6 rule.

### 5.1 CSRF (`VULN-10`)

`POST /api/v2/transfers` accepts **`application/x-www-form-urlencoded` in addition to JSON**, and honours session-cookie authentication. Both conditions are required; either alone makes the lesson unexploitable.

- The existing `payload/csrf/offer.html` PoC then works against it — after the parameter-name repair the inventory flags in §7.5.
- `POST /api/v2/transfers/protected` is the remediated twin, requiring JSON plus a token.
- **Document explicitly that accepting form encoding is the vulnerability**, because a reviewer will otherwise read it as harmless content negotiation. That is exactly why the bug is common in the wild.

### 5.2 Stored and reflected XSS (`VULN-13`, `VULN-14`)

The client renders API responses with `innerHTML` rather than `textContent`, on exactly the fields that are the lesson and nowhere else. The vulnerability class changes from server-side XSS to **DOM-based XSS**, which is a fair trade: DOM XSS is more prevalent in modern applications, harder to detect, and less well covered by most training labs.

The `docs/vulnerabilities.md` entry must state that the class has changed, so learners are not hunting a server-side sink that no longer exists.

### 5.3 Reflected XSS, server-side variant (`VULN-14`)

To keep a genuine server-side reflected XSS, one endpoint returns its JSON body with `Content-Type: text/html`. Browsers render it, and injected markup executes directly from the API response.

This is a real and frequently-missed bug, and it teaches something the DOM variant does not: that content-type handling is a security control, not a formatting detail.

### 5.4 Clickjacking and cookie flags (`VULN-30`, `VULN-40`)

Both are properties of the browser client, so both survive as long as the client exists and is served without framing or cookie protections. No extra work beyond not adding the headers — which is the natural state.

---

## 6. Endpoint map

| Legacy page | API endpoint | Client view | Lessons |
|---|---|---|---|
| `login.php` | `POST /api/v2/auth/login` | `auth.login` | 01, 02, 14, 24, 25 |
| `logout.php` | `POST /api/v2/auth/logout` | — | — |
| `profile.php` | `GET /api/v2/accounts/me` | `account.profile` | 06, 13 |
| `displaydata.php` | `GET /api/v2/accounts/{acno}` | `account.lookup` | 05, 11, 21 |
| `displaydata_safe.php` | `GET /api/v2/accounts/{acno}/safe` | `account.lookup` | 11, 21 |
| `transfer.php` | `POST /api/v2/transfers` | `transfer.form` | 06, 10, 11, 16, 17, 18 |
| `transfer_csrftoken.php` | `POST /api/v2/transfers/protected` | `transfer.form` | 06, 11, 16, 17 |
| `feedback_user.php` | `PUT /api/v2/feedback/me` | `feedback.user` | 06, 12, 13 |
| `feedback_admin.php` | `GET /api/v2/feedback` | `feedback.admin` | 13 |
| `activateform.php` | `GET /api/v2/admin/pending-activations` | `admin.activate` | 13 |
| `activate.php` | `POST /api/v2/admin/activate` | — | 06, 12, 14 |
| `validatekyc.php` | `GET /api/v2/admin/kyc` | `admin.kyc` | 12 |
| `kycdownload_ssrf.php` | `GET /api/v2/admin/kyc/download` | — | 07, 14 |
| `fileupload.php` | `POST /api/v2/kyc` (multipart) | `profile.upload` | 04, 12 |
| `ssrf_getcontents.php` | `GET /api/v2/tools/fetch` | `tools.fetch` | 08 |
| `odysseus.php`, `shell.php` | `POST /api/v2/tools/exec` | `tools.shell` | 03 |
| `api/register.php` | `POST /api/v2/register` (XML) | `api.regxml` | 06, 09 |
| `api/regapi.php` | `POST /api/v2/register` (JSON) | `api.regjson` | 06 |

The two registration endpoints are already APIs in the legacy app and port across almost unchanged — the only part of this refactor that is not new work.

---

## 7. Lessons the refactor creates

The refactor pays for itself: an API surface hosts flaw classes that a server-rendered app structurally cannot.

| ID | Lesson | Notes |
|---|---|---|
| `VULN-66` | **Mass assignment via JSON body** — `User::create($request->all())` with `$guarded = []`, letting a caller set `admin: 1` at registration | The Laravel-native lesson flagged in the inventory's §4 and the abandoned `regapi1.php` experiment, finally with a natural home |
| `VULN-67` | **Content-type confusion** — the endpoint parses the body by sniffing rather than by declared type, so form-encoded, JSON and XML all reach different parsers | The legacy app *already seeds this*: `regjson.php` posts a JSON body while declaring `application/x-www-form-urlencoded`. Promote an existing quirk to a first-class lesson |
| `VULN-68` | **HTTP verb tampering** — `GET /api/v2/admin/activate` performs the same state change as `POST` | Makes state-changing GETs — and therefore CSRF via `<img>` — reachable again |
| `VULN-69` | **Excessive data exposure** — endpoints return the full user model, including `admin` and the password hash, and the client hides fields rather than the server omitting them | OWASP API3. The classic "it's not visible in the UI so it isn't exposed" mistake |
| `VULN-70` | **Verbose error responses** — unhandled exceptions return stack traces, file paths and the failing SQL as JSON | Sharper than the legacy HTML warnings, and easy to harvest at scale |
| `VULN-71` | **Unrestricted resource consumption** — list endpoints have no pagination limit, so `?limit=` is unbounded | OWASP API4. Teaches that limits are a security control |

Combined with [`proposed-lessons-jwt.md`](proposed-lessons-jwt.md), this takes the repo to substantial coverage of the OWASP API Security Top 10 — a framework the current app does not touch at all.

---

## 8. What this changes in the plan

- **Phase 2:** run `php artisan install:api`, which the plan lists as optional. It is now required.
- **Structure (§3):** controllers split into `Api\V2\*` holding all logic, and thin `Web\*` controllers returning Blade shells only. `resources/js/` gains the deliberately-unsafe render helper — one file, heavily commented, the single home of every `innerHTML` sink.
- **Phase 5:** the port becomes API-endpoint-first — build and verify the endpoint, then the view. Reordering per page, not extra phases.
- **Verification (§10):** every exploitability test should now assert against the **API**, which makes them far more robust than browser-driven tests. The six lessons in §2.3 additionally need browser-level tests, since an API-level test cannot prove that a DOM XSS or a clickjacking frame actually fires.
- **DAST (§9):** expect the ZAP baseline to change substantially — traditional web DAST is weak against JSON APIs and will report fewer findings even though the flaws are unchanged. That is a genuinely useful DevSecOps result for the repo's pipeline story, and an argument for adding an API-aware scanner alongside ZAP.

---

## 9. Risks

1. **Silent loss of the §2.3 six is the main danger.** They are the only lessons whose survival depends on decisions rather than on porting logic faithfully, and all six fail *invisibly* — the code looks right, the scanner is quiet, and nobody notices until someone tries the exploit. Each needs an exploitability test written **before** the endpoint is refactored.
2. **The thin client will attract "improvement".** Every `innerHTML` is a magnet for a future contributor, a linter, or an AI assistant to "fix". These need the loudest marker comments in the codebase — they are the most fragile lessons in the app precisely because the fix looks so obviously correct.
3. **Scope growth.** This is a rewrite on top of a rewrite. The plan's §12 already warns that flat PHP to Laravel is not mechanical; API-first on top of it compounds that. Consider doing the faithful port first and refactoring to APIs as a separate, reviewable step, so that a broken lesson can be bisected to one change or the other.
4. **Two auth models double the auth surface** to explain, document and test.

---

## 10. Open questions

1. **Should the API refactor happen during the port, or after it?** Doing it after gives a verified checkpoint where the Laravel app is behaviourally identical to the legacy one — the only clean before/after scanner comparison, and a much easier thing to debug. Doing it during avoids writing the server-rendered controllers twice. **My recommendation is after**, for the same reason the new lessons were scoped to Phase 5b: keep the translation and the redesign separately reviewable.
2. **How thin is the client, really?** My recommendation is deliberately minimal — `fetch` plus a render helper, no framework. A React or Vue client would be more realistic but would auto-escape by default, meaning the XSS lessons would need fighting *back* into it, and the build step would obscure the sinks.
3. **Do the legacy `.php` URLs proxy or redirect?** Redirects are simpler; proxying keeps old exploit write-ups working verbatim, including their POST bodies. Proxying is more faithful and more work.
4. **Is `VULN-10` still CSRF, pedagogically?** Preserving it requires an endpoint that deliberately accepts form encoding — arguably an artificial contrivance. The counter-argument is that this is exactly how the bug appears in the real world: an API that kept form support for backwards compatibility. I lean towards keeping it, documented as such, but it is a judgement call about honesty in the teaching material.
