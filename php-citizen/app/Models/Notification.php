<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Notification
{
    private PDO $db;

    public function __construct()
    {
        require_once __DIR__ . '/../../config/database.php';
        $this->db = getDbConnection();
    }

    public function findByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM citizen_notifications
             WHERE user_id = :user_id
             ORDER BY created_at DESC
             LIMIT 50'
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function markAsRead(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE citizen_notifications
             SET is_read = 1
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }
}
