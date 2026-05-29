<?php

/**
 * TAT monitor.
 *
 * Escalates active tickets whose time-in-current-level exceeds the
 * configured TAT. For each ticket:
 *
 *   1. Skip if the current state is final (workflow already at the end).
 *   2. Look up an escalation_matrix override for (flow, state, level);
 *      if present, use its TAT minutes and notify-user list.
 *      Otherwise fall back to the state's own lN_tat_minutes / lN_user_ids.
 *   3. If TAT is breached at L1-L3, bump to the next level and notify
 *      that level's user pool with a templated email.
 *   4. At L4, flip status to 'escalated' and notify the L4 pool too —
 *      otherwise an admin-attention ticket sits silently.
 *
 * Usage:
 *   php /var/www/pview-alert-system/tat_monitor.php
 *
 * Linux cron (every minute):
 *   * * * * * php /var/www/pview-alert-system/tat_monitor.php >> /var/log/tat_monitor.log 2>&1
 *
 * Refuses to run over HTTP (root .htaccess already hides it; this is the
 * belt-and-suspenders).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use App\Models\App_model;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/app/Config/Paths.php';

$paths = new Config\Paths();

define('FCPATH',     realpath(__DIR__ . '/public')                       . DIRECTORY_SEPARATOR);
define('APPPATH',    realpath(rtrim($paths->appDirectory,      '\\/ ')) . DIRECTORY_SEPARATOR);
define('ROOTPATH',   realpath(APPPATH . '..')                            . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory,   '\\/ ')) . DIRECTORY_SEPARATOR);
define('WRITEPATH',  realpath(rtrim($paths->writableDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('TESTPATH',   realpath(rtrim($paths->testsDirectory,    '\\/ ') ?: __DIR__) . DIRECTORY_SEPARATOR);

(new \CodeIgniter\Config\DotEnv(ROOTPATH))->load();
$envName = $_ENV['CI_ENVIRONMENT'] ?? $_SERVER['CI_ENVIRONMENT'] ?? getenv('CI_ENVIRONMENT') ?: 'production';
define('ENVIRONMENT', $envName);

require_once APPPATH . 'Config/Constants.php';
require_once SYSTEMPATH . 'Common.php';
if (is_file(APPPATH . 'Config/Boot/' . ENVIRONMENT . '.php')) {
    require_once APPPATH . 'Config/Boot/' . ENVIRONMENT . '.php';
}

\Config\Services::autoloader()->initialize(new \Config\Autoload(), new \Config\Modules())->register();
\Config\Services::autoloader()->loadHelpers();

require_once APPPATH . 'Helpers/alert_helper.php';

$appModel = new App_model();

// CONC-01: Enforce single-instance execution via an exclusive file lock.
// If a previous run is still in progress (e.g. SMTP is slow), the new
// cron tick exits immediately instead of pulling the same notification
// rows and sending duplicate emails.
$lockFile = WRITEPATH . 'cache/tat_monitor.lock';
$lockFp = @fopen($lockFile, 'w');
if ($lockFp === false) {
    // Cannot open lock file — log and exit safely rather than running unguarded.
    echo "TAT monitor - " . date('Y-m-d H:i:s') . " - cannot open lock file; skipping run.\n";
    exit;
}
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo "TAT monitor - " . date('Y-m-d H:i:s') . " - already running. Exiting.\n";
    fclose($lockFp);
    exit;
}

$tickets = $appModel->ticketActiveForTatCheck();

echo "TAT monitor - " . date('Y-m-d H:i:s') . " - checking " . count($tickets) . " active tickets\n";

foreach ($tickets as $ticket) {
    $level     = (int) ($ticket['current_level'] ?? 1);
    $flowId    = (int) ($ticket['flow_id'] ?? 0);
    $stateId   = (int) ($ticket['current_state_id'] ?? 0);
    $enteredAt = strtotime((string) ($ticket['state_entered_at'] ?? ''));

    if ($enteredAt === false) {
        echo "  [" . $ticket['alarm_id'] . "] skipped: invalid state_entered_at\n";
        continue;
    }

    // Override → state fallback. Matrix wins when present.
    $tatMinutes = (int) ($ticket['l' . $level . '_tat_minutes'] ?? 60);
    $notifyList = $appModel->stateLevelUsers($ticket, $level);
    $rule = $appModel->escalationRule($flowId, $stateId, $level);
    if (!empty($rule)) {
        $tatMinutes = (int) $rule['tat_minutes'];
        if (!empty($rule['notify_users'])) {
            $notifyList = $rule['notify_users'];
        }
    }

    $expiresAt = $enteredAt + ($tatMinutes * 60);
    if (time() < $expiresAt) {
        continue;
    }

    if ($level < 4) {
        $newLevel = $level + 1;

        $appModel->ticketEscalateLevel((int) $ticket['id'], $newLevel);
        $appModel->ticketLogAction((int) $ticket['id'], 'level_escalated', [
            'from_level'          => $level,
            'to_level'            => $newLevel,
            'comment'             => 'Auto-escalated: TAT breached at L' . $level,
            'performed_by_system' => 'tat_monitor',
        ]);

        $state = $appModel->stateGetById($stateId);
        // Re-resolve notify list for the NEW level (override-aware).
        $nextLevelUsers = $appModel->stateLevelUsers($state, $newLevel);
        $nextRule = $appModel->escalationRule($flowId, $stateId, $newLevel);
        if (!empty($nextRule) && !empty($nextRule['notify_users'])) {
            $nextLevelUsers = $nextRule['notify_users'];
        }

        if (!empty($nextLevelUsers)) {
            notify_ticket_event('level_escalated', $ticket, [
                'from_level'    => $level,
                'to_level'      => $newLevel,
                'current_level' => $newLevel,
                'actor_name'    => 'tat_monitor',
            ], $nextLevelUsers);
        }

        echo "  [" . $ticket['alarm_id'] . "] L" . $level . " -> L" . $newLevel . "\n";
        continue;
    }

    // Level == 4 → terminal escalation. Flip status, notify the L4 pool
    // (admins) so the ticket doesn't sit silently at the top of the chain.
    $appModel->ticketUpdate((int) $ticket['id'], [
        'status'     => 'escalated',
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    $appModel->ticketLogAction((int) $ticket['id'], 'level_escalated', [
        'from_level'          => 4,
        'to_level'            => 4,
        'comment'             => 'L4 TAT breached - admin attention required',
        'performed_by_system' => 'tat_monitor',
    ]);

    if (!empty($notifyList)) {
        notify_ticket_event('tat_breach', $ticket, [
            'level'      => 4,
            'actor_name' => 'tat_monitor',
        ], $notifyList);
    }

    echo "  [" . $ticket['alarm_id'] . "] L4 breached - flagged escalated and notified L4 pool\n";
}

// Drain anything enqueued during this run (or by user requests since the
// last cron tick) so notifications go out within the same minute they
// were triggered.
$qResult = process_notification_queue();
echo "Notification queue drained: sent=" . $qResult['sent']
   . " failed=" . $qResult['failed']
   . " retried=" . $qResult['retried'] . "\n";

// PERF-01: Prune api_request_log once per cron sweep instead of on every
// individual API call. Keeps the ingestion endpoint fast and avoids heavy
// lock contention on the log table under telemetry floods.
try {
    $db = \Config\Database::connect();
    $oldCutoff = date('Y-m-d H:i:s', time() - 86400);
    $db->table('api_request_log')->where('requested_at <', $oldCutoff)->delete();
    echo "api_request_log pruned: entries older than " . $oldCutoff . " removed.\n";
} catch (\Throwable $e) {
    error_log('pview alert >> tat_monitor api_request_log prune failed: ' . $e->getMessage());
}

// Release the exclusive lock so the next cron tick can acquire it normally.
flock($lockFp, LOCK_UN);
fclose($lockFp);

echo "Done.\n";
