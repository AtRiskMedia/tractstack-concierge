<?php

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../../');
$dotenv->load();

define('NEO4J_SECRET', $_ENV['NEO4J_SECRET']);
define('NEO4J_URI', $_ENV['NEO4J_URI']);
define('NEO4J_USER', $_ENV['NEO4J_USER']);
define('NEO4J_ENABLED', $_ENV['NEO4J_ENABLED']==='true');
define('HELDBELIEFS',  array(
  "STRONGLY_AGREES",
  "AGREES",
  "NEITHER_AGREES_NOR_DISAGREES",
  "DISAGREES",
  "STRONGLY_DISAGREES",
  "INTERESTED",
  "NOT_INTERESTED",
  "BELIEVES_YES",
  "BELIEVES_NO",
  "BELIEVES_TRUE",
  "BELIEVES_FALSE",
));

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Contracts\TransactionInterface;

function neo4j_connect()
{
  if (!NEO4J_ENABLED) return null;
  $uri = NEO4J_URI;
  $user = NEO4J_USER;
  $password = NEO4J_SECRET;
  $auth = Authenticate::basic($user, $password);
  $client = ClientBuilder::create()
    ->withDriver('default', $uri, $auth)
    ->withFormatter(\Laudis\Neo4j\Formatter\OGMFormatter::create())
    ->build();
  return $client;
}

function neo4j_merge_fingerprint($client, $fingerprint_id)
{
  if (!NEO4J_ENABLED) return null;
  $result = $client->writeTransaction(static function (TransactionInterface $tsx) use ($fingerprint_id) {
    $result = $tsx->run('MERGE (f:Fingerprint {fingerprint_id: $fingerprint_id}) return elementId(f)', ['fingerprint_id' => $fingerprint_id]);
    return $result->first()->get('elementId(f)');
  });
  if ($result) return $result;
  return null;
}

function neo4j_merge_lead($client, $lead_id)
{
  if (!NEO4J_ENABLED) return null;
  $result = $client->writeTransaction(static function (TransactionInterface $tsx) use ($lead_id) {
    $result = $tsx->run('MERGE (l:Lead {lead_id: $lead_id}) return elementId(l)', ['lead_id' => $lead_id]);
    return $result->first()->get('elementId(l)');
  });
  if ($result) return $result;
  return null;
}

function neo4j_merge_visit($client, $visit_id, $created_at)
{
  if (!NEO4J_ENABLED) return null;
  $result = $client->writeTransaction(static function (TransactionInterface $tsx) use ($visit_id, $created_at) {
    $result = $tsx->run('MERGE (v:Visit {visit_id: $visit_id, created_at: $created_at}) return elementId(v)', ['visit_id' => $visit_id, 'created_at' => $created_at]);
    return $result->first()->get('elementId(v)');
  });
  if ($result) return $result;
  return null;
}

function neo4j_merge_tractstack($client, $objectId, $objectName)
{
  if (!NEO4J_ENABLED) return null;
  $result = $client->writeTransaction(static function (TransactionInterface $tsx) use ($objectId, $objectName) {
    $result = $tsx->run(
      'MERGE (t:TractStack {object_id: $objectId, object_name: $objectName, object_type: "TractStack"}) return elementId(t)',
      ['objectId' => $objectId, 'objectName' => $objectName]
    );
    return $result->first()->get('elementId(t)');
  });
  if ($result) return $result;
  return null;
}

function neo4j_merge_storyfragment($client, $objectId, $objectName)
{
  if (!NEO4J_ENABLED) return null;
  $result = $client->writeTransaction(static function (TransactionInterface $tsx) use ($objectId, $objectName) {
    $result = $tsx->run(
      'MERGE (s:StoryFragment {object_id: $objectId, object_name: $objectName, object_type: "StoryFragment"}) return elementId(s)',
      ['objectId' => $objectId, 'objectName' => $objectName]
    );
    return $result->first()->get('elementId(s)');
  });
  if ($result) return $result;
  return null;
}

