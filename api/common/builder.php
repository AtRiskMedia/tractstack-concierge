<?php

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../../');
$dotenv->load();

define('CONCIERGE_ROOT', $_ENV['CONCIERGE_ROOT']);
define('FRONT_ROOT', $_ENV['FRONT_ROOT']);
define('STORYKEEP_ROOT', $_ENV['STORYKEEP_ROOT']);
define('DRUPAL_OAUTH_ROOT', $_ENV['DRUPAL_OAUTH_ROOT']);

function getPaneDetailsPie($storyFragmentId)
{
  $databaseService = new DatabaseService();
  $conn = $databaseService->getConnection();
  $actions_table_name = 'actions';
  $corpus_table_name = 'corpus';

  // initial SQL lookup
  $activity_query = "SELECT p.object_id as paneId, sum((case when a.verb = 'CLICKED' then 1 else 0 end)) as clicked,"
    . " sum((case when a.verb = 'READ' then 1 else 0 end)) as red,"
    . " sum((case when a.verb = 'GLOSSED' then 1 else 0 end)) as glossed"
    . " from " . $actions_table_name . " as a"
    . " left join " . $corpus_table_name . " as p on a.object_id=p.id"
    . " left join " . $corpus_table_name . " as sf on a.parent_id=sf.id"
    . " where sf.object_id=:storyFragmentId"
    . " group by paneId;";
  $activity_stmt = $conn->prepare($activity_query);
  $activity_stmt->bindParam(':storyFragmentId', $storyFragmentId);
  if ($activity_stmt->execute()) {
    $rows = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(array(
      "data" => json_encode($rows),
      "message" => "Success.",
      "error" => null
    ));
    return (200);
  } else {
    return (500);
  }
  echo json_encode(array(
    "data" => null,
    "message" => "Success.",
    "error" => null
  ));
  return (200);
}

function getPaneActivitySwarm()
{
  $databaseService = new DatabaseService();
  $conn = $databaseService->getConnection();
  $actions_table_name = 'actions';
  $corpus_table_name = 'corpus';

  // initial SQL lookup
  $activity_query = "select p.object_id as paneId, p.object_name as title," .
    " timestampdiff(hour, max(a.created_at),now()) as hours_since_activity," .
    " sum((case when a.verb='CLICKED' and a.object_id=p.id then 1 else 0 end)) as clicked," .
    " sum((case when a.verb='READ' and a.object_id=p.id then 1 else 0 end)) as red," .
    " sum((case when a.verb='GLOSSED' and a.object_id=p.id then 1 else 0 end)) as glossed" .
    " from " . $actions_table_name . " as a" .
    " left join " . $corpus_table_name . " as p on a.object_id=p.id" .
    " where p.object_type='Pane'" .
    " group by paneId;";
  $activity_stmt = $conn->prepare($activity_query);
  if ($activity_stmt->execute()) {
    $rows = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(array(
      "data" => json_encode($rows),
      "message" => "Success.",
      "error" => null
    ));
    return (200);
  } else {
    return (500);
  }
  echo json_encode(array(
    "data" => null,
    "message" => "Success.",
    "error" => null
  ));
  return (200);
}


