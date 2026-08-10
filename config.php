<?php
session_start();

$host = 'localhost';
$port = '5433';
$dbname = 'PLN_material';
$user = 'postgres';
$password = 'Fatma24@';

try {
    $db = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}
?>