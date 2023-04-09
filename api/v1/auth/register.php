<?php
require_once "../../../vendor/autoload.php";
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
$lead_id = false;
$visit_id = false;
$neo4j_fingerprint_id = false;
$neo4j_visit_id = false;
$merge = false;
$lead_merge = false;
$fingerprint_registered = false;
$now_seconds = time();
$now = date('Y-m-d H:i:s', $now_seconds);
$expiry_claim = strtotime("now +15 minutes"); // life of JWT
$valid_until = date('Y-m-d H:i:s', strtotime("now +14 days")); // life of refreshToken
$secret_key = SECRET_KEY;
$auth = false;
$firstname = false;
$emailConflict = false;
$knownNewFingerprint = false;
$heldBeliefs = false;

// response
$http_response_code = 400;

// connect to database
$databaseService = new DatabaseService();
$conn = $databaseService->getConnection();
$fingerprints_table_name = 'fingerprints';
$tokens_table_name = 'tokens';
$leads_table_name = 'leads';
$visits_table_name = 'visits';
$beliefs_table_name = 'heldbeliefs';
$corpus_table_name = 'corpus';

// neo4j
$client = neo4j_connect();

// get POST payload
$data = json_decode(file_get_contents("php://input"));

$fingerprint = isset($data->fingerprint) ? $data->fingerprint : false;
$codeword = isset($data->codeword) ? $data->codeword : false;
$email = isset($data->email) ? $data->email : false;
$encryptedCode = isset($data->encryptedCode) ? $data->encryptedCode : false;
$encryptedEmail = isset($data->encryptedEmail) ? $data->encryptedEmail : false;
$mode = false;
$pre_register_query = false;
$pre_register_stmt = false;
$httpReferrer = $_SERVER['HTTP_REFERER'] ?? false;
$httpUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? false;

// scenario one -- payload includes fingerprint
// mode: fingerprint
//
// scenario two -- payload includes fingerprint, encryptedCode, encryptedEmail
// mode: fastpass
//
// scenario three -- payload includes fingerprint, codeword, email
// mode: authenticate

if ($fingerprint && $codeword && $email) $mode = "authenticate";
else if ($fingerprint && $encryptedCode && $encryptedEmail) $mode = "fastpass";
else if ($fingerprint) $mode = "fingerprint";

switch ($mode) {
  case "fingerprint":
    $pre_register_query = "SELECT f.id as fingerprint_id,l.id as lead_id, first_name, f.merged as neo4j_fingerprint_id, l.merged as neo4j_lead_id";
    $pre_register_query .= " FROM " . $fingerprints_table_name . " as f LEFT JOIN " . $leads_table_name . " as l ON f.lead_id=l.id WHERE";
    $pre_register_query .= " f.fingerprint=:fingerprint";
    $pre_register_stmt = $conn->prepare($pre_register_query);
    $pre_register_stmt->bindParam(':fingerprint', $fingerprint);
    break;

  case "authenticate":
    // not matching on fingerprint ... may be known lead with new fingerprint; must detect
    $pre_register_query = "(SELECT f.id as fingerprint_id,l.id as lead_id, first_name, f.merged as neo4j_fingerprint_id, l.merged as neo4j_lead_id,";
    $pre_register_query .= " TO_BASE64(AES_ENCRYPT(:codeword,Password(:secret))) as codeword, TO_BASE64(AES_ENCRYPT(:email,Password(:secret))) as email";
    $pre_register_query .= " FROM " . $fingerprints_table_name . " as f LEFT JOIN " . $leads_table_name . " as l ON f.lead_id=l.id WHERE";
    $pre_register_query .= " l.email=:email AND l.passwordHash=aes_encrypt(:codeword, Password(:secret)) LIMIT 1)";
    $pre_register_query .= " UNION";
    $pre_register_query .= " SELECT f.id as fingerprint_id,l.id as lead_id, first_name, f.merged as neo4j_fingerprint_id, l.merged as neo4j_lead_id,";
    $pre_register_query .= " TO_BASE64(AES_ENCRYPT(:codeword,Password(:secret))) as codeword, TO_BASE64(AES_ENCRYPT(:email,Password(:secret))) as email";
    $pre_register_query .= " FROM " . $fingerprints_table_name . " as f LEFT JOIN " . $leads_table_name . " as l ON f.lead_id=l.id WHERE";
    $pre_register_query .= " f.fingerprint=:fingerprint";
    $pre_register_stmt = $conn->prepare($pre_register_query);
    $pre_register_stmt->bindParam(':email', $email);
    $pre_register_stmt->bindParam(':fingerprint', $fingerprint);
    $pre_register_stmt->bindParam(':codeword', $codeword);
    $pre_register_stmt->bindParam(':secret', $secret_key);
    break;

  case "fastpass":
    // not matching on fingerprint ... may be known lead with new fingerprint; must detect
    $pre_register_query = "(SELECT f.id as fingerprint_id,l.id as lead_id, first_name, f.merged as neo4j_fingerprint_id, l.merged as neo4j_lead_id";
    $pre_register_query .= " FROM " . $fingerprints_table_name . " as f LEFT JOIN " . $leads_table_name . " as l ON f.lead_id=l.id WHERE";
    $pre_register_query .= " :encryptedEmail=TO_BASE64(AES_ENCRYPT(l.email,Password(:secret))) AND :encryptedCode=TO_BASE64(l.passwordHash) LIMIT 1)";
    $pre_register_query .= " UNION";
    $pre_register_query .= " SELECT f.id as fingerprint_id,l.id as lead_id, first_name, f.merged as neo4j_fingerprint_id, l.merged as neo4j_lead_id";
    $pre_register_query .= " FROM " . $fingerprints_table_name . " as f LEFT JOIN " . $leads_table_name . " as l ON f.lead_id=l.id WHERE";
    $pre_register_query .= " f.fingerprint=:fingerprint";
    $pre_register_stmt = $conn->prepare($pre_register_query);
    $pre_register_stmt->bindParam(':fingerprint', $fingerprint);
    $pre_register_stmt->bindParam(':encryptedCode', $encryptedCode);
    $pre_register_stmt->bindParam(':encryptedEmail', $encryptedEmail);
    $pre_register_stmt->bindParam(':secret', $secret_key);
    break;
}