function getStoryFragmentActivitySwarm()
{
  $databaseService = new DatabaseService();
  $conn = $databaseService->getConnection();
  $actions_table_name = 'actions';
  $corpus_table_name = 'corpus';

  // initial SQL lookup
  $activity_query = "select sf.object_id as storyFragmentId, sf.object_name as title," .
    " timestampdiff(hour, max(a.created_at),now()) as hours_since_activity," .
    " sum((case when a.verb='CLICKED' and a.object_id=p.id then 1 else 0 end)) as clicked," .
    " sum((case when a.verb='READ' and a.object_id=p.id then 1 else 0 end)) as red," .
    " sum((case when a.verb='GLOSSED' and a.object_id=p.id then 1 else 0 end)) as glossed," .
    " sum((case when a.verb='ENTERED' and a.object_id=sf.id then 1 else 0 end)) as entered," .
    " sum((case when a.verb='CONNECTED' and a.parent_id=sf.id then 1 else 0 end)) as discovered" .
    " from " . $actions_table_name . " as a" .
    " left join " . $corpus_table_name . " as sf on a.parent_id=sf.id left join " . $corpus_table_name . " as p on a.object_id=p.id" .
    " where sf.object_type='StoryFragment' group by storyFragmentId;";
  $activity_stmt = $conn->prepare($activity_query);
  if ($activity_stmt->execute()) {
    $rows = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(array(
      "data" => json_encode($rows),
      "message" => "Success.",
      "error" => null
    ));
    return (200);
  } else {
    return (500);
  }
  echo json_encode(array(
    "data" => null,
    "message" => "Success.",
    "error" => null
  ));
  return (200);
}

function getStoryFragmentDaysSince()
{
  $databaseService = new DatabaseService();
  $conn = $databaseService->getConnection();
  $actions_table_name = 'actions';
  $corpus_table_name = 'corpus';

  // initial SQL lookup
  $activity_query = "select sf.object_id as storyFragmentId, sf.object_name as title," .
    " timestampdiff(hour, max(a.created_at),now()) as hours_since_activity," .
    " sum((case when a.verb='CLICKED' and a.parent_id=sf.id then 1 else 0 end)) as clicked," .
    " sum((case when a.verb='READ' and a.parent_id=sf.id then 1 else 0 end)) as red," .
    " sum((case when a.verb='GLOSSED' and a.parent_id=sf.id then 1 else 0 end)) as glossed," .
    " sum((case when a.verb='ENTERED' and a.object_id=sf.id then 1 else 0 end)) as entered," .
    " sum((case when a.verb='CONNECTED' and a.parent_id=sf.id then 1 else 0 end)) as discovered" .
    " from " . $actions_table_name . " as a" .
    " left join " . $corpus_table_name . " as sf on a.parent_id=sf.id" .
    " group by storyFragmentId;";
  $activity_stmt = $conn->prepare($activity_query);
  if ($activity_stmt->execute()) {
    $rows = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(array(
      "data" => json_encode($rows),
      "message" => "Success.",
      "error" => null
    ));
    return (200);
  } else {
    return (500);
  }
  echo json_encode(array(
    "data" => null,
    "message" => "Success.",
    "error" => null
  ));
  return (200);
}


function getPanesDaysSince()
{
  $databaseService = new DatabaseService();
  $conn = $databaseService->getConnection();
  $actions_table_name = 'actions';
  $corpus_table_name = 'corpus';

  // initial SQL lookup
  $activity_query = "select p.object_id as paneId, p.object_name as title," .
    " timestampdiff(hour, max(a.created_at),now()) as hours_since_activity," .
    " sum((case when a.verb='CLICKED' and a.object_id=p.id then 1 else 0 end)) as clicked," .
    " sum((case when a.verb='READ' and a.object_id=p.id then 1 else 0 end)) as red," .
    " sum((case when a.verb='GLOSSED' and a.object_id=p.id then 1 else 0 end)) as glossed" .
    " from " . $actions_table_name . " as a" .
    " left join " . $corpus_table_name . " as p on a.object_id=p.id" .
    " group by paneId;";
  $activity_stmt = $conn->prepare($activity_query);
  if ($activity_stmt->execute()) {
    $rows = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(array(
      "data" => json_encode($rows),
      "message" => "Success.",
      "error" => null
    ));
    return (200);
  } else {
    return (500);
  }
  echo json_encode(array(
    "data" => null,
    "message" => "Success.",
    "error" => null
  ));
  return (200);
}

