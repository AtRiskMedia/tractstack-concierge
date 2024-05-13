<?php

require_once "../../../vendor/autoload.php";

include_once '../../common/database.php';

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
$fingerprint = false;
$fingerprint_id = false;
$visit_id = false;
$lead_id = false;
$has_token = false;
$now = date('Y-m-d H:i:s');
//$expiry_claim = strtotime("now +45 seconds"); // life of JWT
$expiry_claim = strtotime("now +15 minutes"); // life of JWT
$valid_until = date('Y-m-d H:i:s', strtotime("now +14 days")); // life of refreshToken
$secret_key = SECRET_KEY;
$auth = false;
$firstname = false;
$encryptedEmail = false;
$encryptedCode = false;

// response
$http_response_code = 400;

// connect to database
$conn = null;
$databaseService = new DatabaseService();
$conn = $databaseService->getConnection();
$fingerprints_table_name = 'fingerprints';
$tokens_table_name = 'tokens';
$visits_table_name = 'visits';
$leads_table_name = 'leads';

// get POST payload
$data = json_decode(file_get_contents("php://input"));
$email = isset($data->encryptedEmail) ? $data->encryptedEmail : false;
$codeword = isset($data->encryptedCode) ? $data->encryptedCode : false;
$refreshToken = isset($data->refreshToken) ? $data->refreshToken : false;

// get cookie
//$refreshToken = $_COOKIE['refreshToken'] ?? false;
if ($refreshToken) {
  // does active refreshToken exist?
  $token_check_query = "SELECT t.id as token_id, f.lead_id as lead_id, t.fingerprint_id, f.fingerprint, v.id as visit_id, UNIX_TIMESTAMP(v.created_at) as created_at, l.first_name FROM "
    . $tokens_table_name . " as t LEFT JOIN " . $fingerprints_table_name . " as f ON f.id=t.fingerprint_id LEFT JOIN "
    . $visits_table_name . " as v ON f.id=v.fingerprint_id LEFT JOIN "
    . $leads_table_name . " as l ON l.id=f.lead_id "
    . "WHERE refreshToken = aes_encrypt(:refreshToken,Password(:secret)) AND valid_until > :now";
  if ($codeword && $email) {
    $token_check_query .= "  AND :email=to_base64(aes_encrypt(l.email,Password(:secret))) AND :codeword=to_base64(l.passwordHash)";
  }
  error_log('     '.$token_check_query.'    ');
  $token_check_stmt = $conn->prepare($token_check_query);
  $token_check_stmt->bindParam(':secret', $secret_key);
  $token_check_stmt->bindParam(':refreshToken', $refreshToken);
  $token_check_stmt->bindParam(':now', $now);
  if ($codeword && $email) {
    $token_check_stmt->bindParam(':email', $email);
    $token_check_stmt->bindParam(':codeword', $codeword);
  }
  if ($token_check_stmt->execute()) {
    $row = $token_check_stmt->fetch(PDO::FETCH_ASSOC);
    $token_id = isset($row['token_id']) ? $row['token_id'] : false;
    $visit_id = isset($row['visit_id']) ? $row['visit_id'] : false;
    $lead_id = isset($row['lead_id']) ? $row['lead_id'] : false;
    $firstname = isset($row['first_name']) ? $row['first_name'] : false;
    $fingerprint_id = isset($row['fingerprint_id']) ? $row['fingerprint_id'] : false;
    $fingerprint = isset($row['fingerprint']) ? $row['fingerprint'] : false;
    $created_at = isset($row['created_at']) ? $row['created_at'] : false;
    $has_token = (is_int($token_id) && is_string($fingerprint) && is_int($fingerprint_id) && is_int($created_at)) ? true : false;
    if ($codeword && $email)
      $auth = true;
  }
}

if ($has_token) {
  // recycle refreshToken
  $newRefreshToken = uniqid();
  $token_create_query = "UPDATE " . $tokens_table_name .
    " SET updated_at = :update," .
    " valid_until = :valid," .
    " refreshToken = aes_encrypt(:refreshToken,Password(:secret))" .
    " WHERE id = :id";
  $token_create_stmt = $conn->prepare($token_create_query);
  $token_create_stmt->bindParam(':update', $now);
  $token_create_stmt->bindParam(':valid', $valid_until);
  $token_create_stmt->bindParam(':refreshToken', $newRefreshToken);
  $token_create_stmt->bindParam(':secret', $secret_key);
  $token_create_stmt->bindParam(':id', $token_id);
  $token_create_stmt->execute();
  if ($token_create_stmt->rowCount()) {
    // issue JWT
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
        "fingerprint_id" => $fingerprint_id,
        "lead_id" => $lead_id,
        "visit_id" => $visit_id,
        "created_at" => $created_at,
        "auth" => $auth
      )
    );

    // do this for builder *for now*
    setcookie("refreshToken", $newRefreshToken, ['httponly' => true, 'samesite' => 'Lax']);

    $jwt = JWT::encode($token, $secret_key);
    $http_response_code = 200;
    echo json_encode(
      array(
        "message" => "Successful login.",
        "jwt" => $jwt,
        "refreshToken" => $newRefreshToken,
        "auth" => $auth,
        "encryptedEmail" => $encryptedEmail,
        "encryptedCode" => $encryptedCode,
        "first_name" => $firstname,
        "known_lead" => !!$firstname,
        "created_at" => $created_at
      )
    );
  }
} else {
  $http_response_code = 401;
}

http_response_code($http_response_code);
