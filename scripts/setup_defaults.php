<?php

/**
 * Baseline Production/Development Setup Script.
 *
 * Wipes operational tables and seeds a clean, production-ready environment:
 * - Creates only a single role: super_admin
 * - Seeds permissions for super_admin for all modules dynamically
 * - Seeds all default app settings synced with environment config if available
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

say('==> Ensuring modules table exists...');
$db->query("CREATE TABLE IF NOT EXISTS `modules` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `module_key`  VARCHAR(50)  NOT NULL,
    `name`        VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    `is_builtin`  TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order`  INT          NOT NULL DEFAULT 100,
    `created_at`  DATETIME     NOT NULL,
    `created_by`  VARCHAR(100)          DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_module_key` (`module_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
say('  modules table ready');

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
    'cron_runs',
    'module_permissions',
    'modules',
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
    'role_key'       => 'super_admin',
    'label'          => 'Super Admin',
    'is_builtin'     => 1,
    'is_admin_scope' => 1,
    'sort_order'     => 1,
    'created_at'     => ts(),
    'updated_at'     => ts(),
]);
say('  role super_admin created');

// ---------- 3. Seed modules ----------

say('==> Seeding modules...');
$allModules = [
    ['settings',             'Settings',             'System settings — super_admin only.',                                        1,   5],
    ['dashboard',            'Dashboard',            'Main system status and active ticket overview charts.',                       0,  10],
    ['projects',             'Projects',             'Configure project namespaces and operational domains.',                       0,  20],
    ['flows',                'Flows',                'Define ticket state transition flow structures.',                             0,  30],
    ['alerts',               'Alert Defs',           'Map external telemetry thresholds to initial ticket states.',                0,  40],
    ['escalation',           'Escalation Matrix',    'Set up auto-escalation matrix levels and breached-TAT rules.',               0,  50],
    ['tickets',              'Tickets (My & Raise)', 'Create tickets manually and view assigned actionable lists.',                 0,  60],
    ['tickets_all',          'All Tickets',          'Comprehensive operational overview listing all ticket instances.',            0,  70],
    ['users',                'Users',                'System operator accounts, passwords, and assigned user roles.',               0,  80],
    ['api_keys',             'API Keys',             'Generate external telemetry system API keys for ticket injection.',           0,  90],
    ['roles',                'Roles',                'Manage custom roles beyond the built-in super_admin.',                       0, 100],
    ['activity_logs',        'Activity Log',         'Centralized audit + activity feed; read-only history of user events.',       0, 110],
    ['cron_panel',           'Cron Panel',           'View scheduled cron job run history and status.',                            0, 120],
    ['module_control_panel', 'Module Permissions',   'Control which roles can access each module and action.',                     1, 130],
];

foreach ($allModules as $m) {
    $db->table('modules')->insert([
        'module_key'  => $m[0],
        'name'        => $m[1],
        'description' => $m[2],
        'is_builtin'  => $m[3],
        'sort_order'  => $m[4],
        'created_at'  => ts(),
        'created_by'  => 'admin',
    ]);
    say("  module seeded: {$m[0]}");
}

// ---------- 4. Seed module permissions for super_admin ----------

say('==> Seeding module permissions for super_admin...');
foreach ($allModules as $m) {
    $db->table('module_permissions')->insert([
        'role'       => 'super_admin',
        'module_key' => $m[0],
        'can_view'   => 1,
        'can_add'    => 1,
        'can_edit'   => 1,
        'can_delete' => 1,
    ]);
    say("  permission seeded: super_admin -> {$m[0]}");
}

// ---------- 5. Seed single admin user with super_admin role ----------

say('==> Seeding single administrative user...');
$demoPw = password_hash('Demo@1234', PASSWORD_BCRYPT);
$db->table('users')->insert([
    'user_id'             => 'admin',
    'name'                => 'System Administrator',
    'email'               => 'bobil.singh@functionapps.in',
    'password'            => $demoPw,
    'role'                => 'super_admin',
    'phone'               => '+91-9027136352',
    'is_active'           => 1,
    'created_at'          => ts(),
    'updated_at'          => ts(),
    'password_changed_at' => ts(),
    'theme'               => 'dark',
]);
say('  user admin (super_admin) created (default password: Demo@1234)');

// ---------- 6. Seed default app settings ----------

say('==> Seeding default app settings...');
$settings = [
    // Branding
    ['app_name',                        'pView',          'Application display name'],
    ['login_show_demo_creds',           '0',              'Show demo login credentials on login page (0 = No, 1 = Yes)'],

    // Security
    ['maintenance_mode',                '0',              'Put the app into maintenance mode — only super_admin can log in (0 = off, 1 = on)'],
    ['password_min_length',             '8',              'Minimum password length required'],
    ['password_require_letter',         '1',              'Password must contain at least one letter (1 = Yes, 0 = No)'],
    ['password_require_digit',          '1',              'Password must contain at least one digit (1 = Yes, 0 = No)'],
    ['password_rotate_days',            '30',             'Days before forced password rotation (0 = disabled)'],
    ['login_max_attempts',              '3',              'Failed login attempts allowed per window before lockout'],
    ['login_lockout_minutes',           '10',             'Lockout window length in minutes'],
    ['session_idle_timeout_minutes',    '30',             'Minutes of inactivity before auto-logout (0 = disabled)'],

    // Rate limiting
    ['api_rate_per_minute',             '60',             'Max API requests per API key per minute (0 = disabled)'],
    ['api_rate_per_hour',               '1000',           'Max API requests per API key per hour (0 = disabled)'],

    // Attachments
    ['upload_max_mb',                   '10',             'Max file size allowed for uploads (in MB)'],
    ['upload_allowed_ext',              'pdf,doc,docx,jpg,jpeg,png,xlsx,xls,csv,txt', 'Allowed file extensions for upload'],
    ['upload_blocked_ext',              'php,exe',        'Extra blocked extensions on file upload'],

    // TAT defaults
    ['default_tat_l1_minutes',          '60',             'Default L1 Turn-Around Time (minutes)'],
    ['default_tat_l2_minutes',          '120',            'Default L2 Turn-Around Time (minutes)'],
    ['default_tat_l3_minutes',          '240',            'Default L3 Turn-Around Time (minutes)'],
    ['default_tat_l4_minutes',          '480',            'Default L4 Turn-Around Time (minutes)'],

    // UI
    ['datatable_page_length',           '10',             'Default items per page in DataTables'],
    ['dashboard_trend_ranges',          '7,15,30',        'Comma-separated list of day ranges shown as quick-select buttons on the dashboard trend chart'],

    // Live polling
    ['live_poll_seconds',               '15',             'How often the dashboard/bell badge polls for new tickets in seconds (5–120). Set to 0 to disable.'],
    ['live_audio_enabled',              '1',              'Play a short audio cue when the actionable-ticket count rises during live polling'],
    ['live_browser_notify',             '1',              'Show a browser notification when the actionable count rises'],
    ['analytics_refresh_seconds',       '30',             'How often the Analytics tab auto-refreshes in seconds (0 = disabled)'],

    // Email / SMTP
    ['email_from_email',  $_ENV['email.fromEmail']  ?? getenv('email.fromEmail')  ?: 'alert@functionapps.in',  'From-address shown to recipients'],
    ['email_from_name',   $_ENV['email.fromName']   ?? getenv('email.fromName')   ?: 'Functionapps Team',      'Display name shown to recipients'],
    ['email_protocol',    $_ENV['email.protocol']   ?? getenv('email.protocol')   ?: 'smtp',                   'SMTP protocol: smtp / sendmail / mail'],
    ['email_smtp_host',   $_ENV['email.SMTPHost']   ?? getenv('email.SMTPHost')   ?: 'mail.functionapps.in',   'SMTP server hostname'],
    ['email_smtp_port',   $_ENV['email.SMTPPort']   ?? getenv('email.SMTPPort')   ?: '587',                    'SMTP port'],
    ['email_smtp_crypto', $_ENV['email.SMTPCrypto'] ?? getenv('email.SMTPCrypto') ?: 'tls',                    'Encryption: tls (STARTTLS, port 587) / ssl (port 465) / blank for none'],
    ['email_smtp_user',   $_ENV['email.SMTPUser']   ?? getenv('email.SMTPUser')   ?: 'alert@functionapps.in',  'SMTP username'],
    ['email_smtp_pass',   $_ENV['email.SMTPPass']   ?? getenv('email.SMTPPass')   ?: '',                       'SMTP password'],

    // Notification queue
    ['notification_batch_size',         '50',             'Max notification rows the cron worker processes per run'],
    ['notification_max_attempts',       '5',              'Give up on a pending notification after this many failed send attempts'],

    // Maintenance & operations
    ['log_retention_days',              '30',             'Days to retain api_request_log and login_attempts rows (minimum 1)'],
    ['duplicate_detection_window_hours','24',             'Hours within which a new ticket with the same alert_type + project triggers a duplicate warning'],

    // Assets
    ['asset_version', '1', 'Cache-buster appended to CSS/JS URLs — bump by 1 after editing assets on the server'],
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
