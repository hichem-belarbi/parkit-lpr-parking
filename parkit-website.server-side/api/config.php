<?php
// api/config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'park_reservation_db');
define('DB_USER', 'root');
define('DB_PASS', '');
 
// Clé secrète pour sécuriser access_checker.php (appelé par le Raspberry Pi)
define('API_KEY', '4d189edd691f8661adeecb42b86ebebfe57e4c7c376d66c0340a42b6dd368400');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}