<?php
require_once 'config.php';
$pdo->exec("UPDATE library_tables SET status = 'Available', current_user_id = NULL");
echo "Tables reset.";
?>
