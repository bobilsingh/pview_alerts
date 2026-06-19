<?php

// App settings helpers. Two-layer cache: per-request static + 5-min CI4 cache.
if (!function_exists('app_settings_all')) {
    function app_settings_all()
    {
        static $reqCache = null;
        if ($reqCache !== null) {
            return $reqCache;
        }

        // Check the cross-request CI4 cache before hitting the DB.
        $cacheKey = 'pview_app_settings';
        $cached   = null;
        try {
            $cached = cache($cacheKey);
        } catch (\Throwable $e) {
            log_message('warning', 'pview alert >> app_settings_all() cache read failed: ' . $e->getMessage());
        }
        if (is_array($cached)) {
            $reqCache = $cached;
            return $reqCache;
        }

        // Cache miss — re-fetch from DB and persist for 5 minutes.
        $reqCache = [];
        try {
            $rows = model('Helper_model')->getSettings();
            foreach ($rows as $r) {
                $reqCache[(string) $r['setting_key']] = (string) $r['setting_value'];
            }
            cache()->save($cacheKey, $reqCache, 300);
        } catch (\Throwable $e) {
            log_message('error', 'pview alert >> app_settings_all() failed: ' . $e->getMessage());
        }
        return $reqCache;
    }
}

if (!function_exists('app_settings_clear_cache')) {
    // Deletes the cache key so the next request re-reads settings from DB.
    function app_settings_clear_cache()
    {
        cache()->delete('pview_app_settings');
    }
}
if (!function_exists('app_setting')) {
    // Returns a single app setting by key; checks AppDefaults before the caller-supplied fallback.
    function app_setting($key, $default = null)
    {
        $map = app_settings_all();
        if (array_key_exists((string) $key, $map)) {
            return $map[(string) $key];
        }
        $codeDefaults = \App\Config\AppDefaults::$defaults;
        if (isset($codeDefaults[(string) $key])) {
            return $codeDefaults[(string) $key];
        }
        return $default;
    }
}
if (!function_exists('app_setting_int')) {
    function app_setting_int($key, $default = 0)
    {
        $v = app_setting($key, $default);
        return (int) $v;
    }
}
if (!function_exists('app_setting_bool')) {
    function app_setting_bool($key, $default = false)
    {
        $v = app_setting($key, null);
        if ($v === null) {
            return (bool) $default;
        }
        return ((string) $v === '1');
    }
}
if (!function_exists('app_setting_csv')) {
    /** Parse a comma-separated setting into an array of trimmed lowercased tokens. */
    function app_setting_csv($key, $default = [])
    {
        $v = app_setting($key, null);
        if ($v === null || $v === '') {
            return (array) $default;
        }
        $parts = array_filter(array_map('trim', explode(',', strtolower((string) $v))));
        return array_values($parts);
    }
}

if (!function_exists('get_global_date_range')) {
    function get_global_date_range()
    {
        $session = \Config\Services::session();
        $range = $session->get('global_date_range');
        if (empty($range) || !is_array($range) || !isset($range['preset'])) {
            $range = [
                'preset' => '7d',
                'start'  => date('Y-m-d', strtotime('-6 days')),
                'end'    => date('Y-m-d')
            ];
            $session->set('global_date_range', $range);
        } else {
            $preset = $range['preset'];
            if ($preset !== 'custom') {
                $start = '';
                $end = '';
                if ($preset === 'today') {
                    $start = date('Y-m-d');
                    $end = date('Y-m-d');
                } elseif ($preset === 'yesterday') {
                    $start = date('Y-m-d', strtotime('-1 day'));
                    $end = date('Y-m-d', strtotime('-1 day'));
                } elseif ($preset === '7d') {
                    $start = date('Y-m-d', strtotime('-6 days'));
                    $end = date('Y-m-d');
                } elseif ($preset === '30d') {
                    $start = date('Y-m-d', strtotime('-29 days'));
                    $end = date('Y-m-d');
                } elseif ($preset === '90d') {
                    $start = date('Y-m-d', strtotime('-89 days'));
                    $end = date('Y-m-d');
                } elseif ($preset === 'this_month') {
                    $start = date('Y-m-01');
                    $end = date('Y-m-d');
                } elseif ($preset === 'last_month') {
                    $start = date('Y-m-01', strtotime('first day of last month'));
                    $end = date('Y-m-t', strtotime('last day of last month'));
                } else {
                    $preset = '7d';
                    $start = date('Y-m-d', strtotime('-6 days'));
                    $end = date('Y-m-d');
                }

                if ($range['start'] !== $start || $range['end'] !== $end || $range['preset'] !== $preset) {
                    $range['preset'] = $preset;
                    $range['start'] = $start;
                    $range['end'] = $end;
                    $session->set('global_date_range', $range);
                }
            }
        }
        return $range;
    }
}

if (!function_exists('get_global_date_range_label')) {
    function get_global_date_range_label($preset, $start, $end)
    {
        $presetLabels = [
            'today'      => 'Today',
            'yesterday'  => 'Yesterday',
            '7d'         => 'Last 7 Days',
            '30d'        => 'Last 30 Days',
            '90d'        => 'Last 90 Days',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'custom'     => 'Custom Range',
        ];
        if ($preset === 'custom') {
            $formattedStart = date('d-M-Y', strtotime($start));
            if ($start === $end) {
                return $formattedStart;
            }
            $formattedEnd = date('d-M-Y', strtotime($end));
            return $formattedStart . ' - ' . $formattedEnd;
        }
        if (isset($presetLabels[$preset])) {
            return $presetLabels[$preset];
        }
        return 'Last 7 Days';
    }
}

if (!function_exists('or_default')) {
    // Returns $value when non-empty, otherwise $default.
    function or_default($value, $default)
    {
        if (empty($value)) {
            return $default;
        }
        return $value;
    }
}

if (!function_exists('bool_int')) {
    // Converts truthy/falsy to 1/0 for DB boolean columns.
    function bool_int($value)
    {
        if ($value) {
            return 1;
        }
        return 0;
    }
}

// --- Auth / Role guards ---
if (!function_exists('check_isvalidated')) {
    function check_isvalidated()
    {
        $session = \Config\Services::session();
        $session->start();
        if (logged_user_id() === '') {
            // Save the attempted URL on the new session so do_login() can redirect back after auth.
            $path        = (string) service('request')->getUri()->getPath();
            $redirectUrl = null;
            if (strpos($path, 'login') === false && strpos($path, 'logout') === false) {
                $redirectUrl = current_url();
            }
            $session->destroy();
            if ($redirectUrl !== null) {
                $session->set('redirect_after_login', $redirectUrl);
            }
            redirect()->to(site_url('login'))->with('error', 'Your session has expired. Please sign in again.')->send();
            exit;
        }
        // Maintenance mode — bounce non-admin users to the maintenance page.
        if (app_setting_bool('maintenance_mode', false)) {
            $role = (string) $session->get('user_role');
            if (!role_has_admin_scope($role)) {
                $path = (string) service('request')->getUri()->getPath();
                if (
                    strpos($path, 'maintenance') === false
                    && strpos($path, 'logout') === false
                ) {
                    redirect()->to(site_url('maintenance'))->send();
                    exit;
                }
            }
        }
        // Check if the current request is a background polling request.
        $isBackgroundPoll = false;
        $requestUri = (string) service('request')->getUri()->getPath();
        if (
            strpos($requestUri, 'notifications/actionable_count') !== false
            || strpos($requestUri, 'activity_logs/analytics') !== false
        ) {
            $isBackgroundPoll = true;
        }

        // Idle session timeout — 0 means disabled (default).
        $timeoutMin = app_setting_int('session_timeout_minutes', 0);
        if ($timeoutMin > 0) {
            $lastActivity = (int) $session->get('last_activity');
            if ($lastActivity > 0 && (time() - $lastActivity) > ($timeoutMin * 60)) {
                $session->destroy();
                redirect()->to(site_url('login'))->with('error', 'Your session has expired. Please log in again.')->send();
                exit;
            }
        }
        if (!$isBackgroundPoll) {
            $session->set('last_activity', time());
        }
        // Force password change if flagged; exempt /password/change and /logout
        // to avoid a redirect loop.
        if ($session->get('password_must_rotate')) {
            $path = (string) service('request')->getUri()->getPath();
            $isExempt = (strpos($path, 'password/change') !== false)
                || (strpos($path, 'logout') !== false);
            if (!$isExempt) {
                redirect()->to(site_url('password/change'))->send();
                exit;
            }
        }
    }
}

if (!function_exists('check_issuperadmin')) {
    function check_issuperadmin()
    {
        $session = \Config\Services::session();
        $session->start();
        $role = $session->get('user_role');
        if ($role !== ROLE_SUPER_ADMIN) {
            redirect()->to(site_url('dashboard'))->send();
            exit;
        }
    }
}

