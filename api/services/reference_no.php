<?php
declare(strict_types=1);

function generate_reference_no(PDO $pdo): string {
  // Example format: ES-20260106-AB12CD
  // Loop until UNIQUE reference_no is not found
  while (true) {
    $date = date('Ymd');
    $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $ref = "ES-$date-$rand";

    $stmt = $pdo->prepare("SELECT 1 FROM requests WHERE reference_no = :ref LIMIT 1");
    $stmt->execute([":ref" => $ref]);

    if ($stmt->fetch() === false) {
      return $ref;
    }
  }
}
