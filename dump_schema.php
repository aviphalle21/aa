<?php
require_once 'User/config.php';

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$sql = "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    $sql .= "DROP TABLE IF EXISTS `$table`;\n";
    $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
    $sql .= $create['Create Table'] . ";\n\n";
}

// Admin insert
$hash = password_hash('admin1234', PASSWORD_DEFAULT);
$sql .= "INSERT INTO `admin` (`name`, `email`, `username`, `password`, `created_at`) VALUES ('Suhas Vibhute', 'admin@gmail.com', 'admin', '$hash', NOW());\n";

file_put_contents('schema.sql', $sql);
echo "Dumped to schema.sql";
?>
