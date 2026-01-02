<?php
require_once dirname(dirname(dirname(__DIR__))) . '/wp-load.php';
use OsintDeck\Infrastructure\Persistence\ToolsTable;
global $wpdb;
echo "Prefix: " . $wpdb->prefix . "\n";
if (class_exists('OsintDeck\Infrastructure\Persistence\ToolsTable')) {
    echo "Table Name: " . ToolsTable::get_table_name() . "\n";
}
