<?php
// C:\xampp\htdocs\eserbisyo-hub\api\public\staff\list_requests.php
// Strategy 1 — Pagination  : limit + offset params; response includes { data, total }
// Strategy 2 — Archive     : hides is_archived = 1 by default; show_archived=1 reveals them
// Strategy 3 — Date filter : date_from / date_to filter on DATE(created_at)

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../services/response.php';
require_once __DIR__ . '/../../services/validators.php';
require_once __DIR__ . '/../../middleware/auth_staff.php';

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
  json_response(405, ["error" => "Method not allowed"]);
}

// Strategy 1 — Pagination
$limit  = max(1, min((int)($_GET["limit"]  ?? 20), 100));
$offset = max(0, (int)($_GET["offset"] ?? 0));

// Strategy 2 — Archive filter (hide archived by default)
$showArchived = ($_GET["show_archived"] ?? "0") === "1";

// Strategy 3 — Date filter
$dateFrom = clean_str($_GET["date_from"] ?? "");
$dateTo   = clean_str($_GET["date_to"]   ?? "");

$status = clean_str($_GET["status"] ?? "");
$search = clean_str($_GET["search"] ?? "");

try {
  $where  = [];
  $params = [];

  // Strategy 2 — exclude archived unless explicitly requested
  if (!$showArchived) {
    $where[] = "r.is_archived = 0";
  }

  if ($status !== "") {
    $where[] = "r.status = :status";
    $params[":status"] = $status;
  }

  if ($search !== "") {
    $where[] = "(r.reference_no LIKE :q OR r.last_name LIKE :q OR r.first_name LIKE :q OR r.mobile_no LIKE :q)";
    $params[":q"] = "%" . $search . "%";
  }

  // Strategy 3 — date range (inclusive, date-only comparison)
  if ($dateFrom !== "") {
    $where[] = "DATE(r.created_at) >= :date_from";
    $params[":date_from"] = $dateFrom;
  }
  if ($dateTo !== "") {
    $where[] = "DATE(r.created_at) <= :date_to";
    $params[":date_to"] = $dateTo;
  }

  $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

  // Count total matching rows for pagination
  $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM requests r $whereSql");
  $stmtCount->execute($params);
  $total = (int)$stmtCount->fetchColumn();

  // $limit and $offset are cast to int and clamped — safe to interpolate.
  // PDO bound params are not supported for LIMIT/OFFSET in some MySQL drivers.
  $sql = "
    SELECT r.reference_no, r.service_code,
           r.last_name, r.first_name, r.middle_name,
           r.mobile_no, r.status, r.payment_status,
           r.is_archived, r.created_at
    FROM requests r
    $whereSql
    ORDER BY r.created_at DESC
    LIMIT $limit OFFSET $offset
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll();

  json_response(200, ["data" => $rows, "total" => $total]);

} catch (Exception $e) {
  json_response(500, ["error" => "Server error"]);
}