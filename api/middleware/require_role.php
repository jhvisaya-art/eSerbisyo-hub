<?php
declare(strict_types=1);

function require_role(string $role): void {
  if (!isset($_SESSION["staff"])) {
    http_response_code(401);
    header("Content-Type: application/json");
    echo json_encode(["error" => "Unauthorized"]);
    exit;
  }

  $current = $_SESSION["staff"]["role"] ?? null;
  if ($current === null || $current !== $role) {
    http_response_code(403);
    header("Content-Type: application/json");
    echo json_encode(["error" => "Forbidden"]);
    exit;
  }
}
