<?php
// C:\xampp\htdocs\eserbisyo-hub\api\public\staff\archive_toggle.php
// Strategy 2 — Soft archive: flip is_archived on a Released record.
// ADMIN only. Non-Released records are rejected to prevent accidental hiding.

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
if ($reference_no === "") {
  json_response(422, ["error" => "reference_no is required"]);
}

try {
  $stmt = $pdo->prepare(
    "SELECT id, status, is_archived FROM requests WHERE reference_no = :ref LIMIT 1"
  );
  $stmt->execute([":ref" => $reference_no]);
  $req = $stmt->fetch();

  if (!$req) {
    json_response(404, ["error" => "Request not found"]);
  }

  if ($req["status"] !== "Released") {
    json_response(422, ["error" => "Only Released records can be archived."]);
  }

  $newArchived = (int)$req["is_archived"] === 1 ? 0 : 1;

  $pdo->prepare("UPDATE requests SET is_archived = :a, updated_at = NOW() WHERE id = :id")
      ->execute([":a" => $newArchived, ":id" => (int)$req["id"]]);

  $action = $newArchived === 1 ? "archived" : "unarchived";

  json_response(200, [
    "message"      => "Record {$action}.",
    "reference_no" => $reference_no,
    "is_archived"  => $newArchived,
  ]);

} catch (Exception $e) {
  json_response(500, ["error" => "Server error"]);
}