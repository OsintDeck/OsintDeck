<?php
/**
 * Plugin Name: OSINT Deck
 * Description: Tablero OSINT con mazo de cartas, filtros y shortcode, usando Bootstrap 5 y JSON configurable desde el admin.
 * Version:     3.0.0
 * Author:      OSINT Deck
 * Requires at least: 6.0
 * Text Domain: osint-deck
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Basic constants.
if ( ! defined( 'OSD_VERSION' ) ) {
    define( 'OSD_VERSION', '3.0.0' );
}

if ( ! defined( 'OSD_PLUGIN_FILE' ) ) {
    define( 'OSD_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'OSD_PLUGIN_DIR' ) ) {
    define( 'OSD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'OSD_PLUGIN_URL' ) ) {
    define( 'OSD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Options.
if ( ! defined( 'OSD_OPTION_TOOLS' ) ) {
    define( 'OSD_OPTION_TOOLS', 'osd_json_tools' );
}
if ( ! defined( 'OSD_OPTION_THEME_MODE' ) ) {
    define( 'OSD_OPTION_THEME_MODE', 'osd_theme_mode' );
}
if ( ! defined( 'OSD_OPTION_THEME_SELECTOR' ) ) {
    define( 'OSD_OPTION_THEME_SELECTOR', 'osd_theme_selector' );
}
if ( ! defined( 'OSD_OPTION_THEME_TOKEN_LIGHT' ) ) {
    define( 'OSD_OPTION_THEME_TOKEN_LIGHT', 'osd_theme_token_light' );
}
if ( ! defined( 'OSD_OPTION_THEME_TOKEN_DARK' ) ) {
    define( 'OSD_OPTION_THEME_TOKEN_DARK', 'osd_theme_token_dark' );
}
if ( ! defined( 'OSD_OPTION_THEME_COLORS' ) ) {
    define( 'OSD_OPTION_THEME_COLORS', 'osd_theme_colors' );
}

// Core bootstrap.
require_once OSD_PLUGIN_DIR . 'includes/class-osd-core.php';

register_activation_hook( __FILE__, [ 'OSD_Core', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'OSD_Core', 'deactivate' ] );
register_uninstall_hook( __FILE__, [ 'OSD_Core', 'uninstall' ] );

add_action(
    'plugins_loaded',
    static function() {
        OSD_Core::init();
    }
);