if ($pre_register_stmt->execute()) {
  $count = $pre_register_stmt->rowCount();
  if ($count > 1) {
    $rows = $pre_register_stmt->fetchAll(PDO::FETCH_NAMED);
    $row = $rows[0];
    $row['fingerprint_id'] = $rows[1]['fingerprint_id'];
    $row['neo4j_fingerprint_id'] = $rows[1]['neo4j_fingerprint_id'];
    $knownNewFingerprint = true;
    $lead_merge = true;
  } else {
    $row = $pre_register_stmt->fetch(PDO::FETCH_ASSOC);
  }

  $fingerprint_id = isset($row['fingerprint_id']) ? strval($row['fingerprint_id']) : false;
  $neo4j_fingerprint_id = isset($row['neo4j_fingerprint_id']) ? strval($row['neo4j_fingerprint_id']) : false;
  $neo4j_lead_id = isset($row['neo4j_lead_id']) ? strval($row['neo4j_lead_id']) : false;
  $lead_id = isset($row['lead_id']) ? strval($row['lead_id']) : false;
  $firstname = isset($row['first_name']) ? $row['first_name'] : false;
  $fingerprint_registered = $fingerprint_id ? true : false;
  $newEncryptedEmail = isset($row['email']) ? $row['email'] : false;
  $newEncryptedCode = isset($row['codeword']) ? $row['codeword'] : false;
  if ($mode === "authenticate" || $mode === "fastpass")
    $auth = true;
} else if ($mode) {
  error_log('Security violation occurred.');
  http_response_code(401);
  die();
} else {
  http_response_code(500);
  die();
}

// register new fingerprint
if (!$fingerprint_registered && !$emailConflict && !$fingerprint_id) {
  $query = "INSERT INTO " . $fingerprints_table_name . " SET fingerprint = :fingerprint";
  $stmt = $conn->prepare($query);
  $stmt->bindParam(':fingerprint', $fingerprint);
  if ($stmt->execute()) {
    $fingerprint_id = strval($conn->lastInsertId());
    error_log("New fingerprint: " . strval($fingerprint_id));
  } else {
    http_response_code(500);
    die();
  }
}

