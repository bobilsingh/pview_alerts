<?php

if (!function_exists('export_csv_data')) {
    /**
     * Helper to stream data rows as a CSV file.
     *
     * @param string $filename          Name of the generated file (e.g. 'tickets.csv')
     * @param string $module            The module key ('tickets' or 'activity_logs')
     * @param array  $rows              Array of database rows to export
     * @param string $userSelectedCols  Comma-separated list of columns selected by the user (optional)
     */
    function export_csv_data(string $filename, string $module, array $rows, string $userSelectedCols = '')
    {
        // Define default column lists and Excel header labels based on the module
        $columnMap = [];

        if ($module === 'tickets') {
            $columnMap = [
                'alarm_id'        => 'Alarm ID',
                'title'           => 'Title',
                'project_name'    => 'Project',
                'flow_name'       => 'Flow',
                'state_name'      => 'State',
                'current_level'   => 'Level',
                'alert_type'      => 'Severity',
                'priority'        => 'Priority',
                'status'          => 'Status',
                'assignee_name'   => 'Assignee',
                'raised_by_name'  => 'Raised By',
                'source'          => 'Source',
                'created_at'      => 'Created At',
                'resolved_at'     => 'Resolved At',
                'closed_at'       => 'Closed At',
            ];
        } else if ($module === 'activity_logs') {
            $columnMap = [
                'created_at'  => 'Time',
                'user_id'     => 'User ID',
                'user_name'   => 'User Name',
                'user_role'   => 'Role',
                'module'      => 'Module',
                'action'      => 'Action',
                'entity_type' => 'Entity Type',
                'entity_id'   => 'Entity ID',
                'summary'     => 'Summary',
                'ip_address'  => 'IP Address',
                'url'         => 'URL',
                'method'      => 'Method',
                'status'      => 'Status',
                'meta'        => 'Meta',
            ];
        }

        // Apply user column selection filters if sent from frontend
        if ($userSelectedCols !== '') {
            $selectedKeys = explode(',', $userSelectedCols);
            $filteredMap = [];
            foreach ($selectedKeys as $key) {
                $key = trim($key);
                if (isset($columnMap[$key])) {
                    $filteredMap[$key] = $columnMap[$key];
                }
            }
            if (!empty($filteredMap)) {
                $columnMap = $filteredMap;
            }
        }

        // Stream headers to initiate standard browser download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel handles special characters cleanly
        fwrite($out, "\xEF\xBB\xBF");

        // Generate headers row
        fputcsv($out, array_values($columnMap));

        // Generate data rows
        foreach ($rows as $r) {
            $line = [];
            foreach ($columnMap as $field => $label) {
                $val = '';
                if (isset($r[$field])) {
                    $val = $r[$field];
                }

                // Format values based on column-specific rules (without closures or ternary operators)
                if ($module === 'tickets') {
                    if ($field === 'current_level') {
                        $val = 'L' . (int) $val;
                    } else if ($field === 'alert_type') {
                        $val = strtoupper((string) $val);
                    } else if ($field === 'priority') {
                        $val = strtoupper((string) $val);
                    } else if ($field === 'status') {
                        $val = str_replace('_', ' ', strtoupper((string) $val));
                    }
                }

                $line[] = $val;
            }
            fputcsv($out, $line);
        }

        fclose($out);
        exit;
    }
}
