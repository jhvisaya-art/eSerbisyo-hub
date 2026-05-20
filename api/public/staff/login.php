<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../services/response.php';
require_once __DIR__ . '/../../services/validators.php';

const MAX_ATTEMPTS = 5;
const LOCKOUT_MINS = 10;

header("Content-Type: application/json");

$isHttps = (
  (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (($_SERVER['SERVER_PORT'] ?? null) == 443)
  || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null) === 'https')
);
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'httponly' => true,
  'samesite' => 'Lax',
  'secure'   => $isHttps,
]);
session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  json_response(405, ["error" => "Method not allowed"]);
}

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) json_response(400, ["error" => "Invalid JSON body"]);

$username = clean_str($data["username"] ?? "");
$password = (string)($data["password"] ?? "");

if ($username === "" || $password === "") {
  json_response(422, ["error" => "username and password required"]);
}

$stmtChk = $pdo->prepare("
  SELECT COUNT(*) FROM login_attempts
  WHERE username = :u
    AND attempted_at >= (NOW() - make_interval(mins => :mins))
");
$stmtChk->execute([":u" => $username, ":mins" => LOCKOUT_MINS]);
$failCount = (int)$stmtChk->fetchColumn();

if ($failCount >= MAX_ATTEMPTS) {
  json_response(429, [
    "error" => "Too many failed attempts. Try again in " . LOCKOUT_MINS . " minutes."
  ]);
}

$stmt = $pdo->prepare("
  SELECT id, username, password_hash, role, is_active
  FROM staff_users
  WHERE username = :u
  LIMIT 1
");
$stmt->execute([":u" => $username]);
$user = $stmt->fetch();

if (!$user || (int)$user["is_active"] !== 1) {
  $pdo->prepare("INSERT INTO login_attempts (username) VALUES (:u)")
      ->execute([":u" => $username]);
  json_response(401, ["error" => "Invalid login"]);
}

if (!password_verify($password, $user["password_hash"])) {
  $pdo->prepare("INSERT INTO login_attempts (username) VALUES (:u)")
      ->execute([":u" => $username]);
  json_response(401, ["error" => "Invalid login"]);
}

$pdo->prepare("DELETE FROM login_attempts WHERE username = :u")
    ->execute([":u" => $username]);

$_SESSION["staff"] = [
  "id" => (int)$user["id"],
  "username" => $user["username"],
  "role" => $user["role"]
];

session_write_close();

json_response(200, ["ok" => true, "user" => ["username" => $user["username"], "role" => $user["role"]]]);