<?php
// C:\xampp\htdocs\eserbisyo-hub\api\public\staff\send_ready_sms.php

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
$message      = clean_str($data["message"] ?? "Your requested document is READY FOR RELEASE. Please proceed to the municipal office to claim.");
// Actor always from the authenticated session — never trusted from client body
$sent_by      = $_SESSION["staff"]["username"] ?? "STAFF";

if ($reference_no === "") {
  json_response(422, ["error" => "reference_no is required"]);
}

try {
  $stmt = $pdo->prepare("SELECT id, mobile_no, status FROM requests WHERE reference_no = :ref LIMIT 1");
  $stmt->execute([":ref" => $reference_no]);
  $req = $stmt->fetch();

  if (!$req) {
    json_response(404, ["error" => "Request not found"]);
  }

  $request_id = (int)$req["id"];
  $mobile_no  = $req["mobile_no"];
  $old_status = $req["status"];

  $pdo->beginTransaction();

  $pdo->prepare("
    INSERT INTO sms_logs (request_id, message_type, recipient_mobile, message, sent_by, status)
    VALUES (:rid, 'READY_NOTIFICATION', :mobile, :msg, :by, 'SENT')
  ")->execute([
    ":rid"    => $request_id,
    ":mobile" => $mobile_no,
    ":msg"    => $message,
    ":by"     => $sent_by,
  ]);

  if ($old_status !== "Ready for Release" && $old_status !== "Released") {
    $pdo->prepare("UPDATE requests SET status = 'Ready for Release', updated_at = NOW() WHERE id = :id")
        ->execute([":id" => $request_id]);

    $pdo->prepare("
      INSERT INTO status_history (request_id, old_status, new_status, changed_by, note)
      VALUES (:rid, :old, 'Ready for Release', :by, 'Readiness notification sent')
    ")->execute([
      ":rid" => $request_id,
      ":old" => $old_status,
      ":by"  => $sent_by,
    ]);
  }

  $pdo->commit();

  json_response(200, [
    "message"          => "Readiness notification logged",
    "reference_no"     => $reference_no,
    "recipient_mobile" => $mobile_no,
  ]);

} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_response(500, ["error" => "Server error"]);
}