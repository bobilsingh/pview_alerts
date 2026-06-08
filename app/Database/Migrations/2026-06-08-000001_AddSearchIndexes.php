<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSearchIndexes extends Migration
{
    public function up()
    {
        // tickets — FULLTEXT index enables MATCH...AGAINST search on alarm_id, title, description.
        $this->db->query('ALTER TABLE `tickets`
            ADD FULLTEXT INDEX `ft_tickets_search` (`alarm_id`, `title`, `description`)');

        // activity_logs — composite index for module/action dropdown filters.
        $this->db->query('ALTER TABLE `activity_logs`
            ADD INDEX `idx_activity_module_action` (`module`, `action`)');

        // activity_logs — index on created_at for date-range filter queries.
        $this->db->query('ALTER TABLE `activity_logs`
            ADD INDEX `idx_activity_created_at` (`created_at`)');

        // activity_logs — FULLTEXT index on summary for the global search box.
        $this->db->query('ALTER TABLE `activity_logs`
            ADD FULLTEXT INDEX `ft_activity_summary` (`summary`)');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `tickets`
            DROP INDEX `ft_tickets_search`');

        $this->db->query('ALTER TABLE `activity_logs`
            DROP INDEX `idx_activity_module_action`');

        $this->db->query('ALTER TABLE `activity_logs`
            DROP INDEX `idx_activity_created_at`');

        $this->db->query('ALTER TABLE `activity_logs`
            DROP INDEX `ft_activity_summary`');
    }
}
