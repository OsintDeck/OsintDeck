<?php
define('OSINT_DECK_PLUGIN_DIR', __DIR__ . '/');

// Mock WP functions
$mock_options = [];

function get_option($key, $default = false) {
    global $mock_options;
    if (isset($mock_options[$key])) {
        return $mock_options[$key];
    }
    if ($key === 'osint_deck_nb_model') {
        // Return false to force training
        return false;
    }
    if ($key === 'osint_deck_nb_samples') {
        return []; 
    }
    if ($key === 'osint_deck_help_url') {
        return 'https://osint.com.ar/OsintDeck-Ayuda';
    }
    return $default;
}

function update_option($key, $value) {
    global $mock_options;
    $mock_options[$key] = $value;
    return true;
}

function sanitize_text_field($str) {
    return trim(strip_tags($str));
}

function __($str, $domain = 'default') {
    return $str;
}

// Include classes
require_once 'src/Infrastructure/Service/TLDManager.php';
require_once 'src/Domain/Service/NaiveBayesClassifier.php';
require_once 'src/Domain/Service/InputParser.php';
require_once 'src/Domain/Service/DecisionEngine.php';
require_once 'src/Domain/Repository/ToolRepositoryInterface.php';

// Mock ToolRepository
class MockToolRepository implements \OsintDeck\Domain\Repository\ToolRepositoryInterface {
    public function get_all_tools() { return []; }
    public function get_tool_by_id( $id ) { return null; }
    public function get_tool_by_slug( $slug ) { return null; }
    public function search_tools( $query ) { return []; }
    public function increment_clicks( $id ) { return true; }
    public function increment_likes( $id ) { return true; }
    public function increment_reports( $id ) { return true; }
    public function delete_tool( $id ) { return true; }
    public function save_tool( $data ) { return true; }
    public function import_from_json( $json_data ) { return true; }
    public function count_tools() { return 0; }
    public function export_to_json( $id ) { return ''; }
    public function seed_defaults() { return true; }
}

echo "Initializing services...\n";

// Instantiate
$tldManager = new \OsintDeck\Infrastructure\Service\TLDManager();
$classifier = new \OsintDeck\Domain\Service\NaiveBayesClassifier();

echo "Training classifier...\n";
// Load training data from json to train the classifier in memory
$json_file = OSINT_DECK_PLUGIN_DIR . 'data/training_data.json';
if (file_exists($json_file)) {
    $content = file_get_contents($json_file);
    $data = json_decode($content, true);
    if (is_array($data)) {
        foreach ($data as $item) {
            if (isset($item['text']) && isset($item['category'])) {
                $classifier->add_sample($item['text'], $item['category']);
            }
        }
        $res = $classifier->train();
        echo "Training result: " . print_r($res, true) . "\n";
    } else {
        echo "Invalid JSON data\n";
    }
} else {
    echo "Training data not found: $json_file\n";
}

$inputParser = new \OsintDeck\Domain\Service\InputParser($tldManager, $classifier);
$repo = new MockToolRepository();
$engine = new \OsintDeck\Domain\Service\DecisionEngine($repo, $inputParser);

// Test cases
$tests = ["hola", "sexo", "papa", "necesito ayuda", "como se usa esta herramienta?", "ayuda"];

echo "Running tests...\n";
foreach ($tests as $t) {
    $res = $engine->process_search($t);
    
    $firstResult = $res['results'][0] ?? null;
    $cardTitle = $firstResult['cards'][0]['title'] ?? 'No Card';
    
    // Check prediction directly
    $pred = $classifier->predict($t);
    $cat = $pred['category'] ?? 'none';
    
    echo "Query: '$t' -> Predicted: '$cat' -> Card Title: '$cardTitle'\n";
}
