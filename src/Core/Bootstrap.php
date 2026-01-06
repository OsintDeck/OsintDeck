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
use OsintDeck\Infrastructure\Service\Logger;
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
    const VERSION = '1.3.7';

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
        $shortcodes = new Shortcodes( $this->tool_repository, $this->category_repository );
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
        // Admin hooks
        if ( is_admin() ) {
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
            add_action( 'admin_init', array( $this, 'check_environment' ) );
        }

        // Frontend hooks
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );

        // Cron hooks
        add_action( 'osint_deck_daily_cleanup', array( $this, 'daily_cleanup' ) );
    }

    /**
     * Daily cleanup task
     * 
     * @return void
     */
    public function daily_cleanup() {
        $logger = new Logger();
        $deleted = $logger->clean_old_logs();

        if ( $deleted > 0 ) {
            $logger->info( "Daily cleanup: Deleted $deleted old logs." );
        }
    }

    /**
     * Install data and seed defaults
     *
     * @return void
     */
    public function install_data() {
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
     * Cleanup hooks and log deactivation
     *
     * @return void
     */
    public function cleanup_hooks() {
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

        // Enqueue GSAP
        wp_enqueue_script(
            'gsap',
            'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js',
            array(),
            '3.12.5',
            true
        );

        // Enqueue Dashicons (needed for frontend)
        wp_enqueue_style( 'dashicons' );

        // Enqueue legacy CSS
        wp_enqueue_style(
            'osint-deck-public',
            plugin_dir_url( OSINT_DECK_PLUGIN_FILE ) . 'assets/css/osint-deck.css',
            array(),
            time()
        );

        // Enqueue fixes CSS
        wp_enqueue_style(
            'osint-deck-fixes',
            plugin_dir_url( OSINT_DECK_PLUGIN_FILE ) . 'assets/css/osint-deck-fixes.css',
            array( 'osint-deck-public' ),
            time()
        );

        // Enqueue help fixes CSS
        wp_enqueue_style(
            'osint-deck-help-fixes',
            plugin_dir_url( OSINT_DECK_PLUGIN_FILE ) . 'assets/css/osint-deck-help-fixes.css',
            array( 'osint-deck-fixes' ),
            time()
        );

        // Enqueue legacy JavaScript
        wp_enqueue_script(
            'osint-deck-main',
            plugin_dir_url( OSINT_DECK_PLUGIN_FILE ) . 'assets/js/osint-deck.js',
            array( 'jquery' ),
            time(), // Force cache bust for dev
            true
        );

        // Localize script with AJAX config
        wp_localize_script(
            'osint-deck-main',
            'osintDeckAjax',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'osint_deck_public' ),
                'helpCard' => array(
                    'title' => get_option( 'osint_deck_help_card_title', 'Soporte OSINT Deck' ),
                    'desc' => get_option( 'osint_deck_help_card_desc', '¿Encontraste un error o necesitas reportar algo? Contactanos directamente.' ),
                    'buttons' => json_decode( get_option( 'osint_deck_help_buttons', '[]' ), true )
                )
            )
        );
    }

    /**
     * Check environment and create necessary directories
     */
    public function check_environment() {
        self::create_upload_directories();
    }

    /**
     * Plugin activation
     */
    public static function activate() {
        // Create database tables
        \OsintDeck\Infrastructure\Persistence\ToolsTable::create_table();
        \OsintDeck\Infrastructure\Persistence\CategoriesTable::create_table();
        \OsintDeck\Infrastructure\Persistence\LogsTable::create_table();

        // Create upload directories
        self::create_upload_directories();

        // Schedule cron jobs
        if ( ! wp_next_scheduled( 'osint_deck_daily_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'osint_deck_daily_cleanup' );
        }
        
        // Run instance-level installation
        self::get_instance()->install_data();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clear scheduled hooks
        wp_clear_scheduled_hook( 'osint_deck_daily_cleanup' );
        
        // Run instance-level cleanup
        self::get_instance()->cleanup_hooks();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create upload directories
     */
    private static function create_upload_directories() {
        $upload_dir = wp_upload_dir();
        
        if ( isset( $upload_dir['error'] ) && ! empty( $upload_dir['error'] ) ) {
            error_log( 'OSINT Deck: Error getting upload directory: ' . $upload_dir['error'] );
            return;
        }

        $basedir = $upload_dir['basedir'];
        $dirs = [
            $basedir . '/osint-deck',
            $basedir . '/osint-deck/icons'
        ];

        foreach ( $dirs as $dir ) {
            if ( ! file_exists( $dir ) ) {
                if ( wp_mkdir_p( $dir ) ) {
                    // Create index.php for security
                    file_put_contents( $dir . '/index.php', '<?php // Silence is golden.' );
                } else {
                    error_log( 'OSINT Deck: Failed to create directory: ' . $dir );
                }
            } else {
                // Ensure index.php exists
                if ( ! file_exists( $dir . '/index.php' ) ) {
                    file_put_contents( $dir . '/index.php', '<?php // Silence is golden.' );
                }
            }
        }

        // Copy default icon if it doesn't exist in uploads
        $default_icon_source = OSINT_DECK_PLUGIN_DIR . 'assets/images/default-icon.svg';
        $default_icon_dest = $basedir . '/osint-deck/icons/default-icon.svg';

        if ( file_exists( $default_icon_source ) && ! file_exists( $default_icon_dest ) ) {
            copy( $default_icon_source, $default_icon_dest );
        }
    }
}
