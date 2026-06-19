<?php

namespace App\Models;

class App_model
{
    public $db;
    // Constructor to initialize dependencies and references.
    function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    // Fetches user names only for the given IDs; avoids loading the full users table.
    private function userNameBatch(array $ids)
    {
        static $cache = [];
        $ids     = array_unique(array_filter(array_map('strval', $ids)));
        $missing = array_values(array_diff($ids, array_keys($cache)));
        if (!empty($missing)) {
            $rows = $this->db->table('users')
                ->select('user_id, name')
                ->whereIn('user_id', $missing)
                ->get()->getResultArray();
            foreach ($rows as $r) {
                $cache[(string) $r['user_id']] = (string) $r['name'];
            }
            foreach ($missing as $id) {
                if (!isset($cache[$id])) {
                    $cache[$id] = '';
                }
            }
        }
        $out = [];
        foreach ($ids as $id) {
            $cacheVal = '';
            if (isset($cache[$id])) {
                $cacheVal = $cache[$id];
            }
            $out[$id] = $cacheVal;
        }
        return $out;
    }

    // Kept for callers that need the full map (activity log formatting, etc.).
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

    // Returns a map of project IDs to names.
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

    // Returns a map of flow IDs to names.
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

    // Returns a map of state IDs to names.
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

    // Calculates ticket counts per state for a flow.
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

    // Dynamically applies project isolation based on user assignments if user_projects table exists
    private function applyProjectScope($q, $alias = 'projects', $userPk = null, $isAdmin = false)
    {
        if ($isAdmin === true || empty($userPk)) {
            return;
        }
        static $tableExists = null;
        if ($tableExists === null) {
            $tableExists = $this->db->tableExists('user_projects');
        }
        if ($tableExists) {
            $q->join('user_projects up', 'up.project_id = ' . $alias . '.id AND up.user_id = ' . $this->db->escape((string)$userPk), 'inner');
        }
    }

    // Fetches all projects from the database.
    public function projectGetAll($userPk = null, $isAdmin = false)
    {
        $q = $this->db->table('projects p')->select('p.*')->where('p.deleted_at', null);
        $this->applyProjectScope($q, 'p', $userPk, $isAdmin);
        $rows = $q->orderBy('p.created_at', 'desc')->get()->getResultArray();
        $nameMap = $this->userNameBatch(array_column($rows, 'created_by'));
        $out = [];
        foreach ($rows as $r) {
            $r['created_by_name'] = '';
            if (isset($nameMap[(string) $r['created_by']])) {
                $r['created_by_name'] = $nameMap[(string) $r['created_by']];
            }
            $out[] = $r;
        }
        return $out;
    }

    // Fetches a single project by ID.
    public function projectGetById($id)
    {
        return $this->db->table('projects')->where('id', (int) $id)->where('deleted_at', null)->get()->getRowArray();
    }

    // Fetches all active projects.
    public function projectGetActive($userPk = null, $isAdmin = false)
    {
        $q = $this->db->table('projects p')->select('p.*')->where('p.status', 'active')->where('p.deleted_at', null);
        $this->applyProjectScope($q, 'p', $userPk, $isAdmin);
        return $q->orderBy('p.name', 'asc')->get()->getResultArray();
    }

    // Inserts a new project into the database.
    public function projectSave($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->table('projects')->insert($data);
        $id = $this->db->insertID();
        log_message('debug', "pview alert >> project save: query=[" . $this->db->getLastQuery() . "], new_id=[" . $id . "]");
        return $id;
    }

    // Updates project details in the database.
    public function projectUpdate($id, $data)
    {
        $ok = $this->db->table('projects')->where('id', (int) $id)->update($data);
        log_message('debug', "pview alert >> project update: query=[" . $this->db->getLastQuery() . "], id=[" . $id . "], ok=[" . (int) $ok . "]");
        return $ok;
    }

    // Marks a project as deleted.
    public function projectSoftDelete($id)
    {
        $id = (int) $id;
        $now = date('Y-m-d H:i:s');
        $ok = $this->db->table('projects')->where('id', $id)->update([
            'deleted_at' => $now,
            'status'     => 'inactive',
        ]);

        $this->db->table('flows')->where('project_id', $id)->update([
            'deleted_at' => $now,
            'status'     => 'inactive',
        ]);

        $this->db->table('alert_definitions')->where('project_id', $id)->update([
            'is_active' => 0,
        ]);

        $this->db->table('api_keys')->where('project_id', $id)->update([
            'is_active' => 0,
        ]);

        log_message('debug', "pview alert >> project softDelete (cascaded): query=[" . $this->db->getLastQuery() . "], id=[" . $id . "]");
        return $ok;
    }

    // Counts the number of active projects.
    public function projectCountActive($userPk = null, $isAdmin = false)
    {
        $q = $this->db->table('projects p')->where('p.status', 'active')->where('p.deleted_at', null);
        $this->applyProjectScope($q, 'p', $userPk, $isAdmin);
        return (int) $q->countAllResults();
    }

    // Returns true when an active project with this name already exists ($ignoreId skips the row being edited).
    public function projectNameExists($name, $ignoreId = 0)
    {
        $q = $this->db->table('projects')->where('name', (string) $name)->where('deleted_at', null);
        if ((int) $ignoreId > 0) {
            $q->where('id !=', (int) $ignoreId);
        }
        return $q->countAllResults() > 0;
    }

