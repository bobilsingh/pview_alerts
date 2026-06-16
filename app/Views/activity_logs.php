<?php
// Activity Log viewer — event log + analytics (analytics tab visible to
// super_admin by default; other roles need activity_logs.analytics permission).
if (isset($canAnalytics)) {
    $canAnalytics = (bool) $canAnalytics;
} else {
    $canAnalytics = false;
}
if (!isset($modules)) {
    $modules = [];
}
if (!isset($actions)) {
    $actions = [];
}
if (!isset($projects)) {
    $projects = [];
}
if (!isset($roles)) {
    $roles = [];
}
if (!isset($statuses)) {
    $statuses = ['success', 'fail'];
}
$today        = date('Y-m-d');
?>

<div class="page-head">
  <div>
    <h2>Activity Log</h2>
    <div class="subtitle">Centralized history of user events — logins, mutations, navigation, exports.</div>
  </div>
  <div></div>
</div>

<?php if ($canAnalytics) { ?>
  <!-- Tab nav -->
  <ul class="nav nav-tabs mb-3" id="activityTabs" role="tablist">
    <li class="nav-item">
      <a class="nav-link active" id="tab-log-link" data-bs-toggle="tab" href="#tab-log" role="tab">
        <i class="bi bi-list-ul"></i> Event Log
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" id="tab-analytics-link" data-bs-toggle="tab" href="#tab-analytics" role="tab">
        <i class="bi bi-bar-chart-line"></i> Analytics
      </a>
    </li>
  </ul>

  <div class="tab-content">

    <!-- =========================================================
     TAB 1 — EVENT LOG
     ========================================================= -->
    <div class="tab-pane fade show active" id="tab-log" role="tabpanel">
    <?php } ?>

    <!-- Filter bar -->
    <div class="card mb-3">
      <?= view('filter', [
        'fbTitle'          => 'Filters',
        'fbCountId'        => 'activityFilterBadge',
        'fbApplyId'        => 'activityApplyBtn',
        'fbResetId'        => 'activityResetBtn',
        'show_date_widget' => true,
        'drFromId'         => 'filterFrom',
        'drToId'           => 'filterTo',
        'drFrom'           => $today,
        'drTo'             => $today,
        'drDefault'        => 'today',
        'drInline'         => true,
      ]); ?>
      <div class="filter-bar-body">
        <form id="activityFilterForm">
          <div class="filter-bar-controls">

            <div class="filter-item filter-item--search">
              <label class="filter-label">User</label>
              <input type="text" id="filterUser" class="form-control form-control-sm" placeholder="user_id or name">
            </div>

            <div class="filter-item">
              <label class="filter-label">Module</label>
              <select id="filterModule" class="form-select form-select-sm">
                <option value="">Any module</option>
                <?php foreach ($modules as $m) { ?>
                  <option value="<?= esc($m); ?>"><?= esc($m); ?></option>
                <?php } ?>
              </select>
            </div>

            <div class="filter-item">
              <label class="filter-label">Action</label>
              <select id="filterAction" class="form-select form-select-sm">
                <option value="">Any action</option>
                <?php foreach ($actions as $a) { ?>
                  <option value="<?= esc($a); ?>"><?= esc($a); ?></option>
                <?php } ?>
              </select>
            </div>

            <div class="filter-item">
              <label class="filter-label">Role</label>
              <select id="filterRole" class="form-select form-select-sm">
                <option value="">Any role</option>
                <?php foreach ($roles as $r) { ?>
                  <option value="<?= esc($r); ?>"><?= esc(str_replace('_', ' ', $r)); ?></option>
                <?php } ?>
              </select>
            </div>

            <div class="filter-item">
              <label class="filter-label">Status</label>
              <select id="filterStatus" class="form-select form-select-sm">
                <option value="">Any status</option>
                <?php foreach ($statuses as $s) { ?>
                  <option value="<?= esc($s); ?>"><?= esc(strtoupper($s)); ?></option>
                <?php } ?>
              </select>
            </div>

            <?php if (!empty($projects)) { ?>
              <div class="filter-item">
                <label class="filter-label">Project</label>
                <select id="filterProject" class="form-select form-select-sm">
                  <option value="">Any project</option>
                  <?php foreach ($projects as $p) { ?>
                    <option value="<?= esc($p['name']); ?>"><?= esc($p['name']); ?></option>
                  <?php } ?>
                </select>
              </div>
            <?php } else { ?>
              <input type="hidden" id="filterProject" value="">
            <?php } ?>

          </div><!-- /filter-bar-controls -->
        </form>
      </div><!-- /filter-bar-body -->
    </div>

    <!-- Event log table -->
    <div class="card">
      <div class="card-body p-0">
        <table id="activityLogsTable" class="table align-middle mb-0" style="width:100%;"
          data-table-url="<?= esc(site_url('activity_logs/data_table')); ?>"
          data-analytics-url="<?= esc(site_url('activity_logs/analytics')); ?>"
          data-export-url="<?= esc(site_url('activity_logs/export')); ?>">
          <thead class="table-light">
            <tr>
              <th>Time</th>
              <th>User</th>
              <th>Module</th>
              <th>Action</th>
              <th>Entity</th>
              <th>Summary</th>
              <th>Login</th>
              <th>Logout</th>
              <th>Source</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <?php if ($canAnalytics) { ?>
    </div><!-- /tab-log -->

    <!-- =========================================================
     TAB 2 — ANALYTICS
     ========================================================= -->
    <div class="tab-pane fade" id="tab-analytics" role="tabpanel">

      <!-- Analytics date range + refresh controls -->
      <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <label class="form-label small mb-0 fw-semibold">Period:</label>
        <?= view('filter', [
          'only_widget' => true,
          'drFromId'    => 'analyticsFrom',
          'drToId'      => 'analyticsTo',
          'drFrom'      => date('Y-m-d', strtotime('-30 days')),
          'drTo'        => $today,
          'drDefault'   => '30d',
        ]); ?>
        <button type="button" id="analyticsApplyBtn" class="btn btn-sm btn-primary">
          <i class="bi bi-funnel"></i> Apply
        </button>
        <span class="text-muted small ms-auto" id="analyticsLastRefresh"></span>
        <span class="badge bg-success" id="analyticsLiveBadge" hidden>
          <i class="bi bi-broadcast"></i> Live
        </span>
      </div>

      <!-- KPI cards row -->
      <div class="row g-3 mb-3" id="analyticsKpiRow">
        <div class="col-6 col-md-3">
          <div class="card text-center">
            <div class="card-body py-3">
              <div class="fs-1 fw-bold text-primary" id="kpiLoginsToday">—</div>
              <div class="text-muted small">Logins today</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card text-center">
            <div class="card-body py-3">
              <div class="fs-1 fw-bold text-success" id="kpiLoginsPeriod">—</div>
              <div class="text-muted small" id="kpiLoginsPeriodLabel">Logins in period</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card text-center">
            <div class="card-body py-3">
              <div class="fs-1 fw-bold text-danger" id="kpiFailedToday">—</div>
              <div class="text-muted small">Failed logins today</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card text-center">
            <div class="card-body py-3">
              <div class="fs-1 fw-bold text-warning" id="kpiFailedPeriod">—</div>
              <div class="text-muted small">Failed logins in period</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3 analytics-row">

        <!-- Top active users -->
        <div class="col-lg-5">
          <div class="card analytics-card">
            <div class="card-header">
              <strong><i class="bi bi-people text-primary"></i> Top Active Users</strong>
            </div>
            <div class="card-body p-0 analytics-card-body">
              <table class="table table-sm table-hover mb-0" id="analyticsTopUsersTable">
                <thead class="table-light sticky-top">
                  <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th class="text-end">Events</th>
                    <th>Last Seen</th>
                  </tr>
                </thead>
                <tbody id="analyticsTopUsersBody">
                  <tr>
                    <td colspan="4" class="text-center text-muted py-3">Loading…</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Module usage chart -->
        <div class="col-lg-4">
          <div class="card analytics-card">
            <div class="card-header">
              <strong><i class="bi bi-bar-chart text-primary"></i> Module Usage</strong>
            </div>
            <div class="card-body analytics-card-body analytics-chart-body">
              <canvas id="analyticsModuleChart"></canvas>
            </div>
          </div>
        </div>

        <!-- Session duration -->
        <div class="col-lg-3">
          <div class="card analytics-card">
            <div class="card-header">
              <strong><i class="bi bi-clock-history text-primary"></i> Avg Session</strong>
            </div>
            <div class="card-body p-0 analytics-card-body">
              <table class="table table-sm mb-0" id="analyticsSessionTable">
                <thead class="table-light sticky-top">
                  <tr>
                    <th>User</th>
                    <th class="text-end">Avg (min)</th>
                    <th class="text-end">Sessions</th>
                  </tr>
                </thead>
                <tbody id="analyticsSessionBody">
                  <tr>
                    <td colspan="3" class="text-center text-muted py-3">Loading…</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>

      <!-- Failed events -->
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong><i class="bi bi-exclamation-triangle text-danger"></i> Failed Events</strong>
          <span class="badge bg-danger" id="analyticsFailedBadge">0</span>
        </div>
        <div class="card-body p-0">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Time</th>
                <th>User</th>
                <th>Module</th>
                <th>Action</th>
                <th>Summary</th>
                <th>Source</th>
              </tr>
            </thead>
            <tbody id="analyticsFailedBody">
              <tr>
                <td colspan="6" class="text-center text-muted py-3">Loading…</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /tab-analytics -->
  </div><!-- /tab-content -->


<?php } // end if canAnalytics 
?>