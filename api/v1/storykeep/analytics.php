<?php
require "../../../vendor/autoload.php";
include_once '../../common/analytics.php';
include_once '../../common/database.php';
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
        // Get the id from the URL parameters
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        $type = isset($_GET['type']) ? $_GET['type'] : null;
        $duration = isset($_GET['duration']) ? $_GET['duration'] : null;
        if( empty($id) || empty($type) || empty($duration) ) {
          echo json_encode([
              'success' => false,
              'message' => 'Method not allowed'
          ]);
          http_response_code(405);
	  die();
	}		
        $res = getAnalytics($id, $type, $duration );
        http_response_code($res);
    } else {
  	  error_log(3);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
        http_response_code(405);
    }
} else {
  	  error_log(4);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or missing secret'
    ]);
    http_response_code(401);
}
