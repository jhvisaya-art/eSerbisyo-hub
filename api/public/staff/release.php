<?php
// C:\xampp\htdocs\eserbisyo-hub\api\public\staff\release.php

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
$note         = clean_str($data["note"]         ?? "Document claimed/released.");
// Actor always from the authenticated session — never trusted from client body
$released_by  = $_SESSION["staff"]["username"] ?? "ADMIN";

if ($reference_no === "") {
  json_response(422, ["error" => "reference_no is required"]);
}

try {
  $stmt = $pdo->prepare("SELECT id, status FROM requests WHERE reference_no = :ref LIMIT 1");
  $stmt->execute([":ref" => $reference_no]);
  $req = $stmt->fetch();

  if (!$req) {
    json_response(404, ["error" => "Request not found"]);
  }

  $request_id = (int)$req["id"];
  $old_status = $req["status"];

  $pdo->beginTransaction();

  $pdo->prepare("UPDATE requests SET status = 'Released', updated_at = NOW() WHERE id = :id")
      ->execute([":id" => $request_id]);

  $pdo->prepare("
    INSERT INTO status_history (request_id, old_status, new_status, changed_by, note)
    VALUES (:rid, :old, 'Released', :by, :note)
  ")->execute([
    ":rid"  => $request_id,
    ":old"  => $old_status,
    ":by"   => $released_by,
    ":note" => $note,
  ]);

  $pdo->commit();

  json_response(200, [
    "message"      => "Request marked as Released",
    "reference_no" => $reference_no,
    "old_status"   => $old_status,
    "new_status"   => "Released",
  ]);

} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_response(500, ["error" => "Server error"]);
}