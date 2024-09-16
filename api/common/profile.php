<?php

function getProfile($jwt)
{
  // for SQL
  $databaseService = new DatabaseService();
  $conn = $databaseService->getConnection();
  $leads_table_name = 'leads';

  // get jwt
  $lead_id = isset($jwt->lead_id) ? $jwt->lead_id : false;
  // initial SQL lookup
  $profile_query = "SELECT first_name, email, contact_persona, shortBio FROM " . $leads_table_name . " WHERE id=:lead_id";
  $profile_stmt = $conn->prepare($profile_query);
  $profile_stmt->bindParam(':lead_id', $lead_id);
  if ($profile_stmt->execute()) {
    $row = $profile_stmt->fetch(PDO::FETCH_ASSOC);
    $firstname = isset($row['first_name']) ? $row['first_name'] : false;
    $contactPersona = isset($row['contact_persona']) ? $row['contact_persona'] : false;
    $email = isset($row['email']) ? $row['email'] : false;
    $shortBio = isset($row['shortBio']) ? $row['shortBio'] : false;
  } else {
    return (500);
  }

  if ($firstname && $contactPersona && $email) {
    echo json_encode(array(
      "message" => "Profile successfully received.",
      "firstname" => $firstname,
      "contactPersona" => $contactPersona,
      "email" => $email,
      "shortBio" => $shortBio,
      "error" => null,
    ));
    return (200);
  }
  echo json_encode(array(
    "message" => "No profile.",
    "error" => null,
  ));
  return (200);
}

function initProfile($jwt, $payload)
{
  // for Neo4j
  $client = neo4j_connect();

  // for SQL
  $databaseService = new DatabaseService();
  $conn = $databaseService->getConnection();
  $leads_table_name = 'leads';
  $fingerprints_table_name = 'fingerprints';
  $secret_key = SECRET_KEY;

  // get jwt
  $fingerprint_id = isset($jwt->fingerprint_id) ? $jwt->fingerprint_id : false;

  // scenario one -- payload includes fingerprint, codeword, email AND no pre-existing match on email
  // 
  // scenario two -- payload includes fingerprint, codeword, email BUT there's a pre-existing match on email
  // 401
  // 

  // defaults
  $neo4j_fingerprint_id = false;
  $neo4j_lead_id = false;
  $firstname = isset($payload->firstname) ? $payload->firstname : false;
  $codeword = isset($payload->codeword) ? $payload->codeword : false;
  $shortBio = isset($payload->bio) ? $payload->bio : "";
  $contactPersona = isset($payload->persona) ? $payload->persona : "";
  $email = isset($payload->email) ? $payload->email : false;

  // is there a pre-existing match?
  $pre_register_query = "SELECT email FROM " . $leads_table_name . " WHERE email=:email";
  $pre_register_stmt = $conn->prepare($pre_register_query);
  $pre_register_stmt->bindParam(':email', $email);
  if ($pre_register_stmt->execute()) {
    $count = $pre_register_stmt->rowCount();
    if ($count) {
      // pre-existing match found
      return (401);
    }
  } else {
    return (500);
  }

  $query = "INSERT INTO " . $leads_table_name .
    " SET first_name=:first_name, email=:email, contact_persona=:persona, shortBio=:shortBio," .
    " merged=0, updated_at=NOW(), passwordHash=aes_encrypt(:codeword,Password(:secret))";
  $stmt = $conn->prepare($query);
  $stmt->bindParam(':first_name', $firstname);
  $stmt->bindParam(':email', $email);
  $stmt->bindParam(':persona', $contactPersona);
  $stmt->bindParam(':shortBio', $shortBio);
  $stmt->bindParam(':codeword', $codeword);
  $stmt->bindParam(':secret', $secret_key);
  if ($stmt->execute()) {
    $lead_id = $conn->lastInsertId();
  } else {
    return (500);
  }
  if ($lead_id) {
    //now update foreign key on fingerprints table
    $key_query = "UPDATE " . $fingerprints_table_name .
      " SET lead_id=:lead_id" .
      " WHERE id = :fingerprint_id";
    $key_stmt = $conn->prepare($key_query);
    $key_stmt->bindParam(':lead_id', $lead_id);
    $key_stmt->bindParam(':fingerprint_id', $fingerprint_id);
    $key_stmt->execute();
    if (!$key_stmt->rowCount()) {
      return (500);
    }
    // check if fingerprint is merged
    $is_merged_query = "SELECT merged";
    $is_merged_query .= " FROM " . $fingerprints_table_name . " WHERE id=:fingerprint_id";
    $is_merged_stmt = $conn->prepare($is_merged_query);
    $is_merged_stmt->bindParam(':fingerprint_id', $fingerprint_id);
    if ($is_merged_stmt->execute()) {
      $row = $is_merged_stmt->fetch(PDO::FETCH_ASSOC);
      if( isset($row['merged']))
      $neo4j_fingerprint_id = $row['merged'];
    }
  }

  if ($lead_id && $neo4j_fingerprint_id) {
    $neo4j_lead_id = neo4j_merge_lead($client, $lead_id, $neo4j_fingerprint_id);
    //now update foreign key and neo4j id on leads table
    if ($neo4j_lead_id && $neo4j_fingerprint_id) {
      $statement = neo4j_lead_has_fingerprint($neo4j_lead_id, $neo4j_fingerprint_id);
      if ($statement) {
        $client->runStatements([$statement]);
      }
      $set_merged_query = "UPDATE " . $leads_table_name .
        " SET merged=:neo4j_lead_id" .
        " WHERE id = :lead_id";
      $set_merged_stmt = $conn->prepare($set_merged_query);
      $set_merged_stmt->bindParam(':lead_id', $lead_id);
      $set_merged_stmt->bindParam(':neo4j_lead_id', $neo4j_lead_id);
      $set_merged_stmt->execute();
      if (!$set_merged_stmt->rowCount()) {
        return (500);
      }
    }
  }
  echo json_encode(array(
    "message" => "New profile saved.",
    "error" => null,
  ));
  return (200);
}