// does an active token exist? an active visit?
$token_check_query = "SELECT t.id as token_id,aes_decrypt(refreshToken,Password(:secret)) as refreshToken," .
  " v.id as visit_id, v.merged as neo4j_visit_id, l.first_name FROM " . $tokens_table_name . " as t LEFT JOIN " . $visits_table_name .
  " as v ON t.fingerprint_id=v.fingerprint_id" .
  " LEFT JOIN " . $fingerprints_table_name . " as f on v.fingerprint_id=f.id " .
  " LEFT JOIN " . $leads_table_name . " as l on f.lead_id=l.id" .
  " WHERE t.fingerprint_id = :fingerprint_id AND t.valid_until > :now AND TIMESTAMPDIFF(hour, v.updated_at, :now) < 24";
$token_check_stmt = $conn->prepare($token_check_query);
$token_check_stmt->bindParam(':fingerprint_id', $fingerprint_id);
$token_check_stmt->bindParam(':secret', $secret_key);
$token_check_stmt->bindParam(':now', $now);
if ($token_check_stmt->execute()) {
  $row = $token_check_stmt->fetch(PDO::FETCH_ASSOC);
  $token_id = isset($row['token_id']) ? $row['token_id'] : false;
  $visit_id = isset($row['visit_id']) ? $row['visit_id'] : false;
  $neo4j_visit_id = isset($row['neo4j_visit_id']) ? $row['neo4j_visit_id'] : false;
  $firstname = isset($row['first_name']) ? $row['first_name'] : false;
  $refreshToken = isset($row['refreshToken']) ? $row['refreshToken'] : false;
  $has_token = is_int($token_id) ? $token_id : false;
  $http_response_code = 200;
} else {
  http_response_code(500);
  die();
}

if (!$visit_id && !$emailConflict) {
  // no visit exists; must create
  $visit_create_query = "INSERT INTO " . $visits_table_name .
    " SET fingerprint_id = :fingerprint_id,";
  $visit_create_query .=
    " created_at = :created_at," .
    " updated_at = :updated_at," .
    " merged = FALSE," .
    " httpReferrer = :httpReferrer," .
    " httpUserAgent = :httpUserAgent";
  $visit_create_stmt = $conn->prepare($visit_create_query);
  $visit_create_stmt->bindParam(':fingerprint_id', $fingerprint_id);
  $visit_create_stmt->bindParam(':created_at', $now);
  $visit_create_stmt->bindParam(':updated_at', $now);
  $visit_create_stmt->bindParam(':httpReferrer', $httpReferrer);
  $visit_create_stmt->bindParam(':httpUserAgent', $httpUserAgent);
  if ($visit_create_stmt->execute()) {
    $visit_id = strval($conn->lastInsertId());
    error_log("New visit: " . strval($visit_id));
  } else {
    http_response_code(500);
    die();
  }
}
if (!$has_token && !$emailConflict) {
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
    error_log("New token: " . strval($token_id));
  } else {
    http_response_code(500);
    die();
  }
}
// is fingerprint merged?
if (!$neo4j_fingerprint_id) {
  $neo4j_fingerprint_id = neo4j_merge_fingerprint($client, $fingerprint_id);
  error_log("Merged fingerprint: " . strval($neo4j_fingerprint_id));
  if ($neo4j_fingerprint_id)
    $merge = true;
  else $neo4j_fingerprint_id = 0;
}
if ($merge) error_log('merge');
else error_log('no');
// is visit merged?
if (!$neo4j_visit_id) {
  $neo4j_visit_id = neo4j_merge_visit($client, $visit_id, $now_seconds);
  error_log("Merged visit: " . strval($neo4j_visit_id));
  if ($neo4j_visit_id)
    $merge = true;
  else $neo4j_visit_id = 0;
}
if ($merge) {
  $first_merge_query = "UPDATE " . $fingerprints_table_name . " f" .
    " JOIN " . $visits_table_name . " v ON f.id=v.fingerprint_id";
  $first = true;
  $first_merge_query .= " SET ";
  if ($neo4j_fingerprint_id) {
    if ($first) $first = false;
    else $first_merge_query .= ", ";
    $first_merge_query .= "f.merged=:neo4j_fingerprint";
  }
  if ($neo4j_visit_id) {
    if ($first) $first = false;
    else $first_merge_query .= ", ";
    $first_merge_query .= "v.merged=:neo4j_visit";
  }
  $first_merge_query .= " WHERE f.id = :fingerprint_id" .
    " AND v.id = :visit_id";
  $first_merge_stmt = $conn->prepare($first_merge_query);
  $first_merge_stmt->bindParam(':fingerprint_id', $fingerprint_id);
  $first_merge_stmt->bindParam(':visit_id', $visit_id);
  if ($neo4j_fingerprint_id) $first_merge_stmt->bindParam(':neo4j_fingerprint', $neo4j_fingerprint_id);
  if ($neo4j_visit_id) $first_merge_stmt->bindParam(':neo4j_visit', $neo4j_visit_id);
  if (!$first_merge_stmt->execute()) {
    die();
  }
}

