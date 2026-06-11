<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class MakeModulesDynamic extends Migration
{
    public function up()
    {
        // 1. Alter table `modules` to add columns
        $this->db->query("ALTER TABLE `modules`
            ADD COLUMN `permission_module_key` VARCHAR(100) NULL AFTER `module_key`,
            ADD COLUMN `permission_action` VARCHAR(50) NOT NULL DEFAULT 'view' AFTER `permission_module_key`,
            ADD COLUMN `category` VARCHAR(100) NOT NULL DEFAULT 'General' AFTER `name`,
            ADD COLUMN `icon` VARCHAR(100) NOT NULL DEFAULT 'bi-circle' AFTER `category`,
            ADD COLUMN `uri_path` VARCHAR(255) NULL AFTER `icon`,
            ADD COLUMN `show_in_menu` TINYINT NOT NULL DEFAULT 1 AFTER `uri_path`");

        $db = \Config\Database::connect();

        // 2. Update existing modules
        $updates = [
            'dashboard' => [
                'category' => 'Overview',
                'icon' => 'bi-speedometer2',
                'uri_path' => 'dashboard',
                'permission_module_key' => 'dashboard',
                'permission_action' => 'view'
            ],
            'projects' => [
                'category' => 'Configuration',
                'icon' => 'bi-folder2-open',
                'uri_path' => 'projects',
                'permission_module_key' => 'projects',
                'permission_action' => 'view'
            ],
            'flows' => [
                'category' => 'Configuration',
                'icon' => 'bi-diagram-3',
                'uri_path' => 'flows',
                'permission_module_key' => 'flows',
                'permission_action' => 'view'
            ],
            'alerts' => [
                'category' => 'Configuration',
                'icon' => 'bi-bell-fill',
                'uri_path' => 'alerts',
                'permission_module_key' => 'alerts',
                'permission_action' => 'view'
            ],
            'escalation' => [
                'category' => 'Configuration',
                'icon' => 'bi-graph-up-arrow',
                'uri_path' => 'escalation',
                'permission_module_key' => 'escalation',
                'permission_action' => 'view'
            ],
            'tickets' => [
                'name' => 'My Tickets',
                'category' => 'Operations',
                'icon' => 'bi-inbox-fill',
                'uri_path' => 'tickets',
                'permission_module_key' => 'tickets',
                'permission_action' => 'view'
            ],
            'tickets_all' => [
                'category' => 'Operations',
                'icon' => 'bi-list-task',
                'uri_path' => 'tickets/all',
                'permission_module_key' => 'tickets_all',
                'permission_action' => 'view'
            ],
            'users' => [
                'category' => 'System',
                'icon' => 'bi-people-fill',
                'uri_path' => 'users',
                'permission_module_key' => 'users',
                'permission_action' => 'view'
            ],
            'api_keys' => [
                'category' => 'System',
                'icon' => 'bi-key-fill',
                'uri_path' => 'api_keys',
                'permission_module_key' => 'api_keys',
                'permission_action' => 'view'
            ],
            'activity_logs' => [
                'category' => 'System',
                'icon' => 'bi-clipboard-data',
                'uri_path' => 'activity_logs',
                'permission_module_key' => 'activity_logs',
                'permission_action' => 'view'
            ],
            'cron_panel' => [
                'category' => 'System',
                'icon' => 'bi-clock-history',
                'uri_path' => 'cron_panel',
                'permission_module_key' => 'cron_panel',
                'permission_action' => 'view'
            ],
            'roles' => [
                'category' => 'Administration',
                'icon' => 'bi-person-badge',
                'uri_path' => 'roles',
                'permission_module_key' => 'roles',
                'permission_action' => 'view'
            ],
            'module_control_panel' => [
                'name' => 'Manage Modules',
                'category' => 'Administration',
                'icon' => 'bi-shield-lock-fill',
                'uri_path' => 'module_control_panel',
                'permission_module_key' => 'module_control_panel',
                'permission_action' => 'view'
            ]
        ];

        foreach ($updates as $key => $fields) {
            $db->table('modules')
                ->where('module_key', $key)
                ->update($fields);
        }

        // 3. Insert new menu/module rows
        // "Raise Ticket" menu item
        $existingRaise = $db->table('modules')->where('module_key', 'tickets_create')->countAllResults() > 0;
        if (!$existingRaise) {
            $db->table('modules')->insert([
                'module_key' => 'tickets_create',
                'permission_module_key' => 'tickets',
                'permission_action' => 'add',
                'name' => 'Raise Ticket',
                'category' => 'Operations',
                'icon' => 'bi-plus-square',
                'uri_path' => 'tickets/create',
                'show_in_menu' => 1,
                'description' => 'Form to manually raise a new alert',
                'is_builtin' => 0,
                'sort_order' => 65,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => 'migration'
            ]);
        }

        // "Settings" menu item
        $existingSettings = $db->table('modules')->where('module_key', 'settings')->countAllResults() > 0;
        if (!$existingSettings) {
            $db->table('modules')->insert([
                'module_key' => 'settings',
                'permission_module_key' => 'settings',
                'permission_action' => 'view',
                'name' => 'Settings',
                'category' => 'Administration',
                'icon' => 'bi-gear-fill',
                'uri_path' => 'settings',
                'show_in_menu' => 1,
                'description' => 'System settings — super_admin only',
                'is_builtin' => 1,
                'sort_order' => 125,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => 'migration'
            ]);
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        // 1. Delete rows inserted by migration
        $db->table('modules')->whereIn('module_key', ['tickets_create', 'settings'])->delete();

        // 2. Revert module names
        $db->table('modules')->where('module_key', 'tickets')->update(['name' => 'Tickets (My & Raise)']);
        $db->table('modules')->where('module_key', 'module_control_panel')->update(['name' => 'Module Permissions']);

        // 3. Drop columns
        $this->db->query("ALTER TABLE `modules`
            DROP COLUMN `permission_module_key`,
            DROP COLUMN `permission_action`,
            DROP COLUMN `category`,
            DROP COLUMN `icon`,
            DROP COLUMN `uri_path`,
            DROP COLUMN `show_in_menu`");
    }
}
