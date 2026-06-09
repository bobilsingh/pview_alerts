<?php

namespace App\Controllers;

use App\Models\user_model;

class User extends BaseController
{
    public $user_model;

    function __construct()
    {
        $this->user_model = new user_model();
    }

    /** GET /maintenance — shown to non-admin users during maintenance mode; super_admin sees a disable button. */
    public function maintenance()
    {
        if (!app_setting_bool('maintenance_mode', false)) {
            return redirect()->to(site_url('login'));
        }
        $session = \Config\Services::session();
        $session->start();
        $role         = (string) $session->get('user_role');
        $isSuperAdmin = ($session->get('user_id') && $role === ROLE_SUPER_ADMIN);
        $isAdminScope = ($session->get('user_id') && role_has_admin_scope($role));

        // Non-super admins work normally; super_admin sees the maintenance page with a disable button.
        if ($isAdminScope && !$isSuperAdmin) {
            return redirect()->to(site_url('dashboard'));
        }

        $data = [
            'title'        => 'Under Maintenance',
            'isSuperAdmin' => $isSuperAdmin,
        ];
        echo view('templates/auth_header', $data);
        echo view('maintenance', $data);
        echo view('templates/auth_footer');
    }

    public function maintenance_disable()
    {
        $session = \Config\Services::session();
        $session->start();
        // Only super_admin can disable maintenance mode from this endpoint.
        if (!$session->get('user_id') || (string) $session->get('user_role') !== ROLE_SUPER_ADMIN) {
            return redirect()->to(site_url('maintenance'));
        }
        $db = \Config\Database::connect();
        $db->table('app_settings')
            ->where('setting_key', 'maintenance_mode')
            ->update(['setting_value' => '0', 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => (string) $session->get('user_id')]);
        app_settings_clear_cache();
        activity_log('settings', 'update', null, null, 'Disabled maintenance mode via maintenance page');
        return redirect()->to(site_url('dashboard'));
    }

    public function login()
    {
        $session = \Config\Services::session();
        $session->start();

        if ($session->get('user_pk') || $session->get('user_id')) {
            // Destroy a non-admin session during maintenance to break the redirect loop.
            if (app_setting_bool('maintenance_mode', false)
                && !role_has_admin_scope((string) $session->get('user_role'))) {
                $session->destroy();
            } else {
                return redirect()->to(site_url('dashboard'));
            }
        }

        $data = [
            'title'    => 'Sign in',
            'error'    => $session->getFlashdata('error'),
            'oldLogin' => $session->getFlashdata('old_login'),
        ];
        if (empty($data['oldLogin'])) {
            $data['oldLogin'] = '';
        }
        echo view('templates/auth_header', $data);
        echo view('login', $data);
        echo view('templates/auth_footer');
    }

    /** POST /login → verify creds, set session, redirect. */
    public function do_login()
    {
        $session = \Config\Services::session();
        $session->start();

        $login = trim((string) $this->request->getPost('login'));
        if ($login === '') {
            $login = trim((string) $this->request->getPost('email'));
        }
        $password = (string) $this->request->getPost('password');

        if ($login === '' || $password === '') {
            log_message('warning', "pview alert >> Login attempt rejected: empty login or password (login=[" . $login . "])");
            $session->setFlashdata('error', 'User ID / email and password are required.');
            $session->setFlashdata('old_login', $login);
            return redirect()->to(site_url('login'));
        }

        // Check lockout BEFORE password verification to avoid leaking credentials via timing.
        $ip = client_ip();
        $lock = login_is_locked($ip, $login);
        if ($lock['locked']) {
            $mins = (int) ceil($lock['remaining_seconds'] / 60);
            log_message('warning', "pview alert >> Login locked out: login=[" . $login . "], ip=[" . $ip . "], attempts=[" . $lock['attempts'] . "], remaining_min=[" . $mins . "]");
            $session->setFlashdata('error', 'Too many failed attempts. Try again in ' . $mins . ' minute(s).');
            $session->setFlashdata('old_login', $login);
            return redirect()->to(site_url('login'));
        }

        $user = $this->user_model->checkLogin($login, $password);
        if (empty($user)) {
            login_attempt_record($ip, $login, false);
            activity_log(
                'auth',
                'login_failed',
                'user',
                null,
                'Failed login for "' . $login . '"',
                ['login_tried' => $login],
                ['user_id' => '', 'user_name' => '', 'user_role' => '', 'status' => 'fail']
            );
            $session->setFlashdata('error', 'Invalid credentials.');
            $session->setFlashdata('old_login', $login);
            return redirect()->to(site_url('login'));
        }

        login_attempt_record($ip, $login, true);
        login_attempts_clear($ip, $login);

        $this->user_model->setSession($user);
        log_message('debug', "pview alert >> Login complete: login=[" . $login . "], user_pk=[" . $user['id'] . "], user_id=[" . (isset($user['user_id']) ? $user['user_id'] : '') . "], role=[" . $user['role'] . "]");
        activity_log(
            'auth',
            'login',
            'user',
            (string) $user['id'],
            'Login: ' . (isset($user['name']) ? $user['name'] : $login)
        );

        // Redirect to the originally requested page, or fall back to first accessible module.
        $redirectUrl = $session->get('redirect_after_login');
        $session->remove('redirect_after_login');
        if (!empty($redirectUrl) && strpos($redirectUrl, site_url()) === 0) {
            return redirect()->to($redirectUrl);
        }
        return redirect()->to(first_accessible_module_url());
    }

