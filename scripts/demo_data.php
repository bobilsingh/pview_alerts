<?php

/**
 * pView Alert System — single-user end-to-end test seed.
 *
 * Creates the minimum data needed to test the full ticket lifecycle:
 *   1 project → 1 flow (3 states) → 1 alert definition → 1 ticket
 *
 * Uses the existing demo_usr (Admin) account. Does NOT touch users,
 * roles, module_permissions, app_settings, migrations, or api_keys.
 *
 * Safe to re-run: truncates seeded tables first.
 *
 * Usage:  php scripts/seed_demo_data.php
 */

$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'pview_alerts';

$OPERATOR = 'admin';

$db = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if (!$db) {
    fwrite(STDERR, 'DB connection failed: ' . mysqli_connect_error() . PHP_EOL);
    exit(1);
}
mysqli_set_charset($db, 'utf8mb4');

// ---------- helpers ----------------------------------------------------------

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

$now   = date('Y-m-d H:i:s');
$today = date('Y-m-d');

echo "Seeding pView end-to-end test data (operator: $OPERATOR)...\n";

// ---------- 1. wipe ----------------------------------------------------------

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
q($db, 'SET FOREIGN_KEY_CHECKS = 1');
echo "  - wiped " . count($wipe) . " tables\n";

// ---------- 2. project -------------------------------------------------------

$projectId = ins($db, 'projects', [
    'name'        => 'Core Network Monitoring',
    'description' => 'Backbone routers, transport links and core switching fabric.',
    'status'      => 'active',
    'created_by'  => $OPERATOR,
    'created_at'  => $now,
]);
echo "  - created project: Core Network Monitoring (id=$projectId)\n";

// ---------- 3. flow + states -------------------------------------------------
// Simple 3-state linear flow: Triage → Investigation → Closure

$flowId = ins($db, 'flows', [
    'project_id' => $projectId,
    'name'       => 'Incident Response',
    'status'     => 'active',
    'created_by' => $OPERATOR,
    'created_at' => $now,
]);
echo "  - created flow: Incident Response (id=$flowId)\n";

$stateIds = [];

$stateIds['Triage'] = ins($db, 'states', [
    'flow_id'         => $flowId,
    'name'            => 'Triage',
    'parent_state_id' => null,
    'sort_order'      => 1,
    'is_initial'      => 1,
    'is_final'        => 0,
    'l1_user_ids'     => jids([$OPERATOR]),
    'l1_tat_minutes'  => 30,
    'l2_user_ids'     => jids([]),
    'l2_tat_minutes'  => 60,
    'l3_user_ids'     => jids([]),
    'l3_tat_minutes'  => 120,
    'l4_user_ids'     => jids([]),
    'l4_tat_minutes'  => 240,
    'status'          => 'active',
    'created_by'      => $OPERATOR,
    'created_at'      => $now,
]);

$stateIds['Investigation'] = ins($db, 'states', [
    'flow_id'         => $flowId,
    'name'            => 'Investigation',
    'parent_state_id' => $stateIds['Triage'],
    'sort_order'      => 2,
    'is_initial'      => 0,
    'is_final'        => 0,
    'l1_user_ids'     => jids([$OPERATOR]),
    'l1_tat_minutes'  => 60,
    'l2_user_ids'     => jids([]),
    'l2_tat_minutes'  => 120,
    'l3_user_ids'     => jids([]),
    'l3_tat_minutes'  => 240,
    'l4_user_ids'     => jids([]),
    'l4_tat_minutes'  => 480,
    'status'          => 'active',
    'created_by'      => $OPERATOR,
    'created_at'      => $now,
]);

$stateIds['Closure'] = ins($db, 'states', [
    'flow_id'         => $flowId,
    'name'            => 'Closure',
    'parent_state_id' => null,
    'sort_order'      => 3,
    'is_initial'      => 0,
    'is_final'        => 1,
    'l1_user_ids'     => jids([]),
    'l1_tat_minutes'  => 30,
    'l2_user_ids'     => jids([]),
    'l2_tat_minutes'  => 60,
    'l3_user_ids'     => jids([]),
    'l3_tat_minutes'  => 120,
    'l4_user_ids'     => jids([]),
    'l4_tat_minutes'  => 240,
    'status'          => 'active',
    'created_by'      => $OPERATOR,
    'created_at'      => $now,
]);