function neo4j_merge_corpus($client, $objectId, $objectName, $objectType)
{
  if (!NEO4J_ENABLED) return null;
  $result = $client->writeTransaction(static function (TransactionInterface $tsx) use ($objectId, $objectName, $objectType) {
    $result = $tsx->run(
      'MERGE (c:Corpus {object_id: $objectId, object_name: $objectName, object_type: $objectType}) return elementId(c)',
      ['objectId' => $objectId, 'objectName' => $objectName, 'objectType' => $objectType]
    );
    return $result->first()->get('elementId(c)');
  });
  if ($result) return $result;
  return null;
}

function neo4j_merge_belief($client, $beliefId)
{
  if (!NEO4J_ENABLED) return null;
  $result = $client->writeTransaction(static function (TransactionInterface $tsx) use ($beliefId) {
    $result = $tsx->run(
      'MERGE (b:Belief {belief_id: $beliefId}) return elementId(b)',
      ['beliefId' => $beliefId]
    );
    return $result->first()->get('elementId(b)');
  });
  if ($result) return $result;
  return null;
}

function neo4j_merge_menuitem($client, $menuItemId, $title)
{
  if (!NEO4J_ENABLED) return null;
  $result = $client->writeTransaction(static function (TransactionInterface $tsx) use ($menuItemId, $title) {
    $result = $tsx->run(
      'MERGE (i:MenuItem {menuitem_id: $menuItemId, title: $title}) return elementId(i)',
      ['menuItemId' => $menuItemId, 'title' => $title]
    );
    return $result->first()->get('elementId(i)');
  });
  if ($result) return $result;
  return null;
}

function neo4j_merge_impression($client, $impressionId, $title)
{
  if (!NEO4J_ENABLED) return null;
  $result = $client->writeTransaction(static function (TransactionInterface $tsx) use ($impressionId, $title) {
    $result = $tsx->run(
      'MERGE (i:Impression {impression_id: $impressionId, title: $title}) return elementId(i)',
      ['impressionId' => $impressionId, 'title' => $title]
    );
    return $result->first()->get('elementId(i)');
  });
  if ($result) return $result;
  return null;
}

function neo4j_merge_campaign($client, $utmCampaign)
{
  if (!NEO4J_ENABLED) return null;
  $result = $client->writeTransaction(static function (TransactionInterface $tsx) use ($utmCampaign) {
    $result = $tsx->run(
      'MERGE (c:Campaign {name: $utmCampaign}) return elementId(c)',
      ['utmCampaign' => $utmCampaign]
    );
    return $result->first()->get('elementId(c)');
  });
  if ($result) return $result;
  return null;
}

function neo4j_merge_corpus_campaign($client, $corpus_id,$campaign_id, $utmSource="", $utmMedium="",$utmTerm="", $utmContent="", $httpReferrer="")
{
  if (!NEO4J_ENABLED) return null;
  $result = $client->writeTransaction(static function (TransactionInterface $tsx) use ($corpus_id,$campaign_id, $utmSource, $utmMedium,$utmTerm, $utmContent,$httpReferrer) {
    $result = $tsx->run('MATCH (c1),(c) WHERE elementId(c1)=$corpus_id AND elementId(c)=$campaign_id
      MERGE (c)-[r:LINKED {utmSource:$utmSource, utmMedium:$utmMedium, 
      utmTerm:$utmTerm, utmContent:$utmContent, httpReferrer:$httpReferrer}]->(c1)
      ', ['corpus_id' => $corpus_id,
      'campaign_id' => $campaign_id,
      'utmSource' => $utmSource,
      'utmMedium' => $utmMedium,
      'utmTerm' => $utmTerm,
      'utmContent' => $utmContent,
      'httpReferrer' => $httpReferrer]
    );
    return true;
  });
  if ($result) return $result;
  return null;
}

