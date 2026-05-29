<?php

/**
 * Admin Settings page.
 * Key/value rows from the `app_settings` table.
 * Bool-ish keys (current value is exactly "0" or "1") render as a checkbox.
 */

if (!function_exists('setting_label')) {
  function setting_label($key)
  {
    return ucwords(str_replace(['_', '.'], ' ', (string) $key));
  }
}

// Group keys logically so the page reads top-to-bottom.
$groups = [
  'Branding'      => ['app_name', 'login_show_demo_creds'],
  'Security'      => ['password_min_length', 'password_require_letter', 'password_require_digit', 'password_rotate_days', 'login_max_attempts', 'login_lockout_minutes'],
  'Rate Limiting' => ['api_rate_per_minute', 'api_rate_per_hour'],
  'Attachments'   => ['upload_max_mb', 'upload_allowed_ext', 'upload_blocked_ext'],
  'TAT defaults'  => ['default_tat_l1_minutes', 'default_tat_l2_minutes', 'default_tat_l3_minutes', 'default_tat_l4_minutes'],
  'UI'            => ['datatable_page_length', 'dashboard_trend_ranges'],
  'Live polling'  => ['live_poll_seconds', 'live_audio_enabled', 'live_browser_notify'],
  'Email / SMTP'  => ['email_protocol', 'email_smtp_host', 'email_smtp_port', 'email_smtp_user', 'email_smtp_pass', 'email_smtp_crypto', 'email_from_email', 'email_from_name'],
  'Notification queue' => ['notification_batch_size', 'notification_max_attempts'],
  // Bumping this number invalidates every browser's cached copy of
  // app.css / app.js — handy after a hot-fix edited on the server.
  'Assets'             => ['asset_version'],
];

// Keys that must always render as a number input even when their current
// value happens to be "0" or "1" (which would otherwise trip the bool detector).
$numericKeys = [
  'password_min_length',
  'password_rotate_days',
  'login_max_attempts',
  'login_lockout_minutes',
  'api_rate_per_minute',
  'api_rate_per_hour',
  'upload_max_mb',
  'default_tat_l1_minutes',
  'default_tat_l2_minutes',
  'default_tat_l3_minutes',
  'default_tat_l4_minutes',
  'datatable_page_length',
  'live_poll_seconds',
  'email_smtp_port',
  'notification_batch_size',
  'notification_max_attempts',
  'asset_version',
];

// Keys to render as a password input (masked). These all live in app_settings
// in plain text — see the admin note next to the Email / SMTP group.
$passwordKeys = ['email_smtp_pass'];

// Authoritative descriptions — override whatever is stored in the DB.
$descOverrides = [
  'app_name'                => 'Application name shown in the browser tab and sidebar.',
  'login_show_demo_creds'   => 'Show demo credentials on the login page (disable in production).',
  'password_min_length'     => 'Minimum number of characters required for a password.',
  'password_require_letter' => 'Require at least one letter in every password.',
  'password_require_digit'  => 'Require at least one digit in every password.',
  'password_rotate_days'    => 'Force password change after this many days (0 = disabled).',
  'login_max_attempts'       => 'Number of failed login attempts before an IP/account is temporarily locked out (0 = disabled).',
  'login_lockout_minutes'   => 'How long (in minutes) the lockout lasts after too many failed attempts.',
  'api_rate_per_minute'     => 'Maximum API requests per API key per minute (0 = no limit).',
  'api_rate_per_hour'       => 'Maximum API requests per API key per hour (0 = no limit).',
  'upload_max_mb'           => 'Maximum file size in megabytes allowed for ticket attachments.',
  'upload_allowed_ext'      => 'Comma-separated list of allowed attachment file extensions.',
  'upload_blocked_ext'      => 'Extra extensions to block on upload, comma-separated. Added on top of the built-in denylist (php, exe, sh, etc.).',
  'default_tat_l1_minutes'  => 'Default TAT (minutes) for Level 1 before auto-escalation.',
  'default_tat_l2_minutes'  => 'Default TAT (minutes) for Level 2 before auto-escalation.',
  'default_tat_l3_minutes'  => 'Default TAT (minutes) for Level 3 before auto-escalation.',
  'default_tat_l4_minutes'  => 'Default TAT (minutes) for Level 4 before auto-escalation.',
  'datatable_page_length'   => 'Default rows per page shown in all data tables across the app.',
  'dashboard_trend_ranges'  => 'Comma-separated day windows for the dashboard trend chart (e.g. "7,15,30"). The first value is the default range. Each must be between 1 and 365.',
  'live_poll_seconds'       => 'How often the bell badge / dashboard polls for new actionable tickets, in seconds (5-120). 0 disables polling.',
  'live_audio_enabled'      => 'Play a short audio cue when the actionable-ticket count rises during live polling.',
  'live_browser_notify'     => 'Show a browser notification (if the user has granted permission) when the actionable count rises.',
  'email_protocol'          => 'Mail protocol. Usually "smtp" — alternatives are "sendmail" or "mail".',
  'email_smtp_host'         => 'SMTP server hostname (e.g. mail.example.com).',
  'email_smtp_port'         => 'SMTP port. 587 for STARTTLS, 465 for implicit SSL, 25 for plain.',
  'email_smtp_user'         => 'SMTP username (often the from-address).',
  'email_smtp_pass'         => 'SMTP password. Stored in plain text in the database — anyone with DB read access can recover it. See the admin note above.',
  'email_smtp_crypto'       => 'Connection encryption: tls (port 587), ssl (port 465), or leave blank for none.',
  'email_from_email'        => 'From-address shown to recipients.',
  'email_from_name'         => 'Display name shown to recipients.',
  'notification_batch_size'   => 'Max notification_logs rows the cron worker processes per run (1-500).',
  'notification_max_attempts' => 'Give up on a pending notification after this many failed send attempts.',
  'asset_version'             => 'Cache-buster appended to public/assets/css/app.css and public/assets/js/app.js URLs. Bump this number by 1 after editing those files directly on the server so every browser pulls the fresh copy on next page load.',
];

