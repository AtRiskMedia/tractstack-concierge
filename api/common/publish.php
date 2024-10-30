<?php

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../../');
$dotenv->load();

define('CONCIERGE_ROOT', $_ENV['CONCIERGE_ROOT']);
define('FRONT_ROOT', $_ENV['FRONT_ROOT']);

function handlePaneDesignUpload($files = []) {
  foreach ($files as $file) {
    $base64Image = $file['src'];
    $filename = removeExtension($file['filename']);
    if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $matches)) {
      $type = strtolower($matches[1]); // This will be 'jpeg', 'png', 'gif', etc.
      if (/*$originalExtension === 'jpg' && */ $type === 'jpeg') {
        $type = 'jpg';
      }
      $data = substr($base64Image, strpos($base64Image, ',') + 1);
      if (!in_array($type, ['webp','jpg', 'jpeg', 'gif', 'png'])) {
        return(500);
      }
      $data = base64_decode($data);
      if ($data === false) {
        return(500);
      }
      $imagick = new Imagick();
      $imagick->readImageBlob($data);
      $sizes = [800];
      $subSavePath = 'api/images/paneDesigns/';
      $savePath = CONCIERGE_ROOT.$subSavePath;
      createDirectoryIfNotExists($savePath);
      foreach ($sizes as $size) {
        $resized = clone $imagick;
        $resized->resizeImage($size, 0, Imagick::FILTER_LANCZOS, 1);
        $resized->setImageCompressionQuality(70);
        $resized->writeImage($savePath . $filename . "." . $type);
        $resized->clear();
        $resized->destroy();
      }
      $imagick->clear();
      $imagick->destroy();
    } else {
      return(500);
    }
  }
  return(200);
}

function handleFilesUpload($files = []) {
  foreach ($files as $file) {
    $base64Image = $file['src'];
    $filename = removeExtension($file['filename']);
    if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $matches)) {
      $type = strtolower($matches[1]); // This will be 'jpeg', 'png', 'gif', etc.
      if (/*$originalExtension === 'jpg' && */ $type === 'jpeg') {
        $type = 'jpg';
      }
      $data = substr($base64Image, strpos($base64Image, ',') + 1);
      if (!in_array($type, ['webp','jpg', 'jpeg', 'gif', 'png'])) {
        return(500);
      }
      $data = base64_decode($data);
      if ($data === false) {
        return(500);
      }
      $imagick = new Imagick();
      $imagick->readImageBlob($data);
      $sizes = [1920, 1080, 600];
      $dateString = date("Y-m");
      $subSavePath = 'api/images/'.$dateString.'/';
      $savePath = CONCIERGE_ROOT.$subSavePath;
      createDirectoryIfNotExists($savePath);
      foreach ($sizes as $size) {
        $resized = clone $imagick;
        $resized->resizeImage($size, 0, Imagick::FILTER_LANCZOS, 1);
        $resized->setImageCompressionQuality(70);
        $resized->writeImage($savePath . $filename . "_{$size}px.{$type}");
        $resized->clear();
        $resized->destroy();
      }
      $imagick->clear();
      $imagick->destroy();
    } else {
      return(500);
    }
  }
  return(200);
}

function handleTailwindWhitelist($classes = []) {
  file_put_contents(CONCIERGE_ROOT.'api/styles/frontend/tailwind.whitelist', implode(PHP_EOL, $classes));
  return(200);
}

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

function createDirectoryIfNotExists($path) {
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }
}

function removeExtension($filename) {
    $info = pathinfo($filename);
    if (isset($info['extension'])) {
        return substr($filename, 0, -(strlen($info['extension']) + 1));
    }
    return $filename;
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
    "PUBLIC_SITE_URL","PUBLIC_IMAGE_URL","PUBLIC_SOCIALS","PUBLIC_FOOTER","PUBLIC_HOME","PUBLIC_TRACTSTACK","PUBLIC_IMPRESSIONS_DELAY","PUBLIC_SLOGAN","PRIVATE_OPEN_DEMO",
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
  $status = json_decode(file_get_contents(CONCIERGE_ROOT.'api/build.json'), true);
  echo json_encode(array(
    "data" => json_encode(
      $status
    ),
    "message" => "Success.",
    "error" => null
  ));
  return (200);
}
