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

helper('app');

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

        <div class="mb-3">
          <label class="form-label">TAT Level Count
            <small class="text-muted fw-normal ms-1">— how many escalation levels before a ticket is flagged as escalated (1–4)</small>
          </label>
          <?php $currentTatLevel = isset($flow['tat_level_count']) ? (int) $flow['tat_level_count'] : 4; ?>
          <select name="tat_level_count" class="form-select">
            <?php foreach ([1, 2, 3, 4] as $lvl) { ?>
              <option value="<?= $lvl; ?>" <?php if ($currentTatLevel === $lvl) { echo 'selected'; } ?>>
                L<?= $lvl; ?> <?php if ($lvl === 4) { echo '(default)'; } ?>
              </option>
            <?php } ?>
          </select>
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

        <div class="mt-3 d-flex justify-content-end gap-2">
          <a href="<?= site_url('flows'); ?>" class="btn btn-light">Cancel</a>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i>
            <?php if ($isEdit) { ?>Update<?php } else { ?>Create<?php } ?>
          </button>
        </div>
      </form>
    </div>
  </div>

<?php } else if ($view === 'states') { ?>

  <?php
  $isEditState = !empty($editState);
  $editSid     = $isEditState ? (int) $editState['id'] : 0;

  $editL = [];
  if ($isEditState) {
    foreach ([1, 2, 3, 4] as $lvl) {
      $raw = $editState['l' . $lvl . '_user_ids'] ?? '';
      $arr = json_decode((string) $raw, true);
      $editL[$lvl] = is_array($arr) ? array_map('strval', $arr) : [];
    }
  }
  ?>

  <div class="page-head">
    <div>
      <h2><?= esc($flow['name']); ?> - States</h2>
      <div class="subtitle">
        Add states and drag them into order. The ticket moves forward automatically through each state.
        <br>Configure <strong>Allowed Backward States</strong> on any state where tickets may need to be sent back.
      </div>
    </div>
    <a href="<?= site_url('flows'); ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> Back to flows</a>
  </div>

  <?php if (!empty($states)) { ?>
    <div id="flowStepper" class="mb-3">
      <?= flow_widget_html(
        flow_vis_designer_data($states, isset($transitions) ? $transitions : []),
        ['subtitle' => 'Workflow diagram', 'variant' => 'designer', 'legend' => true]
      ); ?>
    </div>
  <?php } ?>

  <div class="row g-3">
    <!-- Left: state list -->
    <div class="col-lg-7">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong><i class="bi bi-list-ol text-primary"></i> States</strong>
          <small class="text-muted"><i class="bi bi-arrows-move"></i> Drag to reorder</small>
        </div>
        <div class="card-body p-2">
          <ul id="stateList" class="state-list list-unstyled mb-0"
            data-reorder-url="<?= site_url('flows/reorder_states'); ?>"
            data-flow-id="<?= (int) $flow['id']; ?>"
            data-preview-target="#flowStepper">

            <?php foreach ($states as $i => $s) { ?>
              <?php
              $sid       = (int) $s['id'];
              $l1c       = count($decode($s['l1_user_ids'] ?? ''));
              $l2c       = count($decode($s['l2_user_ids'] ?? ''));
              $l3c       = count($decode($s['l3_user_ids'] ?? ''));
              $l4c       = count($decode($s['l4_user_ids'] ?? ''));
              $sbwd      = $bwdLabels[$sid] ?? [];
              $isEditing = ($sid === $editSid);
              $isLast    = ($i === count($states) - 1);
              ?>
              <li class="state-item<?= $isEditing ? ' state-item--focus' : ''; ?>"
                  data-id="<?= $sid; ?>" data-parent-id="<?= (int) ($s['parent_state_id'] ?? 0); ?>">
                <div class="d-flex align-items-start gap-2">
                  <i class="bi bi-grip-vertical text-muted state-grip mt-1"></i>
                  <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                      <strong><?= esc($s['name']); ?></strong>
                      <?php if (!empty($s['is_initial'])) { ?><span class="badge bg-success">INITIAL</span><?php } ?>
                      <?php if (!empty($s['is_final'])) { ?><span class="badge bg-dark">CLOSING</span><?php } ?>
                      <?php if (empty($s['is_final']) && $l1c === 0) { ?>
                        <span class="badge bg-warning text-dark" title="No L1 operators assigned — tickets in this state cannot be auto-assigned"><i class="bi bi-exclamation-triangle-fill"></i> No L1 pool</span>
                      <?php } ?>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-wrap mt-1 small">
                      <span class="badge bg-light text-dark border">L1: <?= (int) $s['l1_tat_minutes']; ?>m·<?= $l1c; ?>u</span>
                      <span class="badge bg-light text-dark border">L2: <?= (int) $s['l2_tat_minutes']; ?>m·<?= $l2c; ?>u</span>
                      <span class="badge bg-light text-dark border">L3: <?= (int) $s['l3_tat_minutes']; ?>m·<?= $l3c; ?>u</span>
                      <span class="badge bg-light text-dark border">L4: <?= (int) $s['l4_tat_minutes']; ?>m·<?= $l4c; ?>u</span>
                    </div>
                    <?php if (!empty($sbwd)) { ?>
                      <div class="mt-1 small text-danger">
                        <i class="bi bi-arrow-left-circle"></i>
                        Can send back to: <?= esc(implode(', ', $sbwd)); ?>
                      </div>
                    <?php } ?>
                  </div>
                  <div class="d-flex flex-column gap-1 text-end">
                    <a href="<?= site_url('flows/states/' . $flow['id'] . '?edit_state=' . $sid); ?>"
                       class="btn btn-sm btn-outline-secondary" title="Edit this state" aria-label="Edit this state">
                      <i class="bi bi-pencil" aria-hidden="true"></i>
                    </a>
                    <a href="<?= site_url('flows/delete_state/' . $sid); ?>"
                       class="btn btn-sm btn-outline-danger"
                       data-method="post"
                       data-confirm-message="Delete this state?"
                       aria-label="Delete this state">
                      <i class="bi bi-trash" aria-hidden="true"></i>
                    </a>
                  </div>
                </div>
              </li>
            <?php } ?>

            <?php if (empty($states)) { ?>
              <li class="text-center text-muted py-4">No states yet. Add the first state using the form.</li>
            <?php } ?>
          </ul>
        </div>
      </div>
    </div>

    <!-- Right: Add / Edit state form -->
    <div class="col-lg-5">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>
            <i class="bi bi-<?= $isEditState ? 'pencil-square' : 'plus-square'; ?> text-primary"></i>
            <?= $isEditState ? 'Edit state: ' . esc($editState['name']) : 'Add new state'; ?>
          </strong>
          <?php if ($isEditState) { ?>
            <a href="<?= site_url('flows/states/' . $flow['id']); ?>" class="btn btn-sm btn-light">
              <i class="bi bi-x-lg"></i> Cancel
            </a>
          <?php } ?>
        </div>
        <div class="card-body">
          <form method="post" action="<?= site_url('flows/save_state'); ?>">
            <input type="hidden" name="flow_id" value="<?= (int) $flow['id']; ?>">
            <?php if ($isEditState) { ?>
              <input type="hidden" name="id" value="<?= $editSid; ?>">
            <?php } ?>

            <div class="mb-3">
              <label class="form-label">State name *</label>
              <input type="text" name="name" class="form-control" required
                placeholder="e.g. L1 Validation"
                value="<?= $isEditState ? esc($editState['name']) : ''; ?>">
            </div>

            <div class="d-flex gap-3 mb-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_initial" id="isInitial"
                  <?= ($isEditState && !empty($editState['is_initial'])) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="isInitial">Initial state</label>
              </div>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_final" id="isFinal"
                  <?= ($isEditState && !empty($editState['is_final'])) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="isFinal">Closing state</label>
              </div>
            </div>

            <?php
            // Build list of valid parent candidates: exclude self and closing states.
            $parentCandidates = array_filter($states, function ($sp) use ($isEditState, $editSid) {
              if ($isEditState && (int) $sp['id'] === $editSid) { return false; }
              if (!empty($sp['is_final'])) { return false; }
              return true;
            });
            if (!empty($parentCandidates)) {
              $currentParent = $isEditState ? (int) ($editState['parent_state_id'] ?? 0) : 0;
            ?>
            <div class="mb-3" id="parentStateWrap">
              <label class="form-label">Parent State <small class="text-muted">(defines branching structure)</small></label>
              <select name="parent_state_id" class="form-select">
                <option value="">No parent (root-level)</option>
                <?php foreach ($parentCandidates as $sp) { ?>
                  <option value="<?= (int) $sp['id']; ?>" <?= ($currentParent === (int) $sp['id']) ? 'selected' : ''; ?>>
                    <?= esc($sp['name']); ?>
                  </option>
                <?php } ?>
              </select>
              <div class="form-text">Attach as a child of another state to create a branch. Leave blank for root-level states (initial, closing).</div>
            </div>
            <?php } ?>

            <?php
            // Backward state candidates: exclude self and closing state.
            $bwdCandidates = array_filter($states, function ($sp) use ($isEditState, $editSid) {
              if ($isEditState && (int) $sp['id'] === $editSid) { return false; }
              if (!empty($sp['is_final'])) { return false; }
              return true;
            });
            $editBwdIdsArr = $editBwdIds ?? [];
            if (!empty($bwdCandidates)) {
            ?>
            <div class="mb-3">
              <label class="form-label">Allowed Backward States <small class="text-muted">(send-back targets)</small></label>
              <select name="backward_state_ids[]" class="form-select select2" multiple>
                <?php foreach ($bwdCandidates as $sp) { ?>
                  <option value="<?= (int) $sp['id']; ?>"
                    <?= in_array((int) $sp['id'], $editBwdIdsArr, true) ? 'selected' : ''; ?>>
                    <?= esc($sp['name']); ?>
                  </option>
                <?php } ?>
              </select>
              <div class="form-text">Operators can send tickets back to these states for rework. Leave empty to disable backward movement from this state.</div>
            </div>
            <?php } ?>


            <hr>
            <div class="text-muted small mb-2 fw-semibold">Level escalation users &amp; TAT</div>

            <?php
            $defaultTat = [60, 120, 240, 480];
            for ($lvl = 1; $lvl <= 4; $lvl++) {
              $tatVal   = $isEditState ? (int) ($editState['l' . $lvl . '_tat_minutes'] ?? $defaultTat[$lvl - 1]) : $defaultTat[$lvl - 1];
              $lvlUsers = $isEditState ? $editL[$lvl] : [];
            ?>
              <div class="row g-2 mb-2 align-items-end">
                <div class="col-7">
                  <label class="form-label small fw-semibold mb-1">L<?= $lvl; ?> users</label>
                  <select name="l<?= $lvl; ?>_user_ids[]" class="form-select select2" multiple>
                    <?php foreach ($users as $u) {
                      $sel = in_array((string) $u['user_id'], $lvlUsers, true) ? 'selected' : '';
                    ?>
                      <option value="<?= esc((string) $u['user_id']); ?>" <?= $sel; ?>><?= esc($u['name']); ?> - <?= esc($u['email']); ?></option>
                    <?php } ?>
                  </select>
                </div>
                <div class="col-5">
                  <label class="form-label small fw-semibold mb-1">L<?= $lvl; ?> TAT (min)</label>
                  <input type="number" min="1" name="l<?= $lvl; ?>_tat_minutes"
                    class="form-control" value="<?= $tatVal; ?>">
                </div>
              </div>
            <?php } ?>

            <button type="submit" class="btn btn-primary mt-2 w-100">
              <i class="bi bi-<?= $isEditState ? 'check-lg' : 'plus-lg'; ?>"></i>
              <?= $isEditState ? 'Update State' : 'Add State'; ?>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php } ?>