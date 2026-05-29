<?php

namespace App\Models;

class App_model
{
    public $db;
    function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    // Cached per-request lookup maps — callers merge names/counts in PHP instead of joining.
    private function userNameMap()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        $rows = $this->db->table('users')->select('user_id, name')->get()->getResultArray();
        foreach ($rows as $r) {
            $cache[(string) $r['user_id']] = (string) $r['name'];
        }
        return $cache;
    }

    private function projectNameMap()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        $rows = $this->db->table('projects')->select('id, name')->where('deleted_at', null)->get()->getResultArray();
        foreach ($rows as $r) {
            $cache[(int) $r['id']] = (string) $r['name'];
        }
        return $cache;
    }

    private function flowNameMap()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        $rows = $this->db->table('flows')->select('id, name')->where('deleted_at', null)->get()->getResultArray();
        foreach ($rows as $r) {
            $cache[(int) $r['id']] = (string) $r['name'];
        }
        return $cache;
    }

    private function stateNameMap()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        $rows = $this->db->table('states')->select('id, name')->get()->getResultArray();
        foreach ($rows as $r) {
            $cache[(int) $r['id']] = (string) $r['name'];
        }
        return $cache;
    }

    private function flowStateCounts()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        $rows = $this->db->table('states')->select('flow_id, COUNT(*) AS n', false)->groupBy('flow_id')->get()->getResultArray();
        foreach ($rows as $r) {
            $cache[(int) $r['flow_id']] = (int) $r['n'];
        }
        return $cache;
    }

    public function projectGetAll()
    {
        $rows = $this->db->table('projects')->where('deleted_at', null)->orderBy('created_at', 'desc')->get()->getResultArray();
        $userMap = $this->userNameMap();
        $out = [];
        foreach ($rows as $r) {
            $r['created_by_name'] = '';
            if (isset($userMap[$r['created_by']])) {
                $r['created_by_name'] = $userMap[$r['created_by']];
            }
            $out[] = $r;
        }
        return $out;
    }

    public function projectGetById($id)
    {
        return $this->db->table('projects')->where('id', (int) $id)->where('deleted_at', null)->get()->getRowArray();
    }

    public function projectGetActive()
    {
        return $this->db->table('projects')->where('status', 'active')->where('deleted_at', null)->orderBy('name', 'asc')->get()->getResultArray();
    }

    public function projectSave($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->table('projects')->insert($data);
        $id = $this->db->insertID();
        error_log("pview alert >> project save: query=[" . $this->db->getLastQuery() . "], new_id=[" . $id . "]");
        return $id;
    }

    public function projectUpdate($id, $data)
    {
        $ok = $this->db->table('projects')->where('id', (int) $id)->update($data);
        error_log("pview alert >> project update: query=[" . $this->db->getLastQuery() . "], id=[" . $id . "], ok=[" . (int) $ok . "]");
        return $ok;
    }

    public function projectSoftDelete($id)
    {
        $id = (int) $id;
        $now = date('Y-m-d H:i:s');
        $ok = $this->db->table('projects')->where('id', $id)->update([
            'deleted_at' => $now,
            'status'     => 'inactive',
        ]);

        // Cascade: soft-delete all flows under the project
        $this->db->table('flows')->where('project_id', $id)->update([
            'deleted_at' => $now,
            'status'     => 'inactive',
        ]);

        // Cascade: deactivate all alert definitions under the project
        $this->db->table('alert_definitions')->where('project_id', $id)->update([
            'is_active' => 0,
        ]);

        // Cascade: deactivate all API keys under the project
        $this->db->table('api_keys')->where('project_id', $id)->update([
            'is_active' => 0,
        ]);

        error_log("pview alert >> project softDelete (cascaded): query=[" . $this->db->getLastQuery() . "], id=[" . $id . "]");
        return $ok;
    }

    public function projectCountActive()
    {
        return (int) $this->db->table('projects')->where('status', 'active')->where('deleted_at', null)->countAllResults();
    }

    // True when an active (non-deleted) project already has this name.
    // $ignoreId lets the edit form skip the row it's updating.
    public function projectNameExists($name, $ignoreId = 0)
    {
        $q = $this->db->table('projects')->where('name', (string) $name)->where('deleted_at', null);
        if ((int) $ignoreId > 0) {
            $q->where('id !=', (int) $ignoreId);
        }
        return $q->countAllResults() > 0;
    }

    public function flowGetAll()
    {
        $rows = $this->db->table('flows')->where('deleted_at', null)->orderBy('created_at', 'desc')->get()->getResultArray();
        $projectMap  = $this->projectNameMap();
        $userMap     = $this->userNameMap();
        $stateCounts = $this->flowStateCounts();

        $out = [];
        foreach ($rows as $r) {
            $r['project_name'] = '';
            if (isset($projectMap[(int) $r['project_id']])) {
                $r['project_name'] = $projectMap[(int) $r['project_id']];
            }
            $r['created_by_name'] = '';
            if (isset($userMap[$r['created_by']])) {
                $r['created_by_name'] = $userMap[$r['created_by']];
            }
            $r['state_count'] = 0;
            if (isset($stateCounts[(int) $r['id']])) {
                $r['state_count'] = $stateCounts[(int) $r['id']];
            }
            $out[] = $r;
        }
        return $out;
    }

    public function flowGetById($id)
    {
        return $this->db->table('flows')->where('id', (int) $id)->where('deleted_at', null)->get()->getRowArray();
    }

    public function flowGetActive()
    {
        return $this->db->table('flows')->where('status', 'active')->where('deleted_at', null)->orderBy('name', 'asc')->get()->getResultArray();
    }

    public function flowGetByProject($project_id)
    {
        return $this->db->table('flows')->where('project_id', (int) $project_id)->where('status', 'active')->where('deleted_at', null)->orderBy('name', 'asc')->get()->getResultArray();
    }

    public function flowSave($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->table('flows')->insert($data);
        $id = $this->db->insertID();
        error_log("pview alert >> flow save: query=[" . $this->db->getLastQuery() . "], new_id=[" . $id . "]");
        return $id;
    }

    public function flowUpdate($id, $data)
    {
        $ok = $this->db->table('flows')->where('id', (int) $id)->update($data);
        error_log("pview alert >> flow update: query=[" . $this->db->getLastQuery() . "], id=[" . $id . "], ok=[" . (int) $ok . "]");
        return $ok;
    }

    public function flowSoftDelete($id)
    {
        $id = (int) $id;
        $now = date('Y-m-d H:i:s');
        $ok = $this->db->table('flows')->where('id', $id)->update([
            'deleted_at' => $now,
            'status'     => 'inactive',
        ]);

        // Cascade: deactivate all alert definitions pointing to this flow
        $this->db->table('alert_definitions')->where('flow_id', $id)->update([
            'is_active' => 0,
        ]);

        error_log("pview alert >> flow softDelete (cascaded): query=[" . $this->db->getLastQuery() . "], id=[" . $id . "]");
        return $ok;
    }

    public function flowCountActive()
    {
        return (int) $this->db->table('flows')->where('status', 'active')->where('deleted_at', null)->countAllResults();
    }

    // True when an active flow with this name already exists IN the same
    // project. Flow names are only unique within a project, not globally.
    public function flowNameExists($name, $project_id, $ignoreId = 0)
    {
        $q = $this->db->table('flows')->where('name', (string) $name)->where('project_id', (int) $project_id)->where('deleted_at', null);
        if ((int) $ignoreId > 0) {
            $q->where('id !=', (int) $ignoreId);
        }
        return $q->countAllResults() > 0;
    }

    public function stateGetAll($flow_id)
    {
        return $this->db->table('states')->where('flow_id', (int) $flow_id)->orderBy('sort_order', 'asc')->orderBy('id', 'asc')->get()->getResultArray();
    }

    public function stateGetById($id)
    {
        return $this->db->table('states')->where('id', (int) $id)->get()->getRowArray();
    }

    // Returns the is_initial state; falls back to the first state by sort_order.
    public function stateGetInitial($flow_id)
    {
        $row = $this->db->table('states')->where('flow_id', (int) $flow_id)->where('is_initial', 1)->get()->getRowArray();
        if (!empty($row)) {
            return $row;
        }
        return $this->db->table('states')->where('flow_id', (int) $flow_id)->orderBy('sort_order', 'asc')->orderBy('id', 'asc')->get()->getRowArray();
    }

    // True if the flow uses parent_state_id tree topology. When true, the
    // linear sort_order fallback in stateGetNext() must NOT be used —
    // leaf states are terminal and tickets there should be resolved/closed.
    public function flowIsBranched($flow_id)
    {
        $n = $this->db->table('states')
            ->where('flow_id', (int) $flow_id)
            ->where('parent_state_id IS NOT NULL', null, false)
            ->countAllResults();
        return $n > 0;
    }

    // Returns the next state by sort_order for legacy flat flows ONLY.
    // For tree-shaped flows the caller should rely on stateGetChildren().
    public function stateGetNext($flow_id, $current_state_id)
    {
        // Safety: refuse to linear-fallback inside a branched flow — that
        // would jump tickets between unrelated branches.
        if ($this->flowIsBranched((int) $flow_id)) {
            return null;
        }
        $current = $this->stateGetById($current_state_id);
        if (empty($current)) {
            return null;
        }
        return $this->db->table('states')
            ->where('flow_id', (int) $flow_id)
            ->groupStart()
            ->where('sort_order >', (int) $current['sort_order'])
            ->orGroupStart()
            ->where('sort_order', (int) $current['sort_order'])
            ->where('id >', (int) $current_state_id)
            ->groupEnd()
            ->groupEnd()
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->limit(1)
            ->get()->getRowArray();
    }

    // Returns direct child states for branching flows.
    public function stateGetChildren(int $flow_id, int $parent_state_id): array
    {
        return $this->db->table('states')
            ->where('flow_id', $flow_id)
            ->where('parent_state_id', $parent_state_id)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()->getResultArray();
    }

    public function stateSave($data)
    {
        // JSON-encode level user_id arrays (strings after 2026-05-21 migration).
        foreach (['l1_user_ids', 'l2_user_ids', 'l3_user_ids', 'l4_user_ids'] as $f) {
            if (isset($data[$f]) && is_array($data[$f])) {
                $cleaned = [];
                foreach ($data[$f] as $v) {
                    $s = trim((string) $v);
                    if ($s !== '') {
                        $cleaned[] = $s;
                    }
                }
                $data[$f] = json_encode(array_values(array_unique($cleaned)));
            }
        }
        if (!empty($data['id'])) {
            $id = (int) $data['id'];
            unset($data['id']);
            $this->db->table('states')->where('id', $id)->update($data);
            error_log("pview alert >> state save (update): query=[" . $this->db->getLastQuery() . "], id=[" . $id . "]");
            return $id;
        }
        // Auto sort_order = max + 1
        if (!isset($data['sort_order']) && !empty($data['flow_id'])) {
            $maxRow = $this->db->table('states')
                ->selectMax('sort_order')
                ->where('flow_id', (int) $data['flow_id'])
                ->get()->getRow();
            $max = 0;
            if (!empty($maxRow) && isset($maxRow->sort_order)) {
                $max = (int) $maxRow->sort_order;
            }
            $data['sort_order'] = $max + 1;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->table('states')->insert($data);
        $newId = $this->db->insertID();
        error_log("pview alert >> state save (insert): query=[" . $this->db->getLastQuery() . "], new_id=[" . $newId . "]");
        return $newId;
    }

    // Refuses to delete a state that still has child states or tickets
    // pointing at it. Returns ['ok' => bool, 'reason' => string].
    public function stateDelete($id)
    {
        $id = (int) $id;
        $state = $this->stateGetById($id);
        if (empty($state)) {
            return ['ok' => false, 'reason' => 'State not found'];
        }
        $childCount = $this->db->table('states')->where('parent_state_id', $id)->countAllResults();
        if ($childCount > 0) {
            error_log("pview alert >> state delete REJECTED (has children): id=[" . $id . "], children=[" . $childCount . "]");
            return ['ok' => false, 'reason' => 'State has ' . $childCount . ' child state(s). Re-parent or delete them first.'];
        }
        $ticketCount = $this->db->table('tickets')->where('current_state_id', $id)->countAllResults();
        if ($ticketCount > 0) {
            error_log("pview alert >> state delete REJECTED (tickets present): id=[" . $id . "], tickets=[" . $ticketCount . "]");
            return ['ok' => false, 'reason' => 'State is referenced by ' . $ticketCount . ' ticket(s). Move or close them first.'];
        }
        $this->db->table('states')->where('id', $id)->delete();
        $this->db->table('escalation_matrix')->where('state_id', $id)->delete();
        error_log("pview alert >> state delete OK (cascaded to escalation_matrix): query=[" . $this->db->getLastQuery() . "], id=[" . $id . "]");
        return ['ok' => true, 'reason' => ''];
    }

    // Returns the set of state IDs reachable downward from $state_id
    // (children, grandchildren, etc). Used for cycle prevention when
    // re-parenting a state. Caps recursion at 100 levels as a safety net.
    public function stateGetDescendantIds($flow_id, $state_id)
    {
        $flow_id  = (int) $flow_id;
        $state_id = (int) $state_id;
        $all = $this->db->table('states')
            ->select('id, parent_state_id')
            ->where('flow_id', $flow_id)
            ->get()->getResultArray();
        $childrenByParent = [];
        foreach ($all as $row) {
            $pid = isset($row['parent_state_id']) ? (int) $row['parent_state_id'] : 0;
            if ($pid > 0) {
                $childrenByParent[$pid][] = (int) $row['id'];
            }
        }
        $out  = [];
        $stack = [$state_id];
        $guard = 0;
        while (!empty($stack) && $guard < 1000) {
            $guard++;
            $current = array_pop($stack);
            if (isset($childrenByParent[$current])) {
                foreach ($childrenByParent[$current] as $kid) {
                    if (!isset($out[$kid])) {
                        $out[$kid] = true;
                        $stack[] = $kid;
                    }
                }
            }
        }
        return array_keys($out);
    }

    // Clear is_initial=1 on every OTHER state in the same flow so only
    // one initial state exists per flow.
    public function stateClearOtherInitial($flow_id, $keep_state_id = 0)
    {
        $q = $this->db->table('states')
            ->where('flow_id', (int) $flow_id);
        if ((int) $keep_state_id > 0) {
            $q->where('id !=', (int) $keep_state_id);
        }
        $q->update(['is_initial' => 0]);
    }

    public function stateReorder($flow_id, $id_list)
    {
        $flow_id = (int) $flow_id;
        $ids     = array_values(array_unique(array_map('intval', (array) $id_list)));
        if ($flow_id <= 0 || empty($ids)) {
            return false;
        }
        $owned = $this->db->table('states')
            ->where('flow_id', $flow_id)
            ->whereIn('id', $ids)
            ->get()->getResultArray();
        if (count($owned) !== count($ids)) {
            error_log("pview alert >> state reorder REJECTED for flow_id=[" . $flow_id . "] (state ids do not all belong to flow)");
            return false;
        }
        $i = 1;
        foreach ($ids as $id) {
            $this->db->table('states')
                ->where('id', $id)
                ->where('flow_id', $flow_id)
                ->update(['sort_order' => $i++]);
        }
        error_log("pview alert >> state reorder OK: flow_id=[" . $flow_id . "], count=[" . count($ids) . "]");
        return true;
    }

    // Decode the JSON user_id list for the given level from a state row.
    public function stateLevelUsers($state, $level)
    {
        $key = 'l' . (int) $level . '_user_ids';
        $raw = '';
        if (isset($state[$key])) {
            $raw = $state[$key];
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $arr = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($arr)) {
            return [];
        }
        // Post-2026-05-21: user_ids are strings ("bobil.singh").
        return array_values(array_map('strval', $arr));
    }

    public function alertGetAll()
    {
        $rows = $this->db->table('alert_definitions')
            ->orderBy('created_at', 'desc')
            ->get()->getResultArray();
        $projectMap = $this->projectNameMap();
        $flowMap    = $this->flowNameMap();

        $out = [];
        foreach ($rows as $r) {
            $r['project_name'] = '';
            if (isset($projectMap[(int) $r['project_id']])) {
                $r['project_name'] = $projectMap[(int) $r['project_id']];
            }
            $r['flow_name'] = '';
            if (isset($flowMap[(int) $r['flow_id']])) {
                $r['flow_name'] = $flowMap[(int) $r['flow_id']];
            }
            $out[] = $r;
        }
        return $out;
    }

    public function alertGetById($id)
    {
        return $this->db->table('alert_definitions')->where('id', (int) $id)->get()->getRowArray();
    }

    public function alertSave($data)
    {
        if (isset($data['notify_user_ids']) && is_array($data['notify_user_ids'])) {
            // user_id strings post-2026-05-21 migration.
            $cleaned = [];
            foreach ($data['notify_user_ids'] as $v) {
                $s = trim((string) $v);
                if ($s !== '') {
                    $cleaned[] = $s;
                }
            }
            $data['notify_user_ids'] = json_encode(array_values(array_unique($cleaned)));
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->table('alert_definitions')->insert($data);
        $id = $this->db->insertID();
        error_log("pview alert >> alertdef save: query=[" . $this->db->getLastQuery() . "], new_id=[" . $id . "]");
        return $id;
    }

    public function alertUpdate($id, $data)
    {
        if (isset($data['notify_user_ids']) && is_array($data['notify_user_ids'])) {
            $cleaned = [];
            foreach ($data['notify_user_ids'] as $v) {
                $s = trim((string) $v);
                if ($s !== '') {
                    $cleaned[] = $s;
                }
            }
            $data['notify_user_ids'] = json_encode(array_values(array_unique($cleaned)));
        }
        $ok = $this->db->table('alert_definitions')->where('id', (int) $id)->update($data);
        error_log("pview alert >> alertdef update: query=[" . $this->db->getLastQuery() . "], id=[" . $id . "], ok=[" . (int) $ok . "]");
        return $ok;
    }

    public function alertDeactivate($id)
    {
        $ok = $this->db->table('alert_definitions')->where('id', (int) $id)->update(['is_active' => 0]);
        error_log("pview alert >> alertdef deactivate: query=[" . $this->db->getLastQuery() . "], id=[" . $id . "]");
        return $ok;
    }

    public function escalationGetAll()
    {
        $rows = $this->db->table('escalation_matrix')
            ->orderBy('flow_id', 'asc')
            ->orderBy('state_id', 'asc')
            ->orderBy('level', 'asc')
            ->get()->getResultArray();
        $flowMap  = $this->flowNameMap();
        $stateMap = $this->stateNameMap();

        $out = [];
        foreach ($rows as $r) {
            $r['flow_name'] = '';
            if (isset($flowMap[(int) $r['flow_id']])) {
                $r['flow_name'] = $flowMap[(int) $r['flow_id']];
            }
            $r['state_name'] = '';
            if (isset($stateMap[(int) $r['state_id']])) {
                $r['state_name'] = $stateMap[(int) $r['state_id']];
            }
            $out[] = $r;
        }
        return $out;
    }

    public function escalationSave($data)
    {
        if (isset($data['notify_user_ids']) && is_array($data['notify_user_ids'])) {
            // user_id strings post-2026-05-21 migration.
            $cleaned = [];
            foreach ($data['notify_user_ids'] as $v) {
                $s = trim((string) $v);
                if ($s !== '') {
                    $cleaned[] = $s;
                }
            }
            $data['notify_user_ids'] = json_encode(array_values(array_unique($cleaned)));
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['created_by'] = (string) session('user_id');
        $this->db->table('escalation_matrix')->insert($data);
        $id = $this->db->insertID();
        error_log("pview alert >> escalation save: query=[" . $this->db->getLastQuery() . "], new_id=[" . $id . "]");
        return $id;
    }

    public function escalationDelete($id)
    {
        $ok = $this->db->table('escalation_matrix')->where('id', (int) $id)->delete();
        error_log("pview alert >> escalation delete: query=[" . $this->db->getLastQuery() . "], id=[" . $id . "]");
        return $ok;
    }

    public function apiKeyGetAll()
    {
        $rows = $this->db->table('api_keys')
            ->orderBy('created_at', 'desc')
            ->get()->getResultArray();
        $projectMap = $this->projectNameMap();
        $userMap    = $this->userNameMap();

        $out = [];
        foreach ($rows as $r) {
            $r['project_name'] = '';
            if (isset($projectMap[(int) $r['project_id']])) {
                $r['project_name'] = $projectMap[(int) $r['project_id']];
            }
            $r['created_by_name'] = '';
            if (isset($userMap[$r['created_by']])) {
                $r['created_by_name'] = $userMap[$r['created_by']];
            }
            $out[] = $r;
        }
        return $out;
    }

    public function apiKeyGetById($id)
    {
        return $this->db->table('api_keys')->where('id', (int) $id)->get()->getRowArray();
    }

    // Returns the key row only when the bound project is still active
    // and not soft-deleted; rejects keys for archived projects so
    // external telemetry can't keep raising tickets after a decommission.
    public function apiKeyGetByKey($key)
    {
        return $this->db->table('api_keys k')
            ->select('k.*', false)
            ->join('projects p', 'p.id = k.project_id', 'inner')
            ->where('k.api_key', (string) $key)
            ->where('k.is_active', 1)
            ->where('p.status', 'active')
            ->where('p.deleted_at', null)
            ->get()->getRowArray();
    }

    public function apiKeyGenerate($project_id, $name)
    {
        $key = bin2hex(random_bytes(24));
        $this->db->table('api_keys')->insert([
            'project_id' => (int) $project_id,
            'name'       => $name,
            'api_key'    => $key,
            'is_active'  => 1,
            'created_by' => (string) session('user_id'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        error_log("pview alert >> api key generated: project_id=[" . $project_id . "]");
        return $key;
    }

    public function apiKeyToggle($id)
    {
        $row = $this->apiKeyGetById($id);
        if (empty($row)) {
            return false;
        }
        if ($row['is_active']) {
            $newState = 0;
        } else {
            $newState = 1;
        }
        $this->db->table('api_keys')->where('id', (int) $id)->update(['is_active' => $newState]);
        error_log("pview alert >> api key toggle: id=[" . $id . "], new_state=[" . $newState . "]");
        return true;
    }

    public function apiKeyTouchLastUsed($id)
    {
        $this->db->table('api_keys')->where('id', (int) $id)->update(['last_used' => date('Y-m-d H:i:s')]);
    }

    // Standard SELECT with JOINs for all ticket list/detail queries.
    private function ticketSelect()
    {
        // After migration 2026-05-21, join key for users is user_id string, not PK.
        return $this->db->table('tickets t')->select("t.*,p.name AS project_name,f.name AS flow_name,s.name AS state_name,s.is_final AS state_is_final,s.l1_tat_minutes, s.l2_tat_minutes, s.l3_tat_minutes, s.l4_tat_minutes, s.l1_user_ids, s.l2_user_ids, s.l3_user_ids, s.l4_user_ids,ua.name AS assignee_name,ur.name AS raised_by_name", false)
            ->join('projects p', 'p.id = t.project_id', 'left')
            ->join('flows f',    'f.id = t.flow_id',    'left')
            ->join('states s',   's.id = t.current_state_id', 'left')
            ->join('users ua',   'ua.user_id = t.current_assignee', 'left')
            ->join('users ur',   'ur.user_id = t.raised_by', 'left');
    }

    public function ticketGetByAlarm($alarm_id)
    {
        return $this->ticketSelect()->where('t.alarm_id', $alarm_id)->get()->getRowArray();
    }

    public function ticketRecent($limit = 10, $userPk = null, $isAdmin = false, $projectId = 0)
    {
        $q = $this->ticketSelect();
        $this->applyUserScope($q, 't', $userPk, $isAdmin, 's');
        if ((int) $projectId > 0) {
            $q->where('t.project_id', (int) $projectId);
        }
        return $q->orderBy('t.created_at', 'desc')->limit((int) $limit)->get()->getResultArray();
    }

    // Adds the user-scope JOIN+WHERE (raised_by / assignee / level user lists)
    // to a tickets query. No-op when $isAdmin or $userPk is empty.
    // $tAlias    = the tickets table alias used by the caller ('tickets' or 't').
    // $sAlias    = an existing states alias to reuse, or null to JOIN states as 'us_s'.
    private function applyUserScope($q, $tAlias, $userPk, $isAdmin, $sAlias = null)
    {
        if ($isAdmin === true) {
            return;
        }
        if (empty($userPk)) {
            return;
        }
        $uid        = (string) $userPk;
        $jsonNeedle = $this->db->escape(json_encode($uid));

        if ($sAlias === null) {
            $sAlias = 'us_s';
            $q->join('states us_s', 'us_s.id = ' . $tAlias . '.current_state_id', 'left');
        }

        $q->groupStart()
            ->where($tAlias . '.raised_by', $uid)
            ->orWhere($tAlias . '.current_assignee', $uid)
            ->orWhere("JSON_CONTAINS(" . $sAlias . ".l1_user_ids, {$jsonNeedle})", null, false)
            ->orWhere("JSON_CONTAINS(" . $sAlias . ".l2_user_ids, {$jsonNeedle})", null, false)
            ->orWhere("JSON_CONTAINS(" . $sAlias . ".l3_user_ids, {$jsonNeedle})", null, false)
            ->orWhere("JSON_CONTAINS(" . $sAlias . ".l4_user_ids, {$jsonNeedle})", null, false)
            ->orWhere("EXISTS (SELECT 1 FROM ticket_actions ta WHERE ta.ticket_id = " . $tAlias . ".id AND ta.performed_by = " . $this->db->escape($uid) . ")", null, false)
            ->groupEnd();
    }

    private function ticketApplyFilters($q, $filters)
    {
        if (!empty($filters['project_id'])) {
            $q->where('t.project_id', (int) $filters['project_id']);
        }
        if (!empty($filters['flow_id'])) {
            $q->where('t.flow_id', (int) $filters['flow_id']);
        }
        if (!empty($filters['status'])) {
            // Special value `active` is a convenience alias for
            // "anything still needing attention" — i.e. open,
            // in_progress, or escalated. It lets KPI cards link
            // directly to the same set their count represents.
            if ($filters['status'] === 'active') {
                $q->whereIn('t.status', ['open', 'in_progress', 'escalated']);
            } else {
                $q->where('t.status', $filters['status']);
            }
        }
        if (!empty($filters['alert_type'])) {
            $q->where('t.alert_type', $filters['alert_type']);
        }
        if (!empty($filters['priority'])) {
            $q->where('t.priority', $filters['priority']);
        }
        if (!empty($filters['user_id'])) {
            // user_id is a string after the 2026-05-21 migration.
            // JSON_CONTAINS needs a JSON-encoded needle (with quotes); db->escape() wraps it in single quotes.
            $uid = (string) $filters['user_id'];
            $jsonNeedle = $this->db->escape(json_encode($uid));
            $q->groupStart()
                ->where('t.raised_by', $uid)
                ->orWhere('t.current_assignee', $uid)
                ->orWhere("JSON_CONTAINS(s.l1_user_ids, {$jsonNeedle})", null, false)
                ->orWhere("JSON_CONTAINS(s.l2_user_ids, {$jsonNeedle})", null, false)
                ->orWhere("JSON_CONTAINS(s.l3_user_ids, {$jsonNeedle})", null, false)
                ->orWhere("JSON_CONTAINS(s.l4_user_ids, {$jsonNeedle})", null, false)
                ->orWhere("EXISTS (SELECT 1 FROM ticket_actions ta WHERE ta.ticket_id = t.id AND ta.performed_by = " . $this->db->escape($uid) . ")", null, false)
                ->groupEnd();
        }
    }

    public function ticketGetAll($filters = [])
    {
        $q = $this->ticketSelect();
        $this->ticketApplyFilters($q, $filters);
        if (!empty($filters['search'])) {
            $q->groupStart()->like('t.alarm_id', $filters['search'])->orLike('t.title',  $filters['search'])->groupEnd();
        }
        return $q->orderBy('t.created_at', 'desc')->get()->getResultArray();
    }

    public function ticketListForDataTables($args)
    {
        $filters = [];
        if (!empty($args['filters']) && is_array($args['filters'])) {
            $filters = $args['filters'];
        }

        $allowedSortCols = [
            'alarm_id'         => 't.alarm_id',
            'title'            => 't.title',
            'status'           => 't.status',
            'alert_type'       => 't.alert_type',
            'priority'         => 't.priority',
            'state_name'       => 's.name',
            'current_level'    => 't.current_level',
            'assignee_name'    => 'ua.name',
            'state_entered_at' => 't.state_entered_at',
            'created_at'       => 't.created_at',
        ];

        $orderCol = 't.created_at';
        if (!empty($args['order_col']) && isset($allowedSortCols[$args['order_col']])) {
            $orderCol = $allowedSortCols[$args['order_col']];
        }
        $orderDir = 'desc';
        if (!empty($args['order_dir']) && strtolower($args['order_dir']) === 'asc') {
            $orderDir = 'asc';
        }

        $start = 0;
        if (isset($args['start']) && (int) $args['start'] > 0) {
            $start = (int) $args['start'];
        }
        $length = 25;
        if (isset($args['length']) && (int) $args['length'] > 0) {
            $length = (int) $args['length'];
        }
        if ($length > 200) {
            $length = 200;
        }

        $q = $this->ticketSelect();
        $this->ticketApplyFilters($q, $filters);

        $search = '';
        if (!empty($args['search'])) {
            $search = (string) $args['search'];
        }
        if ($search !== '') {
            // UX-02: extend search across all visible text columns so operators
            // can find tickets by project, flow, state, or assignee name.
            $q->groupStart()
                ->like('t.alarm_id',  $search)
                ->orLike('t.title',   $search)
                ->orLike('p.name',    $search)
                ->orLike('f.name',    $search)
                ->orLike('s.name',    $search)
                ->orLike('ua.name',   $search)
                ->groupEnd();
        }

        $rows = $q->orderBy($orderCol, $orderDir)->limit($length, $start)->get()->getResultArray();

        // Scope the "total" badge to what the user can actually see so the
        // DataTables footer doesn't leak the system-wide count to non-admins.
        $scopeUserPk = null;
        $scopeIsAdmin = false;
        if (isset($args['scope_user_pk'])) {
            $scopeUserPk = $args['scope_user_pk'];
        }
        if (isset($args['scope_is_admin'])) {
            $scopeIsAdmin = (bool) $args['scope_is_admin'];
        }
        $totalAll      = $this->ticketCountAll($scopeUserPk, $scopeIsAdmin);
        $totalFiltered = $this->ticketCountFiltered($filters, $search);

        error_log("pview alert >> ticket dataTable page: query=[" . $this->db->getLastQuery() . "], start=[" . $start . "], length=[" . $length . "], rows=[" . count($rows) . "], total_all=[" . $totalAll . "], total_filtered=[" . $totalFiltered . "]");

        return [
            'rows'           => $rows,
            'total_all'      => $totalAll,
            'total_filtered' => $totalFiltered,
        ];
    }

    // Returns the total ticket count visible to the caller. Admins see
    // every ticket; ordinary users see only what applyUserScope() allows
    // (raised_by / current_assignee / current state level pools).
    // Used as DataTables' recordsTotal so the footer stops leaking the
    // system-wide ticket count to non-admin users.
    public function ticketCountAll($userPk = null, $isAdmin = false)
    {
        $q = $this->db->table('tickets');
        $this->applyUserScope($q, 'tickets', $userPk, $isAdmin);
        return (int) $q->countAllResults();
    }

    public function ticketCountFiltered($filters, $search = '')
    {
        // UX-02: join the same tables as ticketSelect() so the search
        // count accurately mirrors the row query when filtering by name.
        $q = $this->db->table('tickets t')
            ->join('projects p', 'p.id = t.project_id',          'left')
            ->join('flows f',    'f.id = t.flow_id',             'left')
            ->join('states s',   's.id = t.current_state_id',    'left')
            ->join('users ua',   'ua.user_id = t.current_assignee', 'left');
        $this->ticketApplyFilters($q, $filters);
        if ($search !== '') {
            $q->groupStart()
                ->like('t.alarm_id',  $search)
                ->orLike('t.title',   $search)
                ->orLike('p.name',    $search)
                ->orLike('f.name',    $search)
                ->orLike('s.name',    $search)
                ->orLike('ua.name',   $search)
                ->groupEnd();
        }
        return (int) $q->countAllResults();
    }

    public function ticketCountByStatus($userPk = null, $isAdmin = false, $projectId = 0)
    {
        $q = $this->db->table('tickets')->select('tickets.status, COUNT(*) AS n');
        $this->applyUserScope($q, 'tickets', $userPk, $isAdmin);
        if ((int) $projectId > 0) {
            $q->where('tickets.project_id', (int) $projectId);
        }
        $rows = $q->groupBy('tickets.status')->get()->getResultArray();
        $out = ['open' => 0, 'in_progress' => 0, 'escalated' => 0, 'resolved' => 0, 'closed' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['n'];
        }
        return $out;
    }

    public function ticketCountByAlertTypeActive($userPk = null, $isAdmin = false, $projectId = 0)
    {
        $q = $this->db->table('tickets')
            ->select('tickets.alert_type, COUNT(*) AS n')
            ->whereIn('tickets.status', ['open', 'in_progress', 'escalated']);
        $this->applyUserScope($q, 'tickets', $userPk, $isAdmin);
        if ((int) $projectId > 0) {
            $q->where('tickets.project_id', (int) $projectId);
        }
        $rows = $q->groupBy('tickets.alert_type')->get()->getResultArray();
        $out = ['info' => 0, 'major' => 0, 'critical' => 0];
        foreach ($rows as $r) {
            $out[$r['alert_type']] = (int) $r['n'];
        }
        return $out;
    }

    public function ticketTrendByRange($days, $userPk = null, $isAdmin = false, $projectId = 0)
    {
        $days = (int) $days;
        if ($days < 1) {
            $days = 7;
        }
        if ($days > 365) {
            $days = 365;
        }

        $labels = [];
        $buckets = [];
        for ($i = $days; $i >= 1; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            if ($days <= 14) {
                $labels[] = date('D', strtotime($day));
            } else {
                $labels[] = date('d-M', strtotime($day));
            }
            $buckets[$day] = 0;
        }

        $start = array_key_first($buckets) . ' 00:00:00';
        $q = $this->db->table('tickets')
            ->select('DATE(tickets.created_at) AS day_key, COUNT(*) AS n', false)
            ->where('tickets.created_at >=', $start);
        $this->applyUserScope($q, 'tickets', $userPk, $isAdmin);
        if ((int) $projectId > 0) {
            $q->where('tickets.project_id', (int) $projectId);
        }
        $rows = $q->groupBy('DATE(tickets.created_at)')->get()->getResultArray();
        foreach ($rows as $r) {
            $dayKey = (string) $r['day_key'];
            if (isset($buckets[$dayKey])) {
                $buckets[$dayKey] = (int) $r['n'];
            }
        }

        return [
            'labels' => $labels,
            'values' => array_values($buckets),
        ];
    }

    public function ticketCountTatBreached($userPk = null, $isAdmin = false, $projectId = 0)
    {
        $q = $this->db->table('tickets')->where('tickets.status', 'escalated');
        $this->applyUserScope($q, 'tickets', $userPk, $isAdmin);
        if ((int) $projectId > 0) {
            $q->where('tickets.project_id', (int) $projectId);
        }
        return (int) $q->countAllResults();
    }

    // Returns {total, escalated, critical_open} for the bell-badge.
    public function ticketCountActionable($userPk = null, $isAdmin = false)
    {
        $where = "(t.status = 'escalated'"
            . " OR (t.alert_type = 'critical' AND t.status IN ('open','in_progress')))";

        $select = "SELECT"
            . " COUNT(*) AS total,"
            . " SUM(CASE WHEN t.status = 'escalated' THEN 1 ELSE 0 END) AS escalated,"
            . " SUM(CASE WHEN t.alert_type = 'critical' AND t.status IN ('open','in_progress') THEN 1 ELSE 0 END) AS critical_open";

        $from   = " FROM tickets t";
        $params = [];

        if (!$isAdmin && !empty($userPk)) {
            // $userPk is the user_id string after migration (kept param name for back-compat).
            $from  .= " LEFT JOIN states s ON s.id = t.current_state_id";
            $where .= " AND (t.raised_by = ?"
                . " OR t.current_assignee = ?"
                . " OR JSON_CONTAINS(s.l1_user_ids, ?)"
                . " OR JSON_CONTAINS(s.l2_user_ids, ?)"
                . " OR JSON_CONTAINS(s.l3_user_ids, ?)"
                . " OR JSON_CONTAINS(s.l4_user_ids, ?)"
                . " OR EXISTS (SELECT 1 FROM ticket_actions ta WHERE ta.ticket_id = t.id AND ta.performed_by = ?))";
            $uid     = (string) $userPk;
            $uidJson = json_encode($uid);
            $params = [$uid, $uid, $uidJson, $uidJson, $uidJson, $uidJson, $uid];
        }

        $sql = $select . $from . " WHERE " . $where;

        try {
            $row = $this->db->query($sql, $params)->getRow();
        } catch (\Throwable $e) {
            error_log("pview alert >> ticketCountActionable() failed: " . $e->getMessage());
            return ['total' => 0, 'escalated' => 0, 'critical_open' => 0];
        }

        return [
            'total'         => $row ? (int) $row->total : 0,
            'escalated'     => $row ? (int) $row->escalated : 0,
            'critical_open' => $row ? (int) $row->critical_open : 0,
        ];
    }

    public function ticketSave($data)
    {
        $data['created_at']       = date('Y-m-d H:i:s');
        $data['state_entered_at'] = date('Y-m-d H:i:s');
        $this->db->table('tickets')->insert($data);
        $id = $this->db->insertID();
        error_log("pview alert >> ticket save: query=[" . $this->db->getLastQuery() . "], new_id=[" . $id . "], alarm_id=[" . (isset($data["alarm_id"]) ? $data["alarm_id"] : "") . "]");
        return $id;
    }

    public function ticketUpdate($id, $data)
    {
        $ok = $this->db->table('tickets')->where('id', (int) $id)->update($data);
        error_log("pview alert >> ticket update: query=[" . $this->db->getLastQuery() . "], id=[" . $id . "], ok=[" . (int) $ok . "]");
        return $ok;
    }

    public function ticketMoveToState($ticket_id, $new_state_id)
    {
        $this->db->table('tickets')->where('id', (int) $ticket_id)->update([
            'current_state_id'   => (int) $new_state_id,
            'current_level'      => 1,
            'state_entered_at'   => date('Y-m-d H:i:s'),
            'status'             => 'in_progress',
            // The pre-breach warning is a per-state-per-level signal — a
            // state change starts a fresh SLA window so the warning is
            // eligible to fire again at L1 of the new state.
            'last_tat_warn_level' => 0,
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);
        error_log("pview alert >> ticket moveToState: ticket_id=[" . $ticket_id . "], new_state_id=[" . $new_state_id . "]");
    }

    public function ticketEscalateLevel($ticket_id, $new_level)
    {
        if ($new_level >= 5) {
            $newStatus = 'escalated';
        } else {
            $newStatus = 'in_progress';
        }
        $this->db->table('tickets')->where('id', (int) $ticket_id)->update([
            'current_level'      => (int) $new_level,
            'state_entered_at'   => date('Y-m-d H:i:s'),
            'status'             => $newStatus,
            // Reset so the next level becomes eligible for its own 80% warning.
            'last_tat_warn_level' => 0,
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);
        error_log("pview alert >> ticket escalateLevel: query=[" . $this->db->getLastQuery() . "], ticket_id=[" . $ticket_id . "], new_level=[" . $new_level . "], status=[" . $newStatus . "]");
    }

    // Tickets eligible for the TAT-monitor cron sweep. Final-state tickets
    // are skipped — a workflow that has reached its terminal node has no
    // further escalation path, and waking the operator with auto-escalation
    // alerts on a "logically done" ticket creates false noise.
    public function ticketActiveForTatCheck()
    {
        return $this->ticketSelect()
            ->whereIn('t.status', ['open', 'in_progress'])
            ->where('(s.is_final IS NULL OR s.is_final = 0)', null, false)
            ->get()->getResultArray();
    }

    // Returns the escalation_matrix override for (flow, state, level), or
    // null when none is configured. When present, the override supplies the
    // TAT minutes and the notify-user list; otherwise the state's own
    // lN_tat_minutes / lN_user_ids are used. Single source of truth for
    // the cron — admins can configure per-state overrides via the
    // Escalation UI without re-editing the state.
    public function escalationRule($flow_id, $state_id, $level)
    {
        $row = $this->db->table('escalation_matrix')
            ->where('flow_id', (int) $flow_id)
            ->where('state_id', (int) $state_id)
            ->where('level', (int) $level)
            ->orderBy('id', 'desc')
            ->limit(1)
            ->get()->getRowArray();
        if (empty($row)) {
            return null;
        }
        $users = [];
        if (!empty($row['notify_user_ids'])) {
            $decoded = json_decode((string) $row['notify_user_ids'], true);
            if (is_array($decoded)) {
                $users = array_values(array_map('strval', $decoded));
            }
        }
        return [
            'tat_minutes'  => (int) $row['escalate_after'],
            'notify_users' => $users,
            'alert_type'   => isset($row['alert_type']) ? (string) $row['alert_type'] : 'major',
        ];
    }

    public function ticketLogAction($ticket_id, $action_type, $extra = [])
    {
        // performed_by stores user_id string after the 2026-05-21
        // migration; session('user_id') is already that string.
        $row = array_merge([
            'ticket_id'    => (int) $ticket_id,
            'action_type'  => $action_type,
            'created_at'   => date('Y-m-d H:i:s'),
            'performed_by' => (string) session('user_id'),
        ], $extra);
        $this->db->table('ticket_actions')->insert($row);
        error_log("pview alert >> ticket action logged: ticket_id=[" . $ticket_id . "], action=[" . $action_type . "]");
    }

    public function ticketTimeline($ticket_id)
    {
        // performed_by stores the user_id string after the
        // 2026-05-21 migration — join key is users.user_id.
        $sql = "SELECT ta.*, u.name AS performer_name, u.user_id AS performer_uid,"
            . " fs.name AS from_state_name, ts.name AS to_state_name"
            . " FROM ticket_actions ta"
            . " LEFT JOIN users  u  ON u.user_id = ta.performed_by"
            . " LEFT JOIN states fs ON fs.id = ta.from_state_id"
            . " LEFT JOIN states ts ON ts.id = ta.to_state_id"
            . " WHERE ta.ticket_id = ?"
            . " ORDER BY ta.id DESC";
        return $this->db->query($sql, [(int) $ticket_id])->getResultArray();
    }

    public function ticketRecentNotifications($ticket_id, $limit = 5)
    {
        return $this->db->table('notification_logs')->where('ticket_id', (int) $ticket_id)->orderBy('id', 'desc')->limit((int) $limit)->get()->getResultArray();
    }

    // $userId accepts the user_id string (column renamed user_pk→user_id in 2026-05-21 migration).
    public function savedFilterList($userId, $scope = 'tickets')
    {
        return $this->db->table('saved_filters')
            ->where('user_id', (string) $userId)
            ->where('scope', (string) $scope)
            ->orderBy('name', 'asc')
            ->get()->getResultArray();
    }

    // Upsert a saved filter preset; replace existing same-named preset for the user.
    public function savedFilterSave($userId, $name, $queryParams, $scope = 'tickets')
    {
        $row = [
            'user_id'      => (string) $userId,
            'scope'        => (string) $scope,
            'name'         => mb_substr((string) $name, 0, 100),
            'query_params' => (string) $queryParams,
            'created_at'   => date('Y-m-d H:i:s'),
        ];
        $this->db->table('saved_filters')->insert($row);
        return (int) $this->db->insertID();
    }

    // Delete a saved filter; scoped to userId so users can't delete others' presets.
    public function savedFilterDelete($id, $userId)
    {
        return $this->db->table('saved_filters')
            ->where('id', (int) $id)
            ->where('user_id', (string) $userId)
            ->delete();
    }

    public function notificationsRecentForUser($userPk, $userEmail, $isAdmin = false, $limit = 10)
    {
        $limit = (int) $limit;
        if ($limit < 1) {
            $limit = 10;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        $builder = $this->db->table('notification_logs nl')
            ->select('nl.id, nl.ticket_id, nl.recipient_email, nl.subject, nl.status, nl.sent_at, nl.created_at, t.alarm_id, t.title, t.alert_type, t.status AS ticket_status', false)
            ->join('tickets t', 't.id = nl.ticket_id', 'left')
            ->orderBy('nl.id', 'desc')
            ->limit($limit);

        if (!$isAdmin && $userEmail !== '') {
            $builder->where('nl.recipient_email', $userEmail);
        }

        return $builder->get()->getResultArray();
    }

    // Tickets the bell badge counts as "actionable" — anything escalated, or
    // any critical ticket still open / in_progress. One row per ticket
    // (no notification-log duplicates), sorted so the most urgent show first:
    //   critical-escalated > critical-active > other-escalated > other-active.
    // Used by the bell-dropdown so the list matches the badge number 1:1.
    public function actionableTicketsForUser($userPk = null, $isAdmin = false, $limit = 10)
    {
        $limit = (int) $limit;
        if ($limit < 1) {
            $limit = 10;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        $q = $this->ticketSelect()
            ->groupStart()
            ->where('t.status', 'escalated')
            ->orGroupStart()
            ->where('t.alert_type', 'critical')
            ->whereIn('t.status', ['open', 'in_progress'])
            ->groupEnd()
            ->groupEnd();

        $this->applyUserScope($q, 't', $userPk, $isAdmin, 's');

        // Severity ordering: critical=1, major=2, info=3 (then anything else).
        // Status ordering: escalated first.
        $rows = $q->orderBy("FIELD(t.alert_type, 'critical', 'major', 'info')", '', false)
            ->orderBy("FIELD(t.status, 'escalated', 'in_progress', 'open')", '', false)
            ->orderBy('t.state_entered_at', 'desc')
            ->limit($limit)
            ->get()->getResultArray();

        return $rows;
    }

    public function settingGetAll()
    {
        try {
            return $this->db->table('app_settings')->orderBy('setting_key', 'asc')->get()->getResultArray();
        } catch (\Throwable $e) {
            error_log("pview alert >> settingGetAll() failed: " . $e->getMessage());
            return [];
        }
    }

    /** True if the app_settings table exists in the current schema. */
    public function settingsTableExists()
    {
        try {
            $rows = $this->db->query("SHOW TABLES LIKE 'app_settings'")->getResultArray();
            return !empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function settingSet($key, $value, $userId = null)
    {
        // updated_by is a user_id string after migration; empty becomes NULL.
        $byVal = null;
        if ($userId !== null && $userId !== '') {
            $byVal = (string) $userId;
        }
        $now    = date('Y-m-d H:i:s');
        $exists = $this->db->table('app_settings')->where('setting_key', (string) $key)->countAllResults() > 0;
        if ($exists) {
            $this->db->table('app_settings')->where('setting_key', (string) $key)->update([
                'setting_value' => (string) $value,
                'updated_at'    => $now,
                'updated_by'    => $byVal,
            ]);
        } else {
            $this->db->table('app_settings')->insert([
                'setting_key'   => (string) $key,
                'setting_value' => (string) $value,
                'updated_at'    => $now,
                'updated_by'    => $byVal,
            ]);
        }
        error_log("pview alert >> setting saved: key=[" . $key . "], len=[" . strlen((string) $value) . "]");
    }

    public function projectsForDT($args)
    {
        $allowedCols = [
            'p.name'       => 'p.name',
            'p.status'     => 'p.status',
            'p.created_at' => 'p.created_at',
        ];
        $orderCol = 'p.created_at';
        if (!empty($args['order_col']) && isset($allowedCols[$args['order_col']])) {
            $orderCol = $allowedCols[$args['order_col']];
        }
        $orderDir = 'DESC';
        if (!empty($args['order_dir']) && strtolower($args['order_dir']) === 'asc') {
            $orderDir = 'ASC';
        }

        $start  = isset($args['start'])  ? (int) $args['start']  : 0;
        $length = isset($args['length']) ? (int) $args['length'] : 25;
        $search = isset($args['search']) ? (string) $args['search'] : '';

        $total = (int) $this->db->table('projects')->where('deleted_at', null)->countAllResults();

        $baseFrom = "FROM projects p WHERE p.deleted_at IS NULL";
        $params   = [];
        if ($search !== '') {
            $like      = '%' . $search . '%';
            $baseFrom .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $params    = [$like, $like];
        }

        $countRow = $this->db->query("SELECT COUNT(*) AS cnt " . $baseFrom, $params)->getRow();
        $filtered = isset($countRow->cnt) ? (int) $countRow->cnt : 0;

        $dataSql = "SELECT p.id, p.name, p.description, p.status, p.created_at, p.created_by"
            . " " . $baseFrom
            . " ORDER BY " . $orderCol . " " . $orderDir
            . " LIMIT " . $length . " OFFSET " . $start;
        $rows = $this->db->query($dataSql, $params)->getResultArray();

        $userMap = $this->userNameMap();
        $out = [];
        foreach ($rows as $r) {
            $r['created_by_name'] = '';
            if (isset($userMap[$r['created_by']])) {
                $r['created_by_name'] = $userMap[$r['created_by']];
            }
            $out[] = $r;
        }

        return ['total' => $total, 'filtered' => $filtered, 'rows' => $out];
    }

    public function flowsForDT($args)
    {
        $allowedCols = [
            'f.name'       => 'f.name',
            'p.name'       => 'p.name',
            'f.status'     => 'f.status',
            'f.created_at' => 'f.created_at',
        ];
        $orderCol = 'f.created_at';
        if (!empty($args['order_col']) && isset($allowedCols[$args['order_col']])) {
            $orderCol = $allowedCols[$args['order_col']];
        }
        $orderDir = 'DESC';
        if (!empty($args['order_dir']) && strtolower($args['order_dir']) === 'asc') {
            $orderDir = 'ASC';
        }

        $start  = isset($args['start'])  ? (int) $args['start']  : 0;
        $length = isset($args['length']) ? (int) $args['length'] : 25;
        $search = isset($args['search']) ? (string) $args['search'] : '';

        $total = (int) $this->db->table('flows')->where('deleted_at', null)->countAllResults();

        $baseJoin  = "FROM flows f"
            . " LEFT JOIN projects p ON p.id = f.project_id"
            . " WHERE f.deleted_at IS NULL";
        $params    = [];
        if ($search !== '') {
            $like      = '%' . $search . '%';
            $baseJoin .= " AND (f.name LIKE ? OR p.name LIKE ?)";
            $params    = [$like, $like];
        }

        $countRow = $this->db->query("SELECT COUNT(*) AS cnt " . $baseJoin, $params)->getRow();
        $filtered = isset($countRow->cnt) ? (int) $countRow->cnt : 0;

        $dataSql = "SELECT f.id, f.name, f.status, f.created_at, f.created_by,"
            . " p.name AS project_name"
            . " " . $baseJoin
            . " ORDER BY " . $orderCol . " " . $orderDir
            . " LIMIT " . $length . " OFFSET " . $start;
        $rows = $this->db->query($dataSql, $params)->getResultArray();

        $userMap     = $this->userNameMap();
        $stateCounts = $this->flowStateCounts();
        $out = [];
        foreach ($rows as $r) {
            $r['created_by_name'] = '';
            if (isset($userMap[$r['created_by']])) {
                $r['created_by_name'] = $userMap[$r['created_by']];
            }
            $r['state_count'] = 0;
            if (isset($stateCounts[(int) $r['id']])) {
                $r['state_count'] = $stateCounts[(int) $r['id']];
            }
            $out[] = $r;
        }

        return ['total' => $total, 'filtered' => $filtered, 'rows' => $out];
    }

    public function alertsForDT($args)
    {
        $allowedCols = [
            'a.name'       => 'a.name',
            'p.name'       => 'p.name',
            'a.alert_type' => 'a.alert_type',
            'a.is_active'  => 'a.is_active',
            'a.created_at' => 'a.created_at',
        ];
        $orderCol = 'a.created_at';
        if (!empty($args['order_col']) && isset($allowedCols[$args['order_col']])) {
            $orderCol = $allowedCols[$args['order_col']];
        }
        $orderDir = 'DESC';
        if (!empty($args['order_dir']) && strtolower($args['order_dir']) === 'asc') {
            $orderDir = 'ASC';
        }

        $start  = isset($args['start'])  ? (int) $args['start']  : 0;
        $length = isset($args['length']) ? (int) $args['length'] : 25;
        $search = isset($args['search']) ? (string) $args['search'] : '';

        $total = (int) $this->db->table('alert_definitions')->countAllResults();

        $baseJoin  = "FROM alert_definitions a"
            . " LEFT JOIN projects p ON p.id = a.project_id"
            . " WHERE 1=1";
        $params    = [];
        if ($search !== '') {
            $like      = '%' . $search . '%';
            $baseJoin .= " AND (a.name LIKE ? OR a.description LIKE ? OR p.name LIKE ?)";
            $params    = [$like, $like, $like];
        }

        $countRow = $this->db->query("SELECT COUNT(*) AS cnt " . $baseJoin, $params)->getRow();
        $filtered = isset($countRow->cnt) ? (int) $countRow->cnt : 0;

        $dataSql = "SELECT a.id, a.name, a.description, a.alert_type,"
            . " a.threshold_value, a.threshold_unit, a.is_active, a.created_at, a.flow_id,"
            . " p.name AS project_name"
            . " " . $baseJoin
            . " ORDER BY " . $orderCol . " " . $orderDir
            . " LIMIT " . $length . " OFFSET " . $start;
        $rows = $this->db->query($dataSql, $params)->getResultArray();

        $flowMap = $this->flowNameMap();
        $out = [];
        foreach ($rows as $r) {
            $r['flow_name'] = '';
            if (isset($flowMap[(int) $r['flow_id']])) {
                $r['flow_name'] = $flowMap[(int) $r['flow_id']];
            }
            $out[] = $r;
        }

        return ['total' => $total, 'filtered' => $filtered, 'rows' => $out];
    }

    public function modulePermissionsTableExists()
    {
        $rows = $this->db->query("SHOW TABLES LIKE 'module_permissions'")->getResultArray();
        if (empty($rows)) {
            return false;
        }
        return true;
    }

    public function modulePermissionsGetAll()
    {
        return $this->db->table('module_permissions')
            ->orderBy('role', 'asc')
            ->orderBy('module_key', 'asc')
            ->get()->getResultArray();
    }

    public function modulePermissionsSave($role, $permissions)
    {
        foreach ($permissions as $module_key => $actions) {
            $count = $this->db->table('module_permissions')
                ->where('role', $role)
                ->where('module_key', $module_key)
                ->countAllResults();

            $data = [
                'role'       => $role,
                'module_key' => $module_key,
                'can_view'   => 0,
                'can_add'    => 0,
                'can_edit'   => 0,
                'can_delete' => 0
            ];

            if (isset($actions['view'])) {
                $data['can_view'] = (int) $actions['view'];
            }
            if (isset($actions['add'])) {
                $data['can_add'] = (int) $actions['add'];
            }
            if (isset($actions['edit'])) {
                $data['can_edit'] = (int) $actions['edit'];
            }
            if (isset($actions['delete'])) {
                $data['can_delete'] = (int) $actions['delete'];
            }

            if ($count > 0) {
                $this->db->table('module_permissions')
                    ->where('role', $role)
                    ->where('module_key', $module_key)
                    ->update($data);
            } else {
                $this->db->table('module_permissions')->insert($data);
            }
        }
        return true;
    }
}
