<?php
echo "mysqlnd enabled: " . (function_exists('mysqli_stmt_get_result') ? 'YES' : 'NO') . "\n";
echo "PHP version: " . PHP_VERSION . "\n";
?>
