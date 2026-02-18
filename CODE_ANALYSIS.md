# GrownitFX Code Analysis

## High-level architecture
- The repo contains a static marketing site at root (`index.html`, assets in `css/`, `js/`, `images/`) and a lightweight PHP portal under `portal/`.
- The portal is a custom MVC-style app:
  - Entry point + route map: `portal/public/index.php`.
  - Bootstrap/autoload/.env load/session start: `portal/app/bootstrap.php`.
  - Core primitives: router, controller base class, CSRF/session/validation in `portal/app/Core`.
  - Business logic: auth + dashboard controllers in `portal/app/Controllers`.
  - Persistence: PDO wrapper and models in `portal/app/Database` + `portal/app/Models`.
  - MT5 integration: `portal/app/Services/MT5WebApiClient.php`.

## What is implemented well
1. **Reasonable baseline security controls**
   - CSRF tokens are generated with `random_bytes` and verified using `hash_equals`.
   - Session IDs are regenerated after login/registration.
   - Session cookie flags include `httponly` and `samesite=Lax`.
   - Output in views is escaped with `htmlspecialchars`.

2. **Input validation and prepared statements**
   - Registration/login inputs are validated through centralized helpers.
   - SQL in models uses prepared statements and parameter binding.

3. **MT5 client flow encapsulation**
   - MT5 handshake and request wrappers are centralized in a dedicated service class.

## Key risks and improvement opportunities

### 1) Open registration can create real MT5 accounts
`AuthController::register()` calls MT5 `addUser` directly for any visitor who passes validation/rate limits. This is potentially high-impact if the endpoint is public (account spam, infra abuse, operational costs). Consider adding invite/admin gating, email verification, CAPTCHA, or stronger anti-automation controls.

### 2) Rate-limit table has no pruning strategy
`rate_limits` rows are appended on each failed attempt, but there is no cleanup path in app code. Over time this can grow unbounded and impact query performance/storage. Add a scheduled purge (e.g., keep 30–90 days), partitioning, or TTL-like cleanup job.

### 3) Login rate limiting can be bypassed by identity variation
Login checks combine IP + identity, where identity is raw `mt5_login` input. Attackers can rotate identities from one IP to spread attempts. Consider a dual-threshold policy:
- global IP bucket (all identities), and
- per IP+identity bucket.

### 4) Database connection bootstrapping is not environment-safe
`DB::pdo()` defaults `DB_NAME`/credentials to empty strings if env vars are missing, which can fail at runtime with opaque PDO errors. Consider explicit config validation at bootstrap time with clear error messages.

### 5) Router only supports GET/POST and exact-path matching
For the current scope this is fine, but maintainability will suffer as routes grow (no route params, no middleware pipeline, no named routes). If this app expands, consider adding route patterns and middleware hooks.

### 6) Dashboard renders raw MT5 payloads
Dashboard view prints raw JSON blobs for user/account responses. This is useful for debugging but may expose internal or sensitive data to end users. Prefer curated fields and role-based visibility for diagnostics.

## Suggested prioritized actions
1. Add registration protection (invite codes/CAPTCHA/email verification) and stronger login abuse controls.
2. Add a cleanup job for `rate_limits` table + index review.
3. Validate required env vars on startup and fail fast with actionable errors.
4. Replace raw MT5 JSON rendering with a minimal, explicit account summary.
5. Add automated tests for validator rules and auth controller edge cases.

## Quick health summary
- **Security posture:** moderate (good primitives, but abuse controls need hardening).
- **Reliability posture:** moderate (simple architecture, but limited defensive config checks).
- **Scalability posture:** low-to-moderate (works for small deployments, needs ops hygiene for growth).
