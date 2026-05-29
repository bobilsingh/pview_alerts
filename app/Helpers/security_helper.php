<?php

// Login & API rate-limiting and file-upload hardening.
// All thresholds come from app_settings; functions no-op gracefully when
// the backing tables (login_attempts, api_request_log) don't exist yet.

if (!function_exists('client_ip')) {
    // Does NOT trust X-Forwarded-For — add proxy logic here if you put a
    // reverse proxy in front of the app.
    function client_ip(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        if ($ip === '') {
            return '0.0.0.0';
        }
        // Truncate just in case (IPv6 max is 45 chars; DB column is 45).
        return substr($ip, 0, 45);
    }
}

if (!function_exists('security_table_exists')) {
    function security_table_exists(string $table): bool
    {
        try {
            $db = \Config\Database::connect();
            return $db->tableExists($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('login_is_locked')) {
    // Per-user lockout: N failures against THIS login_identifier within the
    // sliding window locks THIS user. The IP is recorded with each attempt
    // for forensic logging but is not used to gate access — on a shared
    // VPN / NAT the IP is everyone's, so an IP-wide lockout would punish
    // real users for someone else's typo.
    //
    // Returns [locked, remaining_seconds, attempts]. ($ip is accepted for
    // signature back-compat but only used in audit logs by the caller.)
    function login_is_locked(string $ip, string $login): array
    {
        $maxAttempts = (int) app_setting('login_max_attempts', 3);
        $windowMin   = (int) app_setting('login_lockout_minutes', 10);
        if ($maxAttempts <= 0 || $windowMin <= 0) {
            return ['locked' => false, 'remaining_seconds' => 0, 'attempts' => 0];
        }
        if (!security_table_exists('login_attempts')) {
            return ['locked' => false, 'remaining_seconds' => 0, 'attempts' => 0];
        }

        $db     = \Config\Database::connect();
        $cutoff = date('Y-m-d H:i:s', time() - ($windowMin * 60));

        $userFails = (int) $db->table('login_attempts')
            ->where('success', 0)
            ->where('attempted_at >=', $cutoff)
            ->where('login_identifier', $login)
            ->countAllResults();

        if ($userFails < $maxAttempts) {
            return ['locked' => false, 'remaining_seconds' => 0, 'attempts' => $userFails];
        }

        // Lockout expires windowMin minutes after the OLDEST failure in the
        // window for this user — so a slow drip of fails doesn't get a
        // perpetually-rolling lock.
        $oldest = $db->table('login_attempts')
            ->where('success', 0)
            ->where('attempted_at >=', $cutoff)
            ->where('login_identifier', $login)
            ->orderBy('attempted_at', 'asc')
            ->limit(1)
            ->get()->getRowArray();
        $remaining = $windowMin * 60;
        if (!empty($oldest['attempted_at'])) {
            $remaining = max(0, ($windowMin * 60) - (time() - strtotime($oldest['attempted_at'])));
        }
        return ['locked' => true, 'remaining_seconds' => $remaining, 'attempts' => $userFails];
    }
}

if (!function_exists('login_attempt_record')) {
    function login_attempt_record(string $ip, string $login, bool $success): void
    {
        if (!security_table_exists('login_attempts')) {
            return;
        }
        try {
            $db = \Config\Database::connect();
            $db->table('login_attempts')->insert([
                'ip'               => $ip,
                'login_identifier' => substr($login, 0, 150),
                'success'          => $success ? 1 : 0,
                'attempted_at'     => date('Y-m-d H:i:s'),
            ]);
            // Trim rows older than 7 days to keep the table bounded.
            $cutoff = date('Y-m-d H:i:s', time() - (7 * 86400));
            $db->table('login_attempts')
                ->where('attempted_at <', $cutoff)
                ->delete();
        } catch (\Throwable $e) {
            error_log('pview alert >> login_attempt_record failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('login_attempts_clear')) {
    // Reset the per-user failed-attempt counter after a successful login.
    //
    // Only THIS user's failures are wiped — failures recorded against OTHER
    // usernames from the same IP are preserved on purpose. A real user proving
    // themselves doesn't absolve an attacker who's been probing other accounts
    // from the same outbound IP, so the credential-stuffing counter survives.
    // ($ip is accepted for signature back-compat but no longer used.)
    function login_attempts_clear(string $ip, string $login): void
    {
        if (!security_table_exists('login_attempts')) {
            return;
        }
        try {
            $db = \Config\Database::connect();
            $db->table('login_attempts')
                ->where('success', 0)
                ->where('login_identifier', $login)
                ->delete();
        } catch (\Throwable $e) {
            // non-fatal
        }
    }
}

if (!function_exists('api_rate_check')) {
    // Returns [allowed, retry_after_seconds].
    // Records the request as a side-effect when allowed.
    function api_rate_check(int $apiKeyId, string $endpoint): array
    {
        $perMin  = (int) app_setting('api_rate_per_minute', 60);
        $perHour = (int) app_setting('api_rate_per_hour', 1000);
        if ($apiKeyId <= 0 || ($perMin <= 0 && $perHour <= 0)) {
            return ['allowed' => true, 'retry_after_seconds' => 0];
        }
        if (!security_table_exists('api_request_log')) {
            return ['allowed' => true, 'retry_after_seconds' => 0];
        }
        $db = \Config\Database::connect();
        $now = time();
        if ($perMin > 0) {
            $minCutoff = date('Y-m-d H:i:s', $now - 60);
            $minCount = (int) $db->table('api_request_log')
                ->where('api_key_id', $apiKeyId)
                ->where('requested_at >=', $minCutoff)
                ->countAllResults();
            if ($minCount >= $perMin) {
                return ['allowed' => false, 'retry_after_seconds' => 60];
            }
        }
        if ($perHour > 0) {
            $hourCutoff = date('Y-m-d H:i:s', $now - 3600);
            $hourCount = (int) $db->table('api_request_log')
                ->where('api_key_id', $apiKeyId)
                ->where('requested_at >=', $hourCutoff)
                ->countAllResults();
            if ($hourCount >= $perHour) {
                return ['allowed' => false, 'retry_after_seconds' => 3600];
            }
        }
        try {
            $db->table('api_request_log')->insert([
                'api_key_id'   => $apiKeyId,
                'endpoint'     => substr($endpoint, 0, 100),
                'requested_at' => date('Y-m-d H:i:s'),
            ]);
            // PERF-01: The 24-hour pruning DELETE has been moved to tat_monitor.php
            // (runs once per cron tick) to avoid lock contention on every API call.
        } catch (\Throwable $e) {
            error_log('pview alert >> api_rate_check insert failed: ' . $e->getMessage());
        }
        return ['allowed' => true, 'retry_after_seconds' => 0];
    }
}

if (!function_exists('upload_blocked_extensions')) {
    // Hard-coded denylist that applies even if an admin adds a dangerous ext
    // to upload_allowed_ext by mistake.
    function upload_blocked_extensions(): array
    {
        $extra = app_setting_csv('upload_blocked_ext', []);
        $base = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'pht', 'sh', 'bash', 'zsh', 'exe', 'bat', 'cmd', 'com', 'msi', 'scr', 'pif', 'js', 'mjs', 'vbs', 'wsf', 'ps1', 'jsp', 'asp', 'aspx', 'cgi', 'pl', 'py', 'rb', 'htaccess', 'htpasswd', 'ini', 'conf', 'htm', 'html', 'xhtml', 'svg'];
        $all = array_unique(array_map('strtolower', array_merge($base, $extra)));
        return $all;
    }
}

if (!function_exists('upload_filename_is_safe')) {
    // Checks every dotted segment, not just the last — catches evil.php.jpg.
    // Returns '' when safe, error message otherwise.
    function upload_filename_is_safe(string $originalName): string
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
    // Reads magic bytes via finfo — defeats "rename .php to .pdf" tricks.
    // Returns '' on failure so the caller can fall back to the header MIME.
    function upload_sniff_mime(string $absPath): string
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
    // Returns false when sniffed MIME contradicts the extension — likely a renamed file.
    function upload_mime_matches_ext(string $ext, string $sniffedMime): bool
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
