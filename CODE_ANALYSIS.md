# GrownitFX Code Analysis (Current Repository)

## 1) Architecture snapshot

This repository is a **PHP 8 server-rendered onboarding portal + static marketing site**.

- Public entry pages live at repository root:
  - `register.php` (create MT5 account + local user row)
  - `login.php` (MT5 credential validation)
  - `dashboard.php` (authenticated account view + wallet actions)
- Shared backend modules are under `app/`:
  - `app/bootstrap.php` for includes/autoload bootstrap
  - `app/db/*` for PDO connection and user persistence
  - `app/MT5/*` for MT5 HTTP/session/auth/client wrappers and payment endpoints
- Styling/assets are mostly static (`css/`, `js/`, `images/`, `fonts/`).

The app pattern is intentionally lightweight: direct page controllers + shared service classes rather than a full framework.

---

## 2) Strengths

1. **Clear separation for MT5 integration concerns**
   - Generic transport concerns are isolated in `app/MT5/HttpClient.php`.
   - MT5 manager authentication handshake is encapsulated in `app/MT5/API/AUTH/Authentication.php`.

2. **Reasonable baseline DB safety in core repositories**
   - PDO is configured with exceptions and non-emulated prepares.
   - `UserRepository` uses parameterized SQL for find/create operations.

3. **Deterministic onboarding flow**
   - Registration page validates inputs, provisions MT5 user, then writes local record.
   - Dashboard checks session state and pulls live MT5 data.

4. **Operational fallback for first-time setup**
   - Database layer can bootstrap DB/schema when schema is missing, reducing first-deploy friction.

---

## 3) Major risks and gaps

### A) Secrets committed in plaintext config (**critical**)
`app/config/config.local.php` currently contains DB credentials and MT5 manager credentials in plaintext. This is a major operational/security risk if the repository is shared or backed up externally.

**Impact:** credential leakage, account compromise, environment drift.

**Recommendation:**
- Replace committed runtime secrets with environment variables.
- Keep only a non-secret template in version control.
- Rotate exposed MT5 manager and DB credentials.

### B) Missing CSRF protections on state-changing endpoints
`register.php` form submission and wallet apply endpoints (`deposit_request.php`, `withdraw_request.php`) do not implement anti-CSRF tokens.

**Impact:** authenticated users could be induced to perform unintended state-changing requests.

**Recommendation:**
- Add CSRF token generation/validation for all POST mutations.
- For API-like endpoints, enforce explicit anti-CSRF header/token checks.

### C) Payment admin endpoints rely on static header token
Admin apply endpoints use `X-ADMIN-TOKEN` and optional IP allowlist. This is workable but fragile if token distribution/rotation and ingress controls are weak.

**Impact:** unauthorized balance operations if token leaks.

**Recommendation:**
- Rotate token regularly and keep it out of source control.
- Prefer stronger authentication (HMAC signing or mTLS/service identity) for admin APIs.
- Enforce HTTPS-only ingress and strict network-level allowlists.

### D) Inconsistent authentication model across pages/endpoints
User-facing pages use `$_SESSION['user_login_id']`; some wallet flows also accept `$_SESSION['mt5_login']`. This mixed convention increases maintenance risk and edge-case auth bugs.

**Recommendation:**
- Normalize to a single session key schema and document it.
- Add a shared auth guard helper used by all protected endpoints.

### E) Limited abuse controls (rate limiting / bot resistance)
Registration and login are exposed flows with no clear throttling/CAPTCHA in current implementation.

**Impact:** credential stuffing, account-spam, MT5 API abuse.

**Recommendation:**
- Add IP + identity aware throttling.
- Add CAPTCHA on registration and optionally after failed login thresholds.
- Add audit logs for failed auth and payment attempts.

---

## 4) Reliability and maintainability observations

1. **Error handling quality is mixed**
   - Backend modules throw runtime exceptions with useful context.
   - Some endpoint scripts return raw detail fields that may leak internals to clients.

2. **Schema management is basic**
   - `schema.sql` plus `migrations.sql` exists, but no strict migration runner/versioning discipline is visible.

3. **Codebase is easy to understand but not centrally structured**
   - Root-level page scripts include significant control logic.
   - As features grow, introducing thin service layers (especially for auth/payments) would reduce duplication.

---

## 5) Suggested prioritized roadmap

1. **Immediate (security hotfixes)**
   - Move secrets to environment variables and rotate credentials.
   - Add CSRF protection for registration and wallet mutation endpoints.

2. **Short term (abuse and auth hardening)**
   - Add login/registration throttling and CAPTCHA.
   - Standardize session/auth guard logic for all protected routes.

3. **Medium term (operability)**
   - Introduce structured logging for MT5 request failures and payment lifecycle events.
   - Formalize migration execution order and environment boot checks.

4. **Long term (architecture)**
   - Refactor root page controllers into thin handlers backed by services.
   - Add automated integration tests for registration/login/payment happy+failure paths.

---

## 6) Overall health summary

- **Security posture:** Moderate-to-high risk until secrets + CSRF + abuse controls are addressed.
- **Reliability posture:** Moderate; core flows are straightforward but error surfaces and endpoint consistency can be improved.
- **Maintainability posture:** Moderate for current size; should evolve toward shared guards/services as scope grows.
