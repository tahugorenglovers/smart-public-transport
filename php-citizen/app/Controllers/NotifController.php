<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Notification;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class NotifController extends BaseController
{
    private Notification $notif;

    public function __construct()
    {
        $this->notif = new Notification();
    }

    // GET /api/notifications
    public function index(Request $request, Response $response, array $args): Response
    {
        $userId  = (int) $request->getAttribute('user_id');
        $records = $this->notif->findByUser($userId);

        return $this->respond($response, $records);
    }

    // PATCH /api/notifications/{id}/read
    public function markRead(Request $request, Response $response, array $args): Response
    {
        $userId  = (int) $request->getAttribute('user_id');
        $id      = (int) ($args['id'] ?? 0);
        $success = $this->notif->markAsRead($id, $userId);

        if (!$success) {
            return $this->respondError($response, 404, 'Notification not found');
        }

        return $this->respond($response, ['id' => $id, 'is_read' => true], 200, 'Marked as read');
    }
}