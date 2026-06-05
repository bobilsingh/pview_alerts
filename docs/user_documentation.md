# pView Alert System — User Guide

This guide covers every feature of pView from the perspective of someone using the system day to day. It is written for operators, team leads, administrators, and anyone being trained on the platform. You do not need technical knowledge to follow this guide — it explains what every screen does, what to fill in, and what to expect.

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Logging In](#2-logging-in)
3. [Navigation & Layout](#3-navigation--layout)
4. [Dashboard](#4-dashboard)
5. [Projects](#5-projects)
6. [Flows — Workflow Designer](#6-flows--workflow-designer)
7. [Alert Definitions](#7-alert-definitions)
8. [Escalation Matrix](#8-escalation-matrix)
9. [Tickets — My Tickets & All Tickets](#9-tickets--my-tickets--all-tickets)
10. [Creating a Ticket](#10-creating-a-ticket)
11. [Working Inside a Ticket](#11-working-inside-a-ticket)
12. [Users](#12-users)
13. [Roles](#13-roles)
14. [Module Control Panel](#14-module-control-panel)
15. [API Keys](#15-api-keys)
16. [Settings](#16-settings)
17. [Activity Log](#17-activity-log)
18. [Cron Panel](#18-cron-panel)
19. [Personal Preferences](#19-personal-preferences)
20. [Notification Preferences](#20-notification-preferences)
21. [Maintenance Mode](#21-maintenance-mode)
22. [Quick Reference — Ticket Statuses, Badges & Buttons](#22-quick-reference)

---

## 1. Introduction

**pView Alert System** is an operations tool designed for Network Operations Centre (NOC) teams. Its purpose is to make sure that every alert raised — whether from a monitoring tool or manually by an operator — is tracked, assigned, worked on, and resolved within defined time limits.

The system works around three core ideas:

**Projects** are the top-level containers. A project usually represents a client, a team, or a business unit.

**Flows** are the stages a ticket moves through — like a workflow. For example, a ticket might go through Triage → Investigation → Fix → Verification → Closed.

**Tickets** are the individual alerts or incidents. Every ticket follows a flow, is worked on by operators assigned to each stage, and automatically escalates if nobody acts within the configured time.

### Who uses pView?

| Role | What they do |
|---|---|
| **Operator (user role)** | Receives, works on, and resolves tickets assigned to them |
| **Admin** | Manages operators, can see all tickets across all projects |
| **Super Admin** | Has full access including Settings, Roles, and all system configuration |

Each role can be fine-tuned further — an Admin can be restricted from certain modules, and custom roles can be created. This is covered in the [Roles](#13-roles) and [Module Control Panel](#14-module-control-panel) sections.

---

## 2. Logging In

### Where to go

Open a web browser and navigate to the URL your administrator has set up (for example, `https://pview.yourcompany.com`). You will see the Sign In page.

### What you will see

- An **"Authenticate with your operator credentials"** message
- A **User ID or Email** field
- A **Password** field with a show/hide toggle
- A **Sign In** button

### How to log in

1. Enter your **User ID** (for example `jdoe`) or your **email address** (for example `jdoe@company.com`)
2. Enter your **password**
3. Click **Sign In**

If your credentials are correct, you will be taken to the first page your role can access. This is usually the Dashboard.

### Login errors and restrictions

**Wrong credentials:** The page will show a red error message — "Invalid credentials." Your username and password are not stored anywhere on the page. Simply try again.

**Too many failed attempts:** After a certain number of failed login attempts (typically 3), you will be locked out for a set number of minutes (typically 10 minutes). The message will say something like "Too many failed attempts. Try again in 10 minute(s)." You do not need to contact anyone — just wait and try again.

**Caps Lock warning:** If your Caps Lock key is on, a small warning appears below the password field. This is a reminder because many password failures happen because of accidental Caps Lock.

**Maintenance mode:** If the system is in maintenance mode, you will see a maintenance page instead of the dashboard. If you have admin-level access, you may see an option to disable maintenance mode on that page. Regular operators must wait for an administrator to turn it off.

### First login — password change

If your administrator has set up your account and you are logging in for the first time, or if your password has not been changed for a long time, you may be taken to a **Change Password** screen instead of the dashboard. You must complete this before you can access anything else.

Fill in:
- **Current password** — the password you just used to log in
- **New password** — your chosen new password (must meet the configured requirements, typically minimum 8 characters with at least one letter and one digit)
- **Confirm password** — type the new password again exactly

Click **Change Password**. You will then be redirected to the dashboard.

---

## 3. Navigation & Layout

### The sidebar

The sidebar on the left side of every page is your main navigation. It is organised into sections:

| Section | Menu items |
|---|---|
| **Overview** | Dashboard |
| **Configuration** | Projects, Flows, Alert Defs, Escalation |
| **Operations** | My Tickets, Raise Ticket, All Tickets |
| **System** | Users, API Keys, Activity Log, Cron Panel |
| **Administration** | Roles, Settings, Manage Module |

> **Note:** You only see menu items that your role has permission to access. If a menu item is missing, it means your role does not have access to that page. Contact your administrator.

### Collapsing the sidebar

Click the toggle button in the top-left corner of the screen. On a desktop, the sidebar collapses to show only icons with tooltips on hover. Click again to expand it. On a mobile device, the sidebar opens as a sliding drawer from the left.

### The topbar

The bar at the top of the screen contains:

- **Breadcrumb** — shows your current location, for example `Home > Tickets`
- **Theme toggle** — click the sun/moon icon to switch between dark and light mode. Your preference is saved automatically
- **Bell icon** — shows a red badge with the number of actionable tickets (critical or escalated). Click it to see a dropdown list of the most urgent tickets
- **Your name and avatar** — your initials appear as an avatar. Click to see your name and role
- **Logout** — the door/arrow icon next to your name logs you out

### Flash messages

When you save something or an action completes, a message appears at the top of the page:

- **Green message** — success. The action worked
- **Red message** — error. Something went wrong. The message describes what happened

Messages disappear automatically after a short time, or you can click the × to close them.

### Dark and light mode

Use the moon/sun toggle in the topbar to switch themes. Your choice is remembered even after you log out.

---

## 4. Dashboard

**Purpose:** The dashboard gives you an immediate picture of what is happening right now — how many tickets are open, whether any are escalated, and how the alert volume has been trending over time.

### What you see

When you log in, the dashboard loads automatically. Here is what each section means:

#### KPI Cards

Four cards across the top of the page:

| Card | What it shows |
|---|---|
| **Open Tickets** | Total number of tickets currently open or in progress or escalated — i.e., tickets that need attention |
| **Critical** | Active tickets marked as Critical severity |
| **Major** | Active tickets marked as Major severity |
| **Resolved** | Total count of all resolved tickets |

Clicking any of these cards takes you to the ticket list filtered to show those tickets.

#### Ticket Trend Chart

A line chart showing how many tickets were raised each day over the last set number of days (default: last 7 days). This helps you spot patterns — for example, if ticket volume spikes on certain days or after specific events.

At the top of the chart, you will see buttons like **7**, **15**, **30** — click these to change the time range without leaving the page. The chart updates instantly.

#### Severity Mix (Doughnut Chart)

A circular chart showing the proportion of active tickets by severity: Info, Major, and Critical. The number in the centre shows the total.

#### Active Tickets Table

Below the charts, a small table shows the five most urgent open tickets. These are sorted by urgency — escalated tickets appear first, then in-progress, then open. Each row shows the alarm ID, title, priority, severity, status, current state, and TAT countdown.

### Project filter

If you manage multiple projects, your dashboard shows data for all of them combined. If you want to focus on one project, click the **Customize** button (top right of the dashboard) and set a default project. See [Personal Preferences — Dashboard](#19-personal-preferences) for full instructions.

When a project filter is active, you will see a small note at the top of the dashboard: **"Filtered to project: ProjectName"** with a "change" link.

### What admins see vs regular users

**Regular users (non-admin scope):** See only tickets they are directly involved with — tickets they raised, tickets assigned to them, or tickets in a state where they are in the user pool.

**Admin-scope users:** See all tickets across the entire system.

---

## 5. Projects

**Purpose:** Projects are containers that organise everything else. Each project holds its own flows, alert definitions, API keys, and tickets. Think of a project as a client, a product, or a team.

### Viewing projects

Go to **Projects** in the sidebar. You will see a table listing all projects with the following columns:

- **Name** — the project name
- **Description** — a short description (if provided)
- **Status** — Active or Inactive
- **Created By** — who created the project
- **Created** — creation date
- **Actions** — Edit and Delete buttons

### Creating a project

1. Click **Add Project** (top right of the page)
2. Fill in:
   - **Name** (required) — a clear, descriptive name like "Infrastructure Monitoring" or "Client ABC"
   - **Description** (optional) — more detail about what this project covers
3. Click **Create**

The project is created with **Active** status and appears in the list immediately.

**Validation:** If a project with the same name already exists, you will see an error: "A project with that name already exists." Use a different name.

### Editing a project

1. Click the **Edit** button on any project row
2. Change the name, description, or status
3. To deactivate a project, set **Status** to **Inactive**
4. Click **Update**

### Deleting a project

Click the **Delete** (trash) button. A confirmation dialog appears. Click **Yes** to confirm.

**What happens when a project is deleted:**
- The project is marked as deleted (it does not immediately disappear from the database)
- All flows belonging to this project are also deactivated
- All alert definitions linked to this project are deactivated
- All API keys linked to this project are deactivated
- Existing tickets are not affected — they remain visible in ticket history

> **Warning:** You cannot undo a deletion from the UI. Deactivating a project is safer if you might need it again.

### Real example

Your company manages alerts for three clients. You create three projects:
- **"Client Alpha - Infrastructure"**
- **"Client Beta - Application"**
- **"Internal NOC - General"**

Each client gets their own flows, escalation rules, and operators. Tickets raised for Client Alpha stay completely separate from Client Beta.

---

## 6. Flows — Workflow Designer

**Purpose:** A flow defines the stages a ticket travels through from creation to resolution. Each stage (called a **state**) has its own team of operators and time limit. When a ticket enters a state, the clock starts. If nobody acts before the time limit, the ticket escalates automatically.

### What is a flow?

Think of a flow as a checklist of stages that every ticket must go through. A simple flow might look like:

```
Triage → Investigation → Fix Applied → Verification → Closed
```

A ticket starts at "Triage", an operator works on it, then moves it to "Investigation", and so on. Each stage has operators assigned to it, and each stage has a time limit.

### Viewing flows

Go to **Flows** in the sidebar. You will see a table with:

- **Name** — flow name
- **Project** — which project this flow belongs to
- **States** — how many states are in this flow
- **Status** — Active or Inactive
- **Created By / Created**
- **Actions** — Edit, States (to manage the stages), and Delete

### Creating a flow

1. Click **Add Flow**
2. Fill in:
   - **Project** (required) — select the project this flow belongs to
   - **Flow Name** (required) — a descriptive name like "Standard Infrastructure Flow" or "Critical Incident Flow"
   - **TAT Level Count** — choose how many escalation levels this flow uses (1, 2, 3, or 4). This controls how many times a ticket automatically escalates before being flagged as a final escalation
3. Click **Create**

**What TAT Level Count means:**
- If you choose **L2**, a ticket at L1 escalates to L2 if time runs out. If L2 also runs out, the ticket is flagged as `escalated` (requires admin attention).
- If you choose **L4**, the ticket goes through L1 → L2 → L3 → L4 before being flagged.

Most teams use L2 or L3 for standard flows and L4 only for complex or high-priority flows.

### Adding and managing states

After creating a flow, click the **States** button on the flow list. This takes you to the workflow designer page.

The page has two main areas:
- **Workflow diagram** on the left — a live visual of your flow
- **States panel** on the right — the list of all states with an add/edit form below

#### Adding a state

Fill in the form at the bottom of the states panel:

1. **State name** (required) — for example "L1 Triage" or "Investigation"
2. **Initial state** (checkbox) — check this for the very first state that a new ticket enters. Every flow must have exactly one initial state
3. **Closing state** (checkbox) — check this for the last stage before the ticket is considered done. Every flow must have exactly one closing state
4. **Allowed Backward States** — if you want operators to be able to send a ticket *back* to a previous stage (for rework), select those earlier states here. For example, "Investigation" might allow sending back to "Triage"
5. **L1 Users** — select the operators who will handle tickets at escalation level 1
6. **L1 TAT (min)** — how many minutes before this state escalates to L2
7. **L2 Users** and **L2 TAT (min)** — operators and time limit for level 2 (repeat for L3, L4 as needed)

Click **Add State**.

**Validation:**
- Every flow needs at least one initial state and one closing state
- If you try to create a state that is both initial and closing, you will see: "A state cannot be both initial and final"
- If a state has no L1 users, a yellow warning "No L1 pool" appears — this means no one will be notified when a ticket enters this state. This is allowed but not recommended

#### Reordering states

The order of states determines the default flow. Drag any state row up or down using the grip handle on the left. The order saves automatically after you release.

The workflow diagram on the left updates in real time as you reorder or add states.

#### Editing a state

Click the **Edit** button on any state row. The form at the bottom populates with the current values. Make your changes and click **Update State**.

#### Deleting a state

Click the **Delete** button on any state row.

**Restriction:** You cannot delete a state that:
- Has active tickets currently sitting in it
- Has other states connecting to it via forward transitions

If either condition applies, you will see an error explaining why. Move or close any tickets in that state first, then remove any transition that points to it before deleting.

### Forward and backward transitions

By default, states flow in the order they are sorted (top to bottom). This is the forward path.

**Backward transitions** are optional send-back paths. For example, if an operator in "Investigation" finds that more information is needed, they can send the ticket back to "Triage". You configure which states are valid backward targets when creating or editing a state (the **Allowed Backward States** field).

Backward transitions always require the operator to type a reason. This is enforced by the system and cannot be bypassed.

### Reading the workflow diagram

The diagram shows each state as a coloured box:

| Colour | Meaning |
|---|---|
| **Green** | Initial state (entry point) |
| **Purple** | Process state (regular stage) |
| **Grey** | Final/closing state |
| **Blue arrows** | Forward transitions |
| **Red dashed arrows** | Backward (send-back) transitions |

Use the zoom and fit buttons in the diagram toolbar to adjust the view. There is also a fullscreen button to see the entire flow at once.

### Real example

**Project:** Infrastructure Monitoring  
**Flow:** Standard Incident Flow  
**States:**

| State | TAT Level Count | L1 Users | L1 TAT | L2 Users | L2 TAT |
|---|---|---|---|---|---|
| Triage | L2 | alice, bob | 30 min | carol, manager | 60 min |
| Investigation | L2 | alice, bob, dave | 60 min | carol, manager | 120 min |
| Fix Applied | L2 | alice, bob | 30 min | carol, manager | 60 min |
| Verification | L2 | qa_team | 45 min | manager | 90 min |
| Closed | — | — | — | — | — |

A ticket enters Triage. If alice or bob do not act within 30 minutes, the ticket escalates to L2 and carol and manager are notified. If 60 more minutes pass without action, the ticket status becomes `escalated` — a red alert visible to all admins.

---

## 7. Alert Definitions

**Purpose:** Alert definitions are templates that connect a type of alert to a specific project flow. When an external monitoring system sends an alert via the API, it references a project and a flow. The alert definition holds the default notification list and other metadata for that alert type.

Alert definitions are mainly used with the API integration. They can also be referenced when creating tickets manually.

### Viewing alert definitions

Go to **Alert Defs** in the sidebar. The table shows:

- **Name** — alert definition name
- **Project** — associated project
- **Flow** — the flow tickets created from this alert will use
- **Severity** — default severity level (Info, Major, Critical)
- **Threshold** — optional threshold value and unit (e.g., "90 %" or "500 ms")
- **Active** — whether this definition is currently active
- **Actions** — Edit and Delete

### Creating an alert definition

1. Click **Add Alert**
2. Fill in:
   - **Name** (required) — a clear name, for example "CPU Over 90%" or "API Response Timeout"
   - **Severity** — Info, Major, or Critical
   - **Description** (optional) — what this alert means
   - **Project** (required) — which project this belongs to
   - **Flow** (required) — which flow tickets should follow
   - **Threshold Value** (optional) — a numeric value, for example "90"
   - **Threshold Unit** (optional) — the unit, for example "%" or "ms" or "errors/min"
   - **Notify Users** — users who should always receive a notification when this alert type fires
3. Click **Create**

### Editing and deleting

Click **Edit** to update any field. Click **Delete** to deactivate the definition (tickets already created using this definition are not affected).

### Real example

You have a monitoring tool that fires an alert whenever CPU usage on a server exceeds 90%. You create an alert definition:

- **Name:** High CPU Usage
- **Severity:** Major
- **Project:** Infrastructure Monitoring
- **Flow:** Standard Incident Flow
- **Threshold:** 90 %
- **Notify Users:** Team Lead

When the monitoring tool fires this alert and sends it to pView via the API, a ticket is automatically created in the "Standard Incident Flow" at the Triage stage, and Team Lead receives an email immediately.

---

## 8. Escalation Matrix

**Purpose:** The escalation matrix lets you set different escalation rules for specific states and levels. This overrides the TAT settings configured in the flow states.

For example, your standard flow might have a 60-minute L1 TAT for all states. But for the "Critical Investigation" state, you want escalation to happen in just 15 minutes because it is high priority. The escalation matrix handles this without changing the flow itself.

### Viewing the escalation matrix

Click **Escalation** in the sidebar. The page is split into two parts:

- **Existing rules** (left table) — all override rules currently configured
- **Add new rule** (right form) — where you add a new override

### Existing rules table

| Column | Meaning |
|---|---|
| Flow | Which flow this rule applies to |
| State | Which state within that flow |
| Level | Which escalation level (L1, L2, L3, or L4) |
| Escalate after (min) | Override TAT in minutes |
| Severity | Severity to treat this escalation as |
| Actions | Delete button |

### Adding a new rule

1. Select the **Flow**
2. The **State** dropdown will load automatically — select the specific state
3. Select the **Level** (L1, L2, L3, L4)
4. Set **Escalate after (min)** — how many minutes before escalation triggers
5. Select the **Severity** — what severity level to flag the escalation notification as
6. Select **Notify users** — who to notify when this specific escalation fires
7. Click **Add rule**

The new rule appears in the table immediately.

### Deleting a rule

Click the **Delete** button on the rule row. The state falls back to using the TAT configured directly in the flow states.

### Real example

Your "Standard Incident Flow" has "Critical Investigation" state with L1 TAT of 60 minutes. But your SLA for critical incidents requires a 15-minute escalation. You add an escalation matrix rule:

- **Flow:** Standard Incident Flow
- **State:** Critical Investigation
- **Level:** L1
- **Escalate after:** 15 minutes
- **Severity:** Critical
- **Notify users:** Engineering Lead, On-Call Manager

Now, when any critical ticket sits in "Critical Investigation" without L1 action for 15 minutes, the escalation fires — regardless of the 60-minute default on that state.

---

## 9. Tickets — My Tickets & All Tickets

### My Tickets

Go to **My Tickets** in the sidebar. This page shows all tickets you are directly connected to:

- Tickets **you raised**
- Tickets **assigned to you**
- Tickets where you are **in the state's user pool** (even if not directly assigned)

This is your personal work queue. The system ensures you only see what is relevant to you.

### All Tickets (admin view)

If your role has admin scope, you will also see **All Tickets** in the sidebar. This shows every ticket in the system across all projects.

### Understanding the ticket list

**Column by column:**

| Column | What it means |
|---|---|
| Checkbox | Select ticket for bulk action |
| Alarm ID | Unique identifier like `ALM-20260605-00042` |
| Title | The ticket title |
| Severity | Info / Major / Critical (coloured badge) |
| Priority | Low / Medium / High / Urgent (coloured badge) |
| State | Which workflow stage the ticket is currently in |
| Level | Current escalation level (L1, L2, L3, or L4) |
| Assignee | Who is currently assigned to this ticket |
| TAT | Time remaining before the next escalation (countdown timer) |
| Created | When the ticket was created |
| Actions | Quick action buttons |

The **TAT countdown** changes colour:
- **Normal** — plenty of time remains
- **Orange/Warning** — less than 25% of the time window remains
- **Red/Breached** — time has run out; escalation will fire on the next cron run (once per minute)

### Filtering tickets

The filter panel appears above the ticket table. Click the **Filters** header to expand or collapse it.

**Available filters:**

| Filter | Options |
|---|---|
| Search | Type any text to search by Alarm ID or ticket title |
| Severity | All, Info, Major, Critical |
| Priority | All, Low, Medium, High, Urgent |
| Project (All Tickets only) | Filter to one project |
| Flow (All Tickets only) | Filter to one flow |
| Date range | From and To dates for when the ticket was created |

**Status filter pills** appear as clickable buttons above the table:

- **All** — no status filter
- **Active** — shows Open, In Progress, and Escalated tickets together
- **Open** — not yet assigned
- **In Progress** — assigned and being worked on
- **Escalated** — TAT breached at the highest level; needs admin attention
- **Resolved** — completed
- **Closed** — finalised

Click any pill to filter instantly. The selected pill is highlighted.

After applying filters, click **Apply** in the filter panel. To clear all filters and return to the full list, click **Reset**.

### Saving a filter

If you regularly use the same combination of filters, you can save it:

1. Set your filters the way you want them
2. The **Save current filter…** button appears above the filter form (it hides when no filters are active)
3. Click it
4. A prompt appears asking for a name — type something like "My Major Tickets" or "Client Alpha Critical"
5. Click OK

Your saved filter appears in the **Saved** dropdown at the top of the page. Click it any time to apply that combination instantly.

To delete a saved filter, open the **Saved** dropdown and click the × next to the filter name.

### Bulk actions

To act on multiple tickets at once:

1. Check the box on the left of each ticket row. Or use the checkbox in the table header to select all visible tickets
2. The **bulk toolbar** appears at the top of the page showing how many tickets are selected
3. Click **Resolve selected** or **Close selected**
4. A confirmation dialog appears — confirm to proceed

The action is applied to all selected tickets. A summary appears showing how many were processed, skipped (already in the target status), or failed.

> **Note:** Resolved and closed tickets are automatically skipped in bulk actions — the system only applies the action where it makes sense.

### Exporting tickets

Click **Export CSV** (appears in the table length selector area) to download the current filtered list as a CSV file. The export respects all active filters — you get exactly what you see on screen.

---

## 10. Creating a Ticket

**Purpose:** Manually raise a new alert or incident that is not coming in through the API.

### How to create a ticket

1. Click **Raise Ticket** in the sidebar (or click **My Tickets** and then the **Raise Ticket** button on that page)
2. Fill in the form:

**Required fields:**

- **Project** — select the project this ticket belongs to. Once selected, the Flow dropdown loads automatically
- **Flow** — select the workflow this ticket should follow. The system validates that the flow belongs to the selected project
- **Title** — a short, clear description of the issue (max 300 characters). A character counter shows how many characters you have used. Example: "High CPU usage on web-server-01"

**Optional fields:**

- **Description** — detailed explanation of the issue (max 10,000 characters). Include any relevant context, error messages, or observations
- **Severity** — Info, Major, or Critical. This determines how visually prominent the ticket is and which notification rules fire
- **Priority** — Low, Medium, High, or Urgent. Default is Medium
- **Assign To** — optionally assign the ticket to a specific operator right now. The dropdown only shows operators who are in the initial state's L1 pool. If you leave this blank, the ticket sits as "Open" until an operator picks it up or is assigned
- **Attachment** — attach a file (screenshot, log file, report). Allowed types and max file size are set by the administrator. After uploading, the attachment becomes part of the ticket history
- **Start Date / End Date** — the actual dates the issue started and ended (if known). This is informational and used for reporting

3. Click **Raise Ticket**

### What happens after creation

- A unique **Alarm ID** is generated in the format `ALM-YYYYMMDD-NNNNN` (e.g., `ALM-20260605-00042`)
- The ticket is placed in the **initial state** of the selected flow
- If you set an assignee, the ticket status becomes **In Progress** and that operator receives an email notification
- If no assignee was set, the status is **Open** and all operators in the initial state's L1 pool receive an email notification
- You are redirected to the ticket detail page

### Duplicate detection

If another open ticket with the same severity already exists in the same project and was raised within the last 24 hours, a yellow warning appears:

> "Possible duplicate: 2 open ticket(s) with the same type already exist in this project in the last 24h — ALM-20260605-00040, ALM-20260605-00041"

The ticket is still created — this is just a warning to help you avoid creating duplicates accidentally.

### Real example

An operator at a NOC receives a phone call about a server outage. They open pView and raise a ticket:

- **Project:** Infrastructure Monitoring
- **Flow:** Standard Incident Flow
- **Title:** web-server-01 is unreachable — connection timeout
- **Description:** "Customer reported website is down at 14:32. Checked from monitoring dashboard — web-server-01 not responding to ping. Last successful check was 14:28."
- **Severity:** Critical
- **Priority:** Urgent
- **Assign To:** alice (who is on duty)

The ticket is created. Alice receives an email saying she has been assigned a Critical ticket. The TAT clock starts counting down from the configured L1 time limit.

---

## 11. Working Inside a Ticket

Click on any Alarm ID in the ticket list to open the ticket detail page. This is where all the action happens.

### Ticket header

At the top of the page, you see:

- The **Alarm ID** in a code chip — click the copy icon next to it to copy it to your clipboard
- The **project, flow, and creation date**
- **Severity badge** and **status badge**

### Ticket Details card

The main card shows:

- **Title** — click on it (on an active ticket) to edit it inline. Type your change and click Save, or press Escape to cancel
- **Description** — click to edit inline the same way
- **Workflow diagram** — the flow is shown as an interactive diagram. The current state is highlighted in blue. Completed states are green. Remaining states are grey

### TAT and escalation level

On the right side of the detail card, you see:

- **Escalation Level** indicator — shows L1, L2, L3, L4 as steps. The current level is highlighted
- **TAT countdown** — shows the time remaining at the current level before the next escalation. Example: "TAT remaining @ L2 — 00:47:23". The colour changes to orange when time is running low and red when it has expired

### The Take Action panel

This panel has four tabs:

#### Comment tab

Write a note about this ticket. Any team member with access to the ticket can add comments. Comments are permanent — they cannot be edited or deleted.

**Using @mentions:** If you want to notify a specific person by email about your comment, type `@` followed by their User ID. For example, `@alice`. A dropdown list appears showing matching users. Click a name to select it, or keep typing to narrow the list. Press Tab or Enter to insert the mention.

After posting, Alice will receive an email saying you mentioned her in the ticket, with the comment text included.

> **Note:** You can only mention active users in the system. Mentions go to real people — make sure you are typing the correct User ID.

**Adding a comment:**
1. Type your comment in the text area
2. Optionally add `@mentions`
3. Click **Add comment**

#### Assign tab

Assign the ticket to an operator. The dropdown shows operators who are in any of the current state's user pools (L1 through L4).

1. Select an operator from the **Assign To** dropdown
2. Click **Assign**

**What happens:**
- The ticket status changes to **In Progress**
- The selected operator receives an email notification
- The assignment is recorded in the activity timeline

**Moving the ticket to a different state:** If the ticket is moved to a new state and the current assignee is not in that state's L1 pool, the assignee is automatically cleared and the status goes back to Open.

#### Move State tab

This tab lets you move the ticket forward through the flow or send it backward for rework.

**Moving forward:**

If the current state has a clear next state, you will see a **Move Forward** button or a dropdown to select the target state. Click the button or select the target and click Move Forward.

Some transitions require a comment — if a forward transition has been configured to require a reason, a "Reason" text field appears. Fill it in before you can proceed.

**Sending back:**

If the current state has configured backward transitions (send-back paths), you will see a **Send Back** section with a dropdown showing valid previous states. Select where to send the ticket, type a reason (required for all backward moves), and click **Send Back**.

**Who can move a ticket?**
- The operator currently **assigned** to the ticket
- Any user with **admin scope** (admin or super_admin role)

If you try to move a ticket you are not assigned to and do not have admin scope, you will see an error: "Only the assigned operator or an admin can move the ticket state."

**Resolving a ticket:**

Click **Resolve**. A confirmation dialog appears. This marks the ticket as **Resolved** and records the resolved timestamp. The ticket can still be reopened if needed.

**Closing a ticket:**

Click **Close**. This marks the ticket as **Closed**. A closed ticket is final — no further actions (comments, moves, attachments, or reassignments) are possible.

**Reopening a ticket:**

If a ticket is **Resolved** (not Closed), you will see a **Reopen** button. Click it to revert the ticket to Open or In Progress (depending on whether it still has an assignee). A Resolved ticket cannot be reopened by default — only the assigned operator or admin-scope users can reopen.

#### Attach tab

Upload a file attachment to the ticket.

1. Click **Choose file** and select a file
2. Click **Upload**

**Restrictions:**
- Maximum file size is set by the administrator (default 10 MB)
- Only certain file types are allowed (PDF, Word, Excel, images, CSV, text). If you try to upload a blocked type, you will see an error
- A maximum of 5 attachments are allowed per ticket. The page shows the current count (e.g., "2 / 5 attached")

### Activity timeline

Below the action tabs, the full history of everything that has happened to this ticket appears in reverse chronological order (newest first):

| Event type | What it means |
|---|---|
| **Created** | When the ticket was first raised |
| **Commented** | A comment was added |
| **State changed** | The ticket moved from one state to another |
| **Level escalated** | The TAT timer ran out and the ticket bumped to the next level |
| **Assigned** | Someone was assigned to the ticket |
| **Attachment** | A file was uploaded — click the filename to download it |
| **Resolved / Closed / Reopened** | Status change events |
| **Title changed / Description changed / Priority changed** | Inline field edits |

### Ticket details sidebar

A side panel shows metadata about the ticket:

- **Project, Flow, State** — current location in the system
- **Status, Severity, Priority** — current flags
- **Assignee** — current assigned operator (or — if unassigned)
- **Source** — "UI" if raised manually, "API" if raised by an external system
- **Raised By** — who created the ticket
- **Actual Start / Actual End** — optional date tracking
- **Created / Updated** — system timestamps

### Notifications panel

Below the timeline, a small panel shows the most recent 5 email notifications that were sent for this ticket. For each notification:

- **Recipient** — the email address the notification was sent to
- **Status** — SENT (green), PENDING (grey, not yet sent), or FAILED (red)
- **Timestamp** — when it was sent or queued

If a notification shows FAILED, the system will have retried it several times. Contact your administrator if emails are consistently failing.

### Real example — complete ticket walkthrough

**Situation:** A monitoring system raises a Critical ticket via the API. Alarm ID: ALM-20260605-00042. Title: "Database connection pool exhausted on db-primary-01"

1. Alice, who is in the L1 pool for the Triage state, receives an email
2. Alice opens the ticket and reads the description
3. Alice goes to the **Assign** tab and assigns the ticket to herself
4. Alice adds a comment: "Checking connection pool config. @bob can you check the application server logs?"
5. Bob receives an email notification because Alice mentioned him
6. Bob finds the issue and adds a comment: "Found the problem — a batch job is not releasing connections. Killing the rogue process now."
7. The connection pool recovers. Alice goes to **Move State** and moves the ticket forward to "Fix Verified"
8. The L1 users for "Fix Verified" receive an email notification
9. The verification team confirms everything is stable
10. Alice moves the ticket to **Closed** state and then clicks **Resolve**
11. The ticket is Resolved. The full timeline shows every action with timestamps

---

## 12. Users

**Purpose:** The Users page is where administrators create and manage operator accounts.

### Viewing users

Go to **Users** in the sidebar. The table shows all active users with their User ID, full name, email, role, phone, active status, and creation date.

### Creating a user

1. Click **Add User**
2. Fill in the form:

   - **User ID** (required) — a short login handle. Rules: 3 to 64 characters, only letters, digits, dots, underscores, and hyphens. Examples: `jdoe`, `alice.smith`, `noc_operator1`. As you type, the system checks availability in real time — a green tick means it is available, a red × means it is taken
   - **Email** (required) — the email address where this operator receives notifications. This is not used for login, only for emails
   - **Full name** (required) — the person's display name
   - **Password** (required) — must meet the configured password rules (shown in the help text). Use a strong password and tell the operator to change it on first login
   - **Phone** (optional) — for reference only
   - **Role** (required) — select the role for this user. The available roles depend on your own role — you cannot create a user with a higher role than your own

3. Click **Create**

The user can now log in using their User ID and the password you set.

**Validation:**
- If the User ID is already taken: "A user with that User ID already exists"
- If the email is already taken: "A user with that email already exists"
- If the password does not meet requirements: the error message describes what is missing

### Editing a user

Click **Edit** on any user row.

You can change:
- User ID, email, full name, phone, role, active status
- **Password** — leave blank to keep the current password. Fill in a new one to reset it

**Restrictions:**
- You cannot demote a `super_admin` user if they are the last active `super_admin` in the system
- You cannot deactivate your own account if you are a `super_admin`
- You cannot edit a user whose role is higher than your own role

### Deactivating a user

On the Edit User page, uncheck **Active** and click Update.

**What happens when a user is deactivated:**
- Their account is immediately locked — they cannot log in
- Any tickets currently assigned to them are unassigned and moved back to "Open" so another operator can pick them up
- Their historical activity and ticket timeline entries are preserved

### Deleting a user

Click the **Delete** button on the user row (trash icon).

> **Note:** Deletion is a soft delete — the user record remains in the database for audit purposes but the account is immediately locked and their tickets are unassigned.

**Restrictions:** You cannot delete yourself, a user with a higher role than yours, or the last active super_admin.

### Real example

Your NOC team hires a new engineer, Bob. You create:

- **User ID:** `bob.engineer`
- **Email:** `bob@company.com`
- **Full name:** Bob Engineer
- **Password:** `Temp@2026`
- **Role:** user

You tell Bob his login is `bob.engineer` and his temporary password is `Temp@2026`. He logs in and is prompted to change his password immediately.

---

## 13. Roles

**Purpose:** Roles control what level of access a user has. The Module Control Panel (covered next) controls which specific pages and actions each role can perform.

### Built-in roles

The system comes with three built-in roles that cannot be deleted:

| Role | Admin Scope | Description |
|---|---|---|
| super_admin | Yes | Full access to everything, including Settings and Roles |
| admin | Yes | Can see all tickets; module access is configurable |
| user | No | Can only see tickets they are directly involved with |

**Admin scope** is a special flag. When a role has admin scope, users with that role can see every ticket in the system. Without admin scope, users only see tickets they raised, are assigned to, or are in a state's user pool.

### Custom roles

You can create additional roles to fine-tune access control. For example, you might create a `vendor_lead` role with admin scope but limited to only the Tickets and Projects modules.

Go to **Roles** in the sidebar.

### Creating a custom role

1. Click **Add Role**
2. Fill in:
   - **Role Key** (required) — a short identifier, lowercase letters, digits, and underscores only. 2–50 characters. Example: `vendor_lead` or `noc_supervisor`. This cannot be changed later
   - **Label** (required) — the display name shown in the UI. Example: "Vendor Lead" or "NOC Supervisor"
   - **Admin Scope** (checkbox) — if checked, users with this role see all tickets. If unchecked, they see only their own
3. Click **Create**

After creation, the new role appears in the Roles list and a blank row is added to the Module Control Panel grid, ready to have permissions configured.

### Editing a role

Click **Edit** on any custom role. You can change the label and the admin scope flag. The role key cannot be changed.

**Note:** You cannot set `super_admin` to non-admin-scope even if you try — the system enforces this as a safety measure.

### Deleting a role

Click **Delete** on a custom role.

**Restrictions:**
- Built-in roles cannot be deleted
- A role with users still assigned to it cannot be deleted. The error will say: "Cannot delete — N user(s) still assigned to this role. Reassign them first."

---

## 14. Module Control Panel

**Purpose:** The Module Control Panel is where you control exactly what each role can see and do, page by page and action by action.

Go to **Manage Module** in the sidebar (visible only to super_admin).

### Reading the permission grid

The page shows a tab for each role (User, Admin, Super Admin, and any custom roles). Click a role's tab to see its permissions.

Each row in the grid is a module (a page or feature). Each module has four permission checkboxes:

| Permission | What it controls |
|---|---|
| **View Access** | Can the role see this page and its data? |
| **Add Action** | Can the role create new records on this page? |
| **Edit Action** | Can the role modify existing records? |
| **Delete Action** | Can the role delete records? |

A checked box means the role has that permission. An unchecked box means access is denied.

### How to change permissions

1. Click the tab for the role you want to adjust
2. Check or uncheck the boxes for each module
3. Click **Save Permissions** at the bottom of the page

The changes take effect immediately for the next request — logged-in users see the change on their next page load.

### Important notes

- Hiding a page from a role does not just remove the menu item — it also blocks direct URL access. If someone tries to go to `/tickets/all` without `tickets_all:view` permission, they are redirected
- The super_admin role always has full access and its permissions cannot be reduced through this panel
- Check at least **View Access** for any module you want a role to use — without View, the other permissions have no effect

### Custom modules

At the bottom of the page is a **Manage Modules** section. This lets you register new module keys if you have developed custom pages.

For standard usage, all the modules you need are already listed. Do not add modules here unless you have specifically developed a new controller method that uses a custom `check_module_access()` call.

### Real example

You want your `user` role operators to be able to:
- View the dashboard
- See their own tickets and raise new tickets
- Add comments and move states within tickets

But you do not want them to:
- See All Tickets
- Manage users, flows, or projects

You configure the User role tab:
- Dashboard: ✓ View
- My Tickets: ✓ View, ✓ Add (raising tickets), ✓ Edit (working tickets)
- All Tickets: ✗ View (unchecked)
- Projects: ✗ View
- Flows: ✗ View
- Users: ✗ View

After saving, the User role sees only Dashboard and My Tickets in their sidebar.

---

## 15. API Keys

**Purpose:** API keys allow external monitoring systems to create and query tickets in pView automatically, without a user logging in.

Go to **API Keys** in the sidebar.

### Understanding API key scoping

Each API key is linked to exactly one project. A key can only create tickets within its project, and can only read tickets from that project. This prevents one monitoring system from accidentally reading or modifying another client's data.

### Generating a new API key

1. Click **Generate Key** (or fill in the form on the right side of the page)
2. Enter a **Name** — something that describes what system will use this key, for example "Zabbix-Production" or "Grafana-Alerts"
3. Select the **Project** this key should be linked to
4. Click **Generate Key**

**Important:** After generation, the full API key is shown once in a highlighted box at the top of the page. Copy it immediately — once you leave this page or refresh, the full key is no longer visible. Only the masked version is shown from then on.

### Using the API key

Pass the key in the `X-API-KEY` HTTP header with every API request. Example:

```
POST /api/raise
X-API-KEY: abc123def456...
```

Full API documentation is in the [README](../README.md) or see [REST API in the README](#rest-api).

### Toggling a key on and off

Each key in the table has a **Toggle** button. Click it to enable or disable the key:
- A disabled key is rejected immediately — requests using it get a 401 error
- This is useful when a key may have been compromised or when a monitoring system is being taken offline temporarily

### Real example

Your Zabbix monitoring platform needs to create pView tickets automatically. You:

1. Create an API key named "Zabbix-Production" linked to the "Infrastructure Monitoring" project
2. Copy the generated key
3. Paste it into Zabbix's webhook configuration
4. Configure Zabbix to POST to `/api/raise` with the key when an alert fires

From then on, Zabbix alerts automatically become pView tickets without anyone manually creating them.

---

## 16. Settings

**Purpose:** The Settings page lets the super_admin configure system-wide behaviour — passwords, email, timing, UI preferences, and more — without touching any code or config files.

Go to **Settings** in the sidebar (visible only to super_admin).

> **Important:** Settings changes take effect immediately for the next page load or request. Some settings (like email) affect background processes too.

### Branding

- **app_name** — the name shown in the browser tab and in email subjects. Change this to match your company or product name

- **login_show_demo_creds** — when enabled, the login page shows a hint about demo credentials. Turn this off in production

### Security

| Setting | What it does |
|---|---|
| **maintenance_mode** | When enabled, regular users (non-admin) see a maintenance page. Admins and super_admins can still work normally |
| **password_min_length** | Minimum characters for passwords. Default: 8 |
| **password_require_letter** | Passwords must contain at least one letter |
| **password_require_digit** | Passwords must contain at least one digit |
| **password_rotate_days** | Operators must change their password after this many days. Set to 0 to disable forced rotation |
| **login_max_attempts** | How many wrong password attempts before lockout. Set to 0 to disable lockout |
| **login_lockout_minutes** | How long the lockout lasts |
| **session_idle_timeout_minutes** | Operators are automatically logged out after this many minutes of inactivity. Set to 0 to disable |

### Rate limiting (API)

| Setting | What it does |
|---|---|
| **api_rate_per_minute** | Maximum API requests an API key can make per minute |
| **api_rate_per_hour** | Maximum API requests per hour |

Set to 0 to disable rate limiting (not recommended in production).

### Attachments

| Setting | What it does |
|---|---|
| **upload_max_mb** | Maximum file size for ticket attachments in megabytes |
| **upload_allowed_ext** | Comma-separated list of allowed file extensions |
| **upload_blocked_ext** | Additional extensions to always block, even if they appear in the allowed list |

### TAT defaults

These are the default time limits (in minutes) used when a flow state has not configured its own TAT:

- **default_tat_l1_minutes** — default L1 TAT
- **default_tat_l2_minutes** — default L2 TAT
- **default_tat_l3_minutes** — default L3 TAT
- **default_tat_l4_minutes** — default L4 TAT

Individual states in a flow can override these defaults.

### UI

| Setting | What it does |
|---|---|
| **datatable_page_length** | How many rows appear in each table by default |
| **dashboard_trend_ranges** | Comma-separated list of day-range options on the dashboard trend chart (e.g., 7,15,30) |

### Live polling

These settings control the real-time features — the bell badge and dashboard updates:

| Setting | What it does |
|---|---|
| **live_poll_seconds** | How often (in seconds) the browser checks for new actionable tickets. Range: 5–120. Set to 0 to disable |
| **live_audio_enabled** | Play a soft beep sound when new actionable tickets appear while you are on the page |
| **live_browser_notify** | Request permission to show browser push notifications when new critical or escalated tickets appear |
| **analytics_refresh_seconds** | How often the Analytics tab auto-refreshes. Set to 0 to disable auto-refresh |

### Email / SMTP

This section configures how pView sends emails. Changes here affect all future emails — including escalation notifications and test emails.

| Setting | Example value |
|---|---|
| **email_protocol** | smtp |
| **email_smtp_host** | smtp.company.com |
| **email_smtp_port** | 587 |
| **email_smtp_user** | alerts@company.com |
| **email_smtp_pass** | (your SMTP password) |
| **email_smtp_crypto** | tls (for port 587) or ssl (for port 465) |
| **email_from_email** | alerts@company.com |
| **email_from_name** | pView Alerts |

**Test email button:** After updating SMTP settings, click **Send test email to me**. The system sends a test email to your own email address using the settings currently saved in the database (it forces a fresh read of the settings before sending). Check your inbox to confirm the email arrived.

### Notification queue

| Setting | What it does |
|---|---|
| **notification_batch_size** | Maximum number of queued emails sent per cron run (1–500) |
| **notification_max_attempts** | How many times to retry a failed email before giving up |

### Assets (cache busting)

| Setting | What it does |
|---|---|
| **asset_version** | A number appended to CSS and JS file URLs. Increase this after deploying updated styles or scripts |

**Bump version button:** Click **Bump version** to automatically increment this number by one. All browsers will reload the CSS and JS files on their next page load.

### Saving settings

Click **Save Settings** at the bottom of the page. A success message confirms the settings were saved. The settings cache is cleared automatically — changes are live immediately.

---

## 17. Activity Log

**Purpose:** The Activity Log is a complete, read-only history of everything that has happened in pView — every login, every ticket action, every settings change, every user creation. It is the audit trail of the entire system.

Go to **Activity Log** in the sidebar.

### Event Log tab

The main tab shows a chronological list of events. Each row represents one action.

**Columns:**

| Column | What it shows |
|---|---|
| **Time** | When the event happened |
| **User** | Who performed the action (User ID, name, and role) |
| **Module** | Which part of the system (tickets, users, settings, auth, etc.) |
| **Action** | What was done (create, update, delete, login, logout, etc.) |
| **Entity** | What object was affected (ticket ID, user ID, etc.) |
| **Summary** | A human-readable description of what happened |
| **Login** | The time the user logged in for the session |
| **Logout** | The time the user logged out |
| **Source** | Web (browser), Mobile, or API |

### Filtering the log

Use the filter bar to narrow down results:

- **Date range** — defaults to today. Change to see older records
- **User** — filter by user ID or name
- **Module** — filter by module (e.g., see only ticket events)
- **Action** — filter by action type (e.g., see only login events)
- **Role** — filter by user role
- **Status** — Success or Fail (e.g., see only failed login attempts)
- **Project** — filter by project (useful for project-specific auditing)

Click **Apply** to apply the filters. Click **Reset** to return to today's full log.

### Exporting the log

Click **Export CSV** to download the filtered log as a CSV file. The export includes all columns including IP address and full timestamps.

### Analytics tab (admin permission required)

The Analytics tab provides a higher-level view of user activity over time. It auto-refreshes every 30 seconds by default.

**KPI cards at the top:**

| Card | Meaning |
|---|---|
| Logins today | Number of successful logins today |
| Logins in period | Successful logins in the selected date range |
| Failed logins today | Number of failed login attempts today |
| Failed logins in period | Failed attempts in the selected date range |

A high number of "Failed logins today" may indicate a brute-force attempt or an operator who forgot their password.

**Top Active Users table**

Shows which users have been most active, their role, total event count, and when they were last seen. Click any user row to open a **drilldown modal** showing that user's complete event history.

**Module Usage chart**

A horizontal bar chart showing which modules are used most. This helps you understand which parts of the system your team relies on.

**Average Session Duration table**

Shows how long each user typically stays logged in per session, and how many sessions they have had in the period.

**Failed Events table**

A list of events that failed (e.g., failed login attempts, blocked API requests). Each row shows the timestamp, user, module, action, summary, and source. This is useful for security monitoring.

### Real example — security review

A team lead suspects that someone is trying to guess an operator's password. They:

1. Open the Activity Log
2. Set the filter: **Module** = auth, **Action** = login_failed
3. Click Apply

They see 47 failed login attempts for the user ID "alice" from the same IP address over the last hour. They report this to the administrator, who can check if Alice's account is locked and investigate the source IP.

---

## 18. Cron Panel

**Purpose:** The Cron Panel shows the recent history of the background escalation process — you can see whether it is running, how long it takes, and whether any errors occurred.

Go to **Cron Panel** in the sidebar.

### What you see

**Summary cards at the top:**

- **Script name** — `tat_monitor` (the TAT escalation engine)
- **Status badge** — OK (green) or FAILED (red)
- **Last run** — when it last ran (e.g., "2 minutes ago")
- **Duration** — how long it took in seconds
- **Tickets checked** — how many tickets were evaluated for escalation
- **Sent / Failed** — how many email notifications were sent and whether any failed

**Today's Activity card:**

- Total runs today
- Total tickets checked today
- Total notifications sent today
- Success rate (last N runs)
- Average duration

**Cron Schedule card:**

Shows the exact crontab line you need to use:
```
* * * * * php /path/to/pview_alerts/tat_monitor.php
```

### Run history table

Below the summary cards, a scrollable table shows the last up to 100 individual runs with:

- Script name
- Start time
- Duration in seconds
- Tickets checked
- Notifications sent
- Notifications failed (shown in red if more than 0)
- Status (OK or FAILED badge)
- Summary line

### When to check the Cron Panel

- If operators report that escalation emails are not arriving — check whether recent runs show a FAILED status
- If you see a high "Failed" count in notifications — check the email settings in the Settings page
- If the "Last run" shows many minutes ago instead of 1–2 minutes — the cron job may have stopped running. Contact your server administrator

### If the cron panel shows no data

If you see a message saying "The cron_runs table does not exist yet", the database needs to be migrated. Contact your technical administrator to run `php spark migrate`.

---

## 19. Personal Preferences — Dashboard

**Purpose:** Each operator can personalise their own dashboard without affecting anyone else.

Click your name or avatar in the topbar, then go to **My Preferences**, or navigate directly to `/me/dashboard`.

### Default project filter

If you manage alerts for a specific project most of the time, set it as your default project. When set:

- The dashboard KPI cards show only that project's data
- The severity chart shows only that project
- The trend chart shows only that project
- A note appears on the dashboard: "Filtered to project: ProjectName" with a "change" link

Select your project from the dropdown, or leave it as "All projects (no filter)" to see the full system view.

### KPI card visibility

Choose which of the four KPI cards you want to see on your dashboard:
- **Open Tickets**
- **Critical**
- **Major**
- **Resolved**

Uncheck any card you do not find useful. At least one card must stay visible — if you uncheck all four, the system automatically keeps "Open Tickets" checked.

### Default trend range

Choose which time window the trend chart opens with:
- "Use system default (7 days)" — whatever the administrator has configured
- 7 days, 15 days, or 30 days (or any other range the administrator has added)

### Saving your preferences

Click **Save Preferences**. The dashboard immediately reflects your choices the next time you load it.

---

## 20. Notification Preferences

**Purpose:** Each operator controls which email notifications they receive. By default, you receive all notifications. Use this page to opt out of specific project/severity combinations.

Navigate to `/me/notifications` or follow the link from your profile area.

### Understanding the notification matrix

The matrix is a grid:
- **Rows** = projects (plus an "All projects" catch-all row at the top)
- **Columns** = severity levels (Info, Major, Critical)

Each cell is a checkbox. Checked means you receive emails for that combination. Unchecked means you do not.

**All projects row:** This is a catch-all. If a specific project row is checked, it takes priority. The "All projects" row is used for any project that does not have a specific row checked.

**Example:**

| Project | Info | Major | Critical |
|---|---|---|---|
| All projects | ✓ | ✓ | ✓ |
| Client Alpha | ✗ | ✓ | ✓ |
| Client Beta | ✗ | ✗ | ✓ |

With this configuration, you receive:
- All severity emails from any project not specifically listed
- Only Major and Critical from Client Alpha (Info is suppressed)
- Only Critical from Client Beta

### Saving your preferences

Click **Save Preferences**. Changes take effect immediately for the next email event.

### Important notes

- If you have never visited this page, you receive all notifications by default (the system is intentionally set to "allow all" when no preferences are saved)
- These preferences only affect you — changing them does not affect your colleagues
- Preferences apply to ticket event notifications. @mention notifications are always sent regardless of these preferences

---

## 21. Maintenance Mode

**Purpose:** Maintenance mode allows super_admins to temporarily prevent regular operators from accessing the system — for example, during a database migration or a major configuration change.

### Enabling maintenance mode

1. Go to **Settings**
2. Find the **maintenance_mode** toggle under the Security section
3. Enable it (turn it on)
4. Click **Save Settings**

**What happens immediately:**
- Regular operators and admin-scope (non-super) users are redirected to a maintenance page on their next page load
- They see a simple message indicating the system is under maintenance
- They cannot access any pages until maintenance mode is turned off
- Currently logged-in super_admins can continue working normally

Admin-scope users (admin role) are redirected to the dashboard and can work normally — only regular users (user role) and non-admin custom roles are blocked.

### Disabling maintenance mode

**Option 1 — through Settings:**
1. Log in as super_admin
2. Go to Settings
3. Toggle maintenance_mode off
4. Save Settings

**Option 2 — from the maintenance page:**
If you are logged in as super_admin and you visit the maintenance page (e.g., because you navigated to `/maintenance`), you will see a **Disable Maintenance Mode** button. Click it to turn off maintenance immediately and be redirected to the dashboard.

---

## 22. Quick Reference

### Ticket statuses

| Status | Badge colour | Meaning |
|---|---|---|
| **Open** | Grey | Ticket raised but not assigned to anyone |
| **In Progress** | Blue | Assigned to an operator who is working on it |
| **Escalated** | Red | TAT breached at the highest configured level — needs admin attention |
| **Resolved** | Green | Operator marked it resolved — can be reopened |
| **Closed** | Dark/Black | Final state — no further changes allowed |

### Severity levels

| Severity | Badge colour | Meaning |
|---|---|---|
| **Info** | Blue | Low-priority informational alert |
| **Major** | Orange/Yellow | Significant issue requiring prompt attention |
| **Critical** | Red | Urgent — high impact, requires immediate action |

### Priority levels

| Priority | Badge colour | Meaning |
|---|---|---|
| **Low** | Grey | Can wait |
| **Medium** | Blue | Normal urgency (default) |
| **High** | Orange | Should be addressed soon |
| **Urgent** | Red | Drop everything and handle this now |

### Escalation levels

| Level | Meaning |
|---|---|
| **L1** | First responders — the primary team assigned to a state |
| **L2** | Second line — escalated when L1 does not act in time |
| **L3** | Third line — escalated when L2 does not act in time |
| **L4** | Final line — when L4 TAT breaches, the ticket becomes `escalated` |

### TAT countdown colours

| Colour | Meaning |
|---|---|
| **Normal** | Plenty of time remaining |
| **Orange pulse** | Warning — less than 25% of the time window remaining |
| **Red pulse** | Breached — the clock has run out; escalation will fire on the next cron tick |

### Common actions and what they require

| Action | Who can do it |
|---|---|
| View My Tickets | Any logged-in user |
| View All Tickets | Admin-scope roles only |
| Raise a ticket | Anyone with tickets:add permission |
| Assign a ticket | The assigned operator or any admin-scope user |
| Move ticket state | The assigned operator or any admin-scope user |
| Resolve a ticket | The assigned operator or any admin-scope user |
| Close a ticket | The assigned operator or any admin-scope user |
| Reopen a ticket | The assigned operator or any admin-scope user |
| Add a comment | Any user with access to the ticket |
| Upload attachment | Any user with access to the ticket (up to 5 per ticket) |
| Download attachment | Any user with access to the ticket |
| Bulk resolve/close | Any user with tickets:edit permission |
| Manage flows/states | Users with flows:edit permission |
| Manage users | Users with users:add/edit/delete permission |
| Manage roles | Users with roles:add/edit/delete permission |
| View Settings | super_admin only |
| Manage Settings | super_admin only |
| View Module Control Panel | super_admin only |

### Common messages and what they mean

| Message | What to do |
|---|---|
| "Invalid credentials" | Check your User ID / email and password. Caps Lock may be on |
| "Too many failed attempts. Try again in N minute(s)." | Wait the specified time then try again |
| "Your session has expired. Please sign in again." | You were inactive too long. Log in again |
| "A project with that name already exists" | Choose a different project name |
| "A user with that User ID already exists" | Choose a different User ID |
| "Only the assigned operator or an admin can move the ticket state" | You are not assigned to this ticket and do not have admin scope |
| "Ticket is resolved/closed; further edits are blocked" | The ticket is in a terminal state — reopen it first if needed |
| "File type is not allowed" | The file extension or MIME type is blocked. Check with your administrator |
| "State must have at least one operator registered in L1 or L2 pools" | Add at least one user to the state's L1 or L2 pool before saving |
| "Cannot delete — this is the last active super-admin" | You cannot remove the only super_admin account |

---

## Appendix A — Suggested Setup Order for New Installations

If you are setting up pView for the first time, here is the recommended order:

1. **Log in** as super_admin (credentials provided by your technical team)
2. **Change your password** immediately
3. Go to **Settings** and configure:
   - App name
   - Email / SMTP settings → send a test email to verify
   - TAT defaults
   - Password policy
4. Go to **Users** and create your operator accounts
5. Go to **Roles** and create any custom roles if needed
6. Go to **Module Control Panel** and configure what each role can access
7. Go to **Projects** and create your projects
8. Go to **Flows** and design your workflows — add states with user pools and TAT settings
9. Go to **Escalation** and add any override rules for specific states
10. Go to **Alert Defs** and create alert templates if you use the API
11. Go to **API Keys** and generate keys for any monitoring systems that will use the API
12. Verify the cron job is running (check the **Cron Panel** after 2–3 minutes)
13. Raise a test ticket and walk it through the full lifecycle

---

## Appendix B — Understanding the Email Flow

Emails in pView are sent asynchronously — they are queued first and delivered in the background. Here is what happens:

1. An event occurs (ticket created, state moved, TAT breached, @mention added)
2. pView writes an email row to the notification queue with status **Pending**
3. Within the next minute, the background cron job processes the queue
4. The cron job calls the SMTP server and sends the email
5. The row status changes to **Sent** (green) or **Failed** (red)

If an email fails, the system retries it up to 5 times. After that, it gives up and marks the row as Failed.

You can see each ticket's notification status at the bottom of its detail page.

**If emails are not arriving:**
1. Check your spam folder first
2. Go to Settings and click "Send test email to me" — if this fails, the SMTP settings are incorrect
3. Check the Cron Panel to see if the background job is running
4. Ask your administrator to check the server logs

---

## Appendix C — Role and Permission Summary

| Page / Feature | user | admin | super_admin |
|---|---|---|---|
| Dashboard | ✓ | ✓ | ✓ |
| My Tickets | ✓ | ✓ | ✓ |
| All Tickets | ✗ (configurable) | ✓ | ✓ |
| Raise Ticket | ✓ (configurable) | ✓ | ✓ |
| Projects | ✗ (configurable) | ✓ | ✓ |
| Flows | ✗ (configurable) | ✓ | ✓ |
| Alert Definitions | ✗ (configurable) | ✓ | ✓ |
| Escalation Matrix | ✗ (configurable) | ✓ | ✓ |
| Users | ✗ (configurable) | ✓ | ✓ |
| Roles | ✗ | ✗ (configurable) | ✓ |
| API Keys | ✗ (configurable) | ✓ | ✓ |
| Activity Log | ✗ (configurable) | ✓ | ✓ |
| Cron Panel | ✗ (configurable) | ✓ | ✓ |
| Settings | ✗ | ✗ | ✓ |
| Module Control Panel | ✗ | ✗ | ✓ |

*(configurable)* means the default may be restricted but can be granted through the Module Control Panel.

---

*This document covers the pView Alert System as of June 2026. For technical documentation including API reference, installation, and deployment, refer to the README file.*
