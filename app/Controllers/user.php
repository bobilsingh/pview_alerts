<?php

namespace App\Controllers;

use App\Models\user_model;

// Login accepts a user_id or email (auto-detected).
class User extends BaseController
{
    public $user_model;

    function __construct()
    {
        $this->user_model = new user_model();
    }

    /** GET /login  → show form (or redirect if already logged in). */
    public function login()
    {
        $session = \Config\Services::session();
        $session->start();

        if ($session->get('user_pk') || $session->get('user_id')) {
            return redirect()->to(site_url('dashboard'));
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

        // Accept legacy `email` field in addition to `login`.
        $login = trim((string) $this->request->getPost('login'));
        if ($login === '') {
            $login = trim((string) $this->request->getPost('email'));
        }
        $password = (string) $this->request->getPost('password');

        if ($login === '' || $password === '') {
            error_log("pview alert >> Login attempt rejected: empty login or password (login=[" . $login . "])");
            $session->setFlashdata('error', 'User ID / email and password are required.');
            $session->setFlashdata('old_login', $login);
            return redirect()->to(site_url('login'));
        }

        // Rate-limit check. We check BEFORE password verification so a
        // locked-out user gets the same error whether their guess was
        // right or wrong — no oracle for attackers.
        $ip = client_ip();
        $lock = login_is_locked($ip, $login);
        if ($lock['locked']) {
            $mins = (int) ceil($lock['remaining_seconds'] / 60);
            error_log("pview alert >> Login locked out: login=[" . $login . "], ip=[" . $ip . "], attempts=[" . $lock['attempts'] . "], remaining_min=[" . $mins . "]");
            $session->setFlashdata('error', 'Too many failed attempts. Try again in ' . $mins . ' minute(s).');
            $session->setFlashdata('old_login', $login);
            return redirect()->to(site_url('login'));
        }

        $user = $this->user_model->checkLogin($login, $password);
        if (empty($user)) {
            login_attempt_record($ip, $login, false);
            // Record failed login so brute-force / credential-stuffing
            // attempts are visible in the activity feed even without an
            // authenticated session.
            activity_log('auth', 'login_failed', 'user', null,
                'Failed login for "' . $login . '"',
                ['login_tried' => $login],
                ['user_id' => '', 'user_name' => '', 'user_role' => '', 'status' => 'fail']
            );
            $session->setFlashdata('error', 'Invalid credentials.');
            $session->setFlashdata('old_login', $login);
            return redirect()->to(site_url('login'));
        }

        // Success — clear the failed-attempt counter.
        login_attempt_record($ip, $login, true);
        login_attempts_clear($ip, $login);

        $this->user_model->setSession($user);
        error_log("pview alert >> Login complete: login=[" . $login . "], user_pk=[" . $user['id'] . "], user_id=[" . (isset($user['user_id']) ? $user['user_id'] : '') . "], role=[" . $user['role'] . "]");
        activity_log('auth', 'login', 'user', (string) $user['id'],
            'Login: ' . (isset($user['name']) ? $user['name'] : $login)
        );

        // Drop the user on the first module their role can actually
        // reach — walked in sidebar order. This way the landing page
        // mirrors the first nav link the user would have clicked, and
        // a role whose dashboard permission has been revoked won't get
        // bounced through an access-denied screen on every login.
        return redirect()->to(first_accessible_module_url());
    }

    /** GET /logout */
    public function logout()
    {
        // Log BEFORE destroying the session so the row is attributed.
        activity_log('auth', 'logout', 'user', (string) logged_user_id(),
            'Logout');
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

        // Verify the current password against the DB row.
        $userPk = (int) $session->get('user_pk');
        $row = $this->user_model->getById($userPk);
        if (empty($row) || !password_verify($current, (string) $row['password'])) {
            error_log('pview alert >> password_change rejected: bad current password, user_pk=[' . $userPk . ']');
            $session->setFlashdata('error', 'Current password is incorrect.');
            return redirect()->to(site_url('password/change'));
        }

        // Update + clear the rotate flag so the user can navigate again.
        $this->user_model->update($userPk, ['password' => $new]);
        $session->remove('password_must_rotate');
        $session->setFlashdata('success', 'Password updated.');
        error_log('pview alert >> password_change complete: user_pk=[' . $userPk . ']');
        activity_log('auth', 'password_change', 'user', (string) $userPk,
            'Password changed');

        // Same dynamic landing as login — first sidebar module the user
        // can actually access — so role-permission edits to dashboard or
        // tickets don't surprise the operator post-password-change.
        return redirect()->to(first_accessible_module_url());
    }

    /** GET /users — list page. */
    public function index()
    {
        check_module_access('users', 'view');
        error_log("pview alert >> users index page open");
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
        // Pre-filter the role list to what THIS actor is allowed to
        // assign. The view trusts this list and the server-side save()
        // re-validates against the same helper — defence in depth.
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
            error_log("pview alert >> users save rejected: missing required field (user_id=[" . $user_id . "], email=[" . $email . "])");
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
        // Validate against the roles table so custom roles defined under
        // /roles (e.g. vendor_lead) are accepted, not just the three
        // built-ins. Falls back to a strict deny if the posted key is
        // missing or doesn't match a real row.
        if (!$this->user_model->roleKeyExists($role)) {
            $this->session->setFlashdata('error', 'Invalid role selected.');
            return redirect()->to(site_url('users/add'));
        }
        // Privilege-escalation guard. Reject any role the current actor
        // is NOT allowed to assign — e.g. an admin-tier user creating a
        // super_admin, or a non-admin-scope user creating an admin-tier
        // user. The form dropdown already hides these options client-side;
        // this is the server-side enforcement against direct POSTs.
        if (!in_array($role, assignable_role_keys(), true)) {
            error_log('pview alert >> users save BLOCKED: actor=[' . logged_user_id() . '] role=[' . logged_user_role() . '] tried to assign role=[' . $role . ']');
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
        activity_log('users', 'create', 'user', (string) $newId,
            'Created user "' . $name . '" (' . $user_id . ', ' . $role . ')',
            ['user_id' => $user_id, 'name' => $name, 'email' => $email, 'role' => $role]);
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

        // Privilege-escalation guard. If the target user's role outranks
        // the actor's assignable list, refuse to open the edit form at
        // all — opening it would expose a path to change the target's
        // email / password and hijack the account. The update() endpoint
        // also re-checks server-side.
        $allowed = assignable_role_keys();
        if (!in_array((string) $user['role'], $allowed, true)) {
            error_log('pview alert >> users edit BLOCKED: actor=[' . logged_user_id() . '] role=[' . logged_user_role() . '] tried to edit user_pk=[' . $id . '] with role=[' . $user['role'] . ']');
            $this->session->setFlashdata('error', 'You do not have permission to edit a user with this role.');
            return redirect()->to(site_url('users'));
        }

        // Pre-filter the role dropdown — same filter as add().
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

        // Self-protection: a super_admin editing their OWN row cannot
        // demote themselves or deactivate the account — that's a one-way
        // self-lockout. They can still edit other fields.
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
        // See save() — validate against the live roles table so custom
        // roles can be assigned via the edit form, not just built-ins.
        if (!$this->user_model->roleKeyExists($role)) {
            $this->session->setFlashdata('error', 'Invalid role selected.');
            return redirect()->to(site_url('users/edit/' . $id));
        }

        // Privilege-escalation guards.
        //   1. The target's CURRENT role must be assignable by the actor
        //      — otherwise a lower-tier user could edit (and thereby
        //      modify email / password of) a higher-tier user's row.
        //   2. The new role being assigned must also be in the actor's
        //      assignable list — blocks promotion to super_admin etc.
        // Both checks together prevent every direct-POST escalation path.
        $allowedRoles = assignable_role_keys();
        $currentRoleKey = '';
        if (!empty($currentRow) && isset($currentRow['role'])) {
            $currentRoleKey = (string) $currentRow['role'];
        }
        if ($currentRoleKey !== '' && !in_array($currentRoleKey, $allowedRoles, true)) {
            error_log('pview alert >> users update BLOCKED: actor=[' . logged_user_id() . '] role=[' . logged_user_role() . '] tried to edit user_pk=[' . $id . '] with role=[' . $currentRoleKey . ']');
            $this->session->setFlashdata('error', 'You do not have permission to edit a user with this role.');
            return redirect()->to(site_url('users'));
        }
        if (!in_array($role, $allowedRoles, true)) {
            error_log('pview alert >> users update BLOCKED: actor=[' . logged_user_id() . '] role=[' . logged_user_role() . '] tried to assign role=[' . $role . '] to user_pk=[' . $id . ']');
            $this->session->setFlashdata('error', 'You do not have permission to assign that role.');
            return redirect()->to(site_url('users/edit/' . $id));
        }

        $isActive = bool_int($this->request->getPost('is_active'));

        // Self-protection: super_admin cannot demote/deactivate own row.
        if ($isSelfEdit && !empty($currentRow) && $currentRow['role'] === 'super_admin') {
            if ($role !== 'super_admin') {
                $this->session->setFlashdata('error', 'You cannot demote your own super-admin role.');
                return redirect()->to(site_url('users/edit/' . $id));
            }
            if ($isActive !== 1) {
                $this->session->setFlashdata('error', 'You cannot deactivate your own account.');
                return redirect()->to(site_url('users/edit/' . $id));
            }
        }

        // Last-super-admin guard: if this edit would demote the only
        // remaining super_admin, refuse — locks everyone out of /settings
        // and /module_control_panel.
        if (!empty($currentRow) && $currentRow['role'] === 'super_admin' && $role !== 'super_admin') {
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
            'password'  => $pass, // empty = unchanged (handled inside the model)
            'role'      => $role,
            'phone'     => (string) $this->request->getPost('phone'),
            'is_active' => $isActive,
        ];
        $this->user_model->update($id, $after);
        error_log("pview alert >> user update: id=[" . $id . "], user_id=[" . $user_id . "], by=[" . logged_user_id() . "]");
        $diff = activity_diff($currentRow, $after, ['user_id', 'name', 'email', 'role', 'phone', 'is_active']);
        if ($pass !== '') {
            $diff['password'] = ['(hidden)', '(reset)'];
        }
        activity_log('users', 'update', 'user', (string) $id,
            'Updated user "' . $name . '"', $diff);
        $this->session->setFlashdata('success', 'User "' . $name . '" updated.');
        return redirect()->to(site_url('users'));
    }

    /** GET /users/delete/(:num) — soft-delete a user. */
    public function delete($id)
    {
        check_module_access('users', 'delete');

        // Self-protection: a user cannot delete themselves.
        $sessionPk = (int) $this->session->get('user_pk');
        if ($sessionPk > 0 && $sessionPk === (int) $id) {
            $this->session->setFlashdata('error', 'You cannot delete your own account.');
            return redirect()->to(site_url('users'));
        }

        $row = $this->user_model->getById($id);

        // Privilege-escalation guard. The target's role must be in the
        // actor's assignable list — otherwise a lower-tier user could
        // delete a higher-tier user (effectively account takeover via
        // recreation, or denial-of-service against the rightful admin).
        if (!empty($row) && isset($row['role'])) {
            $targetRole = (string) $row['role'];
            if (!in_array($targetRole, assignable_role_keys(), true)) {
                error_log('pview alert >> users delete BLOCKED: actor=[' . logged_user_id() . '] role=[' . logged_user_role() . '] tried to delete user_pk=[' . $id . '] with role=[' . $targetRole . ']');
                $this->session->setFlashdata('error', 'You do not have permission to delete a user with this role.');
                return redirect()->to(site_url('users'));
            }
        }

        // Last-super-admin guard.
        if (!empty($row) && $row['role'] === 'super_admin') {
            $remaining = $this->user_model->countActiveSuperAdmins((int) $id);
            if ($remaining < 1) {
                $this->session->setFlashdata('error', 'Cannot delete — this is the last active super-admin.');
                return redirect()->to(site_url('users'));
            }
        }

        error_log("pview alert >> users delete request: id=[" . $id . "]");
        $this->user_model->softDelete($id);
        $deletedName = isset($row['name']) ? (string) $row['name'] : '';
        activity_log('users', 'delete', 'user', (string) $id,
            'Removed user "' . $deletedName . '"',
            ['user_id' => isset($row['user_id']) ? $row['user_id'] : '', 'name' => $deletedName]);
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

        // Visible columns: User ID | Name | Email | Role | Phone | Active | Created | Actions
        // Phone is a free-form column without an index; Actions is not sortable.
        // The old map collapsed both onto 'name' which was misleading on click.
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

        // Pull the actor's assignable role list once so we can hide
        // Edit/Delete on rows the actor can't manage — the controller
        // already rejects those requests; this just keeps the table
        // honest (no buttons that bounce back with an error).
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

            // Determine if the actor is allowed to operate on this row.
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
        $session = \Config\Services::session();
        $session->start();

        $theme = $this->request->getPost('theme');
        if (!in_array($theme, ['dark', 'light'], true)) {
            return json_fail('Invalid theme preference');
        }

        // Update session
        $session->set('theme', $theme);

        // Update database if logged in
        $userPk = (int) $session->get('user_pk');
        if ($userPk > 0) {
            $this->user_model->update($userPk, ['theme' => $theme]);
            error_log("pview alert >> user theme updated to=[" . $theme . "] for user_pk=[" . $userPk . "]");
            activity_log('me', 'theme_change', 'user', (string) $userPk,
                'Theme changed to ' . $theme, ['theme' => $theme]);
        }

        return json_ok([], 'Theme preference updated');
    }

    /** GET /users/active_json — lightweight list of active users for
     *  mention autocomplete. Returns user_id + name; ticket detail page
     *  caches this client-side for the lifetime of the page. */
    public function active_json()
    {
        check_isvalidated();
        $rows = $this->user_model->getActive();
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

        // Sanitise each field; never trust raw POST.
        $defaultProjectId = (int) $this->request->getPost('default_project_id');
        if ($defaultProjectId < 0) {
            $defaultProjectId = 0;
        }

        $kpiKeys = ['open', 'critical', 'major', 'resolved'];
        $kpiVisible = [];
        foreach ($kpiKeys as $k) {
            // Checkbox is only present in the POST when checked. Default to
            // visible if the operator wiped the form clean.
            if ($this->request->getPost('kpi_' . $k) !== null) {
                $kpiVisible[$k] = 1;
            } else {
                $kpiVisible[$k] = 0;
            }
        }
        // Don't let the operator hide ALL four KPI cards — that breaks the
        // dashboard layout. Force at least Open Tickets back on.
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
            $defaultTrendRange = 0; // means "no explicit default — use the system default"
        }

        $layout = [
            'default_project_id'  => $defaultProjectId,
            'kpi_visible'         => $kpiVisible,
            'default_trend_range' => $defaultTrendRange,
        ];

        $this->user_model->update($userPk, ['dashboard_layout' => json_encode($layout)]);

        // Refresh session so the change is visible on the very next render.
        $this->session->set('dashboard_layout', $layout);

        error_log("pview alert >> me_dashboard saved: user_pk=[" . $userPk . "], layout=[" . json_encode($layout) . "]");
        activity_log('me', 'prefs_save', 'user', (string) $userPk,
            'Saved dashboard preferences', $layout);
        $this->session->setFlashdata('success', 'Dashboard preferences updated.');
        return redirect()->to(site_url('dashboard'));
    }

    /** GET /me/notifications — per-user severity-by-project opt-out matrix. */
    public function me_notifications()
    {
        check_isvalidated();

        $appModel = new \App\Models\app_model();
        $userId   = (string) $this->session->get('user_id');

        // Load existing rows keyed by "project_id|severity" for fast lookup
        // in the view. Lenient default applies when no row is found.
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

        // Build the canonical (project_id, severity) list we expect to see:
        //   project_id = 0 row stands for "all projects" catch-all
        //   plus one row per active project.
        $projectIds = [0];
        foreach ($projects as $p) {
            $projectIds[] = (int) $p['id'];
        }
        $severities = ['info', 'major', 'critical'];

        $now = date('Y-m-d H:i:s');
        foreach ($projectIds as $pid) {
            foreach ($severities as $sev) {
                // Checkboxes are only present in POST when checked. We treat
                // a missing key as "disabled" so the user can opt out by
                // unchecking — that's the whole point of the page.
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

        error_log("pview alert >> me_notifications saved: user_id=[" . $userId . "]");
        activity_log('me', 'prefs_save', 'user', $userId,
            'Saved notification preferences');
        $this->session->setFlashdata('success', 'Notification preferences updated.');
        return redirect()->to(site_url('me/notifications'));
    }

    /** GET /roles — role list. Now permission-gated via the Module
     *  Control Panel ('roles' module) instead of hardcoded super_admin,
     *  so an admin can delegate role administration to a custom role. */
    public function roles()
    {
        check_module_access('roles', 'view');
        activity_log('roles', 'view', null, null, 'Opened Roles page');
        $rows = $this->user_model->getAllRoles();
        // Sprinkle a user count next to each row so the admin knows who's
        // assigned where before they consider a delete.
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
        // Same grammar as user_id — keeps everything URL/HTML-safe.
        if (!preg_match('/^[a-z][a-z0-9_]{1,49}$/', $roleKey)) {
            $this->session->setFlashdata('error', 'Role key must start with a letter and contain only lowercase letters, digits, or underscore (2-50 chars).');
            return redirect()->to(site_url('roles/add'));
        }

        if ($this->user_model->roleKeyExists($roleKey)) {
            $this->session->setFlashdata('error', 'A role with that key already exists.');
            return redirect()->to(site_url('roles/add'));
        }

        // Checkbox is only present in POST when checked; treat missing as 0.
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
        // Seed empty module_permissions rows so the Module Control Panel
        // shows this role in its grid right away. All zeros — admin toggles
        // what the role can do.
        $this->user_model->seedDefaultPermissions($roleKey);

        activity_log('roles', 'create', 'role', $roleKey,
            'Created role "' . $label . '" (' . $roleKey . ')',
            ['role_key' => $roleKey, 'label' => $label, 'is_admin_scope' => $isAdminScope]);

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
        // super_admin always carries admin-scope as a safety net — never
        // accept the form's value for it. UI also disables the field for
        // built-ins, but the server-side guard is what actually protects
        // against direct POSTs.
        if ($role_key === 'super_admin') {
            $isAdminScope = 1;
        }

        $this->user_model->updateRoleLabel($role_key, $label);
        $this->user_model->updateRoleAdminScope($role_key, $isAdminScope);
        activity_log('roles', 'update', 'role', (string) $role_key,
            'Updated role "' . $role_key . '" → "' . $label . '"',
            ['role_key' => $role_key, 'label' => $label, 'is_admin_scope' => $isAdminScope]);
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
        activity_log('roles', 'delete', 'role', (string) $role_key,
            'Removed role "' . $role['label'] . '" (' . $role_key . ')',
            ['role_key' => $role_key, 'label' => $role['label']]);
        $this->session->setFlashdata('success', 'Role "' . $role['label'] . '" removed.');
        return redirect()->to(site_url('roles'));
    }
}
