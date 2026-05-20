<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../services/response.php';
require_once __DIR__ . '/../../middleware/auth_staff.php';
require_once __DIR__ . '/../../middleware/csrf.php';

json_response(200, [
  "ok"         => true,
  "user"       => $_SESSION["staff"],
  "csrf_token" => csrf_token(),
]);
