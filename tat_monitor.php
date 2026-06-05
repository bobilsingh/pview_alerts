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
 *   php /var/www/pview_alerts/tat_monitor.php
 *
 * Linux cron (every minute):
 *   * * * * * php /var/www/pview_alerts/tat_monitor.php >> /var/log/tat_monitor.log 2>&1
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

// Exclusive file lock prevents duplicate runs if a previous tick is still processing.
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

$cronStartTime  = microtime(true);
$cronStartedAt  = date('Y-m-d H:i:s');
$cronStatus     = 'ok';
$cronSummaryLog = [];

$tickets = $appModel->ticketActiveForTatCheck();

$cronTicketsChecked = count($tickets);
echo "TAT monitor - " . date('Y-m-d H:i:s') . " - checking " . $cronTicketsChecked . " active tickets\n";

foreach ($tickets as $ticket) {
    $level         = (int) ($ticket['current_level'] ?? 1);
    $flowId        = (int) ($ticket['flow_id'] ?? 0);
    $stateId       = (int) ($ticket['current_state_id'] ?? 0);
    $enteredAt     = strtotime((string) ($ticket['state_entered_at'] ?? ''));
    $tatLevelCount = max(1, min(4, (int) ($ticket['tat_level_count'] ?? 4)));

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

    if ($level < $tatLevelCount) {
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

    // Terminal escalation: flip status and notify the top-level pool.
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

$qResult = process_notification_queue();
echo "Notification queue drained: sent=" . $qResult['sent']
   . " failed=" . $qResult['failed']
   . " retried=" . $qResult['retried'] . "\n";
$cronNotifsSent   = (int) ($qResult['sent']   ?? 0);
$cronNotifsFailed = (int) ($qResult['failed'] ?? 0);

// Prune stale log tables once per cron sweep.
try {
    $db = \Config\Database::connect();
    $retainDays    = (int) app_setting_int('log_retention_days', 30);
    if ($retainDays < 1) { $retainDays = 30; }
    $oldCutoff     = date('Y-m-d H:i:s', time() - ($retainDays * 86400));
    $apiCutoff     = date('Y-m-d H:i:s', time() - 86400); // api_request_log: always 1 day
    $db->table('api_request_log')->where('requested_at <', $apiCutoff)->delete();
    $db->table('login_attempts')->where('attempted_at <', $oldCutoff)->delete();
    echo "Log tables pruned: api_request_log < " . $apiCutoff . ", login_attempts < " . $oldCutoff . "\n";
} catch (\Throwable $e) {
    error_log('pview alert >> tat_monitor log prune failed: ' . $e->getMessage());
}

try {
    $cronDb          = \Config\Database::connect();
    $cronDurationMs  = (int) round((microtime(true) - $cronStartTime) * 1000);
    $cronSummaryText = 'Checked ' . $cronTicketsChecked . ' tickets; sent ' . $cronNotifsSent . ' notifs';
    $cronDb->table('cron_runs')->insert([
        'script'          => 'tat_monitor',
        'started_at'      => $cronStartedAt,
        'finished_at'     => date('Y-m-d H:i:s'),
        'duration_ms'     => $cronDurationMs,
        'status'          => $cronStatus,
        'tickets_checked' => $cronTicketsChecked,
        'notifs_sent'     => $cronNotifsSent,
        'notifs_failed'   => $cronNotifsFailed,
        'output_summary'  => $cronSummaryText,
    ]);
    $minKeep = $cronDb->table('cron_runs')
        ->where('script', 'tat_monitor')
        ->orderBy('id', 'desc')
        ->limit(1)
        ->offset(99)
        ->get()->getRowArray();
    if (!empty($minKeep)) {
        $cronDb->table('cron_runs')
            ->where('script', 'tat_monitor')
            ->where('id <', (int) $minKeep['id'])
            ->delete();
    }
} catch (\Throwable $e) {
    error_log('pview alert >> tat_monitor cron_runs insert failed: ' . $e->getMessage());
}

flock($lockFp, LOCK_UN);
fclose($lockFp);

echo "Done.\n";
