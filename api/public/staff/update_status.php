<?php
// C:\xampp\htdocs\eserbisyo-hub\api\public\staff\update_status.php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../services/response.php';
require_once __DIR__ . '/../../services/validators.php';
require_once __DIR__ . '/../../middleware/auth_staff.php';
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

$reference_no = clean_str($data["reference_no"] ?? "");
$new_status   = clean_str($data["new_status"]   ?? "");
// Actor always from the authenticated session — never trusted from client body
$changed_by   = $_SESSION["staff"]["username"] ?? "STAFF";
$note         = clean_str($data["note"]         ?? "");

$allowed = ["Queued","Under Review","For Payment Verification","In Process","Ready for Release","Released"];

if ($reference_no === "" || $new_status === "") {
  json_response(422, ["error" => "reference_no and new_status are required"]);
}
if (!in_array($new_status, $allowed, true)) {
  json_response(422, ["error" => "Invalid status value"]);
}

try {
  $stmt = $pdo->prepare("SELECT id, status FROM requests WHERE reference_no = :ref LIMIT 1");
  $stmt->execute([":ref" => $reference_no]);
  $req = $stmt->fetch();

  if (!$req) {
    json_response(404, ["error" => "Request not found"]);
  }

  $old_status = $req["status"];
  $request_id = (int)$req["id"];

  $pdo->beginTransaction();

  $pdo->prepare("UPDATE requests SET status = :st, updated_at = NOW() WHERE id = :id")
      ->execute([":st" => $new_status, ":id" => $request_id]);

  $pdo->prepare("
    INSERT INTO status_history (request_id, old_status, new_status, changed_by, note)
    VALUES (:rid, :old, :new, :by, :note)
  ")->execute([
    ":rid"  => $request_id,
    ":old"  => $old_status,
    ":new"  => $new_status,
    ":by"   => $changed_by,
    ":note" => ($note !== "" ? $note : null),
  ]);

  $pdo->commit();

  json_response(200, [
    "message"      => "Status updated",
    "reference_no" => $reference_no,
    "old_status"   => $old_status,
    "new_status"   => $new_status,
  ]);

} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_response(500, ["error" => "Server error"]);
}