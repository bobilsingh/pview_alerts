<?php

/**
 * pView Alert System — realistic demo data seed.
 *
 * Wipes transactional + workflow-config tables and rebuilds a presentable
 * dataset: 6 users (1 super_admin, 1 admin, 4 user-role operators),
 * 3 projects, 3 flows (one rich branching tree with parent/child/sub-child
 * states converging on a single closing state), escalation rules, alert
 * definitions, and a spread of tickets with realistic activity timelines.
 *
 * Safe to re-run: it truncates the seeded tables first. It does NOT touch
 * app_settings, migrations, roles, module_permissions or api_keys.
 *
 * Usage:  php scripts/seed_demo_data.php
 */

$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'pview_alerts';

$DEMO_PASSWORD = 'Demo@2026';

$db = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if (!$db) {
    fwrite(STDERR, 'DB connection failed: ' . mysqli_connect_error() . PHP_EOL);
    exit(1);
}
mysqli_set_charset($db, 'utf8mb4');

// --- small helpers -------------------------------------------------------

function q($db, $sql)
{
    $ok = mysqli_query($db, $sql);
    if (!$ok) {
        fwrite(STDERR, 'SQL FAILED: ' . mysqli_error($db) . PHP_EOL . '  ' . $sql . PHP_EOL);
        exit(1);
    }
    return $ok;
}

function esc($db, $v)
{
    if ($v === null) {
        return 'NULL';
    }
    return "'" . mysqli_real_escape_string($db, (string) $v) . "'";
}

// Insert a row from an associative array; returns the new auto-increment id.
function ins($db, $table, $row)
{
    $cols = [];
    $vals = [];
    foreach ($row as $k => $v) {
        $cols[] = '`' . $k . '`';
        $vals[] = esc($db, $v);
    }
    q($db, 'INSERT INTO `' . $table . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')');
    return (int) mysqli_insert_id($db);
}

function jids($ids)
{
    return json_encode(array_values($ids));
}

$now    = date('Y-m-d H:i:s');
$today  = date('Y-m-d');
$pwHash = password_hash($DEMO_PASSWORD, PASSWORD_BCRYPT);

echo "Seeding pView demo data...\n";

// --- 1. wipe transactional + workflow-config tables ----------------------

q($db, 'SET FOREIGN_KEY_CHECKS = 0');
$wipe = [
    'ticket_actions', 'notification_logs', 'tickets',
    'state_transitions', 'escalation_matrix', 'states', 'flows',
    'alert_definitions', 'projects', 'activity_logs',
    'alarm_id_sequence', 'saved_filters', 'user_notification_settings',
];
foreach ($wipe as $t) {
    q($db, 'TRUNCATE TABLE `' . $t . '`');
}
echo "  - wiped " . count($wipe) . " tables\n";

// --- 2. users (upsert the 6 demo accounts) -------------------------------
// Roles stay as designed: 1 super_admin, 1 admin, 4 user-role operators.