function saveProfile($jwt, $payload)
{
  // for SQL
  $databaseService = new DatabaseService();
  $conn = $databaseService->getConnection();
  $leads_table_name = 'leads';
  $fingerprints_table_name = 'fingerprints';
  $secret_key = SECRET_KEY;
  $row = false;
  $merged = [];

  // for Neo4j
  $client = neo4j_connect();

  // get jwt
  $fingerprint_id = isset($jwt->fingerprint_id) ? $jwt->fingerprint_id : false;
  $lead_id = isset($jwt->lead_id) ? $jwt->lead_id : false;

  // defaults
  $firstname = isset($payload->firstname) ? $payload->firstname : false;
  $codeword = isset($payload->codeword) ? $payload->codeword : false;
  $shortBio = isset($payload->bio) ? $payload->bio : "";
  $contactPersona = isset($payload->persona) ? $payload->persona : "";
  $email = isset($payload->email) ? $payload->email : false;
  $neo4j_lead_id = false;

  if ($firstname && $contactPersona && $codeword && $email) {
    // initial SQL lookup
    $is_merged_query = "SELECT l.id as lead_id, l.first_name, l.contact_persona, l.shortBio, l.email, l.merged as neo4j_lead_id, f.merged as neo4j_fingerprint_id FROM " . $fingerprints_table_name .
      " as f LEFT JOIN " . $leads_table_name . " as l ON f.lead_id=l.id WHERE l.email=:email";
    $is_merged_stmt = $conn->prepare($is_merged_query);
    $is_merged_stmt->bindParam(':email', $email);
    if ($is_merged_stmt->execute()) {
      $row = $is_merged_stmt->fetch(PDO::FETCH_ASSOC);
      if ($row === false) {
        $failsafe_query = "SELECT id as lead_id, first_name, contact_persona, shortBio, email, merged as neo4j_lead_id FROM " . $leads_table_name . " WHERE email=:email";
        $failsafe_stmt = $conn->prepare($failsafe_query);
        $failsafe_stmt->bindParam(':email', $email);
        if ($failsafe_stmt->execute()) {
          $row = $failsafe_stmt->fetch(PDO::FETCH_ASSOC);
        }
        $failsafe2_query = "SELECT merged as neo4j_fingerprint_id FROM " . $fingerprints_table_name . " WHERE id=:fingerprint_id";
        $failsafe2_stmt = $conn->prepare($failsafe2_query);
        $failsafe2_stmt->bindParam(':fingerprint_id', $fingerprint_id);
        if ($failsafe2_stmt->execute()) {
          $row2 = $failsafe2_stmt->fetch(PDO::FETCH_ASSOC);
        }
      }
      if ($lead_id && (isset($merged["lead_id"]) && $merged["lead_id"]) && $merged['lead_id'] !== $lead_id) {
        echo json_encode(array(
          "message" => "Email already registered.",
          "emailAlreadyKnown" => true,
          "error" => null
        ));
        http_response_code(200);
        die();
      }
      $merged["lead_id"] = isset($row['lead_id']) ? $row['lead_id'] : false;
      $merged['neo4j_lead_id'] = isset($row['neo4j_lead_id']) ? $row['neo4j_lead_id'] : false;
      $merged['neo4j_fingerprint_id'] = isset($row['neo4j_fingerprint_id']) ? $row['neo4j_fingerprint_id'] : false;
      if (!$merged['neo4j_fingerprint_id']) $merged['neo4j_fingerprint_id'] = isset($row2['neo4j_fingerprint_id']) ? $row2['neo4j_fingerprint_id'] : false;
      $merged['first_name'] = isset($row['first_name']) ? $row['first_name'] : false;
      $merged['persona'] = isset($row['contact_persona']) ? $row['contact_persona'] : false;
      $merged['email'] = isset($row['email']) ? $row['email'] : false;
      $merged['shortBio'] = isset($row['shortBio']) ? $row['shortBio'] : false;
    } else {
      return (500);
    }
  }

  if (!$lead_id && isset($merged['lead_id']) && $fingerprint_id) {
    // this is a known lead with a new fingerprint
    $key_query = "UPDATE " . $fingerprints_table_name .
      " SET lead_id=:lead_id" .
      " WHERE id=:fingerprint_id";
    $key_stmt = $conn->prepare($key_query);
    $key_stmt->bindParam(':lead_id', $merged['lead_id']);
    $key_stmt->bindParam(':fingerprint_id', $fingerprint_id);
    $key_stmt->execute();
    // now update neo4j graph -- merge lead with fingerprint, each time to be sure
    if (!isset($merged['neo4j_lead_id']) && isset($merged['neo4j_fingerprint_id']) && isset($merged['lead_id']) && $fingerprint_id) {
      $neo4j_lead_id = neo4j_merge_lead($client, $merged['lead_id'], $merged['neo4j_fingerprint_id']);
      //now update foreign key and neo4j id on leads table
      if ($neo4j_lead_id) {
        $set_merged_query = "UPDATE " . $leads_table_name .
          " SET merged=:neo4j_lead_id" .
          " WHERE id = :lead_id";
        $set_merged_stmt = $conn->prepare($set_merged_query);
        $set_merged_stmt->bindParam(':lead_id', $merged['lead_id']);
        $set_merged_stmt->bindParam(':neo4j_lead_id', $neo4j_lead_id);
        $set_merged_stmt->execute();
      }
    }
  } else if (!isset($merged['email']) && $firstname && $email && $contactPersona && $codeword) {
    // new lead; store to SQL then update neo4j
    $query = "INSERT INTO " . $leads_table_name .
      " SET first_name=:first_name, email=:email, contact_persona=:persona, shortBio=:shortBio," .
      " merged=0, updated_at=NOW(), passwordHash=aes_encrypt(:codeword,Password(:secret))";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':first_name', $firstname);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':persona', $contactPersona);
    $stmt->bindParam(':shortBio', $shortBio);
    $stmt->bindParam(':codeword', $codeword);
    $stmt->bindParam(':secret', $secret_key);
    if ($stmt->execute()) {
      $lead_id = $conn->lastInsertId();
    } else {
      return (500);
    }
    if ($lead_id) {
      //now update foreign key on fingerprints table
      $key_query = "UPDATE " . $fingerprints_table_name .
        " SET lead_id=:lead_id" .
        " WHERE id = :fingerprint_id";
      $key_stmt = $conn->prepare($key_query);
      $key_stmt->bindParam(':lead_id', $lead_id);
      $key_stmt->bindParam(':fingerprint_id', $fingerprint_id);
      $key_stmt->execute();
      if (!$key_stmt->rowCount()) {
        return (500);
      }
    
       // now update neo4j below
    }
    // update only; no insert
  } else if (
    $lead_id && $firstname && $email && $contactPersona && $codeword &&
    ($firstname !== $merged['first_name'] || $contactPersona !== $merged['persona'] || $shortBio !== $merged['shortBio'] || $email !== $merged['email'])
  ) {
    if ($merged['neo4j_fingerprint_id'] && !$merged['neo4j_lead_id']) $neo4j_lead_id = neo4j_merge_lead($client, $lead_id, $merged['neo4j_fingerprint_id']);
    $update_query = "UPDATE " . $leads_table_name .
      " SET first_name=:firstname, contact_persona=:contact_persona, shortBio=:shortBio, email=:email";
    if ($neo4j_lead_id) $update_query .= ", merged=:neo4j_lead_id";
    $update_query .=  " WHERE id = :lead_id";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindParam(':lead_id', $merged['lead_id']);
    $update_stmt->bindParam(':firstname', $firstname);
    $update_stmt->bindParam(':contact_persona', $contactPersona);
    $update_stmt->bindParam(':shortBio', $shortBio);
    $update_stmt->bindParam(':email', $email);
    if ($neo4j_lead_id) $update_stmt->bindParam(':neo4j_lead_id', $neo4j_lead_id);
    $update_stmt->execute();
    if (!$update_stmt->rowCount()) {
      return (500);
    }
  }

  // now update neo4j graph -- merge lead with fingerprint, each time to be sure
  if (!isset($merged['neo4j_lead_id']) && isset($merged['neo4j_fingerprint_id']) && $lead_id && $fingerprint_id) {
    $neo4j_lead_id = neo4j_merge_lead($client, $lead_id, $merged['neo4j_fingerprint_id']);
    //now update foreign key and neo4j id on leads table
    if ($neo4j_lead_id) {
      $statement = neo4j_lead_has_fingerprint($neo4j_lead_id, $merged['neo4j_fingerprint_id']);
      if ($statement) {
        $client->runStatements([$statement]);
      }
      $set_merged_query = "UPDATE " . $leads_table_name .
        " SET merged=:neo4j_lead_id" .
        " WHERE id = :lead_id";
      $set_merged_stmt = $conn->prepare($set_merged_query);
      $set_merged_stmt->bindParam(':lead_id', $lead_id);
      $set_merged_stmt->bindParam(':neo4j_lead_id', $neo4j_lead_id);
      $set_merged_stmt->execute();
      if (!$set_merged_stmt->rowCount()) {
        return (500);
      }
    }
  }
  echo json_encode(array(
    "message" => "Profile saved.",
    "error" => null,
  ));
  return (200);
}
