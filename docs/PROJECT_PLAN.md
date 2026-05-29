# pView Alert System — Project Plan

Author: (Developer)
Version: 1.0 (first draft)
Status: For your review

---

## A note before we start

This is the plan I want to follow for the pView Alert System. I have written
it the way I plan to actually do the work, in the order I plan to do it, so
you can see where each piece fits and tell me early if anything needs to
change.

I have kept it free of any specific dates. I'd rather agree on the order of
work first and slot the dates in once you approve, so we don't end up with a
plan that says "Tuesday" but the schedule has moved on.

The whole thing is just a Markdown file in the project repo. If you mark
anything up and send it back, I will edit those sections in place and bump
the version on top.

---

## What we are building, in plain words

A web tool the NOC team will use to manage alerts and tickets. Imagine Jira,
but stripped down to the things a NOC actually needs: projects → flows →
states → tickets, with auto-escalation when a level breaches its TAT.

Stack:

- **CodeIgniter 4** for the backend (PHP 8.1+ on the server, but the code
  itself runs on any PHP 8 — I avoided the newer 8.1-only syntax).
- **MySQL / MariaDB** for storage.
- **Bootstrap 5 + jQuery + DataTables** on the front-end. Nothing exotic.
- **PHPMailer** for SMTP notifications.
- One developer (me) building it. I'll demo to you at the end of each
  meaningful phase.

Two controllers, two models, one view file per major area. That's the whole
shape. I have kept the file count low on purpose — the next person to touch
this should be able to find anything they need by scrolling.

---

## How the work breaks down

Eight phases, in order. They're not equal in size — phase 3 (the actual
modules) is by far the biggest, and phases 7 and 8 are short. I'm being
honest about that here so you know what to expect.

1. Schema and database planning
2. Project setup and environment
3. Module development (the long one)
4. API integration (internal AJAX + external REST)
5. Frontend implementation
6. Testing and bug fixing
7. Production deployment
8. Review and documentation

I'll talk through each one below. Where it's just a list, I'll keep it as
a list. Where it needs a paragraph, I'll write one.

---

## Phase 1 — Schema and database planning

I want the tables locked down before I start typing PHP, because rewriting
the model layer halfway through is the most painful kind of rework.

The tables I need:

