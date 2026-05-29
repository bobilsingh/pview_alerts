# PView Alert System

A web-based alert and ticket workflow application built with **CodeIgniter 4** (PHP 8),
Bootstrap 5, and jQuery. Designed for flat, easy-to-edit source with all frontend
dependencies vendored locally so the UI runs without internet access.

---

## File structure

```text
pview_alerts/
│
├── app/
│   ├── Common.php                        # Global helper bootstrap
│   │
│   ├── Config/
│   │   ├── Routes.php                    # All application routes
│   │   ├── Database.php                  # DB connection settings
│   │   ├── Email.php                     # SMTP / mail settings
│   │   ├── App.php                       # Base URL, release version
│   │   └── ...                           # Other CI4 framework config files
│   │
│   ├── Controllers/
│   │   ├── BaseController.php            # Shared auth + model loading
│   │   ├── app.php                       # All main page + API controllers
│   │   └── user.php                      # Login, logout, user CRUD, settings
│   │
│   ├── Helpers/
│   │   └── alert_helper.php             # Auth helpers, badge renderers,
│   │                                     # app_setting(), TAT helpers,
│   │                                     # validate_password(), validate_user_id()
│   │
│   ├── Models/
│   │   ├── app_model.php                 # Projects, flows, states, tickets,
│   │   │                                 # alerts, escalation, API keys,
│   │   │                                 # app_settings, dashboard stats
│   │   └── user_model.php                # Users: login, session, CRUD
│   │
│   └── Views/
│       ├── templates/
│       │   ├── header.php               # <!DOCTYPE html> … <body> + pre-paint JS
│       │   ├── sidebar.php              # Sidebar nav + topbar (all secured pages)
│       │   ├── footer.php               # Closing </body></html>
│       │   ├── auth_header.php          # Login / auth page shell open
│       │   └── auth_footer.php          # Login / auth page shell close
│       │
│       ├── login.php                    # Sign-in form
│       ├── dashboard.php                # KPI cards + severity / trend charts
│       ├── projects.php                 # Project list + add form
│       ├── flows.php                    # Flow list, states, drag-to-reorder
│       ├── alerts.php                   # Alert definition list + add form
│       ├── tickets.php                  # My tickets / All tickets / Detail / Raise
│       ├── users.php                    # User list + add / edit form
│       └── settings.php                 # App settings key/value editor (admin)
│
├── public/
│   ├── index.php                        # CI4 front controller
│   ├── .htaccess                        # mod_rewrite rules
│   ├── favicon.ico
│   ├── robots.txt
│   └── assets/
│       ├── css/
│       │   └── app.css                  # All custom styles (versioned ?v=N)
│       ├── js/
│       │   └── app.js                   # All custom JS (versioned ?v=N)
│       ├── fonts/                       # Inter font files + google-fonts.css
│       └── vendor/                      # Vendored frontend libraries
│           ├── bootstrap/               # Bootstrap 5
│           ├── bootstrap-icons/         # Bootstrap Icons
│           ├── chartjs/                 # Chart.js
│           ├── datatables/              # DataTables + Bootstrap 5 skin
│           ├── jquery/                  # jQuery 3.7
│           ├── jquery-ui/               # jQuery UI 1.13
│           ├── select2/                 # Select2 (searchable dropdowns)
│           ├── sweetalert2/             # SweetAlert2 (confirm dialogs)
│           └── toastr/                  # Toastr (toast notifications)
│
├── vendor/                              # Composer packages (CI4 framework, PHPMailer)
├── writable/                            # CI4 cache, logs, sessions, uploads
│   ├── cache/
│   ├── logs/
│   ├── session/
│   └── uploads/
│
├── database_upgrade.sql                 # Adds users.user_id + app_settings table
├── tat_monitor.php                      # CLI script: auto-escalate breached tickets
├── preload.php                          # PHP preload script (optional OPcache)
├── composer.json
├── spark                                # CI4 CLI tool
├── .env                                 # Local environment overrides (not committed)
└── env                                  # Example .env template
```

---

## How a request flows

1. Browser hits e.g. `/tickets`.
2. `app/Config/Routes.php` maps it to a method in `app.php` (or `user.php`).
3. `BaseController::initController()` checks session auth and loads models.
4. The controller queries `app_model.php` / `user_model.php`.
5. The controller renders:
   `templates/header.php` → `templates/sidebar.php` → page view → `templates/footer.php`
6. The browser loads static assets from `public/assets/` (all offline-vendored).

---

## Setup

1. Make sure Apache and MySQL are running (XAMPP, Laragon, etc.).
2. Copy `env` to `.env` and set your values:

```env
CI_ENVIRONMENT = development
app.baseURL    = 'http://localhost/pview_alerts/public/'
```

3. Update `app/Config/Database.php` with your DB credentials.
4. Create the database and import the schema:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS alert_system CHARACTER SET utf8mb4;"
mysql -u root -p alert_system < database_upgrade.sql
```

5. _(Optional)_ Configure SMTP in `app/Config/Email.php` for alert email delivery.
6. Open `http://localhost/pview_alerts/public/` in your browser.
7. Default login: **`admin`** / **`Admin@1234`**

> If `mod_rewrite` is enabled you can also use `http://localhost/pview_alerts/`
> with the root `.htaccess` redirecting to `public/`.

---

## Logging in: User ID vs Email

The login form accepts **either** a User ID or an email address:

| Field                 | Purpose                                              |
| --------------------- | ---------------------------------------------------- |
| User ID (e.g. `jdoe`) | Primary login identifier — stored in `users.user_id` |
| Email                 | Notification delivery only — not required to sign in |

The form detects a `@` character: if present it matches against `email`, otherwise against `user_id`.

---

## Admin settings

The **Settings** page (sidebar › System › Settings) edits the `app_settings` key/value table.
Changes take effect immediately — no code deployment required.

| Key                                                 | Effect                                                             |
| --------------------------------------------------- | ------------------------------------------------------------------ |
| `app_name`                                          | Brand name shown in sidebar, page title, and login screen          |
| `password_min_length`                               | Minimum characters required for a new password                     |
| `password_require_letter`                           | `1` = password must contain a letter                               |
| `password_require_digit`                            | `1` = password must contain a digit                                |
| `password_rotate_days`                              | Days before users are prompted to change their password            |
| `upload_max_mb`                                     | Maximum file size for ticket attachments                           |
| `upload_allowed_ext`                                | Comma-separated list of permitted file extensions                  |
| `default_tat_l1_minutes` … `default_tat_l4_minutes` | TAT defaults applied when a state leaves a level blank             |
| `datatable_page_length`                             | Default rows per page on ticket list tables                        |
| `login_show_demo_creds`                             | `1` shows demo credentials on login screen (dev environments only) |

To add a new setting, insert a row into `app_settings`. The Settings page renders
unrecognised keys under an _Other_ group automatically.

---

## TAT monitor

`tat_monitor.php` checks open and in-progress tickets and auto-escalates any that
have breached their configured TAT (Time-to-Acknowledge).

**Linux cron (every minute):**

```bash
* * * * * php /var/www/html/pview_alerts/tat_monitor.php >> /var/log/tat.log 2>&1
```

**Windows Task Scheduler:**

- Program: `C:\xampp8\php\php.exe`
- Arguments: `C:\xampp8\htdocs\pview_alerts\tat_monitor.php`
- Trigger: every 1 minute

---

## REST API

External systems can raise and update tickets via HTTP using an API key generated from
the **API Keys** screen.

**Raise a new alert:**

```bash
curl -X POST http://localhost/pview_alerts/public/api/raise \
  -H "X-API-KEY: <your_key>" \
  -H "Content-Type: application/json" \
  -d "{\"project_id\":1,\"flow_id\":1,\"title\":\"ETL failed\",\"alert_type\":\"critical\"}"
```

**Get ticket status:**

```bash
curl -H "X-API-KEY: <your_key>" \
  http://localhost/pview_alerts/public/api/alert/ALM-20260507-00001
```

**Update ticket (resolve / close / comment):**

```bash
curl -X POST http://localhost/pview_alerts/public/api/alert/ALM-20260507-00001/update \
  -H "X-API-KEY: <your_key>" \
  -H "Content-Type: application/json" \
  -d "{\"action\":\"resolved\",\"comment\":\"Fixed by pipeline restart\"}"
```

| Field        | Values                                  |
| ------------ | --------------------------------------- |
| `alert_type` | `info` \| `major` \| `critical`         |
| `priority`   | `low` \| `medium` \| `high` \| `urgent` |
| `action`     | `resolved` \| `closed` \| `comment`     |

---

## Deployment notes

- `vendor/` — Composer packages (CI4 framework, PHPMailer). Include when copying to another server.
- `public/assets/vendor/` — All frontend libraries vendored locally. No CDN calls at runtime.
- `public/assets/fonts/` — Inter font files served locally.
- `writable/` — Must be writable by the web server (`chmod 775` on Linux).
- Do **not** commit `.env` — it contains credentials. Use the `env` template as a reference.
