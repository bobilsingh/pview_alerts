<?php

namespace App\Models;

class User_model
{
	public $db;

	// Constructor to initialize dependencies and references.
	function __construct()
	{
		$this->db = \Config\Database::connect();
	}

	// Fetches all users.
	public function getAll()
	{
		return $this->db->table('users')
			->where('deleted_at', null)
			->orderBy('name', 'asc')
			->get()->getResultArray();
	}

	// Fetches a user by ID.
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

	// Fetches users by a list of IDs.
	public function getByIds($ids)
	{
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

	// Fetches active users.
	public function getActive()
	{
		return $this->db->table('users')
			->where('is_active', 1)
			->where('deleted_at', null)
			->orderBy('name', 'asc')
			->get()->getResultArray();
	}

	// Fetches assignable users pool.
	public function getPoolUsers($callerUserId)
	{
		$caller = (string) $callerUserId;
		return $this->db->query(
			"SELECT DISTINCT u.user_id, u.name
               FROM users u
              WHERE u.is_active = 1
                AND u.deleted_at IS NULL
                AND (
                    u.user_id = ?
                    OR EXISTS (SELECT 1 FROM states s WHERE s.status = 'active' AND JSON_CONTAINS(s.l1_user_ids, JSON_QUOTE(u.user_id)))
                    OR EXISTS (SELECT 1 FROM states s WHERE s.status = 'active' AND JSON_CONTAINS(s.l2_user_ids, JSON_QUOTE(u.user_id)))
                    OR EXISTS (SELECT 1 FROM states s WHERE s.status = 'active' AND JSON_CONTAINS(s.l3_user_ids, JSON_QUOTE(u.user_id)))
                    OR EXISTS (SELECT 1 FROM states s WHERE s.status = 'active' AND JSON_CONTAINS(s.l4_user_ids, JSON_QUOTE(u.user_id)))
                )
              ORDER BY u.name ASC",
			[$caller]
		)->getResultArray();
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

	// Returns active super_admin count, excluding the given PK (used to guard against deleting the last one).
	public function countActiveSuperAdmins($ignoreId = 0)
	{
		$q = $this->db->table('users')
			->where('role', ROLE_SUPER_ADMIN)
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

	// Saves a new user.
	public function save($data)
	{
		if (isset($data['password']) && $data['password'] !== '') {
			$data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
			$data['password_changed_at'] = date('Y-m-d H:i:s');
		} else {
			unset($data['password']);
		}
		$data['created_at'] = date('Y-m-d H:i:s');
		$this->db->table('users')->insert($data);
		$id = $this->db->insertID();
		log_message('debug', "pview alert >> user save: query=[" . $this->db->getLastQuery() . "], new_id=[" . $id . "]");
		return $id;
	}

	// Updates user details.
	public function update($id, $data)
	{
		if (isset($data['password']) && $data['password'] !== '') {
			$data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
			$data['password_changed_at'] = date('Y-m-d H:i:s');
		} else {
			unset($data['password']);
		}
		$ok = $this->db->table('users')->where('id', (int) $id)->update($data);
		log_message('debug', "pview alert >> user update: query=[" . $this->db->getLastQuery() . "], id=[" . $id . "], ok=[" . (int) $ok . "]");

		// Return active tickets to the unassigned pool when an operator is deactivated.
		if (isset($data['is_active']) && (int) $data['is_active'] === 0) {
			$row = $this->db->table('users')->select('user_id')->where('id', (int) $id)->get()->getRowArray();
			if (!empty($row['user_id'])) {
				$this->db->table('tickets')
					->where('current_assignee', $row['user_id'])
					->whereIn('status', ['open', 'in_progress', 'escalated'])
					->update(['current_assignee' => null]);
				log_message('debug', "pview alert >> user deactivate: unassigned active tickets for user_id=[" . $row['user_id'] . "]");
			}
		}

		return $ok;
	}

	// Marks a user as deleted.
	public function softDelete($id)
	{
		$row = $this->db->table('users')->select('user_id')->where('id', (int) $id)->get()->getRowArray();

		$ok = $this->db->table('users')->where('id', (int) $id)->update([
			'deleted_at' => date('Y-m-d H:i:s'),
			'is_active' => 0,
		]);
		log_message('debug', "pview alert >> user softDelete: query=[" . $this->db->getLastQuery() . "], id=[" . $id . "]");

		// Return active tickets to the pool so they don't remain stuck on a deleted account.
		if (!empty($row['user_id'])) {
			$this->db->table('tickets')
				->where('current_assignee', $row['user_id'])
				->whereIn('status', ['open', 'in_progress', 'escalated'])
				->update(['current_assignee' => null]);
			log_message('debug', "pview alert >> user softDelete: unassigned active tickets for user_id=[" . $row['user_id'] . "]");
		}

		return $ok;
	}

	// Accepts a user_id or email (auto-detected by presence of '@').
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

		log_message('debug', "pview alert >> Login query is [" . $this->db->getLastQuery() . "], num_rows [" . $query->getNumRows() . "]");

		$row = $query->getRowArray();

		if (empty($row)) {
			log_message('debug', "pview alert >> Login failed: no active user found for login=[" . $login . "]");
			return false;
		}
		if (!password_verify((string) $password, (string) $row['password'])) {
			log_message('debug', "pview alert >> Login failed: bad password for login=[" . $login . "]");
			return false;
		}
		$rowUserId = '';
		if (isset($row['user_id'])) {
			$rowUserId = $row['user_id'];
		}
		log_message('debug', "pview alert >> Login OK: login=[" . $login . "], user_pk=[" . $row['id'] . "], user_id=[" . $rowUserId . "]");
		return $row;
	}

	// Saves user profile preferences into session at login.
	public function setSession($user)
	{
		$session = \Config\Services::session();
		$session->start();

		$rotateDays = (int) app_setting('password_rotate_days', 90);
		$pwdChangedAt = null;
		if (isset($user['password_changed_at'])) {
			$pwdChangedAt = $user['password_changed_at'];
		}
		$must_rotate = password_must_rotate($pwdChangedAt, $rotateDays);

		// user_id = human FK string (e.g. "bobil.singh"); user_pk = numeric PK.
		$userIdStr = '';
		if (isset($user['user_id'])) {
			$userIdStr = (string) $user['user_id'];
		}
		// Load dashboard_layout JSON into session so views can read it without extra queries.
		$dashboardLayout = [];
		if (isset($user['dashboard_layout']) && $user['dashboard_layout'] !== '') {
			$decoded = json_decode((string) $user['dashboard_layout'], true);
			if (is_array($decoded)) {
				$dashboardLayout = $decoded;
			}
		}

		// Wipe pre-login session state before writing fresh user data.
		session_unset();

		$themeVal = 'dark';
		if (isset($user['theme'])) {
			$themeVal = $user['theme'];
		}

		$session->set([
			'user_pk' => (int) $user['id'],
			'user_id' => $userIdStr,
			'user_name' => $user['name'],
			'user_email' => $user['email'],
			'user_role' => $user['role'],
			'theme' => $themeVal,
			'dashboard_layout' => $dashboardLayout,
			'logged_in' => true,
			'password_must_rotate' => $must_rotate,
		]);
		// Regenerate session ID post-login to prevent session fixation.
		$session->regenerate(true);
		log_message('debug', "pview alert >> Session set for user_pk=[" . $user['id'] . "], user_id=[" . $userIdStr . "], password_must_rotate=[" . (int) $must_rotate . "]");
	}

	// Destroys user session on logout.
	public function logout()
	{
		$session = \Config\Services::session();
		$session->start();
		$uid = $session->get('user_pk');
		if (!$uid) {
			$uid = $session->get('user_id');
		}
		session_unset();
		$session->destroy();
		app_settings_clear_cache();
		log_message('debug', "pview alert >> Logout: user_pk=[" . (int) $uid . "]");
	}

	// Fetches users formatted for server-side DataTable.
	public function usersForDT($args)
	{
		$allowedCols = [
			'user_id' => 'user_id',
			'name' => 'name',
			'email' => 'email',
			'role' => 'role',
			'phone' => 'phone',
			'is_active' => 'is_active',
			'created_at' => 'created_at',
		];
		$orderCol = 'name';
		if (!empty($args['order_col']) && isset($allowedCols[$args['order_col']])) {
			$orderCol = $allowedCols[$args['order_col']];
		}
		$orderDir = 'ASC';
		if (!empty($args['order_dir']) && strtolower($args['order_dir']) === 'desc') {
			$orderDir = 'DESC';
		}

		$start = 0;
		if (isset($args['start'])) {
			$start = (int) $args['start'];
		}
		$length = 25;
		if (isset($args['length'])) {
			$length = (int) $args['length'];
		}
		$search = '';
		if (isset($args['search'])) {
			$search = (string) $args['search'];
		}

		$total = (int) $this->db->table('users')->where('deleted_at', null)->countAllResults();

		$baseWhere = "WHERE deleted_at IS NULL";
		$params = [];

		if ($search !== '') {
			$like = '%' . $search . '%';
			$baseWhere .= " AND (name LIKE ? OR email LIKE ? OR user_id LIKE ?)";
			$params = [$like, $like, $like];
		}

		$countSql = "SELECT COUNT(*) AS cnt FROM users " . $baseWhere;
		$countRow = $this->db->query($countSql, $params)->getRow();
		$filtered = 0;
		if (isset($countRow->cnt)) {
			$filtered = (int) $countRow->cnt;
		}

		$dataSql = "SELECT id, user_id, name, email, role, phone, is_active, created_at
                    FROM users " . $baseWhere . "
                    ORDER BY " . $orderCol . " " . $orderDir . "
                    LIMIT " . $length . " OFFSET " . $start;
		$rows = $this->db->query($dataSql, $params)->getResultArray();

		return ['total' => $total, 'filtered' => $filtered, 'rows' => $rows];
	}

	// Fetches all system roles.
	public function getAllRoles()
	{
		return $this->db->table('roles')
			->orderBy('sort_order', 'asc')
			->orderBy('role_key', 'asc')
			->get()->getResultArray();
	}

	// Fetches a role by its key.
	public function getRoleByKey($role_key)
	{
		return $this->db->table('roles')
			->where('role_key', (string) $role_key)
			->get()->getRowArray();
	}

	// Checks if a role key already exists.
	public function roleKeyExists($role_key)
	{
		return $this->db->table('roles')
			->where('role_key', (string) $role_key)
			->countAllResults() > 0;
	}

	// Returns active user count for a role; used by the delete guard.
	public function countUsersWithRole($role_key)
	{
		return (int) $this->db->table('users')
			->where('role', (string) $role_key)
			->where('deleted_at', null)
			->countAllResults();
	}

	// Saves a role definition.
	public function saveRole($data)
	{
		$data['created_at'] = date('Y-m-d H:i:s');
		$this->db->table('roles')->insert($data);
		log_message('debug', "pview alert >> role save: query=[" . $this->db->getLastQuery() . "]");
		return $data['role_key'];
	}

	// Updates the display label only; role_key is immutable to avoid orphaning permissions rows.
	public function updateRoleLabel($role_key, $label)
	{
		$ok = $this->db->table('roles')
			->where('role_key', (string) $role_key)
			->update(['label' => (string) $label]);
		log_message('debug', "pview alert >> role updateLabel: query=[" . $this->db->getLastQuery() . "], ok=[" . (int) $ok . "]");
		return $ok;
	}

	// Updates the admin-scope flag (controls global vs own-ticket visibility).
	public function updateRoleAdminScope($role_key, $isAdminScope)
	{
		$adminScopeVal = 0;
		if ((int) $isAdminScope === 1) {
			$adminScopeVal = 1;
		}
		$ok = $this->db->table('roles')
			->where('role_key', (string) $role_key)
			->update(['is_admin_scope' => $adminScopeVal]);
		log_message('debug', "pview alert >> role updateAdminScope: query=[" . $this->db->getLastQuery() . "], ok=[" . (int) $ok . "]");
		return $ok;
	}

	// Deletes a role.
	public function deleteRole($role_key)
	{
		$row = $this->getRoleByKey($role_key);
		if (empty($row) || (int) $row['is_builtin'] === 1) {
			return false;
		}
		if ($this->countUsersWithRole($role_key) > 0) {
			return false;
		}
		$this->db->table('roles')->where('role_key', (string) $role_key)->delete();
		$this->db->table('module_permissions')->where('role', (string) $role_key)->delete();
		log_message('debug', "pview alert >> role delete: role_key=[" . $role_key . "]");
		return true;
	}

	// Seeds zero-value module_permissions for a new role so the Module Control Panel shows it immediately.
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
					'role' => (string) $role_key,
					'module_key' => (string) $modKey,
					'can_view' => 0,
					'can_add' => 0,
					'can_edit' => 0,
					'can_delete' => 0,
				]);
			}
		}
	}
}
