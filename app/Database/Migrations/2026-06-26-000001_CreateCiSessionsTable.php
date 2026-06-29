<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCiSessionsTable extends Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `ci_sessions` (
            `id`          VARCHAR(128) NOT NULL,
            `ip_address`  VARCHAR(45) NOT NULL,
            `timestamp`   INT(10) UNSIGNED DEFAULT 0 NOT NULL,
            `data`        BLOB NOT NULL,
            PRIMARY KEY (`id`),
            KEY `ci_sessions_timestamp` (`timestamp`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down()
    {
        $this->db->query("DROP TABLE IF EXISTS `ci_sessions`");
    }
}
