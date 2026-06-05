<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCronRuns extends Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `cron_runs` (
            `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `script`          VARCHAR(100) NOT NULL,
            `started_at`      DATETIME     NOT NULL,
            `finished_at`     DATETIME     DEFAULT NULL,
            `duration_ms`     INT UNSIGNED DEFAULT 0,
            `status`          ENUM('ok','failed') NOT NULL DEFAULT 'ok',
            `tickets_checked` INT UNSIGNED DEFAULT 0,
            `notifs_sent`     INT UNSIGNED DEFAULT 0,
            `notifs_failed`   INT UNSIGNED DEFAULT 0,
            `output_summary`  TEXT         DEFAULT NULL,
            INDEX `idx_cron_script_started` (`script`, `started_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down()
    {
        $this->db->query("DROP TABLE IF EXISTS `cron_runs`");
    }
}