    /** GET /logout */
    public function logout()
    {
        activity_log(
            'auth',
            'logout',
            'user',
            (string) logged_user_id(),
            'Logout'
        );
        $this->user_model->logout();
        return redirect()->to(site_url('login'));
    }

    /** GET /password/change — forced rotation or voluntary change. */
    public function password_change()
    {
        $session = \Config\Services::session();
        $session->start();
        if (!$session->get('user_id')) {
            return redirect()->to(site_url('login'));
        }
        $data = [
            'title' => 'Change password',
            'forced' => (bool) $session->get('password_must_rotate'),
            'error'  => $session->getFlashdata('error'),
        ];
        echo view('templates/auth_header', $data);
        echo view('password_change', $data);
        echo view('templates/auth_footer');
    }

    /** POST /password/change */
    public function password_change_save()
    {
        $session = \Config\Services::session();
        $session->start();
        if (!$session->get('user_id')) {
            return redirect()->to(site_url('login'));
        }
        $current = (string) $this->request->getPost('current_password');
        $new     = (string) $this->request->getPost('new_password');
        $confirm = (string) $this->request->getPost('confirm_password');

        if ($current === '' || $new === '' || $confirm === '') {
            $session->setFlashdata('error', 'All three fields are required.');
            return redirect()->to(site_url('password/change'));
        }
        if ($new !== $confirm) {
            $session->setFlashdata('error', 'New password and confirmation do not match.');
            return redirect()->to(site_url('password/change'));
        }
        if ($new === $current) {
            $session->setFlashdata('error', 'New password must be different from the current password.');
            return redirect()->to(site_url('password/change'));
        }
        $passErr = validate_password($new);
        if ($passErr !== '') {
            $session->setFlashdata('error', $passErr);
            return redirect()->to(site_url('password/change'));
        }

        $userPk = (int) $session->get('user_pk');
        $row = $this->user_model->getById($userPk);
        if (empty($row) || !password_verify($current, (string) $row['password'])) {
            log_message('warning', 'pview alert >> password_change rejected: bad current password, user_pk=[' . $userPk . ']');
            $session->setFlashdata('error', 'Current password is incorrect.');
            return redirect()->to(site_url('password/change'));
        }

        $this->user_model->update($userPk, ['password' => $new]);
        $session->remove('password_must_rotate');
        $session->setFlashdata('success', 'Password updated.');
        log_message('debug', 'pview alert >> password_change complete: user_pk=[' . $userPk . ']');
        activity_log(
            'auth',
            'password_change',
            'user',
            (string) $userPk,
            'Password changed'
        );

        return redirect()->to(first_accessible_module_url());
    }

