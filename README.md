# GrownitFX — PHP + MT5 Account Onboarding Portal

## 1) Project Goal

GrownitFX is a lightweight PHP web application that connects your website onboarding flow with the **MetaTrader 5 Web API**.

It is designed to:
- Register users into MT5 from a web form.
- Authenticate users by MT5 login/password.
- Show essential live MT5 account details on a dashboard.
- Keep a minimal local user record for website-level tracking.

---

## 2) Core Features

- **MT5 registration flow** using `POST /api/user/add`.
- **MT5 login validation** using `POST /api/user/check_password`.
- **Dashboard data fetch** using `GET /api/user/get?login=...`.
- **Generated MT5-compatible password** during registration.
- **Session-based auth** for dashboard access.
- **Basic local persistence** in MySQL (`users` table).
- **User-facing error diagnostics** with MT5 `retcode` context.

---

## 3) Tech Stack

- **Backend:** PHP 8+
- **Database:** MySQL / MariaDB (via PDO)
- **External Integration:** MT5 Web API (HTTP + JSON)
- **Frontend:** Server-rendered PHP + existing static CSS/JS/fonts assets
- **Runtime requirements:** PHP extensions `pdo_mysql`, `curl`, `mbstring`

---

## 4) High-Level Architecture

1. Browser sends request to `register.php`, `login.php`, `dashboard.php`.
2. App bootstraps DB + MT5 modules via `app/bootstrap.php`.
3. MT5 calls are routed through `app/MT5/HttpClient.php` and endpoint wrappers under `app/MT5/API/*`.
4. Local data access goes through `app/db/Database.php` and `app/db/UserRepository.php`.
5. Session stores active MT5 login id (`$_SESSION['user_login_id']`).

---

## 5) Folder Structure

```text
.
├── README.md
├── register.php                # Register user to MT5, generate credentials
├── login.php                   # MT5 login verification page
├── dashboard.php               # Dashboard + MT5 user/get data
├── logout.php                  # Session logout
├── app/
│   ├── bootstrap.php           # Central file includes
│   ├── config/
│   │   ├── config.local.php    # Active config (current pages include this)
│   │   └── config.prod.php     # Production template values
│   ├── db/
│   │   ├── Database.php        # PDO connection + optional DB/schema bootstrap
│   │   ├── UserRepository.php  # User queries
│   │   └── schema.sql          # users table DDL
│   └── MT5/
│       ├── HttpClient.php      # Generic MT5 HTTP client
│       ├── Session.php         # Cookie file path from PHP session
│       └── API/
│           ├── AUTH/
│           │   ├── Authentication.php
│           │   └── Hash.php
│           └── USER/
│               ├── Add.php
│               ├── CheckPassword.php
│               └── GET/
│                   └── Get.php
├── css/
├── js/
├── images/
├── fonts/
└── storage/
    └── mt5cookies/             # writable cookie storage for MT5 session
```

---

## 6) Setup Guide (Local / VPS / cPanel)

## 6.1 Prerequisites

- PHP 8.1+ (8.2+ recommended)
- MySQL/MariaDB
- Enabled extensions:
  - `pdo_mysql`
  - `curl`
  - `mbstring`
- Outbound HTTPS allowed to your MT5 API server

---

## 6.2 Configure App

Edit `app/config/config.local.php`:

- Database:
  - `DB_HOST`
  - `DB_PORT`
  - `DB_NAME`
  - `DB_USER`
  - `DB_PASS`
- MT5:
  - `MT5_BASE_URL`
  - `MT5_MANAGER_LOGIN`
  - `MT5_MANAGER_PASSWORD`
  - `MT5_VERSION`
  - `MT5_AGENT`
  - `MT5_GROUP`
  - `MT5_LEVERAGE`
  - `MT5_COOKIE_DIR`

> Note: current entry pages include `config.local.php` directly. If you want environment-specific switching, add your own config loader strategy.

---

## 6.3 Database Initialization

### Option A (recommended for cPanel/shared hosting)

1. Create database + database user in cPanel.
2. Grant privileges to the DB user.
3. Import `app/db/schema.sql` using phpMyAdmin.

### Option B (auto-create path)

`app/db/Database.php` can attempt DB create + schema load if DB is missing (MySQL error 1049), but this requires DB user permissions for database creation.

---

## 6.4 File Permissions

Ensure this directory is writable by PHP runtime:

- `storage/mt5cookies/`

Without this, MT5 session cookie creation will fail.

---

## 6.5 Run Locally

```bash
php -S 0.0.0.0:8080 -t .
```

Then open:
- `http://localhost:8080/register.php`
- `http://localhost:8080/login.php`
- `http://localhost:8080/dashboard.php`

---

## 7) Request Flows

## 7.1 Registration

`register.php` flow:
1. Validate name/country/email.
2. Generate MT5-compatible password.
3. Authenticate manager (`/api/auth/start`, `/api/auth/answer`).
4. Create MT5 user (`/api/user/add`).
5. Save local user (hashed main password + mt5_login_id).
6. Show modal with generated credentials.

## 7.2 Login

`login.php` flow:
1. Accept `login_id` + password.
2. Authenticate manager.
3. Validate user credentials with `/api/user/check_password`.
4. Store `$_SESSION['user_login_id']` and redirect to dashboard.

## 7.3 Dashboard

`dashboard.php` flow:
1. Require `$_SESSION['user_login_id']`.
2. Authenticate manager.
3. Fetch user data via `/api/user/get?login=...`.
4. Render essential fields + error state if MT5 call fails.

---

## 8) cPanel Deployment Checklist

- [ ] Upload full project (including `app`, `css`, `js`, `fonts`, `images`, `storage`).
- [ ] Set production DB + MT5 credentials in config.
- [ ] Create/import DB schema (`app/db/schema.sql`).
- [ ] Make `storage/mt5cookies` writable.
- [ ] Confirm required PHP extensions are enabled.
- [ ] Confirm outbound HTTPS access to MT5 API host.
- [ ] Test Register, Login, Dashboard in browser.

---

## 9) Security Recommendations

- Move secrets out of committed PHP files (env variables or secure config outside web root).
- Rotate exposed credentials immediately if previously committed.
- Disable verbose MT5 error display in public production if needed.
- Serve over HTTPS only.
- Add CSRF/rate-limit hardening if app is internet-facing.

---

## 10) Troubleshooting Quick Notes

- **`Unknown database`**: DB not created or wrong DB config.
- **`Unable to create MT5 cookie directory`**: permission issue on `storage/mt5cookies`.
- **MT5 non-zero `retcode`**: check manager credentials/group/MT5 permissions and host connectivity.
- **Dashboard redirect loop**: missing session (`user_login_id`) or session/cookie misconfiguration.

---

## 11) License

Internal/proprietary unless explicitly specified otherwise.
