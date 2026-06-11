<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Report
{
    private PDO $db;

    public function __construct()
    {
        require_once __DIR__ . '/../../config/database.php';
        $this->db = getDbConnection();
    }

    public function create(int $userId, array $data): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO citizen_reports (user_id, category, description, stop_id, status)
             VALUES (:user_id, :category, :description, :stop_id, "pending")'
        );
        $stmt->execute([
            ':user_id'     => $userId,
            ':category'    => $data['category'],
            ':description' => $data['description'],
            ':stop_id'     => $data['stop_id'] ?? null,
        ]);

        return $this->findById((int) $this->db->lastInsertId());
    }

    public function findByUser(int $userId, ?string $status = null): array
    {
        $sql    = 'SELECT * FROM citizen_reports WHERE user_id = :user_id';
        $params = [':user_id' => $userId];

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }

        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id, ?int $userId = null): ?array
    {
        $sql    = 'SELECT * FROM citizen_reports WHERE id = :id';
        $params = [':id' => $id];

        if ($userId !== null) {
            $sql .= ' AND user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