// Returns role keys the current actor may assign: super_admin = all, admin-scope = non-super, else = non-admin only.
if (!function_exists('assignable_role_keys')) {
    function assignable_role_keys($actor_role = null)
    {
        if ($actor_role === null) {
            $actor_role = (string) logged_user_role();
        }
        $actor_role = (string) $actor_role;
        if ($actor_role === '') {
            return [];
        }

        static $cache = [];
        if (isset($cache[$actor_role])) {
            return $cache[$actor_role];
        }

        $actorIsSuper      = ($actor_role === ROLE_SUPER_ADMIN);
        $actorIsAdminScope = role_has_admin_scope($actor_role);

        $allowed = [];
        try {
            $rows = model('Helper_model')->getAssignableRoles();
            foreach ($rows as $r) {
                $key            = (string) $r['role_key'];
                $isSuper        = ($key === ROLE_SUPER_ADMIN);
                $adminScopeVal = 0;
                if (isset($r['is_admin_scope'])) {
                    $adminScopeVal = $r['is_admin_scope'];
                }
                $isAdminScope   = ((int) $adminScopeVal) === 1;

                if ($isSuper && !$actorIsSuper) {
                    continue;
                }
                if ($isAdminScope && !$actorIsAdminScope) {
                    continue;
                }
                $allowed[] = $key;
            }
        } catch (\Throwable $e) {
            log_message('error', 'assignable_role_keys() lookup failed: ' . $e->getMessage());
        }
        $cache[$actor_role] = $allowed;
        return $allowed;
    }
}

// Returns true when the role has admin-scope (sees all tickets). super_admin is always true as a safety net.
if (!function_exists('role_has_admin_scope')) {
    function role_has_admin_scope($role = null)
    {
        if ($role === null) {
            $role = (string) \Config\Services::session()->get('user_role');
        }
        $role = (string) $role;
        if ($role === '') {
            return false;
        }
        if ($role === ROLE_SUPER_ADMIN) {
            return true;
        }
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            try {
                $helperModel = model('Helper_model');
                // Defensive: roles table may not exist on very old installs.
                $tableCheck = $helperModel->checkTableExistsRaw('roles');
                if (!empty($tableCheck)) {
                    $cache = $helperModel->getRolesScopeMap();
                }
            } catch (\Throwable $e) {
                log_message('error', 'role_has_admin_scope() lookup failed: ' . $e->getMessage());
            }
        }
        if (isset($cache[$role])) {
            return $cache[$role];
        }
        // Fallback for older installs before the is_admin_scope column was added.
        return ($role === 'admin');
    }
}

// Strict ALM-YYYYMMDD-XXXXX format check — blocks path-traversal via URL segments.
if (!function_exists('safe_alarm_id')) {
    function safe_alarm_id($alarm_id)
    {
        $alarm_id = (string) $alarm_id;
        if (preg_match('/^ALM-\d{8}-\d{5}$/', $alarm_id)) {
            return $alarm_id;
        }
        return false;
    }
}

if (!function_exists('verify_ticket_access')) {
    function verify_ticket_access($ticket, $checkUid = null, $checkRole = null)
    {
        if (empty($ticket)) {
            return false;
        }

        if ($checkRole !== null) {
            $role = $checkRole;
        } else {
            $role = logged_user_role();
        }

        if (role_has_admin_scope($role)) {
            return $ticket;
        }

        if ($checkUid !== null) {
            $uid = (string) $checkUid;
        } else {
            $uid = (string) logged_user_id();
        }
        $rawAssignee = '';
        if (isset($ticket['current_assignee'])) {
            $rawAssignee = $ticket['current_assignee'];
        }
        $assignee = (string) $rawAssignee;

        $rawRaisedBy = '';
        if (isset($ticket['raised_by'])) {
            $rawRaisedBy = $ticket['raised_by'];
        }
        $raisedBy = (string) $rawRaisedBy;

        if ($uid !== '' && $assignee === $uid) {
            return $ticket;
        }
        if ($uid !== '' && $raisedBy === $uid) {
            return $ticket;
        }

        $stateId = 0;
        if (isset($ticket['current_state_id'])) {
            $stateId = $ticket['current_state_id'];
        }
        $state = model('App_model')->stateGetById($stateId);
        if ($state) {
            for ($lvl = 1; $lvl <= 4; $lvl++) {
                $lvlKey = 'l' . $lvl . '_user_ids';
                $rawLvlVal = '[]';
                if (isset($state[$lvlKey])) {
                    $rawLvlVal = $state[$lvlKey];
                }
                $arr = json_decode((string) $rawLvlVal, true);
                if (is_array($arr) && in_array($uid, array_map('strval', $arr), true)) {
                    return $ticket;
                }
            }
        }

        return false;
    }
}

if (!function_exists('logged_user_id')) {
    function logged_user_id()
    {
        $uid = \Config\Services::session()->get('user_id');
        if (!empty($uid)) {
            return (string) $uid;
        }
        return '';
    }
}

if (!function_exists('logged_user_name')) {
    function logged_user_name()
    {
        $name = \Config\Services::session()->get('user_name');
        if (empty($name)) {
            return '';
        }
        return $name;
    }
}
if (!function_exists('logged_user_role')) {
    function logged_user_role()
    {
        $role = \Config\Services::session()->get('user_role');
        if (empty($role)) {
            return '';
        }
        return $role;
    }
}

// Reads a key from the session's dashboard_layout JSON (loaded at login — no DB hit).
if (!function_exists('user_dashboard_pref')) {
    function user_dashboard_pref($key, $default = null)
    {
        $layout = \Config\Services::session()->get('dashboard_layout');
        if (!is_array($layout)) {
            return $default;
        }
        if (!array_key_exists($key, $layout)) {
            return $default;
        }
        return $layout[$key];
    }
}

// Atomic daily sequence via LAST_INSERT_ID() — concurrent inserts get unique numbers.
if (!function_exists('generate_alarm_id')) {
    function generate_alarm_id()
    {
        $today = date('Ymd');
        $num = model('Helper_model')->incrementAlarmSequence($today);
        return 'ALM-' . $today . '-' . str_pad((string) $num, 5, '0', STR_PAD_LEFT);
    }
}

// Email template builders — inline styles only (email clients strip <style> tags).
if (!function_exists('mail_subject')) {
    function mail_subject($event, $context)
    {
        $alarm = 'ALERT';
        if (isset($context['alarm_id'])) {
            $alarm = $context['alarm_id'];
        }
        $title = '';
        if (isset($context['title'])) {
            $title = $context['title'];
        }
        $rawSev = '';
        if (isset($context['alert_type'])) {
            $rawSev = $context['alert_type'];
        }
        $sev   = strtoupper((string) $rawSev);
        $lvl   = 0;
        if (isset($context['level'])) {
            $lvl   = (int) $context['level'];
        }

        $tag = '[' . $sev . ']';
        if ($sev === '') {
            $tag = '[ALERT]';
        }

        switch ($event) {
            case 'created':
                return $tag . ' New ticket ' . $alarm . ' — ' . $title;
            case 'assigned':
                return $tag . ' Ticket ' . $alarm . ' assigned to you';
            case 'state_changed':
                $to = '';
                if (isset($context['to_state_name'])) {
                    $to = $context['to_state_name'];
                }
                return $tag . ' ' . $alarm . ' moved → ' . $to;
            case 'level_escalated':
                $newLvl = $lvl;
                if (isset($context['to_level'])) {
                    $newLvl = (int) $context['to_level'];
                }
                return '[ESCALATED L' . $newLvl . '] ' . $alarm . ' — ' . $title;
            case 'resolved':
                return '[RESOLVED] ' . $alarm . ' — ' . $title;
            case 'closed':
                return '[CLOSED] ' . $alarm;
            case 'tat_breach':
                return '[TAT BREACH] ' . $alarm . ' is overdue';
            case 'tat_warning':
                return '[SLA WARNING L' . $lvl . '] ' . $alarm . ' approaching TAT breach';
        }
        return $tag . ' ' . $alarm;
    }
}

if (!function_exists('mail_chip_span')) {
    function mail_chip_span($text, $bg, $fg = '#FFFFFF')
    {
        return '<span style="display:inline-block;padding:3px 9px;border-radius:10px;font-size:11px;font-weight:700;letter-spacing:0.5px;background:' . $bg . ';color:' . $fg . ';">' . esc($text) . '</span>';
    }
}

if (!function_exists('mail_kv_row')) {
    function mail_kv_row($label, $value)
    {
        if ($value === '' || $value === null) {
            $value = '—';
        }
        return '<tr>'
            . '<td style="padding:6px 12px;border-bottom:1px solid #E2E8F0;color:#64748B;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;width:35%;">' . esc($label) . '</td>'
            . '<td style="padding:6px 12px;border-bottom:1px solid #E2E8F0;color:#0F172A;font-size:14px;">' . $value . '</td>'
            . '</tr>';
    }
}