function neo4j_merge_visit_campaign($client, $visit_id,$campaign_id, $utmSource="", $utmMedium="",$utmTerm="", $utmContent="", $httpReferrer="")
{
  if (!NEO4J_ENABLED) return null;
  $result = $client->writeTransaction(static function (TransactionInterface $tsx) use ($visit_id,$campaign_id, $utmSource, $utmMedium,$utmTerm, $utmContent,$httpReferrer) {
    $result = $tsx->run('MATCH (v),(c) WHERE elementId(v)=$visit_id AND elementId(c)=$campaign_id
      MERGE (c)-[r:LINKED {utmSource:$utmSource, utmMedium:$utmMedium, 
                           utmTerm:$utmTerm, utmContent:$utmContent, httpReferrer:$httpReferrer}]->(v) 
      ', ['visit_id' => $visit_id,
      'campaign_id' => $campaign_id,
      'utmSource' => $utmSource,
      'utmMedium' => $utmMedium,
      'utmTerm' => $utmTerm,
      'utmContent' => $utmContent,
      'httpReferrer' => $httpReferrer]
    );
    return true;
  });
  if ($result) return $result;
  return null;
}

function neo4j_fingerprint_has_visit($neo4j_fingerprint, $neo4j_visit)
{
  if (!NEO4J_ENABLED) return null;
  $statement =  Statement::create(
    'MATCH (f),(v) WHERE elementId(f)=$neo4j_fingerprint AND elementId(v)=$neo4j_visit MERGE (f)-[:HAS]->(v)',
    ['neo4j_fingerprint' => $neo4j_fingerprint, 'neo4j_visit' => $neo4j_visit]
  );
  return $statement;
}

function neo4j_lead_has_fingerprint($lead_id, $fingerprint_id)
{
  if (!NEO4J_ENABLED) return null;
  $statement =  Statement::create(
    'MATCH (l:Lead {lead_id:$lead_id}) MATCH (f:Fingerprint {fingerprint_id:$fingerprint_id}) MERGE (l)-[:HAS]->(f)',
    ['fingerprint_id' => strval($fingerprint_id), 'lead_id' => strval($lead_id)]
  );
  return $statement;
}

function neo4j_merge_belief_action($neo4j_fingerprint, $neo4j_belief, $verb, $object = NULL)
{
  if (!NEO4J_ENABLED) return null;
  if (!$object && in_array($verb, HELDBELIEFS, true)) {
    $verb = str_replace(' ', '_', $verb);
    $statement =  Statement::create(
      'MATCH (f),(b) WHERE elementId(f)=$neo4j_fingerprint AND elementId(b)=$neo4j_belief MERGE (f)-[:' . $verb . ']->(b)',
      ['neo4j_fingerprint' => $neo4j_fingerprint, 'neo4j_belief' => $neo4j_belief]
    );
    return $statement;
  } else if ($object) {
    // this is an IDENTIFY_AS belief, store "object" on relationship
    $verb = str_replace(' ', '_', $verb);
    $object = str_replace(' ', '_', $object);
    $statement =  Statement::create(
      'MATCH (f),(b) WHERE elementId(f)=$neo4j_fingerprint AND elementId(b)=$neo4j_belief MERGE (f)-[r:' . $verb . ']->(b) ON CREATE SET r.object=$object',
      ['neo4j_fingerprint' => $neo4j_fingerprint, 'neo4j_belief' => $neo4j_belief, 'object' => $object]
    );
    return $statement;
  } else {
    error_log('MISS ON ' . $verb);
  }
}

function neo4j_merge_belief_remove_action($neo4j_fingerprint, $neo4j_belief, $previous_verb, $object = null)
{
  if (!NEO4J_ENABLED) return null;
  if (in_array($previous_verb, HELDBELIEFS, true) || $object) {
    $statement =  Statement::create(
      'MATCH (f:Fingerprint)-[r:' . $previous_verb . ']->(b:Belief) WHERE elementId(f)=$neo4j_fingerprint AND elementId(b)=$neo4j_belief WITH r DELETE r',
      ['neo4j_fingerprint' => $neo4j_fingerprint, 'neo4j_belief' => $neo4j_belief]
    );
    return $statement;
  } else {
    error_log('MISS ON ' . $previous_verb);
  }
}

function neo4j_merge_menuitem_action($neo4j_visit, $neo4j_menuitem)
{
  if (!NEO4J_ENABLED) return null;
  $statement =  Statement::create(
    'MATCH (v),(i) WHERE elementId(v)=$neo4j_visit AND elementId(i)=$neo4j_menuitem MERGE (v)-[:CLICKED]->(i)',
    ['neo4j_visit' => $neo4j_visit, 'neo4j_menuitem' => $neo4j_menuitem]
  );
  return $statement;
}

