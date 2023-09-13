<?php

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

define('SECRET_KEY', $_ENV['SECRET_KEY']);
define('BUILDER_SECRET_KEY', $_ENV['BUILDER_SECRET_KEY']);