$users = [
    ['user_id' => 'sa',        'name' => 'System Administrator', 'email' => 'sa@demo.local',        'role' => 'super_admin', 'phone' => '+91-90000-00001'],
    ['user_id' => 'admin',     'name' => 'Operations Admin',     'email' => 'admin@demo.local',     'role' => 'admin',       'phone' => '+91-90000-00002'],
    ['user_id' => 'noc.l1',    'name' => 'Aarav Patel',          'email' => 'noc.l1@demo.local',    'role' => 'user',        'phone' => '+91-90000-00011'],
    ['user_id' => 'noc.l2',    'name' => 'Priya Sharma',         'email' => 'noc.l2@demo.local',    'role' => 'user',        'phone' => '+91-90000-00012'],
    ['user_id' => 'team.lead', 'name' => 'Karthik Iyer',         'email' => 'team.lead@demo.local', 'role' => 'user',        'phone' => '+91-90000-00013'],
    ['user_id' => 'viewer',    'name' => 'Saanvi Rao',           'email' => 'viewer@demo.local',    'role' => 'user',        'phone' => '+91-90000-00014'],
];
foreach ($users as $u) {
    $exists = mysqli_query($db, 'SELECT id FROM users WHERE user_id = ' . esc($db, $u['user_id']) . ' LIMIT 1');
    $hasRow = $exists && mysqli_num_rows($exists) > 0;
    if ($hasRow) {
        q($db, 'UPDATE users SET '
            . 'name = ' . esc($db, $u['name']) . ', '
            . 'email = ' . esc($db, $u['email']) . ', '
            . 'role = ' . esc($db, $u['role']) . ', '
            . 'phone = ' . esc($db, $u['phone']) . ', '
            . 'password = ' . esc($db, $pwHash) . ', '
            . 'password_changed_at = ' . esc($db, $now) . ', '
            . 'is_active = 1, deleted_at = NULL, theme = ' . esc($db, 'dark') . ' '
            . 'WHERE user_id = ' . esc($db, $u['user_id']));
    } else {
        ins($db, 'users', [
            'user_id'             => $u['user_id'],
            'name'                => $u['name'],
            'email'               => $u['email'],
            'password'            => $pwHash,
            'role'                => $u['role'],
            'phone'               => $u['phone'],
            'is_active'           => 1,
            'created_at'          => $now,
            'password_changed_at' => $now,
            'theme'               => 'dark',
        ]);
    }
}
echo "  - upserted " . count($users) . " users (password: $DEMO_PASSWORD)\n";

// --- 3. projects ---------------------------------------------------------

$projects = [];
$projects['core'] = ins($db, 'projects', [
    'name' => 'Core Network Monitoring', 'status' => 'active', 'created_by' => 'sa', 'created_at' => $now,
    'description' => 'Backbone routers, transport links and core switching fabric.',
]);
$projects['dc'] = ins($db, 'projects', [
    'name' => 'Data Center Operations', 'status' => 'active', 'created_by' => 'sa', 'created_at' => $now,
    'description' => 'Compute, storage and virtualization service requests.',
]);
$projects['edge'] = ins($db, 'projects', [
    'name' => 'Customer Edge Services', 'status' => 'active', 'created_by' => 'sa', 'created_at' => $now,
    'description' => 'Customer-facing edge CPE, access links and change requests.',
]);
echo "  - created " . count($projects) . " projects\n";

// --- 4. flows ------------------------------------------------------------

$flows = [];
$flows['incident'] = ins($db, 'flows', [
    'project_id' => $projects['core'], 'name' => 'Incident Response', 'status' => 'active', 'created_by' => 'sa', 'created_at' => $now,
]);
$flows['service'] = ins($db, 'flows', [
    'project_id' => $projects['dc'], 'name' => 'Service Request', 'status' => 'active', 'created_by' => 'sa', 'created_at' => $now,
]);
$flows['change'] = ins($db, 'flows', [
    'project_id' => $projects['edge'], 'name' => 'Change Management', 'status' => 'active', 'created_by' => 'sa', 'created_at' => $now,
]);
echo "  - created " . count($flows) . " flows\n";

// --- 5. states -----------------------------------------------------------
// Each entry: name, initial, final, parent (name|null), l1[], l2[], tats.
// parent_state_id is resolved by name in a second pass so the tree can be
// declared top-down. Leaf states auto-route to the single is_final state
// (matches stateGetChildren() merge rule).

$T = ['l1' => 30, 'l2' => 60, 'l3' => 120, 'l4' => 240];

