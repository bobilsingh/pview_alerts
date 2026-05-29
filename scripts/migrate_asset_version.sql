-- Migration: admin-controllable asset_version setting.
-- Replaces the hardcoded ?v=NN cache-busters in templates/header.php with
-- a value read from app_settings. After making manual changes to
-- public/assets/css/app.css or public/assets/js/app.js on the dev server,
-- admins can force every browser to reload by bumping this number from
-- Settings → Assets.
-- Safe to re-run; INSERT IGNORE keeps the existing value when present.

USE alert_system;

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES (
  'asset_version',
  '1',
  'Cache-buster appended to CSS / JS URLs. Bump this number by 1 after editing public/assets/css/app.css or public/assets/js/app.js directly on the server so every browser pulls the fresh file.',
  NOW()
);

SELECT 'asset_version migration applied' AS result;
