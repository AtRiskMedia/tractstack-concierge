<?php

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../../');
$dotenv->load();

define('CONCIERGE_ROOT', $_ENV['CONCIERGE_ROOT']);
define('FRONT_ROOT', $_ENV['FRONT_ROOT']);

function handleFrontendFilesUpload($files = []) {
  foreach ($files as $file) {
    $base64Image = $file['src'];
    $filename = $file['filename'];  // This will be 'logo', 'wordmark', etc.

    // Extract the mime type and handle svg+xml specially
    if (preg_match('/^data:(image|application)\/([^;]+);base64,/', $base64Image, $matches)) {
      $mimeGroup = $matches[1];
      $mimeType = strtolower($matches[2]);
      
      // Determine file extension
      $extension = match($mimeType) {
        'svg+xml' => 'svg',
        'vnd.microsoft.icon' => 'ico',
        'jpeg' => 'jpg',
        default => $mimeType
      };

      // Extract base64 data
      $data = substr($base64Image, strpos($base64Image, ',') + 1);
      $data = base64_decode($data);
      if ($data === false) {
        error_log("Failed to decode base64 data for {$filename}");
        return 500;
      }

      $subSavePath = 'custom/';
      $savePath = FRONT_ROOT.'public/'.$subSavePath;
      createDirectoryIfNotExists($savePath);
      $fullPath = $savePath . $filename . '.' . $extension;

      // Handle SVGs and ICO files directly - no processing needed
      if ($extension === 'svg' || $extension === 'ico') {
        if (file_put_contents($fullPath, $data) === false) {
          error_log("Failed to write {$extension} file: {$fullPath}");
          return 500;
        }
        continue;
      }

      // Process images with Imagick
      try {
        $imagick = new Imagick();
        $imagick->readImageBlob($data);

        // For OG image, maintain aspect ratio but ensure correct size
        if ($filename === 'og') {
          $imagick->resizeImage(1200, 630, Imagick::FILTER_LANCZOS, 1, true);
        }
        // For OG Logo, ensure it's square and at least 200x200
        else if ($filename === 'oglogo') {
          $size = max($imagick->getImageWidth(), $imagick->getImageHeight(), 200);
          $imagick->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1, true);
        }
        
        $imagick->setImageCompressionQuality(85);
        $imagick->writeImage($fullPath);
        $imagick->clear();
        $imagick->destroy();
      } catch (Exception $e) {
        error_log("Imagick error processing {$filename}: " . $e->getMessage());
        return 500;
      }
    } else {
      error_log("Invalid base64 image format for {$filename}");
      return 500;
    }
  }
  return 200;
}

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
    if (preg_match('/^data:image\/svg\+xml;base64,/', $base64Image)) {
      $data = substr($base64Image, strpos($base64Image, ',') + 1);
      $data = base64_decode($data);
      if ($data === false) {
        return(500);
      }
      $dateString = date("Y-m");
      $subSavePath = 'api/images/'.$dateString.'/';
      $savePath = CONCIERGE_ROOT.$subSavePath;
      createDirectoryIfNotExists($savePath);
      if (file_put_contents($savePath . $filename . '.svg', $data) === false) {
        return(500);
      } 
    } else if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $matches)) {
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

function updateCustomCss($brandColors) {
    $colors = explode(',', $brandColors);
    if (count($colors) !== 8) {
        throw new Exception('Invalid brand colors format');
    }

    // Build sed commands for each color
    $sedCommands = array_map(function($index, $color) {
        $color = ltrim($color);
        // Simpler pattern that just focuses on the brand color declaration
        // and anything that follows until the semicolon
        return "-e 's/--brand-" . ($index + 1) . ":#[0-9a-fA-F]\\{3,6\\}[^;]*;/--brand-" . ($index + 1) . ":#" . $color . ";/'";
    }, range(0, 7), $colors);

    // Combine into single sed command
    $sedCommand = "sed -i " . implode(' ', $sedCommands);
    
    return $sedCommand;
}

function updateBrandColors($brandColors, $frontRoot) {
    try {
        $cssPath = $frontRoot . 'public/styles/custom.css';
        
        // Verify file exists and is writable
        if (!file_exists($cssPath)) {
            throw new Exception("custom.css not found at: $cssPath");
        }
        if (!is_writable($cssPath)) {
            throw new Exception("custom.css is not writable at: $cssPath");
        }

        // Create backup of CSS file
        $backupPath = $cssPath . '.bak';
        if (!copy($cssPath, $backupPath)) {
            throw new Exception("Failed to create backup of custom.css");
        }

        // Get and execute sed command
        $sedCommand = updateCustomCss($brandColors);
        $sedCommand .= " " . escapeshellarg($cssPath);
        
        $output = array();
        $returnVar = 0;
        exec($sedCommand . " 2>&1", $output, $returnVar);

        if ($returnVar !== 0) {
            // Restore from backup if sed failed
            copy($backupPath, $cssPath);
            throw new Exception("Sed command failed: " . implode("\n", $output));
        }

        // Verify the changes
        $newContent = file_get_contents($cssPath);
        $colors = explode(',', $brandColors);
        foreach ($colors as $index => $color) {
            $color = ltrim($color);
            if (strpos($newContent, "--brand-" . ($index + 1) . ":#" . $color . ";") === false) {
                // Restore from backup if verification fails
                copy($backupPath, $cssPath);
                throw new Exception("Color verification failed for brand-" . ($index + 1));
            }
        }

        unlink($backupPath); // Remove backup if everything succeeded
        return true;
    } catch (Exception $e) {
        error_log("Failed to update custom.css: " . $e->getMessage());
        throw $e;
    }
}
