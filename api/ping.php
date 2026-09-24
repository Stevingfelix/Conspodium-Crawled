<?php
// api/ping.php - Minimal test to verify vercel-php runtime works
header("Content-Type: application/json");
echo json_encode(["pong" => true, "php" => phpversion(), "time" => date('c')]);
