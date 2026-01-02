<?php
require_once dirname(dirname(dirname(__DIR__))) . '/wp-load.php';
use OsintDeck\Infrastructure\Persistence\ToolsTable;
ToolsTable::create_table();
echo "Table v2 created.\n";
