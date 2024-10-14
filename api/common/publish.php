<?php

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../../');
$dotenv->load();

define('CONCIERGE_ROOT', $_ENV['CONCIERGE_ROOT']);
define('FRONT_ROOT', $_ENV['FRONT_ROOT']);

function handleFilesUpload($files = []) {
  error_log(json_encode($files));
}

function handleTailwindWhitelist($classes = []) {
  error_log(json_encode($classes));
}

function handlePublish($target) {
  error_log($target);
}

