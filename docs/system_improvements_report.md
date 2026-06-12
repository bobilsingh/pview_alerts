# System Analysis & Improvements Report

This document presents a comprehensive analysis of the **pView Alert System** and identifies practical, non-overengineered improvements to make the application more efficient, user-friendly, configurable, manageable, scalable, and enterprise-ready, without affecting the existing core flow and functionalities.

---

## 1. User Experience & Usability

| Feature | Proposed Improvement | Priority | Implementation Suggestion |
| :--- | :--- | :--- | :--- |
| **AJAX-Based Inline Updates** | Update ticket fields (assignee, priority, alert type) and submit comments inline on the ticket detail page without full page refreshes. | **High** | Implement simple jQuery AJAX calls on field change and inject new elements into the DOM, avoiding full page reloads. |
| **Bulk Ticket Actions** | Add checkbox selections to the main ticket list tables (My Tickets, All Tickets) to allow bulk assignment, bulk state changes, and bulk closures. | **Medium** | Add selection checkboxes in `datatable.js` and implement a batch action endpoint (e.g., `/tickets/bulk_update`) in the controller. |
| **Auto-Refresh Control** | Provide a "Pause / Resume" toggle button near auto-refreshing UI elements (like dashboards and list tables) so users can freeze the view when reading. | **Low** | Add a state variable in `app.js` to control `setInterval` ticks for polling and datatable reloads based on UI toggle. |

---

## 2. Dashboard & Workflow Optimization

| Feature | Proposed Improvement | Priority | Implementation Suggestion |
| :--- | :--- | :--- | :--- |
| **Kanban Board View** | Introduce a visual Kanban board tab for tickets where cards represent tickets and columns represent workflow states, allowing quick drag-and-drop transitions. | **Medium** | Use a simple JS drag-and-drop library (like SortableJS) with a custom controller method to validate and trigger transitions. |
| **User-Customizable Dashboard** | Allow operators and admins to toggle and rearrange widgets (TAT breaches, ticket volume trends, assignee load) on the main dashboard. | **Low** | Store widget layouts (order and visibility) in local storage or user preferences, loading them dynamically on dashboard render. |

---

## 3. Smart Search & Advanced Filters

| Feature | Proposed Improvement | Priority | Implementation Suggestion |
| :--- | :--- | :--- | :--- |
| **Persistent List Filters** | Retain user-selected filters (assignee, priority, state) when navigating away from the ticket list page and returning. | **High** | Store the Datatable filter state in `sessionStorage` in `datatable.js` so it automatically restores filters on page load. |
| **Global Full-Text Search** | Implement search that queries not only ticket IDs but also comments, activity logs, and descriptions. | **Medium** | Add a MySQL FULLTEXT index on `tickets.description` and `ticket_comments.comment`, and run boolean mode searches. |
| **Advanced Query Builder** | A simple dropdown-based query builder to let power users search by expressions (e.g., `Status is resolved AND Priority is urgent AND Created between X and Y`). | **Medium** | Create a simple query builder UI returning a JSON payload of filters that the backend maps to SQL query builders in the model. |

---

## 4. Notification & Activity Management

| Feature | Proposed Improvement | Priority | Implementation Suggestion |
| :--- | :--- | :--- | :--- |
| **In-App Notification Center** | Replace the simple badge count on the topbar bell icon with a real-time notification drawer listing recent system alerts and updates. | **Medium** | Build a notifications table (`user_notifications`) to log alerts for users, and poll/load them dynamically in the notification drawer. |
| **Notification Subscriptions** | Let users configure which events trigger email alerts (e.g., notify on "assigned to me" and "escalated", but mute "comments added by others"). | **Low** | Add a `notification_settings` table to store user-specific preferences, and filter outgoing notifications based on these rules. |
| **Email Digests** | Group low-priority notifications (like comments added or non-urgent state shifts) into an hourly/daily email digest rather than immediate individual emails. | **Low** | Cache non-critical alerts in a queue and have a cron task compile and send them as HTML digest emails at set intervals. |

---

## 5. Workflow & Escalation Enhancements

| Feature | Proposed Improvement | Priority | Implementation Suggestion |
| :--- | :--- | :--- | :--- |
| **Support for Multiple Terminal States** | Allow a workflow definition to have multiple final/terminal states (e.g., `Closed - Success`, `Closed - Failed`, `Closed - Not Feasible`). | **High** | Remove the single final state restriction (`stateClearOtherFinal()`) in the backend, and allow multiple states to have `is_final = 1`. |
| **SLA/TAT Pausing ("On Hold")** | Define certain states as "Paused" (e.g., waiting on client dependencies) to stop the TAT escalation clock. | **High** | Add a `is_paused` flag to the `states` table. Update `tat_monitor.php` to subtract the time spent in paused states from the TAT calculations. |
| **Escalation Snoozing** | Allow supervisors to "snooze" an escalated ticket, pausing further automatic email escalations for a configured duration (e.g., 4 hours). | **Medium** | Add a `snoozed_until` column to the `tickets` table and update `tat_monitor.php` to skip escalated tickets that are currently snoozed. |

