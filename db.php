<?php

function db_connect(){
    $host = getenv('DB_HOST');
    $db = getenv('DB_NAME'); 
    $user = getenv('DB_USER'); 
    $pass = getenv('DB_PASSWORD'); 
    $charset = 'utf8mb4';
    
    // Data Source Name for the PDO connection
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    // PDO connection options
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    return new PDO($dsn, $user, $pass, $options);
}