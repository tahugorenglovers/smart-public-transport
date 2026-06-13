<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Ticket;
use App\Services\RabbitMQPublisher;
use App\Validators\TicketValidator;

class TicketController extends BaseController
{
    private Ticket             $ticket;
    private RabbitMQPublisher  $publisher;

    public function __construct()
    {
        $this->ticket    = new Ticket();
        $this->publisher = new RabbitMQPublisher();
    }

    // POST /api/tickets
    public function store(array $params, int $userId): void
    {
        $body       = $this->getBody();
        $validation = TicketValidator::validateStore($body);

        if (!$validation['valid']) {
            $this->respondError(422, implode(', ', $validation['errors']));
        }

        $record = $this->ticket->create($userId, $validation['data']['route_id']);

        $this->publisher->publish('ticket.created', [
            'ticket_id'   => $record['id'],
            'ticket_code' => $record['ticket_code'],
            'user_id'     => $userId,
            'route_id'    => $record['route_id'],
        ]);

        $this->respond([
            'ticket_id'   => $record['id'],
            'ticket_code' => $record['ticket_code'],
            'route_id'    => $record['route_id'],
            'status'      => $record['status'],
            'booking_time'=> $record['booking_time'],
        ], 201, 'Ticket booked successfully');
    }

    // GET /api/tickets
    public function index(array $params, int $userId): void
    {
        $this->ticket->expireOldTickets();

        $tickets = $this->ticket->findByUser($userId);
        $this->respond($tickets, 200, 'Tickets retrieved');
    }

    // GET /api/tickets/{id}
    public function show(array $params, int $userId): void
    {
        $id     = (int) ($params['id'] ?? 0);
        $record = $this->ticket->findById($id, $userId);

        if (!$record) {
            $this->respondError(404, 'Ticket not found');
        }

        $this->respond($record);
    }

    // POST /api/tickets/{code}/scan
    // Simulates QR scan
    public function scan(array $params, int $userId): void
    {
        $code   = $params['code'] ?? '';
        $record = $this->ticket->findByCode($code);

        if (!$record) {
            $this->respondError(404, 'Ticket not found');
        }

        if ($record['status'] === 'used') {
            $this->respondError(409, 'Ticket has already been used');
        }

        if ($record['status'] === 'expired') {
            $this->respondError(410, 'Ticket has expired');
        }

        $this->ticket->updateStatus((int) $record['id'], 'used');

        $this->respond([
            'ticket_id'   => $record['id'],
            'ticket_code' => $record['ticket_code'],
            'status'      => 'used',
            'route_name'  => $record['route_name'] ?? null,
        ], 200, 'Ticket scanned successfully');
    }
}