$stateDefs = [
    // ---- Incident Response: rich branching tree ----
    // Triage -> Investigation -> { Hardware Track -> Hardware Diagnosis -> Field Repair },
    //                            { Software Track -> Patch Validation  -> Patch Rollout } -> Closure
    $flows['incident'] => [
        ['name' => 'Triage',             'initial' => 1, 'final' => 0, 'parent' => null,                'l1' => ['noc.l1'],    'l2' => ['noc.l2']],
        ['name' => 'Investigation',      'initial' => 0, 'final' => 0, 'parent' => 'Triage',            'l1' => ['noc.l2'],    'l2' => ['team.lead']],
        ['name' => 'Hardware Track',     'initial' => 0, 'final' => 0, 'parent' => 'Investigation',     'l1' => ['noc.l1'],    'l2' => ['noc.l2']],
        ['name' => 'Hardware Diagnosis', 'initial' => 0, 'final' => 0, 'parent' => 'Hardware Track',    'l1' => ['noc.l2'],    'l2' => ['team.lead']],
        ['name' => 'Field Repair',       'initial' => 0, 'final' => 0, 'parent' => 'Hardware Diagnosis','l1' => ['team.lead'], 'l2' => ['admin']],
        ['name' => 'Software Track',     'initial' => 0, 'final' => 0, 'parent' => 'Investigation',     'l1' => ['noc.l1'],    'l2' => ['noc.l2']],
        ['name' => 'Patch Validation',   'initial' => 0, 'final' => 0, 'parent' => 'Software Track',    'l1' => ['noc.l2'],    'l2' => ['team.lead']],
        ['name' => 'Patch Rollout',      'initial' => 0, 'final' => 0, 'parent' => 'Patch Validation',  'l1' => ['team.lead'], 'l2' => ['admin']],
        ['name' => 'Closure',            'initial' => 0, 'final' => 1, 'parent' => null,                'l1' => [],            'l2' => []],
    ],
    // ---- Service Request: simple linear (sort-order based) ----
    $flows['service'] => [
        ['name' => 'Submitted',    'initial' => 1, 'final' => 0, 'parent' => null, 'l1' => ['noc.l1'],    'l2' => ['noc.l2']],
        ['name' => 'Approval',     'initial' => 0, 'final' => 0, 'parent' => null, 'l1' => ['team.lead'], 'l2' => ['admin']],
        ['name' => 'Provisioning', 'initial' => 0, 'final' => 0, 'parent' => null, 'l1' => ['noc.l2'],    'l2' => ['team.lead']],
        ['name' => 'Completed',    'initial' => 0, 'final' => 1, 'parent' => null, 'l1' => [],            'l2' => []],
    ],
    // ---- Change Management: one branch (CAB vs standard) ----
    $flows['change'] => [
        ['name' => 'Requested',      'initial' => 1, 'final' => 0, 'parent' => null,           'l1' => ['noc.l1'],    'l2' => ['noc.l2']],
        ['name' => 'Review',         'initial' => 0, 'final' => 0, 'parent' => 'Requested',     'l1' => ['noc.l2'],    'l2' => ['team.lead']],
        ['name' => 'CAB Approval',   'initial' => 0, 'final' => 0, 'parent' => 'Review',        'l1' => ['team.lead'], 'l2' => ['admin']],
        ['name' => 'Standard Change','initial' => 0, 'final' => 0, 'parent' => 'Review',        'l1' => ['noc.l2'],    'l2' => ['team.lead']],
        ['name' => 'Implementation', 'initial' => 0, 'final' => 0, 'parent' => 'CAB Approval',  'l1' => ['team.lead'], 'l2' => ['admin']],
        ['name' => 'Closed',         'initial' => 0, 'final' => 1, 'parent' => null,            'l1' => [],            'l2' => []],
    ],
];

