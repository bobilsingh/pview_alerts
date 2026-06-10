<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBrandingSettings extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        $settings = [
            [
                'setting_key'   => 'client_name',
                'setting_value' => 'AlertOps',
                'description'   => 'Client organization display name',
            ],
            [
                'setting_key'   => 'app_logo',
                'setting_value' => '',
                'description'   => 'Path to custom uploaded logo',
            ],
            [
                'setting_key'   => 'app_favicon',
                'setting_value' => '',
                'description'   => 'Path to custom uploaded favicon',
            ],
            [
                'setting_key'   => 'primary_color',
                'setting_value' => '',
                'description'   => 'Primary theme color (hex format, e.g. #0792cd)',
            ],
            [
                'setting_key'   => 'secondary_color',
                'setting_value' => '',
                'description'   => 'Secondary theme color (hex format, e.g. #0476a7)',
            ],
        ];

        foreach ($settings as $s) {
            $exists = $db->table('app_settings')
                ->where('setting_key', $s['setting_key'])
                ->countAllResults() > 0;
            if ($exists === false) {
                $db->table('app_settings')->insert([
                    'setting_key'   => $s['setting_key'],
                    'setting_value' => $s['setting_value'],
                    'description'   => $s['description'],
                    'updated_at'    => date('Y-m-d H:i:s'),
                    'updated_by'    => 'migration',
                ]);
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $keys = ['client_name', 'app_logo', 'app_favicon', 'primary_color', 'secondary_color'];
        foreach ($keys as $key) {
            $db->table('app_settings')->where('setting_key', $key)->delete();
        }
    }
}
