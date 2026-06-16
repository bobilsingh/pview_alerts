<?php

namespace App\Controllers;

use App\Models\app_model;
use App\Models\user_model;

class App extends BaseController
{
    public $app_model;
    public $user_model;
    public $api_key_row;
    // Constructor to initialize dependencies and references.
    function __construct()
    {
        $this->app_model  = new app_model();
        $this->user_model = new user_model();
    }

    // Renders the main dashboard page with tickets summary and trends.
    public function dashboard()
    {
        check_module_access('dashboard', 'view');
        log_message('debug', "pview alert >> dashboard page open by user_id=[" . (string) logged_user_id() . "]");

        $rangesRaw = app_setting_csv('dashboard_trend_ranges', ['7', '15', '30']);
        $trendRangeOptions = [];
        foreach ($rangesRaw as $r) {
            $n = (int) $r;
            if ($n >= 1 && $n <= 365) {
                $trendRangeOptions[] = $n;
            }
        }
        if (empty($trendRangeOptions)) {
            $trendRangeOptions = [7, 15, 30];
        }
        $trendRangeOptions = array_values(array_unique($trendRangeOptions));

        $prefDefaultTrendRange = (int) user_dashboard_pref('default_trend_range', 0);
        $prefDefaultProjectId  = (int) user_dashboard_pref('default_project_id', 0);
        $prefKpiVisible        = user_dashboard_pref('kpi_visible', null);

        $trendRange = (int) $this->request->getGet('range');
        if (!in_array($trendRange, $trendRangeOptions, true)) {
            if (in_array($prefDefaultTrendRange, $trendRangeOptions, true)) {
                $trendRange = $prefDefaultTrendRange;
            } else {
                $trendRange = $trendRangeOptions[0];
            }
        }

        $role    = logged_user_role();
        $isAdmin = role_has_admin_scope($role);
        $userPk  = logged_user_id();

        $statusCounts    = $this->app_model->ticketCountByStatus($userPk, $isAdmin, $prefDefaultProjectId);
        $alertTypeCounts = $this->app_model->ticketCountByAlertTypeActive($userPk, $isAdmin, $prefDefaultProjectId);
        $trend           = $this->app_model->ticketTrendByRange($trendRange, $userPk, $isAdmin, $prefDefaultProjectId);

        $openCount = $statusCounts['open'] + $statusCounts['in_progress'];
        $openCount += $statusCounts['escalated'];

        $prefProjectName = '';
        if ($prefDefaultProjectId > 0) {
            $row = $this->app_model->projectGetById($prefDefaultProjectId);
            if (!empty($row)) {
                $prefProjectName = (string) $row['name'];
            }
        }

        $custKpiVisible = $prefKpiVisible;
        if ($custKpiVisible === null) {
            $custKpiVisible = ['open' => 1, 'critical' => 1, 'major' => 1, 'resolved' => 1];
        }

        $data = [
            'title'             => 'Dashboard',
            'projectCount'      => $this->app_model->projectCountActive($userPk, $isAdmin),
            'flowCount'         => $this->app_model->flowCountActive(),
            'openCount'         => $openCount,
            'criticalCount'     => $alertTypeCounts['critical'],
            'majorCount'        => $alertTypeCounts['major'],
            'resolvedCount'     => $statusCounts['resolved'],
            'statusCounts'      => $statusCounts,
            'alertTypeCounts'   => $alertTypeCounts,
            'trendLabels'       => $trend['labels'],
            'trendValues'       => $trend['values'],
            'trendRange'        => $trendRange,
            'trendRangeOptions' => $trendRangeOptions,
            'recentTickets'     => $this->app_model->ticketRecent(5, $userPk, $isAdmin, $prefDefaultProjectId),
            'tatBreached'       => $this->app_model->ticketCountTatBreached($userPk, $isAdmin, $prefDefaultProjectId),
            'kpiVisible'            => $prefKpiVisible,
            'prefProjectName'       => $prefProjectName,
            'custProjects'          => $this->app_model->projectGetActive($userPk, $isAdmin),
            'custDefaultProjectId'  => $prefDefaultProjectId,
            'custKpiVisible'        => $custKpiVisible,
            'custDefaultTrendRange' => $prefDefaultTrendRange,
            'custRangesInt'         => $trendRangeOptions,
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('dashboard', $data);
        echo view('templates/footer');
    }

    // Lists all projects and their configuration.
    public function projects()
    {
        check_module_access('projects', 'view');
        $data = [
            'title'    => 'Projects',
            'view'     => 'list',
            'projects' => $this->app_model->projectGetAll(logged_user_id(), role_has_admin_scope()),
            'project'  => null,
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('projects', $data);
        echo view('templates/footer');
    }

    // Renders the form to create a new project.
    public function project_add()
    {
        check_module_access('projects', 'add');
        $data = ['title' => 'Add Project', 'view' => 'form', 'project' => null];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('projects', $data);
        echo view('templates/footer');
    }

    // Saves a new project to the database.
    public function project_save()
    {
        check_module_access('projects', 'add');
        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            $this->session->setFlashdata('error', 'Name is required.');
            return redirect()->to(site_url('projects/add'));
        }
        if ($this->app_model->projectNameExists($name)) {
            $this->session->setFlashdata('error', 'A project with that name already exists.');
            return redirect()->to(site_url('projects/add'));
        }
        $newId = $this->app_model->projectSave([
            'name'        => $name,
            'description' => (string) $this->request->getPost('description'),
            'status'      => 'active',
            'created_by'  => logged_user_id(),
        ]);
        activity_log('projects', 'create', 'project', (string) $newId, 'Created project "' . $name . '"', ['name' => $name]);
        $this->session->setFlashdata('success', 'Project "' . $name . '" created.');
        return redirect()->to(site_url('projects'));
    }

    // Renders the edit form for an existing project.
    public function project_edit($id)
    {
        check_module_access('projects', 'edit');
        $project = $this->app_model->projectGetById($id);
        if (empty($project)) {
            return redirect()->to(site_url('projects'));
        }
        $data = ['title' => 'Edit Project', 'view' => 'form', 'project' => $project];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('projects', $data);
        echo view('templates/footer');
    }

    // Updates project settings in the database.
    public function project_update($id)
    {
        check_module_access('projects', 'edit');
        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            $this->session->setFlashdata('error', 'Name is required.');
            return redirect()->to(site_url('projects/edit/' . $id));
        }
        if ($this->app_model->projectNameExists($name, (int) $id)) {
            $this->session->setFlashdata('error', 'A project with that name already exists.');
            return redirect()->to(site_url('projects/edit/' . $id));
        }
        $status = $this->request->getPost('status');
        $status = or_default($status, 'active');

        $before = $this->app_model->projectGetById($id);
        $after  = [
            'name'        => $name,
            'description' => (string) $this->request->getPost('description'),
            'status'      => $status,
        ];
        $this->app_model->projectUpdate($id, $after);
        $diff = activity_diff($before, $after, ['name', 'description', 'status']);
        activity_log('projects', 'update', 'project', (string) $id, 'Updated project "' . $name . '"', $diff);
        $this->session->setFlashdata('success', 'Project "' . $name . '" updated.');
        return redirect()->to(site_url('projects'));
    }

    // Deletes a project from the database.
    public function project_delete($id)
    {
        check_module_access('projects', 'delete');
        $activeCount = $this->db->table('tickets')
            ->whereIn('status', ['open', 'in_progress', 'escalated'])
            ->where('project_id', (int) $id)
            ->countAllResults();
        if ($activeCount > 0) {
            $this->session->setFlashdata('error', 'Cannot delete: ' . $activeCount . ' active ticket(s) are linked to this project.');
            return redirect()->to(site_url('projects'));
        }
        $before = $this->app_model->projectGetById($id);
        $this->app_model->projectSoftDelete($id);
        $name = '';
        if (isset($before['name'])) {
            $name = (string) $before['name'];
        }
        activity_log('projects', 'delete', 'project', (string) $id, 'Removed project "' . $name . '"', ['name' => $name]);
        $this->session->setFlashdata('success', 'Project removed.');
        return redirect()->to(site_url('projects'));
    }

