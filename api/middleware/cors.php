<?php
// api/middleware/cors.php
 
declare(strict_types=1);
 
$ALLOWED_ORIGINS = [
    "http://localhost",
    "http://localhost:3000",
    "https://eserbisyo.yourmunicipality.gov.ph",

];
$ALLOWED_METHODS = "GET, POST, OPTIONS";
$ALLOWED_HEADERS = "Content-Type, X-Requested-With";

$origin = $_SERVER["HTTP_ORIGIN"] ?? "";
if (in_array($origin, $ALLOWED_ORIGINS, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header("Vary: Origin");
} 
header("Access-Control-Allow-Headers: {$ALLOWED_HEADERS}");
header("Access-Control-Allow-Methods: {$ALLOWED_METHODS}");
header("Access-Control-Allow-Credentials: true");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin"); 

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    header("Access-Control-Max-Age: 600");
    http_response_code(204);
    exit;
}