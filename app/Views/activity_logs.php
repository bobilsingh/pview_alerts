<?php
// Activity Log viewer — read-only audit feed of user events.
// Visible to roles with module_permissions row for 'activity_logs' = view.
?>
<div class="page-head">
  <div>
    <h2>Activity Log</h2>
    <div class="subtitle">Centralized history of user events — logins, mutations, navigation, exports. Read-only.</div>
  </div>
  <div>
    <button type="button" id="activityExportBtn" class="btn btn-outline-secondary"
            data-export-url="<?= esc(site_url('activity_logs/export')); ?>">
      <i class="bi bi-download"></i> Export CSV
    </button>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form id="activityFilterForm" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label small mb-1">User</label>
        <input type="text" id="filterUser" class="form-control form-control-sm" placeholder="user_id or name">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Module</label>
        <select id="filterModule" class="form-select form-select-sm">
          <option value="">Any</option>
          <?php foreach ($modules as $m) { ?>
            <option value="<?= esc($m); ?>"><?= esc($m); ?></option>
          <?php } ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Action</label>
        <select id="filterAction" class="form-select form-select-sm">
          <option value="">Any</option>
          <?php foreach ($actions as $a) { ?>
            <option value="<?= esc($a); ?>"><?= esc($a); ?></option>
          <?php } ?>
        </select>
      </div>
      <?php $today = date('Y-m-d'); ?>
      <div class="col-md-2">
        <label class="form-label small mb-1">From</label>
        <input type="date" id="filterFrom" class="form-control form-control-sm"
               value="<?= esc($today); ?>" data-default="<?= esc($today); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">To</label>
        <input type="date" id="filterTo" class="form-control form-control-sm"
               value="<?= esc($today); ?>" data-default="<?= esc($today); ?>">
      </div>
      <div class="col-md-2 text-end">
        <button type="button" id="activityApplyBtn" class="btn btn-sm btn-primary">
          <i class="bi bi-funnel"></i> Apply
        </button>
        <button type="button" id="activityResetBtn" class="btn btn-sm btn-light">
          Reset
        </button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <table id="activityLogsTable" class="table align-middle mb-0" style="width:100%;"
           data-table-url="<?= esc(site_url('activity_logs/data_table')); ?>">
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
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<!-- Custom overlay (not a Bootstrap modal) — the Bootstrap modal manager
     was rendering content without the backdrop in this layout, breaking
     click-outside dismissal. This plain overlay avoids that issue and
     gives us reliable click-the-backdrop / Close-button / Escape-to-close. -->
<div id="activityMetaOverlay" class="activity-meta-overlay" hidden>
  <div class="activity-meta-dialog" role="dialog" aria-modal="true" aria-labelledby="activityMetaTitle">
    <div class="activity-meta-header">
      <h5 id="activityMetaTitle" class="m-0">Event details</h5>
      <button type="button" class="activity-meta-x" aria-label="Close" data-activity-meta-close>
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="activity-meta-body">
      <pre id="activityMetaBody"></pre>
    </div>
    <div class="activity-meta-footer">
      <button type="button" class="btn btn-secondary" data-activity-meta-close>Close</button>
    </div>
  </div>
</div>
