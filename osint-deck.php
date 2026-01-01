<?php
/**
 * Plugin Name: OSINT Deck
 * Plugin URI: https://osintdeck.github.io
 * Description: Plugin para centralizar herramientas OSINT y registrar logs para debugging.
 * Version: 0.0.3
 * Author: Equipo OSINT Deck
 * Author URI: https://github.com/OsintDeck
 * License: GPL2
 * Text Domain: osint-deck
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'OSINT_DECK_VERSION', '0.0.3' );
define( 'OSINT_DECK_PLUGIN_FILE', __FILE__ );
define( 'OSINT_DECK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OSINT_DECK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load autoloader
if ( file_exists( OSINT_DECK_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once OSINT_DECK_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Fallback or error handling if vendor is missing
    error_log( 'OSINT Deck: vendor/autoload.php not found. Please run composer install.' );
}

// Initialize the plugin
add_action( 'plugins_loaded', function() {
    \OsintDeck\Core\Bootstrap::get_instance();
}, 10 );
