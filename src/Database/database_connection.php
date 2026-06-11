<?php
declare(strict_types=1);

/*
    Database connection helper.

    This file creates one reusable MySQL connection.
    Pages can call getDatabaseConnection() when they need the database.
*/

function getDatabaseConnection(): mysqli
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    $settings = $GLOBALS['databaseSettings'] ?? require dirname(__DIR__, 2) . '/config/database.php';

    $host = (string)($settings['database_host'] ?? '127.0.0.1');
    $port = (int)($settings['database_port'] ?? 3306);
    $database = (string)($settings['database_name'] ?? '');
    $username = (string)($settings['database_username'] ?? 'root');
    $password = (string)($settings['database_password'] ?? '');
    $charset = (string)($settings['database_charset'] ?? 'utf8mb4');

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $connection = new mysqli($host, $username, $password, $database, $port);
        $connection->set_charset($charset);
    } catch (mysqli_sql_exception $exception) {
        $showDetailedErrors = (bool)($GLOBALS['environmentSettings']['show_detailed_errors'] ?? false);

        if ($showDetailedErrors) {
            throw $exception;
        }

        throw new RuntimeException('Database connection failed.');
    }

    return $connection;
}