function getRecentDailyActivity()
{
  $databaseService = new DatabaseService();
  $conn = $databaseService->getConnection();
  $actions_table_name = 'actions';

  // initial SQL lookup
  $activity_query = "select datediff(now(),created_at) as daysSince," .
    " sum((case when verb='ENTERED' then 1 else 0 end)) as entered," .
    " sum((case when verb='GLOSSED' then 1 else 0 end)) as glossed," .
    " sum((case when verb='READ' then 1 else 0 end)) as red," .
    " sum((case when verb='CLICKED' then 1 else 0 end)) as clicked," .
    " sum((case when verb='CONNECTED' then 1 else 0 end)) as discovered" .
    " from " . $actions_table_name .
    " where DATE(created_at) >= CURDATE() - INTERVAL 14 DAY" .
    " group by DATE(created_at);";
  $activity_stmt = $conn->prepare($activity_query);

  if ($activity_stmt->execute()) {
    $rows = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(array(
      "data" => json_encode($rows),
      "message" => "Success.",
      "error" => null
    ));
    return (200);
  } else {
    return (500);
  }
  echo json_encode(array(
    "data" => null,
    "message" => "Success.",
    "error" => null
  ));
  return (200);
}

function getDashboardPayloads()
{

  $databaseService = new DatabaseService();
  $conn = $databaseService->getConnection();
  $actions_table_name = 'actions';
  $corpus_table_name = 'corpus';

  $activity_query = "select datediff(now(),created_at) as daysSince," .
    " sum((case when verb='ENTERED' then 1 else 0 end)) as entered," .
    " sum((case when verb='GLOSSED' then 1 else 0 end)) as glossed," .
    " sum((case when verb='READ' then 1 else 0 end)) as red," .
    " sum((case when verb='CLICKED' then 1 else 0 end)) as clicked," .
    " sum((case when verb='CONNECTED' then 1 else 0 end)) as discovered" .
    " from " . $actions_table_name .
    " where DATE(created_at) >= CURDATE() - INTERVAL 14 DAY" .
    " group by DATE(created_at);";
  $activity_stmt = $conn->prepare($activity_query);
  if ($activity_stmt->execute()) {
    $recentDailyActivity = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    return (500);
  }

  $activity_query = "select sf.object_id as storyFragmentId, sf.object_name as title," .
    " timestampdiff(hour, max(a.created_at),now()) as hours_since_activity," .
    " sum((case when a.verb='CLICKED' and a.object_id=p.id then 1 else 0 end)) as clicked," .
    " sum((case when a.verb='READ' and a.object_id=p.id then 1 else 0 end)) as red," .
    " sum((case when a.verb='GLOSSED' and a.object_id=p.id then 1 else 0 end)) as glossed," .
    " sum((case when a.verb='ENTERED' and a.object_id=sf.id then 1 else 0 end)) as entered," .
    " sum((case when a.verb='CONNECTED' and a.parent_id=sf.id then 1 else 0 end)) as discovered" .
    " from " . $actions_table_name . " as a" .
    " left join " . $corpus_table_name . " as sf on a.parent_id=sf.id left join " . $corpus_table_name . " as p on a.object_id=p.id" .
    " where sf.object_type='StoryFragment' group by storyFragmentId;";
  $activity_stmt = $conn->prepare($activity_query);
  if ($activity_stmt->execute()) {
    $storyFragmentActivitySwarm = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    return (500);
  }

  $activity_query = "select p.object_id as paneId, p.object_name as title," .
    " timestampdiff(hour, max(a.created_at),now()) as hours_since_activity," .
    " sum((case when a.verb='CLICKED' and a.object_id=p.id then 1 else 0 end)) as clicked," .
    " sum((case when a.verb='READ' and a.object_id=p.id then 1 else 0 end)) as red," .
    " sum((case when a.verb='GLOSSED' and a.object_id=p.id then 1 else 0 end)) as glossed" .
    " from " . $actions_table_name . " as a" .
    " left join " . $corpus_table_name . " as p on a.object_id=p.id" .
    " where p.object_type='Pane'" .
    " group by paneId;";
  $activity_stmt = $conn->prepare($activity_query);
  if ($activity_stmt->execute()) {
    $paneActivitySwarm = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    return (500);
  }

  $activity_query = "select count(distinct(id)) as uniqueSessions, count(distinct(utmSource))-1 as uniqueUtmSource," .
    " count(distinct(campaign_id))-1 as uniqueUtmCampaign, count(distinct(utmTerm))-1 as uniqueUtmTerm" .
    " from visits WHERE DATE(updated_at) >= CURDATE() - INTERVAL 7 DAY;";
  $activity_stmt = $conn->prepare($activity_query);
  if ($activity_stmt->execute()) {
    $recentMetrics = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    return (500);
  }

  echo json_encode(array(
    "data" => json_encode(array(
      "recentDailyActivity" => $recentDailyActivity,
      "storyFragmentActivitySwarm" => $storyFragmentActivitySwarm,
      "paneActivitySwarm" => $paneActivitySwarm,
      "recentMetrics" => $recentMetrics
    )),
    "message" => "Success.",
    "error" => null
  ));
  return (200);
}