// stateIds[flowId][name] = id
$stateIds = [];
foreach ($stateDefs as $flowId => $defs) {
    $sort = 1;
    foreach ($defs as $d) {
        $row = [
            'flow_id'        => $flowId,
            'name'           => $d['name'],
            'parent_state_id' => null, // resolved in pass 2
            'sort_order'     => $sort,
            'is_initial'     => $d['initial'],
            'is_final'       => $d['final'],
            'l1_user_ids'    => jids($d['l1']),
            'l1_tat_minutes' => $T['l1'],
            'l2_user_ids'    => jids($d['l2']),
            'l2_tat_minutes' => $T['l2'],
            'l3_user_ids'    => jids([]),
            'l3_tat_minutes' => $T['l3'],
            'l4_user_ids'    => jids([]),
            'l4_tat_minutes' => $T['l4'],
            'status'         => 'active',
            'created_by'     => 'sa',
            'created_at'     => $now,
        ];
        $id = ins($db, 'states', $row);
        $stateIds[$flowId][$d['name']] = $id;
        $sort++;
    }
}
// pass 2: resolve parent_state_id by name
foreach ($stateDefs as $flowId => $defs) {
    foreach ($defs as $d) {
        if ($d['parent'] !== null) {
            $childId  = $stateIds[$flowId][$d['name']];
            $parentId = $stateIds[$flowId][$d['parent']];
            q($db, 'UPDATE states SET parent_state_id = ' . (int) $parentId . ' WHERE id = ' . (int) $childId);
        }
    }
}
$stateTotal = 0;
foreach ($stateIds as $m) { $stateTotal += count($m); }
echo "  - created $stateTotal states across 3 flows\n";

// --- 6. backward transitions (send-back / rework) ------------------------
// Declared the same way the flow designer + ticket page now persist them.

$backTrans = [
    // Incident Response: rework paths
    [$flows['incident'], 'Hardware Diagnosis', 'Triage'],
    [$flows['incident'], 'Patch Validation',   'Investigation'],
    [$flows['incident'], 'Field Repair',       'Hardware Track'],
    // Change Management: send a change back for re-review
    [$flows['change'], 'CAB Approval', 'Review'],
];
foreach ($backTrans as $b) {
    list($flowId, $from, $to) = $b;
    ins($db, 'state_transitions', [
        'flow_id'          => $flowId,
        'from_state_id'    => $stateIds[$flowId][$from],
        'to_state_id'      => $stateIds[$flowId][$to],
        'transition_type'  => 'backward',
        'requires_comment' => 1,
        'sort_order'       => 0,
        'created_at'       => $now,
        'created_by'       => 'sa',
    ]);
}
echo "  - created " . count($backTrans) . " backward transitions\n";

// --- 7. escalation matrix (a few realistic overrides) --------------------

$escRows = [
    [$flows['incident'], 'Triage',        1, 30,  ['noc.l2'],            'major'],
    [$flows['incident'], 'Triage',        2, 60,  ['team.lead'],         'critical'],
    [$flows['incident'], 'Investigation', 1, 45,  ['team.lead'],         'major'],
    [$flows['incident'], 'Field Repair',  1, 60,  ['admin'],             'critical'],
];
foreach ($escRows as $e) {
    list($flowId, $stateName, $lvl, $after, $notify, $atype) = $e;
    ins($db, 'escalation_matrix', [
        'flow_id'         => $flowId,
        'state_id'        => $stateIds[$flowId][$stateName],
        'level'           => $lvl,
        'escalate_after'  => $after,
        'notify_user_ids' => jids($notify),
        'alert_type'      => $atype,
        'created_by'      => 'sa',
        'created_at'      => $now,
    ]);
}
echo "  - created " . count($escRows) . " escalation rules\n";

// --- 8. alert definitions ------------------------------------------------

$alertDefs = [
    [$projects['core'], $flows['incident'], 'Core Router CPU High', 'critical', '90', '%',  ['noc.l1', 'noc.l2']],
    [$projects['core'], $flows['incident'], 'Backbone Link Down',   'critical', '0',  'up', ['noc.l1', 'team.lead']],
    [$projects['dc'],   $flows['service'],  'Storage Pool Capacity','major',    '85', '%',  ['noc.l2']],
    [$projects['edge'], $flows['change'],   'CPE Reachability',     'info',     '1',  'ping', ['noc.l1']],
];
foreach ($alertDefs as $a) {
    list($pid, $fid, $name, $atype, $tval, $tunit, $notify) = $a;
    ins($db, 'alert_definitions', [
        'project_id'      => $pid,
        'name'            => $name,
        'description'     => $name . ' threshold alert definition.',
        'alert_type'      => $atype,
        'threshold_value' => $tval,
        'threshold_unit'  => $tunit,
        'flow_id'         => $fid,
        'notify_user_ids' => jids($notify),
        'is_active'       => 1,
        'created_by'      => 'sa',
        'created_at'      => $now,
    ]);
}
echo "  - created " . count($alertDefs) . " alert definitions\n";