// Index by key for fast lookup, and find any keys that don't fit
// into a known group so they still appear on the page.
$byKey = [];
foreach ($settings as $row) {
  $byKey[$row['setting_key']] = $row;
}
$known = [];
foreach ($groups as $keys) {
  foreach ($keys as $k) {
    $known[$k] = true;
  }
}
$other = [];
foreach ($byKey as $k => $row) {
  if (!isset($known[$k])) {
    $other[] = $k;
  }
}
if (!empty($other)) {
  $groups['Other'] = $other;
}
?>

<div class="page-head">
  <div>
    <h2>Settings</h2>
    <div class="subtitle">Tune the app without touching code. Values are read live from the database.</div>
  </div>
</div>

<?php if (empty($tableExists)) { ?>
  <div class="alert alert-warning">
    <h5 class="alert-heading mb-2"><i class="bi bi-exclamation-triangle"></i> Database upgrade required</h5>
    <p class="mb-2">
      The <code>app_settings</code> table doesn't exist yet, so there's nothing to edit on this page.
      Run the upgrade script to create it and seed the default values:
    </p>
    <pre class="bg-dark text-light p-2 mb-2"><code>mysql -u root -p &lt;your_db&gt; &lt; database_upgrade.sql</code></pre>
    <p class="mb-0 small text-muted">
      The same script also adds the <code>users.user_id</code> column used by the new login flow.
      It's idempotent &mdash; safe to re-run if you've partially upgraded already.
    </p>
  </div>
<?php } else if (empty($settings)) { ?>
  <div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    The <code>app_settings</code> table is empty. Insert the seed rows from
    <code>database_upgrade.sql</code> to populate the defaults.
  </div>
<?php } ?>

