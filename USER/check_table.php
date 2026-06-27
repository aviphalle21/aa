<?php
require_once 'config.php';
$stmt = $pdo->query("SELECT * FROM library_tables WHERE table_number = 2");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
?>
