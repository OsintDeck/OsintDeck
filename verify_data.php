<?php
$tool_repo = new \OsintDeck\Infrastructure\Persistence\ToolRepository();
$cat_repo = new \OsintDeck\Infrastructure\Persistence\CategoryRepository();

$tools = $tool_repo->get_all_tools();
$cats = $cat_repo->get_all_categories();

echo "Total Tools: " . count($tools) . "\n";
echo "Total Categories: " . count($cats) . "\n";

if (count($tools) > 0) {
    $t = $tools[0];
    echo "First Tool: " . ($t['name'] ?? 'No Name') . "\n";
    echo "Category Code: " . ($t['category_code'] ?? 'NULL') . "\n";
    
    // Check if category exists in cats
    $code = $t['category_code'] ?? '';
    $found = false;
    foreach ($cats as $c) {
        if (($c['code'] ?? '') === $code) {
            echo "MATCH FOUND: " . ($c['label'] ?? '') . "\n";
            $found = true;
            break;
        }
    }
    if (!$found && $code) {
        echo "WARNING: Category code '$code' not found in categories list.\n";
    }
}

echo "First 5 Categories:\n";
foreach (array_slice($cats, 0, 5) as $c) {
    echo " - " . ($c['code'] ?? 'No Code') . " -> " . ($c['label'] ?? 'No Label') . "\n";
}
