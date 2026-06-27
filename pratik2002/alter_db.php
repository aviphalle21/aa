<?php
require_once 'config.php';
try {
    $pdo->exec("ALTER TABLE system_notifications ADD COLUMN related_id INT NULL");
    echo "Column added successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
