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
use OsintDeck\Infrastructure\Auth\OsintUserSession;
use OsintDeck\Infrastructure\Persistence\UserFavorites;
use OsintDeck\Infrastructure\Persistence\UserLikes;
use OsintDeck\Infrastructure\Persistence\DatabaseSchemaMigration;
use OsintDeck\Infrastructure\Persistence\ToolReports;
use OsintDeck\Infrastructure\Persistence\ToolReportsTable;
use OsintDeck\Infrastructure\Persistence\ReportThanks;
use OsintDeck\Infrastructure\Security\Turnstile;

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
    const VERSION = '1.0.2';

    /**
     * Singleton instance
     *
     * @var Bootstrap
     */
    private static $instance = null;

    /**
     * Textos y botones de la tarjeta de crisis (orientación Argentina; opciones wp opcionales).
     *
     * @return array<string, mixed>
     */
    private static function get_crisis_card_localize() {
        $title = get_option(
            'osint_deck_crisis_card_title',
            'Apoyo emocional'
        );
        $desc  = get_option(
            'osint_deck_crisis_card_desc',
            'Si estás en crisis o con ideas de hacerte daño, no estás solo/a. Estos recursos suelen ser gratuitos y confidenciales.'
        );

        $raw     = get_option( 'osint_deck_crisis_buttons', '' );
        $buttons = ( is_string( $raw ) && $raw !== '' ) ? json_decode( $raw, true ) : null;

        if ( ! is_array( $buttons ) || array() === $buttons ) {
            $buttons = array(
                array(
                    'label' => 'Línea 135 — Salud mental y adicciones',
                    'url'   => 'tel:135',
                    'icon'  => 'ri-phone-line',
                ),
                array(
                    'label' => 'Línea 144 — Violencia de género',
                    'url'   => 'tel:144',
                    'icon'  => 'ri-phone-line',
                ),
                array(
                    'label' => 'Emergencias sanitarias (107)',
                    'url'   => 'tel:107',
                    'icon'  => 'ri-phone-line',
                ),
                array(
                    'label' => 'Argentina.gob — Salud mental',
                    'url'   => 'https://www.argentina.gob.ar/salud/mental',
                    'icon'  => 'ri-health-book-line',
                ),
            );
        }

        return array(
            'title'   => $title,
            'desc'    => $desc,
            'buttons' => $buttons,
        );
    }

    /**
     * URL base de discusiones (documentación → GitHub Discussions); editable por filtro.
     *
     * @return string
     */
    private static function get_community_discussions_url() {
        return (string) apply_filters( 'osint_deck_community_discussions_url', 'https://osintdeck.github.io/discussions.html' );
    }

    /**
     * Botones por defecto de la carta comunidad (misma estructura que guarda el admin).
     *
     * @return array<int, array{label: string, url: string, icon: string}>
     */
    private static function default_community_buttons() {
        $url = self::get_community_discussions_url();

        return array(
            array(
                'label' => 'Sugerir una herramienta',
                'url'   => $url,
                'icon'  => 'ri-add-box-line',
            ),
            array(
                'label' => '💡 Compartir ideas',
                'url'   => $url,
                'icon'  => 'ri-lightbulb-line',
            ),
            array(
                'label' => '❓ Hacer preguntas',
                'url'   => $url,
                'icon'  => 'ri-question-answer-line',
            ),
            array(
                'label' => '🤝 Colaborar',
                'url'   => $url,
                'icon'  => 'ri-team-line',
            ),
        );
    }

    /**
     * Carta de sugerencias / comunidad (opciones de administración + URL filtrable).
     *
     * @return array<string, mixed>
     */
    private static function get_community_card_localize() {
        $title = get_option( 'osint_deck_community_card_title', 'Sugerencias y comunidad' );
        $desc  = get_option(
            'osint_deck_community_card_desc',
            'Proponé herramientas nuevas, ideas de mejora o participá en la comunidad. Las conversaciones se gestionan en GitHub Discussions.'
        );

        $raw     = get_option( 'osint_deck_community_buttons', '' );
        $buttons = ( is_string( $raw ) && $raw !== '' ) ? json_decode( $raw, true ) : null;

        if ( ! is_array( $buttons ) || array() === $buttons ) {
            $buttons = self::default_community_buttons();
        }

        return array(
            'title'   => $title,
            'desc'    => $desc,
            'buttons' => $buttons,
        );
    }

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
        DatabaseSchemaMigration::maybe_run();
        ToolReportsTable::create_table();
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
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_menu_icon_styles' ), 5 );
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

        \OsintDeck\Infrastructure\Persistence\UserHistoryTable::create_table();
        ToolReportsTable::create_table();

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
            'theme_token_dark'      => 'dark',
            'chatbar_sticky_top'      => 0,
            'chatbar_sticky_enabled'  => true,
        );

        foreach ( $defaults as $key => $value ) {
            $option_name = 'osint_deck_' . $key;
            if ( false === get_option( $option_name ) ) {
                add_option( $option_name, $value );
            }
        }
    }

    /**
     * Estilos del icono en el menú lateral (todas las pantallas de admin).
     *
     * @return void
     */
    public function enqueue_admin_menu_icon_styles() {
        if ( ! is_admin() ) {
            return;
        }

        wp_enqueue_style(
            'osint-deck-admin-menu-icon',
            plugin_dir_url( OSINT_DECK_PLUGIN_FILE ) . 'assets/css/admin-menu-icon.css',
            array(),
            self::VERSION
        );
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

        // Enqueue legacy CSS (version = cache buster on each release)
        wp_enqueue_style(
            'osint-deck-public',
            plugin_dir_url( OSINT_DECK_PLUGIN_FILE ) . 'assets/css/osint-deck.css',
            array(),
            OSINT_DECK_VERSION
        );

        // Enqueue fixes CSS
        wp_enqueue_style(
            'osint-deck-fixes',
            plugin_dir_url( OSINT_DECK_PLUGIN_FILE ) . 'assets/css/osint-deck-fixes.css',
            array( 'osint-deck-public' ),
            OSINT_DECK_VERSION
        );

        // Enqueue help fixes CSS
        wp_enqueue_style(
            'osint-deck-help-fixes',
            plugin_dir_url( OSINT_DECK_PLUGIN_FILE ) . 'assets/css/osint-deck-help-fixes.css',
            array( 'osint-deck-fixes' ),
            OSINT_DECK_VERSION
        );

        $osint_main_deps = array( 'jquery' );
        // GSI cargado antes del deck: si no, prompt() corre en un tick async y el navegador puede bloquear el selector de cuentas.
        $sso_on = (bool) get_option( 'osint_deck_sso_enabled', false );
        $has_client = '' !== (string) get_option( 'osint_deck_google_client_id', '' );
        if ( $sso_on && $has_client ) {
            wp_enqueue_script(
                'google-gsi-client',
                'https://accounts.google.com/gsi/client',
                array(),
                null,
                true
            );
            $osint_main_deps[] = 'google-gsi-client';
        }

        // Enqueue legacy JavaScript
        wp_enqueue_script(
            'osint-deck-main',
            plugin_dir_url( OSINT_DECK_PLUGIN_FILE ) . 'assets/js/osint-deck.js',
            $osint_main_deps,
            OSINT_DECK_VERSION,
            true
        );

        // Localize script with AJAX config
        wp_localize_script(
            'osint-deck-main',
            'osintDeckAjax',
            array(
                'url'     => admin_url( 'admin-ajax.php' ),
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'osint_deck_public' ),
                'helpCard' => array(
                    'title' => get_option( 'osint_deck_help_card_title', 'Soporte OSINT Deck' ),
                    'desc' => get_option( 'osint_deck_help_card_desc', '¿Encontraste un error o necesitas reportar algo? Contactanos directamente.' ),
                    'buttons' => json_decode( get_option( 'osint_deck_help_buttons', '[]' ), true )
                ),
                'crisisCard' => self::get_crisis_card_localize(),
                'communityCard' => self::get_community_card_localize(),
                'auth' => array(
                    'enabled'          => (bool) get_option( 'osint_deck_sso_enabled', false ),
                    'googleClientId'   => get_option( 'osint_deck_google_client_id', '' ),
                    'loginWelcome'     => __( 'Sesión iniciada. Hola, %s.', 'osint-deck' ),
                    'accountSwitched'  => __( 'Cambiaste de cuenta. Ahora sos %s.', 'osint-deck' ),
                    'loggedOut'        => __( 'Te desconectaste.', 'osint-deck' ),
                    'loginFailed'      => __( 'No se pudo iniciar sesión. Probá de nuevo.', 'osint-deck' ),
                    'loginNetworkError' => __( 'No hay conexión o el servidor no respondió. Probá de nuevo.', 'osint-deck' ),
                    'logoutFailed'     => __( 'No se pudo cerrar la sesión. Recargá la página e intentá de nuevo.', 'osint-deck' ),
                    'fallbackName'     => __( 'usuario', 'osint-deck' ),
                    'switchAccount'    => __( 'Cambiar de cuenta', 'osint-deck' ),
                    'logOut'           => __( 'Desconectar', 'osint-deck' ),
                    'welcomeTitle'     => __( 'Sesión activa · %s', 'osint-deck' ),
                    'signInAria'       => __( 'Acceder con Google', 'osint-deck' ),
                    'signInTitle'      => __( 'Iniciá sesión pulsando el botón circular con el logo de Google en esta barra.', 'osint-deck' ),
                    /** PNG oficial 128dp (nitido a 30px); filtrable: `osint_deck_google_sso_mark_url`. */
                    'googleMarkUrl'    => esc_url(
                        apply_filters(
                            'osint_deck_google_sso_mark_url',
                            'https://ssl.gstatic.com/images/branding/googleg/1x/googleg_standard_color_128dp.png'
                        )
                    ),
                ),
                'trainingDataUrl' => plugin_dir_url( OSINT_DECK_PLUGIN_FILE ) . 'data/training_data.json',
                'brandUrl'          => apply_filters( 'osint_deck_brand_url', 'https://osintdeck.github.io' ),
                'logoOnLightBg'     => plugins_url( 'assets/images/osint-deck-logo-on-light-bg.png', OSINT_DECK_PLUGIN_FILE ),
                'logoOnDarkBg'      => plugins_url( 'assets/images/osint-deck-logo-on-dark-bg.png', OSINT_DECK_PLUGIN_FILE ),
                'deckLoggedIn'     => (bool) OsintUserSession::get_user_id(),
                'favoriteToolIds'  => array_map(
                    'intval',
                    UserFavorites::get_tool_ids( (int) OsintUserSession::get_user_id() )
                ),
                'likedToolIds'     => array_map(
                    'intval',
                    UserLikes::get_tool_ids( (int) OsintUserSession::get_user_id() )
                ),
                'reportedToolIds'  => array_map(
                    'intval',
                    ToolReports::get_open_tool_ids_for_user( (int) OsintUserSession::get_user_id() )
                ),
                'reportThanksToolIds' => array_map(
                    'intval',
                    ReportThanks::get_pending_for_user( (int) OsintUserSession::get_user_id() )
                ),
                'likes' => array(
                    'on'    => __( 'Te gusta esta herramienta.', 'osint-deck' ),
                    'off'   => __( 'Quitaste tu me gusta.', 'osint-deck' ),
                    'error' => __( 'No se pudo actualizar el me gusta.', 'osint-deck' ),
                ),
                'favorites' => array(
                    'menuShowFavorites' => __( 'Ver solo favoritos', 'osint-deck' ),
                    'menuShowAllDeck'  => __( 'Ver todas las herramientas', 'osint-deck' ),
                    'clearAll'       => __( 'Vaciar favoritos', 'osint-deck' ),
                    'clearAllConfirm' => __( '¿Quitar todas las herramientas de tus favoritos? No se eliminan del deck; solo de tu lista personal.', 'osint-deck' ),
                    'clearAllDone'   => __( 'Favoritos vaciados.', 'osint-deck' ),
                    'clearAllFailed' => __( 'No se pudieron vaciar los favoritos. Probá de nuevo.', 'osint-deck' ),
                    'clearAllEmpty'  => __( 'No tenés favoritos guardados.', 'osint-deck' ),
                    'needLogin'      => __( 'Iniciá sesión con tu cuenta (Google) para guardar y usar favoritos.', 'osint-deck' ),
                    'needLoginFilter' => __( 'Iniciá sesión para ver solo tus favoritos.', 'osint-deck' ),
                    'ssoDisabled'    => __( 'Los favoritos no están disponibles porque el acceso con cuenta no está habilitado en este sitio.', 'osint-deck' ),
                    'favUpdateError' => __( 'No se pudieron actualizar los favoritos. Probá de nuevo.', 'osint-deck' ),
                ),
                'privacy' => array(
                    'historyTitle'    => __( 'Tu actividad en OSINT Deck', 'osint-deck' ),
                    'historyIntro'    => __( 'Solo vos ves este listado. Podés borrar todo el historial o eliminar tu cuenta de acceso a OSINT Deck (sesión con Google en este sitio). Lo registrado refleja tu uso del plugin y está pensado para tu referencia personal —por ejemplo, reconocer desde dónde o con qué dispositivo hiciste una acción cuando esa información se muestre—. El proyecto OSINT Deck no comparte, no vende ni utiliza estos datos con fines comerciales o publicitarios y respeta tu privacidad. Los datos se almacenan en este sitio y pueden ser consultados por quien lo administra, según su política de privacidad y la ley que aplique.', 'osint-deck' ),
                    'historyMenu'     => __( 'Mi actividad y privacidad', 'osint-deck' ),
                    'historyEmpty'    => __( 'No hay registros todavía: aparecerán búsquedas y acciones mientras tengas sesión.', 'osint-deck' ),
                    'clearHistory'    => __( 'Borrar todo mi historial', 'osint-deck' ),
                    'clearConfirm'    => __( '¿Borrar por completo tu historial en este sitio? No se puede deshacer.', 'osint-deck' ),
                    'clearFailed'     => __( 'No se pudo borrar el historial. Probá de nuevo.', 'osint-deck' ),
                    'deleteAccount'   => __( 'Eliminar mi cuenta (derecho al olvido)', 'osint-deck' ),
                    'deleteWarn'      => __( 'Se eliminarán tu cuenta del deck (favoritos, historial y datos asociados), no tu usuario de WordPress. Después deberás aceptar los términos en pantalla y escribir DELETE. No se puede deshacer.', 'osint-deck' ),
                    'deleteFailed'    => __( 'No se pudo eliminar la cuenta. Probá de nuevo o contactá al sitio.', 'osint-deck' ),
                    'deleteBlocked'   => __( 'No se pudo completar la baja. Probá de nuevo.', 'osint-deck' ),
                    'termsUrl'        => apply_filters( 'osint_deck_privacy_terms_url', 'https://osintdeck.github.io/docs.html' ),
                    'deleteTermsIntro' => __( 'La baja es definitiva. El uso del deck, privacidad de datos y responsabilidades del usuario están descriptos en la documentación oficial de OSINT Deck (misma información que enlaza el pie del sitio cuando aplica).', 'osint-deck' ),
                    'deleteTermsCheckbox' => __( 'Leí y acepto los términos y condiciones de uso y la política de privacidad indicados en esa documentación.', 'osint-deck' ),
                    'deleteTermsLink' => __( 'Abrir documentación y términos', 'osint-deck' ),
                    'deleteTermsContinue' => __( 'Continuar', 'osint-deck' ),
                    'deleteTermsCancel' => __( 'Cancelar', 'osint-deck' ),
                    'deleteTermsRequired' => __( 'Tenés que marcar la casilla para confirmar que aceptás los términos.', 'osint-deck' ),
                    'close'           => __( 'Cerrar', 'osint-deck' ),
                    'loadError'       => __( 'No se pudo cargar el historial. Iniciá sesión de nuevo.', 'osint-deck' ),
                    'typeSearch'      => __( 'Búsqueda', 'osint-deck' ),
                    'typeOpen'        => __( 'Abrir herramienta', 'osint-deck' ),
                    'typeLike'        => __( 'Me gusta', 'osint-deck' ),
                    'typeFavorite'    => __( 'Favorito', 'osint-deck' ),
                    'typeReport'      => __( 'Reporte', 'osint-deck' ),
                    'historyOpenWithQuery' => __( 'Buscaste «%1$s» · abriste %2$s', 'osint-deck' ),
                ),
                'reports' => array(
                    'confirmToggleOff' => __( '¿Quitar tu reporte sobre esta herramienta?', 'osint-deck' ),
                    'confirmReport'    => __( '¿Reportar que hay un problema con esta herramienta?', 'osint-deck' ),
                    'askComment'       => __( '¿Querés dejar un comentario para el equipo? (opcional)', 'osint-deck' ),
                    'commentHint'      => __( 'Contanos qué falló o qué corregir (máx. 2000 caracteres).', 'osint-deck' ),
                    'commentLabel'     => __( 'Mensaje para el equipo (opcional)', 'osint-deck' ),
                    'loginForComment'  => __( 'Para dejar un mensaje tenés que iniciar sesión. Podés reportar sin comentario ahora, o iniciar sesión y volver a intentar.', 'osint-deck' ),
                    'reportedOn'       => __( 'Reportaste esta herramienta. Volvé a tocar la bandera para quitar el reporte.', 'osint-deck' ),
                    'reportedOff'      => __( 'Quitaste el reporte.', 'osint-deck' ),
                    'thanks'           => __( 'Ya revisamos la herramienta que reportaste. ¡Gracias por ayudarnos a mejorar OSINT Deck!', 'osint-deck' ),
                    'thanksCloseAria'  => __( 'Cerrar aviso de herramienta reparada', 'osint-deck' ),
                    'error'            => __( 'No se pudo actualizar el reporte. Probá de nuevo.', 'osint-deck' ),
                    'tooltipOn'        => __( 'Quitar reporte', 'osint-deck' ),
                    'tooltipOff'       => __( 'Reportar herramienta', 'osint-deck' ),
                    'dlgTitleRemove'   => __( 'Quitar reporte', 'osint-deck' ),
                    'dlgTitleReport'   => __( 'Reportar herramienta', 'osint-deck' ),
                    'dlgTitleComment'  => __( 'Comentario opcional', 'osint-deck' ),
                    'dlgTitleNeedLogin' => __( 'Comentario con cuenta', 'osint-deck' ),
                    'btnCancel'        => __( 'Cancelar', 'osint-deck' ),
                    'btnContinue'      => __( 'Continuar', 'osint-deck' ),
                    'btnRemoveConfirm' => __( 'Sí, quitar reporte', 'osint-deck' ),
                    'btnSend'          => __( 'Enviar reporte', 'osint-deck' ),
                    'btnNoComment'     => __( 'Sin comentario', 'osint-deck' ),
                    'btnAnonOnlyReport' => __( 'Solo reportar', 'osint-deck' ),
                    'btnNeedLoginForComment' => __( 'Quiero dejar un comentario', 'osint-deck' ),
                    'btnLogin'         => __( 'Iniciar sesión', 'osint-deck' ),
                    'dlgCloseAria'     => __( 'Cerrar', 'osint-deck' ),
                ),
                'turnstile' => array(
                    'enabled'    => Turnstile::is_enabled(),
                    'siteKey'    => Turnstile::is_enabled() ? Turnstile::get_site_key() : '',
                    'modalTitle' => __( 'Verificación de seguridad', 'osint-deck' ),
                    'modalIntro' => __( 'Completá la verificación para continuar usando el deck.', 'osint-deck' ),
                    'close'      => __( 'Cerrar', 'osint-deck' ),
                    'loadFailed' => __( 'No se pudo cargar la verificación. Recargá la página.', 'osint-deck' ),
                ),
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
        DatabaseSchemaMigration::maybe_run();
        // Create database tables
        \OsintDeck\Infrastructure\Persistence\ToolsTable::create_table();
        \OsintDeck\Infrastructure\Persistence\CategoriesTable::create_table();
        \OsintDeck\Infrastructure\Persistence\LogsTable::create_table();
        \OsintDeck\Infrastructure\Persistence\UserHistoryTable::create_table();
        ToolReportsTable::create_table();

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
