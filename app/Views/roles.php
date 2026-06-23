<?php
if (!isset($view) || $view === '') {
  $view = 'list';
}
?>

<?php if ($view === 'list') { ?>
    <div class="page-head">
      <div>
        <h2>Roles</h2>
        <div class="subtitle">Define operator and administrator roles.<br> Each role gets its own tab in the Module
          Permissions panel and an admin-tier flag controlling system-wide ticket scope.</div>
      </div>
      <a href="<?= site_url('roles/add'); ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Add Role
      </a>
    </div>

    <div class="card">
      <div class="card-body p-0">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Role Key</th>
              <th>Label</th>
              <th class="text-center">Built-in</th>
              <th class="text-center">Active Users</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($roles as $r) { ?>
                <tr>
                  <td><code class="user-id-chip"><?= esc($r['role_key']); ?></code></td>
                  <td><strong><?= esc($r['label']); ?></strong></td>
                  <td class="text-center">
                    <?php if ((int) $r['is_builtin'] === 1) { ?>
                        <span class="badge bg-info text-dark">BUILT-IN</span>
                    <?php } else { ?>
                        <span class="badge bg-secondary">CUSTOM</span>
                    <?php } ?>
                  </td>
                  <td class="text-center">
                    <span class="badge bg-light text-dark"><?= (int) $r['user_count']; ?></span>
                  </td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-light" href="<?= site_url('roles/edit/' . esc($r['role_key'])); ?>"
                      title="Rename label">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <?php if ((int) $r['is_builtin'] !== 1) { ?>
                        <a class="btn btn-sm btn-outline-danger" href="<?= site_url('roles/delete/' . esc($r['role_key'])); ?>"
                          data-method="post"
                          data-confirm-message="Remove this role? Module permissions for it will also be cleared.">
                          <i class="bi bi-trash"></i>
                        </a>
                    <?php } ?>
                  </td>
                </tr>
            <?php } ?>
            <?php if (empty($roles)) { ?>
                <tr>
                  <td colspan="5" class="text-center text-muted py-4">No roles yet.</td>
                </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

<?php } else if ($view === 'form') { ?>
        <?php
        $isEdit = !empty($role);
        if ($isEdit) {
          $action = site_url('roles/update/' . esc($role['role_key']));
          $pageTitle = 'Edit Role';
        } else {
          $action = site_url('roles/save');
          $pageTitle = 'Add Role';
        }

        $roleKey = '';
        $label = '';
        $isBuiltin = false;
        $isAdminScope = false;
        if ($isEdit) {
          $roleKey = $role['role_key'];
          $label = $role['label'];
          if ((int) $role['is_builtin'] === 1) {
            $isBuiltin = true;
          }
          if (isset($role['is_admin_scope']) && (int) $role['is_admin_scope'] === 1) {
            $isAdminScope = true;
          }
        }
        ?>

        <div class="page-head">
          <div>
            <h2><?= esc($pageTitle); ?></h2>
            <div class="subtitle">Role key is immutable once created — only the human-readable label can change later.</div>
          </div>
          <a href="<?= site_url('roles'); ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> Back</a>
        </div>

        <div class="card">
          <div class="card-body">
            <form method="post" action="<?= esc($action); ?>" data-loading-form="1">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Role Key *</label>
                  <input type="text" name="role_key" class="form-control" required minlength="2" maxlength="50"
                    pattern="[a-z][a-z0-9_]{1,49}" <?php if ($isEdit) {
                      echo 'readonly';
                    } ?> value="<?= esc($roleKey); ?>"
                    placeholder="vendor_lead">
                  <small class="text-muted">Lowercase letters, digits, underscore. Used internally and in URLs.</small>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Label *</label>
                  <input type="text" name="label" class="form-control" required maxlength="100" value="<?= esc($label); ?>"
                    placeholder="Vendor Lead">
                  <small class="text-muted">Shown in dropdowns and the Module Control Panel tabs.</small>
                </div>
              </div>

              <div class="row g-3 mt-1">
                <div class="col-12">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="role_is_admin_scope" name="is_admin_scope"
                      value="1" <?php if ($isAdminScope) {
                        echo 'checked';
                      } ?>>
                    <label class="form-check-label" for="role_is_admin_scope">
                      <strong>Admin-tier scope</strong>
                    </label>
                    <div class="text-muted small">
                      Roles with this enabled see all tickets system-wide (not just their own or assigned).
                      Leave off for operator-style roles that should only see their own work.
                    </div>
                  </div>
                </div>
              </div>

          <?php if ($isBuiltin) { ?>
                  <div class="alert alert-info mt-3 mb-0">
                    <i class="bi bi-info-circle"></i>
                    This is a built-in role. You can rename its label, but the role key and its existence are protected.
                  </div>
          <?php } ?>

              <div class="mt-3 d-flex justify-content-end gap-2">
                <a href="<?= site_url('roles'); ?>" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-check-lg"></i>
              <?php if ($isEdit) { ?>Update<?php } else { ?>Create<?php } ?>
                </button>
              </div>
            </form>
          </div>
        </div>
<?php } ?>