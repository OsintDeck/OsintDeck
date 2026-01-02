<?php
require_once dirname(dirname(dirname(__DIR__))) . '/wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'osint_deck_tools';
echo "Dropping $table...\n";
$wpdb->query("DROP TABLE IF EXISTS $table");
echo "Dropped. Creating...\n";
use OsintDeck\Infrastructure\Persistence\ToolsTable;
ToolsTable::create_table();
echo "Created.\n";
