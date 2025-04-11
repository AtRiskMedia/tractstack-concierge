<?php

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../../');
$dotenv->load();

define('CONCIERGE_ROOT', $_ENV['CONCIERGE_ROOT']);
define('FRONT_ROOT', $_ENV['FRONT_ROOT']);
define('STORYKEEP_ROOT', $_ENV['STORYKEEP_ROOT']);

function handlePublish($target) {
  $concierge_settings = parse_ini_file(CONCIERGE_ROOT.'.env');
  if( !file_exists($concierge_settings['WATCH_ROOT'].'build.lock')) {
    file_put_contents($concierge_settings['WATCH_ROOT'].'build.lock', $target);
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

function getSettings()
{
  $concierge_settings = array(parse_ini_file(CONCIERGE_ROOT.'.env'));
  $front_settings = array(parse_ini_file(FRONT_ROOT.'.env'));
  echo json_encode(array(
    "data" => json_encode(
      array_merge(...[...$concierge_settings,
      ...$front_settings])
    ),
    "message" => "Success.",
    "error" => null
  ));
  return (200);
}

function postSettings($payload)
{
  $concierge_settings = parse_ini_file(CONCIERGE_ROOT.'.env');
  $front_settings = parse_ini_file(FRONT_ROOT.'.env');

  $frontend_keys = ["PRIVATE_SHOPIFY_STOREFRONT_ACCESS_TOKEN","PUBLIC_SHOPIFY_SHOP","PUBLIC_SHOPIFY_STOREFRONT_ACCESS_TOKEN","PRIVATE_CONCIERGE_BASE_URL","PUBLIC_CONCIERGE_STYLES_URL",
    "PUBLIC_SITE_URL","PUBLIC_IMAGE_URL","PUBLIC_SOCIALS","PUBLIC_FOOTER","PUBLIC_HOME","PUBLIC_TRACTSTACK","PUBLIC_IMPRESSIONS_DELAY","PUBLIC_SLOGAN","PRIVATE_OPEN_DEMO","PUBLIC_BRAND","PUBLIC_LOGO","PUBLIC_WORDMARK","PUBLIC_OG","PUBLIC_OGLOGO","PUBLIC_FAVICON","PUBLIC_OGTITLE","PUBLIC_OGAUTHOR","PUBLIC_OGDESC",
    "PUBLIC_GOOGLE_SITE_VERIFICATION",
    "PUBLIC_DISABLE_FAST_TRAVEL","PUBLIC_USE_CUSTOM_FONTS","PUBLIC_THEME","TURSO_DATABASE_URL","TURSO_AUTH_TOKEN","HEADER_WIDGET_RESOURCE_CATEGORY","ENABLE_HEADER_WIDGET",
    "PRIVATE_CONCIERGE_SECRET","PRIVATE_AUTH_SECRET","PRIVATE_ASSEMBLYAI_API_KEY"];
  $concierge_keys = ["CONCIERGE_ROOT","FRONT_ROOT","WATCH_ROOT","DB_HOST","DB_NAME",
    "DB_USER","NEO4J_URI","NEO4J_USER","NEO4J_SECRET","NEO4J_ENABLED","DB_PASSWORD","CONCIERGE_SECRET","SECRET_KEY"];

  $frontend = '';
  $storykeep = '';
  $concierge = '';
  foreach ($payload as $key => $val) {
    if( in_array($key, $frontend_keys)) {
      $front_settings[$key] = $val;
      if ($key === 'PUBLIC_BRAND') {
        try {
          updateBrandColors($val, $concierge_settings['FRONT_ROOT']);
        } catch (Exception $e) {
          $cssSuccess = false;
          $cssError = $e->getMessage();
        }
      }
    }
    if( in_array($key, $concierge_keys)) {
      $concierge_settings[$key] = $val;
    }
  }
  file_put_contents($concierge_settings['FRONT_ROOT'].'.env',implode(PHP_EOL, prepareIniFile($front_settings)));
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

function getStatus()
{
    // Initialize the combined data array
    $combinedData = [];

    // Read and decode build.json
    $buildFile = CONCIERGE_ROOT . 'api/build.json';
    if (file_exists($buildFile)) {
        $buildStatus = json_decode(file_get_contents($buildFile), true);
        if (is_array($buildStatus)) {
            $combinedData = array_merge($combinedData, $buildStatus);
        }
    }

    // Read and decode concierge.json
    $conciergeFile = CONCIERGE_ROOT . 'concierge.json';
    if (file_exists($conciergeFile)) {
        $conciergeData = json_decode(file_get_contents($conciergeFile), true);
        if (is_array($conciergeData)) {
            $combinedData = array_merge($combinedData, $conciergeData);
        }
    }

    // Read and decode storykeep.json
    $storykeepFile = STORYKEEP_ROOT . 'storykeep.json';
    if (file_exists($storykeepFile)) {
        $storykeepData = json_decode(file_get_contents($storykeepFile), true);
        if (is_array($storykeepData)) {
            $combinedData = array_merge($combinedData, $storykeepData);
        }
    }

    // Prepare the response
    $response = [
        "data" => $combinedData,
        "message" => "Success.",
        "error" => null
    ];

    echo json_encode($response);
    return 200;
}