if (!function_exists('mail_html_body')) {
    function mail_html_body($event, $context)
    {
        $rawAlarm = '';
        if (isset($context['alarm_id'])) {
            $rawAlarm = $context['alarm_id'];
        }
        $alarm = (string) $rawAlarm;

        $rawTitle = '';
        if (isset($context['title'])) {
            $rawTitle = $context['title'];
        }
        $title = (string) $rawTitle;

        $rawDesc = '';
        if (isset($context['description'])) {
            $rawDesc = $context['description'];
        }
        $desc = (string) $rawDesc;

        $rawSeverity = 'info';
        if (isset($context['alert_type'])) {
            $rawSeverity = $context['alert_type'];
        }
        $severity  = strtolower((string) $rawSeverity);

        $rawPriority = 'medium';
        if (isset($context['priority'])) {
            $rawPriority = $context['priority'];
        }
        $priority  = strtolower((string) $rawPriority);

        $rawProject = '';
        if (isset($context['project_name'])) {
            $rawProject = $context['project_name'];
        }
        $project   = (string) $rawProject;

        $rawFlow = '';
        if (isset($context['flow_name'])) {
            $rawFlow = $context['flow_name'];
        }
        $flow      = (string) $rawFlow;

        $rawState = '';
        if (isset($context['state_name'])) {
            $rawState = $context['state_name'];
        }
        $state     = (string) $rawState;

        $rawLevel = 1;
        if (isset($context['level'])) {
            $rawLevel = $context['level'];
        }
        $level     = (int) $rawLevel;

        $rawTatExp = '';
        if (isset($context['tat_expires_at'])) {
            $rawTatExp = $context['tat_expires_at'];
        }
        $tatExp    = (string) $rawTatExp;

        $rawUrl = '#';
        if (isset($context['ticket_url'])) {
            $rawUrl = $context['ticket_url'];
        }
        $url       = (string) $rawUrl;

        $rawActor = 'system';
        if (isset($context['actor_name'])) {
            $rawActor = $context['actor_name'];
        }
        $actor     = (string) $rawActor;

        $rawRecipient = 'team';
        if (isset($context['recipient_name'])) {
            $rawRecipient = $context['recipient_name'];
        }
        $recipient = (string) $rawRecipient;

        $rawComment = '';
        if (isset($context['comment'])) {
            $rawComment = $context['comment'];
        }
        $comment   = (string) $rawComment;

        $appName   = (string) app_setting('app_name', 'pView Alert System');

        $palette = [
            'info'     => ['#0EA5E9', '#0284C7', 'INFO'],
            'major'    => ['#F59E0B', '#B45309', 'MAJOR'],
            'critical' => ['#EF4444', '#B91C1C', 'CRITICAL'],
        ];

        $pal = $palette['info'];
        if (isset($palette[$severity])) {
            $pal = $palette[$severity];
        }
        $sevColor = $pal[0];
        $sevDark  = $pal[1];
        $sevLabel = $pal[2];

        $prio = [
            'low'    => ['#94A3B8', 'LOW'],
            'medium' => ['#3B82F6', 'MEDIUM'],
            'high'   => ['#F97316', 'HIGH'],
            'urgent' => ['#DC2626', 'URGENT'],
        ];
        $pp = $prio['medium'];
        if (isset($prio[$priority])) {
            $pp = $prio[$priority];
        }

        $headline = 'Alert update';
        $lead     = 'There is an update on ticket ' . $alarm . '.';
        $bannerBg = $sevColor;
        switch ($event) {
            case 'created':
                $headline = 'New alert raised';
                $lead     = 'A new ' . strtolower($sevLabel) . ' alert has been raised and is now in <strong>' . esc($state) . '</strong>.';
                break;
            case 'assigned':
                $headline = 'Ticket assigned to you';
                $lead     = '<strong>' . esc($actor) . '</strong> assigned this ticket to <strong>' . esc($recipient) . '</strong>. Please acknowledge and begin investigation.';
                break;
            case 'state_changed':
                $from = '';
                if (isset($context['from_state_name'])) {
                    $from = $context['from_state_name'];
                }
                $to   = $state;
                if (isset($context['to_state_name'])) {
                    $to   = $context['to_state_name'];
                }
                $headline = 'Workflow state changed';
                $lead     = 'Ticket moved from <strong>' . esc($from) . '</strong> to <strong>' . esc($to) . '</strong> by ' . esc($actor) . '.';
                break;
            case 'level_escalated':
                $fromL = $level - 1;
                if (isset($context['from_level'])) {
                    $fromL = (int) $context['from_level'];
                }
                $toL   = $level;
                if (isset($context['to_level'])) {
                    $toL   = (int) $context['to_level'];
                }
                $headline = 'Escalation triggered (L' . $fromL . ' → L' . $toL . ')';
                $lead     = 'TAT for level L' . $fromL . ' was breached. The ticket has been escalated to level <strong>L' . $toL . '</strong>. Immediate attention required.';
                $bannerBg = '#B91C1C';
                break;
            case 'resolved':
                $headline = 'Ticket resolved';
                $lead     = '<strong>' . esc($actor) . '</strong> marked this ticket as resolved. Please verify and close.';
                $bannerBg = '#16A34A';
                break;
            case 'closed':
                $headline = 'Ticket closed';
                $lead     = 'This ticket has been closed by ' . esc($actor) . '. No further action required.';
                $bannerBg = '#334155';
                break;
            case 'tat_breach':
                $headline = 'TAT breach detected';
                $lead     = 'This ticket has exceeded its turn-around time at level <strong>L' . $level . '</strong>. Auto-escalation will run on the next sweep.';
                $bannerBg = '#B91C1C';
                break;
            case 'tat_warning':
                // Fires at 80% TAT consumed — proactive warning before auto-escalation triggers.
                $headline = 'SLA warning — approaching breach (L' . $level . ')';
                $lead     = 'This ticket has consumed 80% of its TAT window at level <strong>L' . $level . '</strong>. Please act now to prevent an auto-escalation.';
                $bannerBg = '#B45309';
                break;
        }

        $rows  = '';
        $rows .= mail_kv_row('Alarm ID',  '<code style="font-family:Consolas,monospace;font-size:13px;background:#F1F5F9;padding:2px 6px;border-radius:4px;color:#0F172A;">' . esc($alarm) . '</code>');
        $rows .= mail_kv_row('Severity',  mail_chip_span($sevLabel, $sevColor));
        $rows .= mail_kv_row('Priority',  mail_chip_span($pp[1], $pp[0]));
        $rows .= mail_kv_row('Project',   esc($project));
        if ($flow !== '') {
            $rows .= mail_kv_row('Flow', esc($flow));
        }
        $rows .= mail_kv_row('State',     esc($state));
        $rows .= mail_kv_row('Level',     'L' . $level);
        if ($tatExp !== '') {
            $ts = strtotime($tatExp);
            $when = '';
            if ($ts) {
                $when = date('D, d M Y H:i', $ts);
            } else {
                $when = $tatExp;
            }
            $rows .= mail_kv_row('TAT expires', esc($when));
        }
        if ($comment !== '') {
            $rows .= mail_kv_row('Note', '<em style="color:#475569;">' . esc($comment) . '</em>');
        }

        $descBlock = '';
        if ($desc !== '') {
            $short = $desc;
            if (mb_strlen($desc) > 600) {
                $short = mb_substr($desc, 0, 600) . '…';
            }
            $descBlock = '<div style="margin-top:18px;padding:14px 16px;background:#F8FAFC;border-left:3px solid ' . $sevColor . ';border-radius:4px;font-size:14px;color:#334155;line-height:1.55;">'
                . nl2br(esc($short)) . '</div>';
        }

        $year  = date('Y');
        $brand = esc($appName);
        $title = esc($title);

        $html = '<!doctype html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>' . esc(mail_subject($event, $context)) . '</title>
        </head>
        <body style="margin:0;padding:0;background:#F1F5F9;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;color:#0F172A;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#F1F5F9;padding:24px 12px;">
        <tr><td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;background:#FFFFFF;border-radius:8px;overflow:hidden;box-shadow:0 4px 14px rgba(15,23,42,0.06);">
            <!-- banner -->
            <tr>
                <td style="background:' . $bannerBg . ';padding:22px 28px;color:#FFFFFF;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                    <td style="font-size:12px;letter-spacing:1.5px;text-transform:uppercase;opacity:0.85;">' . $brand . '</td>
                    <td align="right" style="font-size:11px;opacity:0.85;">' . esc(date('D, d M H:i')) . '</td>
                    </tr>
                </table>
                <div style="margin-top:6px;font-size:22px;font-weight:700;letter-spacing:-0.3px;">' . esc($headline) . '</div>
                <div style="margin-top:6px;font-size:14px;opacity:0.95;line-height:1.5;">' . $lead . '</div>
                </td>
            </tr>
            <!-- title -->
            <tr>
                <td style="padding:22px 28px 4px 28px;">
                <div style="font-size:13px;color:#94A3B8;letter-spacing:0.4px;text-transform:uppercase;">Ticket title</div>
                <div style="font-size:18px;font-weight:600;color:#0F172A;margin-top:4px;line-height:1.35;">' . $title . '</div>
                ' . $descBlock . '
                </td>
            </tr>
            <!-- summary table -->
            <tr>
                <td style="padding:18px 28px 8px 28px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E2E8F0;border-radius:6px;border-collapse:separate;border-spacing:0;">
                    ' . $rows . '
                </table>
                </td>
            </tr>
            <!-- action button -->
            <tr>
                <td align="center" style="padding:22px 28px 6px 28px;">
                <a href="' . esc($url) . '" style="display:inline-block;padding:12px 28px;background:' . $sevDark . ';color:#FFFFFF;font-size:14px;font-weight:600;text-decoration:none;border-radius:6px;letter-spacing:0.3px;">View ticket →</a>
                </td>
            </tr>
            <tr>
                <td align="center" style="padding:0 28px 24px 28px;">
                <div style="font-size:12px;color:#94A3B8;">If the button does not work, copy this link:<br><a href="' . esc($url) . '" style="color:#64748B;word-break:break-all;">' . esc($url) . '</a></div>
                </td>
            </tr>
            <!-- footer -->
            <tr>
                <td style="background:#F8FAFC;padding:16px 28px;border-top:1px solid #E2E8F0;font-size:11px;color:#94A3B8;line-height:1.6;">
                This is an automated notification from ' . $brand . '. Do not reply directly — use the application to respond or comment.<br>
                &copy; ' . $year . ' ' . $brand . ' · Triggered by <strong>' . esc($actor) . '</strong> · ' . esc(strtoupper($event)) . '
                </td>
            </tr>
            </table>
        </td></tr>
        </table>
        </body>
        </html>';
        return $html;
    }
}

