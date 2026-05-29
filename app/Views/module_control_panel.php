<?php
// Build the role list from the roles table (passed in by the controller
// as $rolesAll). No hardcoded fallback — the page is super-admin-only
// and the controller is the single source of truth for which roles
// exist; an empty $roles renders an empty grid rather than re-inventing
// the legacy three built-ins.
$roles = [];
if (isset($rolesAll) && is_array($rolesAll)) {
  foreach ($rolesAll as $r) {
    $roles[(string) $r['role_key']] = (string) $r['label'];
  }
}

$modules = module_registry();

// Let's index the current permissions by [role][module_key]
$indexed = [];
foreach ($permissions as $p) {
  $r = $p['role'];
  $m = $p['module_key'];
  $indexed[$r][$m] = $p;
}
?>

<div class="page-head">
  <div>
    <h2>Module Control Panel</h2>
    <div class="subtitle">Manage page visibility, sidebar menus, and operational action permissions dynamically per role.</div>
  </div>
  <a href="<?= site_url('settings'); ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> Settings</a>
</div>

<form method="post" action="<?= site_url('module_control_panel/save'); ?>" data-loading-form="1">
  <div class="card mb-4">
    <div class="card-header p-0">
      <ul class="nav nav-tabs border-bottom-0" id="roleTabs" role="tablist">
        <?php
        $firstTab = true;
        foreach ($roles as $roleKey => $roleLabel) {
        ?>
          <li class="nav-item" role="presentation">
            <button class="nav-link py-3 px-4 <?php if ($firstTab === true) {
                                                echo 'active';
                                                $firstTab = false;
                                              } ?>"
              id="tab-<?= esc($roleKey); ?>"
              data-bs-toggle="tab"
              data-bs-target="#pane-<?= esc($roleKey); ?>"
              type="button"
              role="tab"
              aria-controls="pane-<?= esc($roleKey); ?>"
              aria-selected="true">
              <strong><?= esc($roleLabel); ?></strong>
            </button>
          </li>
        <?php } ?>
      </ul>
    </div>

    <div class="tab-content" id="roleTabContent">
      <?php
      $firstPane = true;
      foreach ($roles as $roleKey => $roleLabel) {
      ?>
        <div class="tab-pane fade show <?php if ($firstPane === true) {
                                          echo 'active';
                                          $firstPane = false;
                                        } ?>"
          id="pane-<?= esc($roleKey); ?>"
          role="tabpanel"
          aria-labelledby="tab-<?= esc($roleKey); ?>">

          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 35%; padding-left: 20px;">Module / Feature</th>
                  <th class="text-center" style="width: 15%;">View Access</th>
                  <th class="text-center" style="width: 15%;">Add Action</th>
                  <th class="text-center" style="width: 15%;">Edit Action</th>
                  <th class="text-center" style="width: 15%;">Delete Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($modules as $modKey => $modInfo) { ?>
                  <?php
                  $viewVal = 0;
                  $addVal  = 0;
                  $editVal = 0;
                  $delVal  = 0;

                  if (isset($indexed[$roleKey][$modKey])) {
                    $row     = $indexed[$roleKey][$modKey];
                    $viewVal = (int) $row['can_view'];
                    $addVal  = (int) $row['can_add'];
                    $editVal = (int) $row['can_edit'];
                    $delVal  = (int) $row['can_delete'];
                  }
                  ?>
                  <tr>
                    <td style="padding-left: 20px;">
                      <div class="fw-bold"><?= esc($modInfo['name']); ?></div>
                      <small class="text-muted"><?= esc($modInfo['desc']); ?></small>
                    </td>
                    <td class="text-center">
                      <div class="form-check form-switch d-inline-block">
                        <input class="form-check-input" type="checkbox"
                          name="perms[<?= esc($roleKey); ?>][<?= esc($modKey); ?>][view]"
                          value="1"
                          <?php if ($viewVal === 1) {
                            echo 'checked';
                          } ?>>
                      </div>
                    </td>
                    <td class="text-center">
                      <div class="form-check form-switch d-inline-block">
                        <input class="form-check-input" type="checkbox"
                          name="perms[<?= esc($roleKey); ?>][<?= esc($modKey); ?>][add]"
                          value="1"
                          <?php if ($addVal === 1) {
                            echo 'checked';
                          } ?>>
                      </div>
                    </td>
                    <td class="text-center">
                      <div class="form-check form-switch d-inline-block">
                        <input class="form-check-input" type="checkbox"
                          name="perms[<?= esc($roleKey); ?>][<?= esc($modKey); ?>][edit]"
                          value="1"
                          <?php if ($editVal === 1) {
                            echo 'checked';
                          } ?>>
                      </div>
                    </td>
                    <td class="text-center">
                      <div class="form-check form-switch d-inline-block">
                        <input class="form-check-input" type="checkbox"
                          name="perms[<?= esc($roleKey); ?>][<?= esc($modKey); ?>][delete]"
                          value="1"
                          <?php if ($delVal === 1) {
                            echo 'checked';
                          } ?>>
                      </div>
                    </td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          </div>

        </div>
      <?php } ?>
    </div>
  </div>

  <button type="submit" class="btn btn-primary btn-lg px-4 mb-5">
    <i class="bi bi-check-lg"></i> Save Permissions
  </button>
  <a href="<?= site_url('settings'); ?>" class="btn btn-light btn-lg px-4 mb-5">Cancel</a>
</form>