<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Ticket
{
    private PDO $db;

    public function __construct()
    {
        require_once __DIR__ . '/../../config/database.php';
        $this->db = getDbConnection();
    }

    /**
     * Create a new ticket.
     */
    public function create(int $userId, int $routeId): array
    {
        $ticketCode  = $this->generateCode();
        $bookingTime = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare(
            'INSERT INTO citizen_tickets (user_id, route_id, ticket_code, booking_time, status)
             VALUES (:user_id, :route_id, :ticket_code, :booking_time, "active")'
        );
        $stmt->execute([
            ':user_id'     => $userId,
            ':route_id'    => $routeId,
            ':ticket_code' => $ticketCode,
            ':booking_time'=> $bookingTime,
        ]);

        return $this->findById((int) $this->db->lastInsertId());
    }

    /**
     * Get all tickets for a user.
     */
    public function findByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, r.route_name, r.start_point, r.end_point
             FROM citizen_tickets t
             LEFT JOIN traffic_routes r ON r.id = t.route_id
             WHERE t.user_id = :user_id
             ORDER BY t.booking_time DESC'
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Find a single ticket by id, checking ownership.
     */
    public function findById(int $id, ?int $userId = null): ?array
    {
        $sql = 'SELECT t.*, r.route_name, r.start_point, r.end_point
                FROM citizen_tickets t
                LEFT JOIN traffic_routes r ON r.id = t.route_id
                WHERE t.id = :id';
        $params = [':id' => $id];

        if ($userId !== null) {
            $sql .= ' AND t.user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /* Find ticket by ticket_code.*/
    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, r.route_name, r.start_point, r.end_point
             FROM citizen_tickets t
             LEFT JOIN traffic_routes r ON r.id = t.route_id
             WHERE t.ticket_code = :code'
        );
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Update ticket status.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE citizen_tickets SET status = :status WHERE id = :id'
        );
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }

    public function expireOldTickets(): int
    {
        $stmt = $this->db->prepare(
            'UPDATE citizen_tickets
             SET status = "expired"
             WHERE status = "active"
               AND booking_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }


    private function generateCode(): string
    {
        $year   = date('Y');
        $random = strtoupper(bin2hex(random_bytes(4)));
        return "TKT-{$year}-{$random}";
    }
}