if (!function_exists('send_email')) {
    function send_email($to, $subject, $body)
    {
        try {
            $emailConfig = new \Config\Email();
        } catch (\Throwable $e) {
            log_message('error', 'send_email config load failed: ' . $e->getMessage());
            return false;
        }

        // DB settings take priority so admins can reconfigure SMTP without touching .env.
        $protocol  = (string) app_setting('email_protocol',   $emailConfig->protocol);
        $host      = (string) app_setting('email_smtp_host',  $emailConfig->SMTPHost);
        $port      = (int)    app_setting('email_smtp_port',  $emailConfig->SMTPPort);
        $smtpUser  = (string) app_setting('email_smtp_user',  $emailConfig->SMTPUser);
        $smtpPass  = (string) app_setting('email_smtp_pass',  $emailConfig->SMTPPass);
        $crypto    = (string) app_setting('email_smtp_crypto', $emailConfig->SMTPCrypto);
        $fromEmail = (string) app_setting('email_from_email', $emailConfig->fromEmail);
        $fromName  = (string) app_setting('email_from_name',  $emailConfig->fromName);

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $protocol = strtolower($protocol);

            if ($protocol === 'smtp') {
                $mail->isSMTP();
                $mail->Host = $host;
                $mail->Port = $port;
                $mail->Timeout = (int) $emailConfig->SMTPTimeout;
                $mail->SMTPKeepAlive = (bool) $emailConfig->SMTPKeepAlive;
                $mail->SMTPAuth = ($smtpUser !== '') || ($smtpPass !== '');
                $mail->Username = $smtpUser;
                $mail->Password = $smtpPass;

                $crypto = strtolower($crypto);
                if ($crypto === 'ssl') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($crypto === 'tls') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                }
            } elseif ($protocol === 'sendmail') {
                $mail->isSendmail();
                $mail->Sendmail = (string) $emailConfig->mailPath;
            } else {
                $mail->isMail();
            }

            $mail->CharSet = (string) $emailConfig->charset;
            $mail->isHTML(strtolower((string) $emailConfig->mailType) === 'html');
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags((string) $body);

            $mail->send();
            return true;
        } catch (\Throwable $e) {
            log_message('error', 'send_email failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('parse_mentions')) {
    // Extracts @username tokens from text, resolves to active user_ids, and excludes the author.
    function parse_mentions($text, $excludeUserId = '', $ticket = null)
    {
        $text = (string) $text;
        if ($text === '') {
            return [];
        }
        if (!preg_match_all('/(?:^|\s)@([a-zA-Z0-9._-]{3,64})/', $text, $matches)) {
            return [];
        }
        $candidates = array_values(array_unique($matches[1]));
        if (empty($candidates)) {
            return [];
        }
        $excludeUserId = (string) $excludeUserId;
        $rows = model('Helper_model')->parseUsersByMentions($candidates);
        $out = [];
        foreach ($rows as $r) {
            $uid = (string) $r['user_id'];
            if ($uid === '' || $uid === $excludeUserId) {
                continue;
            }
            if ($ticket !== null) {
                $hasAccess = verify_ticket_access($ticket, $uid, $r['role']);
                if ($hasAccess === false) {
                    continue;
                }
            }
            $out[] = $uid;
        }
        return $out;
    }
}

// Wraps @username tokens in a styled span; HTML-escapes all other content first.
if (!function_exists('highlight_mentions')) {
    function highlight_mentions($text)
    {
        $escaped = esc((string) $text);
        $replaced = preg_replace(
            '/(^|\s)@([a-zA-Z0-9._-]{3,64})/u',
            '$1<span class="mention-chip">@$2</span>',
            $escaped
        );
        // Fall back to the escaped string on regex failure.
        if ($replaced === null) {
            return $escaped;
        }
        return $replaced;
    }
}

if (!function_exists('user_notify_allowed')) {
    // Returns true when this user allows notifications for the given project+severity (lenient default = allow).
    function user_notify_allowed($user_id, $project_id, $severity)
    {
        $user_id    = (string) $user_id;
        $project_id = (int) $project_id;
        $severity   = (string) $severity;
        if ($user_id === '' || $severity === '') {
            return true;
        }
        // Per-request cache avoids re-querying for each recipient in a cron run.
        static $cache = [];
        if (!isset($cache[$user_id])) {
            try {
                $rows = model('Helper_model')->getUserNotificationSettings($user_id);
                $map = [];
                foreach ($rows as $r) {
                    $key = (int) $r['project_id'] . '|' . (string) $r['severity'];
                    $map[$key] = (int) $r['is_enabled'];
                }
                $cache[$user_id] = $map;
            } catch (\Throwable $e) {
                $cache[$user_id] = [];
            }
        }
        $map = $cache[$user_id];

        $exactKey = $project_id . '|' . $severity;
        if (isset($map[$exactKey])) {
            return ((int) $map[$exactKey]) === 1;
        }
        $allProjKey = '0|' . $severity;
        if (isset($map[$allProjKey])) {
            return ((int) $map[$allProjKey]) === 1;
        }
        return true; // lenient default
    }
}

if (!function_exists('notify_users')) {
    // Queues email rows in notification_logs for out-of-band delivery by tat_monitor.php.
    function notify_users($user_ids, $ticket_id, $subject, $body)
    {
        if (empty($user_ids)) {
            return;
        }

        $ids = array_values(array_filter(array_map('strval', (array) $user_ids), function ($v) {
            return $v !== '';
        }));
        if (empty($ids)) {
            return;
        }

        $project_id = 0;
        $severity   = '';
        if ((int) $ticket_id > 0) {
            $ticket = model('Helper_model')->getTicketProjectAndSeverity($ticket_id);
            if (!empty($ticket)) {
                $project_id = (int) $ticket['project_id'];
                $severity   = (string) $ticket['alert_type'];
            }
        }

        $rows = model('User_model')->getByIds($ids);
        $now  = date('Y-m-d H:i:s');
        $helperModel = model('Helper_model');
        foreach ($rows as $u) {
            // Filter through the per-user matrix. Lenient default = allow.
            if ($severity !== '' && !user_notify_allowed($u['user_id'], $project_id, $severity)) {
                log_message('debug', "pview alert >> notify_users SKIP (user opted out): user=[" . $u['user_id'] . "], ticket=[" . $ticket_id . "], severity=[" . $severity . "]");
                continue;
            }
            $helperModel->insertNotificationLog([
                'ticket_id'       => $ticket_id,
                'channel'         => 'email',
                'recipient_email' => $u['email'],
                'subject'         => $subject,
                'body'            => $body,
                'status'          => 'pending',
                'sent_at'         => null,
                'created_at'      => $now,
            ]);
            log_message('debug', "pview alert >> email queued: ticket_id=[" . $ticket_id . "], to=[" . $u['email'] . "]");
        }

        // Keep the notification logs table bounded. Prune entries older than 90 days.
        try {
            $cutoff = date('Y-m-d H:i:s', time() - (90 * 86400));
            $helperModel->pruneNotificationLogs($cutoff);
        } catch (\Throwable $e) {
            // non-fatal
        }
    }
}

if (!function_exists('process_notification_queue')) {
    // Drains pending notification_logs rows; marks each sent/failed. Called from tat_monitor.php.
    // Returns ['sent' => int, 'failed' => int, 'retried' => int].
    function process_notification_queue($batch = 0, $maxAttempts = 0)
    {
        $batch       = (int) $batch;
        $maxAttempts = (int) $maxAttempts;
        if ($batch < 1) {
            $batch = (int) app_setting('notification_batch_size', 50);
        }
        if ($maxAttempts < 1) {
            $maxAttempts = (int) app_setting('notification_max_attempts', 5);
        }
        if ($batch < 1) {
            $batch = 50;
        }
        if ($batch > 500) {
            $batch = 500;
        }
        if ($maxAttempts < 1) {
            $maxAttempts = 5;
        }

        $helperModel = model('Helper_model');
        $pending = $helperModel->getPendingNotifications($batch);

        $sent    = 0;
        $failed  = 0;
        $retried = 0;

        // Collect IDs by outcome so we can batch-update sent rows in one query.
        $sentIds    = [];
        $failedRows = []; // id => error_message
        $retryRows  = []; // id => error_message

        foreach ($pending as $row) {
            $recipient = (string) $row['recipient_email'];
            if ($recipient === '') {
                $failedRows[(int) $row['id']] = 'no recipient_email';
                $failed++;
                continue;
            }
            try {
                $ok = send_email($recipient, (string) $row['subject'], (string) $row['body']);
            } catch (\Throwable $e) {
                log_message('error', 'pview alert >> queue send threw: id=[' . $row['id'] . '], err=[' . $e->getMessage() . ']');
                $ok = false;
            }
            if ($ok) {
                $sentIds[] = (int) $row['id'];
                $sent++;
                log_message('debug', 'pview alert >> queue sent: id=[' . $row['id'] . '], to=[' . $recipient . ']');
                continue;
            }

            // Track retry count via "/N" suffix in error_message to avoid adding a column.
            $attempts = 1;
            if (!empty($row['error_message']) && preg_match('#/(\d+)$#', (string) $row['error_message'], $m)) {
                $attempts = (int) $m[1] + 1;
            }
            if ($attempts >= $maxAttempts) {
                $failedRows[(int) $row['id']] = 'send failed after ' . $attempts . ' attempt(s)';
                $failed++;
                log_message('warning', 'pview alert >> queue give-up: id=[' . $row['id'] . '], to=[' . $recipient . '], attempts=[' . $attempts . ']');
            } else {
                $retryRows[(int) $row['id']] = 'transient send failure /' . $attempts;
                $retried++;
            }
        }

        // Batch UPDATE all successfully sent rows in a single query.
        if (!empty($sentIds)) {
            $helperModel->updateNotificationStatusSent($sentIds, date('Y-m-d H:i:s'));
        }
        // Failed and retry rows each carry a distinct error_message, so update individually.
        foreach ($failedRows as $id => $msg) {
            $helperModel->updateNotificationStatusSingle($id, 'failed', $msg);
        }
        foreach ($retryRows as $id => $msg) {
            $helperModel->updateNotificationErrorMsg($id, $msg);
        }

        return ['sent' => $sent, 'failed' => $failed, 'retried' => $retried];
    }
}

if (!function_exists('notify_ticket_event')) {
    // Builds a templated email body for a ticket lifecycle event and queues it via notify_users().
    function notify_ticket_event($event, $ticket, $extraContext = [], $userIds = [])
    {
        if (empty($userIds)) {
            return;
        }
        $alarmIdVal = '';
        if (isset($ticket['alarm_id'])) {
            $alarmIdVal = (string) $ticket['alarm_id'];
        }
        $titleVal = '';
        if (isset($ticket['title'])) {
            $titleVal = (string) $ticket['title'];
        }
        $descVal = '';
        if (isset($ticket['description'])) {
            $descVal = (string) $ticket['description'];
        }
        $alertTypeVal = 'info';
        if (isset($ticket['alert_type'])) {
            $alertTypeVal = (string) $ticket['alert_type'];
        }
        $priorityVal = 'medium';
        if (isset($ticket['priority'])) {
            $priorityVal = (string) $ticket['priority'];
        }
        $projectNameVal = '';
        if (isset($ticket['project_name'])) {
            $projectNameVal = (string) $ticket['project_name'];
        }
        $flowNameVal = '';
        if (isset($ticket['flow_name'])) {
            $flowNameVal = (string) $ticket['flow_name'];
        }
        $stateNameVal = '';
        if (isset($ticket['state_name'])) {
            $stateNameVal = (string) $ticket['state_name'];
        }
        $levelVal = 1;
        if (isset($ticket['current_level'])) {
            $levelVal = (int) $ticket['current_level'];
        }
        $ticketUrlVal = site_url('tickets/detail/' . $alarmIdVal);

        $context = [
            'alarm_id'       => $alarmIdVal,
            'title'          => $titleVal,
            'description'    => $descVal,
            'alert_type'     => $alertTypeVal,
            'priority'       => $priorityVal,
            'project_name'   => $projectNameVal,
            'flow_name'      => $flowNameVal,
            'state_name'     => $stateNameVal,
            'level'          => $levelVal,
            'ticket_url'     => $ticketUrlVal,
        ];
        foreach ($extraContext as $k => $v) {
            $context[$k] = $v;
        }
        $subject = mail_subject($event, $context);
        $body    = mail_html_body($event, $context);
        $ticketId = 0;
        if (isset($ticket['id'])) {
            $ticketId = (int) $ticket['id'];
        }
        notify_users($userIds, $ticketId, $subject, $body);
    }
}

if (!function_exists('tat_expires_at')) {
    // Returns the ISO 8601 TAT expiry timestamp, or '' for resolved/closed/final tickets.
    function tat_expires_at($ticket, $state = null)
    {
        if (empty($ticket)) {
            return '';
        }
        $rawStatus = '';
        if (isset($ticket['status'])) {
            $rawStatus = $ticket['status'];
        }
        $status = (string) $rawStatus;
        if ($status === 'resolved' || $status === 'closed') {
            return '';
        }
        $row = $state;
        if (empty($row)) {
            $row = $ticket;
        }
        $isFinal = 0;
        if (isset($row['is_final'])) {
            $isFinal = (int) $row['is_final'];
        } else if (isset($ticket['state_is_final'])) {
            $isFinal = (int) $ticket['state_is_final'];
        }
        if ($isFinal === 1) {
            return '';
        }
        $level   = 1;
        if (isset($ticket['current_level'])) {
            $level = (int) $ticket['current_level'];
        }
        $tatKey  = 'l' . $level . '_tat_minutes';
        $tat     = 60;
        if (isset($row[$tatKey])) {
            $tat = (int) $row[$tatKey];
        } else if (isset($ticket[$tatKey])) {
            $tat = (int) $ticket[$tatKey];
        }
        $rawEntered = 'now';
        if (isset($ticket['state_entered_at'])) {
            $rawEntered = $ticket['state_entered_at'];
        }
        $entered = strtotime((string) $rawEntered);
        if ($entered === false) {
            return '';
        }
        return date('c', $entered + $tat * 60);
    }
}

if (!function_exists('tat_minutes_for_level')) {
    // TAT minutes configured for $state at $level; defaults to 60.
    function tat_minutes_for_level($state, $level)
    {
        $tatKey = 'l' . (int) $level . '_tat_minutes';
        if (isset($state[$tatKey])) {
            return (int) $state[$tatKey];
        }
        return 60;
    }
}

if (!function_exists('tat_total_minutes')) {
    // Returns the TAT window in minutes for the current level; 0 for resolved/closed/final tickets.
    function tat_total_minutes($ticket, $state = null)
    {
        if (empty($ticket)) {
            return 0;
        }
        $rawStatus = '';
        if (isset($ticket['status'])) {
            $rawStatus = $ticket['status'];
        }
        $status = (string) $rawStatus;
        if ($status === 'resolved' || $status === 'closed') {
            return 0;
        }
        $row = $state;
        if (empty($row)) {
            $row = $ticket;
        }
        $isFinal = 0;
        if (isset($row['is_final'])) {
            $isFinal = (int) $row['is_final'];
        } else if (isset($ticket['state_is_final'])) {
            $isFinal = (int) $ticket['state_is_final'];
        }
        if ($isFinal === 1) {
            return 0;
        }
        $level = 1;
        if (isset($ticket['current_level'])) {
            $level = (int) $ticket['current_level'];
        }
        $tatKey = 'l' . $level . '_tat_minutes';
        if (isset($row[$tatKey])) {
            return (int) $row[$tatKey];
        }
        if (isset($ticket[$tatKey])) {
            return (int) $ticket[$tatKey];
        }
        return 0;
    }
}

if (!function_exists('alert_badge')) {
    function alert_badge($type)
    {
        $type = strtolower((string) $type);
        $map  = [
            'info'     => 'bg-info text-dark',
            'major'    => 'bg-warning text-dark',
            'critical' => 'bg-danger',
        ];
        $cls = 'bg-secondary';
        if (isset($map[$type])) {
            $cls = $map[$type];
        }
        $label = $type;
        if (empty($label)) {
            $label = '-';
        }
        return '<span class="badge ' . $cls . '">' . esc(strtoupper($label)) . '</span>';
    }
}

if (!function_exists('status_badge')) {
    function status_badge($status)
    {
        $status = strtolower((string) $status);
        $map = [
            'open'        => 'bg-secondary',
            'in_progress' => 'bg-primary',
            'escalated'   => 'bg-danger',
            'resolved'    => 'bg-success',
            'closed'      => 'bg-dark',
        ];
        $cls = 'bg-secondary';
        if (isset($map[$status])) {
            $cls = $map[$status];
        }
        $base = $status;
        if (empty($base)) {
            $base = '-';
        }
        $label = str_replace('_', ' ', $base);
        return '<span class="badge ' . $cls . '">' . esc(strtoupper($label)) . '</span>';
    }
}

if (!function_exists('priority_badge')) {
    function priority_badge($priority)
    {
        $priority = strtolower((string) $priority);
        $map = [
            'low'    => 'bg-secondary',
            'medium' => 'bg-info text-dark',
            'high'   => 'bg-warning text-dark',
            'urgent' => 'bg-danger',
        ];
        $cls = 'bg-secondary';
        if (isset($map[$priority])) {
            $cls = $map[$priority];
        }
        $label = $priority;
        if (empty($label)) {
            $label = '-';
        }
        return '<span class="badge ' . $cls . '">' . esc(strtoupper($label)) . '</span>';
    }
}

if (!function_exists('level_badge')) {
    function level_badge($level)
    {
        return '<span class="badge bg-info text-dark">L' . (int) $level . '</span>';
    }
}

if (!function_exists('validate_password')) {
    // Check password against admin-configured min length and character requirements.
    function validate_password($password)
    {
        $password    = (string) $password;
        if (strlen($password) > 1024) {
            return 'Password is too long.';
        }
        $min         = app_setting_int('password_min_length', 8);
        if ($min < 1) {
            $min = 8;
        }
        $needLetter  = app_setting_bool('password_require_letter', true);
        $needDigit   = app_setting_bool('password_require_digit', true);

        if (strlen($password) < $min) {
            return 'Password must be at least ' . $min . ' characters long.';
        }
        if ($needLetter && !preg_match('/[A-Za-z]/', $password)) {
            return 'Password must contain at least one letter.';
        }
        if ($needDigit && !preg_match('/\d/', $password)) {
            return 'Password must contain at least one digit.';
        }
        return '';
    }
}

if (!function_exists('validate_user_id')) {
    // Check user_id format: 3–64 chars, letters/digits/dot/underscore/hyphen.
    function validate_user_id($user_id)
    {
        $user_id = (string) $user_id;
        if ($user_id === '') {
            return 'User ID is required.';
        }
        if (!preg_match('/^[A-Za-z0-9._-]{3,64}$/', $user_id)) {
            return 'User ID must be 3–64 chars: letters, digits, dot, underscore, hyphen.';
        }
        return '';
    }
}

if (!function_exists('password_must_rotate')) {
    function password_must_rotate($password_changed_at, $maxDays = 90)
    {
        if (empty($password_changed_at)) {
            return true; // unknown → force a rotation
        }
        $changed = strtotime((string) $password_changed_at);
        if ($changed === false) {
            return true;
        }
        $ageDays = (time() - $changed) / 86400;
        if ($ageDays > $maxDays) {
            return true;
        }
        return false;
    }
}

if (!function_exists('json_ok')) {
    function json_ok($data = [], $message = 'Success')
    {
        return service('response')->setJSON([
            'success' => true,
            'data'    => $data,
            'message' => $message,
        ]);
    }
}
if (!function_exists('json_fail')) {
    function json_fail($message = 'Failed', $code = 400)
    {
        return service('response')->setStatusCode($code)->setJSON([
            'success' => false,
            'data'    => [],
            'message' => $message,
        ]);
    }
}

if (!function_exists('dt_parse_request')) {
    // $colMap: column index => DB column name (ORDER BY whitelist).
    function dt_parse_request($request, $colMap)
    {
        $draw   = (int) $request->getGet('draw');
        $start  = max(0, (int) $request->getGet('start'));
        $length = (int) $request->getGet('length');
        if ($length <= 0 || $length > 500) {
            $length = 25;
        }

        $searchArr = $request->getGet('search');
        $search    = '';
        if (is_array($searchArr) && isset($searchArr['value'])) {
            $search = trim((string) $searchArr['value']);
        }

        $orderArr = $request->getGet('order');
        $orderCol = 'id';
        if (isset($colMap[0])) {
            $orderCol = $colMap[0];
        }
        $orderDir = 'asc';
        if (is_array($orderArr) && isset($orderArr[0]['column'])) {
            $idx = (int) $orderArr[0]['column'];
            if (isset($colMap[$idx])) {
                $orderCol = $colMap[$idx];
            }
            $dirVal = 'asc';
            if (isset($orderArr[0]['dir'])) {
                $dirVal = $orderArr[0]['dir'];
            }
            $rawDir = strtolower((string) $dirVal);
            if ($rawDir === 'desc') {
                $orderDir = 'desc';
            }
        }

        return [
            'draw'      => $draw,
            'start'     => $start,
            'length'    => $length,
            'search'    => $search,
            'order_col' => $orderCol,
            'order_dir' => $orderDir,
        ];
    }
}

if (!function_exists('dt_json_response')) {
    function dt_json_response($draw, $total, $filtered, $data)
    {
        return service('response')
            ->setStatusCode(200)
            ->setHeader('Content-Type', 'application/json')
            ->setBody(json_encode([
                'draw'            => (int) $draw,
                'recordsTotal'    => (int) $total,
                'recordsFiltered' => (int) $filtered,
                'data'            => $data,
            ]));
    }
}


if (!function_exists('module_registry')) {
    // Reads all registered modules from the `modules` table.
    // Returns [module_key => [name, desc, is_builtin]] ordered by sort_order.
    // Uses a static per-request cache so the DB is only queried once.
    // Falls back to empty when the modules table does not exist yet.
    function module_registry()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        try {
            $db = \Config\Database::connect();
            $tableCheck = $db->query("SHOW TABLES LIKE 'modules'")->getResultArray();
            if (empty($tableCheck)) {
                return $cache;
            }
            $rows = $db->table('modules')
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->get()->getResultArray();
            foreach ($rows as $row) {
                $cache[(string) $row['module_key']] = [
                    'name'       => (string) $row['name'],
                    'desc'       => (string) $row['description'],
                    'is_builtin' => (int)    $row['is_builtin'],
                ];
            }
        } catch (\Throwable $e) {
            log_message('error', 'module_registry() failed: ' . $e->getMessage());
        }
        return $cache;
    }
}


