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
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `module_key`            VARCHAR(50)  NOT NULL,
    `permission_module_key` VARCHAR(100) NULL,
    `permission_action`     VARCHAR(50)  NOT NULL DEFAULT 'view',
    `name`                  VARCHAR(100) NOT NULL,
    `category`              VARCHAR(100) NOT NULL DEFAULT 'General',
    `icon`                  VARCHAR(100) NOT NULL DEFAULT 'bi-circle',
    `uri_path`              VARCHAR(255) NULL,
    `show_in_menu`          TINYINT      NOT NULL DEFAULT 1,
    `description`           VARCHAR(255) NOT NULL DEFAULT '',
    `is_builtin`            TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order`            INT          NOT NULL DEFAULT 100,
    `created_at`            DATETIME     NOT NULL,
    `created_by`            VARCHAR(100)          DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_module_key` (`module_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
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
    [
        'module_key'            => 'dashboard',
        'permission_module_key' => 'dashboard',
        'permission_action'     => 'view',
        'name'                  => 'Dashboard',
        'category'              => 'Overview',
        'icon'                  => 'bi-speedometer2',
        'uri_path'              => 'dashboard',
        'show_in_menu'          => 1,
        'description'           => 'Main system status and active ticket overview charts.',
        'is_builtin'            => 0,
        'sort_order'            => 10,
    ],
    [
        'module_key'            => 'projects',
        'permission_module_key' => 'projects',
        'permission_action'     => 'view',
        'name'                  => 'Projects',
        'category'              => 'Configuration',
        'icon'                  => 'bi-folder2-open',
        'uri_path'              => 'projects',
        'show_in_menu'          => 1,
        'description'           => 'Configure project namespaces and operational domains.',
        'is_builtin'            => 0,
        'sort_order'            => 20,
    ],
    [
        'module_key'            => 'flows',
        'permission_module_key' => 'flows',
        'permission_action'     => 'view',
        'name'                  => 'Flows',
        'category'              => 'Configuration',
        'icon'                  => 'bi-diagram-3',
        'uri_path'              => 'flows',
        'show_in_menu'          => 1,
        'description'           => 'Define ticket state transition flow structures.',
        'is_builtin'            => 0,
        'sort_order'            => 30,
    ],
    [
        'module_key'            => 'alerts',
        'permission_module_key' => 'alerts',
        'permission_action'     => 'view',
        'name'                  => 'Alert Defs',
        'category'              => 'Configuration',
        'icon'                  => 'bi-bell-fill',
        'uri_path'              => 'alerts',
        'show_in_menu'          => 1,
        'description'           => 'Map external telemetry thresholds to initial ticket states.',
        'is_builtin'            => 0,
        'sort_order'            => 40,
    ],
    [
        'module_key'            => 'escalation',
        'permission_module_key' => 'escalation',
        'permission_action'     => 'view',
        'name'                  => 'Escalation Matrix',
        'category'              => 'Configuration',
        'icon'                  => 'bi-graph-up-arrow',
        'uri_path'              => 'escalation',
        'show_in_menu'          => 1,
        'description'           => 'Set up auto-escalation matrix levels and breached-TAT rules.',
        'is_builtin'            => 0,
        'sort_order'            => 50,
    ],
    [
        'module_key'            => 'tickets',
        'permission_module_key' => 'tickets',
        'permission_action'     => 'view',
        'name'                  => 'My Tickets',
        'category'              => 'Operations',
        'icon'                  => 'bi-inbox-fill',
        'uri_path'              => 'tickets',
        'show_in_menu'          => 1,
        'description'           => 'Create tickets manually and view assigned actionable lists.',
        'is_builtin'            => 0,
        'sort_order'            => 60,
    ],
    [
        'module_key'            => 'tickets_create',
        'permission_module_key' => 'tickets',
        'permission_action'     => 'add',
        'name'                  => 'Raise Ticket',
        'category'              => 'Operations',
        'icon'                  => 'bi-plus-square',
        'uri_path'              => 'tickets/create',
        'show_in_menu'          => 1,
        'description'           => 'Form to manually raise a new alert',
        'is_builtin'            => 0,
        'sort_order'            => 65,
    ],
    [
        'module_key'            => 'tickets_all',
        'permission_module_key' => 'tickets_all',
        'permission_action'     => 'view',
        'name'                  => 'All Tickets',
        'category'              => 'Operations',
        'icon'                  => 'bi-list-task',
        'uri_path'              => 'tickets/all',
        'show_in_menu'          => 1,
        'description'           => 'Comprehensive operational overview listing all ticket instances.',
        'is_builtin'            => 0,
        'sort_order'            => 70,
    ],
    [
        'module_key'            => 'users',
        'permission_module_key' => 'users',
        'permission_action'     => 'view',
        'name'                  => 'Users',
        'category'              => 'System',
        'icon'                  => 'bi-people-fill',
        'uri_path'              => 'users',
        'show_in_menu'          => 1,
        'description'           => 'System operator accounts, passwords, and assigned user roles.',
        'is_builtin'            => 0,
        'sort_order'            => 80,
    ],
    [
        'module_key'            => 'api_keys',
        'permission_module_key' => 'api_keys',
        'permission_action'     => 'view',
        'name'                  => 'API Keys',
        'category'              => 'System',
        'icon'                  => 'bi-key-fill',
        'uri_path'              => 'api_keys',
        'show_in_menu'          => 1,
        'description'           => 'Generate external telemetry system API keys for ticket injection.',
        'is_builtin'            => 0,
        'sort_order'            => 90,
    ],
    [
        'module_key'            => 'roles',
        'permission_module_key' => 'roles',
        'permission_action'     => 'view',
        'name'                  => 'Roles',
        'category'              => 'Administration',
        'icon'                  => 'bi-person-badge',
        'uri_path'              => 'roles',
        'show_in_menu'          => 1,
        'description'           => 'Manage custom roles beyond the built-in super_admin.',
        'is_builtin'            => 0,
        'sort_order'            => 100,
    ],
    [
        'module_key'            => 'activity_logs',
        'permission_module_key' => 'activity_logs',
        'permission_action'     => 'view',
        'name'                  => 'Activity Log',
        'category'              => 'System',
        'icon'                  => 'bi-clipboard-data',
        'uri_path'              => 'activity_logs',
        'show_in_menu'          => 1,
        'description'           => 'Centralized audit + activity feed; read-only history of user events.',
        'is_builtin'            => 0,
        'sort_order'            => 110,
    ],
    [
        'module_key'            => 'cron_panel',
        'permission_module_key' => 'cron_panel',
        'permission_action'     => 'view',
        'name'                  => 'Cron Panel',
        'category'              => 'System',
        'icon'                  => 'bi-clock-history',
        'uri_path'              => 'cron_panel',
        'show_in_menu'          => 1,
        'description'           => 'View scheduled cron job run history and status.',
        'is_builtin'            => 0,
        'sort_order'            => 120,
    ],
    [
        'module_key'            => 'settings',
        'permission_module_key' => 'settings',
        'permission_action'     => 'view',
        'name'                  => 'Settings',
        'category'              => 'Administration',
        'icon'                  => 'bi-gear-fill',
        'uri_path'              => 'settings',
        'show_in_menu'          => 1,
        'description'           => 'System settings — super_admin only.',
        'is_builtin'            => 1,
        'sort_order'            => 125,
    ],
    [
        'module_key'            => 'module_control_panel',
        'permission_module_key' => 'module_control_panel',
        'permission_action'     => 'view',
        'name'                  => 'Manage Modules',
        'category'              => 'Administration',
        'icon'                  => 'bi-shield-lock-fill',
        'uri_path'              => 'module_control_panel',
        'show_in_menu'          => 1,
        'description'           => 'Control which roles can access each module and action.',
        'is_builtin'            => 1,
        'sort_order'            => 130,
    ],
];

foreach ($allModules as $m) {
    $db->table('modules')->insert([
        'module_key'            => $m['module_key'],
        'permission_module_key' => $m['permission_module_key'],
        'permission_action'     => $m['permission_action'],
        'name'                  => $m['name'],
        'category'              => $m['category'],
        'icon'                  => $m['icon'],
        'uri_path'              => $m['uri_path'],
        'show_in_menu'          => $m['show_in_menu'],
        'description'           => $m['description'],
        'is_builtin'            => $m['is_builtin'],
        'sort_order'            => $m['sort_order'],
        'created_at'            => ts(),
        'created_by'            => 'admin',
    ]);
    say("  module seeded: {$m['module_key']}");
}

// ---------- 4. Seed module permissions for super_admin ----------

say('==> Seeding module permissions for super_admin...');
foreach ($allModules as $m) {
    $db->table('module_permissions')->insert([
        'role'       => 'super_admin',
        'module_key' => $m['module_key'],
        'can_view'   => 1,
        'can_add'    => 1,
        'can_edit'   => 1,
        'can_delete' => 1,
    ]);
    say("  permission seeded: super_admin -> {$m['module_key']}");
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
    ['client_name',                     'AlertOps',       'Client organization display name'],
    ['app_logo',                        '',               'Path to custom uploaded logo'],
    ['app_favicon',                     '',               'Path to custom uploaded favicon'],
    ['primary_color',                   '',               'Primary theme color (hex format, e.g. #0792cd)'],
    ['secondary_color',                 '',               'Secondary theme color (hex format, e.g. #0476a7)'],
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
