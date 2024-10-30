<?php
require "../../../vendor/autoload.php";
include_once '../../common/publish.php';
//include_once '../../common/database.php';
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
// Allow from any origin
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}
// Access-Control headers are received during OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");     
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}
header("Content-Type: application/json; charset=UTF-8");

// Load environment variables
$concierge_secret = $_ENV['CONCIERGE_SECRET'];

// Check for the secret in the headers
$headers = getallheaders();
$provided_secret = isset($headers['X-Concierge-Secret']) ? $headers['X-Concierge-Secret'] : null;

if ($provided_secret === $concierge_secret) {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $http_response_code = getSettings();

    } else if ($method === 'POST') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (!empty($data)) {
            $http_response_code = postSettings($data);
        }
        else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid JSON payload'
            ]);
            http_response_code(400);
        }
    } else {
        // Method not allowed
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
        http_response_code(405);
    }
} else {
    // The secret doesn't match or wasn't provided
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or missing secret'
    ]);
    http_response_code(401);
}