if (!function_exists('has_module_access')) {
    function has_module_access($module_key, $action = 'view')
    {
        $role = logged_user_role();
        if (empty($role)) {
            return false;
        }

        // Enforce that the module key must be registered in the modules table.
        // If it was deleted, no role (including Super Admin) should be able to access it.
        static $registered_modules = null;
        if ($registered_modules === null) {
            $registered_modules = [];
            try {
                $db = \Config\Database::connect();
                $rows = $db->table('modules')->select('module_key, permission_module_key')->get()->getResultArray();
                foreach ($rows as $row) {
                    if (!empty($row['module_key'])) {
                        $registered_modules[strtolower($row['module_key'])] = true;
                    }
                    if (!empty($row['permission_module_key'])) {
                        $registered_modules[strtolower($row['permission_module_key'])] = true;
                    }
                }
            } catch (\Throwable $e) {
                log_message('error', 'has_module_access() failed to load module list: ' . $e->getMessage());
            }
        }

        $lookupKey = strtolower((string) $module_key);
        if (!isset($registered_modules[$lookupKey])) {
            return false;
        }

        // Settings is always super_admin only — excluded from role configuration by design.
        if ($module_key === 'settings') {
            if ($role === ROLE_SUPER_ADMIN) {
                return true;
            }
            return false;
        }

        // Always override for super_admin to prevent lockout
        if ($role === ROLE_SUPER_ADMIN) {
            return true;
        }

        static $permissions_cache = null;

        if ($permissions_cache === null) {
            $permissions_cache = [];
            try {
                $db = \Config\Database::connect();
                $table_exists = false;
                $table_check = $db->query("SHOW TABLES LIKE 'module_permissions'")->getResultArray();
                if (!empty($table_check)) {
                    $table_exists = true;
                }

                if ($table_exists === true) {
                    $rows = $db->table('module_permissions')->get()->getResultArray();
                    foreach ($rows as $row) {
                        $p_role = $row['role'];
                        $p_module = $row['module_key'];
                        $permissions_cache[$p_role][$p_module] = [
                            'view'   => (int) $row['can_view'],
                            'add'    => (int) $row['can_add'],
                            'edit'   => (int) $row['can_edit'],
                            'delete' => (int) $row['can_delete'],
                        ];
                    }
                }
            } catch (\Throwable $e) {
                log_message('error', 'has_module_access() database query failed: ' . $e->getMessage());
            }
        }
        if (isset($permissions_cache[$role][$module_key])) {
            $perms = $permissions_cache[$role][$module_key];
            if (isset($perms[$action])) {
                return $perms[$action] === 1;
            }
        }

        // No DB row for this role + module combination means no access.
        return false;
    }
}

