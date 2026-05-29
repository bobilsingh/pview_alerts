<?php
if (!function_exists('view_value')) {
  function view_value($source, $key, $default = '')
  {
    if (is_array($source) && array_key_exists($key, $source)) {
      return $source[$key];
    }

    return $default;
  }
}

if (!isset($trendLabels) || !is_array($trendLabels)) {
  $trendLabels = [];
}
if (!isset($trendValues) || !is_array($trendValues)) {
  $trendValues = [];
}

$infoCount = 0;
$majorMixCount = 0;
$criticalMixCount = 0;
if (isset($alertTypeCounts) && is_array($alertTypeCounts)) {
  if (isset($alertTypeCounts['info'])) {
    $infoCount = (int) $alertTypeCounts['info'];
  }
  if (isset($alertTypeCounts['major'])) {
    $majorMixCount = (int) $alertTypeCounts['major'];
  }
  if (isset($alertTypeCounts['critical'])) {
    $criticalMixCount = (int) $alertTypeCounts['critical'];
  }
}

// KPI visibility prefs — default all four visible when the user hasn't set them.
$show = ['open' => 1, 'critical' => 1, 'major' => 1, 'resolved' => 1];
if (isset($kpiVisible) && is_array($kpiVisible)) {
  foreach (['open', 'critical', 'major', 'resolved'] as $k) {
    if (isset($kpiVisible[$k])) {
      $show[$k] = (int) $kpiVisible[$k];
    }
  }
}

// Pinned-project label — empty when no default project pref is set.
$pinnedProjectLabel = '';
if (isset($prefProjectName) && $prefProjectName !== '') {
  $pinnedProjectLabel = $prefProjectName;
}
?>

<div class="page-head">
  <div>
    <h2>NOC Dashboard</h2>
    <div class="subtitle">Live overview of network alerts, ticket flow and escalations.</div>
    <?php if ($pinnedProjectLabel !== '') { ?>
      <div class="mt-2">
        <span class="badge bg-info text-dark">
          <i class="bi bi-pin-angle-fill"></i> Filtered to project: <strong><?= esc($pinnedProjectLabel); ?></strong>
        </span>
        <a href="<?= site_url('me/dashboard'); ?>" class="small text-decoration-none ms-2">change</a>
      </div>
    <?php } ?>
  </div>
  <div class="d-flex align-items-center gap-2">
    <span class="time-chip"><i class="bi bi-clock"></i> <?= esc(strtoupper(date('D, d M Y H:i'))); ?></span>
    <a href="<?= site_url('me/dashboard'); ?>" class="btn btn-light" title="Customize my dashboard">
      <i class="bi bi-sliders"></i> Customize
    </a>
    <a href="<?= site_url('tickets/create'); ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Raise Ticket</a>
  </div>
</div>

<div class="kpi-grid">
  <?php if ($show['open'] === 1) { ?>
    <a class="kpi-link"
      href="<?= site_url('tickets/all?status=active'); ?>"
      title="Tickets currently in Open / In Progress / Escalated. Click to see the list.">
      <div class="kpi-card is-blue">
        <div class="kpi-label">Open Tickets</div>
        <div class="kpi-value"><?= (int) $openCount; ?></div>
        <div class="kpi-trend"><i class="bi bi-activity"></i> currently active</div>
        <div class="kpi-icon"><i class="bi bi-inbox-fill"></i></div>
      </div>
    </a>
  <?php } ?>
  <?php if ($show['critical'] === 1) { ?>
    <a class="kpi-link"
      href="<?= site_url('tickets/all?alert_type=critical&status=active'); ?>"
      title="Critical-severity tickets that are still active (Open / In Progress / Escalated). Resolved or Closed criticals are not counted here.">
      <div class="kpi-card is-red">
        <div class="kpi-label">Critical</div>
        <div class="kpi-value"><?= (int) $criticalCount; ?></div>
        <div class="kpi-trend"><i class="bi bi-exclamation-octagon"></i> P1 &middot; active only</div>
        <div class="kpi-icon"><i class="bi bi-exclamation-octagon-fill"></i></div>
      </div>
    </a>
  <?php } ?>
  <?php if ($show['major'] === 1) { ?>
    <a class="kpi-link"
      href="<?= site_url('tickets/all?alert_type=major&status=active'); ?>"
      title="Major-severity tickets that are still active (Open / In Progress / Escalated). Resolved or Closed majors are not counted here.">
      <div class="kpi-card is-amber">
        <div class="kpi-label">Major</div>
        <div class="kpi-value"><?= (int) $majorCount; ?></div>
        <div class="kpi-trend"><i class="bi bi-exclamation-triangle"></i> P2 &middot; active only</div>
        <div class="kpi-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
      </div>
    </a>
  <?php } ?>
  <?php if ($show['resolved'] === 1) { ?>
    <a class="kpi-link"
      href="<?= site_url('tickets/all?status=resolved'); ?>"
      title="All tickets ever marked Resolved (lifetime total).">
      <div class="kpi-card is-green">
        <div class="kpi-label">Resolved</div>
        <div class="kpi-value"><?= (int) $resolvedCount; ?></div>
        <div class="kpi-trend up"><i class="bi bi-check-circle"></i> all-time</div>
        <div class="kpi-icon"><i class="bi bi-check-circle-fill"></i></div>
      </div>
    </a>
  <?php } ?>
