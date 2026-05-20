<?php
// C:\xampp\htdocs\eserbisyo-hub\api\public\staff\accounts_reset_password.php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../services/response.php';
require_once __DIR__ . '/../../services/validators.php';
require_once __DIR__ . '/../../middleware/auth_staff.php';
require_once __DIR__ . '/../../middleware/require_role.php';
require_role("ADMIN");
require_once __DIR__ . '/../../middleware/csrf.php';
csrf_verify();

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  json_response(405, ["error" => "Method not allowed"]);
}

$raw  = file_get_contents("php://input");
$data = json_decode($raw, true);
if (!is_array($data)) {
  json_response(400, ["error" => "Invalid JSON body"]);
}

$id           = (int)($data["id"]           ?? 0);
$new_password = (string)($data["new_password"] ?? "");

if ($id <= 0) {
  json_response(400, ["error" => "Invalid account ID."]);
}
if (strlen($new_password) < 8) {
  json_response(400, ["error" => "New password must be at least 8 characters."]);
}

try {
  $stmt = $pdo->prepare("SELECT id, username FROM staff_users WHERE id = :id LIMIT 1");
  $stmt->execute([":id" => $id]);
  $account = $stmt->fetch();

  if (!$account) {
    json_response(404, ["error" => "Account not found."]);
  }

  $hash = password_hash($new_password, PASSWORD_BCRYPT, ["cost" => 12]);
  $pdo->prepare("UPDATE staff_users SET password_hash = :h WHERE id = :id")
      ->execute([":h" => $hash, ":id" => $id]);

  json_response(200, ["message" => "Password reset successfully."]);

} catch (Exception $e) {
  json_response(500, ["error" => "Server error"]);
}