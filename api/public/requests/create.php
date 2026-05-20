<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../services/response.php';
require_once __DIR__ . '/../../services/reference_no.php';
require_once __DIR__ . '/../../services/validators.php';

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  json_response(405, ["error" => "Method not allowed"]);
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data)) {
  json_response(400, ["error" => "Invalid JSON body"]);
}

$service_code   = clean_str($data["service_code"] ?? "");
$last_name      = clean_str($data["last_name"] ?? "");
$first_name     = clean_str($data["first_name"] ?? "");
$middle_name    = clean_str($data["middle_name"] ?? "");
$address_line   = clean_str($data["address_line"] ?? "");
$mobile_no      = clean_str($data["mobile_no"] ?? "");
$consent_privacy = (int)($data["consent_privacy"] ?? 0);

$errors = [];
if ($service_code === "") $errors[] = "Service is required.";
if ($last_name === "") $errors[] = "Last name is required.";
if ($first_name === "") $errors[] = "First name is required.";
if ($address_line === "") $errors[] = "Address is required.";
if ($mobile_no === "") $errors[] = "Mobile number is required.";
if ($mobile_no !== "" && !preg_match('/^09\d{9}$/', $mobile_no)) {
  $errors[] = "Mobile number must be in 09XXXXXXXXX format (11 digits, starts with 09).";
}
if ($consent_privacy !== 1) $errors[] = "Privacy consent is required.";

if (!empty($errors)) {
  json_response(422, ["errors" => $errors]);
}

try {
  // Generate unique reference number (loop until unique)
  $reference_no = generate_reference_no($pdo);

  // Insert request — use RETURNING id so we don't depend on lastInsertId()
  // (PostgreSQL's PDO::lastInsertId() needs a sequence name; RETURNING is cleaner.)
  $stmt = $pdo->prepare("
    INSERT INTO requests
      (reference_no, service_code, last_name, first_name, middle_name, address_line, mobile_no, consent_privacy)
    VALUES
      (:reference_no, :service_code, :last_name, :first_name, :middle_name, :address_line, :mobile_no, :consent_privacy)
    RETURNING id
  ");
  $stmt->execute([
    ":reference_no" => $reference_no,
    ":service_code" => $service_code,
    ":last_name" => $last_name,
    ":first_name" => $first_name,
    ":middle_name" => ($middle_name !== "" ? $middle_name : null),
    ":address_line" => $address_line,
    ":mobile_no" => $mobile_no,
    ":consent_privacy" => $consent_privacy
  ]);

  $request_id = (int)$stmt->fetchColumn();

  // Create a payment slip record
  $stmt2 = $pdo->prepare("INSERT INTO payment_slips (request_id, amount, printed_at) VALUES (:rid, :amt, NULL)");
  $stmt2->execute([":rid" => $request_id, ":amt" => 0.00]);

  // Log initial status history
  $stmt3 = $pdo->prepare("
    INSERT INTO status_history (request_id, old_status, new_status, changed_by, note)
    VALUES (:rid, NULL, 'Queued', 'KIOSK', 'Initial submission')
  ");
  $stmt3->execute([":rid" => $request_id]);

  json_response(201, [
    "message" => "Request created",
    "reference_no" => $reference_no,
    "request" => [
      "service_code" => $service_code,
      "name" => trim($first_name . " " . $middle_name . " " . $last_name),
      "mobile_no" => $mobile_no,
      "status" => "Queued"
    ],
    "slip" => [
      "reference_no" => $reference_no,
      "instructions" => "Proceed to the Municipal Treasurer's Office with this slip to pay."
    ]
  ]);

} catch (Exception $e) {
  json_response(500, ["error" => "Server error"]);
}