</div>

<div class="row g-3 mb-3 dashboard-charts">
  <div class="col-xl-8">
    <div class="card chart-card">
      <?php
      // Both vars come from the dashboard() controller. Fall back to
      // sane defaults if a future caller renders this view directly.
      $currentRange = 7;
      if (isset($trendRange)) {
        $currentRange = (int) $trendRange;
      }
      $rangeOptions = [];
      if (isset($trendRangeOptions) && is_array($trendRangeOptions)) {
        foreach ($trendRangeOptions as $days) {
          $d = (int) $days;
          if ($d > 0) {
            $rangeOptions[$d] = $d . 'd';
          }
        }
      }
      if (empty($rangeOptions)) {
        $rangeOptions = [7 => '7d', 15 => '15d', 30 => '30d'];
      }
      ?>
      <div class="chart-title">
        <h6><i class="bi bi-graph-up text-primary"></i> Ticket Trend - Last <?= (int) $currentRange; ?> days</h6>
        <div class="trend-range-picker">
          <?php foreach ($rangeOptions as $days => $label) { ?>
            <?php
            $rangeUrl = site_url('dashboard') . '?range=' . $days;
            $isActiveRange = '';
            if ($currentRange === $days) {
              $isActiveRange = 'is-active';
            }
            ?>
            <a class="filter-pill <?= $isActiveRange; ?>" href="<?= esc($rangeUrl); ?>"><?= esc($label); ?></a>
          <?php } ?>
        </div>
      </div>
      <div class="chart-wrap trend-chart-wrap">
        <canvas id="trendChart" data-chart="trend" data-labels="<?= esc(implode('||', $trendLabels), 'attr'); ?>" data-values="<?= esc(implode(',', array_map('strval', $trendValues)), 'attr'); ?>"></canvas>
      </div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="card chart-card severity-card">
      <div class="chart-title">
        <h6><i class="bi bi-pie-chart text-primary"></i> Severity Mix</h6>
        <span class="chart-meta">By alert type</span>
      </div>
      <div class="chart-wrap severity-chart-wrap">
        <canvas id="severityChart" data-chart="severity" data-info="<?= $infoCount; ?>" data-major="<?= $majorMixCount; ?>" data-critical="<?= $criticalMixCount; ?>"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="card recent-card">
  <div class="card-header">
    <strong><i class="bi bi-list-ul text-primary"></i> Recent Tickets</strong>
    <span class="text-muted small">Escalated: <span class="badge bg-danger"><?= (int) $tatBreached; ?></span></span>
  </div>
  <div class="card-body p-0">
    <table
      id="recentTickets"
      class="table align-middle mb-0"
      data-simple-table="1"
      data-order-col="6"
      data-order-dir="desc">
      <thead>
        <tr>
          <th>Alarm ID</th>
          <th>Title</th>
          <th>Severity</th>
          <th>Status</th>
          <th>State</th>
          <th>TAT</th>
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentTickets as $t) { ?>
          <?php
          // tat_expires_at() returns '' for resolved/closed tickets and final states
          // so the countdown stops automatically. JS renders an "—" pill on empty.
          $expires = tat_expires_at($t);

          $assigneeName = '-';
          if (isset($t['assignee_name']) && $t['assignee_name'] !== '') {
            $assigneeName = $t['assignee_name'];
          }

          $alertType = '';
          if (isset($t['alert_type'])) {
            $alertType = $t['alert_type'];
          }

          $status = '';
          if (isset($t['status'])) {
            $status = $t['status'];
          }

          $stateName = '-';
          if (isset($t['state_name']) && $t['state_name'] !== '') {
            $stateName = $t['state_name'];
          }
          ?>
          <tr>
            <td><a href="<?= site_url('tickets/detail/' . $t['alarm_id']); ?>" class="alarm-id"><?= esc($t['alarm_id']); ?></a></td>
            <td>
              <div class="fw-semibold text-truncate" style="max-width:320px;" title="<?= esc($t['title']); ?>"><?= esc($t['title']); ?></div>
              <small class="text-muted">by <?= esc($assigneeName); ?></small>
            </td>
            <td><?= alert_badge($alertType); ?></td>
            <td><?= status_badge($status); ?></td>
            <td><span class="text-muted"><?= esc($stateName); ?></span></td>
            <td><span class="tat-countdown" data-tat-expires="<?= esc($expires); ?>" data-tat-total-ms="<?= (int) (tat_total_minutes($t) * 60000); ?>"></span></td>
            <td class="text-muted small"><?= esc($t['created_at']); ?></td>
          </tr>
        <?php } ?>
        <?php /* No custom empty-state row here — DataTables initialises
                 this table via data-simple-table="1" and counts <td> cells
                 per row to validate column count (TN/18). A single
                 colspan="7" row throws "Incorrect column count"; instead
                 we leave tbody empty and let DataTables show its built-in
                 "No data available in table" message. */ ?>
      </tbody>
    </table>
  </div>
</div>