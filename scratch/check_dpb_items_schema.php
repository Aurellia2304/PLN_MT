<?php
require_once 'config.php';
$stmt = $db->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'dpb_items'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
