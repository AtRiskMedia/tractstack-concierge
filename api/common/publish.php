<?php

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../../');
$dotenv->load();

define('CONCIERGE_ROOT', $_ENV['CONCIERGE_ROOT']);
define('FRONT_ROOT', $_ENV['FRONT_ROOT']);

function handleFilesUpload($files = []) {
  foreach ($files as $file) {
    $base64Image = $file['src'];
    $filename = removeExtension($file['filename']);
    if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $matches)) {
      $type = strtolower($matches[1]); // This will be 'jpeg', 'png', 'gif', etc.
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
      $savePath = CONCIERGE_ROOT.'api/images/'.$dateString.'/';
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
    //$locked = parse_ini_file($concierge_settings['WATCH_ROOT'].'build.lock');
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
