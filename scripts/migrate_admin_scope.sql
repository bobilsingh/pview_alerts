-- Migration: roles.is_admin_scope flag.
-- Removes the hardcoded `in_array($role, ['admin','super_admin'])` checks
-- scattered across the codebase. Any role with is_admin_scope=1 sees
-- system-wide ticket lists (instead of just their own/assigned tickets).
-- Custom roles get a checkbox in the role add/edit form to opt in.
--
-- super_admin is intentionally kept as a hardcoded master safety net —
-- the only path back if all module_permissions get misconfigured. Its
-- is_admin_scope is set to 1 here so the runtime helper agrees with the
-- hardcoded check, but the hardcoded check itself is the real guarantee.
--
-- Safe to re-run; idempotent.

USE alert_system;

-- ADD COLUMN guarded by information_schema so re-running is a no-op.
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema='alert_system'
             AND table_name='roles'
             AND column_name='is_admin_scope');
SET @s := IF(@c = 0,
  'ALTER TABLE roles ADD COLUMN is_admin_scope TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Seed built-in admin-tier roles. UPDATEs run unconditionally — if the
-- rows already have the right value the change is a no-op.
UPDATE roles SET is_admin_scope = 1 WHERE role_key IN ('super_admin', 'admin');
UPDATE roles SET is_admin_scope = 0 WHERE role_key = 'user';

SELECT 'admin_scope migration applied' AS result;
