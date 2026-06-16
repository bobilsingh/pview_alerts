<?php
/**
 * Helper Model
 *
 * Dedicated database helper queries mapping.
 */

namespace App\Models;

class Helper_model
{
    public $db;

    // Constructor to initialize raw database connection.
    function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    // Fetches all application settings for caching.
    public function getSettings()
    {
        return $this->db->table('app_settings')->get()->getResultArray();
    }

    // Retrieves assignee roles for dropdown whitelist.
    public function getAssignableRoles()
    {
        return $this->db->table('roles')
            ->select('role_key, is_admin_scope')
            ->get()->getResultArray();
    }

    // Returns a map of role key to admin scope flag.
    public function getRolesScopeMap()
    {
        $rows = $this->db->table('roles')
            ->select('role_key, is_admin_scope')
            ->get()->getResultArray();
        $map = [];
        foreach ($rows as $r) {
            $isAdmin = false;
            $adminScopeVal = 0;
            if (isset($r['is_admin_scope'])) {
                $adminScopeVal = $r['is_admin_scope'];
            }
            if (((int) $adminScopeVal) === 1) {
                $isAdmin = true;
            }
            $map[(string) $r['role_key']] = $isAdmin;
        }
        return $map;
    }

    // Retrieves active users matching candidate list for mentions.
    public function parseUsersByMentions($candidates)
    {
        return $this->db->table('users')
            ->select('user_id, role')
            ->whereIn('user_id', $candidates)
            ->where('is_active', 1)
            ->where('deleted_at', null)
            ->get()->getResultArray();
    }

    // Retrieves custom notification settings for a user.
    public function getUserNotificationSettings($userId)
    {
        return $this->db->table('user_notification_settings')
            ->where('user_id', $userId)
            ->get()->getResultArray();
    }

    // Retrieves project ID and alert type from a ticket.
    public function getTicketProjectAndSeverity($ticketId)
    {
        return $this->db->table('tickets')
            ->select('project_id, alert_type')
            ->where('id', (int) $ticketId)
            ->get()->getRowArray();
    }

    // Queues email rows in notification_logs.
    public function insertNotificationLog($data)
    {
        return $this->db->table('notification_logs')->insert($data);
    }

    // Deletes notification logs older than 90 days.
    public function pruneNotificationLogs($cutoff)
    {
        return $this->db->table('notification_logs')
            ->where('created_at <', $cutoff)
            ->delete();
    }

    // Fetches pending notifications logs for delivery batch.
    public function getPendingNotifications($batch)
    {
        return $this->db->table('notification_logs')
            ->where('status', 'pending')
            ->orderBy('id', 'asc')
            ->limit((int) $batch)
            ->get()->getResultArray();
    }

    // Batch updates statuses of sent logs.
    public function updateNotificationStatusSent($ids, $sentAt)
    {
        return $this->db->table('notification_logs')
            ->set(['status' => 'sent', 'sent_at' => $sentAt, 'error_message' => null])
            ->whereIn('id', $ids)
            ->update();
    }

    // Updates individual notification log status.
    public function updateNotificationStatusSingle($id, $status, $errorMsg)
    {
        return $this->db->table('notification_logs')
            ->where('id', (int) $id)
            ->update(['status' => $status, 'error_message' => $errorMsg]);
    }

    // Updates error message for notification log retry attempt.
    public function updateNotificationErrorMsg($id, $errorMsg)
    {
        return $this->db->table('notification_logs')
            ->where('id', (int) $id)
            ->update(['error_message' => $errorMsg]);
    }

    // Increments and returns the last generated sequence for daily alarm ID.
    public function incrementAlarmSequence($today)
    {
        $this->db->query(
            "INSERT INTO alarm_id_sequence (day_key, last_seq) VALUES (?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE last_seq = LAST_INSERT_ID(last_seq + 1)",
            [$today]
        );
        $row = $this->db->query("SELECT LAST_INSERT_ID() AS n")->getRow();
        $num = 1;
        if (isset($row->n)) {
            $num = (int) $row->n;
        }
        return $num;
    }

    // Logs a user activity row to activity_logs.
    public function insertActivityLog($data)
    {
        return $this->db->table('activity_logs')->insert($data);
    }

    // Checks if a database table exists.
    public function tableExists($tableName)
    {
        return $this->db->tableExists($tableName);
    }

    // Checks tables using raw sql (fallback for migration check).
    public function checkTableExistsRaw($tableName)
    {
        return $this->db->query("SHOW TABLES LIKE '{$tableName}'")->getResultArray();
    }

    // Counts failed login attempts within the cutoff window.
    public function countFailedLoginAttempts($login, $cutoff)
    {
        return (int) $this->db->table('login_attempts')
            ->where('success', 0)
            ->where('attempted_at >=', $cutoff)
            ->where('login_identifier', $login)
            ->countAllResults();
    }

    // Retrieves the oldest failed login attempt within cutoff.
    public function getOldestFailedLoginAttempt($login, $cutoff)
    {
        return $this->db->table('login_attempts')
            ->where('success', 0)
            ->where('attempted_at >=', $cutoff)
            ->where('login_identifier', $login)
            ->orderBy('attempted_at', 'asc')
            ->limit(1)
            ->get()->getRowArray();
    }

    // Records a login attempt log row.
    public function insertLoginAttempt($data)
    {
        return $this->db->table('login_attempts')->insert($data);
    }

    // Prunes login attempts older than cutoff.
    public function pruneLoginAttempts($cutoff)
    {
        return $this->db->table('login_attempts')
            ->where('attempted_at <', $cutoff)
            ->delete();
    }

    // Clears failed login attempts for a user identifier.
    public function clearFailedLoginAttempts($login)
    {
        return $this->db->table('login_attempts')
            ->where('success', 0)
            ->where('login_identifier', $login)
            ->delete();
    }

    // Counts API requests log entries within cutoff.
    public function countApiRequests($apiKeyId, $cutoff)
    {
        return (int) $this->db->table('api_request_log')
            ->where('api_key_id', $apiKeyId)
            ->where('requested_at >=', $cutoff)
            ->countAllResults();
    }

    // Records an API request log entry.
    public function insertApiRequestLog($data)
    {
        return $this->db->table('api_request_log')->insert($data);
    }
}