    // Lists all workflow paths.
    public function flows()
    {
        check_module_access('flows', 'view');
        $data = [
            'title'    => 'Flows',
            'view'     => 'list',
            'flows'    => $this->app_model->flowGetAll(),
            'flow'     => null,
            'projects' => [],
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('flows', $data);
        echo view('templates/footer');
    }

    // Renders the form to add a new workflow flow.
    public function flow_add()
    {
        check_module_access('flows', 'add');
        $data = [
            'title'    => 'Add Flow',
            'view'     => 'form',
            'flow'     => null,
            'projects' => $this->app_model->projectGetActive(logged_user_id(), role_has_admin_scope()),
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('flows', $data);
        echo view('templates/footer');
    }

    // Saves a new workflow flow to the database.
    public function flow_save()
    {
        check_module_access('flows', 'add');
        $name       = trim((string) $this->request->getPost('name'));
        $project_id = (int) $this->request->getPost('project_id');
        if ($name === '' || $project_id === 0) {
            $this->session->setFlashdata('error', 'Name and project are required.');
            return redirect()->to(site_url('flows/add'));
        }
        if ($this->app_model->flowNameExists($name, $project_id)) {
            $this->session->setFlashdata('error', 'A flow with that name already exists in this project.');
            return redirect()->to(site_url('flows/add'));
        }
        $tatLevelCount = (int) $this->request->getPost('tat_level_count');
        if ($tatLevelCount < 1) {
            $tatLevelCount = 4;
        }
        if ($tatLevelCount > 4) {
            $tatLevelCount = 4;
        }
        $newId = $this->app_model->flowSave([
            'name'            => $name,
            'project_id'      => $project_id,
            'status'          => 'active',
            'created_by'      => logged_user_id(),
            'tat_level_count' => $tatLevelCount,
        ]);
        activity_log('flows', 'create', 'flow', (string) $newId, 'Created flow "' . $name . '"', ['name' => $name, 'project_id' => $project_id]);
        $this->session->setFlashdata('success', 'Flow "' . $name . '" created.');
        return redirect()->to(site_url('flows'));
    }

    // Renders the edit form for a workflow flow.
    public function flow_edit($id)
    {
        check_module_access('flows', 'edit');
        $flow = $this->app_model->flowGetById($id);
        if (empty($flow)) {
            return redirect()->to(site_url('flows'));
        }
        $data = [
            'title'    => 'Edit Flow',
            'view'     => 'form',
            'flow'     => $flow,
            'projects' => $this->app_model->projectGetActive(logged_user_id(), role_has_admin_scope()),
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('flows', $data);
        echo view('templates/footer');
    }

    // Updates workflow flow details in the database.
    public function flow_update($id)
    {
        check_module_access('flows', 'edit');
        $name = trim((string) $this->request->getPost('name'));
        $project_id = (int) $this->request->getPost('project_id');
        if ($name === '' || $project_id === 0) {
            $this->session->setFlashdata('error', 'Name and project are required.');
            return redirect()->to(site_url('flows/edit/' . $id));
        }
        if ($this->app_model->flowNameExists($name, $project_id, (int) $id)) {
            $this->session->setFlashdata('error', 'A flow with that name already exists in this project.');
            return redirect()->to(site_url('flows/edit/' . $id));
        }
        $status = $this->request->getPost('status');
        $status = or_default($status, 'active');

        $tatLevelCount = (int) $this->request->getPost('tat_level_count');
        if ($tatLevelCount < 1) {
            $tatLevelCount = 4;
        }
        if ($tatLevelCount > 4) {
            $tatLevelCount = 4;
        }
        $before = $this->app_model->flowGetById($id);
        $after  = [
            'name'            => $name,
            'project_id'      => $project_id,
            'status'          => $status,
            'tat_level_count' => $tatLevelCount,
        ];
        $this->app_model->flowUpdate($id, $after);
        $diff = activity_diff($before, $after, ['name', 'project_id', 'status']);
        activity_log('flows', 'update', 'flow', (string) $id, 'Updated flow "' . $name . '"', $diff);
        $this->session->setFlashdata('success', 'Flow updated.');
        return redirect()->to(site_url('flows'));
    }

    // Deletes a workflow flow from the database.
    public function flow_delete($id)
    {
        check_module_access('flows', 'delete');
        $activeCount = $this->db->table('tickets')
            ->whereIn('status', ['open', 'in_progress', 'escalated'])
            ->where('flow_id', (int) $id)
            ->countAllResults();
        if ($activeCount > 0) {
            $this->session->setFlashdata('error', 'Cannot delete: ' . $activeCount . ' active ticket(s) are linked to this flow.');
            return redirect()->to(site_url('flows'));
        }
        $before = $this->app_model->flowGetById($id);
        $this->app_model->flowSoftDelete($id);
        $name = '';
        if (isset($before['name'])) {
            $name = (string) $before['name'];
        }
        activity_log('flows', 'delete', 'flow', (string) $id, 'Removed flow "' . $name . '"', ['name' => $name]);
        $this->session->setFlashdata('success', 'Flow removed.');
        return redirect()->to(site_url('flows'));
    }

    // Renders the state configuration page for a workflow flow.
    public function flow_states($flow_id)
    {
        check_module_access('flows', 'edit');
        $flow = $this->app_model->flowGetById($flow_id);
        if (empty($flow)) {
            return redirect()->to(site_url('flows'));
        }
        $states = $this->app_model->stateGetAll($flow_id);

        $editStateId = (int) $this->request->getGet('edit_state');
        $editState   = null;
        if ($editStateId > 0) {
            $editState = $this->app_model->stateGetById($editStateId);
            if (!empty($editState) && (int) $editState['flow_id'] !== (int) $flow_id) {
                $editState = null;
            }
        }

        $allTransitions = $this->app_model->stateGetAllTransitions((int) $flow_id);
        $stateNameMap   = array_column($states, 'name', 'id');
        $bwdLabels      = [];
        $editBwdIds     = [];
        foreach ($allTransitions as $t) {
            $transType = '';
            if (isset($t['transition_type'])) {
                $transType = $t['transition_type'];
            }
            if ($transType !== 'backward') {
                continue;
            }
            $fromId               = (int) $t['from_state_id'];
            $toStateId            = (int) $t['to_state_id'];
            $toName               = '#' . $toStateId;
            if (isset($stateNameMap[$toStateId])) {
                $toName = $stateNameMap[$toStateId];
            }
            $bwdLabels[$fromId][] = $toName;
            if ($editStateId > 0 && $fromId === $editStateId) {
                $editBwdIds[] = (int) $t['to_state_id'];
            }
        }

        $data = [
            'title'      => 'Flow States: ' . $flow['name'],
            'view'       => 'states',
            'flow'       => $flow,
            'states'     => $states,
            'users'      => $this->user_model->getActive(),
            'editState'  => $editState,
            'transitions' => $allTransitions,
            'bwdLabels'  => $bwdLabels,
            'editBwdIds' => $editBwdIds,
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('flows', $data);
        echo view('templates/footer');
    }

    // Saves or updates a workflow state.
    public function state_save()
    {
        check_module_access('flows', 'edit');

        $id      = (int) $this->request->getPost('id');
        $flow_id = (int) $this->request->getPost('flow_id');
        $name    = trim((string) $this->request->getPost('name'));
        if ($flow_id === 0 || $name === '') {
            $this->session->setFlashdata('error', 'Flow and state name are required.');
            return redirect()->to(site_url('flows/states/' . $flow_id));
        }

        $parent      = (int) $this->request->getPost('parent_state_id');
        $parentValue = null;
        if ($parent > 0) {
            $parentRow = $this->app_model->stateGetById($parent);
            if (empty($parentRow) || (int) $parentRow['flow_id'] !== $flow_id) {
                $this->session->setFlashdata('error', 'Parent state must belong to the same flow.');
                return redirect()->to(site_url('flows/states/' . $flow_id));
            }
            if ($id > 0) {
                if ($parent === $id) {
                    $this->session->setFlashdata('error', 'A state cannot be its own parent.');
                    return redirect()->to(site_url('flows/states/' . $flow_id));
                }
                $descendants = $this->app_model->stateGetDescendantIds($flow_id, $id);
                if (in_array($parent, $descendants, true)) {
                    $this->session->setFlashdata('error', 'Cannot set parent — that would create a cycle in the workflow tree.');
                    return redirect()->to(site_url('flows/states/' . $flow_id));
                }
            }
            $parentValue = $parent;
        }

        $isInitial = bool_int($this->request->getPost('is_initial'));
        $isFinal   = bool_int($this->request->getPost('is_final'));
        if ($isInitial === 1 && $isFinal === 1) {
            $this->session->setFlashdata('error', 'A state cannot be both initial and final.');
            return redirect()->to(site_url('flows/states/' . $flow_id));
        }
        if ($isInitial === 1 || $isFinal === 1) {
            $parentValue = null;
        }

        $l1Users = (array) $this->request->getPost('l1_user_ids');
        $l2Users = (array) $this->request->getPost('l2_user_ids');
        if ($isFinal !== 1 && empty($l1Users) && empty($l2Users)) {
            $this->session->setFlashdata('error', 'State must have at least one operator registered in L1 or L2 pools to prevent operational deadlocks.');
            return redirect()->to(site_url('flows/states/' . $flow_id));
        }

        $l1Tat = (int) $this->request->getPost('l1_tat_minutes');
        if ($l1Tat < 1) {
            $l1Tat = app_setting_int('default_tat_l1_minutes', 60);
        }
        $l2Tat = (int) $this->request->getPost('l2_tat_minutes');
        if ($l2Tat < 1) {
            $l2Tat = app_setting_int('default_tat_l2_minutes', 120);
        }
        $l3Tat = (int) $this->request->getPost('l3_tat_minutes');
        if ($l3Tat < 1) {
            $l3Tat = app_setting_int('default_tat_l3_minutes', 240);
        }
        $l4Tat = (int) $this->request->getPost('l4_tat_minutes');
        if ($l4Tat < 1) {
            $l4Tat = app_setting_int('default_tat_l4_minutes', 480);
        }

        $data = [
            'flow_id'         => $flow_id,
            'name'            => $name,
            'parent_state_id' => $parentValue,
            'is_initial'      => $isInitial,
            'is_final'        => $isFinal,
            'l1_user_ids'     => (array) $this->request->getPost('l1_user_ids'),
            'l1_tat_minutes'  => $l1Tat,
            'l2_user_ids'     => (array) $this->request->getPost('l2_user_ids'),
            'l2_tat_minutes'  => $l2Tat,
            'l3_user_ids'     => (array) $this->request->getPost('l3_user_ids'),
            'l3_tat_minutes'  => $l3Tat,
            'l4_user_ids'     => (array) $this->request->getPost('l4_user_ids'),
            'l4_tat_minutes'  => $l4Tat,
            'status'          => 'active',
            'created_by'      => logged_user_id(),
        ];
        if ($id > 0) {
            $data['id'] = $id;
        }
        $savedId = $this->app_model->stateSave($data);

        if ($isInitial === 1) {
            $this->app_model->stateClearOtherInitial($flow_id, (int) $savedId);
        }
        if ($isFinal === 1) {
            $this->app_model->stateClearOtherFinal($flow_id, (int) $savedId);
        }

        $rawBwdIds = (array) $this->request->getPost('backward_state_ids');
        $bwdIds = [];
        foreach ($rawBwdIds as $v) {
            $v = (int) $v;
            if ($v > 0) {
                $bwdIds[] = $v;
            }
        }
        $bwdIds = array_values($bwdIds);
        $this->app_model->stateDeleteFromTransitions((int) $savedId);
        foreach ($bwdIds as $bwdId) {
            if ($bwdId === (int) $savedId) {
                continue;
            }
            $this->app_model->stateTransitionSave([
                'flow_id'          => $flow_id,
                'from_state_id'    => (int) $savedId,
                'to_state_id'      => $bwdId,
                'transition_type'  => 'backward',
                'requires_comment' => 1,
                'created_by'       => (string) logged_user_id(),
            ]);
        }

        $isCreate = ($id <= 0);
        $stateAction = 'update_state';
        $stateMsgPrefix = 'Updated';
        if ($isCreate) {
            $stateAction = 'create_state';
            $stateMsgPrefix = 'Added';
        }
        activity_log('flows', $stateAction, 'state', (string) $savedId, $stateMsgPrefix . ' state "' . $name . '" in flow #' . $flow_id, ['flow_id' => $flow_id, 'name' => $name, 'is_initial' => $isInitial, 'is_final' => $isFinal]);

        $this->session->setFlashdata('success', 'State saved.');
        return redirect()->to(site_url('flows/states/' . $flow_id));
    }

    // Deletes a workflow state.
    public function state_delete($id)
    {
        check_module_access('flows', 'edit');
        $state   = $this->app_model->stateGetById($id);
        $flow_id = 0;
        if (!empty($state) && isset($state['flow_id'])) {
            $flow_id = (int) $state['flow_id'];
        }
        $result = $this->app_model->stateDelete($id);
        if (!empty($result['ok'])) {
            $stateName = '';
            if (isset($state['name'])) {
                $stateName = (string) $state['name'];
            }
            activity_log('flows', 'delete_state', 'state', (string) $id, 'Removed state "' . $stateName . '" from flow #' . $flow_id, ['flow_id' => $flow_id, 'name' => $stateName]);
            $this->session->setFlashdata('success', 'State removed.');
        } else {
            $msg = 'State could not be removed.';
            if (!empty($result['reason'])) {
                $msg = $result['reason'];
            }
            $this->session->setFlashdata('error', $msg);
        }
        return redirect()->to(site_url('flows/states/' . $flow_id));
    }

    // Reorders workflow states sequence.
    public function state_reorder()
    {
        check_module_access('flows', 'edit');
        try {
            $payload = $this->request->getJSON(true);
            if (!$payload) {
                $payload = [];
            }
        } catch (\Exception $e) {
            log_message('warning', 'state_reorder: request body parse failed — {msg}', ['msg' => $e->getMessage()]);
            $payload = [];
        }
        if (empty($payload)) {
            $payload = $this->request->getPost();
        }
        $flow_id = 0;
        if (isset($payload['flow_id'])) {
            $flow_id = (int) $payload['flow_id'];
        }
        $order = [];
        if (isset($payload['order'])) {
            $order = $payload['order'];
        }
        if ($flow_id <= 0 || !is_array($order) || empty($order)) {
            return json_fail('Flow id and order are required');
        }
        if (!$this->app_model->stateReorder($flow_id, $order)) {
            return json_fail('Reorder rejected: state ids do not belong to this flow');
        }
        activity_log('flows', 'reorder_states', 'flow', (string) $flow_id, 'Reordered states in flow #' . $flow_id, ['flow_id' => $flow_id, 'order' => $order]);
        return json_ok([], 'Order saved');
    }

    /** GET /flows/transitions/(:num) — list all transitions for a flow (JSON). */
    public function state_transitions($flow_id)
    {
        check_module_access('flows', 'view');
        $rows = $this->app_model->stateGetAllTransitions((int) $flow_id);
        return json_ok($rows);
    }

    /** POST /flows/transitions/save — create or update a state transition. */
    public function state_transition_save()
    {
        check_module_access('flows', 'edit');
        $flow_id       = (int) $this->request->getPost('flow_id');
        $from_state_id = (int) $this->request->getPost('from_state_id');
        $to_state_id   = (int) $this->request->getPost('to_state_id');
        $type          = trim((string) $this->request->getPost('transition_type'));
        $requires_comment = (int) $this->request->getPost('requires_comment');
        $id            = (int) $this->request->getPost('id');

        if ($flow_id <= 0 || $from_state_id <= 0 || $to_state_id <= 0) {
            return json_fail('flow_id, from_state_id and to_state_id are required.');
        }
        if (!in_array($type, ['forward', 'backward'], true)) {
            return json_fail('Invalid transition_type.');
        }
        if ($from_state_id === $to_state_id) {
            return json_fail('A state cannot transition to itself.');
        }
        if ($type === 'forward') {
            $descendants = $this->app_model->stateGetDescendantIds($flow_id, $to_state_id);
            if (in_array($from_state_id, $descendants, true)) {
                return json_fail('This forward transition would create a cycle.');
            }
        }
        $dbId = null;
        if ($id !== '' && $id !== null && $id !== 0) {
            $dbId = $id;
        }
        $newId = $this->app_model->stateTransitionSave([
            'id'              => $dbId,
            'flow_id'         => $flow_id,
            'from_state_id'   => $from_state_id,
            'to_state_id'     => $to_state_id,
            'transition_type' => $type,
            'requires_comment' => $requires_comment,
            'created_by'      => (string) logged_user_id(),
        ]);
        $transActionText = 'Added';
        if ($id) {
            $transActionText = 'Updated';
        }
        activity_log('flows', 'transition_save', 'flow', (string) $flow_id, $transActionText . ' ' . $type . ' transition in flow #' . $flow_id);
        return json_ok(['id' => $newId], 'Transition saved');
    }

    /** POST /flows/transitions/delete/(:num) — delete a transition by id. */
    public function state_transition_delete($id)
    {
        check_module_access('flows', 'edit');
        $ok = $this->app_model->stateTransitionDelete((int) $id);
        if (!$ok) {
            return json_fail('Transition not found.');
        }
        activity_log('flows', 'transition_delete', null, null, 'Deleted transition #' . $id);
        return json_ok([], 'Transition deleted');
    }

    // Lists all configured alerts and filters.
    public function alerts()
    {
        check_module_access('alerts', 'view');
        $data = [
            'title'    => 'Alert Definitions',
            'view'     => 'list',
            'alerts'   => $this->app_model->alertGetAll(),
            'alert'    => null,
            'projects' => [],
            'flows'    => [],
            'users'    => [],
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('alerts', $data);
        echo view('templates/footer');
    }

    // Renders the form to define a new alert.
    public function alert_add()
    {
        check_module_access('alerts', 'add');
        $data = [
            'title'    => 'Add Alert Definition',
            'view'     => 'form',
            'alert'    => null,
            'projects' => $this->app_model->projectGetActive(logged_user_id(), role_has_admin_scope()),
            'flows'    => $this->app_model->flowGetActive(),
            'users'    => $this->user_model->getActive(),
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('alerts', $data);
        echo view('templates/footer');
    }

    // Saves a new alert configuration.
    public function alert_save()
    {
        check_module_access('alerts', 'add');
        $projectId = (int) $this->request->getPost('project_id');
        $flowId    = (int) $this->request->getPost('flow_id');
        $flow      = $this->app_model->flowGetById($flowId);
        if (empty($flow) || (int) $flow['project_id'] !== $projectId) {
            $this->session->setFlashdata('error', 'The selected Flow does not belong to the selected Project.');
            return redirect()->to(site_url('alerts/add'));
        }

        $alertType = strtolower((string) $this->request->getPost('alert_type'));
        if (!in_array($alertType, ['info', 'major', 'critical'], true)) {
            $alertType = 'info';
        }
        $alertName = (string) $this->request->getPost('name');
        $newId = $this->app_model->alertSave([
            'project_id'      => $projectId,
            'name'            => $alertName,
            'description'     => (string) $this->request->getPost('description'),
            'alert_type'      => $alertType,
            'threshold_value' => (string) $this->request->getPost('threshold_value'),
            'threshold_unit'  => (string) $this->request->getPost('threshold_unit'),
            'flow_id'         => $flowId,
            'notify_user_ids' => (array) $this->request->getPost('notify_user_ids'),
            'is_active'       => 1,
            'created_by'      => logged_user_id(),
        ]);
        activity_log('alerts', 'create', 'alert', (string) $newId, 'Created alert "' . $alertName . '" (' . $alertType . ')', ['name' => $alertName, 'project_id' => $projectId, 'flow_id' => $flowId, 'alert_type' => $alertType]);
        $this->session->setFlashdata('success', 'Alert definition saved.');
        return redirect()->to(site_url('alerts'));
    }

    // Renders the edit form for an alert configuration.
    public function alert_edit($id)
    {
        check_module_access('alerts', 'edit');
        $alert = $this->app_model->alertGetById($id);
        if (empty($alert)) {
            return redirect()->to(site_url('alerts'));
        }
        $data = [
            'title'    => 'Edit Alert Definition',
            'view'     => 'form',
            'alert'    => $alert,
            'projects' => $this->app_model->projectGetActive(logged_user_id(), role_has_admin_scope()),
            'flows'    => $this->app_model->flowGetActive(),
            'users'    => $this->user_model->getActive(),
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('alerts', $data);
        echo view('templates/footer');
    }

    // Updates an alert configuration.
    public function alert_update($id)
    {
        check_module_access('alerts', 'edit');
        $projectId = (int) $this->request->getPost('project_id');
        $flowId    = (int) $this->request->getPost('flow_id');
        $flow      = $this->app_model->flowGetById($flowId);
        if (empty($flow) || (int) $flow['project_id'] !== $projectId) {
            $this->session->setFlashdata('error', 'The selected Flow does not belong to the selected Project.');
            return redirect()->to(site_url('alerts/edit/' . $id));
        }

        $alertType = strtolower((string) $this->request->getPost('alert_type'));
        if (!in_array($alertType, ['info', 'major', 'critical'], true)) {
            $alertType = 'info';
        }
        $alertName = (string) $this->request->getPost('name');
        $before = $this->app_model->alertGetById($id);
        $after  = [
            'project_id'      => $projectId,
            'name'            => $alertName,
            'description'     => (string) $this->request->getPost('description'),
            'alert_type'      => $alertType,
            'threshold_value' => (string) $this->request->getPost('threshold_value'),
            'threshold_unit'  => (string) $this->request->getPost('threshold_unit'),
            'flow_id'         => $flowId,
            'notify_user_ids' => (array) $this->request->getPost('notify_user_ids'),
            'is_active'       => bool_int($this->request->getPost('is_active')),
        ];
        $this->app_model->alertUpdate($id, $after);
        $diff = activity_diff($before, $after, ['project_id', 'name', 'description', 'alert_type', 'threshold_value', 'threshold_unit', 'flow_id', 'is_active']);
        activity_log('alerts', 'update', 'alert', (string) $id, 'Updated alert "' . $alertName . '"', $diff);
        $this->session->setFlashdata('success', 'Alert definition updated.');
        return redirect()->to(site_url('alerts'));
    }

    // Deletes an alert configuration.
    public function alert_delete($id)
    {
        check_module_access('alerts', 'delete');
        $before = $this->app_model->alertGetById($id);
        $this->app_model->alertDeactivate($id);
        $alertName = '';
        if (isset($before['name'])) {
            $alertName = (string) $before['name'];
        }
        activity_log('alerts', 'delete', 'alert', (string) $id, 'Deactivated alert "' . $alertName . '"', ['name' => $alertName]);
        $this->session->setFlashdata('success', 'Alert deactivated.');
        return redirect()->to(site_url('alerts'));
    }

    // Lists all defined escalation rules.
    public function escalation()
    {
        check_module_access('escalation', 'view');
        $data = [
            'title' => 'Escalation Matrix',
            'view'  => 'escalation',
            'rows'  => $this->app_model->escalationGetAll(),
            'flows' => $this->app_model->flowGetActive(),
            'users' => $this->user_model->getActive(),
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('alerts', $data);
        echo view('templates/footer');
    }

    // Saves or updates an escalation rule.
    public function escalation_save()
    {
        check_module_access('escalation', 'edit');
        $flow_id  = (int) $this->request->getPost('flow_id');
        $state_id = (int) $this->request->getPost('state_id');
        $level    = (int) $this->request->getPost('level');
        $minutes  = (int) $this->request->getPost('escalate_after');

        if ($flow_id <= 0 || $state_id <= 0 || $level < 1 || $level > 4 || $minutes < 1) {
            $this->session->setFlashdata('error', 'Invalid escalation rule.');
            return redirect()->to(site_url('escalation'));
        }
        $alertType = (string) $this->request->getPost('alert_type');
        if (!in_array($alertType, ['info', 'major', 'critical'], true)) {
            $alertType = 'major';
        }

        $this->app_model->escalationSave([
            'flow_id'         => $flow_id,
            'state_id'        => $state_id,
            'level'           => $level,
            'escalate_after'  => $minutes,
            'notify_user_ids' => (array) $this->request->getPost('notify_user_ids'),
            'alert_type'      => $alertType,
        ]);
        activity_log('escalation', 'create', 'escalation', null, 'Added escalation L' . $level . ' @ ' . $minutes . 'm for flow #' . $flow_id . ' state #' . $state_id, ['flow_id' => $flow_id, 'state_id' => $state_id, 'level' => $level, 'escalate_after' => $minutes, 'alert_type' => $alertType]);
        $this->session->setFlashdata('success', 'Escalation rule added.');
        return redirect()->to(site_url('escalation'));
    }

    // Deletes an escalation rule.
    public function escalation_delete($id)
    {
        check_module_access('escalation', 'delete');
        $this->app_model->escalationDelete($id);
        activity_log('escalation', 'delete', 'escalation', (string) $id, 'Removed escalation rule #' . $id);
        $this->session->setFlashdata('success', 'Escalation rule removed.');
        return redirect()->to(site_url('escalation'));
    }

    // Lists and manages API keys.
    public function api_keys()
    {
        check_module_access('api_keys', 'view');
        $data = [
            'title'    => 'API Keys',
            'view'     => 'api_keys',
            'keys'     => $this->app_model->apiKeyGetAll(),
            'projects' => $this->app_model->projectGetActive(logged_user_id(), role_has_admin_scope()),
            'newKey'   => $this->session->getFlashdata('newKey'),
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('alerts', $data);
        echo view('templates/footer');
    }

    // Generates a new API key.
    public function api_key_generate()
    {
        check_module_access('api_keys', 'add');
        $project_id = (int) $this->request->getPost('project_id');
        $name       = trim((string) $this->request->getPost('name'));
        if ($project_id === 0 || $name === '') {
            $this->session->setFlashdata('error', 'Project and name are required.');
            return redirect()->to(site_url('api_keys'));
        }
        $key = $this->app_model->apiKeyGenerate($project_id, $name);
        activity_log('api_keys', 'generate', 'api_key', null, 'Generated API key "' . $name . '" for project #' . $project_id, ['name' => $name, 'project_id' => $project_id]);
        $this->session->setFlashdata('newKey', $key);
        $this->session->setFlashdata('success', 'API key generated. Copy it now — you won\'t see it again.');
        return redirect()->to(site_url('api_keys'));
    }

    // Enables or disables an API key.
    public function api_key_toggle($id)
    {
        check_module_access('api_keys', 'edit');
        $this->app_model->apiKeyToggle($id);
        activity_log('api_keys', 'toggle', 'api_key', (string) $id, 'Toggled API key #' . $id);
        return redirect()->to(site_url('api_keys'));
    }

    // Lists tickets assigned to the logged-in user.
    public function tickets_my()
    {
        check_module_access('tickets', 'view');
        log_message('debug', "pview alert >> tickets MY page open by user_id=[" . (string) logged_user_id() . "]");
        $filters = [
            'status'     => (string) $this->request->getGet('status'),
            'search'     => (string) $this->request->getGet('q'),
            'alert_type' => (string) $this->request->getGet('alert_type'),
            'priority'   => (string) $this->request->getGet('priority'),
            'f_from'     => trim((string) $this->request->getGet('f_from')),
            'f_to'       => trim((string) $this->request->getGet('f_to')),
        ];
        $data = [
            'title'         => 'My Tickets',
            'view'          => 'list',
            'mode'          => 'my',
            'filters'       => $filters,
            'projects'      => $this->app_model->projectGetActive(logged_user_id(), role_has_admin_scope()),
            'flows'         => $this->app_model->flowGetActive(),
            'savedFilters'  => $this->app_model->savedFilterList(logged_user_id(), 'tickets'),
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('tickets', $data);
        echo view('templates/footer');
    }

    // Lists all tickets in the system.
    public function tickets_all()
    {
        check_module_access('tickets_all', 'view');
        log_message('debug', "pview alert >> tickets ALL page open by user_id=[" . (string) logged_user_id() . "]");
        $filters = [
            'project_id' => (int) $this->request->getGet('project_id'),
            'flow_id'    => (int) $this->request->getGet('flow_id'),
            'status'     => (string) $this->request->getGet('status'),
            'alert_type' => (string) $this->request->getGet('alert_type'),
            'priority'   => (string) $this->request->getGet('priority'),
            'search'     => (string) $this->request->getGet('q'),
            'f_from'     => trim((string) $this->request->getGet('f_from')),
            'f_to'       => trim((string) $this->request->getGet('f_to')),
        ];
        $data = [
            'title'         => 'All Tickets',
            'view'          => 'list',
            'mode'          => 'all',
            'filters'       => $filters,
            'projects'      => $this->app_model->projectGetActive(logged_user_id(), role_has_admin_scope()),
            'flows'         => $this->app_model->flowGetActive(),
            'savedFilters'  => $this->app_model->savedFilterList(logged_user_id(), 'tickets'),
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('tickets', $data);
        echo view('templates/footer');
    }

    // Renders the form to create a new ticket.
    public function ticket_create()
    {
        check_module_access('tickets', 'add');
        $data = [
            'title'    => 'Raise Ticket',
            'view'     => 'create',
            'projects' => $this->app_model->projectGetActive(logged_user_id(), role_has_admin_scope()),
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('tickets', $data);
        echo view('templates/footer');
    }

    // Saves a new ticket and triggers initial workflow flow.
    public function ticket_save()
    {
        check_module_access('tickets', 'add');
        $project_id = (int) $this->request->getPost('project_id');
        $flow_id    = (int) $this->request->getPost('flow_id');
        $title      = trim((string) $this->request->getPost('title'));
        if ($project_id === 0 || $flow_id === 0 || $title === '') {
            $this->session->setFlashdata('error', 'Project, flow and title are required.');
            return redirect()->to(site_url('tickets/create'));
        }

        $project = $this->app_model->projectGetById($project_id);
        if (empty($project) || $project['status'] !== 'active') {
            $this->session->setFlashdata('error', 'Selected project is not active.');
            return redirect()->to(site_url('tickets/create'));
        }
        $flow = $this->app_model->flowGetById($flow_id);
        if (empty($flow) || $flow['status'] !== 'active') {
            $this->session->setFlashdata('error', 'Selected flow is not active.');
            return redirect()->to(site_url('tickets/create'));
        }
        if ((int) $flow['project_id'] !== $project_id) {
            $this->session->setFlashdata('error', 'Selected flow does not belong to the chosen project.');
            return redirect()->to(site_url('tickets/create'));
        }

        $initial = $this->app_model->stateGetInitial($flow_id);
        if (empty($initial)) {
            $this->session->setFlashdata('error', 'This flow has no states. Add at least one state first.');
            return redirect()->to(site_url('tickets/create'));
        }

        $alertType = strtolower((string) $this->request->getPost('alert_type'));
        if (!in_array($alertType, ['info', 'major', 'critical'], true)) {
            $alertType = 'info';
        }
        $priority = strtolower((string) $this->request->getPost('priority'));
        if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            $priority = 'medium';
        }

        $assigneeUserId = trim((string) $this->request->getPost('assignee_user_id'));
        if ($assigneeUserId !== '') {
            $pool = $this->app_model->stateLevelUsers($initial, 1);
            if (!in_array($assigneeUserId, $pool, true)) {
                $this->session->setFlashdata('error', 'Selected assignee is not in the L1 pool for this flow.');
                return redirect()->to(site_url('tickets/create'));
            }
        }

        $actualStart = trim((string) $this->request->getPost('actual_start_date'));
        $actualEnd   = trim((string) $this->request->getPost('actual_end_date'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $actualStart)) {
            $actualStart = null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $actualEnd)) {
            $actualEnd = null;
        }
        if ($actualStart !== null && $actualEnd !== null && $actualEnd < $actualStart) {
            $this->session->setFlashdata('error', 'End Date cannot be earlier than Start Date.');
            return redirect()->to(site_url('tickets/create'));
        }

        $alarmId = generate_alarm_id();

        $currentAssignee = null;
        $statusVal = 'open';
        if ($assigneeUserId !== '') {
            $currentAssignee = $assigneeUserId;
            $statusVal = 'in_progress';
        }

        $this->db->transStart();
        $ticket_id = $this->app_model->ticketSave([
            'alarm_id'          => $alarmId,
            'project_id'        => $project_id,
            'flow_id'           => $flow_id,
            'title'             => $title,
            'description'       => (string) $this->request->getPost('description'),
            'alert_type'        => $alertType,
            'priority'          => $priority,
            'actual_start_date' => $actualStart,
            'actual_end_date'   => $actualEnd,
            'current_state_id'  => (int) $initial['id'],
            'current_level'     => 1,
            'current_assignee'  => $currentAssignee,
            'status'            => $statusVal,
            'source'            => 'ui',
            'raised_by'         => logged_user_id(),
        ]);

        $createComment = 'Ticket raised by ' . logged_user_name();
        if ($assigneeUserId !== '') {
            $createComment .= ', assigned to ' . $assigneeUserId;
        }
        $this->app_model->ticketLogAction($ticket_id, 'created', [
            'comment'     => $createComment,
            'to_state_id' => (int) $initial['id'],
            'to_level'    => 1,
        ]);
        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            log_message('error', 'pview alert >> ticket_save() transaction failed: alarm_id=[' . $alarmId . ']');
            $this->session->setFlashdata('error', 'Failed to create ticket due to a database error. Please try again.');
            return redirect()->to(site_url('tickets/create'));
        }

        $file = $this->request->getFile('attachment');
        if (!empty($file) && $file->isValid() && $file->getSize() > 0) {
            $attachErr = $this->processTicketAttachment($ticket_id, $alarmId, $file);
            if ($attachErr !== null) {
                $this->session->setFlashdata('warning', 'Ticket created but attachment failed: ' . $attachErr);
            }
        }

        if ($assigneeUserId !== '') {
            $notifyUsers = [$assigneeUserId];
        } else {
            $notifyUsers = $this->app_model->stateLevelUsers($initial, 1);
        }
        try {
            if (!empty($notifyUsers)) {
                notify_ticket_event('created', [
                    'id'            => $ticket_id,
                    'alarm_id'      => $alarmId,
                    'title'         => $title,
                    'description'   => (string) $this->request->getPost('description'),
                    'alert_type'    => $alertType,
                    'priority'      => $priority,
                    'project_name'  => $project['name'],
                    'flow_name'     => $flow['name'],
                    'state_name'    => $initial['name'],
                    'current_level' => 1,
                ], ['actor_name' => logged_user_name()], $notifyUsers);
            }
        } catch (\Throwable $e) {
            log_message('error', "pview alert >> ticket save notify_ticket_event FAILED: error=[" . $e->getMessage() . "]");
        }

        activity_log('tickets', 'create', 'ticket', $alarmId, 'Raised ticket "' . $title . '" (' . $alertType . '/' . $priority . ')', ['project_id' => $project_id, 'flow_id' => $flow_id, 'alert_type' => $alertType, 'priority' => $priority]);

        // Warn if a recent open ticket with the same type+project already exists.
        $dupWindowHours = app_setting_int('duplicate_detection_window_hours', 24);
        if ($dupWindowHours > 0) {
            $windowStart = date('Y-m-d H:i:s', time() - $dupWindowHours * 3600);
            $dups = $this->db->table('tickets')
                ->select('alarm_id')
                ->where('project_id', $project_id)
                ->where('alert_type', $alertType)
                ->whereNotIn('status', ['resolved', 'closed'])
                ->where('created_at >=', $windowStart)
                ->where('id !=', $ticket_id)
                ->get()->getResultArray();
            if (!empty($dups)) {
                $dupIds = implode(', ', array_column($dups, 'alarm_id'));
                $this->session->setFlashdata('warning', 'Possible duplicate: ' . count($dups) . ' open ticket(s) with the same type already exist in this project in the last ' . $dupWindowHours . 'h — ' . $dupIds);
            }
        }

        $this->session->setFlashdata('success', 'Ticket raised: ' . $alarmId);
        return redirect()->to(site_url('tickets/detail/' . $alarmId));
    }

    /** Validates and stores an uploaded attachment. Returns null on success, error string on failure. */
    private function processTicketAttachment($ticket_id, $alarmId, $file)
    {
        $originalName = (string) $file->getName();
        $nameErr = upload_filename_is_safe($originalName);
        if ($nameErr !== '') {
            return $nameErr;
        }

        $allowedExt = app_setting_csv('upload_allowed_ext', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'xlsx', 'xls', 'csv', 'txt']);
        $ext = strtolower($file->getExtension());
        if ($ext === '') {
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        }
        if (!in_array($ext, $allowedExt, true) || in_array($ext, upload_blocked_extensions(), true)) {
            return 'File type "' . $ext . '" is not allowed.';
        }

        $allowedMime = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
            'text/plain',
            'application/octet-stream',
        ];
        $mime = (string) $file->getMimeType();
        if (!in_array($mime, $allowedMime, true)) {
            return 'File mime type not allowed.';
        }

        $maxMb = app_setting_int('upload_max_mb', 10);
        if ($maxMb < 1) {
            $maxMb = 10;
        }
        if ($file->getSize() > $maxMb * 1024 * 1024) {
            return 'Max ' . $maxMb . ' MB per file.';
        }

        $tmpPath = (string) $file->getTempName();
        $sniffed = upload_sniff_mime($tmpPath);
        if ($sniffed !== '' && !upload_mime_matches_ext($ext, $sniffed)) {
            return 'File content does not match its extension.';
        }

        $dir = WRITEPATH . 'uploads/tickets/' . $alarmId . '/';
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            return 'Could not create upload directory.';
        }
        $htaccess = $dir . '.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents(
                $htaccess,
                "# Auto-generated — do not edit.\n"
                    . "<IfModule mod_php8.c>\n  php_flag engine off\n</IfModule>\n"
                    . "RemoveHandler .php .phtml .phar\nOptions -ExecCGI -Indexes\n"
            );
        }

        $newName = $file->getRandomName();
        $file->move($dir, $newName);
        $rel = 'uploads/tickets/' . $alarmId . '/' . $newName;
        $this->app_model->ticketLogAction($ticket_id, 'attachment', [
            'attachment_path' => $rel,
            'comment'         => basename($originalName),
        ]);
        return null;
    }

    // Returns true for resolved/closed tickets — all mutations are blocked on these.
    private function ticketIsTerminal($ticket)
    {
        $status = '';
        if (isset($ticket['status'])) {
            $status = (string) $ticket['status'];
        }
        return $status === 'resolved' || $status === 'closed';
    }

    // Validates alarm_id, loads the ticket, and checks access. Returns the ticket row or a response object.
    private function ticketLoad($alarm_id, $isAjax)
    {
        $clean = safe_alarm_id($alarm_id);
        if (!$clean) {
            if ($isAjax) {
                return json_fail('Invalid alarm id', 400);
            }
            return redirect()->to(site_url('tickets'))->with('error', 'Invalid alarm id.');
        }
        $ticket = $this->app_model->ticketGetByAlarm($clean);
        if (empty($ticket)) {
            if ($isAjax) {
                return json_fail('Ticket not found', 404);
            }
            return redirect()->to(site_url('tickets'))->with('error', 'Ticket not found.');
        }
        if (!verify_ticket_access($ticket)) {
            if ($isAjax) {
                return json_fail('You do not have access to this ticket', 403);
            }
            return redirect()->to(site_url('tickets'))->with('error', 'You do not have access to this ticket.');
        }
        return $ticket;
    }

    // Renders the detail view for a specific ticket.
    public function ticket_detail($alarm_id)
    {
        check_module_access('tickets', 'view');
        $r = $this->ticketLoad($alarm_id, false);
        if ($r instanceof \CodeIgniter\HTTP\RedirectResponse) {
            return $r;
        }
        $ticket = $r;
        activity_log('tickets', 'view', 'ticket', (string) $ticket['alarm_id'], 'Viewed ticket ' . $ticket['alarm_id']);

        $allStates = $this->app_model->stateGetAll($ticket['flow_id']);
        $state     = $this->app_model->stateGetById($ticket['current_state_id']);
        $tatMin    = tat_minutes_for_level($state, $ticket['current_level']);

        $allPoolIds = [];
        for ($lvl = 1; $lvl <= 4; $lvl++) {
            $allPoolIds = array_merge($allPoolIds, $this->app_model->stateLevelUsers($state, $lvl));
        }
        $assignable = $this->user_model->getByIds(array_values(array_unique($allPoolIds)));

        $attachCount = $this->app_model->ticketAttachmentCount($ticket['id']);

        $sortOrderVal = 0;
        if (isset($state['sort_order'])) {
            $sortOrderVal = $state['sort_order'];
        }
        $currentSortOrder = (int) $sortOrderVal;
        $isInitialState   = !empty($state['is_initial']);
        $bwdTransitions   = $this->app_model->stateGetTransitions(
            (int) $ticket['flow_id'],
            (int) $ticket['current_state_id'],
            'backward'
        );
        $previousStates = [];
        if (!empty($bwdTransitions)) {
            $validBwdIds = array_map('intval', array_column($bwdTransitions, 'to_state_id'));
            foreach ($allStates as $s) {
                if (in_array((int) $s['id'], $validBwdIds, true)) {
                    $previousStates[] = $s;
                }
            }
            usort($previousStates, function ($a, $b) {
                return (int) $b['sort_order'] - (int) $a['sort_order'];
            });
            $previousStates = array_values($previousStates);
        }

        $data = [
            'title'          => 'Ticket Detail',
            'view'           => 'detail',
            'ticket'         => $ticket,
            'allStates'      => $allStates,
            'state'          => $state,
            'tatMinutes'     => $tatMin,
            'tatExpiresAt'   => tat_expires_at($ticket, $state),
            'timeline'       => $this->app_model->ticketTimeline($ticket['id']),
            'assignableUsers' => $assignable,
            'notifications'  => $this->app_model->ticketRecentNotifications($ticket['id'], 5),
            'nextStates'     => $this->app_model->stateGetChildren((int) $ticket['flow_id'], (int) $ticket['current_state_id']),
            'previousStates' => $previousStates,
            'allTransitions' => $this->app_model->stateGetAllTransitions((int) $ticket['flow_id']),
            'attachCount'    => $attachCount,
            'attachMax'      => 5,
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('tickets', $data);
        echo view('templates/footer');
    }

    // Processes a workflow transition action on a ticket.
    public function ticket_action($alarm_id)
    {
        check_module_access('tickets', 'edit');
        $r = $this->ticketLoad($alarm_id, true);
        if (!is_array($r)) {
            return $r;
        }
        $ticket = $r;
        $type   = (string) $this->request->getPost('type');

        if ($this->ticketIsTerminal($ticket)) {
            return json_fail('Ticket is ' . $ticket['status'] . '; further edits are blocked.');
        }

        if ($type === 'comment') {
            $comment = trim((string) $this->request->getPost('comment'));
            if ($comment === '') {
                return json_fail('Comment is empty');
            }
            if (mb_strlen($comment) > 5000) {
                return json_fail('Comment is too long (max 5000 chars)');
            }
            $this->app_model->ticketLogAction($ticket['id'], 'commented', ['comment' => $comment]);

            try {
                $mentioned = parse_mentions($comment, (string) logged_user_id(), $ticket);
                if (!empty($mentioned)) {
                    $body = '<p><strong>' . esc(logged_user_name()) . '</strong> mentioned you in ticket '
                        . '<strong>' . esc($ticket['alarm_id']) . '</strong>:</p>'
                        . '<blockquote style="margin:8px 0;padding:8px 12px;border-left:3px solid #0792cd;background:#f1f5f9;color:#0f172a;">'
                        . nl2br(esc($comment))
                        . '</blockquote>'
                        . '<p><a href="' . esc(site_url('tickets/detail/' . $ticket['alarm_id'])) . '">View ticket</a></p>';
                    notify_users($mentioned, (int) $ticket['id'], '[MENTION] ' . $ticket['alarm_id'] . ' — ' . logged_user_name() . ' mentioned you', $body);
                    log_message('debug', "pview alert >> @mention notify: ticket=[" . $ticket['alarm_id'] . "], mentioned=[" . implode(',', $mentioned) . "], by=[" . logged_user_id() . "]");
                }
            } catch (\Throwable $e) {
                log_message('error', "pview alert >> @mention parse/notify FAILED: " . $e->getMessage());
            }

            activity_log('tickets', 'comment', 'ticket', (string) $ticket['alarm_id'], 'Commented on ' . $ticket['alarm_id']);
            return json_ok([], 'Comment added');
        }

        $editableTypes = ['title', 'description', 'priority'];
        if (in_array($type, $editableTypes, true)) {
            if (!role_has_admin_scope(logged_user_role()) && (string) $ticket['current_assignee'] !== (string) logged_user_id()) {
                return json_fail('Only the assigned operator or an admin can edit ticket fields.');
            }
        }

        if ($type === 'title') {
            $newTitle = trim((string) $this->request->getPost('title'));
            if ($newTitle === '') {
                return json_fail('Title cannot be empty');
            }
            if (mb_strlen($newTitle) > 300) {
                return json_fail('Title is too long (max 300 chars)');
            }
            $this->app_model->ticketUpdate($ticket['id'], ['title' => $newTitle]);
            $this->app_model->ticketLogAction($ticket['id'], 'title_changed', ['comment' => 'Title updated to: ' . $newTitle]);
            activity_log('tickets', 'update', 'ticket', (string) $ticket['alarm_id'], 'Title changed on ' . $ticket['alarm_id'], ['field' => 'title', 'old' => $ticket['title'], 'new' => $newTitle]);
            return json_ok([], 'Title updated');
        }

        if ($type === 'description') {
            $desc = (string) $this->request->getPost('description');
            if (mb_strlen($desc) > 10000) {
                return json_fail('Description too long (max 10000 chars)');
            }
            $this->app_model->ticketUpdate($ticket['id'], ['description' => $desc]);
            $this->app_model->ticketLogAction($ticket['id'], 'description_changed', ['comment' => 'Description updated']);
            activity_log('tickets', 'update', 'ticket', (string) $ticket['alarm_id'], 'Description changed on ' . $ticket['alarm_id'], ['field' => 'description']);
            return json_ok([], 'Description updated');
        }

        if ($type === 'priority') {
            $prio = (string) $this->request->getPost('priority');
            if (!in_array($prio, ['low', 'medium', 'high', 'urgent'], true)) {
                return json_fail('Invalid priority');
            }
            $this->app_model->ticketUpdate($ticket['id'], ['priority' => $prio]);
            $this->app_model->ticketLogAction($ticket['id'], 'priority_changed', ['comment' => 'Priority changed to ' . $prio]);
            activity_log('tickets', 'update', 'ticket', (string) $ticket['alarm_id'], 'Priority changed on ' . $ticket['alarm_id'] . ' to ' . $prio, ['field' => 'priority', 'old' => $ticket['priority'], 'new' => $prio]);
            return json_ok([], 'Priority updated');
        }
        return json_fail('Unknown action');
    }

    /** POST /tickets/move_state/(:any) — move ticket to an allowed forward or
     *  backward state. Backward transitions always require a reason comment. */
    public function ticket_move_state($alarm_id)
    {
        check_module_access('tickets', 'edit');
        $r = $this->ticketLoad($alarm_id, true);
        if (!is_array($r)) {
            return $r;
        }
        $ticket = $r;

        if (!role_has_admin_scope(logged_user_role()) && (string) $ticket['current_assignee'] !== (string) logged_user_id()) {
            return json_fail('Only the assigned operator or an admin can move the ticket state.');
        }

        if ($this->ticketIsTerminal($ticket)) {
            return json_fail('Ticket is ' . $ticket['status'] . '; cannot move state.');
        }

        $targetId      = (int) $this->request->getPost('target_state_id');
        $transType     = trim((string) $this->request->getPost('transition_type'));
        $reason        = trim((string) $this->request->getPost('reason'));

        if ($targetId <= 0) {
            return json_fail('Please select a target state.');
        }
        if (!in_array($transType, ['forward', 'backward'], true)) {
            $transType = 'forward';
        }

        $next = $this->app_model->stateGetById($targetId);
        if (empty($next) || (int) $next['flow_id'] !== (int) $ticket['flow_id']) {
            return json_fail('Target state not found in this flow.');
        }

        $currentState = $this->app_model->stateGetById($ticket['current_state_id']);

        if ($transType === 'forward') {
            if (!empty($ticket['state_is_final']) && (int) $ticket['state_is_final'] === 1) {
                return json_fail('Current state is the closing state — resolve or close the ticket instead.');
            }
            $validNext = $this->app_model->stateGetChildren(
                (int) $ticket['flow_id'],
                (int) $ticket['current_state_id']
            );
            $validIds = array_column($validNext, 'id');
            if (!in_array($targetId, array_map('intval', $validIds), true)) {
                return json_fail('This is not a valid next state from the current position.');
            }
            // Enforce requires_comment on explicit forward transitions even if the UI didn't prompt.
            $fwdTransitions = $this->app_model->stateGetTransitions(
                (int) $ticket['flow_id'],
                (int) $ticket['current_state_id'],
                'forward'
            );
            foreach ($fwdTransitions as $ft) {
                if ((int) $ft['to_state_id'] === $targetId && !empty($ft['requires_comment'])) {
                    if ($reason === '') {
                        return json_fail('A comment is required for this transition.');
                    }
                    break;
                }
            }
        } else {
            if ($reason === '') {
                return json_fail('A reason is required when sending a ticket backward.');
            }
            $bwdTransitions = $this->app_model->stateGetTransitions(
                (int) $ticket['flow_id'],
                (int) $ticket['current_state_id'],
                'backward'
            );
            $validBwdIds = array_map('intval', array_column($bwdTransitions, 'to_state_id'));
            if (!in_array($targetId, $validBwdIds, true)) {
                return json_fail('This state is not a configured backward target from the current state.');
            }
        }

        $this->app_model->ticketMoveToState($ticket['id'], (int) $next['id']);

        // Record backward transition in the diagram if it doesn't exist yet.
        if ($transType === 'backward') {
            $existingBwd = $this->app_model->stateGetTransitions(
                (int) $ticket['flow_id'],
                (int) $ticket['current_state_id'],
                'backward'
            );
            $existingToIds = array_map('intval', array_column($existingBwd, 'to_state_id'));
            if (!in_array((int) $next['id'], $existingToIds, true)) {
                $this->app_model->stateTransitionSave([
                    'flow_id'          => (int) $ticket['flow_id'],
                    'from_state_id'    => (int) $ticket['current_state_id'],
                    'to_state_id'      => (int) $next['id'],
                    'transition_type'  => 'backward',
                    'requires_comment' => 1,
                    'created_by'       => (string) logged_user_id(),
                ]);
            }
        }

        $assigneeVal = '';
        if (isset($ticket['current_assignee'])) {
            $assigneeVal = $ticket['current_assignee'];
        }
        $currentAssignee = (string) $assigneeVal;
        if ($currentAssignee !== '') {
            $newPool = $this->app_model->stateLevelUsers($next, 1);
            if (!in_array($currentAssignee, $newPool, true)) {
                $clearStatus = 'open';
                if ((string) $ticket['status'] === 'escalated') {
                    $clearStatus = 'escalated';
                }
                $this->app_model->ticketUpdate($ticket['id'], [
                    'current_assignee' => null,
                    'status'           => $clearStatus,
                ]);
                $this->app_model->ticketLogAction($ticket['id'], 'unassigned', [
                    'comment' => 'Assignee cleared — not in ' . $next['name'] . ' L1 pool',
                ]);
            }
        }

        $logComment = ucfirst($transType) . ' transition to ' . $next['name'];
        if ($reason !== '') {
            $logComment .= ': ' . $reason;
        }
        $this->app_model->ticketLogAction($ticket['id'], 'state_changed', [
            'from_state_id'   => (int) $ticket['current_state_id'],
            'to_state_id'     => (int) $next['id'],
            'transition_type' => $transType,
            'comment'         => $logComment,
        ]);
        log_message('debug', "pview alert >> ticket move_state: alarm_id=[" . $alarm_id . "], to_state=[" . $next['name'] . "], by=[" . logged_user_id() . "]");
        activity_log('tickets', 'move_state', 'ticket', (string) $ticket['alarm_id'], 'Moved ' . $ticket['alarm_id'] . ' to "' . $next['name'] . '"', ['from_state_id' => (int) $ticket['current_state_id'], 'to_state_id' => (int) $next['id']]);

        try {
            $level_users = $this->app_model->stateLevelUsers($next, 1);
            if (!empty($level_users)) {
                $fromName = '';
                if (!empty($ticket['state_name'])) {
                    $fromName = (string) $ticket['state_name'];
                }
                notify_ticket_event('state_changed', $ticket, [
                    'state_name'      => $next['name'],
                    'from_state_name' => $fromName,
                    'to_state_name'   => $next['name'],
                    'actor_name'      => logged_user_name(),
                ], $level_users);
            }
        } catch (\Throwable $e) {
            log_message('error', "pview alert >> ticket move_state notify FAILED: error=[" . $e->getMessage() . "]");
        }

        return json_ok([], 'Moved to next state');
    }

    /** POST /tickets/assign/(:any) — assign ticket to a user and set status in_progress. */
    public function ticket_assign($alarm_id)
    {
        check_module_access('tickets', 'edit');
        $r = $this->ticketLoad($alarm_id, true);
        if (!is_array($r)) {
            return $r;
        }
        $ticket = $r;

        if (!empty($ticket['current_assignee']) && !role_has_admin_scope(logged_user_role()) && (string) $ticket['current_assignee'] !== (string) logged_user_id()) {
            return json_fail('Only the assigned operator or an admin can reassign this ticket.');
        }

        if ($this->ticketIsTerminal($ticket)) {
            return json_fail('Ticket is ' . $ticket['status'] . '; cannot reassign.');
        }

        $userIdStr = trim((string) $this->request->getPost('user_id'));
        if ($userIdStr === '') {
            return json_fail('Pick a user');
        }
        $user = $this->user_model->getByUserId($userIdStr);
        if (empty($user) || empty($user['is_active'])) {
            return json_fail('Invalid or inactive user');
        }

        $state   = $this->app_model->stateGetById((int) $ticket['current_state_id']);
        $allPool = [];
        for ($lvl = 1; $lvl <= 4; $lvl++) {
            $allPool = array_merge($allPool, $this->app_model->stateLevelUsers($state, $lvl));
        }
        if (!in_array($userIdStr, $allPool, true)) {
            return json_fail('User is not in any pool for this state.');
        }

        $assignData = [
            'current_assignee' => $userIdStr,
            'status'           => 'in_progress',
            'state_entered_at' => date('Y-m-d H:i:s'),
        ];
        if ((string) $ticket['status'] !== 'escalated') {
            $assignData['current_level'] = 1;
        }
        if (empty($ticket['current_assignee']) && empty($ticket['actual_start_date'])) {
            $assignData['actual_start_date'] = date('Y-m-d');
        }
        $this->app_model->ticketUpdate($ticket['id'], $assignData);
        $this->app_model->ticketLogAction($ticket['id'], 'assigned', [
            'comment' => 'Assigned to ' . $user['name'],
        ]);
        log_message('debug', "pview alert >> ticket assign: alarm_id=[" . $alarm_id . "], assigned_to=[" . $userIdStr . "], by=[" . logged_user_id() . "]");
        activity_log('tickets', 'assign', 'ticket', (string) $ticket['alarm_id'], 'Assigned ' . $ticket['alarm_id'] . ' to ' . $user['name'], ['assignee_user_id' => $userIdStr]);
        try {
            notify_ticket_event('assigned', $ticket, [
                'actor_name'    => logged_user_name(),
                'assignee_name' => (string) $user['name'],
            ], [$userIdStr]);
        } catch (\Throwable $e) {
            log_message('error', "pview alert >> ticket assign notify FAILED: error=[" . $e->getMessage() . "]");
        }
        return json_ok([], 'Assigned');
    }

    /** POST /tickets/resolve/(:any) — mark ticket resolved. */
    public function ticket_resolve($alarm_id)
    {
        check_module_access('tickets', 'edit');
        $r = $this->ticketLoad($alarm_id, true);
        if (!is_array($r)) {
            return $r;
        }
        $ticket = $r;
        if (!role_has_admin_scope(logged_user_role()) && (string) $ticket['current_assignee'] !== (string) logged_user_id()) {
            return json_fail('Only the assigned operator or an admin can resolve this ticket.');
        }
        if ($this->ticketIsTerminal($ticket)) {
            return json_fail('Ticket is already ' . $ticket['status'] . '.');
        }
        $resolveData = [
            'status'      => 'resolved',
            'resolved_at' => date('Y-m-d H:i:s'),
        ];
        if (empty($ticket['actual_end_date'])) {
            $resolveData['actual_end_date'] = date('Y-m-d');
        }
        $this->app_model->ticketUpdate($ticket['id'], $resolveData);
        $this->app_model->ticketLogAction($ticket['id'], 'resolved');
        log_message('debug', "pview alert >> ticket resolve: alarm_id=[" . $alarm_id . "], by=[" . logged_user_id() . "]");
        activity_log('tickets', 'resolve', 'ticket', (string) $ticket['alarm_id'], 'Resolved ' . $ticket['alarm_id']);
        return json_ok([], 'Resolved');
    }

    /** POST /tickets/reopen/(:any) — reopen a resolved ticket back to its last active state. */
    public function ticket_reopen($alarm_id)
    {
        check_module_access('tickets', 'edit');
        $r = $this->ticketLoad($alarm_id, true);
        if (!is_array($r)) {
            return $r;
        }
        $ticket = $r;
        $ticketStatus = '';
        if (isset($ticket['status'])) {
            $ticketStatus = (string) $ticket['status'];
        }
        if ($ticketStatus !== 'resolved') {
            return json_fail('Only resolved tickets can be reopened.');
        }

        $canReopen = false;
        if (logged_user_role() === ROLE_SUPER_ADMIN) {
            $canReopen = true;
        } else {
            $raisedBy = '';
            if (isset($ticket['raised_by'])) {
                $raisedBy = (string) $ticket['raised_by'];
            }
            if ($raisedBy !== '' && $raisedBy === (string) logged_user_id()) {
                $canReopen = true;
            }
        }
        if (!$canReopen) {
            return json_fail('Only the ticket creator or a super_admin can reopen this ticket.');
        }
        $initialState = $this->app_model->stateGetInitial((int) $ticket['flow_id']);
        $targetStateId = (int) $ticket['current_state_id'];
        $stateChanged = false;

        if (!empty($initialState)) {
            $targetStateId = (int) $initialState['id'];
            $stateChanged = ((int) $ticket['current_state_id'] !== $targetStateId);
        }

        if ($stateChanged) {
            $this->app_model->ticketMoveToState($ticket['id'], $targetStateId);
        }

        $currentAssignee = '';
        if (isset($ticket['current_assignee'])) {
            $currentAssignee = (string) $ticket['current_assignee'];
        }
        $hasValidAssignee = false;
        if ($currentAssignee !== '' && !empty($initialState)) {
            $newPool = $this->app_model->stateLevelUsers($initialState, 1);
            if (in_array($currentAssignee, $newPool, true)) {
                $hasValidAssignee = true;
            }
        }

        $newStatus = 'open';
        $newAssignee = null;
        if ($hasValidAssignee) {
            $newStatus = 'in_progress';
            $newAssignee = $currentAssignee;
        }

        $this->app_model->ticketUpdate($ticket['id'], [
            'current_assignee' => $newAssignee,
            'status'           => $newStatus,
            'resolved_at'      => null,
        ]);

        if ($currentAssignee !== '' && !$hasValidAssignee) {
            $this->app_model->ticketLogAction($ticket['id'], 'unassigned', [
                'comment' => 'Assignee cleared — not in ' . $initialState['name'] . ' L1 pool',
            ]);
        }

        if ($stateChanged && !empty($initialState)) {
            $this->app_model->ticketLogAction($ticket['id'], 'state_changed', [
                'from_state_id'   => (int) $ticket['current_state_id'],
                'to_state_id'     => $targetStateId,
                'transition_type' => 'backward',
                'comment'         => 'Auto-moved back to state ' . $initialState['name'] . ' on reopen',
            ]);
        }

        $this->app_model->ticketLogAction($ticket['id'], 'reopened', [
            'comment' => 'Ticket reopened by ' . logged_user_name(),
        ]);
        log_message('debug', "pview alert >> ticket reopen: alarm_id=[" . $alarm_id . "], by=[" . logged_user_id() . "]");
        activity_log('tickets', 'reopen', 'ticket', (string) $ticket['alarm_id'], 'Reopened ' . $ticket['alarm_id']);
        return json_ok([], 'Ticket reopened');
    }

    /** POST /tickets/close/(:any) — mark ticket closed. */
    public function ticket_close($alarm_id)
    {
        check_module_access('tickets', 'edit');
        $r = $this->ticketLoad($alarm_id, true);
        if (!is_array($r)) {
            return $r;
        }
        $ticket = $r;
        if (!role_has_admin_scope(logged_user_role()) && (string) $ticket['current_assignee'] !== (string) logged_user_id()) {
            return json_fail('Only the assigned operator or an admin can close this ticket.');
        }
        if (isset($ticket['status']) && $ticket['status'] === 'closed') {
            return json_fail('Ticket is already closed.');
        }
        $closeData = [
            'status'    => 'closed',
            'closed_at' => date('Y-m-d H:i:s'),
        ];
        if (empty($ticket['actual_end_date'])) {
            $closeData['actual_end_date'] = date('Y-m-d');
        }
        $this->app_model->ticketUpdate($ticket['id'], $closeData);
        $this->app_model->ticketLogAction($ticket['id'], 'closed');
        log_message('debug', "pview alert >> ticket close: alarm_id=[" . $alarm_id . "], by=[" . logged_user_id() . "]");
        activity_log('tickets', 'close', 'ticket', (string) $ticket['alarm_id'], 'Closed ' . $ticket['alarm_id']);
        return json_ok([], 'Closed');
    }

    // Handles file uploads and attachments for a ticket.
    public function ticket_attach($alarm_id)
    {
        check_module_access('tickets', 'edit');
        $r = $this->ticketLoad($alarm_id, true);
        if (!is_array($r)) {
            return $r;
        }
        $ticket = $r;

        if ($this->ticketIsTerminal($ticket)) {
            return json_fail('Ticket is ' . $ticket['status'] . '; attachments are blocked.');
        }

        $currentCount = $this->app_model->ticketAttachmentCount($ticket['id']);
        if ($currentCount >= 5) {
            return json_fail('Maximum attachment limit (5 files) reached for this ticket.');
        }

        $file = $this->request->getFile('file');
        if (empty($file) || !$file->isValid()) {
            return json_fail('No file uploaded');
        }

        // Filename check — catches `evil.php.jpg` and traversal attempts.
        $originalName = (string) $file->getName();
        $nameErr      = upload_filename_is_safe($originalName);
        if ($nameErr !== '') {
            log_message('warning', 'pview alert >> attach rejected (unsafe name): ticket=[' . $ticket['alarm_id'] . '], name=[' . $originalName . '], reason=[' . $nameErr . ']');
            return json_fail($nameErr);
        }

        // Extension allow-list (admin-configurable).
        $allowedExt = app_setting_csv('upload_allowed_ext', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'xlsx', 'xls', 'csv', 'txt']);
        $ext  = strtolower($file->getExtension());
        if ($ext === '') {
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        }
        if (!in_array($ext, $allowedExt, true)) {
            return json_fail('File extension not allowed (allowed: ' . implode(', ', $allowedExt) . ')');
        }
        // Defence in depth: even if an admin accidentally adds `php` to
        // the allow-list, the hard denylist still rejects it.
        if (in_array($ext, upload_blocked_extensions(), true)) {
            log_message('warning', 'pview alert >> attach blocked (denylist): ticket=[' . $ticket['alarm_id'] . '], ext=[' . $ext . ']');
            return json_fail('Files of type "' . $ext . '" are not allowed.');
        }

        // MIME allow-list (header-reported).
        $allowedMime = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
            'text/plain',
            'application/octet-stream',
        ];
        $mime = (string) $file->getMimeType();
        if (!in_array($mime, $allowedMime, true)) {
            return json_fail('File mime type not allowed: ' . $mime);
        }

        // Size limit.
        $maxMb = app_setting_int('upload_max_mb', 10);
        if ($maxMb < 1) {
            $maxMb = 10;
        }
        if ($file->getSize() > $maxMb * 1024 * 1024) {
            return json_fail('Max ' . $maxMb . ' MB');
        }

        // Magic-byte sniff defeats renamed extensions (e.g. .php → .pdf).
        $tmpPath = (string) $file->getTempName();
        $sniffed = upload_sniff_mime($tmpPath);
        if ($sniffed !== '' && !upload_mime_matches_ext($ext, $sniffed)) {
            log_message('warning', 'pview alert >> attach blocked (mime mismatch): ticket=[' . $ticket['alarm_id'] . '], ext=[' . $ext . '], sniffed=[' . $sniffed . ']');
            return json_fail('File content does not match its extension.');
        }

        $dir = WRITEPATH . 'uploads/tickets/' . $ticket['alarm_id'] . '/';
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            return json_fail('Could not create upload directory');
        }
        // Deny-exec .htaccess guards against misconfigured webroot serving writable/.
        $htaccess = $dir . '.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents(
                $htaccess,
                "# Auto-generated by ticket_attach — do not edit.\n"
                    . "<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n"
                    . "<IfModule mod_php7.c>\n  php_flag engine off\n</IfModule>\n"
                    . "<IfModule mod_php8.c>\n  php_flag engine off\n</IfModule>\n"
                    . "RemoveHandler .php .phtml .phar .php3 .php4 .php5 .php7 .pht\n"
                    . "RemoveType    .php .phtml .phar .php3 .php4 .php5 .php7 .pht\n"
                    . "AddType text/plain .php .phtml .phar .php3 .php4 .php5 .php7 .pht\n"
                    . "Options -ExecCGI -Indexes\n"
            );
        }

        $newName = $file->getRandomName();
        $file->move($dir, $newName);

        $rel = 'uploads/tickets/' . $ticket['alarm_id'] . '/' . $newName;
        $this->app_model->ticketLogAction($ticket['id'], 'attachment', [
            'attachment_path' => $rel,
            'comment'         => basename($originalName), // display name; basename() strips any path components
        ]);
        activity_log('tickets', 'attach', 'ticket', (string) $ticket['alarm_id'], 'Attached "' . basename($originalName) . '" to ' . $ticket['alarm_id'], ['original_name' => basename($originalName), 'size_bytes' => (int) $file->getSize(), 'mime' => $mime]);
        return json_ok(['path' => $rel], 'File attached');
    }

