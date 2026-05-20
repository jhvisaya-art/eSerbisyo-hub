<?php
declare(strict_types=1);

// Use the "secure" cookie flag only when we're actually on HTTPS.
// On http://localhost the cookie would otherwise be silently dropped by the browser,
// so the session would never persist between login and protected endpoints.
$isHttps = (
  (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (($_SERVER['SERVER_PORT'] ?? null) == 443)
  || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null) === 'https') // Render/Heroku/etc.
);

session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'httponly' => true,
  'samesite' => 'Lax',
  'secure'   => $isHttps,
]);
session_start();

if (!isset($_SESSION["staff"])) {
  http_response_code(401);
  header("Content-Type: application/json");
  echo json_encode(["error" => "Unauthorized"]);
  exit;
}
