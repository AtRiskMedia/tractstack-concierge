<?php

/*
 * OLD DEPRECATED, for gatsby-tractstack-storykeep
 */

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
    " where DATE(created_at) >= CURDATE() - INTERVAL 28 DAY" .
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
    " where DATE(created_at) >= CURDATE() - INTERVAL 28 DAY" .
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

  $activity_query = "select count(distinct(id)) as uniqueSessions, count(distinct(utmSource)) as uniqueUtmSource," .
    " count(distinct(campaign_id)) as uniqueUtmCampaign, count(distinct(utmTerm)) as uniqueUtmTerm" .
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
  $front_settings = parse_ini_file(FRONT_ROOT.'.env');
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
  $front_settings = parse_ini_file(FRONT_ROOT.'.env');
  $oauth_public_key = file_get_contents(DRUPAL_OAUTH_ROOT.'public.key');
  $oauth_private_key = file_get_contents(DRUPAL_OAUTH_ROOT.'private.key');

  $frontend_keys = ["PUBLIC_CONCIERGE_BASE_URL","PUBLIC_CONCIERGE_STYLES_URL","PUBLIC_IMAGE_URL","DRUPAL_BASE_URL",
    "PRIVATE_SHOPIFY_STOREFRONT_ACCESS_TOKEN","PUBLIC_SHOPIFY_SHOP","PUBLIC_SHOPIFY_STOREFRONT_ACCESS_TOKEN","PUBLIC_SITE_URL","PUBLIC_STORYKEEP_URL",
    "PUBLIC_HOME","PUBLIC_READ_THRESHOLD","PUBLIC_SOFT_READ_THRESHOLD",
    "PUBLIC_IMPRESSIONS_DELAY","PUBLIC_SLOGAN","PUBLIC_FOOTER","PUBLIC_SOCIALS"];
  $storykeep_keys = ["BASIC_AUTH_USERNAME","BASIC_AUTH_PASSWORD","BUILDER_SECRET_KEY","CONCIERGE_BASE_URL_BACK","CONCIERGE_REFRESH_TOKEN_URL_BACK",
    "SHOPIFY_SHOP_PASSWORD_BACK","PUBLIC_SHOPIFY_SHOP","DRUPAL_URL_BACK","DRUPAL_APIBASE","DRUPAL_OAUTH_CLIENT_ID",
    "DRUPAL_OAUTH_CLIENT_SECRET","DRUPAL_OAUTH_GRANT_TYPE","DRUPAL_OAUTH_SCOPE","STORYKEEP_URL","OPENDEMO","MESSAGE_DELAY","HOMEPAGE","SLOGAN"];
  $concierge_keys = ["DB_HOST","DB_NAME","DB_USER","DB_PASSWORD","SECRET_KEY","BUILDER_SECRET_KEY","NEO4J_URI",
    "NEO4J_USER","NEO4J_SECRET","NEO4J_ENABLED","CONCIERGE_ROOT","FRONT_ROOT","STORYKEEP_ROOT","DRUPAL_OAUTH_ROOT","WATCH_ROOT"];
  $drupal_keys = ["OAUTH_PUBLIC_KEY","OAUTH_PRIVATE_KEY"];

  $frontend = '';
  $storykeep = '';
  $concierge = '';
  foreach ($payload as $key => $val) {
    if( in_array($key, $frontend_keys)) {
      $front_settings[$key] = $val;
    }
    if( in_array($key, $storykeep_keys)) {
      $storykeep_settings[$key] = $val;
    }
    if( in_array($key, $concierge_keys)) {
      $concierge_settings[$key] = $val;
    }
    if( in_array($key, $drupal_keys)) {
      if( $key === `OAUTH_PUBLIC_KEY` && $value !== $oauth_public_key ) {
        error_log($concierge_settings['DRUPAL_OAUTH_ROOT'].PHP_EOL);
        error_log($value.PHP_EOL);
      }
      if( $key === `OAUTH_PRIVATE_KEY` && $value !== $oauth_private_key ) {
        error_log($concierge_settings['DRUPAL_OAUTH_ROOT'].PHP_EOL);
        error_log($value.PHP_EOL);
      }
    }
  }
  file_put_contents($concierge_settings['FRONT_ROOT'].'.env',implode(PHP_EOL, prepareIniFile($front_settings)));
  file_put_contents($concierge_settings['STORYKEEP_ROOT'].'.env.production',implode(PHP_EOL, prepareIniFile($storykeep_settings)));
  file_put_contents($concierge_settings['CONCIERGE_ROOT'].'.env',implode(PHP_EOL, prepareIniFile($concierge_settings)));
  echo json_encode(array(
    "data" => json_encode(array(
      "updated" => true
    )),
    "message" => "Success.",
    "error" => null
  ));
  return (200);
}

function prepareIniFile($array)
{
        $data = array();
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $data[] = "[$key]";
                foreach ($val as $skey => $sval) {
                    if (is_array($sval)) {
                        foreach ($sval as $_skey => $_sval) {
                            if (is_numeric($_skey)) {
                                $data[] = $skey.'[]='.(is_numeric($_sval) ? $_sval : (ctype_upper($_sval) ? $_sval : '"'.$_sval.'"'));
                            } else {
                                $data[] = $skey.'['.$_skey.']='.(is_numeric($_sval) ? $_sval : (ctype_upper($_sval) ? $_sval : '"'.$_sval.'"'));
                            }
                        }
                    } else {
                        $data[] = $skey.'='.(is_numeric($sval) ? $sval : (ctype_upper($sval) || strpos($sval, ' ') !== false ? $sval : '"'.$sval.'"'));
                    }
                }
            }
            else if ($val == "1" ) $data[] = $key.'=true';
            else if ($val == "0" ) $data[] = $key.'=false';
            else if (str_contains($val,'!'))
                $data[] = $key.'="'.$val.'"';
            else {
              if( strpos($val,'|') > 0 ) $data[] = $key.'="'.$val.'"';
              else if( is_numeric($val)
                ||
              ctype_upper($val) || strpos($val, ' ') == false
                ) $data[] = $key.'='.$val;
              else $data[] = $key.'="'.$val.'"';
            }
        }
        $data[] = null;
        return $data;
}

function triggerPublish($data) {
  $concierge_settings = parse_ini_file(CONCIERGE_ROOT.'.env');
  if( !file_exists($concierge_settings['WATCH_ROOT'].'build.lock')) {
    //$locked = parse_ini_file($concierge_settings['WATCH_ROOT'].'build.lock');
    $target = $data->target;
    file_put_contents($concierge_settings['WATCH_ROOT'].'build.lock', $target);
    file_put_contents($concierge_settings['CONCIERGE_ROOT'].'api/styles/frontend/tailwind.whitelist', implode(PHP_EOL, $data->whitelist));
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