// Walks the sidebar in render order and returns the first module the user sees.
if (!function_exists('get_first_accessible_module')) {
    function get_first_accessible_module()
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $candidates = [
            ['dashboard',            'view', 'dashboard',            'Dashboard'],
            ['projects',             'view', 'projects',             'Projects'],
            ['flows',                'view', 'flows',                'Flows'],
            ['alerts',               'view', 'alerts',               'Alert Defs'],
            ['escalation',           'view', 'escalation',           'Escalation'],
            ['tickets',              'view', 'tickets',              'My Tickets'],
            ['tickets',              'add',  'tickets/create',       'Raise Ticket'],
            ['tickets_all',          'view', 'tickets/all',          'All Tickets'],
            ['users',                'view', 'users',                'Users'],
            ['api_keys',             'view', 'api_keys',             'API Keys'],
            ['activity_logs',        'view', 'activity_logs',        'Activity Log'],
            ['cron_panel',           'view', 'cron_panel',           'Cron Panel'],
            ['settings',             'view', 'settings',             'Settings'],
            ['roles',                'view', 'roles',                'Roles'],
            ['module_control_panel', 'view', 'module_control_panel', 'Module Permissions'],
        ];
        foreach ($candidates as $c) {
            if (has_module_access($c[0], $c[1]) === true) {
                $cached = ['url' => site_url($c[2]), 'label' => $c[3]];
                return $cached;
            }
        }
        $cached = ['url' => site_url('logout'), 'label' => 'Logout'];
        return $cached;
    }
}

if (!function_exists('first_accessible_module_url')) {
    function first_accessible_module_url()
    {
        $info = get_first_accessible_module();
        return $info['url'];
    }
}

if (!function_exists('first_accessible_module_label')) {
    function first_accessible_module_label()
    {
        $info = get_first_accessible_module();
        return $info['label'];
    }
}

if (!function_exists('check_module_access')) {
    function check_module_access($module_key, $action = 'view')
    {
        check_isvalidated();

        $allowed = has_module_access($module_key, $action);
        if ($allowed === false) {
            $user_id = logged_user_id();
            $user_role = logged_user_role();
            log_message('warning', "pview alert >> ACCESS DENIED: user_id=[" . $user_id . "] role=[" . $user_role . "] module=[" . $module_key . "] action=[" . $action . "]");

            $session = \Config\Services::session();
            $session->setFlashdata('error', 'You do not have permission to access that module.');
            redirect()->to(first_accessible_module_url())->send();
            exit;
        }
    }
}