    // Fetches all workflow paths.
    public function flowGetAll()
    {
        $rows        = $this->db->table('flows')->where('deleted_at', null)->orderBy('created_at', 'desc')->get()->getResultArray();
        $projectMap  = $this->projectNameMap();
        $nameMap     = $this->userNameBatch(array_column($rows, 'created_by'));
        $stateCounts = $this->flowStateCounts();

        $out = [];
        foreach ($rows as $r) {
            $r['project_name'] = '';
            if (isset($projectMap[(int) $r['project_id']])) {
                $r['project_name'] = $projectMap[(int) $r['project_id']];
            }
            $r['created_by_name'] = '';
            if (isset($nameMap[(string) $r['created_by']])) {
                $r['created_by_name'] = $nameMap[(string) $r['created_by']];
            }
            $r['state_count'] = 0;
            if (isset($stateCounts[(int) $r['id']])) {
                $r['state_count'] = $stateCounts[(int) $r['id']];
            }
            $out[] = $r;
        }
        return $out;
    }

    // Fetches a single workflow by ID.
    public function flowGetById($id)
    {
        return $this->db->table('flows')->where('id', (int) $id)->where('deleted_at', null)->get()->getRowArray();
    }

    // Fetches all active workflows.
    public function flowGetActive()
    {
        return $this->db->table('flows')->where('status', 'active')->where('deleted_at', null)->orderBy('name', 'asc')->get()->getResultArray();
    }

    // Fetches workflows configured for a specific project.
    public function flowGetByProject($project_id)
    {
        return $this->db->table('flows')->where('project_id', (int) $project_id)->where('status', 'active')->where('deleted_at', null)->orderBy('name', 'asc')->get()->getResultArray();
    }

    // Saves a new workflow to the database.
    public function flowSave($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->table('flows')->insert($data);
        $id = $this->db->insertID();
        log_message('debug', "pview alert >> flow save: query=[" . $this->db->getLastQuery() . "], new_id=[" . $id . "]");
        return $id;
    }

    // Updates workflow details in the database.
    public function flowUpdate($id, $data)
    {
        $ok = $this->db->table('flows')->where('id', (int) $id)->update($data);
        log_message('debug', "pview alert >> flow update: query=[" . $this->db->getLastQuery() . "], id=[" . $id . "], ok=[" . (int) $ok . "]");
        return $ok;
    }

    // Marks a workflow as deleted.
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

