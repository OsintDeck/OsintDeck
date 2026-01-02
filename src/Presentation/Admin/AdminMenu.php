<?php
/**
 * Admin Menu - Main admin interface
 *
 * @package OsintDeck
 */

namespace OsintDeck\Presentation\Admin;

use OsintDeck\Domain\Repository\ToolRepositoryInterface;
use OsintDeck\Domain\Repository\CategoryRepositoryInterface;
use OsintDeck\Infrastructure\Service\TLDManager;
use OsintDeck\Domain\Service\NaiveBayesClassifier;

/**
 * Class AdminMenu
 * 
 * Handles the admin menu and pages
 */
class AdminMenu {

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
     * TLD Manager
     *
     * @var TLDManager
     */
    private $tld_manager;

    /**
     * Classifier
     *
     * @var NaiveBayesClassifier
     */
    private $classifier;

    /**
     * Constructor
     *
     * @param ToolRepositoryInterface $tool_repository Tool Repository.
     * @param CategoryRepositoryInterface $category_repository Category Repository.
     * @param TLDManager $tld_manager TLD Manager.
     * @param NaiveBayesClassifier $classifier Classifier.
     */
    public function __construct( ToolRepositoryInterface $tool_repository, CategoryRepositoryInterface $category_repository, TLDManager $tld_manager, NaiveBayesClassifier $classifier ) {
        $this->tool_repository = $tool_repository;
        $this->category_repository = $category_repository;
        $this->tld_manager = $tld_manager;
        $this->classifier = $classifier;
    }

    /**
     * Initialize hooks
     *
     * @return void
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
    }

    /**
     * Register admin menu
     *
     * @return void
     */
    public function register_menu() {
        // Main menu
        add_menu_page(
            __( 'OSINT Deck', 'osint-deck' ),
            __( 'OSINT Deck', 'osint-deck' ),
            'manage_options',
            'osint-deck',
            array( $this, 'render_dashboard' ),
            'dashicons-search',
            25
        );

        // Remove duplicate submenu (auto-generated)
        remove_submenu_page( 'osint-deck', 'osint-deck' );

        // Dashboard (Explicit)
        add_submenu_page(
            'osint-deck',
            __( 'Dashboard', 'osint-deck' ),
            __( 'Dashboard', 'osint-deck' ),
            'manage_options',
            'osint-deck',
            array( $this, 'render_dashboard' )
        );

        // Tools
        add_submenu_page(
            'osint-deck',
            __( 'Herramientas', 'osint-deck' ),
            __( 'Herramientas', 'osint-deck' ),
            'manage_options',
            'osint-deck-tools',
            array( $this, 'render_tools' )
        );

        // Categories
        add_submenu_page(
            'osint-deck',
            __( 'Categorías', 'osint-deck' ),
            __( 'Categorías', 'osint-deck' ),
            'manage_options',
            'osint-deck-categories',
            array( $this, 'render_categories' )
        );

        // TLDs (Legacy Redirect)
        add_submenu_page(
            'osint-deck',
            __( 'TLDs', 'osint-deck' ),
            __( 'TLDs', 'osint-deck' ),
            'manage_options',
            'osint-deck-tlds',
            array( $this, 'render_tlds' )
        );
        remove_submenu_page( 'osint-deck', 'osint-deck-tlds' );

        // Import/Export (Legacy Redirect)
        add_submenu_page(
            'osint-deck',
            __( 'Importar/Exportar', 'osint-deck' ),
            __( 'Importar/Exportar', 'osint-deck' ),
            'manage_options',
            'osint-deck-import-export',
            array( $this, 'render_import_export' )
        );
        remove_submenu_page( 'osint-deck', 'osint-deck-import-export' );

        // Settings
        add_submenu_page(
            'osint-deck',
            __( 'Configuración', 'osint-deck' ),
            __( 'Configuración', 'osint-deck' ),
            'manage_options',
            'osint-deck-settings',
            array( $this, 'render_settings' )
        );

        // AI Training
        add_submenu_page(
            'osint-deck',
            __( 'Entrenamiento IA', 'osint-deck' ),
            __( 'Entrenamiento IA', 'osint-deck' ),
            'manage_options',
            'osint-deck-ai',
            array( $this, 'render_ai_training' )
        );
    }

