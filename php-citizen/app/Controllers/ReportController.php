<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Report;
use App\Services\RabbitMQPublisher;
use App\Validators\ReportValidator;

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
    public function store(array $params, int $userId): void
    {
        $body       = $this->getBody();
        $validation = ReportValidator::validateStore($body);

        if (!$validation['valid']) {
            $this->respondError(422, implode(', ', $validation['errors']));
        }

        $record = $this->report->create($userId, $validation['data']);

        // Publish event for notification worker
        $this->publisher->publish('report.submitted', [
            'report_id'   => $record['id'],
            'user_id'     => $userId,
            'category'    => $record['category'],
            'stop_id'     => $record['stop_id'],
        ]);

        $this->respond([
            'report_id'   => $record['id'],
            'category'    => $record['category'],
            'description' => $record['description'],
            'status'      => $record['status'],
            'created_at'  => $record['created_at'],
        ], 201, 'Report submitted successfully');
    }

    // GET /api/reports
    public function index(array $params, int $userId): void
    {
        $status  = $_GET['status'] ?? null;
        $records = $this->report->findByUser($userId, $status);
        $this->respond($records);
    }

    // GET /api/reports/{id}
    public function show(array $params, int $userId): void
    {
        $id     = (int) ($params['id'] ?? 0);
        $record = $this->report->findById($id, $userId);

        if (!$record) {
            $this->respondError(404, 'Report not found');
        }

        $this->respond($record);
    }
}
