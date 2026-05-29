<?php

/**
 * Baseline Production/Development Setup Script.
 *
 * Wipes operational tables and seeds a clean, production-ready environment:
 * - Creates only a single role: super_admin
 * - Seeds permissions for super_admin for all modules
 * - Seeds all default app settings
 * - Seeds a single administrative user: 'admin' (password: Demo@1234)
 *
 * Usage (from project root):
 *   php scripts/setup_defaults.php
 *
 * Refuses to run from the browser (it is destructive).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();

define('FCPATH',     realpath(__DIR__ . '/../public') . DIRECTORY_SEPARATOR);
define('APPPATH',    realpath(rtrim($paths->appDirectory,      '\\/ ')) . DIRECTORY_SEPARATOR);
define('ROOTPATH',   realpath(APPPATH . '..') . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory,   '\\/ ')) . DIRECTORY_SEPARATOR);
define('WRITEPATH',  realpath(rtrim($paths->writableDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('TESTPATH',   realpath(rtrim($paths->testsDirectory,    '\\/ ') ?: __DIR__) . DIRECTORY_SEPARATOR);

(new \CodeIgniter\Config\DotEnv(ROOTPATH))->load();
$envName = $_ENV['CI_ENVIRONMENT'] ?? $_SERVER['CI_ENVIRONMENT'] ?? getenv('CI_ENVIRONMENT') ?: 'development';
define('ENVIRONMENT', $envName);

require_once APPPATH . 'Config/Constants.php';
require_once SYSTEMPATH . 'Common.php';
if (is_file(APPPATH . 'Config/Boot/' . ENVIRONMENT . '.php')) {
    require_once APPPATH . 'Config/Boot/' . ENVIRONMENT . '.php';
}
\Config\Services::autoloader()->initialize(new \Config\Autoload(), new \Config\Modules())->register();

$db = \Config\Database::connect();

// ---------- Helpers ----------

function say($msg)
{
    echo $msg . "\n";
}

function ts()
{
    return date('Y-m-d H:i:s');
}

// ---------- 1. Wipe all operational and config tables ----------

say('==> Wiping operational and config tables...');
$db->query("SET FOREIGN_KEY_CHECKS=0");

$tablesToWipe = [
    'api_request_log',
    'login_attempts',
    'notification_logs',
    'ticket_actions',
    'tickets',
    'escalation_matrix',
    'alert_definitions',
    'api_keys',
    'states',
    'flows',
    'projects',
    'saved_filters',
    'user_notification_settings',
    'activity_logs',
    'alarm_id_sequence',
    'module_permissions',
    'roles',
    'users',
    'app_settings',
];

foreach ($tablesToWipe as $t) {
    $db->query("TRUNCATE TABLE `{$t}`");
    say("  truncated {$t}");
}
$db->query("SET FOREIGN_KEY_CHECKS=1");

// ---------- 2. Seed single super_admin role ----------

say('==> Seeding only the super_admin role...');
$db->table('roles')->insert([
    'role_key'   => 'super_admin',
    'label'      => 'Super Admin',
    'is_builtin' => 1,
    'sort_order' => 1,
    'created_at' => ts(),
    'updated_at' => ts(),
]);
say('  role super_admin created');

// ---------- 3. Seed only super_admin permissions ----------

say('==> Seeding module permissions for super_admin...');
$modules = [
    ['dashboard', 1, 1, 1, 1],
    ['projects', 1, 1, 1, 1],
    ['flows', 1, 1, 1, 1],
    ['alerts', 1, 1, 1, 1],
    ['escalation', 1, 1, 1, 1],
    ['tickets', 1, 1, 1, 1],
    ['tickets_all', 1, 1, 1, 1],
    ['users', 1, 1, 1, 1],
    ['api_keys', 1, 1, 1, 1],
    ['settings', 1, 1, 1, 1],
    ['module_control_panel', 1, 1, 1, 1],
    ['activity_logs', 1, 0, 0, 0],
];

foreach ($modules as $m) {
    $db->table('module_permissions')->insert([
        'role'       => 'super_admin',
        'module_key' => $m[0],
        'can_view'   => $m[1],
        'can_add'    => $m[2],
        'can_edit'   => $m[3],
        'can_delete' => $m[4],
    ]);
    say("  permission seeded: super_admin -> {$m[0]}");
}

// ---------- 4. Seed single admin user with super_admin role ----------

say('==> Seeding single administrative user...');
$demoPw = password_hash('Demo@1234', PASSWORD_BCRYPT);
$db->table('users')->insert([
    'user_id'             => 'admin',
    'name'                => 'System Administrator',
    'email'               => 'admin@pview.local',
    'password'            => $demoPw,
    'role'                => 'super_admin',
    'phone'               => '+91-99000-00000',
    'is_active'           => 1,
    'created_at'          => ts(),
    'updated_at'          => ts(),
    'password_changed_at' => ts(),
    'theme'               => 'dark',
]);
say('  user admin (super_admin) created (default password: Demo@1234)');

// ---------- 5. Seed default app settings ----------

say('==> Seeding default app settings...');
$settings = [
    ['api_rate_per_hour', '1000', 'Max API requests per API key per hour (0 = disabled)'],
    ['api_rate_per_minute', '60', 'Max API requests per API key per minute (0 = disabled)'],
    ['app_name', 'pView', 'Application display name'],
    ['datatable_page_length', '10', 'Default items per page in tables'],
    ['default_tat_l1_minutes', '60', 'Default L1 Turn-Around Time (minutes)'],
    ['default_tat_l2_minutes', '120', 'Default L2 Turn-Around Time (minutes)'],
    ['default_tat_l3_minutes', '240', 'Default L3 Turn-Around Time (minutes)'],
    ['default_tat_l4_minutes', '480', 'Default L4 Turn-Around Time (minutes)'],
    ['email_from_email', 'alert@functionapps.in', 'From-address shown to recipients.'],
    ['email_from_name', 'Functionapps Team', 'Display name shown to recipients.'],
    ['email_protocol', 'smtp', 'SMTP protocol: smtp / sendmail / mail.'],
    ['email_smtp_crypto', 'tls', 'Encryption: tls (STARTTLS, port 587), ssl (implicit SSL, port 465), or blank for none.'],
    ['email_smtp_host', 'mail.functionapps.in', 'SMTP server hostname.'],
    ['email_smtp_pass', '', 'SMTP password.'],
    ['email_smtp_port', '587', 'SMTP port.'],
    ['email_smtp_user', 'alert@functionapps.in', 'SMTP username.'],
    ['live_audio_enabled', '1', 'Play a short audio cue when the actionable-ticket count rises during live polling.'],
    ['live_browser_notify', '1', 'Show a browser notification when the actionable count rises.'],
    ['live_poll_seconds', '15', 'How often the dashboard/bell badge polls for new tickets, in seconds (range 5-120). Set to 0 to disable.'],
    ['login_lockout_minutes', '10', 'Lockout window length in minutes'],
    ['login_max_attempts', '3', 'Failed login attempts allowed per window before lockout'],
    ['login_show_demo_creds', '0', 'Show demo login credentials on login page (0 = No, 1 = Yes)'],
    ['notification_batch_size', '50', 'Max notification rows the cron worker processes per run.'],
    ['notification_max_attempts', '5', 'Give up on a pending notification after this many failed send attempts.'],
    ['password_min_length', '8', 'Minimum password length required'],
    ['password_require_digit', '1', 'Password must require at least one number'],
    ['password_require_letter', '1', 'Password must require at least one letter'],
    ['password_rotate_days', '30', 'Days before forced password rotation'],
    ['upload_allowed_ext', 'pdf,doc,docx,jpg,jpeg,png,xlsx,xls,csv,txt', 'Allowed file extensions for upload'],
    ['upload_blocked_ext', 'php,exe', 'Extra blocked extensions on file upload'],
    ['upload_max_mb', '10', 'Max file size allowed for uploads (in MB)'],
];

foreach ($settings as $s) {
    $db->table('app_settings')->insert([
        'setting_key'   => $s[0],
        'setting_value' => $s[1],
        'description'   => $s[2],
        'updated_at'    => ts(),
        'updated_by'    => 'admin',
    ]);
    say("  setting seeded: {$s[0]} = {$s[1]}");
}

say('==> baseline setup complete! System is ready.');
