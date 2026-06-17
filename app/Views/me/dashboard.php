<?php
// Per-user dashboard preferences. Loaded into session at login by
// user_model::setSession() and exposed to views via user_dashboard_pref().
// This page is open to every logged-in user (not super_admin only) so
// each operator can tune their own home page.

$layoutArr = [];
if (isset($layout) && is_array($layout)) {
  $layoutArr = $layout;
}

$defaultProjectId = 0;
if (isset($layoutArr['default_project_id'])) {
  $defaultProjectId = (int) $layoutArr['default_project_id'];
}

$kpiVisible = ['open' => 1, 'critical' => 1, 'major' => 1, 'resolved' => 1];
if (isset($layoutArr['kpi_visible']) && is_array($layoutArr['kpi_visible'])) {
  foreach (['open', 'critical', 'major', 'resolved'] as $k) {
    if (isset($layoutArr['kpi_visible'][$k])) {
      $kpiVisible[$k] = (int) $layoutArr['kpi_visible'][$k];
    }
  }
}

$defaultTrendRange = 0;
if (isset($layoutArr['default_trend_range'])) {
  $defaultTrendRange = (int) $layoutArr['default_trend_range'];
}

$rangeOptions = app_setting_csv('dashboard_trend_ranges');
$rangesInt = [];
foreach ($rangeOptions as $r) {
  $n = (int) $r;
  if ($n >= 1 && $n <= 365) {
    $rangesInt[] = $n;
  }
}
if (empty($rangesInt)) {
  $rangesInt = [7, 15, 30];
}
?>

<div class="page-head">
  <div>
    <h2>Dashboard Preferences</h2>
    <div class="subtitle">Personalise what you see when you land on the dashboard. These settings only affect you.</div>
  </div>
  <a href="<?= site_url('dashboard'); ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
</div>

<form method="post" action="<?= site_url('me/dashboard'); ?>" data-loading-form="1">
  <div class="card mb-3">
    <div class="card-header">
      <strong>Default project filter</strong>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-2">When set, the dashboard KPIs, severity mix and trend chart will be scoped to this project on page load. Pick <em>All projects</em> to keep the broader view.</p>
      <select name="default_project_id" class="form-select" style="max-width: 420px;">
        <option value="0">All projects (no filter)</option>
        <?php foreach ($projects as $p) { ?>
          <option value="<?= (int) $p['id']; ?>" <?php if ($defaultProjectId === (int) $p['id']) {
                                                    echo 'selected';
                                                  } ?>>
            <?= esc($p['name']); ?>
          </option>
        <?php } ?>
      </select>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <strong>KPI cards on the dashboard</strong>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-3">Uncheck any card you don't want to see. At least one card stays visible automatically.</p>
      <div class="row g-3">
        <div class="col-md-3">
          <div class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="kpi_open" id="kpi_open"
              <?php if ($kpiVisible['open'] === 1) {
                echo 'checked';
              } ?>>
            <label class="form-check-label" for="kpi_open">Open Tickets</label>
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="kpi_critical" id="kpi_critical"
              <?php if ($kpiVisible['critical'] === 1) {
                echo 'checked';
              } ?>>
            <label class="form-check-label" for="kpi_critical">Critical</label>
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="kpi_major" id="kpi_major"
              <?php if ($kpiVisible['major'] === 1) {
                echo 'checked';
              } ?>>
            <label class="form-check-label" for="kpi_major">Major</label>
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="kpi_resolved" id="kpi_resolved"
              <?php if ($kpiVisible['resolved'] === 1) {
                echo 'checked';
              } ?>>
            <label class="form-check-label" for="kpi_resolved">Resolved</label>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <strong>Default trend range</strong>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-2">Which time window the Ticket Trend chart opens at. You can still switch via the 7/15/30 picker on the dashboard.</p>
      <select name="default_trend_range" class="form-select" style="max-width: 220px;">
        <option value="0">Use system default (<?= (int) $rangesInt[0]; ?> days)</option>
        <?php foreach ($rangesInt as $r) { ?>
          <option value="<?= (int) $r; ?>" <?php if ($defaultTrendRange === (int) $r) {
                                              echo 'selected';
                                            } ?>>
            <?= (int) $r; ?> days
          </option>
        <?php } ?>
      </select>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">
    <i class="bi bi-check-lg"></i> Save Preferences
  </button>
  <a href="<?= site_url('dashboard'); ?>" class="btn btn-light">Cancel</a>
</form>