---

## 6. Role-Based Access & Module Configurability

| Feature | Proposed Improvement | Priority | Implementation Suggestion |
| :--- | :--- | :--- | :--- |
| **Granular Permission Mapping** | Move away from hardcoded role strings (`super_admin`) in code and map specific capabilities (`reopen_ticket`, `delete_comment`, `edit_workflow`) to roles. | **High** | Create a permissions matrix table (`role_permissions`) and use a helper function like `has_permission('reopen_ticket')` in controller guards. |
| **Configurable Side Navigation** | Make sidebar menu items load dynamically based on the permissions/roles of the logged-in user. | **Medium** | Define sidebar modules in a configuration array and render links in `sidebar.php` dynamically after filtering against user permissions. |

---

## 7. Settings & Dynamic Configuration

| Feature | Proposed Improvement | Priority | Implementation Suggestion |
| :--- | :--- | :--- | :--- |
| **Categorized Settings Panel** | Organize the settings dashboard into tabs (General, Email, SLA, Security, Integrations) for easier system management. | **Medium** | Reorganize the settings view using Bootstrap tabs and save values in categories in the database. |
| **Dynamic Configuration Caching** | Cache system settings in APCu (already implemented) and automatically invalidate the cache whenever settings are saved. | **Complete** | Handled. Invalidates settings cache on edit via `app_settings_clear_cache()`. |

---

## 8. Reporting & Analytics Enhancements

| Feature | Proposed Improvement | Priority | Implementation Suggestion |
| :--- | :--- | :--- | :--- |
| **Export Options** | Enable CSV, Excel, and PDF downloads of ticket lists, audit logs, and analytics reports. | **Medium** | Integrate a lightweight export library or generate CSV outputs directly via PHP streams (`php://output`). |
| **MTTR (Mean Time to Resolve)** | Add performance graphs showing average resolution times grouped by assignee, ticket priority, and flow category. | **Medium** | Calculate difference between `resolved_at`/`closed_at` and `created_at` in the model, and graph it in ChartJS on the Analytics tab. |

---

## 9. System Monitoring & Maintenance

| Feature | Proposed Improvement | Priority | Implementation Suggestion |
| :--- | :--- | :--- | :--- |
| **Cron Runs Dashboard** | A dashboard interface to monitor the execution logs, durations, and outputs of background tasks (e.g., `tat_monitor.php`). | **Medium** | Build a UI panel that queries and displays the `cron_runs` table data, complete with warnings for skipped or failed runs. |
| **System Diagnostics Panel** | A dashboard panel showing connection statuses, cache engine status (APCu vs. File), server disk usage, and mail queue health. | **Low** | Create an admin-only diagnostics route that runs system checks (disk space, PHP extensions, DB check) and returns a status panel. |

---

## 10. Performance Optimization Opportunities

| Feature | Proposed Improvement | Priority | Implementation Suggestion |
| :--- | :--- | :--- | :--- |
| **Query Eager Loading** | Avoid N+1 queries when loading ticket lists by joining assignee and creator details directly in the main query. | **High** | Update the datatable query in `app_model.php` to run LEFT JOINS on the `users` table instead of fetching user names individually. |
| **Historical Data Archiving** | Implement a background job to move resolved/closed tickets older than 1 year to an archive table, keeping active tables small and fast. | **Medium** | Create a `tickets_archive` table structure and append an archiving query inside the weekly cleanup cron sweep. |

---

## 11. Security & Audit Improvements

| Feature | Proposed Improvement | Priority | Implementation Suggestion |
| :--- | :--- | :--- | :--- |
| **Application Audit Trail** | Log administrative changes (workflow additions, role creations, settings edits) to a central system audit table. | **High** | Create a `system_audit_logs` table and log key events inside controller edit methods (noting who changed what configuration). |
| **Configurable Password Lockouts** | Lock user accounts temporarily after N failed login attempts to prevent brute-force attacks. | **Medium** | Track consecutive fails in the `users` table or query `login_attempts` within the last 15 minutes, blocking login if count exceeds limit. |
| **IP White-listing** | Allow restricting access to admin panels or the entire system to specific IP ranges. | **Low** | Create an IP validation filter (`IPFilter.php`) in CodeIgniter and apply it to sensitive routes like `/admin/*` via `app/Config/Filters.php`. |

---

## 12. UI/UX Modernization & Responsiveness

| Feature | Proposed Improvement | Priority | Implementation Suggestion |
| :--- | :--- | :--- | :--- |
| **Fully Responsive Sidebar** | Redesign the main navigation so it collapses cleanly on mobile viewports into a hamburger menu. | **High** | Modify `sidebar.php` and `index.css` to toggle a `.collapsed` CSS class, adjusting margins and text labels on smaller viewports. |
| **Dark Theme Toggle** | Add a visual light/dark mode theme selector in the user profile menu. | **Low** | Use CSS custom properties (variables) for colors and save theme choices (`light`/`dark`) in local storage or user profiles. |
