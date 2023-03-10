<?php

require "../../../vendor/autoload.php";

include_once '../../common/events.php';
include_once '../../common/database.php';
include_once '../../common/neo4j.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

use \Firebase\JWT\JWT;

// who doesn't love CORS?
$httpOrigin = $_SERVER['HTTP_ORIGIN'] ?? false;
header('Access-Control-Allow-Origin: ' . $httpOrigin);
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Authorization');
  header('Access-Control-Max-Age: 1728000');
  header('Content-Length: 0');
  header('Content-Type: text/plain');
  die();
}
header("Content-Type: application/json; charset=UTF-8");

// defaults
$secret_key = SECRET_KEY;

// get POST payload
$data = json_decode(file_get_contents("php://input"));

// response
$http_response_code = 401;

$jwt = null;
$databaseService = new DatabaseService();
$conn = $databaseService->getConnection();

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? false;
$arr = explode(" ", $authHeader);
$jwt = isset($arr[1]) ? $arr[1] : false;

if ($jwt) {
  try {
    $decoded = JWT::decode($jwt, $secret_key, array('HS256'));
    $http_response_code = 200;
    if (isset($data))
      processEventStream($decoded->data, $data);
  } catch (Exception $e) {
    http_response_code(401);
    error_log($e->getMessage());
    echo json_encode(array(
      "message" => "Access denied.",
      "error" => $e->getMessage()
    ));
  }
}

http_response_code($http_response_code);
