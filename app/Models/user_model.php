<?php

namespace App\Models;

class User_model
{
    public $db;

    function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    // --- Read ---

    public function getAll()
    {
        return $this->db->table('users')
            ->where('deleted_at', null)
            ->orderBy('name', 'asc')
            ->get()->getResultArray();
    }

    public function getById($id)
    {
        return $this->db->table('users')
            ->where('id', (int) $id)
            ->where('deleted_at', null)
            ->get()->getRowArray();
    }

    /** Look up by the human-typed user_id (e.g. 'jdoe'). */
    public function getByUserId($user_id)
    {
        return $this->db->table('users')
            ->where('user_id', (string) $user_id)
            ->where('deleted_at', null)
            ->get()->getRowArray();
    }

    public function getByIds($ids)
    {
        // Tolerant of both numeric PKs and user_id strings (migration window).
        if (empty($ids)) {
            return [];
        }
        $arr = array_values(array_filter((array) $ids, function ($v) {
            return $v !== null && $v !== '';
        }));
        if (empty($arr)) {
            return [];
        }
        $allDigits = true;
        foreach ($arr as $v) {
            if (!ctype_digit((string) $v)) {
                $allDigits = false;
                break;
            }
        }
        $column = 'user_id';
        return $this->db->table('users')
            ->whereIn($column, array_map('strval', $arr))
            ->where('deleted_at', null)
            ->get()->getResultArray();
    }

    public function getActive()
    {
        return $this->db->table('users')
            ->where('is_active', 1)
            ->where('deleted_at', null)
            ->orderBy('name', 'asc')
            ->get()->getResultArray();
    }

    /** True if a user_id is already taken (optionally ignoring one row by PK). */
    public function userIdExists($user_id, $ignoreId = 0)
    {
        $q = $this->db->table('users')->where('user_id', (string) $user_id);
        if ((int) $ignoreId > 0) {
            $q->where('id !=', (int) $ignoreId);
        }
        return $q->countAllResults() > 0;
    }

    // Count of active super_admin rows excluding the optional ignore PK
    // (used by the user controller's self-protection guard to refuse the
    // last super_admin being demoted/deleted).
    public function countActiveSuperAdmins($ignoreId = 0)
    {
        $q = $this->db->table('users')
            ->where('role', 'super_admin')
            ->where('is_active', 1)
            ->where('deleted_at', null);
        if ((int) $ignoreId > 0) {
            $q->where('id !=', (int) $ignoreId);
        }
        return (int) $q->countAllResults();
    }

    /** True if an email is already taken (optionally ignoring one row by PK). */
    public function emailExists($email, $ignoreId = 0)
    {
        $q = $this->db->table('users')->where('email', (string) $email);
        if ((int) $ignoreId > 0) {
            $q->where('id !=', (int) $ignoreId);
        }
        return $q->countAllResults() > 0;
    }

    // --- Write ---

    public function save($data)
    {
        if (isset($data['password']) && $data['password'] !== '') {
            $data['password']            = password_hash($data['password'], PASSWORD_BCRYPT);
            $data['password_changed_at'] = date('Y-m-d H:i:s');
        } else {
            unset($data['password']);
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->table('users')->insert($data);
        $id = $this->db->insertID();
        error_log("pview alert >> user save: query=[" . $this->db->getLastQuery() . "], new_id=[" . $id . "]");
        return $id;
    }

    public function update($id, $data)
    {
        if (isset($data['password']) && $data['password'] !== '') {
            $data['password']            = password_hash($data['password'], PASSWORD_BCRYPT);
            $data['password_changed_at'] = date('Y-m-d H:i:s');
        } else {
            unset($data['password']);
        }
        $ok = $this->db->table('users')->where('id', (int) $id)->update($data);
        error_log("pview alert >> user update: query=[" . $this->db->getLastQuery() . "], id=[" . $id . "], ok=[" . (int) $ok . "]");

        // WF-02: If the operator is being deactivated, return their active tickets
        // to the unassigned pool — same logic as softDelete() — so they don't deadlock.
        if (isset($data['is_active']) && (int) $data['is_active'] === 0) {
            $row = $this->db->table('users')->select('user_id')->where('id', (int) $id)->get()->getRowArray();
            if (!empty($row['user_id'])) {
                $this->db->table('tickets')
                    ->where('current_assignee', $row['user_id'])
                    ->whereIn('status', ['open', 'in_progress', 'escalated'])
                    ->update(['current_assignee' => null]);
                error_log("pview alert >> user deactivate: unassigned active tickets for user_id=[" . $row['user_id'] . "]");
            }
        }

        return $ok;
    }

    public function softDelete($id)
    {
        // First look up the user_id string so we can unassign their tickets.
        $row = $this->db->table('users')->select('user_id')->where('id', (int) $id)->get()->getRowArray();

        $ok = $this->db->table('users')->where('id', (int) $id)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
            'is_active'  => 0,
        ]);
        error_log("pview alert >> user softDelete: query=[" . $this->db->getLastQuery() . "], id=[" . $id . "]");

