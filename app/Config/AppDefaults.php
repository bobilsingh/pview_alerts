<?php

namespace App\Config;

// Fallback values for app_setting() when a key is missing from the DB.
// All values are strings to match what app_settings_all() returns from the DB.
class AppDefaults
{
    public static $defaults = [
        'app_name'                     => 'pView Alert System',
        'login_show_demo_creds'        => '0',
        'maintenance_mode'             => '0',
        'password_min_length'          => '8',
        'password_require_letter'      => '1',
        'password_require_digit'       => '1',
        'password_rotate_days'         => '90',
        'login_max_attempts'           => '3',
        'login_lockout_minutes'        => '10',
        'session_idle_timeout_minutes' => '30',
        'api_rate_per_minute'          => '60',
        'api_rate_per_hour'            => '1000',
        'upload_max_mb'                => '10',
        'upload_allowed_ext'           => 'pdf,doc,docx,jpg,jpeg,png,xlsx,xls,csv,txt',
        'upload_blocked_ext'           => '',
        'default_tat_l1_minutes'       => '60',
        'default_tat_l2_minutes'       => '120',
        'default_tat_l3_minutes'       => '240',
        'default_tat_l4_minutes'       => '480',
        'datatable_page_length'        => '10',
        'dashboard_trend_ranges'       => '7,15,30',
        'live_poll_seconds'            => '15',
        'live_audio_enabled'           => '1',
        'live_browser_notify'          => '1',
        'analytics_refresh_seconds'    => '30',
        'email_protocol'               => 'smtp',
        'email_smtp_host'              => '',
        'email_smtp_port'              => '587',
        'email_smtp_user'              => '',
        'email_smtp_pass'              => '',
        'email_smtp_crypto'            => 'tls',
        'email_from_email'             => '',
        'email_from_name'              => 'pView Alert System',
        'notification_batch_size'      => '50',
        'notification_max_attempts'    => '5',
        'asset_version'                => '1',
    ];
}
