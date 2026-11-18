<?php

class Database
{
    private static $instance = null;

    public static function connection()
    {
        if (self::$instance === null) {
            require_once __DIR__ . '/../../config/db.php';
            self::$instance = getDbConnection();
        }
        return self::$instance;
    }
}