        log_message('debug', "pview alert >> flow softDelete (cascaded): query=[" . $this->db->getLastQuery() . "], id=[" . $id . "]");
        return $ok;
    }

    // Counts active workflows.
    public function flowCountActive()
    {
        return (int) $this->db->table('flows')->where('status', 'active')->where('deleted_at', null)->countAllResults();
    }

    // Returns true when an active flow with this name already exists within the same project.
    public function flowNameExists($name, $project_id, $ignoreId = 0)
    {
        $q = $this->db->table('flows')->where('name', (string) $name)->where('project_id', (int) $project_id)->where('deleted_at', null);
        if ((int) $ignoreId > 0) {
            $q->where('id !=', (int) $ignoreId);
        }
        return $q->countAllResults() > 0;
    }

    // Fetches all states for a workflow.
    public function stateGetAll($flow_id)
    {
        return $this->db->table('states')->where('flow_id', (int) $flow_id)->orderBy('sort_order', 'asc')->orderBy('id', 'asc')->get()->getResultArray();
    }

    // Fetches a specific state by ID.
    public function stateGetById($id)
    {
        return $this->db->table('states')->where('id', (int) $id)->get()->getRowArray();
    }

    // Returns the is_initial state; falls back to first by sort_order.
    public function stateGetInitial($flow_id)
    {
        $row = $this->db->table('states')->where('flow_id', (int) $flow_id)->where('is_initial', 1)->get()->getRowArray();
        if (!empty($row)) {
            return $row;
        }
        return $this->db->table('states')->where('flow_id', (int) $flow_id)->orderBy('sort_order', 'asc')->orderBy('id', 'asc')->get()->getRowArray();
    }

    // Returns transitions from a state, optionally filtered by type ('forward'|'backward'|'rework'|null).
    public function stateGetTransitions($flow_id, $from_state_id, $type = null)
    {
        $q = $this->db->table('state_transitions st')
            ->select('st.*, s.name AS to_state_name, s.is_final AS to_is_final')
            ->join('states s', 's.id = st.to_state_id', 'left')
            ->where('st.flow_id', (int) $flow_id)
            ->where('st.from_state_id', (int) $from_state_id)
            ->orderBy('st.transition_type', 'asc')
            ->orderBy('st.sort_order', 'asc')
            ->orderBy('st.id', 'asc');
        if ($type !== null) {
            $q->where('st.transition_type', $type);
        }
        return $q->get()->getResultArray();
    }

    // Returns all transitions for a flow (used by the vis-network diagram renderer).
    public function stateGetAllTransitions($flow_id)
    {
        return $this->db->table('state_transitions')
            ->where('flow_id', (int) $flow_id)
            ->orderBy('transition_type', 'asc')
            ->orderBy('sort_order', 'asc')
            ->get()->getResultArray();
    }

    // Inserts or updates a state transition. Returns the row id.
    public function stateTransitionSave($data)
    {
        $flowIdVal = 0;
        if (isset($data['flow_id'])) {
            $flowIdVal = $data['flow_id'];
        }
        $data['flow_id'] = (int) $flowIdVal;

        $fromStateIdVal = 0;
        if (isset($data['from_state_id'])) {
            $fromStateIdVal = $data['from_state_id'];
        }
        $data['from_state_id'] = (int) $fromStateIdVal;

        $toStateIdVal = 0;
        if (isset($data['to_state_id'])) {
            $toStateIdVal = $data['to_state_id'];
        }
        $data['to_state_id'] = (int) $toStateIdVal;

        $transType = '';
        if (isset($data['transition_type'])) {
            $transType = (string) $data['transition_type'];
        }
        if (!in_array($transType, ['forward', 'backward', 'rework'], true)) {
            $transType = 'forward';
        }
        $data['transition_type'] = $transType;

        $requiresCommentVal = 0;
        if (isset($data['requires_comment'])) {
            $requiresCommentVal = $data['requires_comment'];
        }
        $data['requires_comment'] = (int) $requiresCommentVal;

        $sortOrderVal = 0;
        if (isset($data['sort_order'])) {
            $sortOrderVal = $data['sort_order'];
        }
        $data['sort_order'] = (int) $sortOrderVal;
        if (!empty($data['id'])) {
            $id = (int) $data['id'];
            unset($data['id'], $data['created_at']);
            $this->db->table('state_transitions')->where('id', $id)->update($data);
            return $id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->table('state_transitions')->insert($data);
        return $this->db->insertID();
    }

    // Deletes a transition path between states.
    public function stateTransitionDelete($id)
    {
        $this->db->table('state_transitions')->where('id', (int) $id)->delete();
        return $this->db->affectedRows() > 0;
    }

    // Deletes backward transitions originating from a state (forward movement is implicit via sort_order).
    public function stateDeleteFromTransitions($state_id)
    {
        $this->db->table('state_transitions')
            ->where('from_state_id', (int) $state_id)
            ->where('transition_type', 'backward')
            ->delete();
    }

    // Deletes all transitions involving a state (called before removing the state itself).
    public function stateTransitionDeleteForState($state_id)
    {
        $state_id = (int) $state_id;
        $this->db->table('state_transitions')
            ->groupStart()
            ->where('from_state_id', $state_id)
            ->orWhere('to_state_id', $state_id)
            ->groupEnd()
            ->delete();
    }

    // Fetches sub-states or child states.
    public function stateGetChildren($flow_id, $parent_state_id)
    {
        $rows = $this->db->table('state_transitions st')
            ->select('s.*')
            ->join('states s', 's.id = st.to_state_id', 'inner')
            ->where('st.flow_id', $flow_id)
            ->where('st.from_state_id', $parent_state_id)
            ->where('st.transition_type', 'forward')
            ->orderBy('st.sort_order', 'asc')
            ->orderBy('st.id', 'asc')
            ->get()->getResultArray();
        if (!empty($rows)) {
            return $rows;
        }

        $children = $this->db->table('states')
            ->where('flow_id', $flow_id)
            ->where('parent_state_id', $parent_state_id)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()->getResultArray();
        if (!empty($children)) {
            return $children;
        }

        $treeFlow = $this->db->table('states')
            ->where('flow_id', $flow_id)
            ->where('parent_state_id IS NOT NULL', null, false)
            ->countAllResults() > 0;

        $current = $this->stateGetById($parent_state_id);
        if (empty($current)) {
            return [];
        }

        if ($treeFlow) {
            if (!empty($current['is_final'])) {
                return [];
            }
            $closing = $this->db->table('states')
                ->where('flow_id', $flow_id)
                ->where('is_final', 1)
                ->limit(1)
                ->get()->getRowArray();
            if ($closing) {
                return [$closing];
            }
            return [];
        }

        if (!empty($current['is_final'])) {
            return [];
        }
        $next = $this->db->table('states')
            ->where('flow_id', (int) $flow_id)
            ->groupStart()
            ->where('sort_order >', (int) $current['sort_order'])
            ->orGroupStart()
            ->where('sort_order', (int) $current['sort_order'])
            ->where('id >', (int) $parent_state_id)
            ->groupEnd()
            ->groupEnd()
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->limit(1)
            ->get()->getRowArray();
        if ($next) {
            return [$next];
        }
        return [];
    }

    // Saves a state configuration to the database.
    public function stateSave($data)
    {
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
            log_message('debug', "pview alert >> state save (update): query=[" . $this->db->getLastQuery() . "], id=[" . $id . "]");
            return $id;
        }
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
        log_message('debug', "pview alert >> state save (insert): query=[" . $this->db->getLastQuery() . "], new_id=[" . $newId . "]");
        return $newId;
    }

    // Refuses deletion if the state has forward transitions or live tickets. Returns ['ok' => bool, 'reason' => string].
    public function stateDelete($id)
    {
        $id = (int) $id;
        $state = $this->stateGetById($id);
        if (empty($state)) {
            return ['ok' => false, 'reason' => 'State not found'];
        }
        $childCount = $this->db->table('state_transitions')
            ->where('from_state_id', $id)
            ->where('transition_type', 'forward')
            ->countAllResults();
        if ($childCount === 0) {
            $childCount = $this->db->table('states')->where('parent_state_id', $id)->countAllResults();
        }
        if ($childCount > 0) {
            log_message('debug', "pview alert >> state delete REJECTED (has forward transitions): id=[" . $id . "], children=[" . $childCount . "]");
            return ['ok' => false, 'reason' => 'State has ' . $childCount . ' forward transition(s) from it. Remove them first.'];
        }
        $ticketCount = $this->db->table('tickets')->where('current_state_id', $id)->countAllResults();
        if ($ticketCount > 0) {
            log_message('debug', "pview alert >> state delete REJECTED (tickets present): id=[" . $id . "], tickets=[" . $ticketCount . "]");
            return ['ok' => false, 'reason' => 'State is referenced by ' . $ticketCount . ' ticket(s). Move or close them first.'];
        }
        $this->stateTransitionDeleteForState($id);
        $this->db->table('states')->where('id', $id)->delete();
        $this->db->table('escalation_matrix')->where('state_id', $id)->delete();
        log_message('debug', "pview alert >> state delete OK (cascaded to escalation_matrix): query=[" . $this->db->getLastQuery() . "], id=[" . $id . "]");
        return ['ok' => true, 'reason' => ''];
    }

    // Returns state IDs reachable via forward transitions from $state_id (used for cycle detection).
    public function stateGetDescendantIds($flow_id, $state_id)
    {
        $flow_id  = (int) $flow_id;
        $state_id = (int) $state_id;

        // Build forward-adjacency map from transitions table.
        $transRows = $this->db->table('state_transitions')
            ->select('from_state_id, to_state_id')
            ->where('flow_id', $flow_id)
            ->where('transition_type', 'forward')
            ->get()->getResultArray();
        $fwdMap = [];
        foreach ($transRows as $t) {
            $fwdMap[(int) $t['from_state_id']][] = (int) $t['to_state_id'];
        }

        $allStates = $this->db->table('states')
            ->select('id, parent_state_id')
            ->where('flow_id', $flow_id)
            ->get()->getResultArray();
        foreach ($allStates as $row) {
            $pid = 0;
            if (isset($row['parent_state_id'])) {
                $pid = (int) $row['parent_state_id'];
            }
            if ($pid > 0 && !isset($fwdMap[$pid])) {
                $fwdMap[$pid][] = (int) $row['id'];
            }
        }

        $out   = [];
        $stack = [$state_id];
        $guard = 0;
        while (!empty($stack) && $guard < 1000) {
            $guard++;
            $current = array_pop($stack);
            if (!empty($fwdMap[$current])) {
                foreach ($fwdMap[$current] as $kid) {
                    if (!isset($out[$kid])) {
                        $out[$kid] = true;
                        $stack[] = $kid;
                    }
                }
            }
        }
        return array_keys($out);
    }

    // Clears is_initial on all other states so only one entry point exists per flow.
    public function stateClearOtherInitial($flow_id, $keep_state_id = 0)
    {
        $q = $this->db->table('states')
            ->where('flow_id', (int) $flow_id);
        if ((int) $keep_state_id > 0) {
            $q->where('id !=', (int) $keep_state_id);
        }
        $q->update(['is_initial' => 0]);
    }

    // Clears is_final on all other states so only one terminal state exists per flow.
    public function stateClearOtherFinal($flow_id, $keep_state_id = 0)
    {
        $q = $this->db->table('states')
            ->where('flow_id', (int) $flow_id);
        if ((int) $keep_state_id > 0) {
            $q->where('id !=', (int) $keep_state_id);
        }
        $q->update(['is_final' => 0]);
    }

    // Updates state sequence order.
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
            log_message('debug', "pview alert >> state reorder REJECTED for flow_id=[" . $flow_id . "] (state ids do not all belong to flow)");
            return false;
        }
        $i = 1;
        foreach ($ids as $id) {
            $this->db->table('states')
                ->where('id', $id)
                ->where('flow_id', $flow_id)
                ->update(['sort_order' => $i++]);
        }
        log_message('debug', "pview alert >> state reorder OK: flow_id=[" . $flow_id . "], count=[" . count($ids) . "]");
        return true;
    }

    // Decodes the JSON user_id list for the given level from a state row.
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
        return array_values(array_map('strval', $arr));
    }

    // Fetches all alerts.
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

    // Fetches an alert by ID.
    public function alertGetById($id)
    {
        return $this->db->table('alert_definitions')->where('id', (int) $id)->get()->getRowArray();
    }

    // Saves a new alert configuration.
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
        log_message('debug', "pview alert >> alertdef save: query=[" . $this->db->getLastQuery() . "], new_id=[" . $id . "]");
        return $id;
    }

    // Updates alert configuration.
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
        log_message('debug', "pview alert >> alertdef update: query=[" . $this->db->getLastQuery() . "], id=[" . $id . "], ok=[" . (int) $ok . "]");
        return $ok;
    }

    // Deactivates an alert.
    public function alertDeactivate($id)
    {
        $ok = $this->db->table('alert_definitions')->where('id', (int) $id)->update(['is_active' => 0]);
        log_message('debug', "pview alert >> alertdef deactivate: query=[" . $this->db->getLastQuery() . "], id=[" . $id . "]");
        return $ok;
    }

    // Fetches all escalation configurations.
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

    // Saves or updates an escalation rule.
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
        $existing = $this->db->table('escalation_matrix')
            ->where('flow_id', (int) $data['flow_id'])
            ->where('state_id', (int) $data['state_id'])
            ->where('level', (int) $data['level'])
            ->get()->getRowArray();
        if (!empty($existing)) {
            $this->db->table('escalation_matrix')->where('id', (int) $existing['id'])->update($data);
            $id = (int) $existing['id'];
            log_message('debug', "pview alert >> escalation update: query=[" . $this->db->getLastQuery() . "], id=[" . $id . "]");
            return $id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['created_by'] = (string) session('user_id');
        $this->db->table('escalation_matrix')->insert($data);
        $id = $this->db->insertID();
        log_message('debug', "pview alert >> escalation save: query=[" . $this->db->getLastQuery() . "], new_id=[" . $id . "]");
        return $id;
    }

    // Deletes an escalation rule.
    public function escalationDelete($id)
    {
        $ok = $this->db->table('escalation_matrix')->where('id', (int) $id)->delete();
        log_message('debug', "pview alert >> escalation delete: query=[" . $this->db->getLastQuery() . "], id=[" . $id . "]");
        return $ok;
    }

    // Fetches all API keys.
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

    // Fetches an API key by ID.
    public function apiKeyGetById($id)
    {
        return $this->db->table('api_keys')->where('id', (int) $id)->get()->getRowArray();
    }

    // Returns the API key row only when the bound project is active; rejects keys for archived projects.
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

    // Generates a new API key.
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
        log_message('debug', "pview alert >> api key generated: project_id=[" . $project_id . "]");
        return $key;
    }

    // Enables or disables an API key.
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
        log_message('debug', "pview alert >> api key toggle: id=[" . $id . "], new_state=[" . $newState . "]");
        return true;
    }

    // Updates the last used timestamp for an API key.
    public function apiKeyTouchLastUsed($id)
    {
        $this->db->table('api_keys')->where('id', (int) $id)->update(['last_used' => date('Y-m-d H:i:s')]);
    }

    // Base SELECT with JOINs for all ticket list/detail queries.
    private function ticketSelect()
    {
        return $this->db->table('tickets t')->select("t.*,p.name AS project_name,f.name AS flow_name,s.name AS state_name,s.is_final AS state_is_final,s.l1_tat_minutes, s.l2_tat_minutes, s.l3_tat_minutes, s.l4_tat_minutes, s.l1_user_ids, s.l2_user_ids, s.l3_user_ids, s.l4_user_ids,ua.name AS assignee_name,ur.name AS raised_by_name", false)
            ->join('projects p', 'p.id = t.project_id', 'left')
            ->join('flows f',    'f.id = t.flow_id',    'left')
            ->join('states s',   's.id = t.current_state_id', 'left')
            ->join('users ua',   'ua.user_id = t.current_assignee', 'left')
            ->join('users ur',   'ur.user_id = t.raised_by', 'left');
    }

    // Fetches a ticket by its alarm ID.
    public function ticketGetByAlarm($alarm_id)
    {
        return $this->ticketSelect()->where('t.alarm_id', $alarm_id)->get()->getRowArray();
    }

    // Fetches recent tickets.
    public function ticketRecent($limit = 5, $userPk = null, $isAdmin = false, $projectId = 0)
    {
        $q = $this->ticketSelect();
        $this->applyUserScope($q, 't', $userPk, $isAdmin, 's');
        if ((int) $projectId > 0) {
            $q->where('t.project_id', (int) $projectId);
        }
        // Active tickets only — resolved/closed need no action.
        $q->whereIn('t.status', ['open', 'in_progress', 'escalated']);
        // Urgency order: escalated first → in_progress → open;
        // within each group oldest state_entered_at first (soonest TAT breach).
        $q->orderBy("CASE t.status WHEN 'escalated' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END", 'ASC', false)
            ->orderBy('t.state_entered_at', 'ASC');
        return $q->limit((int) $limit)->get()->getResultArray();
    }

    // Adds user-scope WHERE clauses to a ticket query (raised_by/assignee/level pools). No-op for admins.
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
            ->groupEnd();
    }

    // Applies search and filter conditions to ticket queries.
    private function ticketApplyFilters($q, $filters)
    {
        if (!empty($filters['project_id'])) {
            $q->where('t.project_id', (int) $filters['project_id']);
        }
        if (!empty($filters['flow_id'])) {
            $q->where('t.flow_id', (int) $filters['flow_id']);
        }
        if (!empty($filters['status'])) {
            // 'active' is a convenience alias for open|in_progress|escalated (used by KPI card links).
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
        if (!empty($filters['f_from'])) {
            $q->where('t.created_at >=', $filters['f_from'] . ' 00:00:00');
        }
        if (!empty($filters['f_to'])) {
            $q->where('t.created_at <=', $filters['f_to'] . ' 23:59:59');
        }
        if (!empty($filters['user_id'])) {
            $uid = (string) $filters['user_id'];
            $jsonNeedle = $this->db->escape(json_encode($uid));
            $q->groupStart()
                ->where('t.raised_by', $uid)
                ->orWhere('t.current_assignee', $uid)
                ->orWhere("JSON_CONTAINS(s.l1_user_ids, {$jsonNeedle})", null, false)
                ->orWhere("JSON_CONTAINS(s.l2_user_ids, {$jsonNeedle})", null, false)
                ->orWhere("JSON_CONTAINS(s.l3_user_ids, {$jsonNeedle})", null, false)
                ->orWhere("JSON_CONTAINS(s.l4_user_ids, {$jsonNeedle})", null, false)
                ->groupEnd();
        }
    }

    // Uses FULLTEXT MATCH...AGAINST for terms >= 3 chars; falls back to LIKE for shorter terms.
    // Returns true when FULLTEXT was applied so the caller can sort by relevance score.
    private function applyTicketSearch($q, $search)
    {
        if (strlen($search) >= 3) {
            $escaped = $this->db->escapeString($search);
            $q->groupStart()
                ->where("MATCH(t.alarm_id, t.title, t.description) AGAINST ('{$escaped}' IN BOOLEAN MODE)", null, false)
                ->orLike('p.name',  $search)
                ->orLike('f.name',  $search)
                ->orLike('s.name',  $search)
                ->orLike('ua.name', $search)
                ->groupEnd();
            return true;
        }
        $q->groupStart()
            ->like('t.alarm_id', $search)
            ->orLike('t.title',  $search)
            ->orLike('p.name',   $search)
            ->orLike('f.name',   $search)
            ->orLike('s.name',   $search)
            ->orLike('ua.name',  $search)
            ->groupEnd();
        return false;
    }

    // Fetches all tickets matching filters.
    public function ticketGetAll($filters = [])
    {
        $q = $this->ticketSelect();
        $this->ticketApplyFilters($q, $filters);
        if (!empty($filters['search'])) {
            $q->groupStart()->like('t.alarm_id', $filters['search'])->orLike('t.title',  $filters['search'])->groupEnd();
        }
        return $q->orderBy('t.created_at', 'desc')->get()->getResultArray();
    }

    // Fetches paginated tickets for server-side DataTable.
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

        $scopeUserPk = null;
        $scopeIsAdmin = false;
        if (isset($args['scope_user_pk'])) {
            $scopeUserPk = $args['scope_user_pk'];
        }
        if (isset($args['scope_is_admin'])) {
            $scopeIsAdmin = (bool) $args['scope_is_admin'];
        }

        $q = $this->ticketSelect();
        $this->ticketApplyFilters($q, $filters);

        $search      = '';
        $usedFulltext = false;
        if (!empty($args['search'])) {
            $search = (string) $args['search'];
        }
        if ($search !== '') {
            $usedFulltext = $this->applyTicketSearch($q, $search);
        }

        $this->applyUserScope($q, 't', $scopeUserPk, $scopeIsAdmin, 's');

        if ($usedFulltext) {
            $escapedSearch = $this->db->escapeString($search);
            $q->orderBy("MATCH(t.alarm_id, t.title, t.description) AGAINST ('{$escapedSearch}' IN BOOLEAN MODE)", 'desc', false);
        }
        $rows = $q->orderBy($orderCol, $orderDir)->limit($length, $start)->get()->getResultArray();

        $totalAll      = $this->ticketCountAll($scopeUserPk, $scopeIsAdmin);
        $totalFiltered = $this->ticketCountFiltered($filters, $search, $scopeUserPk, $scopeIsAdmin);

        log_message('debug', "pview alert >> ticket dataTable page: query=[" . $this->db->getLastQuery() . "], start=[" . $start . "], length=[" . $length . "], rows=[" . count($rows) . "], total_all=[" . $totalAll . "], total_filtered=[" . $totalFiltered . "]");

        return [
            'rows'           => $rows,
            'total_all'      => $totalAll,
            'total_filtered' => $totalFiltered,
        ];
    }

    // Returns the total ticket count visible to the caller (scoped for non-admins via applyUserScope).
    public function ticketCountAll($userPk = null, $isAdmin = false)
    {
        $q = $this->db->table('tickets');
        $this->applyUserScope($q, 'tickets', $userPk, $isAdmin);
        return (int) $q->countAllResults();
    }

    // Counts filtered tickets for pagination.
    public function ticketCountFiltered($filters, $search = '', $userPk = null, $isAdmin = false)
    {
        $q = $this->db->table('tickets t')
            ->join('projects p', 'p.id = t.project_id',          'left')
            ->join('flows f',    'f.id = t.flow_id',             'left')
            ->join('states s',   's.id = t.current_state_id',    'left')
            ->join('users ua',   'ua.user_id = t.current_assignee', 'left');
        $this->ticketApplyFilters($q, $filters);
        if ($search !== '') {
            $this->applyTicketSearch($q, $search);
        }
        $this->applyUserScope($q, 't', $userPk, $isAdmin, 's');
        return (int) $q->countAllResults();
    }

    // Counts tickets grouped by status.
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

    // Counts active tickets grouped by alert type.
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

    // Fetches ticket creation counts over a day range for trend charts.
    public function ticketTrendByRange($startDate, $endDate, $userPk = null, $isAdmin = false, $projectId = 0)
    {
        $startObj = new \DateTime($startDate);
        $endObj   = new \DateTime($endDate);

        if ($startObj > $endObj) {
            $temp = $startObj;
            $startObj = $endObj;
            $endObj = $temp;
        }

        $diff = $startObj->diff($endObj);
        $days = (int) $diff->days + 1;

        $labels  = [];
        $buckets = [];
        $fmt = 'd-M';
        if ($days <= 14) {
            $fmt = 'D';
        }

        $current = clone $startObj;
        for ($i = 0; $i < $days; $i++) {
            $dayKey    = $current->format('Y-m-d');
            $labels[]  = $current->format($fmt);
            $buckets[$dayKey] = 0;
            $current->modify('+1 day');
        }

        $startLimit = $startObj->format('Y-m-d') . ' 00:00:00';
        $endLimit   = $endObj->format('Y-m-d') . ' 23:59:59';
        $q = $this->db->table('tickets')->select('DATE(tickets.created_at) AS day_key, COUNT(*) AS n', false)->where('tickets.created_at >=', $startLimit)->where('tickets.created_at <=', $endLimit);
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
            'dates'  => array_keys($buckets),
        ];
    }

    // Counts tickets that have breached their Turnaround Time.
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
            $from  .= " LEFT JOIN states s ON s.id = t.current_state_id";
            $where .= " AND (t.raised_by = ?"
                . " OR t.current_assignee = ?"
                . " OR JSON_CONTAINS(s.l1_user_ids, ?)"
                . " OR JSON_CONTAINS(s.l2_user_ids, ?)"
                . " OR JSON_CONTAINS(s.l3_user_ids, ?)"
                . " OR JSON_CONTAINS(s.l4_user_ids, ?))";
            $uid     = (string) $userPk;
            $uidJson = json_encode($uid);
            $params = [$uid, $uid, $uidJson, $uidJson, $uidJson, $uidJson];
        }

        $sql = $select . $from . " WHERE " . $where;

        try {
            $row = $this->db->query($sql, $params)->getRow();
        } catch (\Throwable $e) {
            log_message('debug', "pview alert >> ticketCountActionable() failed: " . $e->getMessage());
            return ['total' => 0, 'escalated' => 0, 'critical_open' => 0];
        }

        $totalVal = 0;
        $escalatedVal = 0;
        $criticalOpenVal = 0;
        if ($row) {
            $totalVal = (int) $row->total;
            $escalatedVal = (int) $row->escalated;
            $criticalOpenVal = (int) $row->critical_open;
        }

        return [
            'total'         => $totalVal,
            'escalated'     => $escalatedVal,
            'critical_open' => $criticalOpenVal,
        ];
    }

    // Saves a new ticket.
    public function ticketSave($data)
    {
        $data['created_at']       = date('Y-m-d H:i:s');
        $data['state_entered_at'] = date('Y-m-d H:i:s');
        $this->db->table('tickets')->insert($data);
        $id = $this->db->insertID();
        $alarmIdVal = '';
        if (isset($data['alarm_id'])) {
            $alarmIdVal = $data['alarm_id'];
        }
        log_message('debug', "pview alert >> ticket save: query=[" . $this->db->getLastQuery() . "], new_id=[" . $id . "], alarm_id=[" . $alarmIdVal . "]");
        return $id;
    }

    // Updates ticket details.
    public function ticketUpdate($id, $data)
    {
        $ok = $this->db->table('tickets')->where('id', (int) $id)->update($data);
        log_message('debug', "pview alert >> ticket update: query=[" . $this->db->getLastQuery() . "], id=[" . $id . "], ok=[" . (int) $ok . "]");
        return $ok;
    }

    // Transition a ticket to another workflow state.
    public function ticketMoveToState($ticket_id, $new_state_id)
    {
        $this->db->transStart();
        // FOR UPDATE prevents concurrent requests from producing duplicate state moves.
        $current = $this->db->query(
            'SELECT id, status FROM tickets WHERE id = ? FOR UPDATE',
            [(int) $ticket_id]
        )->getRowArray();
        $newStatus = 'in_progress';
        if ($current && (string) $current['status'] === 'escalated') {
            $newStatus = 'escalated';
        }
        $this->db->table('tickets')->where('id', (int) $ticket_id)->update([
            'current_state_id'   => (int) $new_state_id,
            'current_level'      => 1,
            'state_entered_at'   => date('Y-m-d H:i:s'),
            'status'             => $newStatus,
            // Reset so the next level is eligible for its 80% TAT warning.
            'last_tat_warn_level' => 0,
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);
        $this->db->transComplete();
        log_message('debug', "pview alert >> ticket moveToState: ticket_id=[{$ticket_id}], new_state_id=[{$new_state_id}]");
    }

    // Escalates a ticket's level.
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
            'last_tat_warn_level' => 0,
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);
        log_message('debug', "pview alert >> ticket escalateLevel: query=[" . $this->db->getLastQuery() . "], ticket_id=[" . $ticket_id . "], new_level=[" . $new_level . "], status=[" . $newStatus . "]");
    }

    // Returns open/in_progress tickets for the TAT cron sweep; excludes final-state tickets.
    public function ticketActiveForTatCheck()
    {
        return $this->ticketSelect()
            ->select('COALESCE(f.tat_level_count, 4) AS tat_level_count', false)
            ->whereIn('t.status', ['open', 'in_progress'])
            ->where('(s.is_final IS NULL OR s.is_final = 0)', null, false)
            ->get()->getResultArray();
    }

    // Returns the escalation_matrix override for (flow, state, level), or null if none configured.
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
        $alertTypeVal = 'major';
        if (isset($row['alert_type'])) {
            $alertTypeVal = (string) $row['alert_type'];
        }
        return [
            'tat_minutes'  => (int) $row['escalate_after'],
            'notify_users' => $users,
            'alert_type'   => $alertTypeVal,
        ];
    }

    // Logs a user action in the ticket timeline.
    public function ticketLogAction($ticket_id, $action_type, $extra = [])
    {
        $row = array_merge([
            'ticket_id'    => (int) $ticket_id,
            'action_type'  => $action_type,
            'created_at'   => date('Y-m-d H:i:s'),
            'performed_by' => (string) session('user_id'),
        ], $extra);
        $this->db->table('ticket_actions')->insert($row);
        log_message('debug', "pview alert >> ticket action logged: ticket_id=[" . $ticket_id . "], action=[" . $action_type . "]");
    }

    // Fetches the history timeline for a ticket.
    public function ticketTimeline($ticket_id)
    {
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

    // Fetches recent notifications for a ticket.
    public function ticketRecentNotifications($ticket_id, $limit = 5)
    {
        return $this->db->table('notification_logs')->where('ticket_id', (int) $ticket_id)->orderBy('id', 'desc')->limit((int) $limit)->get()->getResultArray();
    }

    // Counts attachments for a ticket.
    public function ticketAttachmentCount($ticket_id)
    {
        return (int) $this->db->table('ticket_actions')
            ->where('ticket_id', (int) $ticket_id)
            ->where('action_type', 'attachment')
            ->countAllResults();
    }

    // Fetches an attachment record by ID.
    public function ticketGetAttachment($ticket_id, $action_id)
    {
        return $this->db->table('ticket_actions')
            ->where('id', (int) $action_id)
            ->where('ticket_id', (int) $ticket_id)
            ->where('action_type', 'attachment')
            ->get()->getRowArray();
    }

    // Fetches notification mentions for a user.
    public function notificationMentionsForUser($email, $cutoff, $limit = 50)
    {
        return $this->db->table('notification_logs nl')
            ->select('nl.id, nl.subject, nl.created_at, nl.ticket_id, t.alarm_id, t.title, t.alert_type')
            ->join('tickets t', 't.id = nl.ticket_id', 'left')
            ->where('nl.recipient_email', (string) $email)
            ->like('nl.subject', '[MENTION]', 'after')
            ->where('nl.created_at >=', $cutoff)
            ->orderBy('nl.created_at', 'desc')
            ->limit((int) $limit)
            ->get()->getResultArray();
    }

    // Returns distinct non-empty values for a given activity_logs column (allowlisted; for filter dropdowns).
    public function activityLogsDistinctValues($column)
    {
        if (!in_array($column, ['module', 'action', 'user_role'], true)) {
            return [];
        }
        $rows = $this->db->table('activity_logs')
            ->select($column)
            ->distinct()
            ->orderBy($column)
            ->get()->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            if (!empty($r[$column])) {
                $out[] = (string) $r[$column];
            }
        }
        return $out;
    }

    // Fetches projects list for filtering activity logs.
    public function activityLogsProjectsForFilter()
    {
        return $this->db->table('projects')
            ->select('id, name')
            ->where('deleted_at IS NULL')
            ->orderBy('name')
            ->get()->getResultArray();
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
        if (!in_array($scope, ['tickets', 'activity_logs'], true)) {
            $scope = 'tickets';
        }
        $row = [
            'user_id'      => (string) $userId,
            'scope'        => $scope,
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

    // Fetches all global settings.
    public function settingGetAll()
    {
        try {
            return $this->db->table('app_settings')->orderBy('setting_key', 'asc')->get()->getResultArray();
        } catch (\Throwable $e) {
            log_message('debug', "pview alert >> settingGetAll() failed: " . $e->getMessage());
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

    // Saves or updates a global setting.
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
        log_message('debug', "pview alert >> setting saved: key=[" . $key . "], len=[" . strlen((string) $value) . "]");
    }

    // Fetches projects formatted for server-side DataTable.
    public function projectsForDT($args)
    {
        $allowedCols = [
            'p.name'        => 'p.name',
            'p.description' => 'p.description',
            'p.status'      => 'p.status',
            'p.created_by'  => 'p.created_by',
            'p.created_at'  => 'p.created_at',
        ];
        $orderCol = 'p.created_at';
        if (!empty($args['order_col']) && isset($allowedCols[$args['order_col']])) {
            $orderCol = $allowedCols[$args['order_col']];
        }
        $orderDir = 'DESC';
        if (!empty($args['order_dir']) && strtolower($args['order_dir']) === 'asc') {
            $orderDir = 'ASC';
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
        $scopeUserPk = null;
        if (isset($args['scope_user_pk'])) {
            $scopeUserPk = $args['scope_user_pk'];
        }
        $scopeIsAdmin = false;
        if (isset($args['scope_is_admin'])) {
            $scopeIsAdmin = (bool) $args['scope_is_admin'];
        }

        $total = (int) $this->db->table('projects')->where('deleted_at', null)->countAllResults();

        $baseFrom = "FROM projects p ";

        static $tableExists = null;
        if ($tableExists === null) {
            $tableExists = $this->db->tableExists('user_projects');
        }
        if (!$scopeIsAdmin && !empty($scopeUserPk) && $tableExists) {
            $baseFrom .= " INNER JOIN user_projects up ON up.project_id = p.id AND up.user_id = " . $this->db->escape((string)$scopeUserPk) . " ";
        }
        $baseFrom .= " WHERE p.deleted_at IS NULL";

        $params   = [];
        if ($search !== '') {
            $like      = '%' . $search . '%';
            $baseFrom .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $params    = [$like, $like];
        }

        $countRow = $this->db->query("SELECT COUNT(*) AS cnt " . $baseFrom, $params)->getRow();
        $filtered = 0;
        if (isset($countRow->cnt)) {
            $filtered = (int) $countRow->cnt;
        }

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

    // Fetches workflows formatted for server-side DataTable.
    public function flowsForDT($args)
    {
        $allowedCols = [
            'f.name'       => 'f.name',
            'p.name'       => 'p.name',
            'f.id'         => 'f.id',
            'f.status'     => 'f.status',
            'f.created_by' => 'f.created_by',
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
        $filtered = 0;
        if (isset($countRow->cnt)) {
            $filtered = (int) $countRow->cnt;
        }

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

    // Fetches alerts formatted for server-side DataTable.
    public function alertsForDT($args)
    {
        $allowedCols = [
            'a.name'            => 'a.name',
            'p.name'            => 'p.name',
            'f.name'            => 'f.name',
            'a.alert_type'      => 'a.alert_type',
            'a.threshold_value' => 'a.threshold_value',
            'a.is_active'       => 'a.is_active',
            'a.created_at'      => 'a.created_at',
        ];
        $orderCol = 'a.created_at';
        if (!empty($args['order_col']) && isset($allowedCols[$args['order_col']])) {
            $orderCol = $allowedCols[$args['order_col']];
        }
        $orderDir = 'DESC';
        if (!empty($args['order_dir']) && strtolower($args['order_dir']) === 'asc') {
            $orderDir = 'ASC';
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
        $filtered = 0;
        if (isset($countRow->cnt)) {
            $filtered = (int) $countRow->cnt;
        }

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

    // Checks if module permissions database table exists.
    public function modulePermissionsTableExists()
    {
        $rows = $this->db->query("SHOW TABLES LIKE 'module_permissions'")->getResultArray();
        if (empty($rows)) {
            return false;
        }
        return true;
    }

    // Fetches all module permissions.
    public function modulePermissionsGetAll()
    {
        return $this->db->table('module_permissions')
            ->orderBy('role', 'asc')
            ->orderBy('module_key', 'asc')
            ->get()->getResultArray();
    }

    // Saves module permissions.
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