    // Downloads a ticket attachment file.
    public function ticket_download($alarm_id, $action_id)
    {
        check_module_access('tickets', 'view');
        $r = $this->ticketLoad($alarm_id, false);
        if ($r instanceof \CodeIgniter\HTTP\RedirectResponse) {
            return $r;
        }
        $ticket = $r;

        $row = $this->app_model->ticketGetAttachment((int) $ticket['id'], (int) $action_id);

        if (empty($row) || empty($row['attachment_path'])) {
            return redirect()->to(site_url('tickets/detail/' . $ticket['alarm_id']))
                ->with('error', 'Attachment not found.');
        }

        $relative = ltrim((string) $row['attachment_path'], "/\\");
        if ($relative === '' || strpbrk($relative, "\0\r\n") !== false) {
            log_message('warning', 'pview alert >> download blocked (bad path): action_id=[' . $action_id . ']');
            return redirect()->to(site_url('tickets/detail/' . $ticket['alarm_id']))
                ->with('error', 'Attachment file missing.');
        }
        $abs  = realpath(WRITEPATH . $relative);
        $base = realpath(WRITEPATH . 'uploads');
        if ($abs === false || $base === false) {
            return redirect()->to(site_url('tickets/detail/' . $ticket['alarm_id']))
                ->with('error', 'Attachment file missing.');
        }
        $baseSep = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($abs, $baseSep) !== 0 || !is_file($abs)) {
            log_message('warning', 'pview alert >> download blocked (traversal): action_id=[' . $action_id . '], abs=[' . $abs . '], base=[' . $base . ']');
            return redirect()->to(site_url('tickets/detail/' . $ticket['alarm_id']))
                ->with('error', 'Attachment file missing.');
        }

