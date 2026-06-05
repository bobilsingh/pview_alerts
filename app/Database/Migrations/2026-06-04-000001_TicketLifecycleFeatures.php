<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class TicketLifecycleFeatures extends Migration
{
    public function up()
    {
        // TL-5: add 'reopened' action type so reopen events can be logged
        $this->db->query("ALTER TABLE `ticket_actions`
            MODIFY COLUMN `action_type` ENUM(
                'created','commented','state_changed','level_escalated',
                'assigned','attachment','resolved','closed','reopened',
                'api_update','title_changed','description_changed','priority_changed'
            ) NOT NULL");

        // TL-7: per-flow configurable TAT level count (1-4, default 4)
        $this->db->query("ALTER TABLE `flows`
            ADD COLUMN `tat_level_count` TINYINT UNSIGNED NOT NULL DEFAULT 4
            AFTER `status`");

        // TL-6: setting for duplicate detection window (hours)
        $this->db->query("INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`, `updated_at`)
            VALUES ('duplicate_detection_window_hours', '24', NOW())");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE `ticket_actions`
            MODIFY COLUMN `action_type` ENUM(
                'created','commented','state_changed','level_escalated',
                'assigned','attachment','resolved','closed',
                'api_update','title_changed','description_changed','priority_changed'
            ) NOT NULL");

        $this->db->query("ALTER TABLE `flows`
            DROP COLUMN `tat_level_count`");

        $this->db->query("DELETE FROM `app_settings` WHERE `setting_key` = 'duplicate_detection_window_hours'");
    }
}
