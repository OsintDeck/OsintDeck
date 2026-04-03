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
use OsintDeck\Infrastructure\Service\Logger;
use OsintDeck\Infrastructure\Persistence\ToolReports;

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
     * Métricas / gráficos
     *
     * @var MetricsScreen
     */
    private $metrics_screen;

    /**
     * Constructor
     *
     * @param ToolRepositoryInterface $tool_repository Tool Repository.
     * @param CategoryRepositoryInterface $category_repository Category Repository.
     * @param TLDManager $tld_manager TLD Manager.
     * @param NaiveBayesClassifier $classifier Classifier.
     */
    public function __construct( ToolRepositoryInterface $tool_repository, CategoryRepositoryInterface $category_repository, TLDManager $tld_manager, NaiveBayesClassifier $classifier ) {
        $this->tool_repository     = $tool_repository;
        $this->category_repository = $category_repository;
        $this->tld_manager         = $tld_manager;
        $this->classifier          = $classifier;
        $this->metrics_screen      = new MetricsScreen( $tool_repository, $category_repository );
    }

    /**
     * Initialize hooks
     *
     * @return void
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        $this->metrics_screen->init();
    }

    /**
     * Register admin menu
     *
     * @return void
     */
    public function register_menu() {
        // Cola de reportes abiertos (filas en base, no suma de stats).
        $report_count = ToolReports::count_open_total();
        $menu_title = __( 'OSINT Deck', 'osint-deck' );
        
        if ( $report_count > 0 ) {
            $menu_title .= sprintf(
                ' <span class="update-plugins count-%1$d"><span class="plugin-count">%1$d</span></span>',
                $report_count
            );
        }

        $menu_icon = 'dashicons-images-alt2';
        if ( defined( 'OSINT_DECK_PLUGIN_FILE' ) && defined( 'OSINT_DECK_PLUGIN_DIR' ) ) {
            $menu_icon = plugins_url( 'assets/images/menu-deck-icon.png', OSINT_DECK_PLUGIN_FILE );
            $icon_path = OSINT_DECK_PLUGIN_DIR . 'assets/images/menu-deck-icon.png';
            $icon_ver  = file_exists( $icon_path ) ? (string) filemtime( $icon_path ) : ( defined( 'OSINT_DECK_VERSION' ) ? OSINT_DECK_VERSION : '1' );
            $menu_icon = add_query_arg( 'ver', rawurlencode( $icon_ver ), $menu_icon );
        }

        // Main menu
        add_menu_page(
            __( 'OSINT Deck', 'osint-deck' ),
            $menu_title,
            'manage_options',
            'osint-deck',
            array( $this, 'render_dashboard' ),
            $menu_icon,
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

        $reports_subtitle = __( 'Reportes', 'osint-deck' );
        if ( $report_count > 0 ) {
            $reports_subtitle .= sprintf(
                ' <span class="awaiting-mod">%d</span>',
                $report_count
            );
        }
        add_submenu_page(
            'osint-deck',
            __( 'Reportes', 'osint-deck' ),
            $reports_subtitle,
            'manage_options',
            'osint-deck-tool-reports',
            array( $this, 'render_tool_reports' )
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

        // Métricas y reportes
        add_submenu_page(
            'osint-deck',
            __( 'Métricas y reportes', 'osint-deck' ),
            __( 'Métricas', 'osint-deck' ),
            'manage_options',
            'osint-deck-metrics',
            array( $this, 'render_metrics' )
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
        $tools_count      = $this->tool_repository->count_tools();
        $categories_count = $this->category_repository->count_categories();
        $reports_open     = ToolReports::count_open_total();
        $reports_total    = $this->tool_repository->count_total_reports();

        ?>
        <div class="wrap osint-deck-admin-wrap osint-deck-dashboard-page">
            <h1><?php esc_html_e( 'OSINT Deck', 'osint-deck' ); ?></h1>
            <p class="osint-deck-dashboard-intro">
                <?php esc_html_e( 'Panel de control: métricas rápidas, accesos al listado de herramientas y recordatorios para publicar el buscador en el sitio.', 'osint-deck' ); ?>
            </p>

            <?php if ( $reports_open > 0 ) : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php
                        printf(
                            /* translators: %d: number of open user reports */
                            esc_html( _n( 'Hay %d reporte de usuario sin resolver (mensajes y huellas activas).', 'Hay %d reportes de usuarios sin resolver (mensajes y huellas activas).', $reports_open, 'osint-deck' ) ),
                            (int) $reports_open
                        );
                        ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-tool-reports' ) ); ?>" class="button button-small"><?php esc_html_e( 'Ver reportes', 'osint-deck' ); ?></a>
                    </p>
                </div>
            <?php endif; ?>

            <div class="osint-deck-dashboard">
                <div class="osint-deck-stats" role="region" aria-label="<?php esc_attr_e( 'Resumen', 'osint-deck' ); ?>">
                    <div class="stat-card stat-card--tools">
                        <span class="stat-card__label"><?php esc_html_e( 'Herramientas', 'osint-deck' ); ?></span>
                        <span class="stat-card__value"><?php echo esc_html( (string) $tools_count ); ?></span>
                        <p class="stat-card__hint"><?php esc_html_e( 'Registros en la base del plugin.', 'osint-deck' ); ?></p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-tools' ) ); ?>" class="button">
                            <?php esc_html_e( 'Gestionar listado', 'osint-deck' ); ?>
                        </a>
                    </div>

                    <div class="stat-card stat-card--categories">
                        <span class="stat-card__label"><?php esc_html_e( 'Categorías', 'osint-deck' ); ?></span>
                        <span class="stat-card__value"><?php echo esc_html( (string) $categories_count ); ?></span>
                        <p class="stat-card__hint"><?php esc_html_e( 'Usadas en filtros y en el front.', 'osint-deck' ); ?></p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-categories' ) ); ?>" class="button">
                            <?php esc_html_e( 'Gestionar categorías', 'osint-deck' ); ?>
                        </a>
                    </div>

                    <div class="stat-card stat-card--reports">
                        <span class="stat-card__label"><?php esc_html_e( 'Reportes', 'osint-deck' ); ?></span>
                        <span class="stat-card__value"><?php echo esc_html( (string) $reports_total ); ?></span>
                        <p class="stat-card__hint">
                            <?php
                            printf(
                                /* translators: %d: open reports count */
                                esc_html( __( 'Acumulado histórico en stats. Pendientes sin resolver: %d.', 'osint-deck' ) ),
                                (int) $reports_open
                            );
                            ?>
                        </p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-tool-reports' ) ); ?>" class="button">
                            <?php esc_html_e( 'Cola de reportes', 'osint-deck' ); ?>
                        </a>
                    </div>
                </div>

                <div class="osint-deck-dashboard-metrics-teaser osint-card-panel">
                    <h2><?php esc_html_e( 'Métricas y reportes', 'osint-deck' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Gráficos por categoría, top de clics e interacciones, con filtros por categoría, estado de vista previa, nombre y rango de fechas de alta en la base.', 'osint-deck' ); ?>
                    </p>
                    <p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-metrics' ) ); ?>" class="button button-primary">
                            <?php esc_html_e( 'Abrir métricas y reportes', 'osint-deck' ); ?>
                        </a>
                    </p>
                </div>

                <div class="osint-deck-quick-actions osint-card-panel">
                    <h2><?php esc_html_e( 'Acciones rápidas', 'osint-deck' ); ?></h2>
                    <p class="osint-deck-action-buttons">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-tools&action=add' ) ); ?>" class="button button-primary button-hero">
                            <?php esc_html_e( 'Nueva herramienta', 'osint-deck' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-categories&action=add' ) ); ?>" class="button button-secondary button-hero">
                            <?php esc_html_e( 'Nueva categoría', 'osint-deck' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-settings&tab=data' ) ); ?>" class="button button-secondary button-hero">
                            <?php esc_html_e( 'Importar / exportar datos', 'osint-deck' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-settings' ) ); ?>" class="button button-secondary button-hero">
                            <?php esc_html_e( 'Configuración', 'osint-deck' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-metrics' ) ); ?>" class="button button-secondary button-hero">
                            <?php esc_html_e( 'Métricas y reportes', 'osint-deck' ); ?>
                        </a>
                    </p>
                </div>

                <div class="osint-deck-info osint-card-panel">
                    <h2><?php esc_html_e( 'Shortcodes', 'osint-deck' ); ?></h2>
                    <p><?php esc_html_e( 'Para mostrar el buscador en una página o entrada:', 'osint-deck' ); ?></p>
                    <code class="osint-code-block">[osint_deck]</code>
                    <p><?php esc_html_e( 'Con categoría y límite:', 'osint-deck' ); ?></p>
                    <code class="osint-code-block">[osint_deck category="seguridad" limit="10"]</code>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Métricas (submenú)
     *
     * @return void
     */
    public function render_metrics() {
        $this->metrics_screen->render();
    }

    /**
     * Render tools page
     *
     * @return void
     */
    public function render_tools() {
        $logger = new Logger();
        $manager = new ToolsManager( $this->tool_repository, $this->category_repository, $logger );
        $manager->render();
    }

    /**
     * Reportes abiertos desde usuarios.
     */
    public function render_tool_reports() {
        ToolReportsAdmin::render_page( $this->tool_repository );
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
