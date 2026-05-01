<?php
$host     = getenv('DB_HOST')     ?: 'localhost';
$dbname   = getenv('DB_NAME')     ?: 'petlove_club';
$user     = getenv('DB_USER')     ?: 'root';
$password = getenv('DB_PASSWORD');
if ($password === false) $password = '';
$port     = getenv('DB_PORT')     ?: '3306';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Pin DB session timezone to PHP's default so NOW() / DATE_ADD output
    // matches what strtotime() parses on the way back. Without this, the DB
    // returns UTC strings that PHP then re-interprets as local time.
    $pdo->exec("SET time_zone = '" . date('P') . "'");
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}
