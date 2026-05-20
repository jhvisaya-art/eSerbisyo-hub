<?php
// api/config/db.php — PostgreSQL connection
// Supports two configurations:
//   1) Cloud hosts (Render, Railway, Heroku, etc.) provide a single DATABASE_URL.
//   2) Local development uses DB_HOST / DB_NAME / DB_USER / DB_PASS / DB_PORT.

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

// Load .env when it exists (won't crash on cloud hosts that inject env vars directly).
if (file_exists(__DIR__ . '/../../.env')) {
  $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
  $dotenv->load();
}

// ---------------------------------------------------------------------
// Build DSN
// ---------------------------------------------------------------------
$DATABASE_URL = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: null;

if ($DATABASE_URL) {
  // Cloud-style URL: postgres://user:pass@host:port/dbname[?sslmode=require]
  $parts = parse_url($DATABASE_URL);
  if (!$parts || !isset($parts['host'], $parts['user'], $parts['path'])) {
    http_response_code(500);
    header("Content-Type: application/json");
    echo json_encode(["error" => "Invalid DATABASE_URL"]);
    exit;
  }
  $DB_HOST = $parts['host'];
  $DB_PORT = $parts['port'] ?? 5432;
  $DB_NAME = ltrim($parts['path'], '/');
  $DB_USER = urldecode($parts['user']);
  $DB_PASS = isset($parts['pass']) ? urldecode($parts['pass']) : '';

  // Render/Heroku require SSL. Default to require unless explicitly disabled.
  $sslmode = 'require';
  if (!empty($parts['query'])) {
    parse_str($parts['query'], $q);
    if (!empty($q['sslmode'])) $sslmode = $q['sslmode'];
  }
  $dsn = "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};sslmode={$sslmode}";
} else {
  // Local development
  $DB_HOST = $_ENV['DB_HOST'] ?? 'localhost';
  $DB_PORT = $_ENV['DB_PORT'] ?? '5432';
  $DB_NAME = $_ENV['DB_NAME'] ?? '';
  $DB_USER = $_ENV['DB_USER'] ?? '';
  $DB_PASS = $_ENV['DB_PASS'] ?? '';

  if ($DB_NAME === '' || $DB_USER === '') {
    http_response_code(500);
    header("Content-Type: application/json");
    echo json_encode(["error" => "Database environment not configured"]);
    exit;
  }
  $dsn = "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME}";
}

$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
  // Match the API's expected timezone behavior (UTC).
  $pdo->exec("SET TIME ZONE 'UTC'");
} catch (PDOException $e) {
  http_response_code(500);
  header("Content-Type: application/json");
  // In production you may want to log $e->getMessage() to a file instead of echoing.
  echo json_encode(["error" => "Database connection failed"]);
  exit;
}