function neo4j_merge_impression_action($neo4j_visit, $neo4j_impression)
{
  if (!NEO4J_ENABLED) return null;
  $statement =  Statement::create(
    'MATCH (v),(i) WHERE elementId(v)=$neo4j_visit AND elementId(i)=$neo4j_impression MERGE (v)-[:CLICKED]->(i)',
    ['neo4j_visit' => $neo4j_visit, 'neo4j_impression' => $neo4j_impression]
  );
  return $statement;
}

function neo4j_merge_action($neo4j_visit, $neo4j_corpus, $relationship, $score)
{
  if (!NEO4J_ENABLED) return null;
  $created_at = time();
  switch ($relationship) {
    case "CONNECTED":
      // uses first parameter as $neo4j_parent
      $statement =  Statement::create(
        'MATCH (c1),(c2) WHERE elementId(c1)=$neo4j_corpus AND elementId(c2)=$neo4j_parent MERGE (c1)-[:' . $relationship . ']->(c2)',
        ['neo4j_parent' => $neo4j_visit, 'neo4j_corpus' => $neo4j_corpus]
      );
      return $statement;
      break;

    case "ENTERED":
    case "DISCOVERED":
    case "CLICKED":
    case "READ":
    case "GLOSSED":
    case "INTERACTED":
    case "ATTEMPTED":
      $statement =  Statement::create(
        'MATCH (v),(c) WHERE elementId(v)=$neo4j_visit AND elementId(c)=$neo4j_corpus MERGE (v)-[:' . $relationship . ']->(c)',
        ['neo4j_visit' => $neo4j_visit, 'neo4j_corpus' => $neo4j_corpus]
      );
      return $statement;
      break;

    case "ANSWERED":
      $statement =  Statement::create(
        'MATCH (v),(c) WHERE elementId(v)=$neo4j_visit AND elementId(c)=$neo4j_corpus MERGE (v)-[:' . $relationship . ' {score:$score}]->(c)',
        ['neo4j_visit' => $neo4j_visit, 'neo4j_corpus' => $neo4j_corpus, 'created_at' => strval($created_at), 'score' => strval($score)]
      );
      return $statement;
      break;

    default:
      error_log('MISS ON ' . $relationship);
  }
}

function neo4j_visit_corpus_contains($neo4j_corpus, $neo4j_parent_id)
{
  if (!NEO4J_ENABLED) return null;
  $statement =  Statement::create(
    'MATCH (p),(c) WHERE elementId(p)=$neo4j_parent_id AND elementId(c)=$neo4j_corpus MERGE (p)-[:CONTAINS]->(c)',
    [
      'neo4j_parent_id' => $neo4j_parent_id,
      'neo4j_corpus' => $neo4j_corpus
    ]
  );
  return $statement;
}

function neo4j_tractStack_contains_storyFragment($neo4j_tractStack, $neo4j_storyFragment)
{
  if (!NEO4J_ENABLED) return null;
  $statement =  Statement::create(
    'MATCH (t),(s) WHERE elementId(t)=$neo4j_tractStack AND elementId(s)=$neo4j_storyFragment MERGE (t)-[:CONTAINS]->(s)',
    [
      'neo4j_tractStack' => $neo4j_tractStack,
      'neo4j_storyFragment' => $neo4j_storyFragment
    ]
  );
  return $statement;
}

function neo4j_tractStack_contains_belief($neo4j_tractStack, $neo4j_belief)
{
  if (!NEO4J_ENABLED) return null;
  $statement =  Statement::create(
    'MATCH (t),(b) WHERE elementId(t)=$neo4j_tractStack AND elementId(b)=$neo4j_belief MERGE (t)-[:CONTAINS]->(b)',
    [
      'neo4j_tractStack' => $neo4j_tractStack,
      'neo4j_belief' => $neo4j_belief
    ]
  );
  return $statement;
}

function neo4j_menuitem_links_storyFragment($neo4j_menuItem, $neo4j_storyFragment)
{
  if (!NEO4J_ENABLED) return null;
  $statement =  Statement::create(
    'MATCH (m),(s) WHERE elementId(m)=$neo4j_menuItem AND elementId(s)=$neo4j_storyFragment MERGE (m)-[:LINKS]->(s)',
    [
      'neo4j_menuItem' => $neo4j_menuItem,
      'neo4j_storyFragment' => $neo4j_storyFragment
    ]
  );
  return $statement;
}

