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

if (!isset($view) || $view === '') {
  $view = 'list';
}
?>

<?php if ($view === 'list') { ?>

    <div class="page-head">
      <div>
        <h2>Users</h2>
        <div class="subtitle">Operators that can log in and handle tickets.</div>
      </div>
      <a href="<?= site_url('users/add'); ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Add User
      </a>
    </div>

    <div class="card">
      <div class="card-body p-0">
        <table id="usersTable" class="table align-middle mb-0" data-table-url="<?= site_url('users/data_table'); ?>">
          <thead>
            <tr>
              <th>User ID</th>
              <th>Name</th>
              <th>Email (notifications)</th>
              <th>Role</th>
              <th>Phone</th>
              <th>Active</th>
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
        $isEdit = !empty($user);
        if ($isEdit) {
          $action = site_url('users/update/' . $user['id']);
          $pageTitle = 'Edit User';
        } else {
          $action = site_url('users/save');
          $pageTitle = 'Add User';
        }

        $currentRole = 'user';
        $userUid = '';
        $userName = '';
        $userEmail = '';
        $userPhone = '';
        $isActive = false;
        $editingId = 0;

        if ($isEdit) {
          $editingId = (int) $user['id'];
          if (isset($user['user_id'])) {
            $userUid = $user['user_id'];
          }
          if (isset($user['role'])) {
            $currentRole = $user['role'];
          }
          if (isset($user['name'])) {
            $userName = $user['name'];
          }
          if (isset($user['email'])) {
            $userEmail = $user['email'];
          }
          if (isset($user['phone'])) {
            $userPhone = $user['phone'];
          }
          if (!empty($user['is_active'])) {
            $isActive = true;
          }
        }

        // Role list is the actor's pre-filtered assignable set (passed by the
        // controller in $rolesAll). No fallback — reinjecting hardcoded role
        // keys here would silently re-add super_admin to the dropdown for a
        // non-super-admin actor and defeat the privilege-escalation guard.
        $roles = [];
        if (isset($rolesAll) && is_array($rolesAll)) {
          foreach ($rolesAll as $rRow) {
            $roles[(string) $rRow['role_key']] = (string) $rRow['label'];
          }
        }
        ?>

        <div class="page-head">
          <div>
            <h2><?= esc($pageTitle); ?></h2>
            <div class="subtitle">User ID is used to sign in. Email is only used to receive notifications.</div>
          </div>
          <a href="<?= site_url('users'); ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> Back</a>
        </div>

        <div class="card">
          <div class="card-body">
            <form method="post" action="<?= esc($action); ?>" data-loading-form="1" data-dirty-guard="1">

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">User ID *</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                    <input type="text" name="user_id" id="userIdInput" class="form-control" required minlength="3"
                      maxlength="64" pattern="[A-Za-z0-9._-]{3,64}" autofocus data-ignore-id="<?= (int) $editingId; ?>"
                      data-check-url="<?= site_url('users/check_user_id'); ?>" value="<?= esc($userUid); ?>" placeholder="jdoe">
                    <span class="input-group-text" id="userIdStatus">
                      <i class="bi bi-dash text-muted"></i>
                    </span>
                  </div>
                  <small class="text-muted">3–64 chars. Letters, digits, dot, underscore, hyphen. Used to sign in.</small>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Email (notifications) *</label>
                  <input type="email" name="email" class="form-control" required maxlength="150"
                    value="<?= esc($userEmail); ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Full name *</label>
                  <input type="text" name="name" class="form-control" required maxlength="100" value="<?= esc($userName); ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label">
                    Password
                <?php if ($isEdit) { ?>
                        <small class="text-muted">(leave blank to keep current)</small>
                <?php } else { ?>
                        *
                <?php } ?>
                  </label>
                  <div class="input-group">
                    <input type="password" name="password" id="userPassword" class="form-control" data-caps-warn="1" <?php if (!$isEdit) {
                      echo 'required';
                    } ?>>
                    <button type="button" class="btn btn-outline-secondary" data-toggle-password="userPassword" tabindex="-1"
                      title="Show / hide password" aria-label="Show or hide password">
                      <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                  </div>
                  <small class="text-muted" id="pwHelp">
                    Min <?= (int) app_setting('password_min_length', 8); ?> characters<?php
                         if ((int) app_setting('password_require_letter', 1) === 1) {
                           echo ', at least one letter';
                         }
                         if ((int) app_setting('password_require_digit', 1) === 1) {
                           echo ', at least one digit';
                         }
                         ?>.
                  </small>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Phone</label>
                  <input type="text" name="phone" class="form-control" value="<?= esc($userPhone); ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Role</label>
                  <select name="role" class="form-select">
                <?php foreach ($roles as $key => $label) { ?>
                        <option value="<?= $key; ?>" <?php if ($currentRole === $key) {
                            echo 'selected';
                          } ?>>
                      <?= $label; ?>
                        </option>
                <?php } ?>
                  </select>
                </div>

            <?php if ($isEdit) { ?>
                    <div class="col-md-6">
                      <div class="form-check mt-4 pt-2">
                        <input type="checkbox" class="form-check-input" name="is_active" id="isActive" <?php if ($isActive) {
                          echo 'checked';
                        } ?>>
                        <label class="form-check-label" for="isActive">Active</label>
                      </div>
                    </div>
            <?php } ?>
              </div>

              <div class="mt-3 d-flex justify-content-end gap-2">
                <a href="<?= site_url('users'); ?>" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-check-lg"></i>
                  <span class="btn-label">
                <?php if ($isEdit) { ?>Update<?php } else { ?>Create<?php } ?>
                  </span>
                </button>
              </div>
            </form>
          </div>
        </div>
<?php } ?>