echo "  - created 3 states: Triage → Investigation → Closure\n";

// ---------- 4. escalation rule -----------------------------------------------

ins($db, 'escalation_matrix', [
    'flow_id'         => $flowId,
    'state_id'        => $stateIds['Triage'],
    'level'           => 1,
    'escalate_after'  => 30,
    'notify_user_ids' => jids([$OPERATOR]),
    'alert_type'      => 'critical',
    'created_by'      => $OPERATOR,
    'created_at'      => $now,
]);
echo "  - created escalation rule: Triage L1 → 30 min → critical\n";

// ---------- 5. alert definition ----------------------------------------------

$alertDefId = ins($db, 'alert_definitions', [
    'project_id'      => $projectId,
    'name'            => 'Core Router CPU High',
    'description'     => 'Fires when core router CPU sustains above 90% for 5 minutes.',
    'alert_type'      => 'critical',
    'threshold_value' => '90',
    'threshold_unit'  => '%',
    'flow_id'         => $flowId,
    'notify_user_ids' => jids([$OPERATOR]),
    'is_active'       => 1,
    'created_by'      => $OPERATOR,
    'created_at'      => $now,
]);
echo "  - created alert definition: Core Router CPU High (id=$alertDefId)\n";

// ---------- 6. ticket --------------------------------------------------------

$dayKey  = date('Ymd');
$alarmId = 'ALM-' . $dayKey . '-00001';

ins($db, 'alarm_id_sequence', [
    'day_key'  => $dayKey,
    'last_seq' => 1,
]);

$ticketId = ins($db, 'tickets', [
    'alarm_id'          => $alarmId,
    'project_id'        => $projectId,
    'flow_id'           => $flowId,
    'alert_def_id'      => $alertDefId,
    'title'             => 'Core router CR-DEL-01 CPU sustained at 96%',
    'description'       => 'CPU utilization on CR-DEL-01 has been above 90% for the last 10 minutes. Control plane processes are impacted. Immediate triage required.',
    'alert_type'        => 'critical',
    'priority'          => 'urgent',
    'actual_start_date' => $today,
    'actual_end_date'   => null,
    'current_state_id'  => $stateIds['Triage'],
    'current_level'     => 1,
    'current_assignee'  => $OPERATOR,
    'status'            => 'in_progress',
    'source'            => 'ui',
    'raised_by'         => $OPERATOR,
    'state_entered_at'  => $now,
    'resolved_at'       => null,
    'closed_at'         => null,
    'created_at'        => $now,
]);
echo "  - created ticket: $alarmId (id=$ticketId)\n";

// ---------- 7. ticket action timeline ----------------------------------------

$actions = [
    ['created',   $OPERATOR, 'Ticket raised from Core Router CPU High alert',          null,          null],
    ['assigned',  $OPERATOR, 'Assigned to Demo for triage',                            null,          null],
    ['commented', $OPERATOR, 'Confirmed high CPU on control plane. Checking process table.', null,    null],
];

$step = 0;
foreach ($actions as $a) {
    $ats = date('Y-m-d H:i:s', strtotime($now . ' +' . ($step * 5) . ' minutes'));
    ins($db, 'ticket_actions', [
        'ticket_id'       => $ticketId,
        'action_type'     => $a[0],
        'from_state_id'   => $a[3],
        'to_state_id'     => $a[4],
        'transition_type' => null,
        'from_level'      => null,
        'to_level'        => null,
        'comment'         => $a[2],
        'performed_by'    => $a[1],
        'created_at'      => $ats,
    ]);
    $step++;
}
echo "  - created " . count($actions) . " ticket actions\n";

// ---------- 8. activity log --------------------------------------------------

ins($db, 'activity_logs', [
    'user_id'     => $OPERATOR,
    'user_name'   => 'Demo',
    'user_role'   => 'admin',
    'module'      => 'tickets',
    'action'      => 'create',
    'entity_type' => 'ticket',
    'entity_id'   => $alarmId,
    'summary'     => 'Raised ticket ' . $alarmId,
    'project_id'  => $projectId,
    'created_at'  => $now,
]);

echo "  - created activity log entry\n";
echo "\nDone. Login as '$OPERATOR' and open ticket $alarmId to test the full lifecycle.\n";

mysqli_close($db);