function neo4j_corpus_contains_impression($neo4j_parent, $neo4j_impression)
{
  if (!NEO4J_ENABLED) return null;
  $statement =  Statement::create(
    'MATCH (c:Corpus),(i:Impression) WHERE elementId(c)=$neo4j_parent AND elementId(i)=$neo4j_impression MERGE (c)-[:CONTAINS]->(i)',
    [
      'neo4j_parent' => $neo4j_parent,
      'neo4j_impression' => $neo4j_impression
    ]
  );
  return $statement;
}

function neo4j_corpus_contains_corpus($neo4j_parent, $neo4j_corpus)
{
  if (!NEO4J_ENABLED) return null;
  $statement =  Statement::create(
    'MATCH (p:Corpus),(c:Corpus) WHERE elementId(p)=$neo4j_parent AND elementId(c)=$neo4j_corpus MERGE (p)-[:CONTAINS]->(c)',
    [
      'neo4j_parent' => $neo4j_parent,
      'neo4j_corpus' => $neo4j_corpus
    ]
  );
  return $statement;
}

function neo4j_storyFragment_contains_corpus($neo4j_storyFragment, $neo4j_corpus)
{
  if (!NEO4J_ENABLED) return null;
  $statement =  Statement::create(
    'MATCH (s:StoryFragment),(c:Corpus) WHERE elementId(s)=$neo4j_storyFragment AND elementId(c)=$neo4j_corpus MERGE (s)-[:CONTAINS]->(c)',
    [
      'neo4j_storyFragment' => $neo4j_storyFragment,
      'neo4j_corpus' => $neo4j_corpus
    ]
  );
  return $statement;
}

function neo4j_getUserGraph($client, $conn, $visit_id)
{
  if (!NEO4J_ENABLED) return null;
  $neo4j_visit = false;
  $visits_table_name = 'visits';
  $query = "SELECT merged FROM "
    . $visits_table_name . " WHERE id = :visit_id";
  $stmt = $conn->prepare($query);
  $stmt->bindParam(':visit_id', $visit_id);
  if ($stmt->execute()) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $neo4j_visit = isset($row['merged']) ? $row['merged'] : false;
  } else {
    die();
  }
  if ($neo4j_visit) {
    // get graph from neo4j
    $results = $client->writeTransaction(static function (TransactionInterface $tsx) use ($neo4j_visit) {
      $results = $tsx->run('MATCH (f:Fingerprint)-[cc]-(v:Visit)-[r]-(c) WHERE elementId(v)=$neo4j_visit OPTIONAL MATCH (b:Belief)-[bb]-(f) OPTIONAL MATCH (c)<-[a:CONTAINS]-(s)<-[d:CONTAINS]-(t) RETURN *', [
        'neo4j_visit' => $neo4j_visit
      ]);
      return $results;
    });
    return $results;
  }
  return null;
}

function neo4j_getGraph($client)
{
  if (!NEO4J_ENABLED) return null;
  $results = $client->writeTransaction(static function (TransactionInterface $tsx) {
    $results = $tsx->run(
      'MATCH (c:Corpus), (s:StoryFragment)' .
        ' OPTIONAL MATCH (s)-[rsf:CONTAINS]->(c) OPTIONAL MATCH (c)-[rc:CONNECTED]->(sf)' .
        ' OPTIONAL MATCH (c)-[rc:CONNECTED]->(c)' .
        ' RETURN *',
      []
    );
    return $results;
  });
  return $results;
}


function neo4j_gds_pageranks($client) {
  /*
   CALL gds.graph.project("pageRank",["Corpus","Visit","StoryFragment"],["CONNECTED","ENTERED","READ","GLOSSED","CLICKED","CONTAINS"]);
CALL gds.pageRank.write('pageRank', {
  maxIterations: 20,
  dampingFactor: .95,
  writeProperty: 'pageRank'
})
YIELD nodePropertiesWritten, ranIterations;
CALL gds.graph.drop("pageRank");
   */
}