// Centralized event/activity logger. Writes a single row to activity_logs.
if (!function_exists('activity_log')) {
    function activity_log($module, $action, $entity_type = null, $entity_id = null, $summary = '', $meta = [], $overrides = [])
    {
        try {
            if (isset($overrides['user_id'])) {
                $userId = (string) $overrides['user_id'];
            } else {
                $userId = (string) logged_user_id();
            }

            if (isset($overrides['user_name'])) {
                $userName = (string) $overrides['user_name'];
            } else {
                $userName = (string) logged_user_name();
            }

            if (isset($overrides['user_role'])) {
                $userRole = (string) $overrides['user_role'];
            } else {
                $userRole = (string) logged_user_role();
            }

            if (isset($overrides['status'])) {
                $status = (string) $overrides['status'];
            } else {
                $status = 'success';
            }

            $projectId = null;
            if (isset($overrides['project_id'])) {
                $rawProjId = (int) $overrides['project_id'];
                if ($rawProjId !== 0) {
                    $projectId = $rawProjId;
                }
            }

            $ip = '';
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $ip = (string) $_SERVER['REMOTE_ADDR'];
            }
            $ua = '';
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $ua = substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255);
            }
            $url = '';
            if (isset($_SERVER['REQUEST_URI'])) {
                $url = substr((string) $_SERVER['REQUEST_URI'], 0, 255);
            }
            $method = '';
            if (isset($_SERVER['REQUEST_METHOD'])) {
                $method = (string) $_SERVER['REQUEST_METHOD'];
            }

            // Parse a short browser name from the UA string.
            $browser = null;
            if ($ua !== '') {
                if (strpos($ua, 'Edg/') !== false || strpos($ua, 'Edge/') !== false) {
                    $browser = 'Edge';
                } elseif (strpos($ua, 'Chrome/') !== false) {
                    $browser = 'Chrome';
                } elseif (strpos($ua, 'Firefox/') !== false) {
                    $browser = 'Firefox';
                } elseif (strpos($ua, 'Safari/') !== false) {
                    $browser = 'Safari';
                } else {
                    $browser = substr($ua, 0, 40);
                }
            }

            $metaJson = null;
            if (!empty($meta)) {
                $metaJson = json_encode($meta);
                if ($metaJson === false) {
                    $metaJson = null;
                }
            }

            $dbUserId = $userId;
            if ($userId === '') {
                $dbUserId = null;
            }
            $dbUserName = null;
            if ($userName !== '') {
                $dbUserName = substr($userName, 0, 160);
            }
            $dbUserRole = $userRole;
            if ($userRole === '') {
                $dbUserRole = null;
            }
            $dbEntityType = null;
            if ($entity_type !== null && $entity_type !== '') {
                $dbEntityType = substr((string) $entity_type, 0, 40);
            }
            $dbEntityId = null;
            if ($entity_id !== null && $entity_id !== '') {
                $dbEntityId = substr((string) $entity_id, 0, 64);
            }
            $dbIpAddress = null;
            if ($ip !== '') {
                $dbIpAddress = substr($ip, 0, 45);
            }
            $dbUserAgent = $ua;
            if ($ua === '') {
                $dbUserAgent = null;
            }
            $dbUrl = $url;
            if ($url === '') {
                $dbUrl = null;
            }
            $dbMethod = null;
            if ($method !== '') {
                $dbMethod = substr($method, 0, 10);
            }
            $dbStatus = null;
            if ($status !== '') {
                $dbStatus = substr($status, 0, 10);
            }

            model('Helper_model')->insertActivityLog([
                'user_id'     => $dbUserId,
                'user_name'   => $dbUserName,
                'user_role'   => $dbUserRole,
                'module'      => substr((string) $module, 0, 40),
                'action'      => substr((string) $action, 0, 40),
                'entity_type' => $dbEntityType,
                'entity_id'   => $dbEntityId,
                'summary'     => substr((string) $summary, 0, 255),
                'meta'        => $metaJson,
                'ip_address'  => $dbIpAddress,
                'user_agent'  => $dbUserAgent,
                'browser'     => $browser,
                'project_id'  => $projectId,
                'url'         => $dbUrl,
                'method'      => $dbMethod,
                'status'      => $dbStatus,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'pview alert >> activity_log insert failed: ' . $e->getMessage());
        }
    }
}

// Build a {field: [old, new]} diff for "update" events. Pass the row as it
if (!function_exists('activity_diff')) {
    function activity_diff($before, $after, $fields = [])
    {
        $diff = [];
        if (empty($fields)) {
            $fields = array_keys((array) $after);
        }
        foreach ($fields as $field) {
            $hadBefore = isset($before[$field]);
            $hasAfter  = isset($after[$field]);
            if (!$hadBefore && !$hasAfter) {
                continue;
            }
            $oldVal = null;
            if ($hadBefore) {
                $oldVal = $before[$field];
            }
            $newVal = null;
            if ($hasAfter) {
                $newVal = $after[$field];
            }
            // Loose comparison — '1' vs 1 from POST should not show as a change.
            if ((string) $oldVal !== (string) $newVal) {
                $diff[$field] = [$oldVal, $newVal];
            }
        }
        return $diff;
    }
}


// =========================================================================
// FLOW HELPER FUNCTIONS (Merged)
// =========================================================================

// Vis-network data builders for the workflow designer and ticket progress diagram.

if (!function_exists('flow_ticket_ancestor_ids')) {
    // Returns all state IDs upstream of $currentStateId via forward transitions (falls back to parent_state_id).
    function flow_ticket_ancestor_ids($states, $currentStateId, $transitions = [])
    {
        // Build reverse-forward adjacency: for each state, which states point TO it
        // via a forward transition?  BFS from currentStateId through this reverse
        // map gives us everything "upstream" of the current state.
        if (!empty($transitions)) {
            $revFwd = [];
            foreach ($transitions as $t) {
                $transType = 'forward';
                if (isset($t['transition_type'])) {
                    $transType = $t['transition_type'];
                }
                if ($transType !== 'forward') {
                    continue;
                }
                $from = (int) $t['from_state_id'];
                $to   = (int) $t['to_state_id'];
                $revFwd[$to][] = $from;
            }
            if (!empty($revFwd)) {
                $ancestors = [];
                $queue     = [$currentStateId];
                $visited   = [$currentStateId => true];
                $guard     = 0;
                while (!empty($queue) && $guard < 200) {
                    $guard++;
                    $node = array_shift($queue);
                    $revFwdNode = [];
                    if (isset($revFwd[$node])) {
                        $revFwdNode = $revFwd[$node];
                    }
                    foreach ($revFwdNode as $prev) {
                        if (!isset($visited[$prev])) {
                            $visited[$prev]    = true;
                            $ancestors[$prev]  = true;
                            $queue[]           = $prev;
                        }
                    }
                }
                return $ancestors;
            }
        }
        $byId     = [];
        foreach ($states as $s) {
            $byId[(int) $s['id']] = $s;
        }
        $ancestors = [];
        $cursor    = $currentStateId;
        $guard     = 0;
        while ($cursor > 0 && isset($byId[$cursor]) && $guard < 100) {
            $row    = $byId[$cursor];
            $parent = 0;
            if (isset($row['parent_state_id'])) {
                $parent = (int) $row['parent_state_id'];
            }
            if ($parent <= 0 || !isset($byId[$parent])) {
                break;
            }
            $ancestors[$parent] = true;
            $cursor = $parent;
            $guard++;
        }
        return $ancestors;
    }
}

if (!function_exists('flow_vis_edges')) {
    // Returns edges for the workflow diagram; priority: explicit transitions → parent tree → sequential sort_order.
    function flow_vis_edges($states, $transitions = [])
    {
        // Collect only forward edges from the transitions table.
        $fwdEdges = [];
        foreach ($transitions as $t) {
            $transType = 'forward';
            if (isset($t['transition_type'])) {
                $transType = $t['transition_type'];
            }
            if ($transType === 'forward') {
                $fwdEdges[] = [
                    'from'            => (int) $t['from_state_id'],
                    'to'              => (int) $t['to_state_id'],
                    'transition_type' => 'forward',
                ];
            }
        }

        if (!empty($fwdEdges)) {
            return $fwdEdges;
        }

        $stateIds       = [];
        foreach ($states as $s) {
            $stateIds[] = (int) $s['id'];
        }
        $hasParentLinks = false;
        $parentSet      = []; // IDs that ARE a parent of at least one other state
        foreach ($states as $s) {
            $pid = 0;
            if (isset($s['parent_state_id'])) {
                $pid = (int) $s['parent_state_id'];
            }
            if ($pid > 0 && in_array($pid, $stateIds, true)) {
                $fwdEdges[]      = ['from' => $pid, 'to' => (int) $s['id'], 'transition_type' => 'forward'];
                $parentSet[$pid] = true;
                $hasParentLinks  = true;
            }
        }
        if ($hasParentLinks) {
            // Leaf states implicitly route to the single closing/final state.
            $closingId = null;
            foreach ($states as $s) {
                if (!empty($s['is_final'])) {
                    $closingId = (int) $s['id'];
                    break;
                }
            }
            if ($closingId !== null) {
                foreach ($states as $s) {
                    $sid = (int) $s['id'];
                    if ($sid === $closingId) {
                        continue;
                    }
                    if (isset($parentSet[$sid])) {
                        continue;
                    }
                    $fwdEdges[] = ['from' => $sid, 'to' => $closingId, 'transition_type' => 'forward'];
                }
            }
            return $fwdEdges;
        }

        $count = count($states);
        for ($i = 0; $i < $count - 1; $i++) {
            $fwdEdges[] = [
                'from'            => (int) $states[$i]['id'],
                'to'              => (int) $states[$i + 1]['id'],
                'transition_type' => 'forward',
            ];
        }
        return $fwdEdges;
    }
}

