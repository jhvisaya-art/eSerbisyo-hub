<?php
declare(strict_types=1);

function json_response(int $status, array $body): void {
  http_response_code($status);
  header("Content-Type: application/json");
  echo json_encode($body);
  exit;
}