        // WF-02: Return all active tickets assigned to this operator to the
        // unassigned pool. Without this, their tickets become invisible —
        // they are still "assigned" but the operator can no longer log in.
        if (!empty($row['user_id'])) {
            $this->db->table('tickets')
                ->where('current_assignee', $row['user_id'])
                ->whereIn('status', ['open', 'in_progress', 'escalated'])
                ->update(['current_assignee' => null]);
            error_log("pview alert >> user softDelete: unassigned active tickets for user_id=[" . $row['user_id'] . "]");
        }

        return $ok;
    }

    // --- Login / Session ---

    // $login may be a user_id or email (branches on presence of '@').
    public function checkLogin($login, $password)
    {
        $login = trim((string) $login);

        $builder = $this->db->table('users');
        if (strpos($login, '@') !== false) {
            $builder->where('email', $login);
        } else {
            $builder->where('user_id', $login);
        }
        $query = $builder
            ->where('is_active', 1)
            ->where('deleted_at', null)
            ->get();

        error_log("pview alert >> Login query is [" . $this->db->getLastQuery() . "], num_rows [" . $query->getNumRows() . "]");

        $row = $query->getRowArray();

        if (empty($row)) {
            error_log("pview alert >> Login failed: no active user found for login=[" . $login . "]");
            return false;
        }
        if (!password_verify((string) $password, (string) $row['password'])) {
            error_log("pview alert >> Login failed: bad password for login=[" . $login . "]");
            return false;
        }
        error_log("pview alert >> Login OK: login=[" . $login . "], user_pk=[" . $row['id'] . "], user_id=[" . (isset($row['user_id']) ? $row['user_id'] : '') . "]");
        return $row;
    }

    public function setSession($user)
    {
        $session = \Config\Services::session();
        $session->start();

        $rotateDays = (int) app_setting('password_rotate_days', 90);
        $must_rotate = password_must_rotate(isset($user['password_changed_at']) ? $user['password_changed_at'] : null, $rotateDays);

        // user_id = human FK string; user_pk = numeric PK; user_uid = alias of user_id for views.
        $userIdStr = '';
        if (isset($user['user_id'])) {
            $userIdStr = (string) $user['user_id'];
        }
        // Load dashboard preferences (kpi visibility / default project /
        // default trend range) from the JSON column so views can read them
        // without an extra query per page render. Empty / invalid JSON
        // collapses to an empty array, which the helpers treat as defaults.
        $dashboardLayout = [];
        if (isset($user['dashboard_layout']) && $user['dashboard_layout'] !== '') {
            $decoded = json_decode((string) $user['dashboard_layout'], true);
            if (is_array($decoded)) {
                $dashboardLayout = $decoded;
            }
        }

        $session->set([
            'user_pk'              => (int) $user['id'],
            'user_id'              => $userIdStr,
            'user_uid'             => $userIdStr,
            'user_name'            => $user['name'],
            'user_email'           => $user['email'],
            'user_role'            => $user['role'],
            'theme'                => isset($user['theme']) ? $user['theme'] : 'dark',
            'dashboard_layout'     => $dashboardLayout,
            'logged_in'            => true,
            'password_must_rotate' => $must_rotate,
        ]);
        // Rotate session id post-login to prevent fixation attacks.
        $session->regenerate(true);
        error_log("pview alert >> Session set for user_pk=[" . $user['id'] . "], user_id=[" . $userIdStr . "], password_must_rotate=[" . (int) $must_rotate . "]");
    }

    public function logout()
    {
        $session = \Config\Services::session();
        $session->start();
        $uid = $session->get('user_pk');
        if (!$uid) {
            $uid = $session->get('user_id');
        }
        $session->destroy();
        error_log("pview alert >> Logout: user_pk=[" . (int) $uid . "]");
    }

    public function usersForDT($args)
    {
        $allowedCols = [
            'user_id'    => 'user_id',
            'name'       => 'name',
            'email'      => 'email',
            'role'       => 'role',
            'phone'      => 'phone',
            'is_active'  => 'is_active',
            'created_at' => 'created_at',
        ];
        $orderCol = 'name';
        if (!empty($args['order_col']) && isset($allowedCols[$args['order_col']])) {
            $orderCol = $allowedCols[$args['order_col']];
        }
        $orderDir = (!empty($args['order_dir']) && strtolower($args['order_dir']) === 'desc') ? 'DESC' : 'ASC';

        $start  = isset($args['start'])  ? (int) $args['start']  : 0;
        $length = isset($args['length']) ? (int) $args['length'] : 25;
        $search = isset($args['search']) ? (string) $args['search'] : '';

        $total = (int) $this->db->table('users')->where('deleted_at', null)->countAllResults();

        $baseWhere = "WHERE deleted_at IS NULL";
        $params    = [];

        if ($search !== '') {
            $like       = '%' . $search . '%';
            $baseWhere .= " AND (name LIKE ? OR email LIKE ? OR user_id LIKE ?)";
            $params     = [$like, $like, $like];
        }

        $countSql = "SELECT COUNT(*) AS cnt FROM users " . $baseWhere;
        $countRow = $this->db->query($countSql, $params)->getRow();
        $filtered = isset($countRow->cnt) ? (int) $countRow->cnt : 0;

        $dataSql = "SELECT id, user_id, name, email, role, phone, is_active, created_at
                    FROM users " . $baseWhere . "
                    ORDER BY " . $orderCol . " " . $orderDir . "
                    LIMIT " . $length . " OFFSET " . $start;
        $rows = $this->db->query($dataSql, $params)->getResultArray();

        return ['total' => $total, 'filtered' => $filtered, 'rows' => $rows];
    }

    // --- Roles ---
    // Role definitions live in the `roles` table; the role_key column on
    // `users` references roles.role_key. Kept here (rather than a separate
    // model) because role admin is part of user management.

    public function getAllRoles()
    {
        return $this->db->table('roles')
            ->orderBy('sort_order', 'asc')
            ->orderBy('role_key', 'asc')
            ->get()->getResultArray();
    }

    public function getRoleByKey($role_key)
    {
        return $this->db->table('roles')
            ->where('role_key', (string) $role_key)
            ->get()->getRowArray();
    }

    public function roleKeyExists($role_key)
    {
        return $this->db->table('roles')
            ->where('role_key', (string) $role_key)
            ->countAllResults() > 0;
    }

    // Count active (non-deleted) users currently assigned to this role.
    // Used by the delete guard so we never strand operators on a deleted role.
    public function countUsersWithRole($role_key)
    {
        return (int) $this->db->table('users')
            ->where('role', (string) $role_key)
            ->where('deleted_at', null)
            ->countAllResults();
    }

    public function saveRole($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->table('roles')->insert($data);
        error_log("pview alert >> role save: query=[" . $this->db->getLastQuery() . "]");
        return $data['role_key'];
    }

    // Label-only update — role_key is the primary key and never changes
    // (renaming the key would orphan module_permissions rows).
    public function updateRoleLabel($role_key, $label)
    {
        $ok = $this->db->table('roles')
            ->where('role_key', (string) $role_key)
            ->update(['label' => (string) $label]);
        error_log("pview alert >> role updateLabel: query=[" . $this->db->getLastQuery() . "], ok=[" . (int) $ok . "]");
        return $ok;
    }

    // Set the admin-scope flag (sees all tickets globally vs only own).
    // Kept separate from updateRoleLabel so the existing label-only path
    // stays minimal; controllers call both when the form is submitted.
    public function updateRoleAdminScope($role_key, $isAdminScope)
    {
        $ok = $this->db->table('roles')
            ->where('role_key', (string) $role_key)
            ->update(['is_admin_scope' => (int) $isAdminScope === 1 ? 1 : 0]);
        error_log("pview alert >> role updateAdminScope: query=[" . $this->db->getLastQuery() . "], ok=[" . (int) $ok . "]");
        return $ok;
    }

    public function deleteRole($role_key)
    {
        $row = $this->getRoleByKey($role_key);
        if (empty($row) || (int) $row['is_builtin'] === 1) {
            return false;
        }
        // Refuse if any active user still has this role.
        if ($this->countUsersWithRole($role_key) > 0) {
            return false;
        }
        $this->db->table('roles')->where('role_key', (string) $role_key)->delete();
        // Also drop their module_permissions rows — they're now dead data.
        $this->db->table('module_permissions')->where('role', (string) $role_key)->delete();
        error_log("pview alert >> role delete: role_key=[" . $role_key . "]");
        return true;
    }

    // Seed default module_permissions rows for a brand-new role so the
    // admin doesn't see an empty grid in the Module Control Panel. All zeros
    // by default — admin then toggles what the role can do.
    public function seedDefaultPermissions($role_key)
    {
        $registry = module_registry();
        foreach ($registry as $modKey => $modInfo) {
            $exists = $this->db->table('module_permissions')
                ->where('role', (string) $role_key)
                ->where('module_key', (string) $modKey)
                ->countAllResults();
            if ($exists === 0) {
                $this->db->table('module_permissions')->insert([
                    'role'       => (string) $role_key,
                    'module_key' => (string) $modKey,
                    'can_view'   => 0,
                    'can_add'    => 0,
                    'can_edit'   => 0,
                    'can_delete' => 0,
                ]);
            }
        }
    }
}
