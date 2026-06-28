<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Report;
use App\Services\RabbitMQPublisher;
use App\Validators\ReportValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ReportController extends BaseController
{
    private Report            $report;
    private RabbitMQPublisher $publisher;

    public function __construct()
    {
        $this->report    = new Report();
        $this->publisher = new RabbitMQPublisher();
    }

    // POST /api/reports
    public function store(Request $request, Response $response, array $args): Response
    {
        $userId     = (int) $request->getAttribute('user_id');
        $body       = $this->getBody($request);
        $validation = ReportValidator::validateStore($body);

        if (!$validation['valid']) {
            return $this->respondError($response, 422, implode(', ', $validation['errors']));
        }

        $record = $this->report->create($userId, $validation['data']);

        $this->publisher->publish('report.submitted', [
            'report_id' => $record['id'],
            'user_id'   => $userId,
            'category'  => $record['category'],
            'stop_id'   => $record['stop_id'],
        ]);

        return $this->respond($response, [
            'report_id'   => $record['id'],
            'category'    => $record['category'],
            'description' => $record['description'],
            'status'      => $record['status'],
            'created_at'  => $record['created_at'],
        ], 201, 'Report submitted successfully');
    }

    // GET /api/reports
    public function index(Request $request, Response $response, array $args): Response
    {
        $userId  = (int) $request->getAttribute('user_id');
        $params  = $request->getQueryParams();
        $status  = $params['status'] ?? null;
        $records = $this->report->findByUser($userId, $status);

        return $this->respond($response, $records);
    }

    // GET /api/reports/{id}
    public function show(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $id     = (int) ($args['id'] ?? 0);
        $record = $this->report->findById($id, $userId);

        if (!$record) {
            return $this->respondError($response, 404, 'Report not found');
        }

        return $this->respond($response, $record);
    }
}