<?php
// C:\xampp\htdocs\eserbisyo-hub\api\public\staff\accounts_register.php

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

$username = clean_str($data["username"] ?? "");
$password = (string)($data["password"] ?? "");
$role     = strtoupper(clean_str($data["role"] ?? "STAFF"));

if (strlen($username) < 3) {
  json_response(400, ["error" => "Username must be at least 3 characters."]);
}
if (strlen($password) < 8) {
  json_response(400, ["error" => "Password must be at least 8 characters."]);
}
if (!in_array($role, ["STAFF", "ADMIN"], true)) {
  json_response(400, ["error" => "Role must be STAFF or ADMIN."]);
}

$username = strtolower($username);

try {
  // Check for duplicate username
  $check = $pdo->prepare("SELECT id FROM staff_users WHERE username = :u LIMIT 1");
  $check->execute([":u" => $username]);
  if ($check->fetch()) {
    json_response(409, ["error" => "Username \"{$username}\" is already taken."]);
  }

  $hash = password_hash($password, PASSWORD_BCRYPT, ["cost" => 12]);

  $stmt = $pdo->prepare("
    INSERT INTO staff_users (username, password_hash, role, is_active)
    VALUES (:u, :h, :r, 1)
  ");
  $stmt->execute([":u" => $username, ":h" => $hash, ":r" => $role]);

  json_response(201, [
    "message"  => "Account created successfully.",
    "id"       => (int)$pdo->lastInsertId(),
    "username" => $username,
    "role"     => $role,
  ]);

} catch (Exception $e) {
  json_response(500, ["error" => "Server error"]);
}