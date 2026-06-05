<?php

/**
 * ALERTS VIEW.
 * Combines four related admin screens. Controller passes $view:
 *   - 'list'       : alert-definitions list
 *   - 'form'       : add / edit alert definition
 *   - 'escalation' : escalation matrix list + add-rule form
 *   - 'api_keys'   : API keys list + generate form
 */
if (!function_exists('view_value')) {
  function view_value($source, $key, $default = '')
  {
    if (is_array($source) && array_key_exists($key, $source)) {
      return $source[$key];
    }

    return $default;
  }
}

if (!isset($view) || $view === '') {
  $view = 'list';
}
?>

<?php if ($view === 'list') { ?>

  <div class="page-head">
    <div>
      <h2>Alert Definitions</h2>
      <div class="subtitle">Reusable alert templates per project &amp; flow.</div>
    </div>
    <a href="<?= site_url('alerts/add'); ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Alert</a>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <table id="alertsTable" class="table align-middle mb-0"
        data-table-url="<?= site_url('alerts/data_table'); ?>">
        <thead>
          <tr>
            <th>Name</th>
            <th>Project</th>
            <th>Flow</th>
            <th>Severity</th>
            <th>Threshold</th>
            <th>Active</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

<?php } else if ($view === 'form') { ?>
  <?php
  $isEdit = !empty($alert);
  if ($isEdit) {
    $action = site_url('alerts/update/' . $alert['id']);
    $pageTitle = 'Edit Alert Definition';
  } else {
    $action = site_url('alerts/save');
    $pageTitle = 'Add Alert Definition';
  }

  $existingNotify = [];
  if ($isEdit && !empty($alert['notify_user_ids'])) {
    $tmp = json_decode((string) $alert['notify_user_ids'], true);
    if (is_array($tmp)) {
      // Post-2026-05-21: notify_user_ids stores user_id strings.
      $existingNotify = array_map('strval', $tmp);
    }
  }

  $currentType = 'info';
  $alertName = '';
  $alertDescription = '';
  $currentProject = 0;
  $currentFlow = 0;
  $thresholdValue = '';
  $thresholdUnit = '';
  $isActive = false;

  if ($isEdit) {
    if (isset($alert['alert_type'])) {
      $currentType = $alert['alert_type'];
    }
    if (isset($alert['name'])) {
      $alertName = $alert['name'];
    }
    if (isset($alert['description'])) {
      $alertDescription = $alert['description'];
    }
    if (isset($alert['project_id'])) {
      $currentProject = (int) $alert['project_id'];
    }
    if (isset($alert['flow_id'])) {
      $currentFlow = (int) $alert['flow_id'];
    }
    if (isset($alert['threshold_value'])) {
      $thresholdValue = $alert['threshold_value'];
    }
    if (isset($alert['threshold_unit'])) {
      $thresholdUnit = $alert['threshold_unit'];
    }
    if (!empty($alert['is_active'])) {
      $isActive = true;
    }
  }

  $severityOptions = [
    'info' => 'Info',
    'major' => 'Major',
    'critical' => 'Critical',
  ];
  ?>

  <div class="page-head">
    <div>
      <h2><?= esc($pageTitle); ?></h2>
    </div>
    <a href="<?= site_url('alerts'); ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> Back</a>
  </div>

  <div class="card">
    <div class="card-body">
      <form method="post" action="<?= esc($action); ?>" data-loading-form="1" data-dirty-guard="1">

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Name *</label>
            <input type="text" name="name" class="form-control" required maxlength="200"
              autofocus data-char-counter="1"
              value="<?= esc($alertName); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Severity</label>
            <select name="alert_type" class="form-select">
              <?php foreach ($severityOptions as $key => $label) { ?>
                <option value="<?= $key; ?>" <?php if ($currentType === $key) {
                                                echo 'selected';
                                              } ?>>
                  <?= $label; ?>
                </option>
              <?php } ?>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3" maxlength="1000"
              data-char-counter="1"><?= esc($alertDescription); ?></textarea>
          </div>

          <div class="col-md-6">
            <label class="form-label">Project *</label>
            <select name="project_id" class="form-select" required>
              <option value="">- Select project -</option>
              <?php foreach ($projects as $p) { ?>
                <option value="<?= (int) $p['id']; ?>" <?php if ((int) $p['id'] === $currentProject) {
                                                          echo 'selected';
                                                        } ?>>
                  <?= esc($p['name']); ?>
                </option>
              <?php } ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Flow *</label>
            <select name="flow_id" class="form-select" required>
              <option value="">Select flow</option>
              <?php foreach ($flows as $f) { ?>
                <option value="<?= (int) $f['id']; ?>" <?php if ((int) $f['id'] === $currentFlow) {
                                                          echo 'selected';
                                                        } ?>>
                  <?= esc($f['name']); ?>
                </option>
              <?php } ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Threshold Value</label>
            <input type="text" name="threshold_value" class="form-control"
              value="<?= esc($thresholdValue); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Threshold Unit</label>
            <input type="text" name="threshold_unit" class="form-control"
              placeholder="%, ms, errors/min" value="<?= esc($thresholdUnit); ?>">
          </div>

          <div class="col-12">
            <label class="form-label">Notify Users</label>
            <select id="alertNotifyUsers" name="notify_user_ids[]" class="form-select select2" multiple size="6">
              <?php foreach ($users as $u) { ?>
                <?php $uVal = (string) $u['user_id']; ?>
                <option value="<?= esc($uVal); ?>"
                  <?php if (in_array($uVal, $existingNotify, true)) {
                    echo 'selected';
                  } ?>>
                  <?= esc($u['name']); ?> - <?= esc($u['email']); ?>
                </option>
              <?php } ?>
            </select>
          </div>

          <?php if ($isEdit) { ?>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                  <?php if ($isActive) {
                    echo 'checked';
                  } ?>>
                <label class="form-check-label" for="isActive">Active</label>
              </div>
            </div>
          <?php } ?>
        </div>

        <div class="mt-3 d-flex justify-content-end gap-2">
          <a href="<?= site_url('alerts'); ?>" class="btn btn-light">Cancel</a>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i>
            <?php if ($isEdit) { ?>Update<?php } else { ?>Create<?php } ?>
          </button>
        </div>
      </form>
    </div>
  </div>

<?php } else if ($view === 'escalation') { ?>

  <div class="page-head">
    <div>
      <h2>Escalation Matrix</h2>
      <div class="subtitle">Custom rules: when a level breaches TAT, who gets notified next.</div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header"><strong>Existing rules</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive" style="max-height: 440px; overflow-y: auto;">
            <table class="table mb-0 align-middle">
              <thead>
                <tr>
                  <th>Flow</th>
                  <th>State</th>
                  <th>Level</th>
                  <th>Escalate after (min)</th>
                  <th>Severity</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $r) { ?>
                  <?php
                  $flowName = null;
                  $stateName = null;
                  if (isset($r['flow_name'])) {
                    $flowName = $r['flow_name'];
                  }
                  if (isset($r['state_name'])) {
                    $stateName = $r['state_name'];
                  }
                  ?>
                  <tr>
                    <td><?= esc(or_default($flowName, '-')); ?></td>
                    <td><?= esc(or_default($stateName, '-')); ?></td>
                    <td><?= level_badge((int) $r['level']); ?></td>
                    <td><?= (int) $r['escalate_after']; ?></td>
                    <td><?= alert_badge($r['alert_type']); ?></td>
                    <td class="text-end">
                      <a href="<?= site_url('escalation/delete/' . (int) $r['id']); ?>"
                        class="btn btn-sm btn-outline-danger"
                        data-method="post"
                        data-confirm-message="Delete this escalation rule?">
                        <i class="bi bi-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php } ?>
                <?php if (empty($rows)) { ?>
                  <tr>
                    <td colspan="6" class="text-center text-muted py-4">No rules yet - add one on the right.</td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card">
        <div class="card-header"><strong>Add new rule</strong></div>
        <div class="card-body">
          <form method="post" action="<?= site_url('escalation/save'); ?>">
            <div class="mb-3">
              <label class="form-label">Flow</label>
              <select name="flow_id" id="escalationFlowSelect" class="form-select"
                data-load-target="#escalationStateSelect"
                data-load-url="<?= site_url('escalation/states_by_flow'); ?>"
                data-item-type="state"
                data-empty-text="Select flow first"
                data-default-text="Select state"
                data-loading-text="Loading states..."
                data-no-data-text="No states in this flow"
                data-error-text="Failed to load states"
                required>
                <option value="">Select flow</option>
                <?php foreach ($flows as $f) { ?>
                  <option value="<?= (int) $f['id']; ?>"><?= esc($f['name']); ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">State</label>
              <select name="state_id" id="escalationStateSelect" class="form-select" required disabled>
                <option value="">Select flow first</option>
              </select>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-6">
                <label class="form-label">Level</label>
                <select name="level" class="form-select">
                  <option value="1">L1</option>
                  <option value="2">L2</option>
                  <option value="3">L3</option>
                  <option value="4">L4</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label">Escalate after (min)</label>
                <input type="number" name="escalate_after" class="form-control" value="60" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Severity</label>
              <select name="alert_type" class="form-select">
                <option value="info">Info</option>
                <option value="major" selected>Major</option>
                <option value="critical">Critical</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Notify users</label>
              <select id="escalationNotifyUsers" name="notify_user_ids[]" class="form-select select2" multiple size="5">
                <?php foreach ($users as $u) { ?>
                  <option value="<?= esc((string) $u['user_id']); ?>"><?= esc($u['name']); ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="d-flex justify-content-end mt-2">
              <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add rule</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php } else if ($view === 'api_keys') { ?>
  <?php $newKeyValue = '';
  if (!empty($newKey)) {
    $newKeyValue = $newKey;
  } ?>

  <div class="page-head">
    <div>
      <h2>API Keys</h2>
      <div class="subtitle">For external systems calling /api/raise.</div>
    </div>
  </div>

  <?php if ($newKeyValue !== '') { ?>
    <div class="alert alert-success">
      <strong>New key generated - copy it now (it won't be shown again):</strong>
      <code class="d-block mt-2" style="font-size:14px;"><?= esc($newKeyValue); ?></code>
    </div>
  <?php } ?>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-header"><strong>Existing keys</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table mb-0 align-middle api-keys-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Project</th>
                  <th>Key (masked)</th>
                  <th>Last used</th>
                  <th>Active</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($keys as $k) { ?>
                  <?php
                  $masked = substr($k['api_key'], 0, 6) . '********' . substr($k['api_key'], -4);
                  $projectName = null;
                  $lastUsed = null;
                  if (isset($k['project_name'])) {
                    $projectName = $k['project_name'];
                  }
                  if (isset($k['last_used'])) {
                    $lastUsed = $k['last_used'];
                  }
                  ?>
                  <tr>
                    <td><strong><?= esc($k['name']); ?></strong></td>
                    <td><?= esc(or_default($projectName, '-')); ?></td>
                    <td><code><?= esc($masked); ?></code></td>
                    <td class="text-muted small"><?= esc(or_default($lastUsed, 'Never')); ?></td>
                    <td>
                      <?php if ($k['is_active']) { ?>
                        <span class="badge bg-success">YES</span>
                      <?php } else { ?>
                        <span class="badge bg-dark">NO</span>
                      <?php } ?>
                    </td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-light"
                        href="<?= site_url('api_keys/toggle/' . $k['id']); ?>"
                        data-method="post"
                        data-confirm-message="Toggle this API key's active state?">
                        <i class="bi bi-power"></i> Toggle
                      </a>
                    </td>
                  </tr>
                <?php } ?>
                <?php if (empty($keys)) { ?>
                  <tr>
                    <td colspan="6" class="text-center text-muted py-4">No API keys yet.</td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header"><strong>Generate new key</strong></div>
        <div class="card-body">
          <form method="post" action="<?= site_url('api_keys/generate'); ?>">
            <div class="mb-3">
              <label class="form-label">Name</label>
              <input type="text" name="name" class="form-control" required placeholder="DataPipeline-v2">
            </div>
            <div class="mb-3">
              <label class="form-label">Project</label>
              <select name="project_id" class="form-select" required>
                <option value="">Select project</option>
                <?php foreach ($projects as $p) { ?>
                  <option value="<?= (int) $p['id']; ?>"><?= esc($p['name']); ?></option>
                <?php } ?>
              </select>
            </div>
            <button class="btn btn-primary w-100"><i class="bi bi-key"></i> Generate Key</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <style>
    .api-keys-table th,
    .api-keys-table td {
      white-space: nowrap;
      vertical-align: middle;
    }

    .api-keys-table code {
      font-size: 12px;
    }
  </style>

<?php } ?>