function getSettings()
{
  $concierge_settings = parse_ini_file(CONCIERGE_ROOT.'.env');
  $storykeep_settings = parse_ini_file(STORYKEEP_ROOT.'.env.production');
  $front_settings = parse_ini_file(FRONT_ROOT.'.env.production');
  $oauth_public_key = file_get_contents(DRUPAL_OAUTH_ROOT.'public.key');
  $oauth_private_key = file_get_contents(DRUPAL_OAUTH_ROOT.'private.key');

  echo json_encode(array(
    "data" => json_encode(array(
      "concierge" => $concierge_settings,
      "storykeep" => $storykeep_settings,
      "frontend" => $front_settings,
      "oauth_public_key" => $oauth_public_key,
      "oauth_private_key" => $oauth_private_key
    )),
    "message" => "Success.",
    "error" => null
  ));
  return (200);
}

function postSettings($payload)
{
  $concierge_settings = parse_ini_file(CONCIERGE_ROOT.'.env');
  $storykeep_settings = parse_ini_file(STORYKEEP_ROOT.'.env.production');
  $front_settings = parse_ini_file(FRONT_ROOT.'.env.production');
  $oauth_public_key = file_get_contents(DRUPAL_OAUTH_ROOT.'public.key');
  $oauth_private_key = file_get_contents(DRUPAL_OAUTH_ROOT.'private.key');

  $frontend_keys = ["BASIC_AUTH_USERNAME","BASIC_AUTH_PASSWORD","CONCIERGE_BASE_URL_FRONT","CONCIERGE_REFRESH_TOKEN_URL_FRONT",
    "SHOPIFY_SHOP_PASSWORD_FRONT","GATSBY_SHOPIFY_STORE_URL","GATSBY_STOREFRONT_ACCESS_TOKEN","DRUPAL_URL_FRONT","SITE_URL",
    "STORYKEEP_URL","HOMEPAGE","READ_THRESHOLD","SOFT_READ_THRESHOLD","CONCIERGE_SYNC","CONCIERGE_FORCE_INTERVAL",
    "IMPRESSIONS_DELAY","SLOGAN","FOOTER","ACTION","LOCAL_STORAGE_KEY","SOCIAL","INITIALIZE_SHOPIFY"];
  $storykeep_keys = ["BASIC_AUTH_USERNAME","BASIC_AUTH_PASSWORD","BUILDER_SECRET_KEY","CONCIERGE_BASE_URL_BACK","CONCIERGE_REFRESH_TOKEN_URL_BACK",
    "SHOPIFY_SHOP_PASSWORD_BACK","GATSBY_SHOPIFY_STORE_URL","DRUPAL_URL_BACK","DRUPAL_APIBASE","DRUPAL_OAUTH_CLIENT_ID",
    "DRUPAL_OAUTH_CLIENT_SECRET","DRUPAL_OAUTH_GRANT_TYPE","DRUPAL_OAUTH_SCOPE","STORYKEEP_URL","OPENDEMO","MESSAGE_DELAY","HOMEPAGE"];
  $concierge_keys = ["DB_HOST","DB_NAME","DB_USER","DB_PASSWORD","SECRET_KEY","BUILDER_SECRET_KEY","NEO4J_URI",
    "NEO4J_USER","NEO4J_SECRET","NEO4J_ENABLED","CONCIERGE_ROOT","FRONT_ROOT","STORYKEEP_ROOT","DRUPAL_OAUTH_ROOT","WATCH_ROOT"];
  $drupal_keys = ["OAUTH_PUBLIC_KEY","OAUTH_PRIVATE_KEY"];

  $frontend = '';
  $storykeep = '';
  $concierge = '';
  foreach ($payload as $key => $val) {
    if( gettype($val) === 'boolean' && $val) $value = "1";
    else if( gettype($val) === 'boolean' && !$val) $value = "0";
    else $value = $val;
    if( in_array($key, $frontend_keys)) {
      $frontend .= $key.'='.$value.PHP_EOL;
      //if( $value === $front_settings[$key] ) error_log('*');
      //else
      //error_log('key: '.$key.'   frontend value:'.$value.'  now:'.$front_settings[$key].'  ');
    }
    if( in_array($key, $storykeep_keys)) {
      $storykeep .= $key.'='.$value.PHP_EOL;
      //if( $value === $storykeep_settings[$key] ) error_log('*');
      //else
      //error_log('key: '.$key.'   storykeep value:'.$value.'  now:'.$storykeep_settings[$key].'  ');
    }
    if( in_array($key, $concierge_keys)) {
      $concierge .= $key.'='.$value.PHP_EOL;
      //if( $value === $concierge_settings[$key] ) error_log('*');
      //else
      //error_log('key: '.$key.'   concierge value:'.$value.'  now:'.$concierge_settings[$key].'  ');
    }
    if( in_array($key, $drupal_keys)) {
      if( $key === `OAUTH_PUBLIC_KEY` && $value !== $oauth_public_key ) {
        error_log('update key: '.$key.'   drupal value:'.$value.'  now:'.$oauth_public_key.'  ');
      }
      if( $key === `OAUTH_PRIVATE_KEY` && $value !== $oauth_private_key ) {
        error_log('update key: '.$key.'   drupal value:'.$value.'  now:'.$oauth_private_key.'  ');
      }
    }
  }
  error_log($frontend);
  error_log($storykeep);
  error_log($concierge);
  file_put_contents('/home/tractstack/tmp/frontend.txt',$frontend);
  file_put_contents('/home/tractstack/tmp/storykeep.txt',$storykeep);
  file_put_contents('/home/tractstack/tmp/concierge.txt',$concierge);
  echo json_encode(array(
    "data" => json_encode(array(
      "updated" => true
    )),
    "message" => "Success.",
    "error" => null
  ));
  return (200);
}

function triggerPublish($data) {
  $concierge_settings = parse_ini_file(CONCIERGE_ROOT.'.env');
  if( !file_exists($concierge_settings['WATCH_ROOT'].'build.lock')) {
    //$locked = parse_ini_file($concierge_settings['WATCH_ROOT'].'build.lock');
    $target = $data->target;
    file_put_contents($concierge_settings['WATCH_ROOT'].'build.lock', $target);
    file_put_contents($concierge_settings['FRONT_ROOT'].'tailwind.whitelist', implode(PHP_EOL, $data->whitelist));
    echo json_encode(array(
      "data" => json_encode(array(
       "build" => true
      )),
      "message" => "Success.",
      "error" => null
    ));
  }
  else {
    echo json_encode(array(
      "data" => json_encode(array(
       "build" => false,
       "locked" => true
      )),
      "message" => "Locked.",
      "error" => null
    ));
  }
  return(200);
}
