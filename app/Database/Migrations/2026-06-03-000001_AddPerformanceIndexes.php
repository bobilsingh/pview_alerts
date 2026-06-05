<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPerformanceIndexes extends Migration
{
    public function up()
    {
        // tickets.state_entered_at — speeds up TAT breach queries that filter
        // open/in_progress/escalated tickets by how long they have been in the
        // current state. Without this index those queries do a full table scan.
        $this->db->query('ALTER TABLE `tickets`
            ADD INDEX `idx_state_entered_at` (`state_entered_at`)');

        // notification_logs (status, id) — idx_notification_logs_status_id already
        // existed in the original schema, so nothing to add here.

        // escalation_matrix (flow_id, state_id, level) — escalation rule lookups
        // always filter on all three columns. The existing separate indexes on
        // flow_id and state_id require two lookups and a merge; a composite
        // index answers the query in a single range scan.
        $this->db->query('ALTER TABLE `escalation_matrix`
            ADD INDEX `idx_flow_state_level` (`flow_id`, `state_id`, `level`)');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `tickets`
            DROP INDEX `idx_state_entered_at`');

        $this->db->query('ALTER TABLE `escalation_matrix`
            DROP INDEX `idx_flow_state_level`');
    }
}
