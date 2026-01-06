<?php
require_once __DIR__ . '/src/Infrastructure/Service/TLDManager.php';
require_once __DIR__ . '/src/Domain/Service/InputParser.php';
require_once __DIR__ . '/src/Domain/Service/DecisionEngine.php';
require_once __DIR__ . '/src/Domain/Repository/ToolRepositoryInterface.php';

use OsintDeck\Infrastructure\Service\TLDManager;
use OsintDeck\Domain\Service\InputParser;
use OsintDeck\Domain\Service\DecisionEngine;
use OsintDeck\Domain\Repository\ToolRepositoryInterface;

// Mock TLDManager
class MockTLDManager extends TLDManager {
    public function __construct() {}
    public function isValid( $tld ) { return true; }
}

// Mock ToolRepository
class MockToolRepo implements ToolRepositoryInterface {
    public function get_all_tools() { return []; }
    public function get_tool_by_id( $id ) { return null; }
    public function get_tool_by_slug( $slug ) { return null; }
    public function search_tools( $query ) { return []; }
    public function increment_clicks( $id ) {}
    public function increment_likes( $id ) {}
    public function increment_favorites( $id ) {}
    public function decrement_favorites( $id ) {}
    public function increment_reports( $id ) {}
    public function delete_tool( $id ) {}
    public function save_tool( $data ) {}
    public function import_from_json( $json_data ) {}
    public function count_tools() { return 0; }
    public function count_total_reports() { return 0; }
    public function export_to_json( $id ) { return ""; }
    public function seed_defaults() {}
}

$tld = new MockTLDManager();
$parser = new InputParser($tld);
$repo = new MockToolRepo();
$engine = new DecisionEngine($repo, $parser);

$inputs = [
    "hola",
    "necesito ayuda",
    "puto",
    "necesito saber la reputacion de una ip 8.8.8.8"
];

foreach ($inputs as $input) {
    echo "Query: '$input'\n";
    $res = $engine->process_search($input);
    echo "Mode: " . $res['mode'] . "\n";
    echo "Results count: " . count($res['results']) . "\n";
    if (count($res['results']) > 0) {
        echo "First result tool: " . $res['results'][0]['tool']['name'] . "\n";
    }
    echo "----------------\n";
}
