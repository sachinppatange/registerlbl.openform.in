<?php
// Fill your DB credentials
define('DB_HOST',     'localhost');   // e.g., 127.0.0.1
define('DB_NAME',     'registerlblnew');       // database name you created
define('DB_USER',     'root');     // db username
define('DB_PASSWORD', ''); // db password
define('DB_CHARSET',  'utf8mb4');


/*
define('DB_HOST',     '127.0.0.1');   // e.g., 127.0.0.1
define('DB_NAME',     'u750208840_registerlbldb');       // database name you created
define('DB_USER',     'u750208840_registerlbluse');     // db username
define('DB_PASSWORD', 'Latur@413512#'); // db password
define('DB_CHARSET',  'utf8mb4');
*/



function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
    return $pdo;
}