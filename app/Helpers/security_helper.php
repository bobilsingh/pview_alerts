<?php

// Login / API rate-limiting and file-upload security helpers.

if (!function_exists('client_ip')) {
    // Returns REMOTE_ADDR only — does not trust X-Forwarded-For.
    function client_ip()
    {
        $ip = '';
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = (string) $_SERVER['REMOTE_ADDR'];
        }
        if ($ip === '') {
            return '0.0.0.0';
        }
        // Truncate just in case (IPv6 max is 45 chars; DB column is 45).
        return substr($ip, 0, 45);
    }
}

if (!function_exists('security_table_exists')) {
    // Checks if security tables exist in the database.
    function security_table_exists($table)
    {
        try {
            return model('Helper_model')->tableExists($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('login_is_locked')) {
    // Checks per-user (not per-IP) lockout to avoid punishing shared NAT users.
    // Returns [locked, remaining_seconds, attempts].
    function login_is_locked($ip, $login)
    {
        $maxAttempts = (int) app_setting('login_max_attempts', 3);
        $windowMin   = (int) app_setting('login_lockout_minutes', 10);
        if ($maxAttempts <= 0 || $windowMin <= 0) {
            return ['locked' => false, 'remaining_seconds' => 0, 'attempts' => 0];
        }
        if (!security_table_exists('login_attempts')) {
            return ['locked' => false, 'remaining_seconds' => 0, 'attempts' => 0];
        }

        $helperModel = model('Helper_model');
        $cutoff = date('Y-m-d H:i:s', time() - ($windowMin * 60));

        $userFails = $helperModel->countFailedLoginAttempts($login, $cutoff);

        if ($userFails < $maxAttempts) {
            return ['locked' => false, 'remaining_seconds' => 0, 'attempts' => $userFails];
        }

        // Expires after the oldest failure so a slow drip doesn't produce a perpetually-rolling lock.
        $oldest = $helperModel->getOldestFailedLoginAttempt($login, $cutoff);
        $remaining = $windowMin * 60;
        if (!empty($oldest['attempted_at'])) {
            $remaining = max(0, ($windowMin * 60) - (time() - strtotime($oldest['attempted_at'])));
        }
        return ['locked' => true, 'remaining_seconds' => $remaining, 'attempts' => $userFails];
    }
}

if (!function_exists('login_attempt_record')) {
    // Records a login attempt status in the database.
    function login_attempt_record($ip, $login, $success)
    {
        if (!security_table_exists('login_attempts')) {
            return;
        }
        try {
            $successInt = 0;
            if ($success) {
                $successInt = 1;
            }
            $helperModel = model('Helper_model');
            $helperModel->insertLoginAttempt([
                'ip'               => $ip,
                'login_identifier' => substr($login, 0, 150),
                'success'          => $successInt,
                'attempted_at'     => date('Y-m-d H:i:s'),
            ]);
            // Trim rows older than 7 days to keep the table bounded.
            $cutoff = date('Y-m-d H:i:s', time() - (7 * 86400));
            $helperModel->pruneLoginAttempts($cutoff);
        } catch (\Throwable $e) {
            log_message('error', 'pview alert >> login_attempt_record failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('login_attempts_clear')) {
    // Clears per-user failed-attempt rows on successful login; leaves other usernames' rows intact.
    function login_attempts_clear($ip, $login)
    {
        if (!security_table_exists('login_attempts')) {
            return;
        }
        try {
            model('Helper_model')->clearFailedLoginAttempts($login);
        } catch (\Throwable $e) {
            // non-fatal
        }
    }
}

if (!function_exists('api_rate_check')) {
    // Returns [allowed, retry_after_seconds]; records the request as a side-effect when allowed.
    function api_rate_check($apiKeyId, $endpoint)
    {
        $perMin  = (int) app_setting('api_rate_per_minute', 60);
        $perHour = (int) app_setting('api_rate_per_hour', 1000);
        if ($apiKeyId <= 0 || ($perMin <= 0 && $perHour <= 0)) {
            return ['allowed' => true, 'retry_after_seconds' => 0];
        }
        if (!security_table_exists('api_request_log')) {
            return ['allowed' => true, 'retry_after_seconds' => 0];
        }
        $helperModel = model('Helper_model');
        $now = time();
        if ($perMin > 0) {
            $minCutoff = date('Y-m-d H:i:s', $now - 60);
            $minCount = $helperModel->countApiRequests($apiKeyId, $minCutoff);
            if ($minCount >= $perMin) {
                return ['allowed' => false, 'retry_after_seconds' => 60];
            }
        }
        if ($perHour > 0) {
            $hourCutoff = date('Y-m-d H:i:s', $now - 3600);
            $hourCount = $helperModel->countApiRequests($apiKeyId, $hourCutoff);
            if ($hourCount >= $perHour) {
                return ['allowed' => false, 'retry_after_seconds' => 3600];
            }
        }
        try {
            $helperModel->insertApiRequestLog([
                'api_key_id'   => $apiKeyId,
                'endpoint'     => substr($endpoint, 0, 100),
                'requested_at' => date('Y-m-d H:i:s'),
            ]);
            // PERF-01: The 24-hour pruning DELETE has been moved to tat_monitor.php
            // (runs once per cron tick) to avoid lock contention on every API call.
        } catch (\Throwable $e) {
            log_message('error', 'pview alert >> api_rate_check insert failed: ' . $e->getMessage());
        }
        return ['allowed' => true, 'retry_after_seconds' => 0];
    }
}

if (!function_exists('upload_blocked_extensions')) {
    // Hard-coded extension denylist — overrides any admin-configured allowed list.
    function upload_blocked_extensions()
    {
        $extra = app_setting_csv('upload_blocked_ext', []);
        $base = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'pht', 'sh', 'bash', 'zsh', 'exe', 'bat', 'cmd', 'com', 'msi', 'scr', 'pif', 'js', 'mjs', 'vbs', 'wsf', 'ps1', 'jsp', 'asp', 'aspx', 'cgi', 'pl', 'py', 'rb', 'htaccess', 'htpasswd', 'ini', 'conf', 'htm', 'html', 'xhtml', 'svg'];
        $all = array_unique(array_map('strtolower', array_merge($base, $extra)));
        return $all;
    }
}

if (!function_exists('upload_filename_is_safe')) {
    // Validates all dot segments to catch double-extension attacks (e.g. evil.php.jpg). Returns '' on success.
    function upload_filename_is_safe($originalName)
    {
        $name = strtolower(basename($originalName));
        if ($name === '' || $name === '.' || $name === '..') {
            return 'Invalid file name.';
        }
        // Null bytes and control chars have been used in RCE exploits.
        if (strpbrk($name, "\0\r\n") !== false) {
            return 'Invalid characters in file name.';
        }
        $blocked = upload_blocked_extensions();
        $parts   = explode('.', $name);
        array_shift($parts); // drop the base name, check every extension segment
        foreach ($parts as $seg) {
            $seg = trim($seg);
            if ($seg === '') {
                continue;
            }
            if (in_array($seg, $blocked, true)) {
                return 'Files of type "' . $seg . '" are not allowed.';
            }
        }
        return '';
    }
}

if (!function_exists('upload_sniff_mime')) {
    // Reads magic bytes via finfo to detect renamed-extension files. Returns '' on finfo failure.
    function upload_sniff_mime($absPath)
    {
        if (!function_exists('finfo_open')) {
            return '';
        }
        $h = @finfo_open(FILEINFO_MIME_TYPE);
        if ($h === false) {
            return '';
        }
        $mime = (string) @finfo_file($h, $absPath);
        @finfo_close($h);
        return strtolower($mime);
    }
}

if (!function_exists('upload_mime_matches_ext')) {
    // Returns false when sniffed MIME contradicts the declared extension (likely a renamed file).
    function upload_mime_matches_ext($ext, $sniffedMime)
    {
        $ext = strtolower(trim($ext, '.'));
        $mime = strtolower($sniffedMime);
        if ($ext === '' || $mime === '') {
            return true; // can't decide either way; let the allow-list gate it
        }
        $map = [
            'pdf'  => ['application/pdf'],
            'doc'  => ['application/msword', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
            'xls'  => ['application/vnd.ms-excel', 'application/octet-stream'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
            'csv'  => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
            'txt'  => ['text/plain'],
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
        ];
        if (!isset($map[$ext])) {
            return true; // unknown ext: let the allow-list gate it
        }
        return in_array($mime, $map[$ext], true);
    }
}