        $name = basename($abs);
        if (!empty($row['comment'])) {
            $candidate = basename((string) $row['comment']);
            $candidate = preg_replace('/[\x00-\x1F\x7F"\\\\]+/', '_', (string) $candidate);
            if ($candidate !== '' && $candidate !== '.' && $candidate !== '..') {
                $name = $candidate;
            }
        }
        activity_log('tickets', 'download', 'ticket', (string) $ticket['alarm_id'], 'Downloaded attachment "' . $name . '" from ' . $ticket['alarm_id'], ['action_id' => (int) $action_id]);
        return $this->response->download($abs, null)->setFileName($name);
    }

    /** GET /tickets/flows_by_project/(:num) — returns active flows of a project. */
    public function ticket_flows_by_project($project_id)
    {
        check_module_access('tickets', 'view');
        $rows = $this->app_model->flowGetByProject((int) $project_id);
        return json_ok($rows);
    }

    /** GET /tickets/assignable_users/(:num) — L1 pool users for a flow's initial state. */
    public function ticket_assignable_users($flow_id)
    {
        check_module_access('tickets', 'add');
        $initial = $this->app_model->stateGetInitial((int) $flow_id);
        if (empty($initial)) {
            return json_ok([]);
        }
        $pool  = $this->app_model->stateLevelUsers($initial, 1);
        $users = [];
        if (!empty($pool)) {
            $users = $this->user_model->getByIds($pool);
        }
        $out   = [];
        foreach ($users as $u) {
            $out[] = ['id' => (string) $u['user_id'], 'name' => (string) $u['name']];
        }
        return json_ok($out);
    }

    /** GET /escalation/states_by_flow/(:num) — states JSON for escalation form. */
    public function escalation_states_by_flow($flow_id)
    {
        check_module_access('escalation', 'view');
        $rows = $this->app_model->stateGetAll((int) $flow_id);
        return json_ok($rows);
    }

    /** GET /tickets/data_table — DataTables server-side endpoint. */
    public function ticket_data_table()
    {
        $mode = (string) $this->request->getGet('mode');
        if ($mode !== 'all') {
            $mode = 'my';
        }
        if ($mode === 'all') {
            check_module_access('tickets_all', 'view');
        } else {
            check_module_access('tickets', 'view');
        }

        $draw   = (int) $this->request->getGet('draw');
        $start  = (int) $this->request->getGet('start');
        $length = (int) $this->request->getGet('length');

        $searchArr = $this->request->getGet('search');
        $search    = '';
        if (is_array($searchArr) && isset($searchArr['value'])) {
            $search = (string) $searchArr['value'];
        }

        $colNames = [
            0 => 't.alarm_id',
            1 => 't.title',
            2 => 't.alert_type',
            3 => 't.priority',
            4 => 's.name',
            5 => 't.current_level',
            6 => 'ua.name',
            7 => 't.state_entered_at',
            8 => 't.created_at',
            9 => 't.created_at', // actions fallback
        ];
        $orderArr = $this->request->getGet('order');
        $orderCol = 'created_at';
        $orderDir = 'desc';
        if (is_array($orderArr) && isset($orderArr[0]['column'])) {
            $idx = (int) $orderArr[0]['column'];
            if (isset($colNames[$idx])) {
                $orderCol = $colNames[$idx];
            }
            if (isset($orderArr[0]['dir']) && strtolower((string) $orderArr[0]['dir']) === 'asc') {
                $orderDir = 'asc';
            }
        }

        $filters = [
            'status'     => (string) $this->request->getGet('status'),
            'alert_type' => (string) $this->request->getGet('alert_type'),
            'priority'   => (string) $this->request->getGet('priority'),
            'f_from'     => trim((string) $this->request->getGet('f_from')),
            'f_to'       => trim((string) $this->request->getGet('f_to')),
        ];
        if ($mode === 'my') {
            $filters['user_id'] = (string) logged_user_id();
        }
        if ($mode === 'all') {
            $filters['project_id'] = (int) $this->request->getGet('project_id');
            $filters['flow_id']    = (int) $this->request->getGet('flow_id');
        }

        $q = (string) $this->request->getGet('q');
        if ($q !== '' && $search === '') {
            $search = $q;
        }

        $role        = logged_user_role();
        $isAdmin     = role_has_admin_scope($role);
        $args = [
            'start'          => $start,
            'length'         => $length,
            'search'         => $search,
            'order_col'      => $orderCol,
            'order_dir'      => $orderDir,
            'filters'        => $filters,
            'scope_user_pk'  => logged_user_id(),
            'scope_is_admin' => $isAdmin,
        ];
        $page = $this->app_model->ticketListForDataTables($args);

        $data = [];
        foreach ($page['rows'] as $t) {
            $level = 1;
            if (isset($t['current_level'])) {
                $level = (int) $t['current_level'];
            }
            $expires = tat_expires_at($t);
            $assignee = '-';
            if (!empty($t['assignee_name'])) {
                $assignee = $t['assignee_name'];
            }
            $created = '';
            if (!empty($t['created_at'])) {
                $createdTs = strtotime((string) $t['created_at']);
                if ($createdTs) {
                    $created = date('Y-m-d H:i', $createdTs);
                } else {
                    $created = (string) $t['created_at'];
                }
            }
            // DEMO: bulk checkbox hidden — $checkbox = '<input type="checkbox" class="form-check-input bulk-select" data-bulk-id="' . esc($t['alarm_id']) . '" aria-label="Select ticket">';
            $checkbox = '';
            $actions = '';
            $canReopen = false;
            if (logged_user_role() === ROLE_SUPER_ADMIN) {
                $canReopen = true;
            } else {
                $raisedBy = '';
                if (isset($t['raised_by'])) {
                    $raisedBy = (string) $t['raised_by'];
                }
                if ($raisedBy !== '' && $raisedBy === (string) logged_user_id()) {
                    $canReopen = true;
                }
            }
            if (isset($t['status']) && $t['status'] === 'resolved' && $canReopen) {
                $actions = '<button class="btn btn-sm btn-outline-warning list-reopen-btn" data-url="' . site_url('tickets/reopen/' . esc($t['alarm_id'])) . '" aria-label="Reopen ticket"><i class="bi bi-arrow-counterclockwise"></i> Reopen</button>';
            }
            $rawAlertType = '';
            if (isset($t['alert_type'])) {
                $rawAlertType = $t['alert_type'];
            }
            $rawPriority = '';
            if (isset($t['priority'])) {
                $rawPriority = $t['priority'];
            }
            $rawStateName = '-';
            if (isset($t['state_name'])) {
                $rawStateName = $t['state_name'];
            }

            $data[] = [
                'select'        => $checkbox,
                'alarm_id_html' => '<a href="' . site_url('tickets/detail/' . esc($t['alarm_id'])) . '" class="alarm-id">' . esc($t['alarm_id']) . '</a>',
                'title_html'    => '<div class="ticket-cell-title" title="' . esc($t['title']) . '">' . esc($t['title']) . '</div>',
                'severity'      => alert_badge($rawAlertType),
                'priority'      => priority_badge($rawPriority),
                'state'         => '<span class="state-text">' . esc($rawStateName) . '</span>',
                'level'         => level_badge($level),
                'assignee'      => esc($assignee),
                'tat'           => '<span class="tat-countdown" data-tat-expires="' . esc($expires) . '" data-tat-total-ms="' . (tat_total_minutes($t) * 60000) . '"></span>',
                'created_at'    => '<span class="created-time">' . esc($created) . '</span>',
                'actions'       => $actions,
            ];
        }

        return service('response')->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $page['total_all'],
            'recordsFiltered' => $page['total_filtered'],
            'data'            => $data,
        ]);
    }

    /** POST /tickets/saved/save — save the current filter set as a named preset. */
    public function tickets_saved_save()
    {
        check_isvalidated();
        $userId = (string) logged_user_id();
        if ($userId === '') {
            return json_fail('Not authenticated', 401);
        }

        $name = trim((string) $this->request->getPost('name'));
        $qs   = (string) $this->request->getPost('query_params');
        if ($name === '') {
            return json_fail('Name is required');
        }
        if (mb_strlen($name) > 100) {
            $name = mb_substr($name, 0, 100);
        }
        if (mb_strlen($qs) > 1000) {
            return json_fail('Query string is too long');
        }

        $id = $this->app_model->savedFilterSave($userId, $name, $qs, 'tickets');
        log_message('debug', "pview alert >> saved filter save: user_id=[" . $userId . "], name=[" . $name . "], filter_id=[" . $id . "]");
        activity_log('tickets', 'saved_filter_save', 'saved_filter', (string) $id, 'Saved filter "' . $name . '"', ['filter_name' => $name]);
        return json_ok(['id' => $id], 'Filter saved');
    }

    /** POST /tickets/saved/delete/(:num) — remove a saved filter preset. */
    public function tickets_saved_delete($id)
    {
        check_module_access('tickets', 'view');
        $userId = (string) logged_user_id();
        if ($userId === '') {
            return json_fail('Not authenticated', 401);
        }
        $this->app_model->savedFilterDelete((int) $id, $userId);
        log_message('debug', "pview alert >> saved filter delete: id=[" . $id . "], user_id=[" . $userId . "]");
        activity_log('tickets', 'saved_filter_delete', 'saved_filter', (string) $id, 'Removed saved filter');
        return json_ok([], 'Filter removed');
    }

    /** POST /tickets/bulk — bulk resolve or close a set of tickets by ID. */
    // DEMO: tickets_bulk() hidden — uncomment to restore
    /*
    public function tickets_bulk()
    {
        check_module_access('tickets', 'edit');
        try {
            $body = $this->request->getJSON(true) ?: [];
        } catch (\Exception $e) {
            // Log the bad request so it appears in the activity log and CI4 logs.
            log_message('warning', 'tickets_bulk: request body parse failed — {msg} — IP: {ip}', [
                'msg' => $e->getMessage(),
                'ip'  => $this->request->getIPAddress(),
            ]);
            activity_log(
                'tickets',
                'bulk_error',
                null,
                null,
                'Bulk action request body could not be parsed: ' . $e->getMessage()
            );
            $body = $this->request->getPost();
        }
        if (empty($body)) {
            $body = $this->request->getPost();
        }

        $action = '';
        if (isset($body['action'])) {
            $action = (string) $body['action'];
        }
        if (!in_array($action, ['resolve', 'close'], true)) {
            return json_fail('Unknown bulk action');
        }

        $ids = [];
        if (isset($body['ids']) && is_array($body['ids'])) {
            foreach ($body['ids'] as $v) {
                $s = safe_alarm_id((string) $v);
                if ($s !== '') {
                    $ids[] = $s;
                }
            }
            $ids = array_values(array_unique($ids));
        }
        if (empty($ids)) {
            return json_fail('No tickets selected');
        }
        if (count($ids) > 200) {
            return json_fail('Too many tickets selected (max 200 per batch)');
        }

        $processed = 0;
        $skipped   = 0;
        $failed    = 0;

        foreach ($ids as $id) {
            $row = $this->app_model->ticketGetByAlarm($id);
            if (empty($row)) {
                $skipped++;
                continue;
            }
            if (!verify_ticket_access($row)) {
                $skipped++;
                continue;
            }
            if ($action === 'resolve' && in_array($row['status'], ['resolved', 'closed'], true)) {
                $skipped++;
                continue;
            }
            if ($action === 'close' && $row['status'] === 'closed') {
                $skipped++;
                continue;
            }

            $update = [];
            $logType = $action;
            if ($action === 'resolve') {
                $update['status'] = 'resolved';
                $update['resolved_at'] = date('Y-m-d H:i:s');
                $logType = 'resolved';
            }
            if ($action === 'close') {
                $update['status'] = 'closed';
                $update['closed_at'] = date('Y-m-d H:i:s');
                $logType = 'closed';
            }

            $ok = $this->app_model->ticketUpdate((int) $row['id'], $update);
            if (!$ok) {
                $failed++;
                continue;
            }
            $this->app_model->ticketLogAction((int) $row['id'], $logType, [
                'performed_by' => logged_user_id(),
                'comment'      => 'Bulk ' . $action . ' from list',
            ]);
            $processed++;
        }

        $msg = $processed . ' ticket(s) ' . $action . 'd';
        if ($skipped > 0) {
            $msg .= ', ' . $skipped . ' skipped';
        }
        if ($failed > 0) {
            $msg .= ', ' . $failed . ' failed';
        }
        log_message('debug', "pview alert >> tickets bulk: action=[" . $action . "], processed=[" . $processed . "], skipped=[" . $skipped . "], failed=[" . $failed . "], by=[" . logged_user_id() . "]");
        activity_log(
            'tickets',
            'bulk',
            null,
            null,
            'Bulk ' . $action . ': ' . $processed . ' processed, ' . $skipped . ' skipped, ' . $failed . ' failed',
            ['action' => $action, 'processed' => $processed, 'skipped' => $skipped, 'failed' => $failed, 'ids' => $ids]
        );
        return json_ok([
            'processed' => $processed,
            'skipped'   => $skipped,
            'failed'    => $failed,
        ], $msg);
    }
    */

    public function tickets_export()
    {
        $mode = (string) $this->request->getGet('mode');
        if ($mode !== 'all') {
            $mode = 'my';
        }
        if ($mode === 'all') {
            check_module_access('tickets_all', 'view');
        } else {
            check_module_access('tickets', 'view');
        }

        $filters = [
            'status'     => (string) $this->request->getGet('status'),
            'alert_type' => (string) $this->request->getGet('alert_type'),
            'priority'   => (string) $this->request->getGet('priority'),
            'search'     => (string) $this->request->getGet('q'),
            'f_from'     => trim((string) $this->request->getGet('f_from')),
            'f_to'       => trim((string) $this->request->getGet('f_to')),
        ];
        if ($mode === 'my') {
            $filters['user_id'] = (string) logged_user_id();
        }
        if ($mode === 'all') {
            $filters['project_id'] = (int) $this->request->getGet('project_id');
            $filters['flow_id']    = (int) $this->request->getGet('flow_id');
        }

        $rows = $this->app_model->ticketGetAll($filters);
        if (count($rows) > 5000) {
            $rows = array_slice($rows, 0, 5000);
        }

        $filename = 'tickets-' . date('Ymd-His') . '.csv';
        activity_log('tickets', 'export', null, null, 'Exported ' . count($rows) . ' tickets (' . $mode . ')', ['mode' => $mode, 'row_count' => count($rows), 'filters' => $filters]);

        $userSelectedCols = (string) $this->request->getGet('export_cols');
        export_csv_data($filename, 'tickets', $rows, $userSelectedCols);
    }

    // Authenticates external API requests using API keys.
    private function apiAuthenticate()
    {
        $key = $this->request->getHeaderLine('X-API-KEY');
        if ($key === '') {
            return false;
        }
        $row = $this->app_model->apiKeyGetByKey($key);
        if (empty($row)) {
            return false;
        }
        $this->app_model->apiKeyTouchLastUsed((int) $row['id']);
        $this->api_key_row = $row;
        return true;
    }

    // Returns standard access denied response for API requests.
    private function apiDeny()
    {
        return service('response')->setStatusCode(401)->setJSON([
            'success' => false,
            'message' => 'Unauthorized — missing or invalid X-API-KEY',
        ]);
    }

    // Returns 429 response when rate-limited, null otherwise.
    private function apiRateLimit()
    {
        if (empty($this->api_key_row)) {
            return null;
        }
        $endpoint = (string) $this->request->getUri()->getPath();
        $check = api_rate_check((int) $this->api_key_row['id'], $endpoint);
        if (!empty($check['allowed'])) {
            return null;
        }
        $retry = 60;
        if (isset($check['retry_after_seconds'])) {
            $retry = (int) $check['retry_after_seconds'];
        }
        log_message('warning', 'pview alert >> API rate-limited: api_key_id=[' . $this->api_key_row['id'] . '], endpoint=[' . $endpoint . '], retry_after=[' . $retry . ']');
        return service('response')
            ->setStatusCode(429)
            ->setHeader('Retry-After', (string) $retry)
            ->setJSON([
                'success'             => false,
                'message'             => 'Rate limit exceeded. Try again later.',
                'retry_after_seconds' => $retry,
            ]);
    }

    /** POST /api/raise — external system raises an alert. */
    public function api_raise()
    {
        if (!$this->apiAuthenticate()) {
            return $this->apiDeny();
        }
        $rate = $this->apiRateLimit();
        if ($rate !== null) {
            return $rate;
        }
        try {
            $body = $this->request->getJSON(true);
            if (!$body) {
                $body = [];
            }
        } catch (\Exception $e) {
            log_message('warning', 'api_raise: request body parse failed — {msg} — IP: {ip}', [
                'msg' => $e->getMessage(),
                'ip'  => $this->request->getIPAddress(),
            ]);
            $body = [];
        }
        if (empty($body)) {
            $body = $this->request->getPost();
        }
        $project_id = 0;
        if (isset($body['project_id'])) {
            $project_id = (int) $body['project_id'];
        }
        $flow_id = 0;
        if (isset($body['flow_id'])) {
            $flow_id = (int) $body['flow_id'];
        }
        $rawTitle = '';
        if (isset($body['title'])) {
            $rawTitle = $body['title'];
        }
        $title      = trim((string) $rawTitle);
        if ($project_id === 0 || $flow_id === 0 || $title === '') {
            return service('response')->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'project_id, flow_id and title are required',
            ]);
        }

        // The API key is bound to a specific project — reject mismatches.
        if ((int) $this->api_key_row['project_id'] !== $project_id) {
            return service('response')->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'API key is not authorised for this project_id',
            ]);
        }

        $flow = $this->app_model->flowGetById($flow_id);
        if (empty($flow) || (int) $flow['project_id'] !== $project_id) {
            return service('response')->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'flow_id does not belong to the given project',
            ]);
        }

        $rawAlertType = 'info';
        if (isset($body['alert_type'])) {
            $rawAlertType = $body['alert_type'];
        }
        $alertType = strtolower((string) $rawAlertType);
        if (!in_array($alertType, ['info', 'major', 'critical'], true)) {
            $alertType = 'info';
        }
        $rawPriority = 'medium';
        if (isset($body['priority'])) {
            $rawPriority = $body['priority'];
        }
        $priority = strtolower((string) $rawPriority);
        if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            $priority = 'medium';
        }

        $initial = $this->app_model->stateGetInitial($flow_id);
        if (empty($initial)) {
            return service('response')->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Flow has no states',
            ]);
        }

        $alarmId   = generate_alarm_id();

        $alertDefId = null;
        if (isset($body['alert_def_id'])) {
            $alertDefId = $body['alert_def_id'];
        }

        $rawDescription = '';
        if (isset($body['description'])) {
            $rawDescription = $body['description'];
        }
        $description = mb_substr((string) $rawDescription, 0, 10000);

        $rawSourceSystem = '';
        if (isset($body['source_system'])) {
            $rawSourceSystem = $body['source_system'];
        }
        $sourceSystem = mb_substr((string) $rawSourceSystem, 0, 100);

        $ticket_id = $this->app_model->ticketSave([
            'alarm_id'         => $alarmId,
            'project_id'       => $project_id,
            'flow_id'          => $flow_id,
            'alert_def_id'     => $alertDefId,
            'title'            => mb_substr($title, 0, 300),
            'description'      => $description,
            'alert_type'       => $alertType,
            'priority'         => $priority,
            'current_state_id' => (int) $initial['id'],
            'current_level'    => 1,
            'status'           => 'open',
            'source'           => 'api',
            'source_system'    => $sourceSystem,
        ]);

        $actorName = 'system';
        if (isset($body['source_system'])) {
            $actorName = $body['source_system'];
        }

        $this->app_model->ticketLogAction($ticket_id, 'created', [
            'comment'             => 'API raised by ' . $actorName,
            'to_state_id'         => (int) $initial['id'],
            'to_level'            => 1,
            'performed_by_system' => (string) $actorName,
        ]);

        $emails = [];
        try {
            $level_users = $this->app_model->stateLevelUsers($initial, 1);
            if (!empty($level_users)) {
                $eventDesc = '';
                if (isset($body['description'])) {
                    $eventDesc = $body['description'];
                }

                notify_ticket_event('created', [
                    'id'            => $ticket_id,
                    'alarm_id'      => $alarmId,
                    'title'         => $title,
                    'description'   => $eventDesc,
                    'alert_type'    => $alertType,
                    'priority'      => $priority,
                    'state_name'    => $initial['name'],
                    'current_level' => 1,
                ], ['actor_name' => $actorName], $level_users);
                $rows = $this->user_model->getByIds($level_users);
                foreach ($rows as $r) {
                    $emails[] = $r['email'];
                }
            }
        } catch (\Throwable $e) {
            log_message('error', "pview alert >> API raise notify FAILED: error=[" . $e->getMessage() . "]");
        }

        activity_log('api', 'raise', 'ticket', $alarmId, 'API raised ticket ' . $alarmId . ' via project #' . $project_id, ['flow_id' => $flow_id, 'alert_type' => $alertType, 'priority' => $priority], ['user_id' => 'api:' . $this->api_key_row['name'], 'user_name' => (string) $this->api_key_row['name'], 'user_role' => 'api', 'project_id' => $project_id]);

        return service('response')->setStatusCode(201)->setJSON([
            'success'        => true,
            'alarm_id'       => $alarmId,
            'ticket_id'      => $ticket_id,
            'message'        => 'Alert raised successfully',
            'current_state'  => $initial['name'],
            'notified_users' => $emails,
        ]);
    }

    /** GET /api/alert/{alarm_id} */
    public function api_show($alarm_id)
    {
        if (!$this->apiAuthenticate()) {
            return $this->apiDeny();
        }
        $rate = $this->apiRateLimit();
        if ($rate !== null) {
            return $rate;
        }
        $clean = safe_alarm_id($alarm_id);
        if (!$clean) {
            return service('response')->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Invalid alarm_id']);
        }
        $t = $this->app_model->ticketGetByAlarm($clean);
        if (empty($t)) {
            return service('response')->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Not found']);
        }

        if ((int) $t['project_id'] !== (int) $this->api_key_row['project_id']) {
            return service('response')->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'API key is not authorised for this ticket',
            ]);
        }

        activity_log('api', 'show', 'ticket', $clean, 'API read ticket ' . $clean, [], ['user_id' => 'api:' . $this->api_key_row['name'], 'user_name' => (string) $this->api_key_row['name'], 'user_role' => 'api', 'project_id' => (int) $t['project_id']]);

        $entered = strtotime((string) $t['state_entered_at']);
        $tatKey2 = 'l' . (int) $t['current_level'] . '_tat_minutes';
        $tat = 60;
        if (isset($t[$tatKey2])) {
            $tat = (int) $t[$tatKey2];
        }
        $remaining = max(0, intval(($entered + $tat * 60 - time()) / 60));

        return service('response')->setJSON([
            'success'              => true,
            'alarm_id'             => $t['alarm_id'],
            'title'                => $t['title'],
            'status'               => $t['status'],
            'current_state'        => $t['state_name'],
            'current_level'        => (int) $t['current_level'],
            'alert_type'           => $t['alert_type'],
            'priority'             => $t['priority'],
            'created_at'           => $t['created_at'],
            'tat_remaining_minutes' => $remaining,
            'activity'             => $this->app_model->ticketTimeline((int) $t['id']),
        ]);
    }

    /** POST /api/alert/{alarm_id}/update */
    public function api_update($alarm_id)
    {
        if (!$this->apiAuthenticate()) {
            return $this->apiDeny();
        }
        $rate = $this->apiRateLimit();
        if ($rate !== null) {
            return $rate;
        }
        $clean = safe_alarm_id($alarm_id);
        if (!$clean) {
            return service('response')->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Invalid alarm_id']);
        }
        try {
            $body = $this->request->getJSON(true);
            if (!$body) {
                $body = [];
            }
        } catch (\Exception $e) {
            log_message('warning', 'api_update: request body parse failed — {msg} — IP: {ip}', [
                'msg' => $e->getMessage(),
                'ip'  => $this->request->getIPAddress(),
            ]);
            $body = [];
        }
        if (empty($body)) {
            $body = $this->request->getPost();
        }
        $t = $this->app_model->ticketGetByAlarm($clean);
        if (empty($t)) {
            return service('response')->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Not found']);
        }

        if ((int) $t['project_id'] !== (int) $this->api_key_row['project_id']) {
            return service('response')->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'API key is not authorised for this ticket',
            ]);
        }

        $rawAction = '';
        if (isset($body['action'])) {
            $rawAction = $body['action'];
        }
        $action  = (string) $rawAction;
        $rawComment = '';
        if (isset($body['comment'])) {
            $rawComment = $body['comment'];
        }
        $comment = (string) $rawComment;
        $rawSys = '';
        if (isset($body['performed_by_system'])) {
            $rawSys = $body['performed_by_system'];
        }
        $sys     = (string) $rawSys;

        if ($this->ticketIsTerminal($t)) {
            return service('response')->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Ticket is already ' . $t['status']]);
        }

        if ($action === 'resolved') {
            $resolveData = ['status' => 'resolved', 'resolved_at' => date('Y-m-d H:i:s')];
            if (empty($t['actual_end_date'])) {
                $resolveData['actual_end_date'] = date('Y-m-d');
            }
            $this->app_model->ticketUpdate((int) $t['id'], $resolveData);
            $this->app_model->ticketLogAction((int) $t['id'], 'resolved', ['comment' => $comment, 'performed_by_system' => $sys]);
        } elseif ($action === 'closed') {
            $closeData = ['status' => 'closed', 'closed_at' => date('Y-m-d H:i:s')];
            if (empty($t['actual_end_date'])) {
                $closeData['actual_end_date'] = date('Y-m-d');
            }
            $this->app_model->ticketUpdate((int) $t['id'], $closeData);
            $this->app_model->ticketLogAction((int) $t['id'], 'closed', ['comment' => $comment, 'performed_by_system' => $sys]);
        } elseif ($action === 'comment') {
            $this->app_model->ticketLogAction((int) $t['id'], 'api_update', ['comment' => $comment, 'performed_by_system' => $sys]);
        } else {
            return service('response')->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Unknown action']);
        }

        activity_log('api', 'update', 'ticket', $clean, 'API ' . $action . ' on ticket ' . $clean, ['comment' => $comment, 'performed_by_system' => $sys], ['user_id' => 'api:' . $this->api_key_row['name'], 'user_name' => (string) $this->api_key_row['name'], 'user_role' => 'api', 'project_id' => (int) $t['project_id']]);

        return service('response')->setJSON([
            'success'    => true,
            'alarm_id'   => $clean,
            'new_status' => $action,
            'message'    => 'Alert updated successfully',
        ]);
    }

    /** GET /api/alerts */
    public function api_index()
    {
        if (!$this->apiAuthenticate()) {
            return $this->apiDeny();
        }
        $rate = $this->apiRateLimit();
        if ($rate !== null) {
            return $rate;
        }
        $filters = [
            'project_id' => (int) $this->api_key_row['project_id'],
            'status'     => (string) $this->request->getGet('status'),
            'alert_type' => (string) $this->request->getGet('alert_type'),
        ];
        $rows  = $this->app_model->ticketGetAll($filters);

        $limit = (int) $this->request->getGet('limit');
        if ($limit <= 0) {
            $limit = 50;
        }
        if ($limit > 200) {
            $limit = 200;
        }
        $offset = (int) $this->request->getGet('offset');
        if ($offset < 0) {
            $offset = 0;
        }
        $rows = array_slice($rows, $offset, $limit);

        activity_log('api', 'index', null, null, 'API listed ' . count($rows) . ' alert(s) for project #' . $filters['project_id'], ['status' => $filters['status'], 'alert_type' => $filters['alert_type']], ['user_id' => 'api:' . $this->api_key_row['name'], 'user_name' => (string) $this->api_key_row['name'], 'user_role' => 'api', 'project_id' => $filters['project_id']]);

        return service('response')->setJSON([
            'success' => true,
            'count'   => count($rows),
            'data'    => $rows,
        ]);
    }

    /** GET /api/flows */
    public function api_flows()
    {
        if (!$this->apiAuthenticate()) {
            return $this->apiDeny();
        }
        $rate = $this->apiRateLimit();
        if ($rate !== null) {
            return $rate;
        }
        $project_id = (int) $this->api_key_row['project_id'];
        $rows = $this->app_model->flowGetByProject($project_id);

        activity_log('api', 'flows', null, null, 'API listed ' . count($rows) . ' flow(s) for project #' . $project_id, [], ['user_id' => 'api:' . $this->api_key_row['name'], 'user_name' => (string) $this->api_key_row['name'], 'user_role' => 'api', 'project_id' => $project_id]);

        return service('response')->setJSON(['success' => true, 'data' => $rows]);
    }

    // Returns JSON dataset for the dashboard ticket trend chart.
    public function dashboard_trend()
    {
        check_module_access('dashboard', 'view');

        $rangesRaw = app_setting_csv('dashboard_trend_ranges', ['7', '15', '30']);
        $allowed = [];
        foreach ($rangesRaw as $r) {
            $n = (int) $r;
            if ($n >= 1 && $n <= 365) {
                $allowed[] = $n;
            }
        }
        if (empty($allowed)) {
            $allowed = [7, 15, 30];
        }

        $range = (int) $this->request->getGet('range');
        if (!in_array($range, $allowed, true)) {
            $range = $allowed[0];
        }

        $role    = logged_user_role();
        $isAdmin = role_has_admin_scope($role);
        $userPk  = logged_user_id();

        $trend = $this->app_model->ticketTrendByRange($range, $userPk, $isAdmin);
        return json_ok([
            'range'  => $range,
            'labels' => $trend['labels'],
            'values' => $trend['values'],
        ]);
    }

    // Returns the number of actionable tickets for polling.
    public function actionable_count()
    {
        check_isvalidated();
        $userId  = (string) session('user_id');
        $role    = (string) session('user_role');
        $isAdmin = role_has_admin_scope($role);

        $counts = $this->app_model->ticketCountActionable($userId, $isAdmin);
        return json_ok($counts);
    }

    /** GET /notifications/recent — actionable tickets for the bell-icon dropdown. */
    public function notifications_recent()
    {
        check_isvalidated();
        $userId  = (string) session('user_id');
        $role    = (string) session('user_role');
        $isAdmin = role_has_admin_scope($role);

        $rows = $this->app_model->actionableTicketsForUser($userId, $isAdmin, 10);

        $items = [];
        foreach ($rows as $r) {
            $when = '';
            if (!empty($r['state_entered_at'])) {
                $when = (string) $r['state_entered_at'];
            } elseif (!empty($r['created_at'])) {
                $when = (string) $r['created_at'];
            }
            $url = '';
            if (!empty($r['alarm_id'])) {
                $url = site_url('tickets/detail/' . $r['alarm_id']);
            }
            $alarmIdVal = '';
            if (isset($r['alarm_id'])) {
                $alarmIdVal = $r['alarm_id'];
            }
            $titleVal = '';
            if (isset($r['title'])) {
                $titleVal = $r['title'];
            }
            $alertTypeVal = 'info';
            if (isset($r['alert_type'])) {
                $alertTypeVal = $r['alert_type'];
            }
            $statusVal = '';
            if (isset($r['status'])) {
                $statusVal = $r['status'];
            }
            $currentLevelVal = 1;
            if (isset($r['current_level'])) {
                $currentLevelVal = $r['current_level'];
            }
            $stateNameVal = '';
            if (isset($r['state_name'])) {
                $stateNameVal = $r['state_name'];
            }

            $items[] = [
                'id'            => (int) $r['id'],
                'alarm_id'      => (string) $alarmIdVal,
                'title'         => (string) $titleVal,
                'alert_type'    => (string) $alertTypeVal,
                'ticket_status' => (string) $statusVal,
                'level'         => (int)    $currentLevelVal,
                'state_name'    => (string) $stateNameVal,
                'when'          => $when,
                'url'           => $url,
            ];
        }

        // @mentions surfaced from notification_logs. Matched by the
        // recipient's email (notification_logs has no user_id column) and
        // the '[MENTION]' subject prefix that parse_mentions emits. Capped
        // to 7 days + 10 rows so an old ticket can't keep popping up in
        // the bell forever. One row per ticket — multiple mentions on the
        // same alarm collapse to the most recent.
        $mentions = [];
        $userEmail = (string) session('user_email');
        if ($userEmail !== '') {
            $cutoff = date('Y-m-d H:i:s', strtotime('-7 days'));
            $rawMentions = $this->app_model->notificationMentionsForUser($userEmail, $cutoff, 50);

            $seenTickets = [];
            foreach ($rawMentions as $m) {
                $ticketIdVal = 0;
                if (isset($m['ticket_id'])) {
                    $ticketIdVal = $m['ticket_id'];
                }
                $tid = (int) $ticketIdVal;
                if ($tid <= 0 || isset($seenTickets[$tid])) {
                    continue;
                }
                $seenTickets[$tid] = true;

                // Subject pattern: "[MENTION] {alarm_id} — {name} mentioned you"
                // Pull the mentioner name out so the bell row is readable.
                $fromName = '';
                if (!empty($m['subject'])) {
                    $subj = (string) $m['subject'];
                    if (preg_match('/—\s*(.+?)\s+mentioned you/u', $subj, $sm)) {
                        $fromName = trim($sm[1]);
                    }
                }

                $url = '';
                if (!empty($m['alarm_id'])) {
                    $url = site_url('tickets/detail/' . $m['alarm_id']);
                }
                $mAlarmIdVal = '';
                if (isset($m['alarm_id'])) {
                    $mAlarmIdVal = $m['alarm_id'];
                }
                $mTitleVal = '';
                if (isset($m['title'])) {
                    $mTitleVal = $m['title'];
                }
                $mAlertTypeVal = 'info';
                if (isset($m['alert_type'])) {
                    $mAlertTypeVal = $m['alert_type'];
                }

                $mentions[] = [
                    'id'         => (int) $m['id'],
                    'alarm_id'   => (string) $mAlarmIdVal,
                    'title'      => (string) $mTitleVal,
                    'alert_type' => (string) $mAlertTypeVal,
                    'from_name'  => $fromName,
                    'when'       => (string) $m['created_at'],
                    'url'        => $url,
                ];

                if (count($mentions) >= 10) {
                    break;
                }
            }
        }

        return json_ok(['items' => $items, 'mentions' => $mentions]);
    }

    // Creates the modules table on first use; safe to call repeatedly.
    private function ensureModulesTable()
    {
        try {
            $check = $this->db->query("SHOW TABLES LIKE 'modules'")->getResultArray();
            if (!empty($check)) {
                return;
            }
            $this->db->query("CREATE TABLE IF NOT EXISTS `modules` (
              `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `module_key`  VARCHAR(50)  NOT NULL,
              `name`        VARCHAR(100) NOT NULL,
              `description` VARCHAR(255) NOT NULL DEFAULT '',
              `is_builtin`  TINYINT(1)   NOT NULL DEFAULT 0,
              `sort_order`  INT          NOT NULL DEFAULT 100,
              `created_at`  DATETIME     NOT NULL,
              `created_by`  VARCHAR(100)          DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `idx_module_key` (`module_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            log_message('debug', 'pview alert >> modules table created (run setup_defaults.php to seed)');
        } catch (\Throwable $e) {
            log_message('error', 'pview alert >> ensureModulesTable failed: ' . $e->getMessage());
        }
    }

    // Renders the Module Permissions Control Panel.
    public function module_control_panel()
    {
        check_module_access('module_control_panel', 'view');
        activity_log('module_control_panel', 'view', null, null, 'Opened Module Control Panel');

        $this->ensureModulesTable();

        $tableExists = $this->app_model->modulePermissionsTableExists();

        if ($tableExists === false) {
            try {
                $this->db->query("CREATE TABLE IF NOT EXISTS `module_permissions` (
                  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `role` VARCHAR(50) NOT NULL,
                  `module_key` VARCHAR(50) NOT NULL,
                  `can_view` TINYINT(1) NOT NULL DEFAULT 0,
                  `can_add` TINYINT(1) NOT NULL DEFAULT 0,
                  `can_edit` TINYINT(1) NOT NULL DEFAULT 0,
                  `can_delete` TINYINT(1) NOT NULL DEFAULT 0,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `idx_role_module` (`role`, `module_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

                $registry = module_registry();
                $roleRows = $this->user_model->getAllRoles();
                $defaults = [];

                foreach ($roleRows as $rRow) {
                    $rKey = (string) $rRow['role_key'];
                    foreach ($registry as $mKey => $mInfo) {
                        $defaults[] = [$rKey, $mKey, 0, 0, 0, 0];
                    }
                }

                foreach ($defaults as $d) {
                    $this->db->table('module_permissions')->insert([
                        'role'       => $d[0],
                        'module_key' => $d[1],
                        'can_view'   => $d[2],
                        'can_add'    => $d[3],
                        'can_edit'   => $d[4],
                        'can_delete' => $d[5],
                    ]);
                }
                $tableExists = true;
            } catch (\Throwable $e) {
                log_message('error', "pview alert >> modulePermissions table creation failed: " . $e->getMessage());
            }
        }

        $rows = [];
        if ($tableExists === true) {
            $rows = $this->app_model->modulePermissionsGetAll();
        }

        $rolesAllRaw = $this->user_model->getAllRoles();
        $rolesAll = [];
        foreach ($rolesAllRaw as $r) {
            if ((string) $r['role_key'] !== ROLE_SUPER_ADMIN) {
                $rolesAll[] = $r;
            }
        }

        $data = [
            'title'       => 'Module Control Panel',
            'permissions' => $rows,
            'rolesAll'    => $rolesAll,
            'tableExists' => $tableExists,
        ];

        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('module_control_panel', $data);
        echo view('templates/footer');
    }

    // Saves updated module permissions for roles.
    public function module_control_panel_save()
    {
        check_module_access('module_control_panel', 'edit');

        $posted = $this->request->getPost('perms');
        if (empty($posted)) {
            $posted = [];
        }

        $roleRows = $this->user_model->getAllRoles();
        $roles = [];
        foreach ($roleRows as $rr) {
            $roles[] = (string) $rr['role_key'];
        }
        $registry = module_registry();
        $modules = [];
        foreach ($registry as $mKey => $mInfo) {
            $modules[] = $mKey;
        }

        $oldPermsRaw = $this->app_model->modulePermissionsGetAll();
        $oldPerms = [];
        foreach ($oldPermsRaw as $r) {
            $oldPerms[$r['role']][$r['module_key']] = [
                'view'   => (int) $r['can_view'],
                'add'    => (int) $r['can_add'],
                'edit'   => (int) $r['can_edit'],
                'delete' => (int) $r['can_delete'],
            ];
        }

        $newPermsAll = [];
        foreach ($roles as $role) {
            if ($role === ROLE_SUPER_ADMIN) {
                continue;
            }
            $rolePerms = [];
            foreach ($modules as $module) {
                if ($module === 'settings') {
                    continue;
                }
                $rolePerms[$module] = [
                    'view'   => 0,
                    'add'    => 0,
                    'edit'   => 0,
                    'delete' => 0
                ];

                if (isset($posted[$role][$module]['view'])) {
                    $rolePerms[$module]['view'] = 1;
                }
                if (isset($posted[$role][$module]['add'])) {
                    $rolePerms[$module]['add'] = 1;
                }
                if (isset($posted[$role][$module]['edit'])) {
                    $rolePerms[$module]['edit'] = 1;
                }
                if (isset($posted[$role][$module]['delete'])) {
                    $rolePerms[$module]['delete'] = 1;
                }
            }
            $newPermsAll[$role] = $rolePerms;
            $this->app_model->modulePermissionsSave($role, $rolePerms);
        }

        $diff = [];
        foreach ($newPermsAll as $role => $modulesNew) {
            foreach ($modulesNew as $module => $newP) {
                $oldP = ['view' => 0, 'add' => 0, 'edit' => 0, 'delete' => 0];
                if (isset($oldPerms[$role][$module])) {
                    $oldP = $oldPerms[$role][$module];
                }
                $changed = [];
                foreach (['view', 'add', 'edit', 'delete'] as $act) {
                    $oVal = 0;
                    if (isset($oldP[$act])) {
                        $oVal = $oldP[$act];
                    }
                    $o = (int) $oVal;

                    $nVal = 0;
                    if (isset($newP[$act])) {
                        $nVal = $newP[$act];
                    }
                    $n = (int) $nVal;
                    if ($o !== $n) {
                        $changed[$act] = $o . '→' . $n;
                    }
                }
                if (!empty($changed)) {
                    $diff[$role . '.' . $module] = $changed;
                }
            }
        }

        log_message('debug', "pview alert >> module permissions save: by=[" . logged_user_id() . "], roles=[" . implode(',', $roles) . "], changes=[" . count($diff) . "]");
        activity_log('module_control_panel', 'update', null, null, 'Updated module permissions for ' . count($roles) . ' role(s) (' . count($diff) . ' change(s))', ['roles' => $roles, 'diff' => $diff]);

        $this->session->setFlashdata('success', 'Module permissions updated successfully.');
        return redirect()->to(site_url('module_control_panel'));
    }

    /** POST /module_control_panel/module/add — register a new custom module. */
    public function module_add()
    {
        check_module_access('module_control_panel', 'add');

        $moduleKey   = trim((string) $this->request->getPost('module_key'));
        $name        = trim((string) $this->request->getPost('name'));
        $description = trim((string) $this->request->getPost('description'));
        $sortOrder   = (int) $this->request->getPost('sort_order');
        $category    = trim((string) $this->request->getPost('category'));
        $icon        = trim((string) $this->request->getPost('icon'));
        $uriPath     = trim((string) $this->request->getPost('uri_path'));
        $permModule  = trim((string) $this->request->getPost('permission_module_key'));
        $permAction  = trim((string) $this->request->getPost('permission_action'));

        if ($moduleKey === '' || $name === '') {
            $this->session->setFlashdata('error', 'Module key and name are required.');
            return redirect()->to(site_url('module_control_panel'));
        }

        if (!preg_match('/^[a-z][a-z0-9_]{1,49}$/', $moduleKey)) {
            $this->session->setFlashdata('error', 'Module key must start with a lowercase letter and contain only lowercase letters, digits, or underscore (2-50 chars).');
            return redirect()->to(site_url('module_control_panel'));
        }

        $existing = $this->db->table('modules')
            ->where('module_key', $moduleKey)
            ->countAllResults();
        if ($existing > 0) {
            $this->session->setFlashdata('error', 'A module with that key already exists.');
            return redirect()->to(site_url('module_control_panel'));
        }

        if ($sortOrder < 1) {
            $sortOrder = 100;
        }
        if ($category === '') {
            $category = 'General';
        }
        if ($icon === '') {
            $icon = 'bi-circle';
        }
        if ($uriPath === '') {
            $uriPath = null;
        }
        if ($permModule === '') {
            $permModule = $moduleKey;
        }
        if ($permAction === '') {
            $permAction = 'view';
        }

        $this->db->table('modules')->insert([
            'module_key'            => $moduleKey,
            'permission_module_key' => $permModule,
            'permission_action'     => $permAction,
            'name'                  => $name,
            'category'              => $category,
            'icon'                  => $icon,
            'uri_path'              => $uriPath,
            'show_in_menu'          => 1,
            'description'           => $description,
            'is_builtin'            => 0,
            'sort_order'            => $sortOrder,
            'created_at'            => date('Y-m-d H:i:s'),
            'created_by'            => (string) logged_user_id(),
        ]);

        $roles = $this->user_model->getAllRoles();
        foreach ($roles as $role) {
            $exists = $this->db->table('module_permissions')
                ->where('role', (string) $role['role_key'])
                ->where('module_key', $permModule)
                ->countAllResults();
            if ($exists === 0) {
                $this->db->table('module_permissions')->insert([
                    'role'       => (string) $role['role_key'],
                    'module_key' => $permModule,
                    'can_view'   => 0,
                    'can_add'    => 0,
                    'can_edit'   => 0,
                    'can_delete' => 0,
                ]);
            }
        }

        log_message('debug', "pview alert >> module_add: key=[" . $moduleKey . "], by=[" . logged_user_id() . "]");
        activity_log('module_control_panel', 'module_add', 'module', $moduleKey, 'Added module "' . $name . '" (' . $moduleKey . ')', ['module_key' => $moduleKey, 'name' => $name, 'sort_order' => $sortOrder]);

        $this->session->setFlashdata('success', 'Module "' . $name . '" added. Grant role access in the permission grid above.');
        return redirect()->to(site_url('module_control_panel'));
    }

    /** POST /module_control_panel/module/delete/(:any) — remove a custom module. */
    public function module_delete($module_key)
    {
        check_module_access('module_control_panel', 'delete');

        $module_key = (string) $module_key;
        $row = $this->db->table('modules')
            ->where('module_key', $module_key)
            ->get()->getRowArray();

        if (empty($row)) {
            $this->session->setFlashdata('error', 'Module not found.');
            return redirect()->to(site_url('module_control_panel'));
        }

        if ((int) $row['is_builtin'] === 1) {
            $this->session->setFlashdata('error', 'Built-in modules cannot be deleted.');
            return redirect()->to(site_url('module_control_panel'));
        }

        $name = (string) $row['name'];

        $this->db->table('modules')->where('module_key', $module_key)->delete();
        $this->db->table('module_permissions')->where('module_key', $module_key)->delete();

        log_message('debug', "pview alert >> module_delete: key=[" . $module_key . "], by=[" . logged_user_id() . "]");
        activity_log('module_control_panel', 'module_delete', 'module', $module_key, 'Removed module "' . $name . '" (' . $module_key . ')', ['module_key' => $module_key, 'name' => $name]);

        $this->session->setFlashdata('success', 'Module "' . $name . '" removed.');
        return redirect()->to(site_url('module_control_panel'));
    }

    /** GET /cron_panel — shows last run history for all cron scripts. */
    public function cron_panel()
    {
        check_module_access('cron_panel', 'view');
        activity_log('cron_panel', 'view', null, null, 'Opened Cron Management Panel');

        $tableExists = false;
        try {
            $this->db->query("SELECT 1 FROM `cron_runs` LIMIT 1");
            $tableExists = true;
        } catch (\Throwable $e) {
            $tableExists = false;
        }

        $runs = [];
        $scripts = [];
        $lastRuns = [];

        if ($tableExists) {
            $runs = $this->db->table('cron_runs')
                ->orderBy('started_at', 'desc')
                ->limit(100)
                ->get()->getResultArray();

            $scripts = $this->db->table('cron_runs')
                ->select('script, MAX(started_at) AS last_run')
                ->groupBy('script')
                ->get()->getResultArray();

            foreach ($scripts as $s) {
                $row = $this->db->table('cron_runs')
                    ->where('script', $s['script'])
                    ->orderBy('started_at', 'desc')
                    ->limit(1)
                    ->get()->getRowArray();
                if ($row) {
                    $lastRuns[$s['script']] = $row;
                }
            }
        }

        $data = [
            'title'       => 'Cron Management',
            'tableExists' => $tableExists,
            'runs'        => $runs,
            'lastRuns'    => $lastRuns,
        ];

        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('cron_panel', $data);
        echo view('templates/footer');
    }

    // Server-side source for cron execution logs data table.
    public function cron_panel_data_table()
    {
        check_module_access('cron_panel', 'view');

        $colMap = [
            0 => 'script',
            1 => 'started_at',
            2 => 'duration_ms',
            3 => 'tickets_checked',
            4 => 'notifs_sent',
            5 => 'notifs_failed',
            6 => 'status',
            7 => 'output_summary',
        ];
        $params = dt_parse_request($this->request, $colMap);

        $fFrom   = trim((string) $this->request->getGet('f_from'));
        $fTo     = trim((string) $this->request->getGet('f_to'));
        $fScript = trim((string) $this->request->getGet('f_script'));
        $fStatus = trim((string) $this->request->getGet('f_status'));

        if ($fFrom !== '' && $fTo !== '' && $fFrom > $fTo) {
            $tmp   = $fFrom;
            $fFrom = $fTo;
            $fTo   = $tmp;
        }

        $builder = $this->db->table('cron_runs');

        if ($fFrom !== '') {
            $builder->where('started_at >=', $fFrom . ' 00:00:00');
        }
        if ($fTo !== '') {
            $builder->where('started_at <=', $fTo . ' 23:59:59');
        }
        if ($fScript !== '') {
            $builder->where('script', $fScript);
        }
        if ($fStatus !== '') {
            $builder->where('status', $fStatus);
        }
        if ($params['search'] !== '') {
            $term = $params['search'];
            $builder->groupStart()
                ->like('script', $term)
                ->orLike('output_summary', $term)
                ->orLike('status', $term)
                ->groupEnd();
        }

        $total    = (int) $this->db->table('cron_runs')->countAllResults();
        $filtered = (int) $builder->countAllResults(false);

        $rows = $builder
            ->orderBy($params['order_col'], $params['order_dir'])
            ->limit($params['length'], $params['start'])
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $statusVal = 'ok';
            if (isset($r['status'])) {
                $statusVal = $r['status'];
            }
            $isOk   = $statusVal === 'ok';

            $durMsVal = 0;
            if (isset($r['duration_ms'])) {
                $durMsVal = $r['duration_ms'];
            }
            $durSec = round((int) $durMsVal / 1000, 2);

            $failedVal = 0;
            if (isset($r['notifs_failed'])) {
                $failedVal = $r['notifs_failed'];
            }
            $failed = (int) $failedVal;

            $tmp = [];
            $tmp['script']   = '<code class="small">' . esc($r['script']) . '</code>';

            $startedAtVal = '-';
            if (isset($r['started_at'])) {
                $startedAtVal = $r['started_at'];
            }
            $tmp['started']  = esc(substr($startedAtVal, 0, 19));
            $tmp['duration'] = esc($durSec) . 's';

            $ticketsCheckedVal = 0;
            if (isset($r['tickets_checked'])) {
                $ticketsCheckedVal = $r['tickets_checked'];
            }
            $tmp['tickets']  = (int) $ticketsCheckedVal;

            $notifsSentVal = 0;
            if (isset($r['notifs_sent'])) {
                $notifsSentVal = $r['notifs_sent'];
            }
            $tmp['sent']     = (int) $notifsSentVal;
            $failedHtml = '0';
            if ($failed > 0) {
                $failedHtml = '<span class="text-danger fw-bold">' . $failed . '</span>';
            }
            $tmp['failed']   = $failedHtml;

            $statusHtml = '<span class="badge bg-danger">FAILED</span>';
            if ($isOk) {
                $statusHtml = '<span class="badge bg-success">OK</span>';
            }
            $tmp['status']   = $statusHtml;

            $outputSummaryVal = '-';
            if (isset($r['output_summary'])) {
                $outputSummaryVal = $r['output_summary'];
            }
            $tmp['summary']  = esc($outputSummaryVal);
            $out[] = $tmp;
        }

        return $this->response->setJSON([
            'draw'            => $params['draw'],
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $out,
        ]);
    }

    // Renders the global administration settings page.
    public function settings()
    {
        check_isvalidated();
        check_issuperadmin();
        activity_log('settings', 'view', null, null, 'Opened Settings page');

        $tableExists = $this->app_model->settingsTableExists();
        $rows        = [];
        if ($tableExists) {
            $rows = $this->app_model->settingGetAll();
        }
        $data = [
            'title'       => 'Settings',
            'view'        => 'settings',
            'settings'    => $rows,
            'tableExists' => $tableExists,
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('settings', $data);
        echo view('templates/footer');
    }

    // Updates global application settings in the database.
    public function settings_save()
    {
        check_isvalidated();
        check_issuperadmin();

        $userId  = (string) session('user_id');
        $changed = 0;
        $changedKeys = [];
        $rows    = $this->app_model->settingGetAll();
        foreach ($rows as $row) {
            $key = (string) $row['setting_key'];

            // Skip logo and favicon files from standard text inputs
            if ($key === 'app_logo' || $key === 'app_favicon') {
                continue;
            }

            // Authoritative list of keys that are checkboxes (on/off toggles).
            static $booleanKeys = [
                'maintenance_mode',
                'login_show_demo_creds',
                'password_require_letter',
                'password_require_digit',
                'live_audio_enabled',
                'live_browser_notify',
            ];
            $isBool = in_array($key, $booleanKeys, true);

            if ($isBool) {
                $val = '0';
                if ($this->request->getPost($key) !== null) {
                    $val = '1';
                }
            } else {
                if ($this->request->getPost($key) === null) {
                    continue;
                }
                $val = trim((string) $this->request->getPost($key));
            }

            if ((string) $val !== (string) $row['setting_value']) {
                $this->app_model->settingSet($key, $val, $userId);
                $changed++;
                $oldVal = (string) $row['setting_value'];
                $newVal = (string) $val;
                if (stripos($key, 'pass') !== false || stripos($key, 'secret') !== false) {
                    $oldVal = '(hidden)';
                    $newVal = '(updated)';
                }
                $changedKeys[$key] = [$oldVal, $newVal];
            }
        }

        // Handle Logo Reset/Upload
        if ($this->request->getPost('clear_logo') !== null) {
            $oldLogo = app_setting('app_logo', '');
            if ($oldLogo !== '') {
                $oldPath = FCPATH . $oldLogo;
                if (file_exists($oldPath) && is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $this->app_model->settingSet('app_logo', '', $userId);
            $changed++;
            $changedKeys['app_logo'] = [$oldLogo, ''];
        } else {
            $logoFile = $this->request->getFile('app_logo');
            if ($logoFile !== null && $logoFile->isValid() && !$logoFile->hasMoved()) {
                // Size validation: max 2MB
                $maxLogoSize = 2 * 1024 * 1024;
                if ($logoFile->getSize() > $maxLogoSize) {
                    $this->session->setFlashdata('error', 'Logo file size exceeds the 2MB limit.');
                    return redirect()->to(site_url('settings'));
                }

                // Type validation
                $allowedLogoMimes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml', 'image/webp'];
                $logoMime = $logoFile->getMimeType();
                $logoExt = $logoFile->getExtension();
                if (in_array($logoMime, $allowedLogoMimes, true) && in_array(strtolower($logoExt), ['png', 'jpg', 'jpeg', 'svg', 'webp'], true)) {
                    $uploadDir = FCPATH . 'uploads/branding';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $newLogoName = 'logo_' . time() . '.' . $logoExt;
                    $oldLogo = app_setting('app_logo', '');

                    if ($logoFile->move($uploadDir, $newLogoName)) {
                        if ($oldLogo !== '') {
                            $oldPath = FCPATH . $oldLogo;
                            if (file_exists($oldPath) && is_file($oldPath)) {
                                @unlink($oldPath);
                            }
                        }
                        $newLogoPath = 'uploads/branding/' . $newLogoName;
                        $this->app_model->settingSet('app_logo', $newLogoPath, $userId);
                        $changed++;
                        $changedKeys['app_logo'] = [$oldLogo, $newLogoPath];
                    }
                } else {
                    $this->session->setFlashdata('error', 'Invalid logo file type. Only PNG, JPG, SVG, and WEBP are supported.');
                    return redirect()->to(site_url('settings'));
                }
            }
        }

        // Handle Favicon Reset/Upload
        if ($this->request->getPost('clear_favicon') !== null) {
            $oldFavicon = app_setting('app_favicon', '');
            if ($oldFavicon !== '') {
                $oldPath = FCPATH . $oldFavicon;
                if (file_exists($oldPath) && is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $this->app_model->settingSet('app_favicon', '', $userId);
            $changed++;
            $changedKeys['app_favicon'] = [$oldFavicon, ''];
        } else {
            $favFile = $this->request->getFile('app_favicon');
            if ($favFile !== null && $favFile->isValid() && !$favFile->hasMoved()) {
                // Size validation: max 500KB
                $maxFavSize = 500 * 1024;
                if ($favFile->getSize() > $maxFavSize) {
                    $this->session->setFlashdata('error', 'Favicon file size exceeds the 500KB limit.');
                    return redirect()->to(site_url('settings'));
                }

                // Type validation
                $allowedFavMimes = ['image/png', 'image/jpeg', 'image/jpg', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml', 'image/webp'];
                $favMime = $favFile->getMimeType();
                $favExt = $favFile->getExtension();
                if (in_array($favMime, $allowedFavMimes, true) && in_array(strtolower($favExt), ['png', 'jpg', 'jpeg', 'ico', 'svg', 'webp'], true)) {
                    $uploadDir = FCPATH . 'uploads/branding';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $newFavName = 'favicon_' . time() . '.' . $favExt;
                    $oldFavicon = app_setting('app_favicon', '');

                    if ($favFile->move($uploadDir, $newFavName)) {
                        if ($oldFavicon !== '') {
                            $oldPath = FCPATH . $oldFavicon;
                            if (file_exists($oldPath) && is_file($oldPath)) {
                                @unlink($oldPath);
                            }
                        }
                        $newFavPath = 'uploads/branding/' . $newFavName;
                        $this->app_model->settingSet('app_favicon', $newFavPath, $userId);
                        $changed++;
                        $changedKeys['app_favicon'] = [$oldFavicon, $newFavPath];
                    }
                } else {
                    $this->session->setFlashdata('error', 'Invalid favicon file type. Only PNG, JPG, ICO, SVG, and WEBP are supported.');
                    return redirect()->to(site_url('settings'));
                }
            }
        }

        if ($changed > 0) {
            app_settings_clear_cache();
            activity_log('settings', 'update', null, null, 'Updated ' . $changed . ' setting(s)', $changedKeys);
            $this->session->setFlashdata('success', $changed . ' setting(s) saved.');
        } else {
            $this->session->setFlashdata('success', 'No changes to save.');
        }
        return redirect()->to(site_url('settings'));
    }

    /** POST /settings/send_test_email — fires send_email() with the
     *  currently-saved SMTP settings to the logged-in admin's address. */
    public function settings_send_test_email()
    {
        check_isvalidated();
        check_issuperadmin();

        $to = (string) session('user_email');
        if ($to === '') {
            return json_fail('Your account has no email address on file.');
        }

        app_settings_clear_cache();

        $subject = '[pView] SMTP test from ' . app_setting('app_name', 'pView');
        $body    = '<p>This is a test email sent from the pView Alert System Settings page.</p>'
            . '<p>Time: ' . esc(date('Y-m-d H:i:s')) . '</p>'
            . '<p>If you received it, the saved SMTP settings work.</p>';

        $ok = send_email($to, $subject, $body);
        if ($ok) {
            log_message('debug', 'pview alert >> settings_send_test_email OK: to=[' . $to . '], by=[' . logged_user_id() . ']');
            activity_log('settings', 'send_test_email', null, null, 'Sent test email to ' . $to, ['to' => $to]);
            return json_ok([], 'Test email sent to ' . $to . '. Check your inbox in a minute.');
        }
        log_message('error', 'pview alert >> settings_send_test_email FAILED: to=[' . $to . ']');
        activity_log('settings', 'send_test_email', null, null, 'Test email FAILED to ' . $to, ['to' => $to], ['status' => 'fail']);
        return json_fail('Send failed. Check writable/logs/log-*.php for the SMTP error.');
    }

    /** POST /settings/bump_asset_version — increments asset_version to bust browser caches. */
    public function settings_bump_asset_version()
    {
        check_isvalidated();
        check_issuperadmin();

        $currentRaw = app_setting('asset_version', '1');
        $current = (int) $currentRaw;
        if ($current < 1) {
            $current = 1;
        }
        $next = $current + 1;

        $userId = (string) session('user_id');
        $this->app_model->settingSet('asset_version', (string) $next, $userId);
        app_settings_clear_cache();

        activity_log('settings', 'bump_asset_version', null, null, 'Bumped asset_version ' . $current . ' → ' . $next, ['from' => $current, 'to' => $next]);

        return json_ok(['value' => $next], 'Asset version bumped to ' . $next . '. Refresh once to pull the new files.');
    }

    /** POST /settings/clear_cache — flushes the app_settings CI4 cache so the next request re-reads from the DB. */
    public function settings_clear_cache()
    {
        check_isvalidated();
        check_issuperadmin();

        app_settings_clear_cache();

        activity_log('settings', 'clear_cache', null, null, 'Cleared app settings cache', []);

        return json_ok([], 'Settings cache cleared. Next page load will re-read all settings from the database.');
    }

    /** GET /activity_logs — read-only centralized event/audit feed. */
    public function activity_logs()
    {
        check_module_access('activity_logs', 'view');
        activity_log('activity_logs', 'view', null, null, 'Opened Activity Log viewer');

        $modules  = $this->app_model->activityLogsDistinctValues('module');
        $actions  = $this->app_model->activityLogsDistinctValues('action');
        $roles    = $this->app_model->activityLogsDistinctValues('user_role');
        $statuses = ['success', 'fail'];
        $projects = $this->app_model->activityLogsProjectsForFilter();

        $canAnalytics = (logged_user_role() === ROLE_SUPER_ADMIN)
            || has_module_access('activity_logs', 'analytics');

        $data = [
            'title'        => 'Activity Log',
            'modules'      => $modules,
            'actions'      => $actions,
            'roles'        => $roles,
            'statuses'     => $statuses,
            'projects'     => $projects,
            'canAnalytics' => $canAnalytics,
        ];
        echo view('templates/header', $data);
        echo view('templates/sidebar', $data);
        echo view('activity_logs', $data);
        echo view('templates/footer');
    }

    /** GET /activity_logs/data_table — DataTables server-side feed. */
    public function activity_logs_data_table()
    {
        check_module_access('activity_logs', 'view');

        $colMap = [
            0 => 'created_at',
            1 => 'user_name',
            2 => 'module',
            3 => 'action',
            4 => 'entity_type',
            5 => 'summary',
        ];
        $params = dt_parse_request($this->request, $colMap);

        $fUser    = trim((string) $this->request->getGet('f_user'));
        $fModule  = trim((string) $this->request->getGet('f_module'));
        $fAction  = trim((string) $this->request->getGet('f_action'));
        $fRole    = trim((string) $this->request->getGet('f_role'));
        $fStatus  = trim((string) $this->request->getGet('f_status'));
        $fProject = trim((string) $this->request->getGet('f_project'));
        $fFrom    = trim((string) $this->request->getGet('f_from'));
        $fTo      = trim((string) $this->request->getGet('f_to'));
        if ($fFrom !== '' && $fTo !== '' && $fFrom > $fTo) {
            $tmp   = $fFrom;
            $fFrom = $fTo;
            $fTo   = $tmp;
        }

        $isAdminScope = role_has_admin_scope(logged_user_role());

        $builder = $this->db->table('activity_logs');
        if (!$isAdminScope) {
            $builder->where('user_id', (string) logged_user_id());
        }
        if ($fUser !== '') {
            $builder->groupStart()
                ->like('user_id', $fUser)
                ->orLike('user_name', $fUser)
                ->groupEnd();
        }
        if ($fModule !== '') {
            $builder->where('module', $fModule);
        }
        if ($fAction !== '') {
            $builder->where('action', $fAction);
        }
        if ($fRole !== '') {
            $builder->where('user_role', $fRole);
        }
        if ($fStatus !== '') {
            $builder->where('status', $fStatus);
        }
        if ($fProject !== '') {
            $builder->like('meta', $fProject);
        }
        if ($fFrom !== '') {
            $builder->where('created_at >=', $fFrom . ' 00:00:00');
        }
        if ($fTo !== '') {
            $builder->where('created_at <=', $fTo . ' 23:59:59');
        }
        if ($params['search'] !== '') {
            $term = $params['search'];
            if (strlen($term) >= 3) {
                $escaped = $this->db->escapeString($term);
                $builder->groupStart()
                    ->where("MATCH(summary) AGAINST ('{$escaped}' IN BOOLEAN MODE)", null, false)
                    ->orLike('user_name',   $term)
                    ->orLike('user_id',     $term)
                    ->orLike('module',      $term)
                    ->orLike('action',      $term)
                    ->orLike('entity_type', $term)
                    ->orLike('entity_id',   $term)
                    ->orLike('ip_address',  $term)
                    ->groupEnd();
            } else {
                $builder->groupStart()
                    ->like('user_name',   $term)
                    ->orLike('user_id',   $term)
                    ->orLike('module',    $term)
                    ->orLike('action',    $term)
                    ->orLike('entity_type', $term)
                    ->orLike('entity_id', $term)
                    ->orLike('summary',   $term)
                    ->orLike('ip_address', $term)
                    ->groupEnd();
            }
        }

        if ($isAdminScope) {
            $total = (int) $this->db->table('activity_logs')->countAllResults();
        } else {
            $total = (int) $this->db->table('activity_logs')->where('user_id', (string) logged_user_id())->countAllResults();
        }
        $filtered = (int) $builder->countAllResults(false);

        $rows = $builder
            ->orderBy($params['order_col'], $params['order_dir'])
            ->limit($params['length'], $params['start'])
            ->get()->getResultArray();

        // Batch-build the per-user login/logout map for session-window display.
        $userIdsInPage = [];
        foreach ($rows as $r) {
            if (!empty($r['user_id'])) {
                $userIdsInPage[(string) $r['user_id']] = true;
            }
        }
        $sessions = [];
        if (!empty($userIdsInPage)) {
            $authRows = $this->db->table('activity_logs')
                ->select('user_id, action, created_at')
                ->whereIn('user_id', array_keys($userIdsInPage))
                ->whereIn('action', ['login', 'logout'])
                ->orderBy('created_at', 'asc')
                ->get()->getResultArray();
            foreach ($authRows as $a) {
                $uid = (string) $a['user_id'];
                if (!isset($sessions[$uid])) {
                    $sessions[$uid] = ['login' => [], 'logout' => []];
                }
                $sessions[$uid][$a['action']][] = (string) $a['created_at'];
            }
        }

        $data = [];
        foreach ($rows as $r) {
            $userLabel = '-';
            if (!empty($r['user_name'])) {
                $userLabel = $r['user_name'];
            }
            if (!empty($r['user_id'])) {
                $userLabel .= ' <small class="text-muted">@' . esc($r['user_id']) . '</small>';
            }
            if (!empty($r['user_role'])) {
                $userLabel .= ' <span class="badge bg-light text-dark">' . esc(str_replace('_', ' ', $r['user_role'])) . '</span>';
            }

            $entityLabel = '-';
            if (!empty($r['entity_type'])) {
                $entityLabel = esc($r['entity_type']);
                if (!empty($r['entity_id'])) {
                    $entityLabel .= ' <small class="text-muted">#' . esc($r['entity_id']) . '</small>';
                }
            }

            $statusBadge = '';
            if (isset($r['status']) && $r['status'] === 'fail') {
                $statusBadge = ' <span class="badge bg-danger">FAIL</span>';
            }


            $loginAt  = null;
            $logoutAt = null;
            $eventTs  = (string) $r['created_at'];
            $uid      = (string) $r['user_id'];
            if ($uid !== '' && isset($sessions[$uid])) {
                foreach ($sessions[$uid]['login'] as $t) {
                    if ($t <= $eventTs) {
                        $loginAt = $t;
                    } else {
                        break;
                    }
                }
                foreach ($sessions[$uid]['logout'] as $t) {
                    if ($t >= $eventTs) {
                        $logoutAt = $t;
                        break;
                    }
                }
                // Discard logout if it predates the matched login (event sits between sessions).
                if ($loginAt !== null && $logoutAt !== null && $logoutAt < $loginAt) {
                    $logoutAt = null;
                }
            }

            $loginCell  = '<span class="text-muted">—</span>';
            $logoutCell = '<span class="text-muted">—</span>';
            if ($loginAt !== null) {
                $loginCell = '<span title="' . esc($loginAt) . '">' . esc(substr($loginAt, 11, 8)) . '</span>';
            }
            if ($logoutAt !== null) {
                $logoutCell = '<span title="' . esc($logoutAt) . '">' . esc(substr($logoutAt, 11, 8)) . '</span>';
            }

            $uaVal = '';
            if (isset($r['user_agent'])) {
                $uaVal = $r['user_agent'];
            }
            $ua  = strtolower((string) $uaVal);

            $modVal = '';
            if (isset($r['module'])) {
                $modVal = $r['module'];
            }
            $mod = strtolower((string) $modVal);
            if ($mod === 'api' || preg_match('/curl|python|java(?!script)|go-http|ruby|axios|requests|okhttp|httpie|wget|postman|insomnia/i', $ua)) {
                $srcLabel = 'API';
                $srcClass = 'bg-warning text-dark';
                $srcIcon  = 'bi-braces';
            } elseif (preg_match('/android|iphone|ipad|mobile|tablet/i', $ua)) {
                $srcLabel = 'Mobile';
                $srcClass = 'bg-info text-dark';
                $srcIcon  = 'bi-phone';
            } elseif ($ua !== '') {
                $srcLabel = 'Web';
                $srcClass = 'bg-primary';
                $srcIcon  = 'bi-globe';
            } else {
                $srcLabel = '—';
                $srcClass = '';
                $srcIcon  = '';
            }
            $sourceCell = '<span class="text-muted">—</span>';
            if ($srcLabel !== '—') {
                $sourceCell = '<span class="badge ' . $srcClass . '"><i class="bi ' . $srcIcon . ' me-1"></i>' . $srcLabel . '</span>';
            }

            $data[] = [
                'created_at'  => esc((string) $r['created_at']),
                'user'        => $userLabel,
                'module'      => '<span class="badge bg-secondary">' . esc($r['module']) . '</span>',
                'action'      => '<span class="badge bg-info text-dark">' . esc($r['action']) . '</span>' . $statusBadge,
                'entity'      => $entityLabel,
                'summary'     => esc((string) $r['summary']),
                'login'       => $loginCell,
                'logout'      => $logoutCell,
                'source'      => $sourceCell,
            ];
        }

        return dt_json_response($params['draw'], $total, $filtered, $data);
    }

    /** GET /activity_logs/export — streams the filtered activity feed
     *  as CSV. Same filter parameters as the data_table endpoint so the
     *  download honours whatever the viewer currently shows. Hard-capped
     *  at 50,000 rows so a misclick can't exhaust memory. */
    public function activity_logs_export()
    {
        check_module_access('activity_logs', 'view');

        $fUser    = trim((string) $this->request->getGet('f_user'));
        $fModule  = trim((string) $this->request->getGet('f_module'));
        $fAction  = trim((string) $this->request->getGet('f_action'));
        $fRole    = trim((string) $this->request->getGet('f_role'));
        $fStatus  = trim((string) $this->request->getGet('f_status'));
        $fProject = trim((string) $this->request->getGet('f_project'));
        $fFrom    = trim((string) $this->request->getGet('f_from'));
        $fTo      = trim((string) $this->request->getGet('f_to'));
        $fSearch  = trim((string) $this->request->getGet('q'));
        if ($fFrom !== '' && $fTo !== '' && $fFrom > $fTo) {
            $tmp   = $fFrom;
            $fFrom = $fTo;
            $fTo   = $tmp;
        }

        $isAdminScope = role_has_admin_scope(logged_user_role());

        $builder = $this->db->table('activity_logs');
        if (!$isAdminScope) {
            $builder->where('user_id', (string) logged_user_id());
        }
        if ($fUser !== '') {
            $builder->groupStart()
                ->like('user_id', $fUser)
                ->orLike('user_name', $fUser)
                ->groupEnd();
        }
        if ($fModule !== '') {
            $builder->where('module', $fModule);
        }
        if ($fAction !== '') {
            $builder->where('action', $fAction);
        }
        if ($fRole   !== '') {
            $builder->where('user_role', $fRole);
        }
        if ($fStatus !== '') {
            $builder->where('status', $fStatus);
        }
        if ($fProject !== '') {
            $builder->like('meta', $fProject);
        }
        if ($fFrom !== '') {
            $builder->where('created_at >=', $fFrom . ' 00:00:00');
        }
        if ($fTo !== '') {
            $builder->where('created_at <=', $fTo . ' 23:59:59');
        }
        if ($fSearch !== '') {
            if (strlen($fSearch) >= 3) {
                $escaped = $this->db->escapeString($fSearch);
                $builder->groupStart()
                    ->where("MATCH(summary) AGAINST ('{$escaped}' IN BOOLEAN MODE)", null, false)
                    ->orLike('user_name',   $fSearch)
                    ->orLike('user_id',     $fSearch)
                    ->orLike('module',      $fSearch)
                    ->orLike('action',      $fSearch)
                    ->orLike('entity_type', $fSearch)
                    ->orLike('entity_id',   $fSearch)
                    ->orLike('ip_address',  $fSearch)
                    ->groupEnd();
            } else {
                $builder->groupStart()
                    ->like('user_name',   $fSearch)
                    ->orLike('user_id',   $fSearch)
                    ->orLike('module',    $fSearch)
                    ->orLike('action',    $fSearch)
                    ->orLike('entity_type', $fSearch)
                    ->orLike('entity_id', $fSearch)
                    ->orLike('summary',   $fSearch)
                    ->orLike('ip_address', $fSearch)
                    ->groupEnd();
            }
        }

        $rows = $builder
            ->orderBy('created_at', 'desc')
            ->limit(50000)
            ->get()->getResultArray();

        $filename = 'activity-' . date('Ymd-His') . '.csv';

        // Audit the audit-export itself so an admin downloading the trail
        // also leaves a row behind. Won't appear in *this* CSV (already
        // streamed) but lands in the next export / live viewer.
        activity_log('activity_logs', 'export', null, null, 'Exported ' . count($rows) . ' activity rows to CSV', ['row_count' => count($rows), 'filters' => ['user' => $fUser, 'module' => $fModule, 'action' => $fAction, 'from' => $fFrom, 'to' => $fTo, 'search' => $fSearch]]);

        $userSelectedCols = (string) $this->request->getGet('export_cols');
        export_csv_data($filename, 'activity_logs', $rows, $userSelectedCols);
    }

    /** GET /activity_logs/analytics — JSON analytics summary for the Analytics tab.
     *  Visible to super_admin by default; other roles need the 'analytics'
     *  action granted on activity_logs in the Module Control Panel. */
    public function activity_logs_analytics()
    {
        check_isvalidated();
        if (logged_user_role() !== ROLE_SUPER_ADMIN && !has_module_access('activity_logs', 'analytics')) {
            return json_fail('Access denied', 403);
        }

        $fFrom = trim((string) $this->request->getGet('f_from'));
        $fTo   = trim((string) $this->request->getGet('f_to'));

        // Default to last 30 days when no range is supplied.
        if ($fFrom === '') {
            $fFrom = date('Y-m-d', strtotime('-30 days'));
        }
        if ($fTo === '') {
            $fTo = date('Y-m-d');
        }

        $fromTs = $fFrom . ' 00:00:00';
        $toTs   = $fTo   . ' 23:59:59';

        // --- Login / logout / failure stats ---
        $authRows = $this->db->table('activity_logs')
            ->select('action, DATE(created_at) as day, COUNT(*) as cnt')
            ->where('module', 'auth')
            ->where('created_at >=', $fromTs)
            ->where('created_at <=', $toTs)
            ->groupBy('action, DATE(created_at)')
            ->get()->getResultArray();

        $today    = date('Y-m-d');
        $weekAgo  = date('Y-m-d', strtotime('-7 days'));

        $authStats = [
            'logins_today'   => 0,
            'logins_week'    => 0,
            'logins_period'  => 0,
            'failed_today'   => 0,
            'failed_week'    => 0,
            'failed_period'  => 0,
            'logouts_period' => 0,
        ];
        foreach ($authRows as $a) {
            $isToday = ($a['day'] === $today);
            $isWeek  = ($a['day'] >= $weekAgo);
            $cnt     = (int) $a['cnt'];
            if ($a['action'] === 'login') {
                $authStats['logins_period'] += $cnt;
                if ($isWeek) {
                    $authStats['logins_week']  += $cnt;
                }
                if ($isToday) {
                    $authStats['logins_today'] += $cnt;
                }
            } elseif ($a['action'] === 'login_failed') {
                $authStats['failed_period'] += $cnt;
                if ($isWeek) {
                    $authStats['failed_week']  += $cnt;
                }
                if ($isToday) {
                    $authStats['failed_today'] += $cnt;
                }
            } elseif ($a['action'] === 'logout') {
                $authStats['logouts_period'] += $cnt;
            }
        }

        // --- Top 10 active users by event count ---
        $topUsers = $this->db->table('activity_logs')
            ->select('user_id, user_name, user_role, COUNT(*) as event_count, MAX(created_at) as last_seen')
            ->where('created_at >=', $fromTs)
            ->where('created_at <=', $toTs)
            ->where('user_id IS NOT NULL')
            ->groupBy('user_id, user_name, user_role')
            ->orderBy('event_count', 'desc')
            ->limit(10)
            ->get()->getResultArray();

        // --- Module usage breakdown ---
        $moduleUsage = $this->db->table('activity_logs')
            ->select('module, COUNT(*) as cnt')
            ->where('created_at >=', $fromTs)
            ->where('created_at <=', $toTs)
            ->groupBy('module')
            ->orderBy('cnt', 'desc')
            ->get()->getResultArray();

        // --- Action breakdown ---
        $actionBreakdown = $this->db->table('activity_logs')
            ->select('action, COUNT(*) as cnt')
            ->where('created_at >=', $fromTs)
            ->where('created_at <=', $toTs)
            ->groupBy('action')
            ->orderBy('cnt', 'desc')
            ->limit(15)
            ->get()->getResultArray();

        // --- Failed events (last 30 in period) ---
        $failedRaw = $this->db->table('activity_logs')
            ->select('created_at, user_id, user_name, user_role, module, action, summary, user_agent')
            ->where('status', 'fail')
            ->where('created_at >=', $fromTs)
            ->where('created_at <=', $toTs)
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get()->getResultArray();

        $failedEvents = [];
        foreach ($failedRaw as $fe) {
            $uaVal = '';
            if (isset($fe['user_agent'])) {
                $uaVal = $fe['user_agent'];
            }
            $ua  = strtolower((string) $uaVal);

            $modVal = '';
            if (isset($fe['module'])) {
                $modVal = $fe['module'];
            }
            $mod = strtolower((string) $modVal);
            if ($mod === 'api' || preg_match('/curl|python|java(?!script)|go-http|ruby|axios|requests|okhttp|httpie|wget|postman|insomnia/i', $ua)) {
                $source = 'API';
            } elseif (preg_match('/android|iphone|ipad|mobile|tablet/i', $ua)) {
                $source = 'Mobile';
            } elseif ($ua !== '') {
                $source = 'Web';
            } else {
                $source = 'Unknown';
            }
            $fe['source'] = $source;
            unset($fe['user_agent']);
            $failedEvents[] = $fe;
        }

        // --- Average session duration per user (login → next logout) ---
        // Pull all login/logout pairs for the period and compute in PHP.
        $sessionRows = $this->db->table('activity_logs')
            ->select('user_id, user_name, action, created_at')
            ->whereIn('action', ['login', 'logout'])
            ->where('created_at >=', $fromTs)
            ->where('created_at <=', $toTs)
            ->where('user_id IS NOT NULL')
            ->orderBy('user_id')->orderBy('created_at')
            ->get()->getResultArray();

        $userSessions = [];
        foreach ($sessionRows as $s) {
            $uid = (string) $s['user_id'];
            if (!isset($userSessions[$uid])) {
                $userSessions[$uid] = ['name' => (string) $s['user_name'], 'logins' => [], 'logouts' => []];
            }
            $sessKey = 'logouts';
            if ($s['action'] === 'login') {
                $sessKey = 'logins';
            }
            $userSessions[$uid][$sessKey][] = strtotime($s['created_at']);
        }

        $sessionAvg = [];
        foreach ($userSessions as $uid => $sd) {
            $durations = [];
            $logoutIdx = 0;
            foreach ($sd['logins'] as $loginTs) {
                while ($logoutIdx < count($sd['logouts']) && $sd['logouts'][$logoutIdx] < $loginTs) {
                    $logoutIdx++;
                }
                if ($logoutIdx < count($sd['logouts'])) {
                    $durations[] = $sd['logouts'][$logoutIdx] - $loginTs;
                    $logoutIdx++;
                }
            }
            if (!empty($durations)) {
                $avgSec = (int) (array_sum($durations) / count($durations));
                $sessionAvg[] = [
                    'user_id'       => $uid,
                    'user_name'     => $sd['name'],
                    'avg_minutes'   => round($avgSec / 60, 1),
                    'session_count' => count($durations),
                ];
            }
        }
        usort($sessionAvg, function ($a, $b) {
            return $b['avg_minutes'] <=> $a['avg_minutes'];
        });

        return json_ok([
            'period'          => ['from' => $fFrom, 'to' => $fTo],
            'auth'            => $authStats,
            'top_users'       => array_values($topUsers),
            'modules'         => array_values($moduleUsage),
            'actions'         => array_values($actionBreakdown),
            'failed'          => array_values($failedEvents),
            'session_avg'     => array_slice($sessionAvg, 0, 10),
        ]);
    }

    /** GET /projects/data_table */
    public function projects_data_table()
    {
        check_module_access('projects', 'view');

        // Visible columns: Name | Description | Status | Created By | Created | Actions
        // Description, Created By, Actions are not sensibly sortable in the
        // backend — fall back to name for those, but use the right key for
        // status and created_at. Previously every non-name column collapsed
        // to 'p.name'.
        $colMap = [
            0 => 'p.name',
            1 => 'p.description',
            2 => 'p.status',
            3 => 'p.created_by',
            4 => 'p.created_at',
            5 => 'p.created_at', // actions fallback
        ];
        $params    = dt_parse_request($this->request, $colMap);
        $params['scope_user_pk']  = logged_user_id();
        $params['scope_is_admin'] = role_has_admin_scope();
        $result    = $this->app_model->projectsForDT($params);
        $canEdit   = has_module_access('projects', 'edit')   === true;
        $canDelete = has_module_access('projects', 'delete') === true;

        $data = [];
        foreach ($result['rows'] as $p) {
            $statusBadge = '<span class="badge bg-dark">INACTIVE</span>';
            if ($p['status'] === 'active') {
                $statusBadge = '<span class="badge bg-success">ACTIVE</span>';
            }
            $desc = '';
            if (isset($p['description'])) {
                $desc = (string) $p['description'];
            }
            $creator = '-';
            if (isset($p['created_by_name'])) {
                $creator = (string) $p['created_by_name'];
            }

            $actionsHtml = '';
            if ($canEdit) {
                $actionsHtml .= '<a class="btn btn-sm btn-light" href="' . site_url('projects/edit/' . $p['id']) . '"><i class="bi bi-pencil"></i></a> ';
            }
            if ($canDelete) {
                $actionsHtml .= '<a class="btn btn-sm btn-outline-danger" href="' . site_url('projects/delete/' . $p['id']) . '" data-method="post" data-confirm-message="Remove this project?"><i class="bi bi-trash"></i></a>';
            }
            if ($actionsHtml === '') {
                $actionsHtml = '<span class="text-muted small">—</span>';
            }

            $descVal = '-';
            if ($desc !== '') {
                $descVal = $desc;
            }
            $creatorVal = '-';
            if ($creator !== '') {
                $creatorVal = $creator;
            }

            $data[] = [
                'name'        => '<strong>' . esc($p['name']) . '</strong>',
                'description' => esc($descVal),
                'status'      => $statusBadge,
                'created_by'  => esc($creatorVal),
                'created_at'  => '<span class="text-muted small">' . esc($p['created_at']) . '</span>',
                'actions'     => $actionsHtml,
            ];
        }

        return dt_json_response($params['draw'], $result['total'], $result['filtered'], $data);
    }

    /** GET /flows/data_table */
    public function flows_data_table()
    {
        check_module_access('flows', 'view');

        $colMap = [
            0 => 'f.name',
            1 => 'p.name',
            2 => 'f.id', // state_count fallback
            3 => 'f.status',
            4 => 'f.created_by',
            5 => 'f.created_at',
            6 => 'f.created_at', // actions fallback
        ];
        $params    = dt_parse_request($this->request, $colMap);
        $result    = $this->app_model->flowsForDT($params);
        $canEdit   = has_module_access('flows', 'edit')   === true;
        $canDelete = has_module_access('flows', 'delete') === true;

        $data = [];
        foreach ($result['rows'] as $f) {
            $statusBadge = '<span class="badge bg-dark">INACTIVE</span>';
            if ($f['status'] === 'active') {
                $statusBadge = '<span class="badge bg-success">ACTIVE</span>';
            }
            $stateCount  = '<span class="badge bg-info text-dark">' . (int) $f['state_count'] . '</span>';
            $projectName = '-';
            if (isset($f['project_name'])) {
                $projectName = (string) $f['project_name'];
            }
            $creator = '-';
            if (isset($f['created_by_name'])) {
                $creator = (string) $f['created_by_name'];
            }

            $actionsHtml = '<a class="btn btn-sm btn-primary" href="' . site_url('flows/states/' . $f['id']) . '" title="States"><i class="bi bi-diagram-3"></i></a> ';
            if ($canEdit) {
                $actionsHtml .= '<a class="btn btn-sm btn-light" href="' . site_url('flows/edit/' . $f['id']) . '"><i class="bi bi-pencil"></i></a> ';
            }
            if ($canDelete) {
                $actionsHtml .= '<a class="btn btn-sm btn-outline-danger" href="' . site_url('flows/delete/' . $f['id']) . '" data-method="post" data-confirm-message="Remove this flow?"><i class="bi bi-trash"></i></a>';
            }

            $projectVal = '-';
            if ($projectName !== '') {
                $projectVal = $projectName;
            }
            $creatorVal = '-';
            if ($creator !== '') {
                $creatorVal = $creator;
            }

            $data[] = [
                'name'        => '<strong>' . esc($f['name']) . '</strong>',
                'project'     => esc($projectVal),
                'state_count' => $stateCount,
                'status'      => $statusBadge,
                'created_by'  => esc($creatorVal),
                'created_at'  => '<span class="text-muted small">' . esc($f['created_at']) . '</span>',
                'actions'     => $actionsHtml,
            ];
        }

        return dt_json_response($params['draw'], $result['total'], $result['filtered'], $data);
    }

    /** GET /alerts/data_table */
    public function alerts_data_table()
    {
        check_module_access('alerts', 'view');

        $colMap = [
            0 => 'a.name',
            1 => 'p.name',
            2 => 'f.name',
            3 => 'a.alert_type',
            4 => 'a.threshold_value',
            5 => 'a.is_active',
            6 => 'a.created_at', // actions fallback
        ];
        $params    = dt_parse_request($this->request, $colMap);
        $result    = $this->app_model->alertsForDT($params);
        $canEdit   = has_module_access('alerts', 'edit')   === true;
        $canDelete = has_module_access('alerts', 'delete') === true;

        $data = [];
        foreach ($result['rows'] as $a) {
            $activeBadge = '<span class="badge bg-dark">NO</span>';
            if (!empty($a['is_active'])) {
                $activeBadge = '<span class="badge bg-success">YES</span>';
            }
            $tval = '';
            if (isset($a['threshold_value'])) {
                $tval = $a['threshold_value'];
            }
            $tunit = '';
            if (isset($a['threshold_unit'])) {
                $tunit = $a['threshold_unit'];
            }
            $proj = '-';
            if (isset($a['project_name'])) {
                $proj = (string) $a['project_name'];
            }
            $flow = '-';
            if (isset($a['flow_name'])) {
                $flow = (string) $a['flow_name'];
            }
            $desc = '';
            if (isset($a['description'])) {
                $desc = (string) $a['description'];
            }

            $rawThreshold = '-';
            if ($tval !== '') {
                $rawThreshold = $tval;
            }
            $threshold = esc($rawThreshold);
            if ($tunit !== '') {
                $threshold .= ' <small class="text-muted">' . esc($tunit) . '</small>';
            }

            $actionsHtml = '';
            if ($canEdit) {
                $actionsHtml .= '<a class="btn btn-sm btn-light" href="' . site_url('alerts/edit/' . $a['id']) . '"><i class="bi bi-pencil"></i></a> ';
            }
            if ($canDelete) {
                $actionsHtml .= '<a class="btn btn-sm btn-outline-danger" href="' . site_url('alerts/delete/' . $a['id']) . '" data-method="post" data-confirm-message="Remove this alert definition?"><i class="bi bi-trash"></i></a>';
            }
            if ($actionsHtml === '') {
                $actionsHtml = '<span class="text-muted small">—</span>';
            }

            $descSub = '';
            if ($desc !== '') {
                $descSub = '<br><small class="text-muted">' . esc($desc) . '</small>';
            }
            $projVal = '-';
            if ($proj !== '') {
                $projVal = $proj;
            }
            $flowVal = '-';
            if ($flow !== '') {
                $flowVal = $flow;
            }

            $data[] = [
                'name'      => '<strong>' . esc($a['name']) . '</strong>' . $descSub,
                'project'   => esc($projVal),
                'flow'      => esc($flowVal),
                'severity'  => alert_badge($a['alert_type']),
                'threshold' => $threshold,
                'active'    => $activeBadge,
                'actions'   => $actionsHtml,
            ];
        }

        return dt_json_response($params['draw'], $result['total'], $result['filtered'], $data);
    }
}
