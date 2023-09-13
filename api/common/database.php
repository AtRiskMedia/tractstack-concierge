<?php

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

define('SECRET_KEY', $_ENV['SECRET_KEY']);
define('BUILDER_SECRET_KEY', $_ENV['BUILDER_SECRET_KEY']);
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASSWORD', $_ENV['DB_PASSWORD']);

class DatabaseService
{
  private $db_host = DB_HOST;
  private $db_name = DB_NAME;
  private $db_user = DB_USER;
  private $db_password = DB_PASSWORD;
  public $conn;
  public function getConnection()
  {
    $this->conn = null;
    try {
      $this->conn = new PDO("mysql:host=" . $this->db_host . ";dbname=" . $this->db_name, $this->db_user, $this->db_password);
    } catch (PDOException $exception) {
      echo "Connection failed: " . $exception->getMessage();
    }
    return $this->conn;
  }
}
