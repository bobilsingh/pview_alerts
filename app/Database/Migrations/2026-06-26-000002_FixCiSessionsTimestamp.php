<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixCiSessionsTimestamp extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE `ci_sessions` MODIFY `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE `ci_sessions` MODIFY `timestamp` INT(10) UNSIGNED DEFAULT 0 NOT NULL");
    }
}
