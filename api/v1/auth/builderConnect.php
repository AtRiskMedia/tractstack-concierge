<?php
require_once "../../../vendor/autoload.php";
include_once '../../common/database.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

use \Firebase\JWT\JWT;

// who doesn't love CORS?
$httpOrigin = $_SERVER['HTTP_ORIGIN'] ?? false;
//header('Access-Control-Allow-Origin: ' . $httpOrigin);
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
$now_seconds = time();
$now = date('Y-m-d H:i:s', $now_seconds);
$expiry_claim = strtotime("now +15 minutes"); // life of JWT
$valid_until = date('Y-m-d H:i:s', strtotime("now +14 days")); // life of refreshToken
$secret_key = SECRET_KEY;
$builder_secret_key = BUILDER_SECRET_KEY;
$auth = false;

// response
$http_response_code = 401;

// connect to database
$databaseService = new DatabaseService();
$conn = $databaseService->getConnection();
$fingerprints_table_name = 'fingerprints';
$tokens_table_name = 'tokens';

// get POST payload
$data = json_decode(file_get_contents("php://input"));
$secret = isset($data->secret) ? $data->secret : false;
$fingerprint = isset($data->fingerprint) ? $data->fingerprint : false;

if ($secret === $builder_secret_key) {
  $pre_register_query = "SELECT id as fingerprint_id";
  $pre_register_query .= " FROM " . $fingerprints_table_name . " WHERE ";
  $pre_register_query .= " fingerprint=:fingerprint";
  $pre_register_stmt = $conn->prepare($pre_register_query);
  $pre_register_stmt->bindParam(':fingerprint', $fingerprint);

  if ($pre_register_stmt->execute()) {
    $row = $pre_register_stmt->fetch(PDO::FETCH_ASSOC);
    $fingerprint_id = isset($row['fingerprint_id']) ? strval($row['fingerprint_id']) : false;
    $fingerprint_registered = $fingerprint_id ? true : false;
  } else {
    http_response_code(403);
    die();
  }

  // register new fingerprint
  if (!$fingerprint_registered && !$fingerprint_id) {
    $query = "INSERT INTO " . $fingerprints_table_name . " SET fingerprint = :fingerprint";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':fingerprint', $fingerprint);
    if ($stmt->execute()) {
      $fingerprint_id = strval($conn->lastInsertId());
      //error_log("New fingerprint: " . strval($fingerprint_id));
    } else {
      http_response_code(403);
      die();
    }
  }

  // does an active token exist? 
  $token_check_query = "SELECT t.id as token_id,aes_decrypt(refreshToken,Password(:secret)) as refreshToken" .
    " FROM " . $tokens_table_name . " as t" .
    " LEFT JOIN " . $fingerprints_table_name . " as f on t.fingerprint_id=f.id " .
    " WHERE t.fingerprint_id = :fingerprint_id AND t.valid_until > :now";
  $token_check_stmt = $conn->prepare($token_check_query);
  $token_check_stmt->bindParam(':fingerprint_id', $fingerprint_id);
  $token_check_stmt->bindParam(':secret', $secret_key);
  $token_check_stmt->bindParam(':now', $now);
  if ($token_check_stmt->execute()) {
    $row = $token_check_stmt->fetch(PDO::FETCH_ASSOC);
    $token_id = isset($row['token_id']) ? $row['token_id'] : false;
    $firstname = isset($row['first_name']) ? $row['first_name'] : false;
    $refreshToken = isset($row['refreshToken']) ? $row['refreshToken'] : false;
    $has_token = is_int($token_id) ? $token_id : false;
    $http_response_code = 200;
  } else {
    http_response_code(403);
    die();
  }

  if (!$has_token) {
    // no token, must create
    $refreshToken = uniqid();
    $token_create_query = "INSERT INTO " . $tokens_table_name .
      " SET fingerprint_id = :fingerprint_id," .
      " created_at = :created_at," .
      " updated_at = :updated_at," .
      " valid_until = :valid_until," .
      " refreshToken = aes_encrypt(:refreshToken,Password(:secret))";
    $token_create_stmt = $conn->prepare($token_create_query);
    $token_create_stmt->bindParam(':fingerprint_id', $fingerprint_id);
    $token_create_stmt->bindParam(':created_at', $now);
    $token_create_stmt->bindParam(':updated_at', $now);
    $token_create_stmt->bindParam(':valid_until', $valid_until);
    $token_create_stmt->bindParam(':refreshToken', $refreshToken);
    $token_create_stmt->bindParam(':secret', $secret_key);
    if ($token_create_stmt->execute()) {
      $token_id = strval($conn->lastInsertId());
    } else {
      http_response_code(403);
      die();
    }
  }

  // now issue JWT
  $refreshToken = uniqid();
  $issuer_claim = "Tract Stack by At Risk Media";
  $audience_claim = $fingerprint;
  $issuedat_claim = time();
  $notbefore_claim = time();
  $token = array(
    "iss" => $issuer_claim,
    "sub" => $fingerprint,
    "aud" => $audience_claim,
    "exp" => $expiry_claim,
    "nbf" => $notbefore_claim,
    "iat" => $issuedat_claim,
    "data" => array(
      "created_at" => $now_seconds,
    )
  );
  setcookie("refreshToken", $refreshToken, ['httponly' => true, 'samesite' => 'Lax']);
  $jwt = JWT::encode($token, $secret_key);
  $http_response_code = 200;
  $results = array(
    "message" => "Successful login.",
    "jwt" => $jwt,
    "created_at" => $now_seconds
  );

  echo json_encode(
    $results
  );
  http_response_code($http_response_code);
} else http_response_code(401);
