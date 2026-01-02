<?php
require_once dirname(dirname(dirname(__DIR__))) . '/wp-load.php';
global $wpdb;
$table_name = $wpdb->prefix . 'osint_deck_tools';
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
foreach ($columns as $col) {
    echo $col->Field . "\n";
}