// run on every register
if ($neo4j_fingerprint_id && $neo4j_visit_id) {
  error_log("Merged Fingerprint has Visit: " . strval($neo4j_fingerprint_id) . " " . strval($neo4j_visit_id));
  $statement = neo4j_fingerprint_has_visit($neo4j_fingerprint_id, $neo4j_visit_id);
  if ($statement)
    $client->runStatement($statement);
  else error_log('bad on fingerprint has visit');
}

// is lead merged to this fingerprint?
if (!$neo4j_lead_id && $neo4j_fingerprint_id && $lead_id) {
  $neo4j_lead_id = neo4j_merge_lead($client, $lead_id);
  error_log("Merged lead: " . strval($neo4j_lead_id));
  $lead_merge = true;
}

if ($lead_merge && $neo4j_lead_id && $neo4j_fingerprint_id) {
  $statement = neo4j_lead_has_fingerprint($lead_id, $fingerprint_id);
  error_log("Merged Lead has Fingerprint: " . strval($lead_id) . " " . strval($fingerprint_id));
  if ($statement)
    $client->runStatement($statement);
  else error_log('bad on lead has fingerprint');
}

// known Lead with new Fingerprint
if ($knownNewFingerprint && $neo4j_lead_id) {
  $set_merged_query = "UPDATE " . $leads_table_name .
    " SET merged=:neo4j_lead_id" .
    " WHERE id = :lead_id";
  $set_merged_stmt = $conn->prepare($set_merged_query);
  $set_merged_stmt->bindParam(':lead_id', $merged['lead_id']);
  $set_merged_stmt->bindParam(':neo4j_lead_id', $neo4j_lead_id);
  $set_merged_stmt->execute();
  $set_merged2_query = "UPDATE " . $fingerprints_table_name .
    " SET lead_id=:lead_id" .
    " WHERE id=:fingerprint_id";
  $set_merged2_stmt = $conn->prepare($set_merged2_query);
  $set_merged2_stmt->bindParam(':lead_id', $lead_id);
  $set_merged2_stmt->bindParam(':fingerprint_id', $fingerprint_id);
  $set_merged2_stmt->execute();
}

// load heldBeliefs for this fingerprint_id
if ( $fingerprint_id ) {
    $belief_query = "SELECT c.object_name as title, c.object_id as slug, b.verb";
    $belief_query .= " FROM " . $beliefs_table_name . " as b LEFT JOIN " . $corpus_table_name . " as c ON b.belief_id=c.id WHERE";
    $belief_query .= " b.fingerprint_id=:fingerprint";
    $belief_stmt = $conn->prepare($belief_query);
    $belief_stmt->bindParam(':fingerprint', $fingerprint_id);
    if ($belief_stmt->execute()) {
        $count = $belief_stmt->rowCount();
        if ($count > 1) {
		$rows = $belief_stmt->fetchAll(PDO::FETCH_NAMED);
		$heldBeliefs = json_encode($rows);
        } 
    }
}

// now issue JWT
$issuer_claim = "tractStack by At Risk Media";
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
    "created_at" => $now_seconds,
    "auth" => $auth
  )
);
setcookie("refreshToken", $refreshToken, ['httponly' => true, 'samesite' => 'Lax']);
$jwt = JWT::encode($token, $secret_key);
$http_response_code = 200;
$results = array(
  "message" => "Successful login.",
  "jwt" => $jwt,
  "auth" => $auth,
  "encryptedEmail" => $newEncryptedEmail,
  "encryptedCode" => $newEncryptedCode,
  "first_name" => $firstname,
  "beliefs" => $heldBeliefs,
  "known_lead" => !!$firstname,
  "email_conflict" => $emailConflict,
  "created_at" => $now_seconds
);

echo json_encode(
  $results
);
http_response_code($http_response_code);
