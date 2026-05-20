<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../services/response.php';
require_once __DIR__ . '/../../services/validators.php';
require_once __DIR__ . '/../../middleware/auth_staff.php';
require_once __DIR__ . '/../../middleware/require_role.php';
require_role("ADMIN");
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
$or_no        = clean_str($data["or_no"]        ?? "");
$paid_amount  = (float)($data["paid_amount"]    ?? 0);
$verified_by  = clean_str($data["verified_by"]  ?? "TREASURER");

if ($reference_no === "" || $paid_amount <= 0) {
  json_response(422, ["error" => "reference_no and paid_amount are required"]);
}

try {
  // Fetch id AND current status so we can log the real old_status
  $stmt = $pdo->prepare("SELECT id, status FROM requests WHERE reference_no = :ref LIMIT 1");
  $stmt->execute([":ref" => $reference_no]);
  $req = $stmt->fetch();

  if (!$req) {
    json_response(404, ["error" => "Request not found"]);
  }

  $request_id = (int)$req["id"];
  $old_status = $req["status"];

  $pdo->beginTransaction();

  $stmt2 = $pdo->prepare("
    INSERT INTO payments (request_id, or_no, paid_amount, verified_by, verified_at)
    VALUES (:rid, :or_no, :amt, :by, NOW())
  ");
  $stmt2->execute([
    ":rid"   => $request_id,
    ":or_no" => ($or_no !== "" ? $or_no : null),
    ":amt"   => $paid_amount,
    ":by"    => $verified_by,
  ]);

  $stmt3 = $pdo->prepare("
    UPDATE requests SET payment_status = 'Paid' WHERE id = :id
  ");
  $stmt3->execute([":id" => $request_id]);

  // Log with the real old_status; new_status stays the same because
  // verifying payment does not change the request's workflow status —
  // only its payment_status column changes. old = new signals a payment
  // event without a status transition, which is queryable in history.
  $stmt4 = $pdo->prepare("
    INSERT INTO status_history (request_id, old_status, new_status, changed_by, note)
    VALUES (:rid, :old, :old, :by, 'Payment verified')
  ");
  $stmt4->execute([
    ":rid" => $request_id,
    ":old" => $old_status,
    ":by"  => $_SESSION["staff"]["username"] ?? $verified_by,
  ]);

  $pdo->commit();

  json_response(200, [
    "message"        => "Payment verified",
    "reference_no"   => $reference_no,
    "old_status"     => $old_status,
    "payment_status" => "Paid",
  ]);

} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_response(500, ["error" => "Server error"]);
}
