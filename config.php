<?php
session_start();

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5433';
$dbname = getenv('DB_DATABASE') ?: 'PLN_material';
$user = getenv('DB_USERNAME') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: 'Fatma24@';

try {
    $db = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("Database Connection Failed: " . $e->getMessage());
    die("Terjadi kesalahan koneksi database. Silakan hubungi administrator.");
}
?>