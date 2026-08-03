<?php
session_start();

$host = 'localhost';
$port = '5433';
$dbname = 'pln_material';
$user = 'postgres';
$password = 'Politeknik01_';

try {
    $db = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}
?>