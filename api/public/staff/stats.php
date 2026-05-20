<?php
// C:\xampp\htdocs\eserbisyo-hub\api\public\staff\stats.php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../services/response.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../middleware/auth_staff.php';

header("Content-Type: application/json");

try {
  // Exclude archived records so the KPI cards only reflect active work
  $stmt = $pdo->query("
    SELECT status, COUNT(*) AS total
    FROM requests
    WHERE is_archived = 0
    GROUP BY status
  ");
  $rows = $stmt->fetchAll();

  json_response(200, ["data" => $rows]);
} catch (Exception $e) {
  json_response(500, ["error" => "Server error"]);
}