    /** GET /users — list page. */
    public function index()
    {
        check_module_access('users', 'view');
        log_message('debug', "pview alert >> users index page open");
        activity_log('users', 'view', null, null, 'Opened Users page');

        $data = [
            'title'  => 'Users',
            'users'  => $this->user_model->getAll(),
            'view'   => 'list',
            'user'   => null,
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('users', $data);
        echo view('templates/footer');
    }

    /** GET /users/add  → show empty form. */
    public function add()
    {
        check_module_access('users', 'add');
        $allowed = assignable_role_keys();
        $rolesFiltered = [];
        foreach ($this->user_model->getAllRoles() as $r) {
            if (in_array((string) $r['role_key'], $allowed, true)) {
                $rolesFiltered[] = $r;
            }
        }
        $data = [
            'title'    => 'Add User',
            'view'     => 'form',
            'user'     => null,
            'rolesAll' => $rolesFiltered,
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('users', $data);
        echo view('templates/footer');
    }

    /** POST /users/save — create a new user. */
    public function save()
    {
        check_module_access('users', 'add');

        $user_id = trim((string) $this->request->getPost('user_id'));
        $email   = trim((string) $this->request->getPost('email'));
        $name    = trim((string) $this->request->getPost('name'));
        $pass    = (string) $this->request->getPost('password');

        if ($user_id === '' || $email === '' || $name === '' || $pass === '') {
            log_message('warning', "pview alert >> users save rejected: missing required field (user_id=[" . $user_id . "], email=[" . $email . "])");
            $this->session->setFlashdata('error', 'User ID, name, email and password are required.');
            return redirect()->to(site_url('users/add'));
        }

        $uidErr = validate_user_id($user_id);
        if ($uidErr !== '') {
            $this->session->setFlashdata('error', $uidErr);
            return redirect()->to(site_url('users/add'));
        }

        if ($this->user_model->userIdExists($user_id)) {
            $this->session->setFlashdata('error', 'A user with that User ID already exists.');
            return redirect()->to(site_url('users/add'));
        }
        if ($this->user_model->emailExists($email)) {
            $this->session->setFlashdata('error', 'A user with that email already exists.');
            return redirect()->to(site_url('users/add'));
        }

        $passErr = validate_password($pass);
        if ($passErr !== '') {
            $this->session->setFlashdata('error', $passErr);
            return redirect()->to(site_url('users/add'));
        }

        $role = $this->request->getPost('role');
        $role = or_default($role, 'user');
        if (!$this->user_model->roleKeyExists($role)) {
            $this->session->setFlashdata('error', 'Invalid role selected.');
            return redirect()->to(site_url('users/add'));
        }
        // Server-side escalation guard — the dropdown hides these options but direct POSTs can bypass it.
        if (!in_array($role, assignable_role_keys(), true)) {
            log_message('warning', 'pview alert >> users save BLOCKED: actor=[' . logged_user_id() . '] role=[' . logged_user_role() . '] tried to assign role=[' . $role . ']');
            $this->session->setFlashdata('error', 'You do not have permission to assign that role.');
            return redirect()->to(site_url('users/add'));
        }

        $newId = $this->user_model->save([
            'user_id'   => $user_id,
            'name'      => $name,
            'email'     => $email,
            'password'  => $pass,
            'role'      => $role,
            'phone'     => (string) $this->request->getPost('phone'),
            'is_active' => 1,
        ]);
        activity_log(
            'users',
            'create',
            'user',
            (string) $newId,
            'Created user "' . $name . '" (' . $user_id . ', ' . $role . ')',
            ['user_id' => $user_id, 'name' => $name, 'email' => $email, 'role' => $role]
        );
        $this->session->setFlashdata('success', 'User "' . $name . '" created.');
        return redirect()->to(site_url('users'));
    }

    /** GET /users/edit/(:num)  → show form pre-filled. */
    public function edit($id)
    {
        check_module_access('users', 'edit');
        $user = $this->user_model->getById($id);
        if (empty($user)) {
            return redirect()->to(site_url('users'));
        }

        // Refuse to open the edit form if the target's role is outside the actor's assignable list.
        $allowed = assignable_role_keys();
        if (!in_array((string) $user['role'], $allowed, true)) {
            log_message('warning', 'pview alert >> users edit BLOCKED: actor=[' . logged_user_id() . '] role=[' . logged_user_role() . '] tried to edit user_pk=[' . $id . '] with role=[' . $user['role'] . ']');
            $this->session->setFlashdata('error', 'You do not have permission to edit a user with this role.');
            return redirect()->to(site_url('users'));
        }

        $rolesFiltered = [];
        foreach ($this->user_model->getAllRoles() as $r) {
            if (in_array((string) $r['role_key'], $allowed, true)) {
                $rolesFiltered[] = $r;
            }
        }
        $data = [
            'title'    => 'Edit User',
            'view'     => 'form',
            'user'     => $user,
            'rolesAll' => $rolesFiltered,
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('users', $data);
        echo view('templates/footer');
    }

    /** POST /users/update/(:num) — save edits. */
    public function update($id)
    {
        check_module_access('users', 'edit');

        $id      = (int) $id;
        $user_id = trim((string) $this->request->getPost('user_id'));
        $name    = trim((string) $this->request->getPost('name'));
        $email   = trim((string) $this->request->getPost('email'));
        $pass    = (string) $this->request->getPost('password');

        // Self-edit guard: super_admin cannot demote or deactivate their own account.
        $sessionPk = (int) $this->session->get('user_pk');
        $isSelfEdit = ($sessionPk > 0 && $sessionPk === $id);
        $currentRow = $this->user_model->getById($id);

        if ($user_id === '' || $name === '' || $email === '') {
            $this->session->setFlashdata('error', 'User ID, name and email are required.');
            return redirect()->to(site_url('users/edit/' . $id));
        }

        $uidErr = validate_user_id($user_id);
        if ($uidErr !== '') {
            $this->session->setFlashdata('error', $uidErr);
            return redirect()->to(site_url('users/edit/' . $id));
        }

        if ($this->user_model->userIdExists($user_id, $id)) {
            $this->session->setFlashdata('error', 'That User ID is taken.');
            return redirect()->to(site_url('users/edit/' . $id));
        }
        if ($this->user_model->emailExists($email, $id)) {
            $this->session->setFlashdata('error', 'That email is taken.');
            return redirect()->to(site_url('users/edit/' . $id));
        }

        if ($pass !== '') {
            $passErr = validate_password($pass);
            if ($passErr !== '') {
                $this->session->setFlashdata('error', $passErr);
                return redirect()->to(site_url('users/edit/' . $id));
            }
        }

        $role = $this->request->getPost('role');
        $role = or_default($role, 'user');
        if (!$this->user_model->roleKeyExists($role)) {
            $this->session->setFlashdata('error', 'Invalid role selected.');
            return redirect()->to(site_url('users/edit/' . $id));
        }

        // Both current role and new role must be in the actor's assignable list (prevents all escalation paths).
        $allowedRoles = assignable_role_keys();
        $currentRoleKey = '';
        if (!empty($currentRow) && isset($currentRow['role'])) {
            $currentRoleKey = (string) $currentRow['role'];
        }
        if ($currentRoleKey !== '' && !in_array($currentRoleKey, $allowedRoles, true)) {
            log_message('warning', 'pview alert >> users update BLOCKED: actor=[' . logged_user_id() . '] role=[' . logged_user_role() . '] tried to edit user_pk=[' . $id . '] with role=[' . $currentRoleKey . ']');
            $this->session->setFlashdata('error', 'You do not have permission to edit a user with this role.');
            return redirect()->to(site_url('users'));
        }
        if (!in_array($role, $allowedRoles, true)) {
            log_message('warning', 'pview alert >> users update BLOCKED: actor=[' . logged_user_id() . '] role=[' . logged_user_role() . '] tried to assign role=[' . $role . '] to user_pk=[' . $id . ']');
            $this->session->setFlashdata('error', 'You do not have permission to assign that role.');
            return redirect()->to(site_url('users/edit/' . $id));
        }

        $isActive = bool_int($this->request->getPost('is_active'));

        if ($isSelfEdit && !empty($currentRow) && $currentRow['role'] === ROLE_SUPER_ADMIN) {
            if ($role !== ROLE_SUPER_ADMIN) {
                $this->session->setFlashdata('error', 'You cannot demote your own super-admin role.');
                return redirect()->to(site_url('users/edit/' . $id));
            }
            if ($isActive !== 1) {
                $this->session->setFlashdata('error', 'You cannot deactivate your own account.');
                return redirect()->to(site_url('users/edit/' . $id));
            }
        }

        // Refuse if this would demote the last active super_admin.
        if (!empty($currentRow) && $currentRow['role'] === ROLE_SUPER_ADMIN && $role !== ROLE_SUPER_ADMIN) {
            $remaining = $this->user_model->countActiveSuperAdmins($id);
            if ($remaining < 1) {
                $this->session->setFlashdata('error', 'Cannot demote — this is the last active super-admin.');
                return redirect()->to(site_url('users/edit/' . $id));
            }
        }

        $after = [
            'user_id'   => $user_id,
            'name'      => $name,
            'email'     => $email,
            'password'  => $pass,
            'role'      => $role,
            'phone'     => (string) $this->request->getPost('phone'),
            'is_active' => $isActive,
        ];
        $this->user_model->update($id, $after);
        log_message('debug', "pview alert >> user update: id=[" . $id . "], user_id=[" . $user_id . "], by=[" . logged_user_id() . "]");
        $diff = activity_diff($currentRow, $after, ['user_id', 'name', 'email', 'role', 'phone', 'is_active']);
        if ($pass !== '') {
            $diff['password'] = ['(hidden)', '(reset)'];
        }
        activity_log(
            'users',
            'update',
            'user',
            (string) $id,
            'Updated user "' . $name . '"',
            $diff
        );
        $this->session->setFlashdata('success', 'User "' . $name . '" updated.');
        return redirect()->to(site_url('users'));
    }

    /** GET /users/delete/(:num) — soft-delete a user. */
    public function delete($id)
    {
        check_module_access('users', 'delete');
        $sessionPk = (int) $this->session->get('user_pk');
        if ($sessionPk > 0 && $sessionPk === (int) $id) {
            $this->session->setFlashdata('error', 'You cannot delete your own account.');
            return redirect()->to(site_url('users'));
        }

        $row = $this->user_model->getById($id);
        if (!empty($row) && isset($row['role'])) {
            $targetRole = (string) $row['role'];
            if (!in_array($targetRole, assignable_role_keys(), true)) {
                log_message('warning', 'pview alert >> users delete BLOCKED: actor=[' . logged_user_id() . '] role=[' . logged_user_role() . '] tried to delete user_pk=[' . $id . '] with role=[' . $targetRole . ']');
                $this->session->setFlashdata('error', 'You do not have permission to delete a user with this role.');
                return redirect()->to(site_url('users'));
            }
        }

        // Refuse if this would remove the last active super_admin.
        if (!empty($row) && $row['role'] === ROLE_SUPER_ADMIN) {
            $remaining = $this->user_model->countActiveSuperAdmins((int) $id);
            if ($remaining < 1) {
                $this->session->setFlashdata('error', 'Cannot delete — this is the last active super-admin.');
                return redirect()->to(site_url('users'));
            }
        }

        log_message('debug', "pview alert >> users delete request: id=[" . $id . "]");
        $this->user_model->softDelete($id);
        $deletedName = isset($row['name']) ? (string) $row['name'] : '';
        activity_log(
            'users',
            'delete',
            'user',
            (string) $id,
            'Removed user "' . $deletedName . '"',
            ['user_id' => isset($row['user_id']) ? $row['user_id'] : '', 'name' => $deletedName]
        );
        $this->session->setFlashdata('success', 'User removed.');
        return redirect()->to(site_url('users'));
    }

    /** GET /users/check_user_id — live user_id availability check. */
    public function check_user_id()
    {
        check_module_access('users', 'view');
        $candidate = trim((string) $this->request->getGet('user_id'));
        $ignore    = (int) $this->request->getGet('ignore');
        $err       = validate_user_id($candidate);
        if ($err !== '') {
            return json_fail($err);
        }
        if ($this->user_model->userIdExists($candidate, $ignore)) {
            return json_fail('Taken');
        }
        return json_ok([], 'Available');
    }

    /** GET /users/data_table */
    public function data_table()
    {
        check_module_access('users', 'view');
        $colMap = [
            0 => 'user_id',
            1 => 'name',
            2 => 'email',
            3 => 'role',
            4 => 'phone',
            5 => 'is_active',
            6 => 'created_at',
            7 => 'name',           // Actions — fallback
        ];
        $params = dt_parse_request($this->request, $colMap);
        $result = $this->user_model->usersForDT($params);

        $assignable = assignable_role_keys();
        $canEdit    = has_module_access('users', 'edit')   === true;
        $canDelete  = has_module_access('users', 'delete') === true;

        $data = [];
        foreach ($result['rows'] as $u) {
            $activeBadge = '<span class="badge bg-dark">NO</span>';
            if (!empty($u['is_active'])) {
                $activeBadge = '<span class="badge bg-success">YES</span>';
            }
            $phone = isset($u['phone']) ? (string) $u['phone'] : '';
            $uid   = isset($u['user_id']) ? (string) $u['user_id'] : '';
            $role  = isset($u['role']) ? str_replace('_', ' ', strtoupper((string) $u['role'])) : '';

            $rowRoleKey = isset($u['role']) ? (string) $u['role'] : '';
            $allowedRow = ($rowRoleKey !== '' && in_array($rowRoleKey, $assignable, true));

            $actionsHtml = '';
            if ($canEdit && $allowedRow) {
                $actionsHtml .= '<a class="btn btn-sm btn-light" href="' . site_url('users/edit/' . $u['id']) . '" title="Edit"><i class="bi bi-pencil"></i></a> ';
            }
            if ($canDelete && $allowedRow) {
                $actionsHtml .= '<a class="btn btn-sm btn-outline-danger" href="' . site_url('users/delete/' . $u['id']) . '" data-method="post" data-confirm-message="Remove this user?" title="Remove"><i class="bi bi-trash"></i></a>';
            }
            if ($actionsHtml === '') {
                $actionsHtml = '<span class="text-muted small">—</span>';
            }

            $data[] = [
                'user_id'    => '<code class="user-id-chip">' . esc($uid !== '' ? $uid : '-') . '</code>',
                'name'       => '<strong>' . esc($u['name']) . '</strong>',
                'email'      => '<span class="text-muted">' . esc($u['email']) . '</span>',
                'role'       => '<span class="badge bg-info text-dark">' . esc($role) . '</span>',
                'phone'      => '<span class="text-muted">' . esc($phone !== '' ? $phone : '-') . '</span>',
                'is_active'  => $activeBadge,
                'created_at' => '<span class="text-muted small">' . esc($u['created_at']) . '</span>',
                'actions'    => $actionsHtml,
            ];
        }

        return dt_json_response($params['draw'], $result['total'], $result['filtered'], $data);
    }

    /** POST /users/update_theme — background AJAX call to update user theme pref. */
    public function update_theme()
    {
        check_isvalidated();
        $session = \Config\Services::session();
        $session->start();

        $theme = $this->request->getPost('theme');
        if (!in_array($theme, ['dark', 'light'], true)) {
            return json_fail('Invalid theme preference');
        }

        $session->set('theme', $theme);
        $userPk = (int) $session->get('user_pk');
        if ($userPk > 0) {
            $this->user_model->update($userPk, ['theme' => $theme]);
            log_message('debug', "pview alert >> user theme updated to=[" . $theme . "] for user_pk=[" . $userPk . "]");
            activity_log(
                'me',
                'theme_change',
                'user',
                (string) $userPk,
                'Theme changed to ' . $theme,
                ['theme' => $theme]
            );
        }

        return json_ok([], 'Theme preference updated');
    }

    /** GET /users/active_json — lightweight user list for @mention autocomplete. */
    public function active_json()
    {
        check_isvalidated();
        if (has_module_access('users', 'view')) {
            $rows = $this->user_model->getActive();
        } else {
            $rows = $this->user_model->getPoolUsers(logged_user_id());
        }
        $out = [];
        foreach ($rows as $u) {
            $out[] = [
                'user_id' => (string) $u['user_id'],
                'name'    => (string) $u['name'],
            ];
        }
        return json_ok($out);
    }

    /** GET /me/dashboard — personal dashboard preferences page. */
    public function me_dashboard()
    {
        check_isvalidated();

        $appModel = new \App\Models\app_model();
        $data = [
            'title'    => 'Dashboard Preferences',
            'projects' => $appModel->projectGetActive(),
            'layout'   => (array) $this->session->get('dashboard_layout'),
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('me/dashboard', $data);
        echo view('templates/footer');
    }

    /** POST /me/dashboard — save dashboard preferences for the current user. */
    public function me_dashboard_save()
    {
        check_isvalidated();

        $userPk = (int) $this->session->get('user_pk');
        if ($userPk <= 0) {
            return redirect()->to(site_url('login'));
        }

        $defaultProjectId = (int) $this->request->getPost('default_project_id');
        if ($defaultProjectId < 0) {
            $defaultProjectId = 0;
        }

        $kpiKeys = ['open', 'critical', 'major', 'resolved'];
        $kpiVisible = [];
        foreach ($kpiKeys as $k) {
            if ($this->request->getPost('kpi_' . $k) !== null) {
                $kpiVisible[$k] = 1;
            } else {
                $kpiVisible[$k] = 0;
            }
        }
        // Ensure at least one KPI card is visible to prevent a broken layout.
        if ($kpiVisible['open'] + $kpiVisible['critical'] + $kpiVisible['major'] + $kpiVisible['resolved'] === 0) {
            $kpiVisible['open'] = 1;
        }

        $defaultTrendRange = (int) $this->request->getPost('default_trend_range');
        $allowedRanges = app_setting_csv('dashboard_trend_ranges', ['7', '15', '30']);
        $allowedInt = [];
        foreach ($allowedRanges as $r) {
            $n = (int) $r;
            if ($n >= 1 && $n <= 365) {
                $allowedInt[] = $n;
            }
        }
        if (!in_array($defaultTrendRange, $allowedInt, true)) {
            $defaultTrendRange = 0;
        }

        $layout = [
            'default_project_id'  => $defaultProjectId,
            'kpi_visible'         => $kpiVisible,
            'default_trend_range' => $defaultTrendRange,
        ];

        $this->user_model->update($userPk, ['dashboard_layout' => json_encode($layout)]);

        $this->session->set('dashboard_layout', $layout);

        log_message('debug', "pview alert >> me_dashboard saved: user_pk=[" . $userPk . "], layout=[" . json_encode($layout) . "]");
        activity_log(
            'me',
            'prefs_save',
            'user',
            (string) $userPk,
            'Saved dashboard preferences',
            $layout
        );
        $this->session->setFlashdata('success', 'Dashboard preferences updated.');
        return redirect()->to(site_url('dashboard'));
    }

    /** GET /me/notifications — per-user severity-by-project opt-out matrix. */
    public function me_notifications()
    {
        check_isvalidated();

        $appModel = new \App\Models\app_model();
        $userId   = (string) $this->session->get('user_id');

        $existing = [];
        $rows = $this->db->table('user_notification_settings')
            ->where('user_id', $userId)
            ->get()->getResultArray();
        foreach ($rows as $r) {
            $key = (int) $r['project_id'] . '|' . (string) $r['severity'];
            $existing[$key] = (int) $r['is_enabled'];
        }

        $data = [
            'title'    => 'Notification Preferences',
            'projects' => $appModel->projectGetActive(),
            'existing' => $existing,
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('me/notifications', $data);
        echo view('templates/footer');
    }

    /** POST /me/notifications — upsert the per-user notification matrix. */
    public function me_notifications_save()
    {
        check_isvalidated();

        $userId = (string) $this->session->get('user_id');
        if ($userId === '') {
            return redirect()->to(site_url('login'));
        }

        $appModel = new \App\Models\app_model();
        $projects = $appModel->projectGetActive();

        // project_id = 0 is the "all projects" catch-all row.
        $projectIds = [0];
        foreach ($projects as $p) {
            $projectIds[] = (int) $p['id'];
        }
        $severities = ['info', 'major', 'critical'];

        $now = date('Y-m-d H:i:s');
        foreach ($projectIds as $pid) {
            foreach ($severities as $sev) {
                $fieldName = 'pref_' . $pid . '_' . $sev;
                $enabled = 0;
                if ($this->request->getPost($fieldName) !== null) {
                    $enabled = 1;
                }

                $existing = $this->db->table('user_notification_settings')
                    ->where('user_id', $userId)
                    ->where('project_id', $pid)
                    ->where('severity', $sev)
                    ->get()->getRowArray();

                if (empty($existing)) {
                    $this->db->table('user_notification_settings')->insert([
                        'user_id'    => $userId,
                        'project_id' => $pid,
                        'severity'   => $sev,
                        'is_enabled' => $enabled,
                        'updated_at' => $now,
                    ]);
                } else {
                    $this->db->table('user_notification_settings')
                        ->where('id', (int) $existing['id'])
                        ->update([
                            'is_enabled' => $enabled,
                            'updated_at' => $now,
                        ]);
                }
            }
        }

        log_message('debug', "pview alert >> me_notifications saved: user_id=[" . $userId . "]");
        activity_log(
            'me',
            'prefs_save',
            'user',
            $userId,
            'Saved notification preferences'
        );
        $this->session->setFlashdata('success', 'Notification preferences updated.');
        return redirect()->to(site_url('me/notifications'));
    }

    /** GET /roles — role list. */
    public function roles()
    {
        check_module_access('roles', 'view');
        activity_log('roles', 'view', null, null, 'Opened Roles page');
        $rows = $this->user_model->getAllRoles();
        foreach ($rows as &$r) {
            $r['user_count'] = $this->user_model->countUsersWithRole($r['role_key']);
        }
        unset($r);
        $data = [
            'title' => 'Roles',
            'view'  => 'list',
            'roles' => $rows,
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('roles', $data);
        echo view('templates/footer');
    }

    /** GET /roles/add — empty form. */
    public function role_add()
    {
        check_module_access('roles', 'add');
        $data = ['title' => 'Add Role', 'view' => 'form', 'role' => null];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('roles', $data);
        echo view('templates/footer');
    }

    /** POST /roles/save — create a new (non-builtin) role. */
    public function role_save()
    {
        check_module_access('roles', 'add');
        $roleKey = trim((string) $this->request->getPost('role_key'));
        $label   = trim((string) $this->request->getPost('label'));

        if ($roleKey === '' || $label === '') {
            $this->session->setFlashdata('error', 'Role key and label are required.');
            return redirect()->to(site_url('roles/add'));
        }
        if (!preg_match('/^[a-z][a-z0-9_]{1,49}$/', $roleKey)) {
            $this->session->setFlashdata('error', 'Role key must start with a letter and contain only lowercase letters, digits, or underscore (2-50 chars).');
            return redirect()->to(site_url('roles/add'));
        }

        if ($this->user_model->roleKeyExists($roleKey)) {
            $this->session->setFlashdata('error', 'A role with that key already exists.');
            return redirect()->to(site_url('roles/add'));
        }

        $isAdminScope = 0;
        if ($this->request->getPost('is_admin_scope') !== null) {
            $isAdminScope = 1;
        }

        $this->user_model->saveRole([
            'role_key'       => $roleKey,
            'label'          => $label,
            'is_builtin'     => 0,
            'is_admin_scope' => $isAdminScope,
            'sort_order'     => 100,
        ]);
        $this->user_model->seedDefaultPermissions($roleKey);

        activity_log(
            'roles',
            'create',
            'role',
            $roleKey,
            'Created role "' . $label . '" (' . $roleKey . ')',
            ['role_key' => $roleKey, 'label' => $label, 'is_admin_scope' => $isAdminScope]
        );

        $this->session->setFlashdata('success', 'Role "' . $label . '" created.');
        return redirect()->to(site_url('roles'));
    }

    /** GET /roles/edit/{key} — rename label only; role_key is immutable. */
    public function role_edit($role_key)
    {
        check_module_access('roles', 'edit');
        $role = $this->user_model->getRoleByKey($role_key);
        if (empty($role)) {
            return redirect()->to(site_url('roles'));
        }
        $data = ['title' => 'Edit Role', 'view' => 'form', 'role' => $role];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('roles', $data);
        echo view('templates/footer');
    }

    /** POST /roles/update/{key} — save label change. */
    public function role_update($role_key)
    {
        check_module_access('roles', 'edit');
        $label = trim((string) $this->request->getPost('label'));
        if ($label === '') {
            $this->session->setFlashdata('error', 'Label is required.');
            return redirect()->to(site_url('roles/edit/' . $role_key));
        }
        $isAdminScope = 0;
        if ($this->request->getPost('is_admin_scope') !== null) {
            $isAdminScope = 1;
        }
        // super_admin is always admin-scope regardless of form value.
        if ($role_key === ROLE_SUPER_ADMIN) {
            $isAdminScope = 1;
        }

        $this->user_model->updateRoleLabel($role_key, $label);
        $this->user_model->updateRoleAdminScope($role_key, $isAdminScope);
        activity_log(
            'roles',
            'update',
            'role',
            (string) $role_key,
            'Updated role "' . $role_key . '" → "' . $label . '"',
            ['role_key' => $role_key, 'label' => $label, 'is_admin_scope' => $isAdminScope]
        );
        $this->session->setFlashdata('success', 'Role updated.');
        return redirect()->to(site_url('roles'));
    }

    /** POST /roles/delete/{key} — refuses for builtins or roles with users. */
    public function role_delete($role_key)
    {
        check_module_access('roles', 'delete');
        $role = $this->user_model->getRoleByKey($role_key);
        if (empty($role)) {
            $this->session->setFlashdata('error', 'Role not found.');
            return redirect()->to(site_url('roles'));
        }
        if ((int) $role['is_builtin'] === 1) {
            $this->session->setFlashdata('error', 'Built-in roles cannot be deleted.');
            return redirect()->to(site_url('roles'));
        }
        $userCount = $this->user_model->countUsersWithRole($role_key);
        if ($userCount > 0) {
            $this->session->setFlashdata('error', 'Cannot delete — ' . $userCount . ' user(s) still assigned to this role. Reassign them first.');
            return redirect()->to(site_url('roles'));
        }
        $this->user_model->deleteRole($role_key);
        activity_log(
            'roles',
            'delete',
            'role',
            (string) $role_key,
            'Removed role "' . $role['label'] . '" (' . $role_key . ')',
            ['role_key' => $role_key, 'label' => $role['label']]
        );
        $this->session->setFlashdata('success', 'Role "' . $role['label'] . '" removed.');
        return redirect()->to(site_url('roles'));
    }
}