- **users** — operators of the system. Carries `role`, `is_active`,
  `deleted_at` (so deleted users don't break old foreign keys) and
  `password_changed_at` (so we can prompt people to rotate their
  password every 90 days).
- **projects** and **flows** — top-level grouping plus the workflow that
  lives under a project. Both get `deleted_at` so a soft-delete is
  reversible and doesn't orphan anything.
- **states** — the steps inside a flow. This one is heavy: `parent_state_id`
  for branching, `sort_order` for drag-to-reorder, `is_initial` /
  `is_final` flags, plus `l1_user_ids` … `l4_user_ids` (stored as JSON) and
  the matching `l1_tat_minutes` … `l4_tat_minutes`.
- **tickets** — the alerts themselves. The interesting columns are
  `alarm_id` (formatted `ALM-YYYYMMDD-XXXXX`, unique), `current_state_id`,
  `current_level`, `state_entered_at`, `status`, and `source` (`ui` or
  `api`).
- **ticket_actions** — the audit trail. Every comment, state change,
  escalation, assignment, attachment, resolve, close goes in here.
- **alert_definitions**, **escalation_matrix**, **api_keys**,
  **notification_logs**, **alarm_id_sequence** — supporting tables.

About `alarm_id_sequence`: it's a tiny per-day counter table. The helper
`generate_alarm_id()` does an INSERT…ON DUPLICATE KEY UPDATE, then reads the
counter back. That's the only safe way I know to get monotonic ids when
multiple requests can come in at the same second.

What I'll hand to you at the end of this phase: an ER diagram, a single
`database.sql` file that drops onto a fresh MySQL with seed data, and a
short note describing the soft-delete approach so it doesn't surprise you
later.

---

## Phase 2 — Project setup and environment

This is the boring but necessary part. Nothing here is interesting on its
own; it just has to be right or every later phase becomes harder.

I will:

- Pull CodeIgniter 4 in via Composer, plus PHPMailer for email.
- Create the `.env` from the example, set the DB credentials, the SMTP
  credentials, and `app.baseURL`.
- Set `app.indexPage = ''` and add an `.htaccess` at the project root that
  internally rewrites every request into `public/`. End result: clean URLs,
  no `index.php`, no `/public/` in the URL bar.
- Turn on `Session::regenerateDestroy = true` for session-fixation
  hardening.
- Drop all the front-end vendor files (Bootstrap, jQuery, DataTables,
  Select2, Toastr, SweetAlert2, Chart.js, Bootstrap Icons, Inter and
  JetBrains Mono fonts) under `public/assets/vendor/` so we don't depend
  on a CDN once we're in production.
- Create `app/Common.php` and have it `require_once` the helper file. That
  way every controller and view gets `check_isvalidated()`, the badge
  renderers, the password validator, etc. for free, without having to load
  the helper in each constructor.

By the end of this phase, hitting the project URL gives a working CI4
welcome page over HTTPS-ready clean URLs, and the developer can run
`php spark routes` to see everything wired up.

---

## Phase 3 — Module development

This is where most of the time will go. I plan to build the modules in this
order, because each one leans on the previous:

### 3.1 Auth (login, logout)

The login screen, login POST handler, and logout. The login model verifies
the credentials, logs the SQL it ran along with `num_rows` (same style we
use in pview_analytics — it's saved my time more than once when something
went wrong). On success, the session id is regenerated to prevent fixation
attacks. On failure, the email field is preserved so the user doesn't
retype it.

I'll add a small banner at the bottom of the login screen showing the
default super-admin credentials, but it's gated by `ENVIRONMENT !==
'production'` so it disappears the moment we deploy.

### 3.2 User management

Standard CRUD for the user table, admin-only. The interesting bit is the
password complexity check — `validate_password()` rejects anything under 8
characters, or without at least one letter and one digit. The same check
runs on create and update. Whenever a password is hashed,
`password_changed_at` is set.

Soft-delete: the delete button doesn't actually delete; it sets
`deleted_at` to now and `is_active` to 0. Every read filters
`WHERE deleted_at IS NULL`.

### 3.3 Projects, flows, states

CRUD for projects and flows is straightforward. The states screen is the
one that needs the most care. It's a single page with three regions:

1. A live preview of the workflow (the same node-graph component used on
   the ticket detail page).
2. A drag-to-reorder list of states. Reorder posts to
   `flows/reorder_states` over AJAX, and the model verifies that **every
   state id belongs to the target flow** before any UPDATE runs. Without
   that check, someone could reorder another flow's states by changing
   the payload in dev tools.
3. The "add new state" form: name, parent state (for branching),
   initial / final flags, and four levels of users (with Select2) plus
   their TAT in minutes.

### 3.4 Alert definitions, escalation, API keys

These are smaller modules.

- **Alert definitions** are reusable templates: name, description,
  severity, threshold, project, flow, list of users to notify.
- **Escalation matrix** lets an admin add custom rules per (flow, state,
  level). Has a small "add" form on the right and a list of existing
  rules with a delete button on the left.
- **API keys** are bound to a single project. The plain key is shown
  exactly once, right after generation. After that the table only shows
  the masked version.

### 3.5 Tickets — the heart of the system

This is the biggest sub-module by a margin. Operations that need
endpoints:

| What | Endpoint |
| --- | --- |
| Raise a ticket | `POST /tickets/save` |
| My tickets / All tickets | `GET /tickets`, `GET /tickets/all` |
| DataTables data feed | `GET /tickets/data_table` |
| Ticket detail | `GET /tickets/detail/{alarm_id}` |
| Inline action (comment, title edit, description edit, priority) | `POST /tickets/action/{alarm_id}` |
| Move to next state, assign, resolve, close | one POST endpoint each |
| Attach a file | `POST /tickets/attach/{alarm_id}` |
| Authenticated download | `GET /tickets/download/{alarm_id}/{action_id}` |

Two things deserve a paragraph each.

**Server-side pagination.** With ten thousand tickets, sending them all to
the browser is a mistake. So `/tickets/data_table` returns one page (25
rows by default) of JSON when DataTables asks for it. The browser only
ever holds 25 rows. Sorting, searching, and paging all round-trip to the
server. I'll cap page size at 200 in the model so a malicious client can't
ask for a million.

**Access checks.** Every ticket action goes through a private helper
`loadTicketOrFail()` that:

1. Validates the alarm id matches the strict regex `ALM-YYYYMMDD-XXXXX`
   (this also stops path-traversal in the upload directory).
2. Loads the ticket from the DB.
3. Calls `verify_ticket_access()` to confirm the logged-in user is either
   admin, the assignee, the raiser, or in one of the level user lists for
   the current state. Anyone else gets a 403.

Without this helper, any logged-in user could comment on / move / close
any ticket in the system by typing the alarm id in the URL.

### 3.6 TAT monitor (the cron job)

A standalone CLI script — `tat_monitor.php`. It's not part of the web
flow; it runs every minute via cron. The logic is small: walk every ticket
that's open or in-progress, and if `state_entered_at + Lₙ_tat_minutes` is
in the past, escalate to the next level. If the ticket is already at L4,
mark it `escalated` so the dashboard pulse-dot lights up.

Each escalation writes a row in `ticket_actions` and notifies the new
level's users.

---

## Phase 4 — API integration

We have two API surfaces. They sound similar but they aren't.

**Internal AJAX** is what the front-end uses to talk to its own backend.
The DataTables data feed, the inline ticket actions, the dependent flow
dropdown on the raise-ticket page, the drag-to-reorder endpoint. These all
sit behind session auth and return a tiny JSON envelope (`success`, `data`,
`message`).

**External REST API** is for outside systems — say, a network-monitoring
tool — to raise alerts into pView automatically. Auth is via an
`X-API-KEY` HTTP header. Every call validates the key against the
`api_keys` table, updates `last_used`, and (this is important) **rejects
payloads that don't match the project the key was issued for**. Otherwise
a key issued for project A could raise tickets in project B by changing
one number in the JSON.

Five endpoints, all flat: `/api/raise`, `/api/alert/{alarm_id}`,
`/api/alert/{alarm_id}/update`, `/api/alerts`, `/api/flows`. I will write
curl examples in the README so the integrator doesn't have to read code.

---

## Phase 5 — Frontend

The look I have in mind is "Grafana / Ericsson OSS" — sharp, data-dense,
clearly an operations tool. Sky-blue accents, dark navy sidebar, clean
off-white content area. I'll keep it minimal. Junior developers can read
the CSS later if they want; it's not magic.

Things worth flagging here:

**Light and dark theme.** Both. There is a sun/moon button in the topbar.
The trick is in the CSS — every colour is a CSS variable. Light mode
defines them under `:root`, dark mode redefines them under
`html[data-theme="dark"]`. JS only has to flip one HTML attribute. There
is also a tiny inline script in the `<head>` that reads the saved theme
from `localStorage` and applies it before the body parses, so users never
see a flash of the wrong colour while the page loads.

**Tables.** Every list page uses DataTables with `autoWidth: false`,
`scrollX: true`, and a CSS rule that puts `white-space: nowrap` on every
cell. This means: rows stay short (no wrapping), the table fills 100% of
its card, and if it's wider than the card it scrolls horizontally inside
the card instead of breaking the layout.

**Live TAT countdown.** A small JS function reads
`data-tat-expires` ISO timestamps and renders a colour-coded timer that
ticks every second. Re-bound after every DataTables redraw so it keeps
working when the user pages through the table.

**Topbar items, left to right:** breadcrumb, sun/moon toggle, alerts
bell (with a pulsing red dot when there are open critical tickets), the
user chip, and a sign-out button.

---

## Phase 6 — Testing

I treat this as its own phase rather than something I do alongside
development, because if you let testing slip into phase 3 it never gets
done properly.

What I plan to test:

- The full happy path. Login, create project, create flow, add states,
  raise a ticket, assign, move state, resolve, close. Every step shows
  up correctly in the activity timeline.
- Auto-escalation. Backdate `state_entered_at`, run `tat_monitor.php`,
  confirm `current_level` got bumped and the right users got an email.
- Attachments. Upload a real PDF — confirm it works. Upload a `.php`
  renamed to `.pdf` — confirm it's rejected.
- Theme toggle. Light → dark → reload → still dark.
- Password rotation. Backdate `password_changed_at`, log in as that
  user, confirm `password_must_rotate` is set in the session.
- Soft delete. Delete a project, confirm it disappears from the listing
  and dropdowns, but tickets that reference it still show its name.
- Security: CSRF on every form, file-upload mime check, alarm-id regex,
  cross-flow state reorder rejected, cross-project API call rejected
  with HTTP 403.
- Performance: the DataTables endpoint should return the first page in
  under 250ms even with 50k seed rows.

I will write a short test report (one page) listing each scenario as
pass / fail and hand it to you before we go to deployment.

---

## Phase 7 — Production deployment

Once you've signed off on the test report, deployment itself is a checklist
job. The bullet points:

- Linux box, Apache 2.4 with `mod_rewrite`, PHP 8.1+, MySQL 8 or
  MariaDB 10.5+. SSL cert from Let's Encrypt.
- Clone the repo, `composer install --no-dev --optimize-autoloader`.
- Production `.env`: `CI_ENVIRONMENT=production`, real DB credentials,
  real SMTP, `app.baseURL=https://<domain>/`.
- Run `database.sql` on a fresh schema (or just the soft-delete and
  password-rotation `ALTER TABLE` statements if migrating from staging).
- Apache vhost: document root is the project root. The root `.htaccess`
  rewrites internally into `public/`.
- Cron entry, every minute:
  ```
  * * * * * php /var/www/pview-alert-system/tat_monitor.php >> /var/log/pview/tat.log 2>&1
  ```
- Daily `mysqldump` (30-day retention), weekly `rsync` of the uploads
  directory (90-day retention).
- Logrotate for the Apache error log so it doesn't fill the disk.

Plan to do this on a Saturday morning so we have a quiet period to verify
everything before Monday. After cutover, I'll watch
`grep "pview alert" /var/log/apache2/error.log` for the first hour to
confirm the application is producing the breadcrumbs we expect.

---

## Phase 8 — Review and documentation

Five documents will live alongside the code in the repo:

1. **README.md** — for any developer joining later. High-level project
   map and setup steps.
2. **API_DOCS.md** — curl examples for each REST endpoint, request and
   response shapes.
3. **OPERATIONS_RUNBOOK.md** — for sysadmin / on-call. How to start
   MySQL, where the logs are, how to run `tat_monitor.php` by hand, how
   to restore from backup.
4. **ADMIN_USER_GUIDE.md** — for the NOC team leads. Screenshot-driven
   walkthrough of managing projects, flows, states, users, alerts, and
   raising tickets.
5. **PROJECT_PLAN.md** — this file.

I'd like five short demos with you, one after each meaningful phase:

- After phase 1: schema sign-off.
- After phase 3: end-to-end happy-path demo.
- After phase 5: UI walkthrough (you'll want to see the theme toggle).
- After phase 6: walk you through the test report.
- After phase 7: production smoke test, you watch the logs with me.

After phase 7's smoke test, we hand over formally to the NOC team lead.

---

## Things that might go wrong

A short, honest list. Better to surface these now than discover them halfway
through.

- **Production PHP version.** The new server might be on PHP 8.0, not 8.1.
  The code is written so that PHP 8.0+ runs it without changes. I'll verify
  the version on day one of phase 7.
- **SMTP not ready by phase 3.** The notification calls are wrapped in
  `try { … } catch (\Throwable $e)` so a failed send never breaks the
  ticket flow. The failure gets logged to `notification_logs.status =
  'failed'`. So we can develop without working SMTP and turn it on later.
- **Volume.** Server-side pagination is in place from day one, so even a
  million tickets won't slow the listing pages. The N+1 query that crept
  into the early `getMyTickets` is already fixed.
- **Scope creep.** If the manager wants extra columns on the ticket
  table, I'll add them in the model + the consolidated view in one focused
  change. Each model method reads / writes a plain associative array, so
  this is genuinely cheap to do — but it's still my time, so I'll flag it
  rather than just absorbing it silently.

---

## How to revise this document

If you want changes, mark up the section you want changed and send it
back. I'll edit that section in place, bump the version on the top line
(1.0 → 1.1, etc.) and add one line to the changelog below describing what
changed.

### Changelog

| Version | What changed |
| --- | --- |
| 1.0 | First draft, sent for review. |

---

## Sign-off

When you're happy with the plan, please sign here and send it back. That's
the green light for me to start phase 1.

| Role | Name | Signature | Date |
| --- | --- | --- | --- |
| Developer |  |  |  |
| Manager |  |  |  |

---

*That's it. Happy to walk you through any of the phases on a call before
you sign off.*
