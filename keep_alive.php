<?php
require_once 'db.php';
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['loggedIn' => isset($_SESSION['user_id'])]);