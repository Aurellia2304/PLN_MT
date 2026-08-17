<?php
require_once 'config.php';
$stmt = $db->query("SELECT name FROM materials ORDER BY name ASC");
$materials = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($materials as $m) {
    echo $m . "\n";
}
