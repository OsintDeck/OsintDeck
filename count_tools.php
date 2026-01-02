<?php
require_once dirname(dirname(dirname(__DIR__))) . '/wp-load.php';
use OsintDeck\Infrastructure\Persistence\ToolsTable;
if (class_exists('OsintDeck\Infrastructure\Persistence\ToolsTable')) {
    echo "Count: " . ToolsTable::count() . "\n";
}
