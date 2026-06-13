<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Notification;

class NotifController extends BaseController
{
    private Notification $notif;

    public function __construct()
    {
        $this->notif = new Notification();
    }

    // GET /api/notifications
    public function index(array $params, int $userId): void
    {
        $records = $this->notif->findByUser($userId);
        $this->respond($records);
    }

    // PATCH /api/notifications/{id}/read
    public function markRead(array $params, int $userId): void
    {
        $id      = (int) ($params['id'] ?? 0);
        $success = $this->notif->markAsRead($id, $userId);

        if (!$success) {
            $this->respondError(404, 'Notification not found');
        }

        $this->respond(['id' => $id, 'is_read' => true], 200, 'Marked as read');
    }
}
