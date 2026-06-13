<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Ticket;
use App\Services\RabbitMQPublisher;
use App\Validators\TicketValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TicketController extends BaseController
{
    private Ticket            $ticket;
    private RabbitMQPublisher $publisher;

    public function __construct()
    {
        $this->ticket    = new Ticket();
        $this->publisher = new RabbitMQPublisher();
    }

    // POST /api/tickets
    public function store(Request $request, Response $response, array $args): Response
    {
        $userId     = (int) $request->getAttribute('user_id');
        $body       = $this->getBody($request);
        $validation = TicketValidator::validateStore($body);

        if (!$validation['valid']) {
            return $this->respondError($response, 422, implode(', ', $validation['errors']));
        }

        $record = $this->ticket->create($userId, $validation['data']['route_id']);

        $this->publisher->publish('ticket.created', [
            'ticket_id'   => $record['id'],
            'ticket_code' => $record['ticket_code'],
            'user_id'     => $userId,
            'route_id'    => $record['route_id'],
        ]);

        return $this->respond($response, [
            'ticket_id'    => $record['id'],
            'ticket_code'  => $record['ticket_code'],
            'route_id'     => $record['route_id'],
            'status'       => $record['status'],
            'booking_time' => $record['booking_time'],
        ], 201, 'Ticket booked successfully');
    }

    // GET /api/tickets
    public function index(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $request->getAttribute('user_id');

        $this->ticket->expireOldTickets();
        $tickets = $this->ticket->findByUser($userId);

        return $this->respond($response, $tickets, 200, 'Tickets retrieved');
    }

    // GET /api/tickets/{id}
    public function show(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $id     = (int) ($args['id'] ?? 0);
        $record = $this->ticket->findById($id, $userId);

        if (!$record) {
            return $this->respondError($response, 404, 'Ticket not found');
        }

        return $this->respond($response, $record);
    }

    // POST /api/tickets/{code}/scan
    public function scan(Request $request, Response $response, array $args): Response
    {
        $code   = $args['code'] ?? '';
        $record = $this->ticket->findByCode($code);

        if (!$record) {
            return $this->respondError($response, 404, 'Ticket not found');
        }

        if ($record['status'] === 'used') {
            return $this->respondError($response, 409, 'Ticket has already been used');
        }

        if ($record['status'] === 'expired') {
            return $this->respondError($response, 410, 'Ticket has expired');
        }

        $this->ticket->updateStatus((int) $record['id'], 'used');

        return $this->respond($response, [
            'ticket_id'   => $record['id'],
            'ticket_code' => $record['ticket_code'],
            'status'      => 'used',
            'route_name'  => $record['route_name'] ?? null,
        ], 200, 'Ticket scanned successfully');
    }
}
