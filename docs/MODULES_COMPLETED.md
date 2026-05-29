# Project Milestone Progress Update

* **Project**: pView Alert System
* **Milestone**: Core Workflow & Incident Operations Modules
* **Prepared By**: Bobil Chauhan (Development Team)
* **Date**: 29 May 2026

---

## Overview

This progress report summarizes the core functional modules completed for this milestone. Every module has been fully implemented, validated against the database schema, and tested on the local development environment. 

This milestone focuses exclusively on the following 6 core modules:
1. **User Management**
2. **Project Module**
3. **Flow Module**
4. **Raise Ticket**
5. **My Tickets**
6. **All Tickets**

All core workflows, database structures, UI templates, form validations, and interactivity are working.

---

## 1. User Management & Authentication

This module manages system operators, handles authentication, and implements role-based access control.

### Key Functionalities Completed:
* **Dynamic Login Flow**: Supports logging in with either a unique **User ID** or **Email**. The system automatically detects the input type. Passwords are fully secured using strong **bcrypt hashing**.
* **Role-Based Access**:
  * **Super Admin**: Full administrative capabilities, including user management, role assignments, and system-wide configurations.
  * **Admin**: Operational control over projects, workflow flows, and states.
  * **User**: Standard operator access focused on resolving and managing assigned tickets.
* **Add & Edit User Controls**: Fully validated form including:
  * **Live Availability Check**: Dynamically queries the database as you type the User ID to show instantly whether it is available or already taken.
  * **Password Strength Policies**: Enforces a minimum of 8 characters, at least one letter, and at least one digit. 
  * **Interactivity**: Includes a password visibility toggle (eye icon) and an active Caps Lock warning indicator.
* **Security & Operations**:
  * **Soft Delete**: Deleting a user marks them as soft-deleted in the database (`deleted_at`) to preserve historical audit logs and ticket assignment histories.
  * **Password Rotation Policy**: Forces users to update their credentials if their password is older than 90 days.
  * **Theme Preferences**: Supports both **Light Mode** and **Dark Mode** toggle. Preferences are saved both in the session and the database per-user, maintaining consistency across logins.

---

## 2. Project Module

Projects are the top-level organization units within pView, grouping related alert rules and workflows.

### Key Functionalities Completed:
* **Project CRUD**: Supports creating, viewing, editing, and soft-deleting project configurations.
* **Project Listing Table**: Implemented as an interactive DataTable. It displays:
  * Project Name & Description
  * Current Status (Active / Inactive badges)
  * Creator name and creation timestamp
  * Action buttons (Edit & Delete)
* **Delete Safeguards**: Integrated SweetAlert confirmation dialogs to prevent accidental deletion. Soft-deletion ensures historical alerts remain valid.
* **Form Validation**: Standard validations are active; project names are strictly required and sanitized.

### UI Progress Reference:
The active projects dashboard is clean and structured. It displays the operational state, creator metadata, and fast CRUD action buttons:

![pView Projects Table](file:///c:/xampp8/htdocs/pview_alerts/docs/projects_screenshot.png)
*Figure 1.1: Project Listing Panel showing the active "IT Infrastructure" project namespace.*

---

## 3. Flow Module (including Visual Workflow Engine)

A Flow defines the lifecycle of a ticket. Each flow is represented by a sequence of states (steps) which can be a straight linear path or a branching structure.

### Key Functionalities Completed:
* **Flow Configurations**:
  * Allows creating and editing workflows under specific projects.
  * Soft-delete capability prevents breaking active incidents.
* **States Configuration**:
  * **Escalation Levels (L1 to L4)**: Each state supports detailed level configurations. Multiple users can be assigned to each level along with custom TAT (Turn-Around-Time) minutes.
  * **Branching Workflows**: Fully supports multi-branch structures by letting states define a `parent_state_id`.
  * **Interactive Ordering**: Supports drag-and-drop reordering of states on the management screen. Reordering automatically triggers an AJAX call to save the new order indexes in real-time.
* **Visual Workflow Engine**:
  * Built using **Mermaid.js** to render dynamic, responsive visual diagrams.
  * Distinct node styles: Start state (green border + glow), Process states (purple border + glow), and End state (slate border + soft glow).
  * Connector lines are rendered as smooth curves with a subtle moving-dash animation to represent active transitions.
  * **Interactive Diagrams**: The visual canvas includes a full toolbar with Zoom In/Out (with percentage indicator), Fit to View, Fullscreen mode, and Mouse click-and-drag panning.
  * The preview updates dynamically in real-time as states are reordered or edited.

---

## 4. Raise Ticket (UI)

Enables operators to manually raise incident tickets in the system.

### Key Functionalities Completed:
* **Interactive Ticket Raising**: Form fields include Project, Flow, Alert Definition template mapping (which auto-populates default severity, priority, initial state, and extra notify lists), Title, Description, and attachments.
* **Sequential Alarm ID Generation**: Automatically assigns a human-readable identifier (e.g., `ALM-20260529-00001`) reset daily. Daily counters are tracked atomically using the `alarm_id_sequence` table.
* **File Upload Sandboxing**: Supports multiple file uploads. Uploaded files are placed in a protected subdirectory inside the `writable/` folder. They are served dynamically through a backend controller, blocking direct URL access from unauthenticated users.
* **Validation**: Extensively validates file extensions and max sizes (derived from app settings).

---

## 5. My Tickets

Designed as a personal dashboard for operational operators to focus on their active workload.

### Key Functionalities Completed:
* **Focused Incident Feed**: Filters out all system noise. It only lists tickets where the logged-in user is:
  * The direct assignee.
  * Part of the L1, L2, L3, or L4 escalation responder lists for the ticket's current state.
  * The original ticket creator.
* **Urgency Signals**: Displays live-updating TAT countdown badges that alert the operator when a milestone is approaching and turn red once overdue.

---

## 6. All Tickets

The centralized command grid for administrators to monitor the complete operational landscape.

### Key Functionalities Completed:
* **Comprehensive Incident Grid**: Displays all tickets across all projects with real-time server-side pagination, sorting, and global search.
* **Advanced Filters**: Dropdown filters for Project, Flow, Current State, Severity, Status, Assignee, and Date Range.
* **Saved Filters**: Users can save their current filter setups under a custom name to quickly load them later.
* **Bulk Operations**: Bulk action bar allows selected tickets to be resolved, closed, reassigned, or moved to a different state in a single batch request.
* **CSV Export**: One-click export function generates a CSV of the currently filtered ticket list for external reporting.

---

## 7. Interactive Ticket Detail Page & Actions

The central hub for managing an individual ticket through its lifecycle.

### Key Functionalities Completed:
* **Visual Progress Tracker**: Embeds the workflow engine at the top of the ticket. It highlights the ticket's path: completed states show a green border/glow, the **current live state has a blue border with a soft pulse animation**, and pending states are muted slate.
* **Lifecycle State Transitions**:
  * **Move State**: Resets the TAT clock and advances the ticket.
  * **Assign**: Reassigns the ticket (restricted to users listed in the current state's L1–L4 settings).
  * **Resolve & Close**: Requires mandatory resolution comments. Closing a ticket seals it permanently from further edits.
* **Chronological Timeline**: An audit timeline logs every action taken (comments, reassignments, file uploads, state updates) with timestamps and the performing user's ID.
* **TAT Status**: Shows the active escalation level (L1–L4) and the remaining time before auto-escalation triggers.
* **Inline Edits**: Admins and assignees can update the title or description inline by clicking directly on the text.

---

## 8. Database Schema Details (Milestone Tables)

Below is the database structure for the core operational entities, mapping the design system implemented on the MySQL/MariaDB server. All tables reside under the `pview_alerts` database.

### 1. Table: `users`
Stores system users, credentials, roles, and user interface preferences.
* `id` **(INT UNSIGNED, Primary Key, AUTO_INCREMENT)**: Unique auto-incrementing key.
* `user_id` **(VARCHAR(64), UNIQUE, Not Null)**: Human-readable login identifier (e.g. `bobil.singh`).
* `name` **(VARCHAR(100), Not Null)**: Operator's full name.
* `email` **(VARCHAR(150), UNIQUE, Not Null)**: Email address used for notifications/login.
* `password` **(VARCHAR(255), Not Null)**: Bcrypt password hash.
* `role` **(VARCHAR(50), Not Null)**: Assigned security tier (references `roles.role_key`).
* `phone` **(VARCHAR(20), Null)**: Contact number.
* `is_active` **(TINYINT(1), Default 1)**: Active status flag (1 = Enabled, 0 = Disabled from login).
* `created_at` / `updated_at` **(DATETIME, Not Null)**: Timestamps recording row creation and edits.
* `deleted_at` **(DATETIME, Null)**: Timestamp used for soft deletions.
* `password_changed_at` **(DATETIME, Null)**: Date of last password update, enforcing rotation rules.
* `dashboard_layout` **(TEXT, Null)**: JSON formatted string storing operator-level layout preferences.
* `theme` **(VARCHAR(20), Default 'dark')**: Active interface styling preference (`dark` or `light`).

### 2. Table: `roles`
Stores the dynamic roles available in the system.
* `role_key` **(VARCHAR(50), Primary Key)**: Role identifier slug (e.g. `super_admin`, `admin`, `user`).
* `label` **(VARCHAR(100), Not Null)**: Display name.
* `is_builtin` **(TINYINT(1), Default 0)**: Protected roles flag (1 = Built-in, 0 = Custom).
* `is_admin_scope` **(TINYINT(1), Default 0)**: Privilege flag indicating if this role holds user administrative access.
* `sort_order` **(INT, Default 100)**: Interface sorting weight.
* `created_at` / `updated_at` **(DATETIME, Not Null)**: Creation and update logs.

### 3. Table: `module_permissions`
Explicit access control mapping linking roles to specific dashboard sections.
* `id` **(INT UNSIGNED, Primary Key, AUTO_INCREMENT)**: Auto-key.
* `role` **(VARCHAR(50), Not Null)**: Target user role (references `roles.role_key` with cascading deletions).
* `module_key` **(VARCHAR(50), Not Null)**: Application module identifier (e.g. `flows`, `users`, `tickets`).
* `can_view` / `can_add` / `can_edit` / `can_delete` **(TINYINT(1), Default 0)**: Specific operation permission bitmasks.
* *Note: Enforces a strict UNIQUE index combination on `(role, module_key)`.*

### 4. Table: `projects`
Top-level namespaces grouping active operations.
* `id` **(INT UNSIGNED, Primary Key, AUTO_INCREMENT)**: Unique project key.
* `name` **(VARCHAR(200), Not Null)**: Project name.
* `description` **(TEXT, Null)**: Detail summaries.
* `status` **(ENUM('active', 'inactive'), Default 'active')**: Operational status flag.
* `created_by` **(VARCHAR(64), Not Null)**: User identifier of creator (references `users.user_id`).
* `created_at` / `updated_at` **(DATETIME, Not Null)**: Audit timestamps.
* `deleted_at` **(DATETIME, Null)**: Soft-deletion log.

### 5. Table: `flows`
Workflows registered under project scopes.
* `id` **(INT UNSIGNED, Primary Key, AUTO_INCREMENT)**: Unique flow key.
* `project_id` **(INT UNSIGNED, Not Null)**: Parent project reference (references `projects.id`).
* `name` **(VARCHAR(200), Not Null)**: Flow name.
* `status` **(ENUM('active', 'inactive'), Default 'active')**: Operational status flag.
* `created_by` **(VARCHAR(64), Not Null)**: User identifier of creator (references `users.user_id`).
* `created_at` / `updated_at` / `deleted_at`: Standard creation, modification, and soft-delete logs.

### 6. Table: `states`
Configured transition checkpoints inside workflows, holding escalations and limits.
* `id` **(INT UNSIGNED, Primary Key, AUTO_INCREMENT)**: Unique state identifier.
* `flow_id` **(INT UNSIGNED, Not Null)**: Parent workflow reference (references `flows.id`).
* `name` **(VARCHAR(200), Not Null)**: Display label.
* `parent_state_id` **(INT UNSIGNED, Null)**: Parent state reference for branch workflows (references `states.id`, null on root states).
* `sort_order` **(INT, Default 0)**: Drag-and-drop order weight.
* `is_initial` **(TINYINT(1), Default 0)**: Initial flow entry-point flag (1 = Entry, 0 = Intermediate).
* `is_final` **(TINYINT(1), Default 0)**: Final endpoint flag (1 = Completion, 0 = In-progress).
* `l1_user_ids` / `l2_user_ids` / `l3_user_ids` / `l4_user_ids` **(JSON, Null)**: Structured array lists of assigned user IDs (e.g. `["operator.a", "operator.b"]`) representing escalation tiers.
* `l1_tat_minutes` / `l2_tat_minutes` / `l3_tat_minutes` / `l4_tat_minutes` **(INT, Not Null)**: Turn-Around Time window limits configured in minutes for escalation alarms.
* `status` **(ENUM('active', 'inactive'), Default 'active')**: Status flag.
* `created_by` **(VARCHAR(64), Not Null)**: Creator log.
* `created_at` / `updated_at`: Standard logs.

### 7. Table: `tickets`
The central operational records capturing incidents, state flows, and responders.
* `id` **(INT UNSIGNED, Primary Key, AUTO_INCREMENT)**: Primary key.
* `alarm_id` **(VARCHAR(30), UNIQUE, Not Null)**: Sequential human-readable identifier (e.g. `ALM-20260529-00001`).
* `project_id` / `flow_id` **(INT UNSIGNED, Not Null)**: Links to standard project and workflow pathways.
* `alert_def_id` **(INT UNSIGNED, Null)**: Linked alert rule mapping reference (references `alert_definitions.id`).
* `title` **(VARCHAR(300), Not Null)**: Incident summary header.
* `description` **(TEXT, Null)**: Detailed payload parameters and diagnostic notes.
* `alert_type` **(ENUM('info', 'major', 'critical'), Default 'info')**: Telemetry priority level.
* `priority` **(ENUM('low', 'medium', 'high', 'urgent'), Default 'medium')**: Action priority.
* `current_state_id` **(INT UNSIGNED, Null)**: Linked state checkpoint (references `states.id`).
* `current_level` **(TINYINT, Default 1)**: Active escalation level (L1–L4).
* `current_assignee` **(VARCHAR(64), Null)**: Logged user currently operating on the ticket (references `users.user_id`).
* `status` **(ENUM('open', 'in_progress', 'escalated', 'resolved', 'closed'), Default 'open')**: Ticket operational state.
* `source` **(ENUM('ui', 'api'), Default 'ui')**: Trigger source.
* `source_system` **(VARCHAR(100), Null)**: External system identifier.
* `raised_by` **(VARCHAR(64), Null)**: Operator who raised the ticket (references `users.user_id`).
* `state_entered_at` **(DATETIME, Not Null)**: Timestamp when ticket entered the current state, initializing/resetting TAT windows.
* `resolved_at` / `closed_at` **(DATETIME, Null)**: Resolution and closure logs.
* `created_at` / `updated_at`: Standard lifecycle timestamps.
* `last_tat_warn_level` **(TINYINT UNSIGNED, Default 0)**: Log of last warnings triggered.

### 8. Table: `ticket_actions`
Complete chronological audit timeline capturing every edit, comment, and event on a ticket.
* `id` **(INT UNSIGNED, Primary Key, AUTO_INCREMENT)**: Primary key.
* `ticket_id` **(INT UNSIGNED, Not Null)**: Linked incident reference (references `tickets.id` with cascade options).
* `action_type` **(ENUM('created', 'commented', 'state_changed', 'level_escalated', 'assigned', 'attachment', 'resolved', 'closed', 'api_update', 'title_changed', 'description_changed', 'priority_changed'), Not Null)**: Incident lifecycle event identifier.
* `from_state_id` / `to_state_id` **(INT UNSIGNED, Null)**: State transition tracking (references `states.id`).
* `from_level` / `to_level` **(TINYINT, Null)**: Escalation details.
* `comment` **(TEXT, Null)**: Resolution notes or comments.
* `attachment_path` **(VARCHAR(500), Null)**: Sandbox path of uploaded files.
* `performed_by` **(VARCHAR(64), Null)**: Acting user ID (references `users.user_id`).
* `performed_by_system` **(VARCHAR(100), Null)**: Script/system actor label (for automated background tasks).
* `created_at` **(DATETIME, Default current_timestamp)**: Trigger log.

### 9. Table: `alarm_id_sequence`
Atomic daily counter used to ensure clean, collision-free Alarm ID sequences.
* `id` **(INT UNSIGNED, Primary Key, AUTO_INCREMENT)**: Unique key.
* `day_key` **(VARCHAR(8), UNIQUE, Not Null)**: Sequence reset tracker date in `YYYYMMDD` format.
* `last_seq` **(INT UNSIGNED, Default 0)**: Last issued sequence counter.
* `updated_at` **(DATETIME)**: Sequence update lock log.

---

## Milestone Summary

| Module Name | Scope / Deliverables | Status |
| :--- | :--- | :--- |
| **User Management** | Login flow, custom roles, soft-deletion, password validation/rotation, and Light/Dark themes. | **Completed** |
| **Project Module** | Project CRUD operations, DataTable grid, delete safeguards, and name validations. | **Completed** |
| **Flow Module** | Flow configuration, state assignments, L1–L4 responder setup, drag-and-drop reordering, and Visual Workflow Engine (Mermaid.js). | **Completed** |
| **Raise Ticket** | Manual incident creation, default mappings, ALM sequence ID generator, and secure sandboxed uploads. | **Completed** |
| **My Tickets** | Filtered incident inbox for active assignees and responders, with live TAT alerts. | **Completed** |
| **All Tickets** | Global search grid, advanced filters, saved filter presets, bulk actions, and CSV export. | **Completed** |

Every feature listed above is fully implemented, verified, and operational.
