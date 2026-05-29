-- Migration: register 'roles' as a manageable module in module_permissions.
-- Adds one row per existing role so the Module Control Panel grid shows
-- the 'roles' row right away. Defaults match module_registry()'s defaults
-- for the 'roles' entry: super_admin gets full access, everyone else 0.
-- Safe to re-run; idempotent via INSERT IGNORE on the (role, module_key)
-- unique key.

USE alert_system;

-- Built-in roles get the registry defaults.
INSERT IGNORE INTO module_permissions (role, module_key, can_view, can_add, can_edit, can_delete) VALUES
  ('super_admin', 'roles', 1, 1, 1, 1),
  ('admin',       'roles', 0, 0, 0, 0),
  ('user',        'roles', 0, 0, 0, 0);

-- Any custom role rows already in `roles` table get all-zeros — the
-- admin then toggles them on per role from the MCP.
INSERT IGNORE INTO module_permissions (role, module_key, can_view, can_add, can_edit, can_delete)
SELECT r.role_key, 'roles', 0, 0, 0, 0
FROM roles r
WHERE r.role_key NOT IN ('super_admin', 'admin', 'user');

SELECT 'roles module registration applied' AS result;
