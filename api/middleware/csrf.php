<?php
declare(strict_types=1);

// Generate a token and store it in the session (call once per session)
function csrf_token(): string {
  if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
  }
  return $_SESSION["csrf_token"];
}

// Validate the token sent in the request header
function csrf_verify(): void {
  $token    = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
  $expected = $_SESSION["csrf_token"]       ?? "";

  if ($expected === "" || !hash_equals($expected, $token)) {
    http_response_code(403);
    header("Content-Type: application/json");
    echo json_encode(["error" => "Invalid or missing CSRF token"]);
    exit;
  }
}
