<?php
/**
 * Main Plugin Class
 *
 * @package OsintDeck
 */

namespace OsintDeck\Core;

use OsintDeck\Domain\Repository\ToolRepositoryInterface;
use OsintDeck\Infrastructure\Persistence\CustomTableToolRepository;
use OsintDeck\Infrastructure\Persistence\CustomTableCategoryRepository;
use OsintDeck\Presentation\Api\AjaxHandler;
use OsintDeck\Presentation\Frontend\Shortcodes;
use OsintDeck\Presentation\Api\UserEvents;
use OsintDeck\Presentation\Admin\AdminMenu;
use OsintDeck\Infrastructure\Service\TLDManager;
use OsintDeck\Infrastructure\Service\Migration;
use OsintDeck\Domain\Service\InputParser;
use OsintDeck\Domain\Service\DecisionEngine;
use OsintDeck\Domain\Service\NaiveBayesClassifier;

/**
 * Class Bootstrap
 * 
 * Main plugin initialization and coordination
 */
class Bootstrap {

    /**
     * Plugin version
     *
     * @var string
     */
    const VERSION = '1.3.1';

    /**
     * Singleton instance
     *
     * @var Bootstrap
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Bootstrap
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * TLD Manager
     *
     * @var TLDManager
     */
    private $tld_manager;

    /**
     * Tool Repository
     *
     * @var ToolRepositoryInterface
     */
    private $tool_repository;

