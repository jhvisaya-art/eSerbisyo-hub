<?php
// C:\xampp\htdocs\eserbisyo-hub\api\public\staff\accounts_list.php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../services/response.php';
require_once __DIR__ . '/../../middleware/auth_staff.php';
require_once __DIR__ . '/../../middleware/require_role.php';
require_role("ADMIN");

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
  json_response(405, ["error" => "Method not allowed"]);
}

try {
  $stmt = $pdo->query("
    SELECT id, username, role, is_active, created_at
    FROM staff_users
    ORDER BY created_at DESC
  ");
  $rows = $stmt->fetchAll();

  json_response(200, ["data" => $rows]);

} catch (Exception $e) {
  json_response(500, ["error" => "Server error"]);
}