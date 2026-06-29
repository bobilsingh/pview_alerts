# pView Alert System — Technical Documentation

**Version:** Current (as of June 2026)  
**Framework:** CodeIgniter 4  
**Purpose:** Master technical reference for development, maintenance, onboarding, support, and future enhancements

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Application Architecture](#2-application-architecture)
3. [Database Documentation](#3-database-documentation)
4. [User Management](#4-user-management)
5. [Project Module](#5-project-module)
6. [Flow Module — Workflow Designer](#6-flow-module--workflow-designer)
7. [Ticket Management](#7-ticket-management)
8. [Workflow Engine](#8-workflow-engine)
9. [Notifications](#9-notifications)
10. [Activity Logging](#10-activity-logging)
11. [Dashboard & Reporting](#11-dashboard--reporting)
12. [Settings & Administration](#12-settings--administration)
13. [REST API](#13-rest-api)
14. [Frontend Architecture](#14-frontend-architecture)
15. [Security & Validation](#15-security--validation)
16. [Background Jobs & Cron](#16-background-jobs--cron)
17. [Deployment & Environment Setup](#17-deployment--environment-setup)
18. [Code Standards](#18-code-standards)
19. [Known Limitations & Future Scope](#19-known-limitations--future-scope)
20. [End-to-End Business Flow](#20-end-to-end-business-flow)

---

## 1. Project Overview

### 1.1 Purpose

pView Alert System is a self-hosted, real-time alert management platform built for Network Operations Centre (NOC) teams. It converts raw alerts from monitoring systems into structured, trackable tickets that follow configurable multi-stage workflows with automatic time-based escalation.

### 1.2 Business Objective

- Eliminate alert fatigue by centralising all alert channels into one system
- Enforce accountability through workflow-based assignment and time limits (TAT — Turnaround Time)
- Automatically escalate unhandled alerts to supervisors before SLAs are breached
- Provide a complete audit trail of every action for compliance and reporting
- Allow external monitoring tools to create and update tickets programmatically via REST API

### 1.3 Key Features

| Feature | Description |
|---|---|
| Multi-project support | Separate namespaces for different clients or teams |
| Configurable workflows | State machine flows with 1–4 escalation levels per state |
| TAT-based auto-escalation | Cron job escalates tickets when time thresholds are exceeded |
| Role-based access control | Per-module, per-action permissions for each role |
| REST API | External systems raise and update tickets via API key auth |
| Async email queue | Notifications queued to DB and sent by background process |
| @mention in comments | Direct notification to specific users from ticket comments |
| Real-time bell badge | Browser polls for actionable ticket count every N seconds |
| Activity audit log | Append-only log of every user action |
| Per-user notification prefs | Opt-out matrix per project and severity |
| Maintenance mode | System-wide lockout for non-admins |
| Dark/light theme | Per-user persistent theme preference |

### 1.4 System Architecture Overview

```
┌───────────────────────────────────────────────────────────┐
│                        Browser                            │
│    jQuery + Bootstrap + DataTables + Chart.js +           │
│    vis-network + Select2 + SweetAlert2 + Toastr           │
└───────────────────┬───────────────────────────────────────┘
                    │ HTTPS
┌───────────────────▼───────────────────────────────────────┐
│              Apache / Nginx (Web Server)                  │
│            Document root → public/                        │
└───────────────────┬───────────────────────────────────────┘
                    │
┌───────────────────▼───────────────────────────────────────┐
│          CodeIgniter 4 Application (PHP 8.1+)             │
│  ┌──────────┐  ┌────────────┐  ┌────────────────────────┐ │
│  │ user.php │  │  app.php   │  │   Helpers              │ │
│  │ Controller│  │ Controller │  │ app_helper.php         │ │
│  │ (auth,   │  │ (all other │  │ (centralized)          │ │
│  │  users,  │  │  modules)  │  │ security_helper.php    │ │
│  │  roles)  │  │            │  └────────────────────────┘ │
│  └────┬─────┘  └─────┬──────┘                            │
│       │              │                                    │
│  ┌────▼──────────────▼────────────────────────────────┐  │
│  │               Models                               │  │
│  │  app_model.php (tickets, flows, projects, ...)     │  │
│  │  user_model.php (users, roles, permissions)        │  │
│  └────────────────────────┬───────────────────────────┘  │
└───────────────────────────┼───────────────────────────────┘
                            │
┌───────────────────────────▼───────────────────────────────┐
│              MySQL / MariaDB (22 tables)                  │
└───────────────────────────────────────────────────────────┘

Background:
┌───────────────────────────────────────────────────────────┐
│   tat_monitor.php (Cron, every minute)                    │
│   → Reads tickets → Escalates → Flushes email queue      │
└───────────────────────────────────────────────────────────┘
```

### 1.5 Technology Stack

| Layer | Technology | Version |
|---|---|---|
| PHP framework | CodeIgniter 4 | ~4.5.0 |
| PHP | PHP | 8.1+ |
| Database | MySQL / MariaDB | 8.0+ / 10.4+ |
| Email | PHPMailer | ^6.9 |
| CSS framework | Bootstrap | 5.x |
| Icons | Bootstrap Icons | latest |
| DOM/AJAX | jQuery | 3.7.1 |
| UI widgets | jQuery UI | 1.13.2 |
| Tables | DataTables | latest |
| Dropdowns | Select2 | latest |
| Charts | Chart.js | latest |
| Graph rendering | vis-network | latest |
| Dialogs | SweetAlert2 | latest |
| Toast notifications | Toastr | latest |

---

## 2. Application Architecture

### 2.1 Folder Structure

```
pview_alerts/
├── app/
│   ├── Config/
│   │   ├── Routes.php          ← All URL-to-controller mappings (157 lines, 57+ routes)
│   │   ├── App.php             ← Base URL, CI4 framework config
│   │   ├── Email.php           ← SMTP defaults (overridden by app_settings table)
│   │   ├── Session.php         ← Session driver (database), cookie name, expiry (28800s)
│   │   ├── Database.php        ← DB connection config (reads from .env)
│   │   ├── Security.php        ← CSRF config
│   │   ├── Filters.php         ← Global filter registration
│   │   └── ... (40+ CI4 built-in config files)
│   ├── Controllers/
│   │   ├── BaseController.php  ← Loads session, initialises $this->db
│   │   ├── app.php             ← Main controller: all project/flow/ticket/API endpoints
│   │   └── user.php            ← Auth, users, roles, preferences
│   ├── Database/
│   │   └── Migrations/
│   │       ├── 2026-06-03-000001_AddPerformanceIndexes.php
│   │       ├── 2026-06-04-000001_TicketLifecycleFeatures.php
│   │       ├── 2026-06-04-000002_AddCronRuns.php
│   │       └── 2026-06-26-000001_FixCiSessionsTimestamp.php ← Configures database-driven sessions
│   ├── Helpers/
│   │   ├── app_helper.php      ← Consolidated global helper: settings, auth, email, activity log, badges, and flow builders
│   │   └── security_helper.php ← Login rate-limiting, file upload security
│   ├── Models/
│   │   ├── app_model.php       ← All data operations except users/roles
│   │   └── user_model.php      ← User CRUD, authentication, role management
│   └── Views/
│       ├── templates/          ← Shared layout: header, sidebar, footer, auth_header
│       ├── filters/            ← filter_bar_header.php, date_range_widget.php
│       ├── me/                 ← dashboard.php (preferences), notifications.php
│       ├── dashboard.php
│       ├── tickets.php         ← Handles list, create, and detail views by $data['view']
│       ├── flows.php           ← Handles list, form, and states views
│       ├── alerts.php          ← Alert defs, escalation matrix, API keys
│       ├── users.php
│       ├── roles.php
│       ├── projects.php
│       ├── settings.php
│       ├── activity_logs.php
│       ├── module_control_panel.php
│       ├── cron_panel.php
│       ├── maintenance.php
│       ├── login.php
│       └── password_change.php
├── public/
│   ├── index.php               ← CI4 front controller
│   ├── .htaccess               ← URL rewriting
│   └── assets/
│   │       ├── css/app.css         ← Custom styles (~4600 lines)
│   │       ├── js/app.js           ← Core JS (~3946 lines after split)
│   │       ├── js/datatable.js     ← DataTable logic (~1496 lines)
│   │       ├── js/calendar.js      ← Premium global date range picker logic
│   │       └── vendor/             ← Bundled libraries (no CDN dependency)
│   └── ...
├── scripts/
│   ├── schema.sql              ← Full DB schema (22 tables)
│   ├── setup_defaults.php      ← Seeds roles, modules, permissions, 40+ settings
│   ├── seed_demo_data.php      ← Demo data for evaluation
│   └── backup.sh               ← Daily backup utility
├── writable/
│   ├── cache/                  ← app_settings.cache (5-min TTL)
│   ├── logs/                   ← CI4 application logs
│   └── uploads/tickets/        ← Ticket attachments organised by alarm_id
├── .env                        ← Environment variables (not committed)
├── .env.example                ← Template
├── composer.json               ← PHP dependencies
├── spark                       ← CI4 CLI
└── tat_monitor.php             ← Cron escalation engine
```

### 2.2 MVC Flow

Every HTTP request goes through the same sequence:

```
Browser Request
     │
     ▼
public/index.php                  ← CI4 bootstrap
     │
     ▼
CodeIgniter Router                 ← Matches URL against Routes.php
     │
     ▼
Filter Pipeline                    ← CSRF check (CI4 global filters)
     │
     ▼
Controller::method()               ← e.g., App::ticket_save()
     │
     ├── check_isvalidated()       ← Session guard (see §4.4)
     ├── check_module_access()     ← Permission guard (see §4.5)
     │
     ├── $this->app_model->...     ← Database queries
     ├── activity_log(...)         ← Audit trail write
     │
     ├── redirect()->to(url)       ← For POST→redirect flows
     │   or
     └── json_ok() / json_fail()   ← For AJAX endpoints
         or
         echo view('header', $data) ← For page renders
         echo view('sidebar', $data)
         echo view('page', $data)
         echo view('footer')
```

### 2.3 Controllers

#### `app/Controllers/app.php`

The main controller handling all non-auth endpoints. Approximately 4,300 lines and 60+ public methods.

**Key private helpers:**
- `ticketLoad($alarm_id, $isAjax)` — validates alarm ID format, loads ticket, checks access. Returns ticket array or redirect/JSON response
- `ticketIsTerminal($ticket)` — returns true when status is `resolved` or `closed`
- `processTicketAttachment($ticket_id, $alarmId, $file)` — full upload validation pipeline, returns null on success or error string
- `apiAuthenticate()` — validates `X-API-KEY` header, populates `$this->api_key_row`
- `apiDeny()` — returns HTTP 401 JSON
- `apiRateLimit()` — checks rate limits, returns HTTP 429 or null

**Method groups:**

| Group | Methods |
|---|---|
| Dashboard | `dashboard()`, `dashboard_trend()` |
| Projects | `projects()`, `projects_data_table()`, `project_add()`, `project_save()`, `project_edit()`, `project_update()`, `project_delete()` |
| Flows | `flows()`, `flows_data_table()`, `flow_add()`, `flow_save()`, `flow_edit()`, `flow_update()`, `flow_delete()`, `flow_states()` |
| States | `state_save()`, `state_delete()`, `state_reorder()`, `state_transitions()`, `state_transition_save()`, `state_transition_delete()` |
| Alerts | `alerts()`, `alerts_data_table()`, `alert_add()`, `alert_save()`, `alert_edit()`, `alert_update()`, `alert_delete()` |
| Escalation | `escalation()`, `escalation_save()`, `escalation_delete()`, `escalation_states_by_flow()` |
| API Keys | `api_keys()`, `api_key_generate()`, `api_key_toggle()` |
| Notifications (UI) | `actionable_count()`, `notifications_recent()` |
| Settings | `settings()`, `settings_save()`, `settings_send_test_email()`, `settings_bump_asset_version()` |
| Module Control Panel | `module_control_panel()`, `module_control_panel_save()`, `module_add()`, `module_delete()` |
| Cron Panel | `cron_panel()` |
| Tickets (UI) | `tickets_my()`, `tickets_all()`, `ticket_data_table()`, `ticket_create()`, `ticket_save()`, `ticket_detail()`, `ticket_action()`, `ticket_move_state()`, `ticket_attach()`, `ticket_download()`, `ticket_assign()`, `ticket_resolve()`, `ticket_close()`, `ticket_reopen()`, `ticket_flows_by_project()`, `ticket_assignable_users()`, `tickets_export()`, `tickets_bulk()`, `tickets_saved_save()`, `tickets_saved_delete()` |
| Activity Logs | `activity_logs()`, `activity_logs_data_table()`, `activity_logs_export()`, `activity_logs_analytics()`, `activity_logs_user_events()` |
| REST API | `api_raise()`, `api_show()`, `api_update()`, `api_index()`, `api_flows()` |

#### `app/Controllers/user.php`

Handles auth and user management. 27 public methods.

**Method groups:**

| Group | Methods |
|---|---|
| Auth | `login()`, `do_login()`, `logout()`, `maintenance()`, `maintenance_disable()` |
| Password | `password_change()`, `password_change_save()` |
| Users | `index()`, `data_table()`, `add()`, `save()`, `edit()`, `update()`, `delete()`, `check_user_id()`, `active_json()`, `update_theme()` |
| Preferences | `me_dashboard()`, `me_dashboard_save()`, `me_notifications()`, `me_notifications_save()` |
| Roles | `roles()`, `role_add()`, `role_save()`, `role_edit()`, `role_update()`, `role_delete()` |

### 2.4 Models

#### `app/Models/app_model.php`

Handles all data operations for projects, flows, states, tickets, escalation, API keys, settings, and activity logs. No ORM — uses CI4's Query Builder directly.

**Key method categories:**

| Category | Methods |
|---|---|
| Projects | `projectGetAll()`, `projectGetById()`, `projectGetActive()`, `projectSave()`, `projectUpdate()`, `projectSoftDelete()`, `projectCountActive()`, `projectNameExists()` |
| Flows | `flowGetAll()`, `flowGetById()`, `flowGetActive()`, `flowGetByProject()`, `flowSave()`, `flowUpdate()`, `flowSoftDelete()`, `flowCountActive()`, `flowNameExists()` |
| States | `stateGetAll()`, `stateGetById()`, `stateGetInitial()`, `stateSave()`, `stateDelete()`, `stateReorder()`, `stateLevelUsers()`, `stateClearOtherInitial()`, `stateClearOtherFinal()`, `stateGetChildren()`, `stateGetDescendantIds()` |
| Transitions | `stateGetTransitions()`, `stateGetAllTransitions()`, `stateTransitionSave()`, `stateTransitionDelete()`, `stateDeleteFromTransitions()`, `stateTransitionDeleteForState()` |
| Tickets | `ticketGetByAlarm()`, `ticketSave()`, `ticketUpdate()`, `ticketMoveToState()`, `ticketEscalateLevel()`, `ticketActiveForTatCheck()`, `ticketRecent()`, `ticketGetAll()`, `ticketListForDataTables()`, `ticketCountAll()`, `ticketCountFiltered()`, `ticketCountByStatus()`, `ticketCountByAlertTypeActive()`, `ticketCountTatBreached()`, `ticketCountActionable()`, `ticketTrendByRange()` |
| Ticket Actions | `ticketLogAction()`, `ticketTimeline()`, `ticketAttachmentCount()`, `ticketGetAttachment()`, `ticketRecentNotifications()` |
| Alerts | `alertGetAll()`, `alertGetById()`, `alertSave()`, `alertUpdate()`, `alertDeactivate()` |
| Escalation | `escalationGetAll()`, `escalationSave()`, `escalationDelete()`, `escalationRule()` |
| API Keys | `apiKeyGetAll()`, `apiKeyGetById()`, `apiKeyGetByKey()`, `apiKeyGenerate()`, `apiKeyToggle()`, `apiKeyTouchLastUsed()` |
| Settings | `settingGetAll()`, `settingSet()`, `settingsTableExists()` |
| Module Permissions | `modulePermissionsGetAll()`, `modulePermissionsSave()`, `modulePermissionsTableExists()` |
| Saved Filters | `savedFilterList()`, `savedFilterSave()`, `savedFilterDelete()` |
| Activity (Actionable) | `ticketCountActionable()`, `actionableTicketsForUser()` |

**Private internal caches (per request, static):**
- `userNameMap()` — `user_id → name` lookup map
- `projectNameMap()` — `id → name` lookup map
- `flowNameMap()` — `id → name` lookup map
- `stateNameMap()` — `id → name` lookup map
- `flowStateCounts()` — `flow_id → state count` map

These eliminate repeated queries when building list responses.

#### `app/Models/user_model.php`

Handles users, roles, and authentication.

**Key methods:**

| Method | Purpose |
|---|---|
| `getAll()` | All non-deleted users |
| `getById($id)` | Single user by PK |
| `getByUserId($user_id)` | Single user by login handle |
| `getByIds($ids)` | Multiple users by user_id strings |
| `getActive()` | Active users for assignment dropdowns |
| `getPoolUsers($callerUserId)` | Users in any active state pool (for @mention) |
| `userIdExists($user_id, $ignoreId)` | Uniqueness check |
| `emailExists($email, $ignoreId)` | Uniqueness check |
| `countActiveSuperAdmins($ignoreId)` | Guards against last-admin deletion |
| `save($data)` | Insert new user (hashes password, sets created_at) |
| `update($id, $data)` | Update user; if deactivated, unassigns active tickets |
| `softDelete($id)` | Soft delete; unassigns active tickets |
| `checkLogin($login, $password)` | Verifies credentials against bcrypt hash |
| `setSession($user)` | Writes all session keys post-login; regenerates session ID |
| `logout()` | Destroys session, clears settings cache |
| `usersForDT($args)` | Server-side DataTable query for user list |
| `getAllRoles()` | All roles ordered by sort_order |
| `getRoleByKey($role_key)` | Single role |
| `roleKeyExists($role_key)` | Check existence |
| `countUsersWithRole($role_key)` | Guard for role deletion |
| `saveRole($data)` | Insert new role |
| `updateRoleLabel($role_key, $label)` | Rename display label |
| `updateRoleAdminScope($role_key, $isAdminScope)` | Toggle admin scope |
| `deleteRole($role_key)` | Hard delete (also removes module_permissions rows) |
| `seedDefaultPermissions($role_key)` | Inserts all-zero permission rows for a new role |

### 2.5 Helpers

Helpers are loaded globally via CI4's autoloader config. They are available in every controller, model, and view without explicit loading. To improve maintainability and performance, the previously separate helpers (`alert_helper.php`, `flow_helper.php`, and `csv_helper.php`) have been consolidated into a single centralized [app_helper.php](file:///c:/xampp8/htdocs/pview_alerts/app/Helpers/app_helper.php).

#### `app_helper.php` (consolidated utility file)

**Settings functions:**
- `app_settings_all()` — loads all settings from DB, caches in file (`writable/cache/app_settings.cache`, 5-min TTL) and static variable
- `app_settings_clear_cache()` — deletes cache file; called after any setting save
- `app_setting($key, $default)` — reads one setting
- `app_setting_int($key, $default)` — casts to int
- `app_setting_bool($key, $default)` — '1' → true, else false
- `app_setting_csv($key, $default)` — splits comma-separated string into lowercase array

**Auth & Access helpers:**
- `check_isvalidated()` — verifies session has user_id; handles maintenance mode bounce and idle session timeout; enforces password rotation redirect
- `check_issuperadmin()` — redirects non-super_admin to dashboard
- `has_module_access($module_key, $action)` — reads `module_permissions` table; super_admin always true; settings always requires super_admin; caches all permissions in static variable for the request lifetime
- `check_module_access($module_key, $action)` — calls `check_isvalidated()` then `has_module_access()`; redirects to first accessible module on denial
- `assignable_role_keys($actor_role)` — returns roles the current actor may assign; super_admin → all, admin-scope → all except super_admin, user → non-admin-scope only; per-request cached
- `role_has_admin_scope($role)` — reads `roles.is_admin_scope`; super_admin always true; per-request cached; fallback: `$role === 'admin'` for legacy installs
- `verify_ticket_access($ticket)` — admin-scope passes; others checked against raised_by, current_assignee, state level pools (L1–L4 JSON arrays), and ticket_actions history
- `logged_user_id()`, `logged_user_name()`, `logged_user_role()` — session readers
- `user_dashboard_pref($key, $default)` — reads from session's `dashboard_layout` JSON (loaded once at login)
- `get_first_accessible_module()` / `first_accessible_module_url()` — walks sidebar order to find first accessible page; used for post-login landing and access-denied fallback

**Global Date Filter helpers:**
- `get_global_date_range()` — parses the global date range from session or request (start and end dates)
- `get_global_date_range_label()` — returns a human-readable label for the active date range (e.g., "Last 7 Days")

**ID generation:**
- `generate_alarm_id()` — uses MySQL `LAST_INSERT_ID()` on `alarm_id_sequence` table to generate atomic daily sequences; format: `ALM-YYYYMMDD-NNNNN`
- `safe_alarm_id($alarm_id)` — regex validates `ALM-\d{8}-\d{5}` format; blocks path traversal

**Email builders & delivery:**
- `mail_subject($event, $context)` — builds subject line per event type
- `mail_chip_span($text, $bg, $fg)` — inline-styled badge for email
- `mail_kv_row($label, $value)` — table row for email body
- `mail_html_body($event, $context)` — full HTML email template with inline styles; handles 8 event types
- `send_email($to, $subject, $body)` — PHPMailer wrapper; reads SMTP config from app_settings (DB-first) with fallback to Config\Email; never called directly from controllers — always through notification queue
- `parse_mentions($text, $excludeUserId)` — extracts `@user_id` tokens, validates against `users` table, deduplicates, excludes author
- `highlight_mentions($text)` — HTML-escapes text then wraps @mentions in styled span
- `user_notify_allowed($user_id, $project_id, $severity)` — checks `user_notification_settings` table with fallback to allow; per-request static cache
- `notify_users($user_ids, $ticket_id, $subject, $body)` — inserts rows into `notification_logs` with `status=pending`; prunes logs older than 90 days
- `process_notification_queue($batch, $maxAttempts)` — drains pending rows via `send_email()`; tracks retry count via `/N` suffix in error_message; returns sent/failed/retried counts
- `notify_ticket_event($event, $ticket, $extraContext, $userIds)` — builds context, calls mail_subject + mail_html_body + notify_users in one call

**TAT helpers:**
- `tat_expires_at($ticket, $state)` — computes ISO 8601 expiry timestamp from `state_entered_at` + level TAT; returns '' for terminal tickets
- `tat_minutes_for_level($state, $level)` — reads `l{N}_tat_minutes` from state row
- `tat_total_minutes($ticket, $state)` — total TAT window for JS countdown warning

**Badge renderers (HTML output):**
- `alert_badge($type)` — renders severity badge (`<span class="badge ...">`)
- `status_badge($status)` — renders status badge
- `priority_badge($priority)` — renders priority badge
- `level_badge($level)` — renders `L{N}` badge

**Validation:**
- `validate_password($password)` — checks min length, letter requirement, digit requirement against app_settings
- `validate_user_id($user_id)` — validates 3–64 chars, `[A-Za-z0-9._-]` pattern
- `password_must_rotate($password_changed_at, $maxDays)` — returns true if age exceeds max days

**AJAX & CSV response helpers:**
- `json_ok($data, $message)` — sets `success: true`, `data`, `message` as JSON response
- `json_fail($message, $code)` — sets `success: false`, `data: []`, `message`, HTTP status code
- `dt_parse_request($request, $colMap)` — parses DataTables server-side request parameters (draw, start, length, search, order column/dir)
- `dt_json_response($draw, $total, $filtered, $data)` — builds DataTables-compatible JSON response
- `export_csv_helper($filename, $headers, $rows)` — memory-efficient, streaming CSV export utility with UTF-8 BOM

**Audit:**
- `activity_log($module, $action, $entity_type, $entity_id, $summary, $meta, $overrides)` — inserts one row to `activity_logs`; captures user from session or overrides (for pre-auth events); captures IP, UA, URL, method, browser; failures are swallowed (non-fatal)
- `activity_diff($before, $after, $fields)` — returns `{field: [old, new]}` map for update events

**Module registry:**
- `module_registry()` — reads all module rows from `modules` table; per-request cached

**Workflow Diagram (vis-network) builders:**
- `flow_ticket_ancestor_ids($states, $currentStateId, $transitions)` — BFS upstream from current state via reverse-forward edges; fallback to parent_state_id tree for legacy flows
- `flow_vis_edges($states, $transitions)` — builds edge list; priority: explicit forward transitions → parent tree → sequential sort_order
- `flow_vis_designer_data($states, $transitions)` — nodes + edges for the flow designer preview
- `flow_vis_ticket_data($states, $currentStateId, $transitions)` — nodes with `passed|current|pending` status + edges for ticket detail diagram
- `flow_widget_html($visData, $opts)` — renders complete HTML widget chrome (toolbar + vis canvas + legend); embeds JSON data in `<script type="application/json">`

#### `security_helper.php`

- `client_ip()` — returns `$_SERVER['REMOTE_ADDR']`; does not trust `X-Forwarded-For`; truncates to 45 chars
- `security_table_exists($table)` — checks if table exists before security operations
- `login_is_locked($ip, $login)` — per-user lockout based on `login_attempts` table; counts failures within sliding window; returns earliest failure timestamp to compute remaining lockout
- `login_attempt_record($ip, $login, $success)` — inserts row; prunes rows older than 7 days
- `login_attempts_clear($ip, $login)` — deletes only this user's failure rows (not other users from same IP)
- `api_rate_check($apiKeyId, $endpoint)` — per-minute and per-hour limits from app_settings; records request in `api_request_log`; pruning moved to cron job (PERF-01)
- `upload_blocked_extensions()` — hard-coded denylist (php, phar, sh, exe, js, html, asp, jsp, etc.) merged with `upload_blocked_ext` setting
- `upload_filename_is_safe($originalName)` — checks all dot segments (catches `evil.php.jpg`); validates null bytes and control chars
- `upload_sniff_mime($absPath)` — uses PHP `finfo` to read magic bytes
- `upload_mime_matches_ext($ext, $sniffedMime)` — validates sniffed MIME against ext map; unknown ext returns true (allow-list gates it)

---

## 3. Database Documentation

### 3.1 Schema Overview

```
users ─────────────────────────── roles
  │                                 │
  │ created_by                      │ role_key FK in users.role
  ▼                                 ▼
projects ──── flows ──── states ─── state_transitions
                │          │
                │          └─────── escalation_matrix
                │
                └─────────────────── alert_definitions ─── api_keys
                                              │
tickets ──── ticket_actions                  │
  │    │                              (referenced at creation)
  │    └─── notification_logs
  │
  ├─── alarm_id_sequence (daily counter)
  │
saved_filters ── (user_id → users)
activity_logs
login_attempts
api_request_log
user_notification_settings
module_permissions ── (role → roles.role_key)
modules
app_settings
cron_runs
```

### 3.2 Complete Table Descriptions

#### `users`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | Numeric primary key |
| `user_id` | VARCHAR(64) UNIQUE | Human-readable login handle (e.g., `jdoe`) |
| `name` | VARCHAR(160) | Display name |
| `email` | VARCHAR(255) UNIQUE | Notification email (not used for login) |
| `phone` | VARCHAR(30) | Optional, reference only |
| `password` | VARCHAR(255) | bcrypt hash via `password_hash()` |
| `password_changed_at` | DATETIME | Updated whenever password changes |
| `role` | VARCHAR(50) | FK to `roles.role_key` |
| `theme` | VARCHAR(10) | `dark` or `light` |
| `dashboard_layout` | JSON | Stored KPI visibility, default project, default trend range |
| `is_active` | TINYINT(1) | 0 = locked out |
| `created_at` | DATETIME | |
| `deleted_at` | DATETIME NULL | Soft delete; non-null = deleted |

**Business rules:** When `is_active` is set to 0 or deleted_at is set, all open/in_progress/escalated tickets assigned to this user have `current_assignee` cleared to NULL automatically.

#### `roles`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `role_key` | VARCHAR(50) PK-like UNIQUE | Immutable identifier (e.g., `super_admin`) |
| `label` | VARCHAR(100) | Display name |
| `is_builtin` | TINYINT(1) | 1 = cannot be deleted |
| `is_admin_scope` | TINYINT(1) | 1 = sees all tickets globally |
| `sort_order` | INT | Display sort order |
| `created_at` | DATETIME | |

**Built-in row:** Only `super_admin` is seeded by `setup_defaults.php`. The `admin` and `user` role keys are referenced in code logic (e.g., legacy fallback in `role_has_admin_scope()`) but are not pre-created — they must be added via `/roles` after first login.

#### `module_permissions`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `role` | VARCHAR(50) | FK to `roles.role_key` |
| `module_key` | VARCHAR(50) | FK to `modules.module_key` |
| `can_view` | TINYINT(1) | |
| `can_add` | TINYINT(1) | |
| `can_edit` | TINYINT(1) | |
| `can_delete` | TINYINT(1) | |

UNIQUE constraint on `(role, module_key)`. The `settings` module is hardcoded to super_admin-only in `has_module_access()` — no DB row controls it.

#### `modules`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `module_key` | VARCHAR(50) UNIQUE | Used in `check_module_access()` |
| `name` | VARCHAR(100) | Display name |
| `description` | VARCHAR(255) | |
| `is_builtin` | TINYINT(1) | Built-in modules cannot be deleted |
| `sort_order` | INT | Order in permission grid |
| `created_at` | DATETIME | |
| `created_by` | VARCHAR(100) NULL | |

#### `projects`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `name` | VARCHAR(200) UNIQUE | Project name |
| `description` | TEXT NULL | |
| `status` | ENUM('active','inactive') | |
| `created_by` | VARCHAR(100) | `users.user_id` string |
| `created_at` | DATETIME | |
| `deleted_at` | DATETIME NULL | Soft delete |

Soft delete cascades: flows set to `inactive`, alert definitions and API keys set to `is_active = 0`.

#### `flows`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `project_id` | INT UNSIGNED | FK to `projects.id` |
| `name` | VARCHAR(200) | Unique within project |
| `status` | ENUM('active','inactive') | |
| `tat_level_count` | TINYINT | 1–4; controls how many L{N} levels the cron monitors |
| `created_by` | VARCHAR(100) | |
| `created_at` | DATETIME | |
| `deleted_at` | DATETIME NULL | Soft delete |

Soft delete cascades: alert definitions deactivated.

#### `states`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `flow_id` | INT UNSIGNED | FK to `flows.id` |
| `name` | VARCHAR(200) | State label |
| `is_initial` | TINYINT(1) | One per flow; enforced by `stateClearOtherInitial()` |
| `is_final` | TINYINT(1) | One per flow; enforced by `stateClearOtherFinal()` |
| `parent_state_id` | INT UNSIGNED NULL | Legacy branching (parent-child tree) |
| `sort_order` | INT | Default flow order |
| `l1_user_ids` | JSON | Array of `users.user_id` strings for L1 pool |
| `l1_tat_minutes` | INT | L1 TAT threshold (default: 60) |
| `l2_user_ids` | JSON | L2 pool |
| `l2_tat_minutes` | INT | |
| `l3_user_ids` | JSON | L3 pool |
| `l3_tat_minutes` | INT | |
| `l4_user_ids` | JSON | L4 pool |
| `l4_tat_minutes` | INT | |
| `status` | ENUM('active','inactive') | |
| `created_by` | VARCHAR(100) | |
| `created_at` | DATETIME | |

**JSON arrays:** Stored as standard JSON e.g. `["alice","bob"]`. Read via `stateLevelUsers($state, $level)` which decodes and returns `string[]`.

#### `state_transitions`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `flow_id` | INT UNSIGNED | |
| `from_state_id` | INT UNSIGNED | |
| `to_state_id` | INT UNSIGNED | |
| `transition_type` | ENUM('forward','backward','rework') | |
| `requires_comment` | TINYINT(1) | 1 = operator must type a reason |
| `sort_order` | INT | |
| `created_by` | VARCHAR(100) | |
| `created_at` | DATETIME | |

UNIQUE on `(flow_id, from_state_id, to_state_id, transition_type)`.

**Usage:** Forward transitions define explicit branching paths. Backward transitions define send-back paths. When a ticket is moved backward for the first time, a new backward row is auto-inserted so the diagram reflects real paths taken.

#### `tickets`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `alarm_id` | VARCHAR(20) UNIQUE | `ALM-YYYYMMDD-NNNNN` |
| `project_id` | INT UNSIGNED | FK to projects |
| `flow_id` | INT UNSIGNED | FK to flows |
| `alert_def_id` | INT UNSIGNED NULL | FK to alert_definitions (set by API) |
| `title` | VARCHAR(300) | |
| `description` | TEXT NULL | |
| `alert_type` | ENUM('info','major','critical') | Severity |
| `priority` | ENUM('low','medium','high','urgent') | |
| `current_state_id` | INT UNSIGNED | Active state |
| `current_level` | TINYINT | 1–4 escalation level |
| `current_assignee` | VARCHAR(64) NULL | `users.user_id` |
| `status` | ENUM('open','in_progress','escalated','resolved','closed') | |
| `state_entered_at` | DATETIME | When ticket entered current state; resets on state move |
| `last_tat_warn_level` | TINYINT | Last level that received 80% TAT warning; prevents duplicate warns |
| `source` | VARCHAR(20) | `ui` or `api` |
| `source_system` | VARCHAR(100) NULL | Name of external system (API-raised tickets) |
| `raised_by` | VARCHAR(64) | `users.user_id` of creator |
| `actual_start_date` | DATE NULL | Informational; auto-set on first assignment if blank |
| `actual_end_date` | DATE NULL | Informational; auto-set on resolve/close if blank |
| `resolved_at` | DATETIME NULL | |
| `closed_at` | DATETIME NULL | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME NULL | |

**Terminal status:** When `status` is `resolved` or `closed`, all write endpoints reject mutations. This is enforced by `ticketIsTerminal()` in every action method.

#### `ticket_actions`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `ticket_id` | INT UNSIGNED | FK to tickets |
| `action_type` | VARCHAR(40) | See action type list below |
| `performed_by` | VARCHAR(64) NULL | `users.user_id` or system identifier |
| `performed_by_system` | VARCHAR(100) NULL | External system name for API actions |
| `comment` | TEXT NULL | Free text or structured note |
| `from_state_id` | INT UNSIGNED NULL | State before move |
| `to_state_id` | INT UNSIGNED NULL | State after move |
| `to_level` | TINYINT NULL | Level after escalation |
| `from_level` | TINYINT NULL | Level before escalation |
| `transition_type` | VARCHAR(20) NULL | `forward` or `backward` |
| `attachment_path` | VARCHAR(512) NULL | Relative path to uploaded file |
| `created_at` | DATETIME | |

**Action types:** `created`, `commented`, `state_changed`, `level_escalated`, `assigned`, `unassigned`, `attachment`, `resolved`, `closed`, `reopened`, `title_changed`, `description_changed`, `priority_changed`, `api_update`

#### `alert_definitions`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `project_id` | INT UNSIGNED | |
| `flow_id` | INT UNSIGNED | |
| `name` | VARCHAR(200) | |
| `description` | TEXT NULL | |
| `alert_type` | ENUM('info','major','critical') | |
| `threshold_value` | VARCHAR(50) NULL | e.g., '90' |
| `threshold_unit` | VARCHAR(50) NULL | e.g., '%' |
| `notify_user_ids` | JSON NULL | Default notify list |
| `is_active` | TINYINT(1) | |
| `created_by` | VARCHAR(64) | |
| `created_at` | DATETIME | |

#### `escalation_matrix`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `flow_id` | INT UNSIGNED | |
| `state_id` | INT UNSIGNED | |
| `level` | TINYINT | 1–4 |
| `escalate_after` | INT | TAT override in minutes |
| `notify_user_ids` | JSON | Notify list override |
| `alert_type` | ENUM('info','major','critical') | Severity flag for escalation notification |
| `created_by` | VARCHAR(64) | |
| `created_at` | DATETIME | |

**Precedence:** In `tat_monitor.php`, `escalationRule($flow_id, $state_id, $level)` is checked first. If a row exists, its `escalate_after` and `notify_user_ids` override the state's built-in values.

#### `api_keys`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `project_id` | INT UNSIGNED | Scopes the key to one project |
| `name` | VARCHAR(100) | Display name |
| `api_key` | VARCHAR(64) UNIQUE | `bin2hex(random_bytes(24))` — 48-char hex string |
| `is_active` | TINYINT(1) | |
| `last_used` | DATETIME NULL | Updated on every authenticated request |
| `created_by` | VARCHAR(64) | |
| `created_at` | DATETIME | |

**Security note:** The raw key is shown once in the UI immediately after generation. It is stored as plain text in the DB (not hashed) because it must be compared directly. Access to the `api_keys` table must be strictly controlled at the DB level.

#### `api_request_log`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `api_key_id` | INT UNSIGNED | |
| `endpoint` | VARCHAR(100) | |
| `requested_at` | DATETIME | |

Pruned daily by `tat_monitor.php` (rows older than 24 hours deleted). Used for rate-limiting calculations in `api_rate_check()`.

#### `notification_logs`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `ticket_id` | INT UNSIGNED NULL | |
| `channel` | VARCHAR(20) | `email` |
| `recipient_email` | VARCHAR(255) | |
| `subject` | VARCHAR(500) | |
| `body` | LONGTEXT | Full HTML email body |
| `status` | ENUM('pending','sent','failed') | |
| `error_message` | TEXT NULL | Retry counter stored as `transient send failure /N` |
| `sent_at` | DATETIME NULL | |
| `created_at` | DATETIME | |

Rows older than 90 days are pruned by `notify_users()` on each call.

#### `user_notification_settings`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `user_id` | VARCHAR(64) | `users.user_id` |
| `project_id` | INT UNSIGNED | 0 = catch-all "all projects" row |
| `severity` | VARCHAR(20) | `info`, `major`, `critical` |
| `is_enabled` | TINYINT(1) | 1 = receive emails |
| `updated_at` | DATETIME | |

UNIQUE on `(user_id, project_id, severity)`. Lookup in `user_notify_allowed()`: exact match → all-projects match → allow (lenient default).

#### `saved_filters`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `user_id` | VARCHAR(64) | |
| `name` | VARCHAR(100) | |
| `query_params` | VARCHAR(1000) | URL query string |
| `scope` | VARCHAR(40) | `tickets` |
| `created_at` | DATETIME | |

#### `activity_logs`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `user_id` | VARCHAR(64) NULL | |
| `user_name` | VARCHAR(160) NULL | |
| `user_role` | VARCHAR(50) NULL | |
| `module` | VARCHAR(40) | |
| `action` | VARCHAR(40) | |
| `entity_type` | VARCHAR(40) NULL | |
| `entity_id` | VARCHAR(64) NULL | |
| `summary` | VARCHAR(255) | Human-readable description |
| `meta` | JSON NULL | Structured diff or additional data |
| `ip_address` | VARCHAR(45) NULL | IPv4 or IPv6 |
| `user_agent` | VARCHAR(255) NULL | |
| `browser` | VARCHAR(40) NULL | Parsed browser name |
| `project_id` | INT UNSIGNED NULL | For project-specific filtering |
| `url` | VARCHAR(255) NULL | |
| `method` | VARCHAR(10) NULL | GET/POST |
| `status` | VARCHAR(10) NULL | `success` or `fail` |
| `created_at` | DATETIME | |

Append-only. No UPDATE or DELETE path exists in the application code. Exported via `activity_logs/export` with UTF-8 BOM for Excel compatibility.

#### `login_attempts`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `ip` | VARCHAR(45) | |
| `login_identifier` | VARCHAR(150) | The user_id or email that was tried |
| `success` | TINYINT(1) | |
| `attempted_at` | DATETIME | |

Pruned to 7 days by `login_attempt_record()` on every call. Lockout is **per-user** (not per-IP) — determined by counting `login_identifier` failures within the sliding window.

#### `alarm_id_sequence`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `day_key` | VARCHAR(8) UNIQUE | `YYYYMMDD` |
| `last_seq` | INT UNSIGNED | Current daily counter |

**Generation query:**
```sql
INSERT INTO alarm_id_sequence (day_key, last_seq)
VALUES (?, LAST_INSERT_ID(1))
ON DUPLICATE KEY UPDATE last_seq = LAST_INSERT_ID(last_seq + 1);
SELECT LAST_INSERT_ID() AS n;
```
`LAST_INSERT_ID()` is connection-scoped — concurrent inserts get unique numbers without explicit locking.

#### `app_settings`

| Column | Type | Notes |
|---|---|---|
| `setting_key` | VARCHAR(100) PK | e.g., `password_min_length` |
| `setting_value` | TEXT | All values stored as strings |
| `updated_at` | DATETIME NULL | |
| `updated_by` | VARCHAR(64) NULL | |

Two-layer cache: static PHP variable (per-request) and file cache (`writable/cache/app_settings.cache`, 5-minute TTL). `settingSet()` uses INSERT ... ON DUPLICATE KEY UPDATE for upsert behaviour.

#### `ci_sessions`

| Column | Type | Notes |
|---|---|
| `id` | VARCHAR(128) PK | Session identifier |
| `ip_address` | VARCHAR(45) | Client IP address |
| `timestamp` | TIMESTAMP | Last active timestamp (default: current_timestamp() on update) |
| `data` | BLOB | Serialized session data |

This table supports database-driven sessions, replacing the previous file-based session handler. It is configured in `app/Config/Session.php` using the `DatabaseHandler`.

#### `cron_runs`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `script` | VARCHAR(100) | e.g., `tat_monitor` |
| `started_at` | DATETIME | |
| `finished_at` | DATETIME | |
| `duration_ms` | INT | Milliseconds |
| `status` | VARCHAR(20) | `ok` or `failed` |
| `tickets_checked` | INT | |
| `notifs_sent` | INT | |
| `notifs_failed` | INT | |
| `output_summary` | TEXT NULL | |

Retains last 99 rows per script (oldest rows pruned after each insert).

---

## 4. User Management

### 4.1 User Types

There are two fundamental scopes of access:

- **Admin-scope** (`is_admin_scope = 1`): User sees all tickets across all projects
- **Non-admin-scope** (`is_admin_scope = 0`): User sees only tickets they raised, are assigned to, or are in a state's user pool

The admin-scope flag is on the `roles` table, not on the user. Changing a role's scope immediately affects all users in that role.

### 4.2 Built-in Roles

`setup_defaults.php` seeds only one role:

| Role key | Admin scope | Seeded | Notes |
|---|---|---|---|
| `super_admin` | Always true | Yes | Full access; always overrides permission checks |
| `admin` | Yes (default) | No | Must be created manually; module access configurable |
| `user` | No | No | Must be created manually; module access configurable |

`admin` and `user` are documented here because the application code references them in legacy fallback logic (`role_has_admin_scope()` defaults `admin` → true on installs without a DB row). In practice they are optional — operators can use any custom role key.

### 4.3 Custom Roles

Created via `/roles/add`. Constraints:
- `role_key` must match `/^[a-z][a-z0-9_]{1,49}$/`
- `role_key` is immutable after creation (renaming would orphan `module_permissions` rows)
- Deletion blocked if any users are assigned to it
- Deletion removes `module_permissions` rows for that role

When a custom role is created, `seedDefaultPermissions($role_key)` inserts all-zero permission rows for every module currently in the `modules` table. The admin then configures permissions in the Module Control Panel.

### 4.4 Authentication Flow

```
POST /login
    │
    ▼
User::do_login()
    │
    ├── Accept `login` (primary) or `email` field (legacy compatibility)
    ├── Validate not empty
    │
    ├── login_is_locked($ip, $login)
    │   └── Count failures for login_identifier within lockout window
    │       If locked → show lockout message (same regardless of password correctness)
    │
    ├── user_model::checkLogin($login, $password)
    │   ├── Branches: contains '@' → WHERE email; else → WHERE user_id
    │   ├── Must be is_active=1, deleted_at IS NULL
    │   └── password_verify($password, $row['password'])
    │
    ├── On failure:
    │   ├── login_attempt_record($ip, $login, false)
    │   ├── activity_log('auth', 'login_failed', ...)
    │   └── Redirect to /login with error
    │
    └── On success:
        ├── login_attempt_record($ip, $login, true)
        ├── login_attempts_clear($ip, $login)  ← clears only this user's failures
        ├── user_model::setSession($user)
        │   ├── Check password_must_rotate() → set session['password_must_rotate']
        │   ├── Decode dashboard_layout JSON into session
        │   ├── session_unset() ← wipes anonymous state
        │   ├── session->set([...]) ← writes all session keys
        │   └── session->regenerate(true) ← session fixation prevention
        ├── activity_log('auth', 'login', ...)
        └── redirect()->to(first_accessible_module_url())
```

**Session keys set at login:**

| Key | Value |
|---|---|
| `user_pk` | `users.id` (integer) |
| `user_id` | `users.user_id` (string, e.g. `jdoe`) |
| `user_name` | Display name |
| `user_email` | Email address |
| `user_role` | Role key |
| `theme` | `dark` or `light` |
| `dashboard_layout` | Decoded JSON array |
| `logged_in` | `true` |
| `password_must_rotate` | `true/false` |
| `last_activity` | Unix timestamp (updated per request by `check_isvalidated()`) |

### 4.5 Authorization Flow

Every controller method that requires authentication calls one or both guards:

```php
check_isvalidated();           // Session guard
check_module_access('tickets', 'edit');  // Permission guard
```

**`check_isvalidated()` steps:**
1. Checks `session('user_id')` — redirects to `/login` if empty.
2. Checks `maintenance_mode` setting — redirects non-admin-scope to `/maintenance`.
3. Checks `session_timeout_minutes` setting (server-side session timeout) against `last_activity` timestamp. If the difference exceeds the timeout value:
   - Destroys the database-driven session in the `ci_sessions` table.
   - Redirects to `/login` with an "expired" message.
4. Updates the `session['last_activity']` timestamp. Note: Background AJAX polls (such as the notification bell badge) do not extend this timestamp to prevent keeping idle tabs logged in indefinitely.
5. Checks `password_must_rotate` flag — redirects to `/password/change` unless exempt.

**`has_module_access($module_key, $action)` logic:**
1. `super_admin` → always `true`
2. `settings` module → `true` only for `super_admin`
3. Load full `module_permissions` table into static cache (once per request)
4. Return `permissions_cache[$role][$module_key][$action] === 1`
5. No DB row → `false` (no implicit grant)

### 4.6 Privilege Escalation Guards

Multiple layers prevent privilege escalation:

| Check | Where enforced |
|---|---|
| Cannot assign role higher than own | `user.php::save()`, `user.php::update()` — `assignable_role_keys()` |
| Cannot edit user whose role outranks actor | `user.php::edit()`, `user.php::update()` |
| super_admin cannot demote/deactivate self | `user.php::update()` |
| Last super_admin cannot be demoted/deleted | `user.php::update()`, `user.php::delete()` — `countActiveSuperAdmins()` |
| API key scoped to one project | `app.php::apiAuthenticate()` + project check in every API method |

---

## 5. Project Module

### 5.1 Business Flow

Projects are the top-level organisational unit. Every flow, ticket, API key, and alert definition belongs to one project.

```
Create Project
    │
    ▼
Add Flows to Project
    │
    ▼
Add States to Flows (with user pools)
    │
    ▼
Create Alert Definitions (maps alert type → flow)
    │
    ▼
Generate API Keys (one key → one project)
    │
    ▼
Raise Tickets (UI or API)
```

### 5.2 Data Operations

- **Create:** `projectSave()` — inserts with `status = active`; `check_module_access('projects', 'add')`
- **Update:** `projectUpdate()` — validates unique name excluding self; allows status change to `inactive`
- **Soft delete:** `projectSoftDelete()` — cascades to flows (`status = inactive`, `deleted_at = now`), alert_definitions (`is_active = 0`), api_keys (`is_active = 0`)
- **Unique constraint:** `projectNameExists($name, $ignoreId)` — checked before create and update

### 5.3 DataTable Server-Side Query

`projectsForDT($args)` in app_model: Raw SQL with `WHERE deleted_at IS NULL`; search across `name` and `description`; `LIMIT` + `OFFSET` for pagination; joins `userNameMap()` for created_by display name.

---

## 6. Flow Module — Workflow Designer

### 6.1 Flow Creation

A flow is created with:
- `project_id` — parent project
- `name` — unique within the project
- `tat_level_count` — 1–4; controls how many escalation levels the cron job monitors

`tat_level_count` is stored on `flows` and read by `tat_monitor.php` as `tat_level_count` in the ticket row (joined from flows). This determines when L4 breach triggers `status = escalated` rather than bumping to L5 (which does not exist).

### 6.2 State Creation

`state_save()` is a combined create/update endpoint. When `data['id']` is present, it updates; otherwise inserts.

**State creation steps:**
1. Validate `flow_id` and `name` are non-empty
2. Validate parent_state_id: must belong to same flow, must not be self (edit), must not create cycle (BFS descendant check)
3. Validate initial+final mutual exclusion: a state cannot be both
4. Initial/final states always have `parent_state_id = null`
5. JSON-encode level user_id arrays: trim each value, deduplicate, re-index
6. Auto-assign `sort_order = max_existing + 1` for new states
7. If `is_initial`: call `stateClearOtherInitial($flow_id, $savedId)` — sets all other states' `is_initial = 0`
8. If `is_final`: call `stateClearOtherFinal($flow_id, $savedId)` — sets all other states' `is_final = 0`
9. Delete existing backward transitions from this state, then re-insert from posted `backward_state_ids`
10. Log to activity_logs

### 6.3 State Ordering

States are ordered by `sort_order ASC, id ASC`. Reordering happens via AJAX POST to `/flows/reorder_states` with `{flow_id, order: [id1, id2, ...]}`. The model validates that all provided IDs belong to the flow, then updates each state's `sort_order` to its position index.

### 6.4 Workflow Navigation Logic

`stateGetChildren($flow_id, $parent_state_id)` determines where a ticket can go next. Priority:

1. **Explicit forward transitions** (`state_transitions` table, `transition_type = 'forward'`) — for branching flows
2. **Parent-child tree** (`parent_state_id` column) — for legacy flows
3. **Leaf-to-closing rule** — any leaf state (not parent of anything, not the closing state) implicitly routes to the single closing state
4. **Flat flow fallback** — next state by `sort_order` — default for all simple workflows

### 6.5 Cycle Prevention

`stateGetDescendantIds($flow_id, $state_id)` performs a BFS forward traversal using the `state_transitions` table (forward edges) with `parent_state_id` as fallback. Before saving a new forward transition, `state_transition_save()` checks that `to_state_id` is not already a descendant of `from_state_id` — if it is, the transition would create a cycle and is rejected.

### 6.6 Workflow Visualization

`flow_vis_designer_data($states, $transitions)` and `flow_vis_ticket_data($states, $currentStateId, $transitions)` build the data structures for vis-network.

The rendering pipeline:
1. PHP builds JSON `{nodes: [...], edges: [...]}` and embeds it in `<script type="application/json" class="flow-vis-data">`
2. JS reads the JSON from the DOM (`$dataScript.text()`)
3. `renderFlowWidget($widget)` creates a `vis.Network` instance
4. After layout: `storePositions()` is called, hierarchical layout disabled, backward edges added as overlays
5. Fit with extra padding if backward edges exist
6. Animation loop (`flowAnimTick`) runs at ~30fps via `requestAnimationFrame`

**Backward edge rendering:** Vis-network's hierarchical layout only uses forward edges for positioning. After `afterDrawing` fires once, the layout is frozen (`hierarchical: {enabled: false}`) and backward edges are added to the DataSet — they render as curved CCW overlays without disrupting the LR layout.

---

## 7. Ticket Management

### 7.1 Raising a Ticket

`ticket_save()` flow:

```
POST /tickets/save
    │
    ├── check_module_access('tickets', 'add')
    ├── Validate: project_id, flow_id, title required
    ├── Validate: project is active
    ├── Validate: flow is active and belongs to project
    ├── Load initial state via stateGetInitial()
    │   └── If no states → error
    │
    ├── Validate: alert_type ∈ [info, major, critical]
    ├── Validate: priority ∈ [low, medium, high, urgent]
    │
    ├── Validate assignee (if provided):
    │   └── Must be in initial state's L1 pool
    │
    ├── Validate actual dates: end ≥ start
    │
    ├── generate_alarm_id()  ← atomic sequence
    │
    ├── ticketSave([...])
    │   ├── alarm_id
    │   ├── current_state_id = initial.id
    │   ├── current_level = 1
    │   ├── status = 'in_progress' (if assignee) or 'open'
    │   └── state_entered_at = NOW()
    │
    ├── ticketLogAction($id, 'created', [...])
    │
    ├── processTicketAttachment() ← if file provided
    │
    ├── notify_ticket_event('created', ...)
    │   └── Notifies assignee (if set) or L1 pool
    │
    ├── activity_log('tickets', 'create', ...)
    │
    ├── Duplicate detection:
    │   └── Query for open tickets with same alert_type + project in last N hours
    │       → Flash warning if found (ticket still created)
    │
    └── Redirect to ticket detail
```

### 7.2 Ticket Scoping (My Tickets vs All Tickets)

**My Tickets** (`tickets_my()`): passes `mode=my` to DataTable endpoint; `ticketFilters['user_id']` set to logged-in user_id.

**All Tickets** (`tickets_all()`): passes `mode=all`; requires `tickets_all:view` permission; no user_id filter.

**`applyUserScope($q, $tAlias, $userPk, $isAdmin, $sAlias)`** — when not admin:
```sql
AND (
    t.raised_by = ?
    OR t.current_assignee = ?
    OR JSON_CONTAINS(s.l1_user_ids, ?)
    OR JSON_CONTAINS(s.l2_user_ids, ?)
    OR JSON_CONTAINS(s.l3_user_ids, ?)
    OR JSON_CONTAINS(s.l4_user_ids, ?)
    OR EXISTS (SELECT 1 FROM ticket_actions ta WHERE ta.ticket_id = t.id AND ta.performed_by = ?)
)
```

### 7.3 Ticket Assignment

`ticket_assign()` validates:
- Ticket must not be terminal
- `user_id` must be an active user
- User must be in any L1–L4 pool of the current state (not just L1)

On assignment:
- `current_assignee` = user_id string
- `status` = `in_progress`
- `state_entered_at` = NOW()
- `current_level` reset to 1 only if ticket is not already `escalated`
- `actual_start_date` auto-set to today if no assignee existed before and no start date was set
- Email notification sent to new assignee

### 7.4 Ticket Actions (Comments, Title, Description, Priority)

`ticket_action()` handles multiple action types via `$type`:
- `comment`: validates non-empty, ≤5000 chars; calls `parse_mentions()` for @mention notifications
- `title`: validates non-empty, ≤300 chars; logs old/new values
- `description`: validates ≤10000 chars
- `priority`: validates against enum list

All require `tickets:edit` permission. Terminal tickets rejected.

### 7.5 State Movement

`ticket_move_state()`:
- Requires caller to be assignee or admin-scope
- Forward: validates target against `stateGetChildren()`; checks `requires_comment` on explicit forward transitions
- Backward: reason always required; validates against configured backward transitions or sort_order fallback
- On backward move: auto-registers the backward transition if it doesn't exist yet (so diagram reflects actual paths)
- Calls `ticketMoveToState()` which uses `FOR UPDATE` to prevent concurrent duplicate state moves
- Clears assignee if they are not in new state's L1 pool
- Notifies new state's L1 users

### 7.6 Resolve, Close, Reopen

| Action | Method | Requirements | What happens |
|---|---|---|---|
| Resolve | `ticket_resolve()` | Assignee or admin; not terminal | `status='resolved'`, `resolved_at=NOW()`, `actual_end_date` auto-set |
| Close | `ticket_close()` | Assignee or admin; not already closed | `status='closed'`, `closed_at=NOW()`, `actual_end_date` auto-set |
| Reopen | `ticket_reopen()` | Assignee or admin; must be `resolved` (not `closed`) | `status='open'` or `'in_progress'`; `resolved_at=null` |

### 7.7 Attachment Pipeline

`processTicketAttachment($ticket_id, $alarmId, $file)` returns null on success, error string on failure:

1. `upload_filename_is_safe($originalName)` — checks all segments, blocks null bytes
2. Extension against allowed list (`upload_allowed_ext` setting)
3. Extension against hard-coded denylist (`upload_blocked_extensions()`)
4. MIME type against allowed MIME list
5. File size against `upload_max_mb`
6. `upload_sniff_mime($tmpPath)` + `upload_mime_matches_ext($ext, $sniffedMime)` — magic byte check
7. Create directory `writable/uploads/tickets/{alarm_id}/`
8. Write `.htaccess` in directory (disables PHP/CGI execution, removes directory listing)
9. `$file->move($dir, $file->getRandomName())` — moves to randomised filename
10. Log action with `attachment_path` and original filename as `comment`

**Download:** `ticket_download()` resolves the relative path to absolute, validates it starts with `writable/uploads/` (path traversal prevention), checks file exists, serves via `$this->response->download()`.

---

## 8. Workflow Engine

### 8.1 State Movement — Full Decision Tree

```
ticket_move_state($alarm_id)
    │
    ├── Load ticket (ticketLoad)
    ├── Check: caller is assignee or admin-scope
    ├── Check: ticket is not terminal
    │
    ├── if transition_type == 'forward':
    │   ├── Check: current state is not is_final
    │   ├── Get valid next states: stateGetChildren()
    │   │   Priority: explicit forward transitions → parent tree → flat sort_order
    │   ├── Check: targetId ∈ validNextIds
    │   ├── If explicit forward transition has requires_comment: enforce reason
    │   └── proceed
    │
    └── if transition_type == 'backward':
        ├── Require reason (always)
        ├── Get backward transitions: stateGetTransitions(..., 'backward')
        ├── If configured: check targetId ∈ validBwdIds
        ├── If not configured: check targetId.sort_order < currentState.sort_order
        └── proceed

    After validation:
    ├── ticketMoveToState($id, $newStateId)  ← uses FOR UPDATE transaction
    │   ├── Reads current status (escalated or not)
    │   ├── Updates: current_state_id, current_level=1, state_entered_at=NOW()
    │   ├── status: 'escalated' stays 'escalated'; others → 'in_progress'
    │   └── last_tat_warn_level = 0  (fresh warning eligible for new state)
    │
    ├── Auto-register backward transition if new (so diagram shows real paths)
    │
    ├── Clear assignee if not in new state's L1 pool
    │
    ├── Log to ticket_actions (state_changed)
    ├── activity_log
    └── Notify new state's L1 users
```

### 8.2 The `stateGetChildren()` Method

This is the most complex navigation method. It determines where a ticket can legally go next:

```php
// Priority 1: Explicit forward transitions in state_transitions table
$rows = JOIN state_transitions ON s.id = st.to_state_id
        WHERE st.flow_id = $flow_id
          AND st.from_state_id = $parent_state_id
          AND st.transition_type = 'forward'
        ORDER BY st.sort_order, st.id;

// Priority 2: Parent-child tree (legacy)
if (state has parent_state_id links):
    // Leaf → closing state (implicit)

// Priority 3: Flat flow, next by sort_order
SELECT * FROM states WHERE flow_id = ? 
    AND (sort_order > currentSortOrder OR (sort_order = currentSortOrder AND id > currentId))
    ORDER BY sort_order ASC, id ASC
    LIMIT 1;
```

### 8.3 Rework Scenarios

When an operator needs to rework a ticket:
1. Opens ticket detail → Move State tab → Send Back section
2. Selects a previous state from the backward transition list
3. Must provide a reason (enforced client-side AND server-side)
4. After successful backward move, the transition is recorded in `state_transitions` as backward type

The vis-network diagram will show the red dashed backward arrow after this operation because the transition record now exists. This gives management visibility into which tickets required rework.

---

## 9. Notifications

### 9.1 Email Queue Architecture

```
Controller method
    │
    ▼
notify_ticket_event($event, $ticket, $extraContext, $userIds)
    │
    ├── Build $context (alarm_id, title, state_name, level, TAT expiry, URL, ...)
    ├── mail_subject($event, $context) → Subject line
    ├── mail_html_body($event, $context) → Full HTML template (inline styles)
    │
    └── notify_users($userIds, $ticket_id, $subject, $body)
        ├── Filter: user_notify_allowed($uid, $project_id, $severity)
        ├── INSERT INTO notification_logs (status='pending')
        └── Prune logs older than 90 days

Background (tat_monitor.php every minute):
    process_notification_queue()
        ├── SELECT WHERE status='pending' LIMIT $batch ORDER BY id ASC
        ├── For each row:
        │   ├── send_email($recipient_email, $subject, $body)
        │   ├── On success: UPDATE status='sent', sent_at=NOW()
        │   └── On failure:
        │       ├── Parse retry count from error_message '/N'
        │       ├── If attempts >= maxAttempts: UPDATE status='failed'
        │       └── Else: UPDATE error_message='transient send failure /N'
        └── Return {sent, failed, retried}
```

### 9.2 Event Types and Notification Triggers

| Event | Trigger point | Recipients |
|---|---|---|
| `created` | `ticket_save()` | Assignee (if set) or initial state L1 pool |
| `assigned` | `ticket_assign()` | Newly assigned user |
| `state_changed` | `ticket_move_state()` | L1 pool of the new state |
| `level_escalated` | `tat_monitor.php` | L{N+1} pool (from escalation matrix or state) |
| `tat_breach` | `tat_monitor.php` (L4) | L4 pool |
| `resolved` | (configured separately if needed) | Configurable |
| `@mention` in comment | `ticket_action()` → `parse_mentions()` | Each mentioned user individually |

### 9.3 Bell Badge — Live Polling

The topbar bell badge polls `GET /notifications/actionable_count` every `live_poll_seconds` (default 15) seconds.

`actionable_count()` calls `ticketCountActionable($userId, $isAdmin)` which runs a raw SQL query counting:
- All escalated tickets visible to the user
- All critical open/in_progress tickets visible to the user

Returns `{total, escalated, critical_open}`.

When `total` increases compared to the last poll:
- Plays a beep via Web Audio API (if `live_audio_enabled = 1`)
- Shows a browser push notification (if `live_browser_notify = 1` and permission granted)
- Updates the tab title to `(N) pView`
- Updates the favicon to a red badge (canvas-drawn)

`GET /notifications/recent` loads the bell dropdown content: up to 10 actionable tickets (sorted by severity then escalation status) + up to 10 recent @mention notifications.

### 9.4 @Mention System

`parse_mentions($text, $excludeUserId)`:
- Regex: `(?:^|\s)@([a-zA-Z0-9._-]{3,64})`
- Extracts all candidate usernames
- Validates each against `users` WHERE `user_id IN (candidates)` AND `is_active = 1`
- Excludes the comment author
- Deduplicates

After a comment is saved, each mentioned user receives a personal email via `notify_users()` with the @mention context. This notification bypasses the user's notification preferences (mention → always notify).

`highlight_mentions($text)` is used in the ticket timeline view to render `@user_id` tokens as styled chips. It HTML-escapes the full string first, then applies a regex replace — safe against XSS even if the replacement fails (returns the escaped string).

---

## 10. Activity Logging

### 10.1 Architecture

`activity_log()` is called throughout controllers after every significant operation. It is non-fatal — exceptions are caught and logged via `error_log()` so logging failures never break the live request.

**Signature:**
```php
activity_log(
    string $module,         // 'tickets', 'users', 'auth', 'settings', etc.
    string $action,         // 'create', 'update', 'delete', 'login', etc.
    ?string $entity_type,   // 'ticket', 'user', 'role', etc.
    ?string $entity_id,     // alarm_id, user pk, role_key, etc.
    string $summary,        // Human-readable description
    array $meta,            // Structured data (diffs, field changes)
    array $overrides        // Force user_id/name/role (for pre-auth events)
)
```

**Activity diff pattern** (used on update operations):
```php
$before = $this->app_model->projectGetById($id);
// ... perform update ...
$diff = activity_diff($before, $after, ['name', 'description', 'status']);
activity_log('projects', 'update', 'project', (string)$id, 'Updated project', $diff);
```

`activity_diff()` performs string comparison on all specified fields and returns only changed values as `[field → [old, new]]`.

### 10.2 Analytics Pipeline

`activity_logs_analytics()` returns a JSON payload with:

**Login stats:**
```sql
SELECT COUNT(*) FROM activity_logs
WHERE action = 'login' AND DATE(created_at) = CURDATE()
```

**Top active users:**
```sql
SELECT user_id, user_name, user_role, COUNT(*) AS event_count, MAX(created_at) AS last_seen
FROM activity_logs
WHERE created_at BETWEEN ? AND ?
GROUP BY user_id, user_name, user_role
ORDER BY event_count DESC
LIMIT 10
```

**Module usage:**
```sql
SELECT module, COUNT(*) AS cnt
FROM activity_logs
WHERE created_at BETWEEN ? AND ?
GROUP BY module ORDER BY cnt DESC
```

**Session duration** (computed by pairing login/logout rows for each user within the period).

**User events drilldown** (`activity_logs_user_events()`): Returns metadata and event list for one user over last 30 days.

### 10.3 Export

`activity_logs_export()` streams a CSV file with UTF-8 BOM. Applies the same filters as the DataTable view. Limited to 50,000 rows.

---

## 11. Dashboard & Reporting

### 11.1 KPI Queries

All KPI queries call `applyUserScope()` when the user is not admin-scope, filtering to the user's relevant tickets.

**`ticketCountByStatus($userPk, $isAdmin, $projectId)`:**
```sql
SELECT status, COUNT(*) AS n FROM tickets
[USER SCOPE JOIN]
WHERE project_id = ? (optional)
GROUP BY status
```
Returns: `{open, in_progress, escalated, resolved, closed}`.

**`ticketCountByAlertTypeActive($userPk, $isAdmin, $projectId)`:**
```sql
SELECT alert_type, COUNT(*) AS n FROM tickets
WHERE status IN ('open', 'in_progress', 'escalated')
[USER SCOPE JOIN]
GROUP BY alert_type
```
Returns: `{info, major, critical}`.

**`ticketCountTatBreached($userPk, $isAdmin, $projectId)`:**
```sql
SELECT COUNT(*) FROM tickets WHERE status = 'escalated' [USER SCOPE]
```

**Open count for KPI card:** `open + in_progress + escalated`.

### 11.2 Trend Chart

`ticketTrendByRange($days, $userPk, $isAdmin, $projectId)`:
1. Builds a PHP array of `{YYYY-MM-DD: 0}` for the last N days
2. Queries:
```sql
SELECT DATE(created_at) AS day_key, COUNT(*) AS n
FROM tickets WHERE created_at >= ?
[USER SCOPE] GROUP BY DATE(created_at)
```
3. Merges query results into the pre-built array (unfilled days stay 0)
4. Returns `{labels: [], values: []}` — labels are short date strings (Mon/d-M depending on range)

### 11.3 Dashboard Trend AJAX Update

When user clicks a range pill, JS calls `GET /dashboard/trend?range=N`. The controller returns the same data structure. JS updates the chart without a page reload.

### 11.4 Recent Tickets Widget

`ticketRecent(5, $userPk, $isAdmin, $prefDefaultProjectId)`:
- Selects open/in_progress/escalated tickets visible to the user
- Sorts by: `FIELD(status, 'escalated', 'in_progress', 'open') ASC, FIELD(alert_type, 'critical', 'major', 'info') ASC, state_entered_at ASC`
- This ensures escalated critical tickets appear first, then older entries

---

## 12. Settings & Administration

### 12.1 Settings Cache

`app_settings_all()` implements a two-layer cache:

1. **Static PHP variable** (`$cache`) — lives for the current request (zero DB hit on repeated reads)
2. **File cache** (`writable/cache/app_settings.cache`) — survives across requests with 5-minute TTL. Written as JSON. Read on cache miss or expiry

`app_settings_clear_cache()` deletes the file cache. Called after:
- Any settings save (`settings_save()`)
- Logout (`user_model::logout()`) — ensures a different user on the same server gets fresh settings
- Asset version bump (`settings_bump_asset_version()`)
- Maintenance mode disable (`maintenance_disable()`)

### 12.2 Settings Form Processing

`settings_save()` reads all existing settings, then for each row:
- If the setting's current value is `'0'` or `'1'` AND it's not in the `$numericKeys` list → treat as boolean toggle (checkbox: present=1, absent=0)
- Otherwise → read directly from POST (text/number inputs)
- Only write to DB if the value changed (reduces write load and audit log noise)
- Passwords/secrets are masked in the activity_log diff (`(hidden)` / `(updated)`)

### 12.3 Module Control Panel

The permission grid is a matrix of role × module × action checkboxes. On save:

`modulePermissionsSave($role, $permissions)` iterates each module and does an upsert (check count, then INSERT or UPDATE) for each `(role, module_key)` pair.

Permission cache in `has_module_access()` is per-request static, so changes take effect on the next request for all users.

The `ensureModulesTable()` call in `module_control_panel()` creates the `modules` table on first open if it does not exist (lazy schema migration for very old installations).

### 12.4 API Key Management

`apiKeyGenerate($project_id, $name)`:
```php
$key = bin2hex(random_bytes(24));  // 48-character hex string
```
The key is stored as plain text in `api_keys.api_key`. The full key is shown once in the UI via a flash session variable (`$session->setFlashdata('newKey', $key)`). On the next page load after redirect, the key is displayed and the flash data is consumed — it cannot be retrieved again.

`apiKeyGetByKey($key)` JOINs projects to validate that the project is still active and not soft-deleted. A key for a deleted project is treated as invalid.

---

## 13. REST API

### 13.1 Authentication

Every API endpoint calls `$this->apiAuthenticate()` first:
1. Reads `X-API-KEY` header
2. Calls `apiKeyGetByKey($key)` — checks active key + active project
3. Calls `apiKeyTouchLastUsed($id)` — updates `last_used` timestamp
4. Populates `$this->api_key_row` with the full key row

On failure: returns `apiDeny()` — HTTP 401 with JSON error.

### 13.2 Rate Limiting

After authenticate, every endpoint calls `$this->apiRateLimit()`:
- `api_rate_check($apiKeyId, $endpoint)` checks per-minute and per-hour limits from app_settings
- Records request in `api_request_log`
- Returns null (allowed) or HTTP 429 with `Retry-After` header

### 13.3 Endpoint Reference

#### `POST /api/raise`

**Purpose:** External system creates a new ticket.

**Request body (JSON):**
```json
{
    "project_id": 1,
    "flow_id": 2,
    "title": "string (required, max 300 chars)",
    "description": "string (optional, max 10000 chars)",
    "alert_type": "info|major|critical (default: info)",
    "priority": "low|medium|high|urgent (default: medium)",
    "source_system": "string (optional, max 100 chars)",
    "alert_def_id": 42  (optional)
}
```

**Validation:**
- `project_id` must match the API key's project (403 if mismatch)
- `flow_id` must exist and belong to the project
- Flow must have at least one state

**Process:** Same as UI ticket creation except `source = 'api'` and no assignee, no attachment.

**Response (201):**
```json
{
    "success": true,
    "alarm_id": "ALM-20260605-00042",
    "ticket_id": 42,
    "current_state": "Triage",
    "notified_users": ["ops@example.com"],
    "message": "Alert raised successfully"
}
```

#### `GET /api/alert/(:any)`

**Purpose:** Read ticket details.

**Validation:** alarm_id format check; project isolation.

**Response (200):**
```json
{
    "success": true,
    "alarm_id": "ALM-20260605-00042",
    "title": "...",
    "status": "in_progress",
    "current_state": "Investigation",
    "current_level": 2,
    "alert_type": "critical",
    "priority": "urgent",
    "created_at": "2026-06-05 14:30:00",
    "tat_remaining_minutes": 47,
    "activity": [...]
}
```

`tat_remaining_minutes` calculated as: `state_entered_at + lN_tat_minutes - NOW()`, expressed in minutes.

#### `POST /api/alert/(:any)/update`

**Purpose:** Update ticket from external system.

**Request body:**
```json
{
    "action": "resolved|closed|comment",
    "comment": "string",
    "performed_by_system": "string"
}
```

**Response (200):**
```json
{
    "success": true,
    "alarm_id": "ALM-20260605-00042",
    "new_status": "resolved",
    "message": "Alert updated successfully"
}
```

#### `GET /api/alerts`

**Purpose:** List tickets for the API key's project.

**Query parameters:** `status`, `alert_type`, `limit` (default 50, max 200), `offset` (default 0).

**Response (200):**
```json
{
    "success": true,
    "count": 12,
    "data": [...]
}
```

#### `GET /api/flows`

**Purpose:** List flows available in the API key's project.

**Response (200):**
```json
{
    "success": true,
    "data": [{ "id": 1, "name": "Standard Incident", "status": "active", ... }]
}
```

### 13.4 Error Responses

| HTTP code | When |
|---|---|
| 400 | Missing required fields, invalid alarm_id format, flow_id not in project |
| 401 | Missing or invalid X-API-KEY |
| 403 | API key is not authorised for this project/ticket |
| 404 | Ticket not found |
| 429 | Rate limit exceeded (includes Retry-After header) |

All error responses:
```json
{
    "success": false,
    "message": "description of the error"
}
```

---

## 14. Frontend Architecture

### 14.1 Script Loading Order

```html
<script src="vendor/jquery/jquery-3.7.1.min.js">
<script src="vendor/jquery-ui/jquery-ui-1.13.2.min.js">
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js">
<script src="vendor/datatables/js/jquery.dataTables.min.js">
<script src="vendor/datatables/js/dataTables.bootstrap5.min.js">
<script src="vendor/select2/js/select2.min.js">
<script src="vendor/toastr/toastr.min.js">
<script src="vendor/sweetalert2/sweetalert2.min.js">
<script src="vendor/chartjs/chart.umd.min.js">
<script src="vendor/vis-network/vis-network.min.js">
<script src="assets/js/app.js?v={asset_version}">         ← Core app
<script src="assets/js/datatable.js?v={asset_version}">   ← Tables (loaded after app.js)
<script src="assets/js/calendar.js?v={asset_version}">    ← Global Date Range Picker
```

`datatable.js` and `calendar.js` must load after `app.js` because they depend on globals defined there (`showError`, `escapeHtml`, `APP_COLORS`, etc.). The top-level call `setupDataTablesDefaults()` was moved to the start of `document.ready` to ensure both scripts are fully parsed before it executes.

### 14.2 `app.js` Structure

| Section | Line range (approx) | Contents |
|---|---|---|
| 01. Globals & Config | 1–24 | `APP_COLORS`, `tatTimer`, `$appDocument`, `$appWindow`, `$appHtml`, `$appBody`, `APP_MOBILE_BREAKPOINT` |
| 02. Utility Helpers | 25–165 | `getLocalPref/saveLocalPref`, `escapeHtml`, `getArrayFromText`, `showSuccess/showError/extractErrorMessage`, `handleResponse`, `getSettingInt`, `toBoolean` |
| 03. Toast Setup | 145–170 | `applyUserPreferences()`, `setupToastr()` |
| 27. Page Init | 170–270 | Top-level calls + `$appDocument.ready()` wiring all init functions |
| 04. Confirm Dialogs | 270–340 | `confirmDialog()`, `initConfirmLinks()`, `initConfirmForms()` |
| 05. Tooltips | 340–420 | Custom positioned tooltip (replaces browser default) |
| 07. Dropdowns | 420–550 | `initSelectFields()` (Select2), `loadLinkedSelect()`, `initAjaxSelectLoaders()` |
| 13. TAT Countdowns | 550–660 | `renderTatItem()`, `updateTatCountdowns()`, `initTatCountdowns()` |
| 14. Dashboard Charts | 660–980 | `setupChartDefaults()`, `initTrendCharts()`, `initSeverityCharts()` |
| 08. Form Helpers | 980–1100 | `submitNormalForm()`, `submitFileForm()`, `bindPostForm()`, `bindPostButton()` |
| 18. Ticket Detail | 1100–1350 | `initPriorityInline()`, `initEditableFields()`, `initTicketCreatePage()`, `initCopyButtons()`, `initTicketDetailPage()` |
| 19. Flow Vis | 1350–1900 | vis-network rendering, animation loop, widget controls |
| 20. State Designer | 1900–2100 | `initStateSorter()`, `initTransitionsDesigner()`, `initMoveStateTypedForms()` |
| 12. Form Validation | 2100–2300 | `initPasswordToggle()`, `initLoadingForms()`, `initCharCount()`, `initUnsavedFormWarning()`, `initCapsLockAlert()` |
| 23. Users | 2300–2400 | `initUserIdLiveCheck()` |
| 09. Theme | 2400–2500 | `initThemeSwitch()` |
| 10. Sidebar | 2500–2800 | `initSidebarMenu()` (desktop collapse + mobile drawer) |
| 11. Search Hotkey | 2800–2850 | `/` key focuses DataTable search |
| 21. Notifications Bell | 2850–3300 | Bell badge polling, favicon badge, tab title, audio cue, browser push |
| 22. Bell Dropdown | 3300–3450 | `renderBellList()`, `loadBellList()`, `initBellDropdown()` |
| 26. Mentions | 3450–3560 | `initMentionAutocomplete()` |
| 24. Settings | 3560–3660 | `initSendTestEmail()`, `initBumpAssetVersion()` |
| 27. Date Range Widget | 3660–3800 | `drwDateStr()`, `setDateRangePreset()`, `getDateRange()`, `initDateRangeWidgets()` (Note: unified with the global calendar picker) |
| Auto Logout | 3800–3900 | `initAutoLogout()` (idle detection + SweetAlert countdown) |
| Sidebar Scroll | 3900–3950 | `initSidebarScrollSave()` |
| 28. Form Validation | 3950–3946 | `markInvalid()`, `validateField()`, `validateForm()`, `initFormValidation()` |

### 14.3 `datatable.js` Structure

| Section | Contents |
|---|---|
| 01. Table Utilities | `getTablePageLength()`, `setupDataTablesDefaults()`, `dtRegistry`, `trackDataTable()`, `resizeAllTables()`, `initTableAutoResize()`, `initServerTable()`, `initTableFromDataUrl()`, `truncateCell()` |
| 02. Simple Tables | `initSimpleTables()` — client-side DataTables for static HTML tables |
| 03–06. Module Tables | `initProjectsTable()`, `initUsersTable()`, `initAlertsTable()`, `initFlowsTable()`, `initListTables()` |
| 07. Tickets Table | `ticketFilters`, `getTicketFilters()`, `initTicketsTable()` |
| 08. Bulk Actions | `bulkSelected`, `getSelectedIds()`, `refreshBulkToolbar()`, `clearBulkSelection()`, `postBulkAction()`, `initListReopenButtons()`, `initBulkActions()` |
| 09. Saved Filters | `initSavedFilters()` |
| 10. Ticket Filters | `parseQueryString()`, `syncTicketsFilterUI()`, `applyTicketUrl()`, `updateTicketsExportHref()`, `initTicketsAjaxFilters()` |
| 11. Activity Logs | `initAuditLogTable()` |
| 12. Analytics | `loadAnalytics()`, `renderAnalytics()`, `openUserDrilldown()`, `initAnalyticsTab()` |

### 14.4 DataTable Server-Side Pattern

Every server-side DataTable uses this pattern:

**PHP side (`dt_parse_request`):** Reads draw, start, length, search, order[0][column], order[0][dir] from GET parameters. Maps column index to allowed DB column name via a whitelist `$colMap`.

**JS side (`initServerTable`):** Sends standard DataTables parameters on every page/sort/search event. The `ajax.data` callback for the tickets table merges `ticketFilters` object into the request parameters.

**Filter flow for tickets:**
1. User applies filter (pill click, form submit, saved filter link)
2. `applyTicketUrl(href, true)` parses the URL query string
3. Updates `ticketFilters` object (module-scoped in `datatable.js`)
4. Calls `syncTicketsFilterUI(params)` — updates form inputs, pills, badges
5. Calls `$table.DataTable().ajax.reload(null, false)` — reloads without resetting page
6. Pushes URL to browser history (pushState)

### 14.5 Theme Management

Theme preference is stored three ways:
1. `localStorage['noc-theme']` — read immediately on page load via inline `<script>` in `<head>` to prevent flash
2. `session['theme']` — loaded from DB at login, used by PHP to set `data-theme` on `<html>`
3. `users.theme` column — persisted to DB via AJAX POST to `/users/update_theme`

On toggle: updates all three stores, calls `trendChart.update()` if the trend chart exists (grid colour changes between themes).

### 14.6 Flow Visualization — Canvas Animation

The vis-network canvas renders via WebGL/canvas. The animation loop (`flowAnimTick`) drives:

1. **Flowing dots along edges:** `flowBezierPoint(p0, cp1, cp2, p3, t)` computes positions along a cubic Bezier curve. Two dots per edge, offset by 0.5 in t, giving a "flow" effect. Only forward edges animate.

2. **Pulsing ring around current state:** An ellipse with pulsing radius and alpha, drawn in canvas coordinates using vis-network's `getBoundingBox(nodeId)`.

The loop throttles to ~30fps (`ts - flowAnimLast >= 32`). It parks itself when `document.hidden` (tab not visible) and resumes on `visibilitychange`.

---

## 15. Security & Validation

### 15.1 Authentication Security

| Measure | Implementation |
|---|---|
| Password hashing | `password_hash($pass, PASSWORD_BCRYPT)` in `user_model::save()` and `::update()` |
| Brute-force lockout | Per-user sliding window in `login_attempts` table; same error message regardless of whether guess was correct |
| Session fixation | `session->regenerate(true)` immediately after successful login |
| Session data isolation | `session_unset()` before writing new user data |
| Session Storage | Database-driven session storage (`DatabaseHandler` on `ci_sessions` table) to support multi-server setups |
| CSRF protection | CI4 global CSRF filter (tokens in all forms) |
| Idle timeout | `check_isvalidated()` enforces `session_timeout_minutes` and `session_idle_timeout_minutes` settings |

### 15.2 Authorisation Security

| Measure | Implementation |
|---|---|
| Every page checks session | `check_isvalidated()` called first in every protected method |
| Every page checks permission | `check_module_access()` |
| Privilege escalation prevention | `assignable_role_keys()` limits role assignments |
| Last admin guard | `countActiveSuperAdmins()` before demote/delete |
| Terminal ticket immutability | `ticketIsTerminal()` before every mutation |
| File download path traversal | `realpath()` + prefix check against `writable/uploads/` |
| Alarm ID format validation | `safe_alarm_id()` regex before any ticket lookup |

### 15.3 File Upload Security

Six validation layers in `processTicketAttachment()`:
1. Filename safety (null bytes, control chars, all dot segments)
2. Extension allowlist check
3. Hard-coded extension denylist (always applied, cannot be overridden)
4. MIME type allowlist check (declared header MIME)
5. Magic byte check (`finfo` reads actual bytes)
6. MIME-extension cross-validation

Upload directory protection:
- Files stored in `writable/uploads/tickets/{alarm_id}/` — outside web root equivalent
- Auto-generated `.htaccess` disables PHP execution and CGI in each directory
- Files served only through `ticket_download()` — never by web server directly

### 15.4 Input Validation

| Input | Validation |
|---|---|
| User ID | `/^[A-Za-z0-9._-]{3,64}$/` via `validate_user_id()` |
| Role key | `/^[a-z][a-z0-9_]{1,49}$/` in `user.php::role_save()` |
| Module key | Same pattern as role key |
| Alarm ID | `/^ALM-\d{8}-\d{5}$/` via `safe_alarm_id()` |
| Password | `validate_password()` checks length and character requirements from app_settings |
| State enum fields | Explicit `in_array()` checks against valid values |
| Sort direction | Only `asc` or `desc` accepted; defaults to `asc`/`desc` |
| DataTable columns | Column map whitelist in every DT query method |

### 15.5 SQL Injection Prevention

All queries use CI4's Query Builder parameterisation. Raw SQL queries (performance-critical paths) use `$this->db->query($sql, $params)` with array binding. No string interpolation is used in query construction except for whitelist-validated column names and sort directions.

### 15.6 XSS Prevention

All PHP output is passed through CI4's `esc()` function. All JS-rendered user content uses `escapeHtml()` before DOM insertion. The `highlight_mentions()` function escapes first, then applies the regex replacement — it cannot output unescaped user content even if the regex fails.

---

## 16. Background Jobs & Cron

### 16.1 tat_monitor.php — Complete Implementation

```
Startup
├── CLI check: PHP_SAPI !== 'cli' → exit with 404
├── Bootstrap CI4 environment (Paths, DotEnv, autoloader, helpers)
├── Instantiate App_model
│
├── File lock: fopen('writable/cache/tat_monitor.lock', 'w')
│   flock(LOCK_EX | LOCK_NB)
│   → Already locked: print message, exit
│
├── Record start time
│
Ticket Processing
├── ticketActiveForTatCheck()
│   └── SELECT all tickets WHERE status IN ('open', 'in_progress')
│       AND (s.is_final IS NULL OR s.is_final = 0)
│       WITH full ticketSelect() JOINs + tat_level_count
│
├── For each ticket:
│   ├── Extract: level, flow_id, state_id, state_entered_at, tat_level_count
│   ├── Skip if state_entered_at is invalid
│   │
│   ├── Resolve TAT: escalationRule(flow_id, state_id, level) → override or state.l{N}_tat_minutes
│   ├── Resolve notify list: matrix override or stateLevelUsers(ticket, level)
│   │
│   ├── expiresAt = state_entered_at + tatMinutes * 60
│   ├── If time() < expiresAt → continue (not breached)
│   │
│   ├── If level < tat_level_count (L1, L2, L3):
│   │   ├── ticketEscalateLevel($id, level + 1)
│   │   │   ├── status: level >= 5 → 'escalated'; else → 'in_progress'
│   │   │   ├── current_level = newLevel
│   │   │   ├── state_entered_at = NOW()
│   │   │   └── last_tat_warn_level = 0
│   │   ├── ticketLogAction('level_escalated', ...)
│   │   ├── Resolve next-level notify list (matrix-aware)
│   │   └── notify_ticket_event('level_escalated', ...)
│   │
│   └── If level == tat_level_count (L4, or whatever max is):
│       ├── ticketUpdate: status = 'escalated'
│       ├── ticketLogAction('level_escalated', level=4)
│       └── notify_ticket_event('tat_breach', ...)
│
Email Queue
└── process_notification_queue() ← sends queued emails
    Returns {sent, failed, retried}
│
Log Table Pruning
├── Prune api_request_log WHERE requested_at < 24h ago
└── Prune login_attempts WHERE attempted_at < retention_days ago
│
Cron Run Recording
├── INSERT INTO cron_runs: script, started_at, finished_at, duration_ms, status, tickets_checked, notifs_sent/failed
└── Prune old cron_runs: keep last 99 per script
│
Lock Release
└── flock(LOCK_UN) + fclose()
```

### 16.2 TAT Warning (80% Threshold)

The current codebase sets `last_tat_warn_level = 0` when a ticket moves state or escalates. The JS countdown uses `data-tat-total-ms` on the countdown element to trigger a visual warning at ≤25% remaining. A full server-side 80% warning notification can be implemented by adding a check in tat_monitor.php:

```php
// Pseudocode for 80% TAT warning (not yet implemented server-side):
$warnThreshold = $enteredAt + ($tatMinutes * 60 * 0.8);
if (time() >= $warnThreshold && $ticket['last_tat_warn_level'] < $level) {
    notify_ticket_event('tat_warning', $ticket, ['level' => $level], $notifyList);
    ticketUpdate($id, ['last_tat_warn_level' => $level]);
}
```

The `last_tat_warn_level` column already exists for this purpose.

---

## 17. Deployment & Environment Setup

### 17.1 Requirements

| Requirement | Minimum |
|---|---|
| PHP | 8.1+ |
| PHP extensions | `pdo_mysql`, `mbstring`, `intl`, `json`, `openssl`, `fileinfo` |
| Database | MySQL 8.0+ or MariaDB 10.4+ |
| Composer | 2.x |
| Web server | Apache (mod_rewrite) or Nginx |
| Cron | System cron or Task Scheduler |

### 17.2 Installation Steps

```bash
# 1. Clone
git clone <repo> /var/www/pview_alerts
cd /var/www/pview_alerts

# 2. PHP dependencies
composer install --no-dev --optimize-autoloader

# 3. Permissions
chmod -R 775 writable/
chown -R www-data:www-data writable/

# 4. Environment
cp .env.example .env
# Edit .env: baseURL, database credentials, SMTP

# 5. Database
mysql -u root -p -e "CREATE DATABASE pview_alerts CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u pview -p pview_alerts < scripts/schema.sql
php scripts/setup_defaults.php

# 6. Run migrations (upgrades only)
php spark migrate

# 7. Cron
crontab -e
# Add: * * * * * /usr/bin/php /var/www/pview_alerts/tat_monitor.php >> /var/log/pview_tat.log 2>&1

# 8. Web server: point DocumentRoot to /var/www/pview_alerts/public/
```

### 17.3 Environment Variables

```ini
CI_ENVIRONMENT = production           # or: development
app.baseURL = 'https://pview.com/'    # trailing slash required

# Database
database.default.hostname = localhost
database.default.database = pview_alerts
database.default.username = pview
database.default.password = secure_password
database.default.DBDriver = MySQLi
database.default.port = 3306

# Email (overridden by app_settings table at runtime)
email.fromEmail = alerts@example.com
email.fromName = 'pView Alerts'
email.protocol = smtp
email.SMTPHost = smtp.example.com
email.SMTPUser = alerts@example.com
email.SMTPPass = smtp_password
email.SMTPPort = 587
email.SMTPCrypto = tls
```

### 17.4 `setup_defaults.php` — What It Seeds

**Roles:** Creates only the `super_admin` role. The `admin` and `user` roles are not seeded — they must be created manually via `/roles` after first login.

**Admin user:** User ID `admin`, password `Demo@1234`, role `super_admin`.

**Modules:** Inserts all system module rows (dashboard, projects, flows, alerts, escalation, tickets, tickets_all, users, api_keys, activity_logs, cron_panel, settings, roles, module_control_panel).

**Module permissions:** Full grant to `super_admin` for all modules only. No permission rows are created for other roles since only `super_admin` is seeded.

**App settings:** All 40+ settings with production defaults including:
- `app_name = pView Alert System`
- `password_min_length = 8`, `password_require_letter = 1`, `password_require_digit = 1`, `password_rotate_days = 90`
- `login_max_attempts = 3`, `login_lockout_minutes = 10`
- `session_idle_timeout_minutes = 30`
- `default_tat_l1_minutes = 60`, `l2 = 120`, `l3 = 240`, `l4 = 480`
- `live_poll_seconds = 15`, `live_audio_enabled = 1`, `live_browser_notify = 1`
- `notification_batch_size = 50`, `notification_max_attempts = 5`
- `upload_max_mb = 10`, `upload_allowed_ext = pdf,doc,docx,jpg,jpeg,png,xlsx,xls,csv,txt`
- `api_rate_per_minute = 60`, `api_rate_per_hour = 1000`
- `datatable_page_length = 25`, `dashboard_trend_ranges = 7,15,30`
- `asset_version = 1`, `log_retention_days = 30`

### 17.5 Production Checklist

- [ ] `CI_ENVIRONMENT = production` in `.env`
- [ ] Web server DocumentRoot → `public/` only
- [ ] `writable/` not web-accessible (Apache: `<Directory>` block or Nginx `deny all`)
- [ ] SMTP configured and test email sent
- [ ] Cron running (`cron_panel` shows runs within 2 minutes)
- [ ] Default admin password changed
- [ ] `login_show_demo_creds = 0` in settings
- [ ] HTTPS configured (SSL certificate)
- [ ] Daily backup script scheduled
- [ ] `asset_version` bumped after any CSS/JS deployment

---

## 18. Code Standards

### 18.1 PHP Conventions

| Convention | Example |
|---|---|
| Class names | `App` (controller), `App_model`, `User_model` |
| Method names | `snake_case` for all methods |
| Variable names | `$camelCase` for local vars, `$snake_case` for DB columns |
| Helper functions | `snake_case`, prefixed by module (`activity_log`, `validate_password`) |
| Type casting | Explicit: `(int)`, `(string)`, `(array)` on all external inputs |
| Null safety | `isset() ? ... : ''` pattern; no null-coalescing (`??`) for compatibility |

**No ternary and null-coalescing operators:** Per team coding standards, all ternary expressions (`? :`) and null-coalescing (`??`) operators are strictly avoided in favor of explicit `if/else` and `isset()` statements to ensure maximum PHP compatibility and style uniformity. This applies to all PHP and JS code.

**No arrow functions in JS:** All JS uses `function()` syntax for browser compatibility and consistency with existing code.

**No complex abstractions:** Single-purpose functions preferred over class hierarchies. Helper functions are all standalone (`function_exists()` guards for safe re-registration).

### 18.2 Database Standards

| Convention | Example |
|---|---|
| Table names | `snake_case` plural (`ticket_actions`, `login_attempts`) |
| Column names | `snake_case` (`current_state_id`, `is_active`) |
| Foreign keys | Named as `{table_singular}_id` |
| Soft delete | `deleted_at DATETIME NULL` (NULL = active) |
| Timestamps | `created_at`, `updated_at`, `deleted_at` as DATETIME |
| JSON columns | Store as standard JSON; decode in PHP before use |
| Boolean flags | `TINYINT(1)` — 0 or 1; never stored as bool |
| Enum columns | Explicit ENUM type for controlled vocabularies |

**User references (post-2026-05-21 migration):** All user references (state pools, assignee, raised_by, performed_by, etc.) use `users.user_id` strings (e.g., `"jdoe"`), not numeric `users.id`. This is a deliberate design choice for clarity and readability in audit logs.

### 18.3 JavaScript Standards

- Variables declared with `var` (no `let`/`const`) for broad browser compatibility
- All event handlers use namespaced events (e.g., `.on('click.confirmLinks', ...)`) with `.off()` before `.on()` to prevent duplicate binding
- jQuery objects prefixed with `$` (e.g., `$table`, `$form`)
- AJAX responses always checked for `response.success` before acting
- Functions separated by section comments `// ====...====`

### 18.4 Commenting Standards

PHP comments are kept minimal:
- One-line `//` comments for non-obvious WHY (not WHAT)
- No multi-line block comments for obvious code
- Cross-reference tickets/decisions as inline comments (e.g., `// WF-02`, `// PERF-01`)

JS comments follow the same pattern — function purpose is stated once at the top of each section header, individual functions are self-documented by name.

### 18.5 Route Conventions

- GET routes for read-only views and AJAX reads
- POST routes for all create, update, delete, and destructive operations (prevents CSRF via GET-triggered links)
- Numeric segment constraint `(:num)` for DB primary keys
- `(:any)` for alarm IDs (validated by `safe_alarm_id()` inside the method)
- `(:segment)` for role keys and module keys (no slashes)

---

## 19. Known Limitations & Future Scope

### 19.1 Current Limitations

| Limitation | Detail |
|---|---|
| Monolithic controllers | `app.php` is ~4,300 lines with 60+ methods; hard to navigate for new developers |
| No real-time websockets | Bell badge uses polling (15s default); not truly real-time |
| No test suite | No PHPUnit tests beyond a stub test config; changes require manual testing |
| Single SMTP sender | No multi-tenant SMTP; all notifications go from one From address |
| File storage — local only | Attachments in `writable/uploads/`; no S3 or cloud storage support |
| No database-level FKs enforced | FKs are logical (enforced in PHP); no CONSTRAINT FOREIGN KEY in schema for flexibility with soft deletes |
| Session driver — file | Works for single-server setups; requires Redis/database session for multi-server |
| API key stored plain text | API keys are not hashed; access to `api_keys` table must be strictly controlled |
| `tat_monitor.php` — single cron | One process handles all projects; very high ticket volumes could exceed 60-second window |

### 19.2 Future Scope

| Enhancement | Priority | Notes |
|---|---|---|
| Controller refactoring | High | Split `app.php` into per-module controllers (`TicketController`, `FlowController`, etc.) |
| WebSocket/Server-Sent Events | Medium | Replace polling for real-time updates |
| PHPUnit test suite | High | Particularly for model methods, helper functions, and ticket lifecycle |
| Redis session driver | Medium | Required for horizontal scaling |
| Cloud file storage | Low | S3 adapter for attachments |
| Multi-tenant SMTP | Low | Per-project email configuration |
| API key hashing | Medium | Hash stored key; compare hash of incoming key |
| SLA breach reports | Medium | Scheduled CSV reports for management |
| Ticket merge/link | Low | Link related tickets together |
| Mobile app | Low | Progressive Web App shell |
| Two-factor authentication | Medium | TOTP for super_admin accounts |
| Rate limiting per endpoint | Low | Currently rate-limited globally per key; per-endpoint limits would be more granular |

### 19.3 Scalability Considerations

**Current architecture scales to:** ~50 concurrent users, ~10,000 active tickets, ~100 emails/minute.

**Bottlenecks at scale:**
1. `applyUserScope()` uses `JSON_CONTAINS()` on unindexed JSON columns — add generated columns + indexes for `l1_user_ids` at high ticket volumes
2. `app_settings` file cache is single-file on the filesystem — Redis cache would be faster
3. `tat_monitor.php` single-process — partition by project_id or flow_id for parallel processing
4. `notification_logs` table grows large — archive rows older than 90 days to separate history table

---

## 20. End-to-End Business Flow

### 20.1 Complete System Setup Flow (New Installation)

```
1. Install → schema.sql + setup_defaults.php
2. Configure SMTP in Settings
3. Create Projects (one per client/team)
4. Design Flows:
   a. Create flow, choose tat_level_count
   b. Add initial state (Triage): assign L1 operators, set L1 TAT
   c. Add process states (Investigation, Fix Applied): assign operators per level
   d. Add closing state (Resolved): no operators needed
   e. Configure backward transitions (Investigation → Triage for rework)
5. Create Alert Definitions (maps alert types to flows)
6. Add Escalation Matrix overrides for critical states
7. Generate API Keys (link to project; give key to monitoring system)
8. Create Users (operators, team leads)
9. Configure Roles and Module Permissions
10. Schedule tat_monitor.php cron
```

### 20.2 Complete Ticket Lifecycle

```
ALERT FIRED (External)
        │
        ▼
POST /api/raise (X-API-KEY)
        │
        ├── Validate key + project
        ├── Rate limit check
        ├── Create ticket (status=open, current_state=Initial, level=1)
        ├── Log to ticket_actions (created)
        ├── Queue emails to L1 pool → notification_logs (status=pending)
        └── Log to activity_logs
        
        CRON (within 1 minute):
        └── process_notification_queue() → send_email() to L1 operators

L1 OPERATOR RECEIVES EMAIL
        │
        ├── Logs in to pView
        ├── Opens ticket from "My Tickets" (it's in their state pool)
        └── Assigns to self (status → in_progress, TAT clock starts)

OPERATOR WORKS
        ├── Adds comments (+ @mentions to colleagues)
        ├── Uploads log files as attachments
        └── Moves state forward: Initial → Investigation
                │
                ├── Validates: caller is assignee
                ├── ticketMoveToState() with FOR UPDATE
                ├── state_entered_at = NOW()  (TAT clock resets)
                └── Notifies Investigation L1 pool

IF TAT BREACHES (cron detects):
        ├── Level 1 → Level 2: notify L2 supervisors
        ├── Level 2 → Level 3: notify engineering manager
        └── Level 3 → Level 4: ticket status = 'escalated', manager notified

RESOLUTION
        ├── Operator moves to closing state (Fix Applied → Verified → Resolved)
        ├── Operator clicks Resolve
        │   ├── status = 'resolved', resolved_at = NOW()
        │   └── Ticket frozen (no further edits)
        └── Manager closes ticket
            ├── status = 'closed', closed_at = NOW()
            └── Ticket permanently locked
```

### 20.3 Real-World Scenario — Database Server Outage

**Setup:**
- Project: "Production Infrastructure"
- Flow: "Database Incident" with states: Triage → RCA Investigation → Fix Applied → Monitoring → Resolved
- L1 pool: DBA team; L2 pool: Senior DBA + Dev Lead; L3 pool: CTO
- TAT: L1=15min, L2=30min, L3=60min

**Timeline:**

| Time | Event |
|---|---|
| 14:00 | Database monitoring detects outage; POST to /api/raise with alert_type=critical, priority=urgent |
| 14:00 | Ticket ALM-20260605-00001 created; L1 DBA team emailed |
| 14:01 | cron sends emails; bell badge lights up for all L1 operators |
| 14:03 | DBA Alice logs in, assigns ticket to herself; status → in_progress |
| 14:05 | Alice moves to "RCA Investigation"; adds comment "@bob can you check replication lag?" |
| 14:05 | Bob receives @mention email |
| 14:18 | L1 TAT (15 min) would have breached but Alice is working — no escalation since clock reset when she assigned |
| 14:45 | L1 TAT on "RCA Investigation" breaches (Alice has been investigating 40 min, TAT was 30 min) |
| 14:45 | cron escalates to L2; Senior DBA Carol and Dev Lead Dan emailed |
| 14:50 | Carol reviews; adds comment with analysis; moves ticket forward to "Fix Applied" |
| 15:00 | Fix applied; ticket moved to "Monitoring" |
| 15:30 | All stable; Bob resolves ticket; status → resolved |
| 16:00 | Manager reviews and closes; status → closed |

**Audit trail shows:** Every state move, every comment with timestamp, the escalation event at 14:45, who sent each email, all file attachments. Activity log records every action including which user viewed the ticket.

### 20.4 Flow Rework Scenario

A quality assurance team reviews a resolved ticket and finds the root cause analysis is incomplete. They:
1. Reopen the ticket (status → in_progress)
2. Send it backward: "Fix Applied" → "RCA Investigation" (requires reason: "Root cause not fully documented — needs additional analysis")
3. The backward transition is recorded in `state_transitions` and appears as a red dashed arrow in the workflow diagram
4. The RCA state's L1 pool is notified
5. Engineer completes the analysis, moves forward again
6. Ticket follows the normal close path

This rework path is visible in the ticket's full activity timeline — management can see how many tickets required rework and at which stage.

---

*This document reflects the pView Alert System codebase as of June 2026. All function references, table names, column names, and route paths are derived directly from the actual implementation files.*