if (!function_exists('flow_vis_designer_data')) {
    // Returns vis-network nodes+edges for the designer preview widget.
    function flow_vis_designer_data($states, $transitions = [])
    {
        if (empty($states)) {
            return ['nodes' => [], 'edges' => []];
        }
        $nodes = [];
        foreach ($states as $s) {
            $type = 'process';
            if (!empty($s['is_initial'])) {
                $type = 'initial';
            } elseif (!empty($s['is_final'])) {
                $type = 'final';
            }
            $nodes[] = [
                'id'    => (int) $s['id'],
                'label' => (string) $s['name'],
                'type'  => $type,
            ];
        }
        $edges = flow_vis_edges($states, $transitions);
        foreach ($transitions as $t) {
            $transType = '';
            if (isset($t['transition_type'])) {
                $transType = $t['transition_type'];
            }
            if ($transType === 'backward') {
                $edges[] = [
                    'from'            => (int) $t['from_state_id'],
                    'to'              => (int) $t['to_state_id'],
                    'transition_type' => 'backward',
                ];
            }
        }
        return ['nodes' => $nodes, 'edges' => $edges];
    }
}

if (!function_exists('flow_vis_ticket_data')) {
    // Returns vis-network nodes+edges for the ticket detail widget (node status: passed|current|pending).
    function flow_vis_ticket_data($states, $currentStateId, $transitions = [])
    {
        if (empty($states)) {
            return ['nodes' => [], 'edges' => []];
        }
        $stateIds = [];
        foreach ($states as $s) {
            $stateIds[] = (int) $s['id'];
        }
        if ($currentStateId === 0 || !in_array($currentStateId, $stateIds, true)) {
            foreach ($states as $s) {
                if (!empty($s['is_initial'])) {
                    $currentStateId = (int) $s['id'];
                    break;
                }
            }
            if (($currentStateId === 0 || !in_array($currentStateId, $stateIds, true)) && !empty($states)) {
                $currentStateId = (int) $states[0]['id'];
            }
        }
        $ancestors = flow_ticket_ancestor_ids($states, $currentStateId, $transitions);

        $nodes = [];
        foreach ($states as $s) {
            $sid = (int) $s['id'];
            if ($sid === $currentStateId) {
                $status = 'current';
            } elseif (isset($ancestors[$sid])) {
                $status = 'passed';
            } else {
                $status = 'pending';
            }
            $nodes[] = ['id' => $sid, 'label' => (string) $s['name'], 'status' => $status];
        }
        $edges = flow_vis_edges($states, $transitions);
        foreach ($transitions as $t) {
            $transType = '';
            if (isset($t['transition_type'])) {
                $transType = $t['transition_type'];
            }
            if ($transType === 'backward') {
                $edges[] = [
                    'from'            => (int) $t['from_state_id'],
                    'to'              => (int) $t['to_state_id'],
                    'transition_type' => 'backward',
                ];
            }
        }
        return ['nodes' => $nodes, 'edges' => $edges];
    }
}

if (!function_exists('flow_widget_html')) {
    // Wraps vis-network node/edge data in the standard widget chrome (toolbar + canvas + legend).
    function flow_widget_html($visData, $opts = [])
    {
        $subtitle = 'How tickets travel through this flow';
        if (isset($opts['subtitle'])) {
            $subtitle = (string) $opts['subtitle'];
        }
        $showLegend = true;
        if (isset($opts['legend'])) {
            $showLegend = (bool) $opts['legend'];
        }
        $variant = 'designer';
        if (isset($opts['variant'])) {
            $variant = (string) $opts['variant'];
        }

        // JSON_HEX_TAG prevents </script> from breaking the embedded JSON block.
        $dataJson = json_encode($visData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

        $html  = '<div class="flow-widget" data-flow-variant="' . esc($variant) . '">';
        $html .= '  <div class="flow-widget-toolbar">';
        $html .= '    <div class="flow-widget-meta">';
        $html .= '      <i class="bi bi-diagram-3"></i>';
        $html .= '      <span class="flow-widget-subtitle">' . esc($subtitle) . '</span>';
        $html .= '    </div>';
        $html .= '    <div class="flow-widget-controls">';
        $html .= '      <button type="button" class="fw-btn" data-flow-fit title="Fit to view" aria-label="Fit to view"><i class="bi bi-aspect-ratio"></i><span class="fw-btn-label">Fit</span></button>';
        $html .= '      <button type="button" class="fw-btn fw-btn--icon" data-flow-zoom-out title="Zoom out" aria-label="Zoom out"><i class="bi bi-dash-lg"></i></button>';
        $html .= '      <span class="fw-zoom-pct" data-flow-zoom-pct>100%</span>';
        $html .= '      <button type="button" class="fw-btn fw-btn--icon" data-flow-zoom-in title="Zoom in" aria-label="Zoom in"><i class="bi bi-plus-lg"></i></button>';
        $html .= '      <button type="button" class="fw-btn fw-btn--icon" data-flow-fullscreen title="Fullscreen" aria-label="Fullscreen"><i class="bi bi-arrows-fullscreen"></i></button>';
        $html .= '    </div>';
        $html .= '  </div>';
        $html .= '  <div class="flow-vis-wrap" data-flow-canvas>';
        $html .= '    <div class="flow-vis-container"></div>';
        $html .= '    <script type="application/json" class="flow-vis-data">' . $dataJson . '</script>';
        $html .= '  </div>';
        if ($showLegend) {
            $html .= '  <div class="flow-widget-legend">';
            $html .= '    <span class="fw-lg"><span class="fw-lg-dot fw-lg-initial"></span> Start</span>';
            $html .= '    <span class="fw-lg"><span class="fw-lg-dot fw-lg-process"></span> Process</span>';
            $html .= '    <span class="fw-lg"><span class="fw-lg-dot fw-lg-final"></span> End</span>';
            $html .= '    <span class="fw-lg"><span class="fw-lg-arrow"></span> Forward</span>';
            $html .= '    <span class="fw-lg"><span class="fw-lg-arrow fw-lg-arrow--back"></span> Send back</span>';
            $html .= '  </div>';
        }
        $html .= '</div>';
        return $html;
    }
}


// =========================================================================
// CSV HELPER FUNCTIONS (Merged)
// =========================================================================

if (!function_exists('export_csv_data')) {
    /**
     * Helper to stream data rows as a CSV file.
     *
     * @param string $filename          Name of the generated file (e.g. 'tickets.csv')
     * @param string $module            The module key ('tickets' or 'activity_logs')
     * @param array  $rows              Array of database rows to export
     * @param string $userSelectedCols  Comma-separated list of columns selected by the user (optional)
     */
    function export_csv_data(string $filename, string $module, array $rows, string $userSelectedCols = '')
    {
        // Define default column lists and Excel header labels based on the module
        $columnMap = [];

        if ($module === 'tickets') {
            $columnMap = [
                'alarm_id'        => 'Alarm ID',
                'title'           => 'Title',
                'project_name'    => 'Project',
                'flow_name'       => 'Flow',
                'state_name'      => 'State',
                'current_level'   => 'Level',
                'alert_type'      => 'Severity',
                'priority'        => 'Priority',
                'status'          => 'Status',
                'assignee_name'   => 'Assignee',
                'raised_by_name'  => 'Raised By',
                'source'          => 'Source',
                'created_at'      => 'Created At',
                'resolved_at'     => 'Resolved At',
                'closed_at'       => 'Closed At',
            ];
        } else if ($module === 'activity_logs') {
            $columnMap = [
                'created_at'  => 'Time',
                'user_id'     => 'User ID',
                'user_name'   => 'User Name',
                'user_role'   => 'Role',
                'module'      => 'Module',
                'action'      => 'Action',
                'entity_type' => 'Entity Type',
                'entity_id'   => 'Entity ID',
                'summary'     => 'Summary',
                'ip_address'  => 'IP Address',
                'url'         => 'URL',
                'method'      => 'Method',
                'status'      => 'Status',
                'meta'        => 'Meta',
            ];
        }

        // Apply user column selection filters if sent from frontend
        if ($userSelectedCols !== '') {
            $selectedKeys = explode(',', $userSelectedCols);
            $filteredMap = [];
            foreach ($selectedKeys as $key) {
                $key = trim($key);
                if (isset($columnMap[$key])) {
                    $filteredMap[$key] = $columnMap[$key];
                }
            }
            if (!empty($filteredMap)) {
                $columnMap = $filteredMap;
            }
        }

        // Stream headers to initiate standard browser download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel handles special characters cleanly
        fwrite($out, "\xEF\xBB\xBF");

        // Generate headers row
        fputcsv($out, array_values($columnMap));

        // Generate data rows
        foreach ($rows as $r) {
            $line = [];
            foreach ($columnMap as $field => $label) {
                $val = '';
                if (isset($r[$field])) {
                    $val = $r[$field];
                }

                // Format values based on column-specific rules (without closures or ternary operators)
                if ($module === 'tickets') {
                    if ($field === 'current_level') {
                        $val = 'L' . (int) $val;
                    } else if ($field === 'alert_type') {
                        $val = strtoupper((string) $val);
                    } else if ($field === 'priority') {
                        $val = strtoupper((string) $val);
                    } else if ($field === 'status') {
                        $val = str_replace('_', ' ', strtoupper((string) $val));
                    }
                }

                $line[] = $val;
            }
            fputcsv($out, $line);
        }

        fclose($out);
        exit;
    }
}