// --- 9. tickets + activity timelines -------------------------------------

// alarm id generator (matches ALM-YYYYMMDD-XXXXX, per-day sequence)
$almSeq = [];
function nextAlarm(&$almSeq, $dayKey)
{
    if (!isset($almSeq[$dayKey])) {
        $almSeq[$dayKey] = 0;
    }
    $almSeq[$dayKey]++;
    return 'ALM-' . $dayKey . '-' . str_pad((string) $almSeq[$dayKey], 5, '0', STR_PAD_LEFT);
}

function tsDaysAgo($days, $h = 9, $m = 0)
{
    return date('Y-m-d H:i:s', strtotime("-$days days " . sprintf('%02d:%02d:00', $h, $m)));
}

// Each ticket: project, flow, current state, level, assignee, status,
// alert_type, priority, title, raised_by, age (days), plus an action log.
$ticketPlan = [
    [
        'flow' => 'incident', 'project' => 'core', 'state' => 'Triage', 'level' => 1,
        'assignee' => 'noc.l1', 'status' => 'in_progress', 'alert_type' => 'critical', 'priority' => 'urgent',
        'title' => 'Core router CR-DEL-01 CPU sustained at 96%', 'raised_by' => 'sa', 'age' => 0,
        'actions' => [
            ['created',   'sa',     'Ticket raised from Core Router CPU High alert', null, null],
            ['assigned',  'noc.l1', 'Assigned to Aarav Patel (L1)', null, null],
            ['commented', 'noc.l1', 'Confirmed high CPU on control plane, investigating process table.', null, null],
        ],
    ],
    [
        'flow' => 'incident', 'project' => 'core', 'state' => 'Investigation', 'level' => 1,
        'assignee' => 'noc.l2', 'status' => 'in_progress', 'alert_type' => 'critical', 'priority' => 'high',
        'title' => 'Backbone link DEL-MUM-TRUNK-2 flapping', 'raised_by' => 'sa', 'age' => 1,
        'actions' => [
            ['created',      'sa',     'Ticket raised from Backbone Link Down alert', null, null],
            ['assigned',     'noc.l1', 'Assigned to Aarav Patel (L1)', null, null],
            ['state_changed','noc.l1', 'Forward transition to Investigation', 'Triage', 'Investigation'],
            ['assigned',     'noc.l2', 'Reassigned to Priya Sharma (L2)', null, null],
            ['commented',    'noc.l2', 'Optical levels degraded on east span, suspect fiber.', null, null],
        ],
    ],
    [
        'flow' => 'incident', 'project' => 'core', 'state' => 'Hardware Diagnosis', 'level' => 1,
        'assignee' => 'noc.l2', 'status' => 'escalated', 'alert_type' => 'critical', 'priority' => 'urgent',
        'title' => 'Line card failure on CR-MUM-03 slot 4', 'raised_by' => 'admin', 'age' => 2,
        'actions' => [
            ['created',       'admin',  'Ticket raised by Operations Admin', null, null],
            ['state_changed', 'noc.l1', 'Forward transition to Investigation', 'Triage', 'Investigation'],
            ['state_changed', 'noc.l1', 'Forward transition to Hardware Track', 'Investigation', 'Hardware Track'],
            ['state_changed', 'noc.l2', 'Forward transition to Hardware Diagnosis', 'Hardware Track', 'Hardware Diagnosis'],
            ['level_escalated','noc.l2','TAT breached at L1, escalated to L2', null, null, 1, 2],
        ],
    ],
    [
        'flow' => 'incident', 'project' => 'core', 'state' => 'Investigation', 'level' => 1,
        'assignee' => 'noc.l2', 'status' => 'in_progress', 'alert_type' => 'major', 'priority' => 'high',
        'title' => 'Intermittent packet loss on peering edge PE-BLR-1', 'raised_by' => 'sa', 'age' => 1,
        'actions' => [
            ['created',       'sa',     'Ticket raised from monitoring', null, null],
            ['state_changed', 'noc.l1', 'Forward transition to Investigation', 'Triage', 'Investigation'],
            ['state_changed', 'noc.l2', 'Backward transition to Triage: needs baseline recheck', 'Investigation', 'Triage', null, null, 'backward'],
            ['state_changed', 'noc.l2', 'Forward transition to Investigation', 'Triage', 'Investigation'],
            ['commented',     'noc.l2', 'Re-ran baseline, loss correlates with peak BGP churn.', null, null],
        ],
    ],
    [
        'flow' => 'incident', 'project' => 'core', 'state' => 'Closure', 'level' => 1,
        'assignee' => null, 'status' => 'resolved', 'alert_type' => 'major', 'priority' => 'medium',
        'title' => 'OSPF adjacency reset on CR-HYD-02', 'raised_by' => 'noc.l1', 'age' => 3,
        'actions' => [
            ['created',       'noc.l1', 'Ticket raised by Aarav Patel', null, null],
            ['state_changed', 'noc.l1', 'Forward transition to Investigation', 'Triage', 'Investigation'],
            ['state_changed', 'noc.l2', 'Forward transition to Software Track', 'Investigation', 'Software Track'],
            ['state_changed', 'noc.l2', 'Forward transition to Patch Validation', 'Software Track', 'Patch Validation'],
            ['state_changed', 'team.lead', 'Forward transition to Patch Rollout', 'Patch Validation', 'Patch Rollout'],
            ['state_changed', 'team.lead', 'Forward transition to Closure', 'Patch Rollout', 'Closure'],
            ['resolved',      'team.lead', 'Adjacency stable after timer tuning', null, null],
        ],
    ],
    [
        'flow' => 'service', 'project' => 'dc', 'state' => 'Approval', 'level' => 1,
        'assignee' => 'team.lead', 'status' => 'in_progress', 'alert_type' => 'info', 'priority' => 'medium',
        'title' => 'New VM request: 8 vCPU / 32GB for billing-stage', 'raised_by' => 'noc.l1', 'age' => 1,
        'actions' => [
            ['created',       'noc.l1', 'Service request submitted', null, null],
            ['state_changed', 'noc.l1', 'Forward transition to Approval', 'Submitted', 'Approval'],
            ['assigned',      'team.lead', 'Assigned to Karthik Iyer for approval', null, null],
        ],
    ],
    [
        'flow' => 'service', 'project' => 'dc', 'state' => 'Provisioning', 'level' => 1,
        'assignee' => 'noc.l2', 'status' => 'in_progress', 'alert_type' => 'info', 'priority' => 'low',
        'title' => 'Storage pool expansion for backup-tier (+10TB)', 'raised_by' => 'admin', 'age' => 2,
        'actions' => [
            ['created',       'admin',  'Service request submitted by Operations Admin', null, null],
            ['state_changed', 'noc.l1', 'Forward transition to Approval', 'Submitted', 'Approval'],
            ['state_changed', 'team.lead', 'Forward transition to Provisioning', 'Approval', 'Provisioning'],
            ['assigned',      'noc.l2', 'Assigned to Priya Sharma for provisioning', null, null],
        ],
    ],
    [
        'flow' => 'service', 'project' => 'dc', 'state' => 'Completed', 'level' => 1,
        'assignee' => null, 'status' => 'closed', 'alert_type' => 'info', 'priority' => 'low',
        'title' => 'Decommission legacy host esx-old-07', 'raised_by' => 'noc.l1', 'age' => 5,
        'actions' => [
            ['created',       'noc.l1', 'Service request submitted', null, null],
            ['state_changed', 'noc.l1', 'Forward transition to Approval', 'Submitted', 'Approval'],
            ['state_changed', 'team.lead', 'Forward transition to Provisioning', 'Approval', 'Provisioning'],
            ['state_changed', 'noc.l2', 'Forward transition to Completed', 'Provisioning', 'Completed'],
            ['resolved',      'noc.l2', 'Host wiped and removed from inventory', null, null],
            ['closed',        'admin',  'Verified and closed', null, null],
        ],
    ],
    [
        'flow' => 'change', 'project' => 'edge', 'state' => 'Review', 'level' => 1,
        'assignee' => 'noc.l2', 'status' => 'in_progress', 'alert_type' => 'major', 'priority' => 'high',
        'title' => 'Edge firmware upgrade for 12 CPE sites (batch B)', 'raised_by' => 'team.lead', 'age' => 1,
        'actions' => [
            ['created',       'team.lead', 'Change request raised by Karthik Iyer', null, null],
            ['state_changed', 'noc.l1',    'Forward transition to Review', 'Requested', 'Review'],
            ['assigned',      'noc.l2',    'Assigned to Priya Sharma for technical review', null, null],
            ['commented',     'noc.l2',    'Validating rollback plan before CAB.', null, null],
        ],
    ],
    [
        'flow' => 'change', 'project' => 'edge', 'state' => 'CAB Approval', 'level' => 1,
        'assignee' => 'team.lead', 'status' => 'escalated', 'alert_type' => 'major', 'priority' => 'high',
        'title' => 'Access ring re-route for MUM-METRO maintenance', 'raised_by' => 'admin', 'age' => 2,
        'actions' => [
            ['created',        'admin',     'Change request raised by Operations Admin', null, null],
            ['state_changed',  'noc.l1',    'Forward transition to Review', 'Requested', 'Review'],
            ['state_changed',  'noc.l2',    'Forward transition to CAB Approval', 'Review', 'CAB Approval'],
            ['level_escalated','team.lead', 'Awaiting CAB sign-off, escalated', null, null, 1, 2],
        ],
    ],
    [
        'flow' => 'change', 'project' => 'edge', 'state' => 'Requested', 'level' => 1,
        'assignee' => null, 'status' => 'open', 'alert_type' => 'info', 'priority' => 'low',
        'title' => 'Enable SNMPv3 on remaining access switches', 'raised_by' => 'noc.l1', 'age' => 0,
        'actions' => [
            ['created', 'noc.l1', 'Change request submitted, awaiting pickup', null, null],
        ],
    ],
    [
        'flow' => 'incident', 'project' => 'core', 'state' => 'Triage', 'level' => 1,
        'assignee' => null, 'status' => 'open', 'alert_type' => 'info', 'priority' => 'low',
        'title' => 'Minor interface error counters rising on AGG-PUN-5', 'raised_by' => 'sa', 'age' => 0,
        'actions' => [
            ['created', 'sa', 'Ticket raised from monitoring, awaiting triage', null, null],
        ],
    ],
];

