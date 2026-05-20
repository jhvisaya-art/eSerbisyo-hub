<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../services/response.php';
require_once __DIR__ . '/../../services/validators.php';

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
  json_response(405, ["error" => "Method not allowed"]);
}

$ref = clean_str($_GET["ref"] ?? "");
$last_name = clean_str($_GET["last_name"] ?? "");
$mobile_no = clean_str($_GET["mobile_no"] ?? "");

if ($ref === "" || ($last_name === "" && $mobile_no === "")) {
  json_response(422, ["error" => "Provide ref and (last_name or mobile_no)."]);
}

try {
  // Find the request using reference number plus optional last name / mobile filters
  $conditions = ["r.reference_no = :ref"];
  $params     = [":ref" => $ref];

  $orParts = [];
  if ($last_name !== "") {
    $orParts[] = "r.last_name = :last_name";
    $params[":last_name"] = $last_name;
  }
  if ($mobile_no !== "") {
    $orParts[] = "r.mobile_no = :mobile_no";
    $params[":mobile_no"] = $mobile_no;
  }
  $conditions[] = "(" . implode(" OR ", $orParts) . ")";

  $sql = "
    SELECT r.id, r.reference_no, r.service_code,
           r.last_name, r.first_name, r.middle_name,
           r.mobile_no, r.status, r.payment_status, r.created_at
    FROM requests r
    WHERE " . implode(" AND ", $conditions) . "
    LIMIT 1
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  $req = $stmt->fetch();
  if (!$req) {
    json_response(404, ["error" => "No matching request found."]);
  }

  // Get status history (latest first)
  $stmt2 = $pdo->prepare("
    SELECT new_status, changed_by, note, changed_at
    FROM status_history
    WHERE request_id = :rid
    ORDER BY changed_at DESC
    LIMIT 20
  ");
  $stmt2->execute([":rid" => $req["id"]]);
  $history = $stmt2->fetchAll();

  json_response(200, [
    "reference_no" => $req["reference_no"],
    "service_code" => $req["service_code"],
    "name" => trim($req["first_name"]." ".$req["middle_name"]." ".$req["last_name"]),
    "mobile_no" => $req["mobile_no"],
    "status" => $req["status"],
    "payment_status" => $req["payment_status"],
    "created_at" => $req["created_at"],
    "history" => $history
  ]);

} catch (Exception $e) {
  json_response(500, ["error" => "Server error"]);
}
