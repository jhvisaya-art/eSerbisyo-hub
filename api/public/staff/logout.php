<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../services/response.php';
require_once __DIR__ . '/../../middleware/auth_staff.php';
require_once __DIR__ . '/../../middleware/csrf.php';

csrf_verify();

$_SESSION = [];
session_destroy();

if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(
    session_name(),
    '',
    time() - 42000,
    $params["path"],
    $params["domain"],
    $params["secure"] ?? false,
    $params["httponly"] ?? true
  );
}

json_response(200, ["ok" => true, "message" => "Logged out"]);