$ticketCount = 0;
$actionCount = 0;
$notifCount  = 0;

foreach ($ticketPlan as $tp) {
    $flowId  = $flows[$tp['flow']];
    $stateId = $stateIds[$flowId][$tp['state']];
    $created = tsDaysAgo($tp['age'], 9, 15);
    $dayKey  = date('Ymd', strtotime($created));
    $alarmId = nextAlarm($almSeq, $dayKey);

    $isResolved = in_array($tp['status'], ['resolved', 'closed'], true);
    $isClosed   = ($tp['status'] === 'closed');

    $ticketId = ins($db, 'tickets', [
        'alarm_id'          => $alarmId,
        'project_id'        => $projects[$tp['project']],
        'flow_id'           => $flowId,
        'title'             => $tp['title'],
        'description'       => $tp['title'] . '. Auto-generated demo incident for presentation.',
        'alert_type'        => $tp['alert_type'],
        'priority'          => $tp['priority'],
        'actual_start_date' => $today,
        'actual_end_date'   => $isResolved ? $today : null,
        'current_state_id'  => $stateId,
        'current_level'     => $tp['level'],
        'current_assignee'  => $tp['assignee'],
        'status'            => $tp['status'],
        'source'            => 'ui',
        'raised_by'         => $tp['raised_by'],
        'state_entered_at'  => $created,
        'resolved_at'       => $isResolved ? $created : null,
        'closed_at'         => $isClosed ? $created : null,
        'created_at'        => $created,
    ]);
    $ticketCount++;

    // action timeline — spaced a few minutes apart, starting at created time
    $step = 0;
    foreach ($tp['actions'] as $a) {
        $atype   = $a[0];
        $by      = $a[1];
        $comment = $a[2];
        $fromNm  = $a[3];
        $toNm    = $a[4];
        $fromLvl = isset($a[5]) ? $a[5] : null;
        $toLvl   = isset($a[6]) ? $a[6] : null;
        $transTy = isset($a[7]) ? $a[7] : null;

        $fromId = ($fromNm !== null && isset($stateIds[$flowId][$fromNm])) ? $stateIds[$flowId][$fromNm] : null;
        $toId   = ($toNm !== null && isset($stateIds[$flowId][$toNm]))   ? $stateIds[$flowId][$toNm]   : null;

        // forward/backward inference for state_changed when not explicit
        if ($atype === 'state_changed' && $transTy === null) {
            $transTy = 'forward';
        }

        $ats = date('Y-m-d H:i:s', strtotime($created . " +" . ($step * 7) . " minutes"));
        ins($db, 'ticket_actions', [
            'ticket_id'       => $ticketId,
            'action_type'     => $atype,
            'from_state_id'   => $fromId,
            'to_state_id'     => $toId,
            'transition_type' => $transTy,
            'from_level'      => $fromLvl,
            'to_level'        => $toLvl,
            'comment'         => $comment,
            'performed_by'    => $by,
            'created_at'      => $ats,
        ]);
        $actionCount++;
        $step++;
    }

    // one notification log per assigned/active ticket (sent)
    if ($tp['assignee'] !== null) {
        $assigneeEmail = $tp['assignee'] . '@demo.local';
        ins($db, 'notification_logs', [
            'ticket_id'       => $ticketId,
            'channel'         => 'email',
            'recipient_email' => $assigneeEmail,
            'subject'         => '[' . $alarmId . '] ' . $tp['title'],
            'body'            => 'You have been assigned ticket ' . $alarmId . '.',
            'status'          => 'sent',
            'sent_at'         => $created,
            'created_at'      => $created,
        ]);
        $notifCount++;
    }

    // activity log entry for the raise
    ins($db, 'activity_logs', [
        'user_id'     => $tp['raised_by'],
        'user_name'   => 'Demo Seed',
        'user_role'   => 'system',
        'module'      => 'tickets',
        'action'      => 'create',
        'entity_type' => 'ticket',
        'entity_id'   => $alarmId,
        'summary'     => 'Raised ticket ' . $alarmId,
        'project_id'  => $projects[$tp['project']],
        'created_at'  => $created,
    ]);
}

// keep alarm_id_sequence consistent with what we generated
foreach ($almSeq as $dayKey => $last) {
    ins($db, 'alarm_id_sequence', [
        'day_key'  => $dayKey,
        'last_seq' => $last,
    ]);
}

echo "  - created $ticketCount tickets, $actionCount actions, $notifCount notifications\n";

q($db, 'SET FOREIGN_KEY_CHECKS = 1');

echo "Done.\n";
mysqli_close($db);
