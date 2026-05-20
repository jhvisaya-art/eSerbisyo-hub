<?php
// C:\xampp\htdocs\eserbisyo-hub\api\public\staff\get_request.php

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

$ref = clean_str($_GET["ref"] ?? "");
if ($ref === "") {
  json_response(422, ["error" => "ref is required"]);
}

try {
  // is_archived added so the dashboard modal can show/hide the archive button correctly
  $stmt = $pdo->prepare("
    SELECT id, reference_no, service_code,
           last_name, first_name, middle_name,
           address_line, mobile_no,
           status, payment_status, is_archived, created_at
    FROM requests
    WHERE reference_no = :ref
    LIMIT 1
  ");
  $stmt->execute([":ref" => $ref]);
  $req = $stmt->fetch();

  if (!$req) {
    json_response(404, ["error" => "Request not found"]);
  }

  $rid = (int)$req["id"];

  $stmt2 = $pdo->prepare("
    SELECT new_status, changed_by, note, changed_at
    FROM status_history
    WHERE request_id = :rid
    ORDER BY changed_at DESC
    LIMIT 50
  ");
  $stmt2->execute([":rid" => $rid]);
  $history = $stmt2->fetchAll();

  $stmt3 = $pdo->prepare("
    SELECT message_type, recipient_mobile, message, sent_by, sent_at, status
    FROM sms_logs
    WHERE request_id = :rid
    ORDER BY sent_at DESC
    LIMIT 20
  ");
  $stmt3->execute([":rid" => $rid]);
  $sms = $stmt3->fetchAll();

  $stmt4 = $pdo->prepare("
    SELECT or_no, paid_amount, verified_by, verified_at
    FROM payments
    WHERE request_id = :rid
    ORDER BY verified_at DESC
    LIMIT 1
  ");
  $stmt4->execute([":rid" => $rid]);
  $latest_payment = $stmt4->fetch() ?: null;

  json_response(200, [
    "request"        => $req,
    "history"        => $history,
    "sms_logs"       => $sms,
    "latest_payment" => $latest_payment,
  ]);

} catch (Exception $e) {
  json_response(500, ["error" => "Server error"]);
}