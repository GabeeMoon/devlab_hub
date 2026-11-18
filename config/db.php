<?php
// Configurações de conexão com o container mysql-local via IP da VM
define('DB_HOST', '10.13.13.115');
define('DB_PORT', '3306');
define('DB_NAME', 'meu_sistema');
define('DB_USER', 'dev_user');
define('DB_PASS', 'dev_password_123');

function getDbConnection() {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = array(
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 3
    );

    return new PDO($dsn, DB_USER, DB_PASS, $options);
}