    /**
     * Category Repository
     *
     * @var CategoryRepositoryInterface
     */
    private $category_repository;

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_components();
        $this->init_hooks();
    }

    /**
     * Initialize plugin components
     *
     * @return void
     */
    private function init_components() {
        // Initialize Repositories
        $this->tool_repository = new CustomTableToolRepository();
        $this->category_repository = new CustomTableCategoryRepository();

        // Initialize TLD Manager
        $this->tld_manager = new TLDManager();
        $this->tld_manager->init();

        // Initialize Domain Services
        $classifier = new NaiveBayesClassifier();
        $input_parser = new InputParser( $this->tld_manager, $classifier );
        $decision_engine = new DecisionEngine( $this->tool_repository, $input_parser );

        // Initialize AJAX Handler
        $ajax_handler = new AjaxHandler( $this->tool_repository, $decision_engine );
        $ajax_handler->init();

        // Initialize Shortcodes
        $shortcodes = new Shortcodes( $this->tool_repository );
        $shortcodes->init();

        // Initialize User Events
        $user_events = new UserEvents( $this->tool_repository );
        $user_events->init();
        
        // Initialize Admin Menu (admin only)
        if ( is_admin() ) {
            $admin_menu = new AdminMenu( $this->tool_repository, $this->category_repository, $this->tld_manager, $classifier );
            $admin_menu->init();
        }
    }

    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function init_hooks() {
        // Register activation/deactivation hooks
        register_activation_hook( OSINT_DECK_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( OSINT_DECK_PLUGIN_FILE, array( $this, 'deactivate' ) );

        // Admin hooks
        if ( is_admin() ) {
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        }

        // Frontend hooks
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
    }

    /**
     * Plugin activation
     *
     * @return void
     */
    public function activate() {
        // Set default options
        $this->set_default_options();

        // Install repositories (create tables if needed)
        if ( method_exists( $this->tool_repository, 'install' ) ) {
            $this->tool_repository->install();
        }

        if ( method_exists( $this->category_repository, 'install' ) ) {
            $this->category_repository->install();
        }

        // Seed default categories if table is empty
        if ( $this->category_repository->count_categories() === 0 ) {
            $seed_result = $this->category_repository->seed_defaults();
            
            if ( defined( 'OSD_DEBUG_PANEL' ) && OSD_DEBUG_PANEL && $seed_result['imported'] > 0 ) {
                error_log( '[OSINT Deck] ' . $seed_result['message'] );
            }
        }

        // Initialize TLD Manager
        // Already initialized in init_components, but we need to seed here
        
        // Seed TLDs and try to update from IANA
        $this->tld_manager->seed_default();
        $this->tld_manager->update_from_iana();

        // Migrate from legacy option if table is empty
        if ( $this->tool_repository->count_tools() === 0 ) {
            $migration = new Migration( $this->tool_repository );
            $migration_result = $migration->migrate_from_option();
            
            if ( defined( 'OSD_DEBUG_PANEL' ) && OSD_DEBUG_PANEL && $migration_result['migrated'] > 0 ) {
                error_log( '[OSINT Deck] ' . $migration_result['message'] );
            }
        }

        // Log activation
        if ( defined( 'OSD_DEBUG_PANEL' ) && OSD_DEBUG_PANEL ) {
            error_log( '[OSINT Deck] Plugin activated - Version ' . self::VERSION );
        }
    }

    /**
     * Plugin deactivation
     *
     * @return void
     */
    public function deactivate() {
        // Log deactivation
        if ( defined( 'OSD_DEBUG_PANEL' ) && OSD_DEBUG_PANEL ) {
            error_log( '[OSINT Deck] Plugin deactivated' );
        }
    }

    /**
     * Set default plugin options
     *
     * @return void
     */
    private function set_default_options() {
        $defaults = array(
            'health_checker_enabled' => false,
            'health_check_interval'  => 12, // hours
            'badge_calculation_enabled' => true,
            'stats_tracking_enabled' => true,
            'theme_mode' => 'auto', // auto, light, dark
            'theme_selector' => '[data-site-skin]',
            'theme_token_light' => 'light',
            'theme_token_dark' => 'dark',
        );

        foreach ( $defaults as $key => $value ) {
            $option_name = 'osint_deck_' . $key;
            if ( false === get_option( $option_name ) ) {
                add_option( $option_name, $value );
            }
        }
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our plugin pages
        // Check if the current page is one of our plugin pages
        if ( ! is_string( $hook ) || strpos( $hook, 'osint-deck' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'osint-deck-admin',
            plugin_dir_url( OSINT_DECK_PLUGIN_FILE ) . 'assets/css/admin.css',
            array(),
            self::VERSION
        );

        wp_enqueue_script(
            'osint-deck-admin',
            plugin_dir_url( OSINT_DECK_PLUGIN_FILE ) . 'assets/js/admin.js',
            array( 'jquery' ),
            self::VERSION,
            true
        );

        // Localize script with data
        wp_localize_script(
            'osint-deck-admin',
            'osintDeckAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'osint_deck_admin' ),
            )
        );
    }

    /**
     * Enqueue public assets
     *
     * @return void
     */
    public function enqueue_public_assets() {
        // Enqueue RemixIcon
        wp_enqueue_style(
            'remixicon',
            'https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css',
            array(),
            '3.5.0'
        );

        // Enqueue Dashicons (needed for frontend)
        wp_enqueue_style( 'dashicons' );

        // Enqueue legacy CSS
        wp_enqueue_style(
            'osint-deck-public',
            plugin_dir_url( OSINT_DECK_PLUGIN_FILE ) . 'assets/css/osint-deck.css',
            array(),
            self::VERSION
        );

        // Enqueue fixes CSS
        wp_enqueue_style(
            'osint-deck-fixes',
            plugin_dir_url( OSINT_DECK_PLUGIN_FILE ) . 'assets/css/osint-deck-fixes.css',
            array( 'osint-deck-public' ),
            self::VERSION
        );

        // Enqueue legacy JavaScript
        wp_enqueue_script(
            'osint-deck-main',
            plugin_dir_url( OSINT_DECK_PLUGIN_FILE ) . 'assets/js/osint-deck.js',
            array( 'jquery' ),
            self::VERSION,
            true
        );

        // Localize script with AJAX config
        wp_localize_script(
            'osint-deck-main',
            'osintDeckAjax',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'osint_deck_public' ),
            )
        );
    }
}