<form method="post" action="<?= site_url('settings/save'); ?>" data-loading-form="1">
  <?php foreach ($groups as $groupName => $keys) { ?>
    <?php
    // Skip an empty group (e.g. if a seeded key was deleted manually).
    $hasAny = false;
    foreach ($keys as $k) {
      if (isset($byKey[$k])) {
        $hasAny = true;
        break;
      }
    }
    if (!$hasAny) {
      continue;
    }
    ?>
    <div class="card mb-3">
      <div class="card-header">
        <strong><?= esc($groupName); ?></strong>
      </div>
      <div class="card-body">
        <?php if ($groupName === 'Email / SMTP') { ?>
          <div class="alert alert-warning py-2 small">
            <i class="bi bi-shield-exclamation"></i>
            <strong>Admin note:</strong> Configure SMTP settings carefully to ensure email notifications work properly. Use valid SMTP credentials and the correct port/crypto combination (TLS → 587, SSL → 465). Keep the SMTP password secure and avoid sharing database access with unauthorized users..
          </div>
        <?php } ?>
        <div class="row g-3">
          <?php foreach ($keys as $key) { ?>
            <?php
            if (!isset($byKey[$key])) {
              continue;
            }
            $row    = $byKey[$key];
            $value  = (string) $row['setting_value'];
            $desc   = isset($descOverrides[$key]) ? $descOverrides[$key] : (string) (isset($row['description']) ? $row['description'] : '');
            $isBool = ($value === '0' || $value === '1') && !in_array($key, $numericKeys, true);
            $isLong = (mb_strlen($value) > 80);
            $isPass = in_array($key, $passwordKeys, true);
            ?>
            <div class="col-md-6">
              <label class="form-label" for="set_<?= esc($key); ?>">
                <?= esc(setting_label($key)); ?>
              </label>

              <?php if ($isBool) { ?>
                <div class="form-check form-switch">
                  <input type="checkbox" class="form-check-input"
                    name="<?= esc($key); ?>" id="set_<?= esc($key); ?>"
                    value="1" <?php if ($value === '1') {
                                echo 'checked';
                              } ?>>
                  <label class="form-check-label" for="set_<?= esc($key); ?>">
                    Enabled
                  </label>
                </div>
              <?php } else if ($isPass) { ?>
                <input type="password" class="form-control"
                  name="<?= esc($key); ?>" id="set_<?= esc($key); ?>"
                  autocomplete="new-password"
                  value="<?= esc($value); ?>">
              <?php } else if ($isLong) { ?>
                <textarea class="form-control" rows="2"
                  name="<?= esc($key); ?>" id="set_<?= esc($key); ?>"><?= esc($value); ?></textarea>
              <?php } else { ?>
                <input type="text" class="form-control"
                  name="<?= esc($key); ?>" id="set_<?= esc($key); ?>"
                  value="<?= esc($value); ?>">
              <?php } ?>

              <?php if ($desc !== '') { ?>
                <small class="text-muted d-block mt-1"><?= esc($desc); ?></small>
              <?php } ?>
            </div>
          <?php } ?>
        </div>

        <?php if ($groupName === 'Email / SMTP') { ?>
          <div class="mt-3 d-flex align-items-center gap-2">
            <button type="button" id="sendTestEmailBtn"
              class="btn btn-outline-primary btn-sm"
              data-url="<?= site_url('settings/send_test_email'); ?>">
              <i class="bi bi-envelope-check"></i> Send test email to me
            </button>
            <small class="text-muted">Sends a one-line test using the saved settings, to <code><?= esc(session('user_email')); ?></code>. Save changes first.</small>
          </div>
        <?php } ?>

        <?php if ($groupName === 'Assets') { ?>
          <div class="mt-3 d-flex align-items-center gap-2">
            <button type="button" id="bumpAssetVersionBtn"
              class="btn btn-outline-primary btn-sm"
              data-url="<?= site_url('settings/bump_asset_version'); ?>">
              <i class="bi bi-arrow-up-circle"></i> Bump version
            </button>
            <small class="text-muted">Quick way to invalidate every browser's cached CSS / JS after a server-side edit. Increments the value by 1 and saves immediately.</small>
          </div>
        <?php } ?>
      </div>
    </div>
  <?php } ?>

  <div class="card mb-4">
    <div class="card-header">
      <strong>Permissions Management</strong>
    </div>
    <div class="card-body">
      <div class="row align-items-center">
        <div class="col-md-8">
          <p class="text-muted mb-0">Control sidebar visibility, dynamic page access, and granular CRUD action privileges (View, Add, Edit, Delete) for each role.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
          <a href="<?= site_url('module_control_panel'); ?>" class="btn btn-outline-primary">
            <i class="bi bi-shield-lock-fill"></i> Manage Module Permissions
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">
      <strong>Roles</strong>
    </div>
    <div class="card-body">
      <div class="row align-items-center">
        <div class="col-md-8">
          <p class="text-muted mb-0">Define the operator and administrator roles available in the system. Each role gets its own tab in the Module Permissions panel where you toggle View / Add / Edit / Delete access per module, and an admin-tier flag that controls whether the role sees all tickets system-wide.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
          <a href="<?= site_url('roles'); ?>" class="btn btn-outline-primary">
            <i class="bi bi-people-fill"></i> Manage Roles
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">
      <strong>Activity Log</strong>
    </div>
    <div class="card-body">
      <div class="row align-items-center">
        <div class="col-md-8">
          <p class="text-muted mb-0">Centralized read-only history of user events — logins, mutations, navigation, exports. Use it to investigate incidents, audit configuration changes, or track time-on-platform per operator.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
          <a href="<?= site_url('activity_logs'); ?>" class="btn btn-outline-primary">
            <i class="bi bi-clipboard-data"></i> View Activity Log
          </a>
        </div>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">
    <i class="bi bi-check-lg"></i>
    <span class="btn-label">Save Settings</span>
  </button>
  <a href="<?= site_url('dashboard'); ?>" class="btn btn-light">Cancel</a>
</form>