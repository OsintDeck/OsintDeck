<?php
require_once dirname(dirname(dirname(__DIR__))) . '/wp-load.php';

use OsintDeck\Infrastructure\Service\DataSeeder;
use OsintDeck\Infrastructure\Persistence\CustomTableToolRepository;
use OsintDeck\Infrastructure\Persistence\CustomTableCategoryRepository;

if ( class_exists('OsintDeck\Infrastructure\Service\DataSeeder') ) {
    $tool_repo = new CustomTableToolRepository();
    $cat_repo = new CustomTableCategoryRepository();
    $seeder = new DataSeeder($cat_repo, $tool_repo);

    echo "Seeding data...\n";
    $result = $seeder->seed_all();
    print_r($result);
} else {
    echo "DataSeeder class not found.\n";
}
