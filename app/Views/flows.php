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

helper('flow');

if (!isset($view) || $view === '') {
  $view = 'list';
}

$decode = function ($raw) {
  $arr = json_decode((string) $raw, true);
  if (!is_array($arr)) {
    return [];
  }

  return array_map('intval', $arr);
};
?>

<?php if ($view === 'list') { ?>

  <div class="page-head">
    <div>
      <h2>Flows</h2>
      <div class="subtitle">Each flow is a sequence of states a ticket moves through.</div>
    </div>
    <a href="<?= site_url('flows/add'); ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Flow</a>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <table id="flowsTable" class="table align-middle mb-0"
        data-table-url="<?= site_url('flows/data_table'); ?>">
        <thead>
          <tr>
            <th>Name</th>
            <th>Project</th>
            <th>States</th>
            <th>Status</th>
            <th>Created By</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

<?php } else if ($view === 'form') { ?>
  <?php
  $isEdit = !empty($flow);
  if ($isEdit) {
    $action = site_url('flows/update/' . $flow['id']);
    $pageTitle = 'Edit Flow';
  } else {
    $action = site_url('flows/save');
    $pageTitle = 'Add Flow';
  }

  $currentProjectId = 0;
  $flowName = '';
  $currentStatus = 'active';

  if ($isEdit) {
    if (isset($flow['project_id'])) {
      $currentProjectId = (int) $flow['project_id'];
    }
    if (isset($flow['name'])) {
      $flowName = $flow['name'];
    }
    if (isset($flow['status'])) {
      $currentStatus = $flow['status'];
    }
  }
  ?>

  <div class="page-head">
    <div>
      <h2><?= esc($pageTitle); ?></h2>
    </div>
    <a href="<?= site_url('flows'); ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> Back</a>
  </div>

  <div class="card">
    <div class="card-body">
      <form method="post" action="<?= esc($action); ?>" data-loading-form="1" data-dirty-guard="1">

        <div class="mb-3">
          <label class="form-label">Project *</label>
          <select name="project_id" class="form-select" required autofocus>
            <option value="">Select project</option>
            <?php foreach ($projects as $p) { ?>
              <option value="<?= (int) $p['id']; ?>"
                <?php if ((int) $p['id'] === $currentProjectId) {
                  echo 'selected';
                } ?>>
                <?= esc($p['name']); ?>
              </option>
            <?php } ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Flow Name *</label>
          <input type="text" name="name" class="form-control" required maxlength="200"
            data-char-counter="1"
            value="<?= esc($flowName); ?>">
        </div>

        <?php if ($isEdit) { ?>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="active" <?php if ($currentStatus === 'active') {
                                        echo 'selected';
                                      } ?>>Active</option>
              <option value="inactive" <?php if ($currentStatus === 'inactive') {
                                          echo 'selected';
                                        } ?>>Inactive</option>
            </select>
          </div>
        <?php } ?>

        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check-lg"></i>
          <?php if ($isEdit) { ?>
            Update
          <?php } else { ?>
            Create
          <?php } ?>
        </button>
        <a href="<?= site_url('flows'); ?>" class="btn btn-light">Cancel</a>
      </form>
    </div>
  </div>

<?php } else if ($view === 'states') { ?>

  <div class="page-head">
    <div>
      <h2><?= esc($flow['name']); ?> - States</h2>
      <div class="subtitle">Add, reorder, and configure flow states. Drag the rows to reorder.</div>
    </div>
    <a href="<?= site_url('flows'); ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> Back to flows</a>
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <strong><i class="bi bi-diagram-3 text-primary"></i> Workflow preview</strong>
      <small class="text-muted">How tickets travel through this flow</small>
    </div>
    <div class="card-body">
      <?php if (!empty($states)) { ?>
        <div id="flowStepper">
          <?= flow_widget_html(
            flow_mermaid_designer_source($states),
            ['subtitle' => 'How tickets travel through this flow', 'variant' => 'designer', 'legend' => true]
          ); ?>
        </div>
      <?php } else { ?>
        <div class="text-center text-muted py-4">
          <i class="bi bi-diagram-3" style="font-size:32px; opacity:0.4;"></i>
          <div class="mt-2">No states yet - add one on the right.</div>
        </div>
      <?php } ?>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong><i class="bi bi-list-ol text-primary"></i> Existing states</strong>
          <small class="text-muted"><i class="bi bi-arrows-move"></i> Drag rows to reorder</small>
        </div>
        <div class="card-body p-2">
          <ul id="stateList" class="state-list list-unstyled mb-0"
            data-reorder-url="<?= site_url('flows/reorder_states'); ?>"
            data-flow-id="<?= (int) $flow['id']; ?>"
            data-preview-target="#flowStepper">
            <?php foreach ($states as $s) { ?>
              <?php
              $rawL1 = '';
              $rawL2 = '';
              $rawL3 = '';
              $rawL4 = '';
              if (isset($s['l1_user_ids'])) {
                $rawL1 = $s['l1_user_ids'];
              }
              if (isset($s['l2_user_ids'])) {
                $rawL2 = $s['l2_user_ids'];
              }
              if (isset($s['l3_user_ids'])) {
                $rawL3 = $s['l3_user_ids'];
              }
              if (isset($s['l4_user_ids'])) {
                $rawL4 = $s['l4_user_ids'];
              }
              $l1 = $decode($rawL1);
              $l2 = $decode($rawL2);
              $l3 = $decode($rawL3);
              $l4 = $decode($rawL4);
              ?>
              <?php $liParentId = isset($s['parent_state_id']) ? (int) $s['parent_state_id'] : 0; ?>
              <li class="state-item" data-id="<?= (int) $s['id']; ?>" data-parent-id="<?= $liParentId; ?>">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-grip-vertical text-muted state-grip"></i>
                  <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                      <strong><?= esc($s['name']); ?></strong>
                      <?php if (!empty($s['is_initial'])) { ?><span class="badge bg-success">INITIAL</span><?php } ?>
                      <?php if (!empty($s['is_final'])) { ?><span class="badge bg-dark">FINAL</span><?php } ?>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-wrap mt-1 small">
                      <span class="badge bg-light text-dark border">L1: <?= (int) $s['l1_tat_minutes']; ?>m - <?= count($l1); ?>u</span>
                      <span class="badge bg-light text-dark border">L2: <?= (int) $s['l2_tat_minutes']; ?>m - <?= count($l2); ?>u</span>
                      <span class="badge bg-light text-dark border">L3: <?= (int) $s['l3_tat_minutes']; ?>m - <?= count($l3); ?>u</span>
                      <span class="badge bg-light text-dark border">L4: <?= (int) $s['l4_tat_minutes']; ?>m - <?= count($l4); ?>u</span>
                    </div>
                  </div>
                  <div class="text-end">
                    <a href="<?= site_url('flows/delete_state/' . $s['id']); ?>"
                      class="btn btn-sm btn-outline-danger"
                      data-method="post"
                      data-confirm-message="Delete this state?">
                      <i class="bi bi-trash"></i>
                    </a>
                  </div>
                </div>
              </li>
            <?php } ?>
            <?php if (empty($states)) { ?>
              <li class="text-center text-muted py-4">No states yet.</li>
            <?php } ?>
          </ul>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card h-100">
        <div class="card-header"><strong><i class="bi bi-plus-square text-primary"></i> Add new state</strong></div>
        <div class="card-body">
          <form method="post" action="<?= site_url('flows/save_state'); ?>">
            <input type="hidden" name="flow_id" value="<?= (int) $flow['id']; ?>">

            <div class="mb-3">
              <label class="form-label">State name *</label>
              <input type="text" name="name" class="form-control" required placeholder="e.g. L1 Validation">
            </div>

            <div class="mb-3">
              <label class="form-label">Parent state <small class="text-muted">(optional - for branching)</small></label>
              <select name="parent_state_id" class="form-select">
                <option value="">None</option>
                <?php foreach ($states as $s) { ?>
                  <option value="<?= (int) $s['id']; ?>"><?= esc($s['name']); ?></option>
                <?php } ?>
              </select>
            </div>

            <div class="d-flex gap-3 mb-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_initial" id="isInitial">
                <label class="form-check-label" for="isInitial">Initial state</label>
              </div>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_final" id="isFinal">
                <label class="form-check-label" for="isFinal">Final state</label>
              </div>
            </div>

            <hr>
            <div class="text-muted small mb-2 fw-semibold">Level escalation users &amp; TAT</div>

            <?php
            $defaults = [60, 120, 240, 480];
            for ($lvl = 1; $lvl <= 4; $lvl++) {
            ?>
              <div class="row g-2 mb-2 align-items-end">
                <div class="col-7">
                  <label class="form-label small fw-semibold mb-1">L<?= $lvl; ?> users</label>
                  <select id="levelUsers<?= $lvl; ?>" name="l<?= $lvl; ?>_user_ids[]" class="form-select level-users select2" multiple>
                    <?php foreach ($users as $u) { ?>
                      <option value="<?= esc((string) $u['user_id']); ?>"><?= esc($u['name']); ?> - <?= esc($u['email']); ?></option>
                    <?php } ?>
                  </select>
                </div>
                <div class="col-5">
                  <label class="form-label small fw-semibold mb-1">L<?= $lvl; ?> TAT (min)</label>
                  <input type="number" min="1" name="l<?= $lvl; ?>_tat_minutes"
                    class="form-control" value="<?= $defaults[$lvl - 1]; ?>">
                </div>
              </div>
            <?php
            }
            ?>

            <button type="submit" class="btn btn-primary mt-2 w-100">
              <i class="bi bi-plus-lg"></i> Add State
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php } ?>