    /**
     * Render dashboard page
     *
     * @return void
     */
    public function render_dashboard() {
        $tools_count = $this->tool_repository->count_tools();
        $categories_count = $this->category_repository->count_categories();
        
        ?>
        <div class="wrap osint-deck-admin-wrap">
            <h1><?php _e( 'OSINT Deck - Dashboard', 'osint-deck' ); ?></h1>
            
            <div class="osint-deck-dashboard">
                <div class="osint-deck-stats">
                    <div class="stat-card">
                        <h3><?php echo esc_html( $tools_count ); ?></h3>
                        <p><?php _e( 'Herramientas', 'osint-deck' ); ?></p>
                        <a href="<?php echo admin_url( 'admin.php?page=osint-deck-tools' ); ?>" class="button">
                            <?php _e( 'Gestionar', 'osint-deck' ); ?>
                        </a>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php echo esc_html( $categories_count ); ?></h3>
                        <p><?php _e( 'Categorías', 'osint-deck' ); ?></p>
                        <a href="<?php echo admin_url( 'admin.php?page=osint-deck-categories' ); ?>" class="button">
                            <?php _e( 'Gestionar', 'osint-deck' ); ?>
                        </a>
                    </div>
                </div>

                <div class="osint-deck-quick-actions">
                    <h2><?php _e( 'Acciones Rápidas', 'osint-deck' ); ?></h2>
                    <p>
                        <a href="<?php echo admin_url( 'admin.php?page=osint-deck-tools&action=add' ); ?>" class="button button-primary">
                            <?php _e( '+ Nueva Herramienta', 'osint-deck' ); ?>
                        </a>
                        <a href="<?php echo admin_url( 'admin.php?page=osint-deck-categories&action=add' ); ?>" class="button">
                            <?php _e( '+ Nueva Categoría', 'osint-deck' ); ?>
                        </a>
                        <a href="<?php echo admin_url( 'admin.php?page=osint-deck-import-export' ); ?>" class="button">
                            <?php _e( 'Importar/Exportar', 'osint-deck' ); ?>
                        </a>
                    </p>
                </div>

                <div class="osint-deck-info">
                    <h2><?php _e( 'Uso del Shortcode', 'osint-deck' ); ?></h2>
                    <p><?php _e( 'Para mostrar el buscador OSINT en una página, usá el siguiente shortcode:', 'osint-deck' ); ?></p>
                    <code class="osint-code-block">[osint_deck]</code>
                    <p><?php _e( 'También podés filtrar por categoría:', 'osint-deck' ); ?></p>
                    <code class="osint-code-block">[osint_deck category="seguridad" limit="10"]</code>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render tools page
     *
     * @return void
     */
    public function render_tools() {
        $manager = new ToolsManager( $this->tool_repository, $this->category_repository );
        $manager->render();
    }

    /**
     * Render categories page
     *
     * @return void
     */
    public function render_categories() {
        $manager = new CategoriesManager( $this->category_repository );
        $manager->render();
    }

    /**
     * Render TLDs page placeholder
     */
    public function render_tlds() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos para acceder a esta página.', 'osint-deck' ) );
        }
        
        wp_safe_redirect( admin_url( 'admin.php?page=osint-deck-settings&tab=tlds' ) );
        exit;
    }

    /**
     * Render import/export page placeholder
     */
    public function render_import_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos para acceder a esta página.', 'osint-deck' ) );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=osint-deck-settings&tab=data' ) );
        exit;
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function render_settings() {
        $settings = new Settings( $this->tool_repository, $this->category_repository, $this->tld_manager, $this->classifier );
        $settings->render();
    }

    /**
     * Render AI Training page
     */
    public function render_ai_training() {
        $manager = new AiTrainingManager( $this->classifier );
        $manager->render();
    }
}
