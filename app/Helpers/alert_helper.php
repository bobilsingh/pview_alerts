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
            $db   = \Config\Database::connect();
            $rows = $db->table('app_settings')->get()->getResultArray();
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
            $db = \Config\Database::connect();
            $rows = $db->table('roles')
                ->select('role_key, is_admin_scope')
                ->get()->getResultArray();
            foreach ($rows as $r) {
                $key            = (string) $r['role_key'];
                $isSuper        = ($key === ROLE_SUPER_ADMIN);
                $isAdminScope   = ((int) ($r['is_admin_scope'] ?? 0)) === 1;

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
                $db = \Config\Database::connect();
                // Defensive: roles table may not exist on very old installs.
                $tableCheck = $db->query("SHOW TABLES LIKE 'roles'")->getResultArray();
                if (!empty($tableCheck)) {
                    $rows = $db->table('roles')
                        ->select('role_key, is_admin_scope')
                        ->get()->getResultArray();
                    foreach ($rows as $r) {
                        $cache[(string) $r['role_key']] = ((int) ($r['is_admin_scope'] ?? 0)) === 1;
                    }
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
        $assignee = (string) (isset($ticket['current_assignee']) ? $ticket['current_assignee'] : '');
        $raisedBy = (string) (isset($ticket['raised_by']) ? $ticket['raised_by'] : '');
        if ($uid !== '' && $assignee === $uid) {
            return $ticket;
        }
        if ($uid !== '' && $raisedBy === $uid) {
            return $ticket;
        }

        $db = \Config\Database::connect();
        $state = $db->table('states')
            ->where('id', (int) (isset($ticket['current_state_id']) ? $ticket['current_state_id'] : 0))
            ->get()->getRowArray();
        if ($state) {
            for ($lvl = 1; $lvl <= 4; $lvl++) {
                $lvlKey = 'l' . $lvl . '_user_ids';
                $arr = json_decode((string) (isset($state[$lvlKey]) ? $state[$lvlKey] : '[]'), true);
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
        return !empty($uid) ? (string) $uid : '';
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
        $db    = \Config\Database::connect();
        $today = date('Ymd');

        $db->query(
            "INSERT INTO alarm_id_sequence (day_key, last_seq) VALUES (?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE last_seq = LAST_INSERT_ID(last_seq + 1)",
            [$today]
        );
        $row = $db->query("SELECT LAST_INSERT_ID() AS n")->getRow();
        $num = isset($row->n) ? (int) $row->n : 1;
        return 'ALM-' . $today . '-' . str_pad((string) $num, 5, '0', STR_PAD_LEFT);
    }
}

// Email template builders — inline styles only (email clients strip <style> tags).
if (!function_exists('mail_subject')) {
    function mail_subject($event, $context)
    {
        $alarm = isset($context['alarm_id']) ? $context['alarm_id'] : 'ALERT';
        $title = isset($context['title']) ? $context['title'] : '';
        $sev   = strtoupper((string) (isset($context['alert_type']) ? $context['alert_type'] : ''));
        $lvl   = isset($context['level']) ? (int) $context['level'] : 0;

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
                $to = isset($context['to_state_name']) ? $context['to_state_name'] : '';
                return $tag . ' ' . $alarm . ' moved → ' . $to;
            case 'level_escalated':
                $newLvl = isset($context['to_level']) ? (int) $context['to_level'] : $lvl;
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
        $alarm     = (string) (isset($context['alarm_id'])      ? $context['alarm_id']      : '');
        $title     = (string) (isset($context['title'])         ? $context['title']         : '');
        $desc      = (string) (isset($context['description'])   ? $context['description']   : '');
        $severity  = strtolower((string) (isset($context['alert_type']) ? $context['alert_type'] : 'info'));
        $priority  = strtolower((string) (isset($context['priority'])   ? $context['priority']   : 'medium'));
        $project   = (string) (isset($context['project_name']) ? $context['project_name'] : '');
        $flow      = (string) (isset($context['flow_name'])    ? $context['flow_name']    : '');
        $state     = (string) (isset($context['state_name'])   ? $context['state_name']   : '');
        $level     = (int)    (isset($context['level'])        ? $context['level']        : 1);
        $tatExp    = (string) (isset($context['tat_expires_at']) ? $context['tat_expires_at'] : '');
        $url       = (string) (isset($context['ticket_url'])   ? $context['ticket_url']   : '#');
        $actor     = (string) (isset($context['actor_name'])   ? $context['actor_name']   : 'system');
        $recipient = (string) (isset($context['recipient_name']) ? $context['recipient_name'] : 'team');
        $comment   = (string) (isset($context['comment'])      ? $context['comment']      : '');
        $appName   = (string) app_setting('app_name', 'pView Alert System');

        $palette = [
            'info'     => ['#0EA5E9', '#0284C7', 'INFO'],
            'major'    => ['#F59E0B', '#B45309', 'MAJOR'],
            'critical' => ['#EF4444', '#B91C1C', 'CRITICAL'],
        ];
        $pal = isset($palette[$severity]) ? $palette[$severity] : $palette['info'];
        $sevColor = $pal[0];
        $sevDark  = $pal[1];
        $sevLabel = $pal[2];

        $prio = [
            'low'    => ['#94A3B8', 'LOW'],
            'medium' => ['#3B82F6', 'MEDIUM'],
            'high'   => ['#F97316', 'HIGH'],
            'urgent' => ['#DC2626', 'URGENT'],
        ];
        $pp = isset($prio[$priority]) ? $prio[$priority] : $prio['medium'];

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
                $from = (string) (isset($context['from_state_name']) ? $context['from_state_name'] : '');
                $to   = (string) (isset($context['to_state_name']) ? $context['to_state_name'] : $state);
                $headline = 'Workflow state changed';
                $lead     = 'Ticket moved from <strong>' . esc($from) . '</strong> to <strong>' . esc($to) . '</strong> by ' . esc($actor) . '.';
                break;
            case 'level_escalated':
                $fromL = (int) (isset($context['from_level']) ? $context['from_level'] : ($level - 1));
                $toL   = (int) (isset($context['to_level']) ? $context['to_level'] : $level);
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
            $short = mb_strlen($desc) > 600 ? mb_substr($desc, 0, 600) . '…' : $desc;
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
        $db = \Config\Database::connect();
        $rows = $db->table('users')
            ->select('user_id, role')
            ->whereIn('user_id', $candidates)
            ->where('is_active', 1)
            ->where('deleted_at', null)
            ->get()->getResultArray();
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
                $db = \Config\Database::connect();
                $rows = $db->table('user_notification_settings')
                    ->where('user_id', $user_id)
                    ->get()->getResultArray();
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
        $db = \Config\Database::connect();

        $ids = array_values(array_filter(array_map('strval', (array) $user_ids), function ($v) {
            return $v !== '';
        }));
        if (empty($ids)) {
            return;
        }

        $project_id = 0;
        $severity   = '';
        if ((int) $ticket_id > 0) {
            $ticket = $db->table('tickets')
                ->select('project_id, alert_type')
                ->where('id', (int) $ticket_id)
                ->get()->getRowArray();
            if (!empty($ticket)) {
                $project_id = (int) $ticket['project_id'];
                $severity   = (string) $ticket['alert_type'];
            }
        }

        $rows = $db->table('users')->whereIn('user_id', $ids)->get()->getResultArray();
        $now  = date('Y-m-d H:i:s');
        foreach ($rows as $u) {
            // Filter through the per-user matrix. Lenient default = allow.
            if ($severity !== '' && !user_notify_allowed($u['user_id'], $project_id, $severity)) {
                log_message('debug', "pview alert >> notify_users SKIP (user opted out): user=[" . $u['user_id'] . "], ticket=[" . $ticket_id . "], severity=[" . $severity . "]");
                continue;
            }
            $db->table('notification_logs')->insert([
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
            $db->table('notification_logs')
                ->where('created_at <', $cutoff)
                ->delete();
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

        $db = \Config\Database::connect();
        $pending = $db->table('notification_logs')
            ->where('status', 'pending')
            ->orderBy('id', 'asc')
            ->limit($batch)
            ->get()->getResultArray();

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
            $db->table('notification_logs')
                ->set(['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'), 'error_message' => null])
                ->whereIn('id', $sentIds)
                ->update();
        }
        // Failed and retry rows each carry a distinct error_message, so update individually.
        foreach ($failedRows as $id => $msg) {
            $db->table('notification_logs')->where('id', $id)->update(['status' => 'failed', 'error_message' => $msg]);
        }
        foreach ($retryRows as $id => $msg) {
            $db->table('notification_logs')->where('id', $id)->update(['error_message' => $msg]);
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
        $context = [
            'alarm_id'       => isset($ticket['alarm_id'])     ? (string) $ticket['alarm_id']     : '',
            'title'          => isset($ticket['title'])        ? (string) $ticket['title']        : '',
            'description'    => isset($ticket['description'])  ? (string) $ticket['description']  : '',
            'alert_type'     => isset($ticket['alert_type'])   ? (string) $ticket['alert_type']   : 'info',
            'priority'       => isset($ticket['priority'])     ? (string) $ticket['priority']     : 'medium',
            'project_name'   => isset($ticket['project_name']) ? (string) $ticket['project_name'] : '',
            'flow_name'      => isset($ticket['flow_name'])    ? (string) $ticket['flow_name']    : '',
            'state_name'     => isset($ticket['state_name'])   ? (string) $ticket['state_name']   : '',
            'level'          => isset($ticket['current_level']) ? (int) $ticket['current_level']  : 1,
            'ticket_url'     => site_url('tickets/detail/' . (isset($ticket['alarm_id']) ? $ticket['alarm_id'] : '')),
        ];
        foreach ($extraContext as $k => $v) {
            $context[$k] = $v;
        }
        $subject = mail_subject($event, $context);
        $body    = mail_html_body($event, $context);
        $ticketId = isset($ticket['id']) ? (int) $ticket['id'] : 0;
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
        $status = (string) (isset($ticket['status']) ? $ticket['status'] : '');
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
        $entered = strtotime((string) (isset($ticket['state_entered_at']) ? $ticket['state_entered_at'] : 'now'));
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
        return isset($state[$tatKey]) ? (int) $state[$tatKey] : 60;
    }
}

if (!function_exists('tat_total_minutes')) {
    // Returns the TAT window in minutes for the current level; 0 for resolved/closed/final tickets.
    function tat_total_minutes($ticket, $state = null)
    {
        if (empty($ticket)) {
            return 0;
        }
        $status = (string) (isset($ticket['status']) ? $ticket['status'] : '');
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
        $orderCol = isset($colMap[0]) ? $colMap[0] : 'id';
        $orderDir = 'asc';
        if (is_array($orderArr) && isset($orderArr[0]['column'])) {
            $idx = (int) $orderArr[0]['column'];
            if (isset($colMap[$idx])) {
                $orderCol = $colMap[$idx];
            }
            $rawDir = isset($orderArr[0]['dir']) ? strtolower((string) $orderArr[0]['dir']) : 'asc';
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

// Walks the sidebar in render order and returns the first module the
if (!function_exists('_first_accessible_module')) {
    function _first_accessible_module()
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $candidates = [
            // Overview group
            ['dashboard',     'view', 'dashboard',      'Dashboard'],
            // Configuration group
            ['projects',      'view', 'projects',       'Projects'],
            ['flows',         'view', 'flows',          'Flows'],
            ['alerts',        'view', 'alerts',         'Alert Defs'],
            ['escalation',    'view', 'escalation',     'Escalation'],
            // Operations group
            ['tickets',       'view', 'tickets',        'My Tickets'],
            ['tickets',       'add',  'tickets/create', 'Raise Ticket'],
            ['tickets_all',   'view', 'tickets/all',    'All Tickets'],
            // the user sees in the menu.
            ['users',                'view', 'users',                'Users'],
            ['api_keys',             'view', 'api_keys',             'API Keys'],
            ['activity_logs',        'view', 'activity_logs',        'Activity Log'],
            ['cron_panel',           'view', 'cron_panel',           'Cron Panel'],
            // Administration group
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
        $info = _first_accessible_module();
        return $info['url'];
    }
}

if (!function_exists('first_accessible_module_label')) {
    function first_accessible_module_label()
    {
        $info = _first_accessible_module();
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
            $db = \Config\Database::connect();
            $userId    = isset($overrides['user_id'])    ? (string) $overrides['user_id']    : (string) logged_user_id();
            $userName  = isset($overrides['user_name'])  ? (string) $overrides['user_name']  : (string) logged_user_name();
            $userRole  = isset($overrides['user_role'])  ? (string) $overrides['user_role']  : (string) logged_user_role();
            $status    = isset($overrides['status'])     ? (string) $overrides['status']     : 'success';
            $projectId = isset($overrides['project_id']) ? ((int) $overrides['project_id'] ?: null) : null;

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

            $db->table('activity_logs')->insert([
                'user_id'     => ($userId === '') ? null : $userId,
                'user_name'   => ($userName === '') ? null : substr($userName, 0, 160),
                'user_role'   => ($userRole === '') ? null : $userRole,
                'module'      => substr((string) $module, 0, 40),
                'action'      => substr((string) $action, 0, 40),
                'entity_type' => ($entity_type === null || $entity_type === '') ? null : substr((string) $entity_type, 0, 40),
                'entity_id'   => ($entity_id === null || $entity_id === '') ? null : substr((string) $entity_id, 0, 64),
                'summary'     => substr((string) $summary, 0, 255),
                'meta'        => $metaJson,
                'ip_address'  => ($ip === '') ? null : substr($ip, 0, 45),
                'user_agent'  => ($ua === '') ? null : $ua,
                'browser'     => $browser,
                'project_id'  => $projectId,
                'url'         => ($url === '') ? null : $url,
                'method'      => ($method === '') ? null : substr($method, 0, 10),
                'status'      => ($status === '') ? null : substr($status, 0, 10),
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
            $oldVal = $hadBefore ? $before[$field] : null;
            $newVal = $hasAfter  ? $after[$field]  : null;
            // Loose comparison — '1' vs 1 from POST should not show as a change.
            if ((string) $oldVal !== (string) $newVal) {
                $diff[$field] = [$oldVal, $newVal];
            }
        }
        return $diff;
    }
}
