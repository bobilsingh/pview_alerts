<?php
$today = date('Y-m-d');
?>

<div class="page-head">
  <div>
    <h2><i class="bi bi-clock-history text-primary"></i> Cron Management</h2>
    <div class="subtitle">Last run status and history for all scheduled scripts.</div>
  </div>
  <a href="<?= site_url('settings'); ?>" class="btn btn-light">
    <i class="bi bi-arrow-left"></i> Settings
  </a>
</div>

<?php if (!$tableExists): ?>
  <div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    The <code>cron_runs</code> table does not exist yet.
    Run <code>php spark migrate</code> to create it, then let the cron run once.
  </div>
<?php else: ?>

  <!-- Summary cards -->
  <?php if (empty($lastRuns)): ?>
    <div class="alert alert-info">
      <i class="bi bi-info-circle"></i>
      No cron runs recorded yet. The TAT monitor cron has not run since the table was created.
    </div>
  <?php else: ?>
    <?php
    // Pre-compute stats from $runs (last 100 records already fetched).
    $todayStr = date('Y-m-d');
    $totalRuns = count($runs);
    $okRuns = 0;
    $todayRuns = 0;
    $todayTickets = 0;
    $todayNotifs = 0;
    $durTotal = 0;
    foreach ($runs as $r) {
      $statusVal = 'ok';
      if (isset($r['status'])) {
        $statusVal = $r['status'];
      }
      if ($statusVal === 'ok') {
        $okRuns++;
      }

      $durMsVal = 0;
      if (isset($r['duration_ms'])) {
        $durMsVal = $r['duration_ms'];
      }
      $durTotal += (int) $durMsVal;

      $startedAtVal = '';
      if (isset($r['started_at'])) {
        $startedAtVal = $r['started_at'];
      }
      if (substr($startedAtVal, 0, 10) === $todayStr) {
        $todayRuns++;

        $ticketsCheckedVal = 0;
        if (isset($r['tickets_checked'])) {
          $ticketsCheckedVal = $r['tickets_checked'];
        }
        $todayTickets += (int) $ticketsCheckedVal;

        $notifsSentVal = 0;
        if (isset($r['notifs_sent'])) {
          $notifsSentVal = $r['notifs_sent'];
        }
        $todayNotifs += (int) $notifsSentVal;
      }
    }

    $successRate = 0;
    if ($totalRuns > 0) {
      $successRate = round($okRuns / $totalRuns * 100, 1);
    }

    $avgDurSec = 0;
    if ($totalRuns > 0) {
      $avgDurSec = round($durTotal / $totalRuns / 1000, 2);
    }

    $successCls = 'text-danger';
    if ($successRate >= 95) {
      $successCls = 'text-success';
    } elseif ($successRate >= 80) {
      $successCls = 'text-warning';
    }

    $progressBarCls = 'bg-danger';
    if ($successRate >= 95) {
      $progressBarCls = 'bg-success';
    } elseif ($successRate >= 80) {
      $progressBarCls = 'bg-warning';
    }
    ?>
    <div class="row g-3 mb-4">

      <!-- Per-script last-run cards -->
      <?php foreach ($lastRuns as $script => $run): ?>
        <?php
        $runStatusVal = 'ok';
        if (isset($run['status'])) {
          $runStatusVal = $run['status'];
        }
        $isOk = $runStatusVal === 'ok';
        $statusCls = 'bg-danger';
        if ($isOk) {
          $statusCls = 'bg-success';
        }
        $statusLbl = 'FAILED';
        if ($isOk) {
          $statusLbl = 'OK';
        }

        $runDurMsVal = 0;
        if (isset($run['duration_ms'])) {
          $runDurMsVal = $run['duration_ms'];
        }
        $durSec = round((int) $runDurMsVal / 1000, 2);

        $minutesAgo = '';
        if (!empty($run['started_at'])) {
          $diff = time() - strtotime($run['started_at']);
          if ($diff < 120) {
            $minutesAgo = $diff . 's ago';
          } elseif ($diff < 3600) {
            $minutesAgo = round($diff / 60) . 'm ago';
          } else {
            $minutesAgo = round($diff / 3600, 1) . 'h ago';
          }
        }

        $runStartedAt = '-';
        if (isset($run['started_at'])) {
          $runStartedAt = $run['started_at'];
        }

        $runTicketsChecked = 0;
        if (isset($run['tickets_checked'])) {
          $runTicketsChecked = (int) $run['tickets_checked'];
        }

        $runNotifsSent = 0;
        if (isset($run['notifs_sent'])) {
          $runNotifsSent = (int) $run['notifs_sent'];
        }

        $runNotifsFailed = 0;
        if (isset($run['notifs_failed'])) {
          $runNotifsFailed = (int) $run['notifs_failed'];
        }
        $failedCls = '';
        if ($runNotifsFailed > 0) {
          $failedCls = 'text-danger';
        }
        ?>
        <div class="col-lg-4">
          <div class="card h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <strong class="text-primary"><i class="bi bi-terminal"></i> <?= esc($script); ?></strong>
                <span class="badge <?= $statusCls; ?>"><?= $statusLbl; ?></span>
              </div>
              <div class="small text-muted mb-1">
                <i class="bi bi-clock"></i>
                Last run: <?= esc($runStartedAt); ?>
                <?php if ($minutesAgo): ?>
                  <span class="ms-1 text-muted">(<?= esc($minutesAgo); ?>)</span>
                <?php endif; ?>
              </div>
              <div class="small text-muted mb-1">
                <i class="bi bi-stopwatch"></i> Duration: <?= esc($durSec); ?>s
              </div>
              <div class="row g-2 mt-2 text-center">
                <div class="col-4">
                  <div class="fw-bold fs-5"><?= $runTicketsChecked; ?></div>
                  <div class="small text-muted">Tickets</div>
                </div>
                <div class="col-4">
                  <div class="fw-bold fs-5 text-success"><?= $runNotifsSent; ?></div>
                  <div class="small text-muted">Sent</div>
                </div>
                <div class="col-4">
                  <div class="fw-bold fs-5 <?= $failedCls; ?>">
                    <?= $runNotifsFailed; ?>
                  </div>
                  <div class="small text-muted">Failed</div>
                </div>
              </div>
              <?php if (!empty($run['output_summary'])): ?>
                <div class="small text-muted mt-2 border-top pt-2">
                  <i class="bi bi-chat-text"></i> <?= esc($run['output_summary']); ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <!-- Today's activity stats -->
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-header">
            <strong><i class="bi bi-calendar-check text-primary"></i> Today's Activity</strong>
          </div>
          <div class="card-body">
            <div class="row g-2 text-center mb-3">
              <div class="col-4">
                <div class="fw-bold fs-4"><?= $todayRuns; ?></div>
                <div class="small text-muted">Runs</div>
              </div>
              <div class="col-4">
                <div class="fw-bold fs-4"><?= $todayTickets; ?></div>
                <div class="small text-muted">Tickets</div>
              </div>
              <div class="col-4">
                <div class="fw-bold fs-4 text-success"><?= $todayNotifs; ?></div>
                <div class="small text-muted">Notifs</div>
              </div>
            </div>
            <div class="border-top pt-3">
              <div class="d-flex justify-content-between small mb-2">
                <span class="text-muted">Success rate (last <?= $totalRuns; ?> runs)</span>
                <span class="fw-bold <?= $successCls; ?>"><?= $successRate; ?>%</span>
              </div>
              <div class="progress" style="height:6px;">
                <div class="progress-bar <?= $progressBarCls; ?>" style="width:<?= $successRate; ?>%"></div>
              </div>
              <div class="d-flex justify-content-between small mt-2">
                <span class="text-muted">Avg duration</span>
                <span class="fw-bold"><?= $avgDurSec; ?>s</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Cron schedule reference -->
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-header">
            <strong><i class="bi bi-terminal-fill text-primary"></i> Cron Schedule</strong>
          </div>
          <div class="card-body">
            <p class="small text-muted mb-2">Add this line to your server crontab (<code>crontab -e</code>):</p>
            <pre class="small rounded p-2 mb-2"
              style="background:#1e293b;color:#7dd3fc;word-break:break-all;white-space:pre-wrap;border:1px solid #334155;">* * * * * /home/pview/apache_pview/php/bin/php <?= esc(ROOTPATH); ?>tat_monitor.php >> <?= esc(WRITEPATH); ?>logs/tat_monitor.log 2>&amp;1</pre>
            <div class="small text-muted">
              <i class="bi bi-info-circle"></i>
              Runs every minute. The script enforces a single-instance lock so overlapping runs are skipped automatically.
            </div>
            <div class="small text-muted mt-2">
              <i class="bi bi-archive"></i>
              Keeps the last <strong>100</strong> run records per script. Older rows are pruned automatically.
            </div>
          </div>
        </div>
      </div>

    </div>
  <?php endif; ?>

  <!-- Run History Table -->
  <div class="card">

    <div class="card-header">
      <strong><i class="bi bi-list-ul text-primary"></i> Run History</strong>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" id="cronRunsTable" style="width:100%"
          data-table-url="<?= site_url('cron_panel/data_table'); ?>">
          <thead class="table-light">
            <tr>
              <th>Script</th>
              <th>Started</th>
              <th>Duration</th>
              <th class="text-center">Tickets</th>
              <th class="text-center">Sent</th>
              <th class="text-center">Failed</th>
              <th class="text-center">Status</th>
              <th>Summary</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>


<?php endif; ?>