<?php
/**
 * Plugin Name: OSINT Deck
 * Plugin URI: https://osintdeck.github.io
 * Description: Mazo OSINT para WordPress: catálogo de herramientas, motor de decisión por indicadores (IoC), shortcodes, métricas, integraciones (Google, Turnstile) y panel de administración. Incluye logs opcionales para diagnóstico.
 * Version: 1.0.1
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
define( 'OSINT_DECK_VERSION', '1.0.1' );
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

/**
 * Actualizaciones desde GitHub (Plugin Update Checker; ver github.com/YahnisElsts/plugin-update-checker).
 *
 * En el repo: tags o releases alineados con el header Version de este archivo.
 * Desactivar: define( 'OSINT_DECK_DISABLE_REMOTE_UPDATES', true );
 */
if ( ( ! defined( 'OSINT_DECK_DISABLE_REMOTE_UPDATES' ) || ! OSINT_DECK_DISABLE_REMOTE_UPDATES )
    && class_exists( \YahnisElsts\PluginUpdateChecker\v5\PucFactory::class ) ) {
    \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/OsintDeck/OsintDeck',
        __FILE__,
        'osint-deck'
    );
    // Si en cada release de GitHub publicás un ZIP como asset: $x->getVcsApi()->enableReleaseAssets();
}

/**
 * Enlaces bajo el nombre del plugin en Plugins instalados (p. ej. «Configuración» junto a Desactivar).
 */
add_filter(
    'plugin_action_links_' . plugin_basename( OSINT_DECK_PLUGIN_FILE ),
    static function ( array $links ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $links;
        }
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( ! is_plugin_active( plugin_basename( OSINT_DECK_PLUGIN_FILE ) ) ) {
            return $links;
        }
        $settings_url = admin_url( 'admin.php?page=osint-deck-settings' );
        array_unshift(
            $links,
            sprintf(
                '<a href="%s">%s</a>',
                esc_url( $settings_url ),
                esc_html__( 'Configuración', 'osint-deck' )
            )
        );
        return $links;
    }
);

/**
 * Segunda fila de enlaces (documentación, sitio).
 */
add_filter(
    'plugin_row_meta',
    static function ( array $links, $file ) {
        if ( $file !== plugin_basename( OSINT_DECK_PLUGIN_FILE ) ) {
            return $links;
        }
        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url( 'https://osintdeck.github.io/docs.html' ),
            esc_html__( 'Documentación', 'osint-deck' )
        );
        return $links;
    },
    10,
    2
);

// Initialize the plugin
add_action( 'plugins_loaded', function() {
    \OsintDeck\Core\Bootstrap::get_instance();
}, 10 );

// Register activation hook
register_activation_hook( __FILE__, array( 'OsintDeck\Core\Bootstrap', 'activate' ) );

// Register deactivation hook
register_deactivation_hook( __FILE__, array( 'OsintDeck\Core\Bootstrap', 'deactivate' ) );
