<?php
/**
 * config/database.php
 *
 * Reusable PDO database connection.
 * Include this file wherever database access is needed:
 *   require_once __DIR__ . '/../config/database.php';
 * The connection is then available as $pdo.
 */

// ---- Database configuration (matches database.sql) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'crime_reporting_system');
define('DB_USER', 'root');
define('DB_PASS', '');       // Default XAMPP MySQL password is empty
define('DB_CHARSET', 'utf8mb4');

// Define port explicitly if it is 3307
define('DB_PORT', '3307');

$dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
// $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw exceptions on error
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,          // return associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                     // use real prepared statements
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Never expose raw DB credentials/errors to the end user.
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection failed. Please try again later.');
}