<?php
/**
 * Settings - Plugin settings page
 *
 * @package OsintDeck
 */

namespace OsintDeck\Presentation\Admin;

use OsintDeck\Domain\Repository\ToolRepositoryInterface;
use OsintDeck\Domain\Repository\CategoryRepositoryInterface;
use OsintDeck\Domain\Service\NaiveBayesClassifier;
use OsintDeck\Infrastructure\Service\Logger;
use OsintDeck\Infrastructure\Service\GitHubRepairIssueNotifier;
use OsintDeck\Infrastructure\Persistence\DeckActivityDataCleanup;

/**
 * Class Settings
 * 
 * Handles plugin settings
 */
class Settings {

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
     * @var mixed
     */
    private $tld_manager;

    /**
     * Classifier
     *
     * @var NaiveBayesClassifier
     */
    private $classifier;

    /**
     * Import/Export Manager
     *
     * @var ImportExport
     */
    private $import_export_manager;

    /**
     * TLD Manager Admin
     *
     * @var TLDManagerAdmin
     */
    private $tld_manager_admin;

    /**
     * Logger
     *
     * @var Logger
     */
    private $logger;

    /**
     * Constructor
     *
     * @param ToolRepositoryInterface $tool_repository Tool Repository.
     * @param CategoryRepositoryInterface $category_repository Category Repository.
     * @param mixed $tld_manager TLD Manager (Service).
     * @param NaiveBayesClassifier $classifier Classifier.
     */
    public function __construct( ToolRepositoryInterface $tool_repository, CategoryRepositoryInterface $category_repository, $tld_manager, NaiveBayesClassifier $classifier = null ) {
        $this->tool_repository = $tool_repository;
        $this->category_repository = $category_repository;
        $this->tld_manager = $tld_manager;
        $this->classifier = $classifier;
        
        // Initialize Logger
        $this->logger = new Logger();
        
        // Initialize sub-components for tabs
        $this->import_export_manager = new ImportExport( $tool_repository, $category_repository, $this->logger );
        $this->tld_manager_admin = new TLDManagerAdmin( $tld_manager );
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function render() {
        $allowed_tabs = array( 'general', 'design', 'data', 'tlds', 'logs', 'support', 'auth' );
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        
        if ( ! in_array( $active_tab, $allowed_tabs ) ) {
            $active_tab = 'general';
        }

        // Descarga JSON / ZIP backup: antes de cualquier salida HTML (headers limpios).
        if ( 'data' === $active_tab && isset( $_GET['action'] ) && 'export' === $_GET['action'] ) {
            check_admin_referer( 'osint_deck_export' );
            $this->import_export_manager->stream_export_download();
        }
        if ( 'data' === $active_tab && isset( $_GET['action'] ) && 'export_full' === $_GET['action'] ) {
            check_admin_referer( 'osint_deck_export_full' );
            $this->import_export_manager->stream_full_backup_download();
        }

        ?>
        <div class="wrap">
            <h1><?php _e( 'Configuración OSINT Deck', 'osint-deck' ); ?></h1>
            
            <?php settings_errors( 'osint_deck' ); ?>

            <h2 class="nav-tab-wrapper">
                <a href="?page=osint-deck-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Inicio', 'osint-deck' ); ?></a>
                <a href="?page=osint-deck-settings&tab=design" class="nav-tab <?php echo $active_tab == 'design' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Diseño', 'osint-deck' ); ?></a>
                <a href="?page=osint-deck-settings&tab=support" class="nav-tab <?php echo $active_tab == 'support' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Cartas de Asistencia', 'osint-deck' ); ?></a>
                <a href="?page=osint-deck-settings&tab=data" class="nav-tab <?php echo $active_tab == 'data' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Datos', 'osint-deck' ); ?></a>
                <a href="?page=osint-deck-settings&tab=tlds" class="nav-tab <?php echo $active_tab == 'tlds' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Dominios / TLDs', 'osint-deck' ); ?></a>
                <a href="?page=osint-deck-settings&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Logs', 'osint-deck' ); ?></a>
                <a href="?page=osint-deck-settings&tab=auth" class="nav-tab <?php echo $active_tab == 'auth' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Integraciones y Seguridad', 'osint-deck' ); ?></a>
            </h2>

            <div class="tab-content">
                <?php
                switch ( $active_tab ) {
                    case 'design':
                        $this->render_design_tab();
                        break;
                    case 'data':
                        $this->render_data_tab();
                        break;
                    case 'tlds':
                        $this->render_tlds_tab();
                        break;
                    case 'logs':
                        $this->render_logs_tab();
                        break;
                    case 'support':
                        $this->render_support_tab();
                        break;
                    case 'auth':
                        $this->render_auth_tab();
                        break;
                    case 'general':
                    default:
                        $this->render_general_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Pestaña Integraciones y Seguridad (SSO, Turnstile, GitHub).
     */
    private function render_auth_tab() {
        if ( isset( $_GET['osint_deck_reset_github_collab_nudge'], $_GET['_wpnonce'] )
            && '1' === (string) $_GET['osint_deck_reset_github_collab_nudge'] ) {
            if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'osint_deck_reset_github_collab_nudge' ) ) {
                GitHubRepairIssueNotifier::reset_collaboration_nudge_dismissal();
                wp_safe_redirect( admin_url( 'admin.php?page=osint-deck-settings&tab=auth&github_collab_nudge_restored=1#osint-deck-github-section' ) );
                exit;
            }
        }

        if ( isset( $_POST['osint_deck_auth_submit'] ) ) {
            check_admin_referer( 'osint_deck_auth' );
            $enabled = isset( $_POST['sso_enabled'] ) ? (bool) $_POST['sso_enabled'] : false;
            $client_id = isset( $_POST['google_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['google_client_id'] ) ) : '';
            $client_secret = isset( $_POST['google_client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['google_client_secret'] ) ) : '';
            
            update_option( 'osint_deck_sso_enabled', $enabled, false );
            update_option( 'osint_deck_google_client_id', $client_id, false );
            update_option( 'osint_deck_google_client_secret', $client_secret, false );

            $turnstile_on = isset( $_POST['turnstile_enabled'] ) ? (bool) $_POST['turnstile_enabled'] : false;
            $ts_site      = isset( $_POST['turnstile_site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['turnstile_site_key'] ) ) : '';
            $ts_secret    = isset( $_POST['turnstile_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['turnstile_secret_key'] ) ) : '';
            update_option( 'osint_deck_turnstile_enabled', $turnstile_on, false );
            update_option( 'osint_deck_turnstile_site_key', $ts_site, false );
            update_option( 'osint_deck_turnstile_secret_key', $ts_secret, false );

            $github_repair_enabled = isset( $_POST['github_repair_enabled'] )
                && '1' === sanitize_text_field( wp_unslash( $_POST['github_repair_enabled'] ) );
            $github_repair_repo    = isset( $_POST['github_repair_repo'] ) ? sanitize_text_field( wp_unslash( $_POST['github_repair_repo'] ) ) : '';
            $github_repair_repo    = trim( $github_repair_repo, " \t\n\r\0\x0B/" );
            $github_repair_token   = isset( $_POST['github_repair_token'] ) ? trim( (string) wp_unslash( $_POST['github_repair_token'] ) ) : '';

            update_option( 'osint_deck_github_repair_issue_enabled', $github_repair_enabled, false );
            if ( $github_repair_repo !== '' ) {
                update_option( 'osint_deck_github_repair_issue_repo', $github_repair_repo, false );
            }
            if ( $github_repair_token !== '' ) {
                update_option( 'osint_deck_github_repair_issue_token', $github_repair_token, false );
            }
            
            add_settings_error( 'osint_deck', 'auth_saved', __( 'Configuración guardada.', 'osint-deck' ), 'updated' );
        }

        $enabled = (bool) get_option( 'osint_deck_sso_enabled', false );
        $client_id = get_option( 'osint_deck_google_client_id', '' );
        $client_secret = get_option( 'osint_deck_google_client_secret', '' );
        $turnstile_enabled = (bool) get_option( 'osint_deck_turnstile_enabled', false );
        $turnstile_site    = get_option( 'osint_deck_turnstile_site_key', '' );
        $turnstile_secret  = get_option( 'osint_deck_turnstile_secret_key', '' );
        $gh_repair_on      = (bool) get_option( 'osint_deck_github_repair_issue_enabled', false );
        $gh_repair_repo    = (string) get_option( 'osint_deck_github_repair_issue_repo', 'OsintDeck/OsintDeck' );
        $gh_token_set      = get_option( 'osint_deck_github_repair_issue_token', '' ) !== '';
        $gh_nudge_undo     = wp_nonce_url(
            admin_url( 'admin.php?page=osint-deck-settings&tab=auth&osint_deck_reset_github_collab_nudge=1' ),
            'osint_deck_reset_github_collab_nudge',
            '_wpnonce'
        );
        $site_url = site_url();
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'osint_deck_auth' ); ?>

            <?php if ( isset( $_GET['github_collab_nudge_restored'] ) && '1' === (string) $_GET['github_collab_nudge_restored'] ) : ?>
                <div class="notice notice-success is-dismissible" style="max-width:52rem;">
                    <p><?php esc_html_e( 'El aviso de colaboración en Reportes volverá a mostrarse mientras GitHub no esté configurado (o hasta que lo descartés de nuevo).', 'osint-deck' ); ?></p>
                </div>
            <?php endif; ?>

            <p class="description" style="max-width:52rem;"><?php esc_html_e( 'Conectá el deck con Google (cuentas de usuario del catálogo), Cloudflare Turnstile (anti-bots) y, si querés colaborar con reparaciones, GitHub Issues.', 'osint-deck' ); ?></p>

            <h2><?php esc_html_e( 'Autenticación (SSO)', 'osint-deck' ); ?></h2>
            
            <p><?php _e( 'Configura el inicio de sesión con Google para permitir a los usuarios guardar favoritos y reportar herramientas.', 'osint-deck' ); ?></p>
            <p class="description"><?php _e( 'Las cuentas del deck se guardan solo en tablas del plugin; no se crean ni se modifica usuarios ni roles de WordPress.', 'osint-deck' ); ?></p>
            <p class="description"><?php esc_html_e( 'Gracias por configurar el inicio de sesión: los usuarios pueden dejar mensajes más claros en los reportes.', 'osint-deck' ); ?></p>

            <details class="osint-deck-guide-sso" style="max-width:52rem; margin:16px 0; padding:12px 14px; background:#f6f7f7; border:1px solid #c3c4c7; border-radius:4px;">
                <summary style="cursor:pointer; font-weight:600;"><?php esc_html_e( 'Paso a paso: Google SSO (desplegable)', 'osint-deck' ); ?></summary>
            <div class="card" style="max-width: 100%; margin-top: 16px; padding: 20px; background:#fff;">
                <h3 style="margin-top: 0;"><?php _e( 'Guía de Configuración Rápida', 'osint-deck' ); ?></h3>
                <ol>
                    <li><?php printf( __( 'Ve a la %s.', 'osint-deck' ), '<a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console (Credenciales) <span class="dashicons dashicons-external"></span></a>' ); ?></li>
                    <li><?php _e( 'Crea un nuevo proyecto o selecciona uno existente.', 'osint-deck' ); ?></li>
                    <li><?php _e( 'Haz clic en "Crear Credenciales" > "ID de cliente de OAuth".', 'osint-deck' ); ?></li>
                    <li><?php _e( 'Si es necesario, configura la "Pantalla de consentimiento de OAuth" (tipo: Externo).', 'osint-deck' ); ?></li>
                    <li><?php _e( 'En tipo de aplicación, selecciona "Aplicación web".', 'osint-deck' ); ?></li>
                    <li>
                        <?php _e( 'En "Orígenes autorizados de JavaScript", agrega exactamente esta URL:', 'osint-deck' ); ?><br>
                        <code style="display: inline-block; margin: 5px 0; padding: 5px; background: #f0f0f1;"><?php echo esc_url( $site_url ); ?></code><br>
                        <small><?php _e( 'Es importante que no tenga barra al final (/)', 'osint-deck' ); ?></small>
                    </li>
                    <li>
                        <?php _e( 'En "URI de redireccionamiento autorizados", no es estrictamente necesario para el botón "Sign In With Google", pero puedes agregar la misma URL por compatibilidad:', 'osint-deck' ); ?><br>
                        <code style="display: inline-block; margin: 5px 0; padding: 5px; background: #f0f0f1;"><?php echo esc_url( $site_url ); ?></code>
                    </li>
                    <li><?php _e( 'Haz clic en "Crear" y copia el "ID de cliente" y el "Secreto de cliente" generados.', 'osint-deck' ); ?></li>
                </ol>
            </div>
            </details>

            <table class="form-table">
                <tr>
                    <th><label for="sso_enabled"><?php _e( 'Habilitar SSO', 'osint-deck' ); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="sso_enabled" id="sso_enabled" value="1" <?php checked( $enabled, true ); ?>>
                            <?php _e( 'Activar inicio de sesión con Google', 'osint-deck' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="google_client_id"><?php _e( 'Google Client ID', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="text" name="google_client_id" id="google_client_id" value="<?php echo esc_attr( $client_id ); ?>" class="regular-text code">
                        <p class="description"><?php _e( 'Pega aquí el ID de cliente que obtuviste en el paso 8 (termina en .apps.googleusercontent.com).', 'osint-deck' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="google_client_secret"><?php _e( 'Google Client Secret', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="password" name="google_client_secret" id="google_client_secret" value="<?php echo esc_attr( $client_secret ); ?>" class="regular-text code">
                        <p class="description"><?php _e( 'Pega aquí el Secreto de cliente.', 'osint-deck' ); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php _e( 'Cloudflare Turnstile (anti-abuso)', 'osint-deck' ); ?></h2>
            <p><?php _e( 'Protege llamadas públicas del deck (búsqueda, eventos, reportes, SSO) frente a bots. Creá un widget en el dashboard de Cloudflare y pegá las claves.', 'osint-deck' ); ?></p>
            <p>
                <?php
                printf(
                    /* translators: %s: Cloudflare Turnstile URL */
                    __( 'Documentación: %s', 'osint-deck' ),
                    '<a href="https://developers.cloudflare.com/turnstile/" target="_blank" rel="noopener">Turnstile</a>'
                );
                ?>
            </p>
            <p class="description"><?php esc_html_e( 'Gracias por activar Turnstile: reduce abuso sin obligar a los usuarios a puzzles agresivos.', 'osint-deck' ); ?></p>

            <details class="osint-deck-guide-turnstile" style="max-width:52rem; margin:16px 0; padding:12px 14px; background:#f6f7f7; border:1px solid #c3c4c7; border-radius:4px;">
                <summary style="cursor:pointer; font-weight:600;"><?php esc_html_e( 'Paso a paso: Cloudflare Turnstile', 'osint-deck' ); ?></summary>
                <ol style="margin:12px 0 0 1.25em; line-height:1.55;">
                    <li><?php echo wp_kses_post( __( 'Entrá a <strong>Cloudflare Dashboard</strong> → elegí un sitio (o creá uno) → menú <strong>Turnstile</strong>.', 'osint-deck' ) ); ?></li>
                    <li><?php esc_html_e( 'Add widget → nombre descriptivo (ej. OSINT Deck).', 'osint-deck' ); ?></li>
                    <li><?php esc_html_e( 'Modo recomendado «Managed» (invisible o mínimo fricción). Dominios: agregá el dominio donde está WordPress.', 'osint-deck' ); ?></li>
                    <li><?php esc_html_e( 'Tras crear, copiá la Site key y la Secret key en los campos de abajo.', 'osint-deck' ); ?></li>
                    <li><?php esc_html_e( 'Activá «Habilitar Turnstile», guardá, y probá el deck en una ventana privada.', 'osint-deck' ); ?></li>
                </ol>
            </details>

            <table class="form-table">
                <tr>
                    <th><label for="turnstile_enabled"><?php _e( 'Habilitar Turnstile', 'osint-deck' ); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="turnstile_enabled" id="turnstile_enabled" value="1" <?php checked( $turnstile_enabled, true ); ?>>
                            <?php _e( 'Exigir verificación en el front del deck', 'osint-deck' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="turnstile_site_key"><?php _e( 'Site key', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="text" name="turnstile_site_key" id="turnstile_site_key" value="<?php echo esc_attr( $turnstile_site ); ?>" class="regular-text code" autocomplete="off">
                    </td>
                </tr>
                <tr>
                    <th><label for="turnstile_secret_key"><?php _e( 'Secret key', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="password" name="turnstile_secret_key" id="turnstile_secret_key" value="<?php echo esc_attr( $turnstile_secret ); ?>" class="regular-text code" autocomplete="off">
                    </td>
                </tr>
            </table>

            <h2 id="osint-deck-github-section"><?php esc_html_e( 'GitHub (repo público)', 'osint-deck' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Opcional y voluntario: permite que, desde Reportes, el administrador elija si enviar cada reparación al repositorio del proyecto (issue con mensajes de usuarios, nota y JSON). El token es siempre el de la cuenta GitHub de quien configura el sitio.', 'osint-deck' ); ?>
            </p>
            <p class="description">
                <?php esc_html_e( 'Gracias por compartir correcciones: ayuda a que el mismo arreglo llegue a otras instalaciones.', 'osint-deck' ); ?>
            </p>
            <table class="form-table">
                <tr>
                    <th><label for="github_repair_enabled"><?php esc_html_e( 'Activar', 'osint-deck' ); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="github_repair_enabled" id="github_repair_enabled" value="1" <?php checked( $gh_repair_on, true ); ?>>
                            <?php esc_html_e( 'Crear issue al marcar «reparada» en Reportes', 'osint-deck' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="github_repair_repo"><?php esc_html_e( 'Repo', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="text" name="github_repair_repo" id="github_repair_repo" value="<?php echo esc_attr( $gh_repair_repo ); ?>" class="regular-text code" placeholder="OsintDeck/OsintDeck" autocomplete="off">
                        <p class="description"><?php esc_html_e( 'owner/repo. GitHub Enterprise: constante OSINT_DECK_GITHUB_API_URL en wp-config.', 'osint-deck' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="github_repair_token"><?php esc_html_e( 'Token', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="password" name="github_repair_token" id="github_repair_token" value="" class="regular-text code" autocomplete="off" placeholder="<?php echo esc_attr( $gh_token_set ? __( 'Vacío = no cambiar', 'osint-deck' ) : __( 'Pegá aquí el token que generó GitHub', 'osint-deck' ) ); ?>">
                        <p class="description">
                            <?php esc_html_e( 'No es tu contraseña de GitHub: es una clave larga que creás abajo (paso a paso). Cada sitio WordPress necesita su propia configuración aquí o la variable OSINT_DECK_GITHUB_ISSUES_TOKEN en el servidor.', 'osint-deck' ); ?>
                        </p>
                        <div class="notice notice-warning inline" style="margin:10px 0; max-width:44rem;">
                            <p style="margin:0.35em 0;">
                                <?php esc_html_e( 'Nunca pegues el token en chats, issues públicos ni código. Si se filtró, revocalo en GitHub y generá uno nuevo.', 'osint-deck' ); ?>
                            </p>
                        </div>
                        <details class="osint-deck-github-token-steps" style="max-width:44rem; margin-top:10px; padding:10px 12px; background:#f6f7f7; border:1px solid #c3c4c7; border-radius:4px;">
                            <summary style="cursor:pointer; font-weight:600;">
                                <?php esc_html_e( 'Paso a paso: crear el token en GitHub (fine-grained, recomendado)', 'osint-deck' ); ?>
                            </summary>
                            <ol style="margin:12px 0 0 1.25em; line-height:1.55;">
                                <li><?php echo wp_kses_post( __( 'Iniciá sesión en <strong>github.com</strong> con la cuenta que tenga acceso al repo.', 'osint-deck' ) ); ?></li>
                                <li><?php echo wp_kses_post( __( 'Arriba a la derecha: tu foto → <strong>Settings</strong> (Configuración de la cuenta).', 'osint-deck' ) ); ?></li>
                                <li><?php echo wp_kses_post( __( 'Menú izquierdo, abajo del todo: <strong>Developer settings</strong>.', 'osint-deck' ) ); ?></li>
                                <li><?php echo wp_kses_post( __( '<strong>Personal access tokens</strong> → <strong>Fine-grained tokens</strong> → <strong>Generate new token</strong>.', 'osint-deck' ) ); ?></li>
                                <li><?php esc_html_e( 'Nombre: por ejemplo «OSINT Deck issues». Elegí una caducidad.', 'osint-deck' ); ?></li>
                                <li><?php echo wp_kses_post( __( '<strong>Repository access</strong>: «Only select repositories» y elegí el mismo repo que pusiste arriba (ej. OsintDeck/OsintDeck).', 'osint-deck' ) ); ?></li>
                                <li><?php echo wp_kses_post( __( '<strong>Permissions</strong> → <strong>Repository permissions</strong> → buscá <strong>Issues</strong> y poné <strong>Read and write</strong>.', 'osint-deck' ) ); ?></li>
                                <li><?php esc_html_e( 'Generate token. GitHub muestra el texto una sola vez: copiálo entero.', 'osint-deck' ); ?></li>
                                <li><?php esc_html_e( 'Pegalo en el campo «Token» de esta pestaña (Integraciones y Seguridad) y pulsá «Guardar Cambios».', 'osint-deck' ); ?></li>
                                <li><?php esc_html_e( 'Probalo: Reportes → Marcar reparada (con reportes abiertos). Deberías ver un enlace al issue o un mensaje de error de la API.', 'osint-deck' ); ?></li>
                            </ol>
                            <p class="description" style="margin:12px 0 0;">
                                <?php esc_html_e( 'Alternativa token «classic»:', 'osint-deck' ); ?>
                                <?php echo wp_kses_post( __( 'Developer settings → <strong>Tokens (classic)</strong> → Generate; en repo público suele alcanzar el permiso <strong>public_repo</strong>.', 'osint-deck' ) ); ?>
                            </p>
                        </details>
                    </td>
                </tr>
                <?php if ( GitHubRepairIssueNotifier::is_collaboration_nudge_dismissed() ) : ?>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Aviso en Reportes', 'osint-deck' ); ?></th>
                    <td>
                        <p class="description" style="margin-top:0;"><?php esc_html_e( 'Ocultaste el recordatorio para configurar GitHub desde la pantalla Reportes.', 'osint-deck' ); ?></p>
                        <p>
                            <a href="<?php echo esc_url( $gh_nudge_undo ); ?>" class="button button-secondary">
                                <?php esc_html_e( 'Volver a mostrar el aviso en Reportes', 'osint-deck' ); ?>
                            </a>
                        </p>
                        <p class="description"><?php esc_html_e( 'Solo afecta el cartel de colaboración; no cambia el token ni el envío de issues al marcar reparada.', 'osint-deck' ); ?></p>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <p class="submit">
                <input type="submit" name="osint_deck_auth_submit" class="button button-primary" value="<?php _e( 'Guardar Cambios', 'osint-deck' ); ?>">
            </p>
        </form>
        <?php
    }

    /**
     * Pestaña Inicio: resumen y enlaces (sin formulario).
     */
    private function render_general_tab() {
        $version = defined( 'OSINT_DECK_VERSION' ) ? OSINT_DECK_VERSION : '';
        $base    = admin_url( 'admin.php?page=osint-deck-settings' );
        ?>
        <div class="card" style="max-width:52rem; padding: 0 1.5em 1.5em;">
            <h2 style="margin-top:1em;"><?php esc_html_e( 'Inicio', 'osint-deck' ); ?></h2>
            <p class="description" style="font-size:14px;">
                <?php esc_html_e( 'Desde aquí podés ir a las demás secciones. La configuración técnica está repartida por pestañas según el tema (diseño en el front, integraciones externas, datos, registros, etc.).', 'osint-deck' ); ?>
            </p>
            <?php if ( $version !== '' ) : ?>
                <p><strong><?php esc_html_e( 'Versión instalada:', 'osint-deck' ); ?></strong> <code><?php echo esc_html( $version ); ?></code></p>
            <?php endif; ?>
            <h3 style="margin-top:1.25em;"><?php esc_html_e( 'Accesos rápidos', 'osint-deck' ); ?></h3>
            <ul style="list-style:disc; margin-left:1.35em; line-height:1.7;">
                <li><a href="<?php echo esc_url( $base . '&tab=design' ); ?>"><?php esc_html_e( 'Diseño', 'osint-deck' ); ?></a> — <?php esc_html_e( 'barra sticky y tema claro/oscuro del shortcode', 'osint-deck' ); ?></li>
                <li><a href="<?php echo esc_url( $base . '&tab=auth' ); ?>"><?php esc_html_e( 'Integraciones y Seguridad', 'osint-deck' ); ?></a> — <?php esc_html_e( 'Google SSO, Cloudflare Turnstile, GitHub (issues desde Reportes)', 'osint-deck' ); ?></li>
                <li><a href="<?php echo esc_url( $base . '&tab=support' ); ?>"><?php esc_html_e( 'Cartas de Asistencia', 'osint-deck' ); ?></a></li>
                <li><a href="<?php echo esc_url( $base . '&tab=data' ); ?>"><?php esc_html_e( 'Datos', 'osint-deck' ); ?></a></li>
                <li><a href="<?php echo esc_url( $base . '&tab=tlds' ); ?>"><?php esc_html_e( 'Dominios / TLDs', 'osint-deck' ); ?></li>
                <li><a href="<?php echo esc_url( $base . '&tab=logs' ); ?>"><?php esc_html_e( 'Logs', 'osint-deck' ); ?></a> — <?php esc_html_e( 'registro de eventos y retención', 'osint-deck' ); ?></li>
            </ul>
        </div>
        <?php
    }

    /**
     * Pestaña Diseño: barra sticky y sincronización de tema con el sitio.
     */
    private function render_design_tab() {
        if ( isset( $_POST['osint_deck_design_submit'] ) ) {
            check_admin_referer( 'osint_deck_design' );
            $this->save_design_settings();
        }

        $theme_mode         = get_option( 'osint_deck_theme_mode', 'auto' );
        $theme_selector     = get_option( 'osint_deck_theme_selector', '[data-site-skin]' );
        $theme_token_light  = get_option( 'osint_deck_theme_token_light', 'light' );
        $theme_token_dark   = get_option( 'osint_deck_theme_token_dark', 'dark' );
        $chatbar_sticky_top = (int) get_option( 'osint_deck_chatbar_sticky_top', 0 );
        $chatbar_sticky_on  = (bool) get_option( 'osint_deck_chatbar_sticky_enabled', true );
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'osint_deck_design' ); ?>

            <h2><?php esc_html_e( 'Diseño del deck', 'osint-deck' ); ?></h2>
            <p class="description" style="max-width:52rem; margin-bottom:16px;">
                <?php esc_html_e( 'Estas opciones afectan solo al shortcode del deck en el front: cómo se fija la barra al hacer scroll y cómo el deck detecta si el sitio está en modo claro u oscuro.', 'osint-deck' ); ?>
            </p>

            <details class="osint-deck-design-guide" style="max-width:52rem; margin:0 0 20px; padding:12px 14px; background:#f6f7f7; border:1px solid #c3c4c7; border-radius:4px;">
                <summary style="cursor:pointer; font-weight:600;"><?php esc_html_e( 'Instrucciones: cómo obtener selector y valores de tema', 'osint-deck' ); ?></summary>
                <div style="margin-top:12px; line-height:1.55;">
                    <p><strong><?php esc_html_e( 'Modo de tema «Auto»', 'osint-deck' ); ?></strong></p>
                    <ol style="margin-left:1.25em;">
                        <li><?php esc_html_e( 'Abrí en el navegador la página pública donde está el deck.', 'osint-deck' ); ?></li>
                        <li><?php echo wp_kses_post( __( 'Abrí las herramientas de desarrollo (F12 o clic derecho → «Inspeccionar»).', 'osint-deck' ) ); ?></li>
                        <li><?php esc_html_e( 'En modo claro del sitio, en la pestaña «Elementos», buscá qué nodo del HTML cambia entre claro y oscuro: suele ser <html>, <body> o un contenedor del tema.', 'osint-deck' ); ?></li>
                        <li><?php echo wp_kses_post( __( 'Anotá el <strong>atributo</strong> que indica el tema, por ejemplo <code>data-theme</code>, <code>data-site-skin</code> o <code>class</code> con valores como <code>light</code>/<code>dark</code>.', 'osint-deck' ) ); ?></li>
                        <li><?php echo wp_kses_post( __( '<strong>Selector CSS</strong>: escribí un selector único que apunte a ese elemento, p. ej. <code>[data-site-skin]</code>, <code>html</code> o <code>body</code>. Si usás <code>class</code>, puede ser <code>body.dark-mode</code> (solo ejemplo).', 'osint-deck' ) ); ?></li>
                        <li><?php esc_html_e( 'Cambiá el tema del sitio (si el tema lo permite) y mirá el valor del atributo en modo claro y en modo oscuro: esos textos son los «Token tema claro» y «Token tema oscuro».', 'osint-deck' ); ?></li>
                        <li><?php esc_html_e( 'Si el tema no expone un atributo claro, probá «Siempre claro» o «Siempre oscuro» para forzar el aspecto del deck.', 'osint-deck' ); ?></li>
                    </ol>
                    <p><strong><?php esc_html_e( 'Barra sticky y separación en píxeles', 'osint-deck' ); ?></strong></p>
                    <ol style="margin-left:1.25em;">
                        <li><?php esc_html_e( 'Activá la barra fija y hacé scroll: si la barra del deck queda debajo de una cabecera fija del tema, necesitás un offset.', 'osint-deck' ); ?></li>
                        <li><?php echo wp_kses_post( __( 'Medí la altura visible de la cabecera fija (por ejemplo inspeccionando el header y leyendo su altura en píxeles, o probando valores como 56, 64 u 80 hasta que la barra quede bien alineada).', 'osint-deck' ) ); ?></li>
                        <li><?php esc_html_e( 'Ese número es el que va en «Separación de la barra» (px).', 'osint-deck' ); ?></li>
                    </ol>
                </div>
            </details>

            <h3><?php esc_html_e( 'Barra de búsqueda (sticky)', 'osint-deck' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label for="chatbar_sticky_enabled"><?php esc_html_e( 'Barra fija al hacer scroll (sticky)', 'osint-deck' ); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="chatbar_sticky_enabled" id="chatbar_sticky_enabled" value="1" <?php checked( $chatbar_sticky_on, true ); ?>>
                            <?php esc_html_e( 'Mantener la barra de búsqueda y los filtros visibles al desplazarse por la página', 'osint-deck' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Si lo desactivás, la barra se mueve con el contenido. La separación en píxeles solo aplica cuando esta opción está activa.', 'osint-deck' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="chatbar_sticky_top"><?php esc_html_e( 'Separación de la barra de búsqueda (px)', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="number" name="chatbar_sticky_top" id="chatbar_sticky_top" value="<?php echo esc_attr( $chatbar_sticky_top ); ?>" class="small-text" min="0" max="500" step="1">
                        <p class="description"><?php esc_html_e( 'Solo con sticky activo: si el tema tiene cabecera fija y la barra queda tapada al hacer scroll, indicá cuántos píxeles debe bajar (por ejemplo 65). 0 = borde superior del viewport.', 'osint-deck' ); ?></p>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e( 'Sistema de temas', 'osint-deck' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label for="theme_mode"><?php esc_html_e( 'Modo de tema', 'osint-deck' ); ?></label></th>
                    <td>
                        <select name="theme_mode" id="theme_mode">
                            <option value="auto" <?php selected( $theme_mode, 'auto' ); ?>><?php esc_html_e( 'Auto (sincronizar con el sitio)', 'osint-deck' ); ?></option>
                            <option value="light" <?php selected( $theme_mode, 'light' ); ?>><?php esc_html_e( 'Siempre claro', 'osint-deck' ); ?></option>
                            <option value="dark" <?php selected( $theme_mode, 'dark' ); ?>><?php esc_html_e( 'Siempre oscuro', 'osint-deck' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( '«Auto» lee el atributo del selector indicado abajo; «Siempre claro/oscuro» ignora el tema del sitio.', 'osint-deck' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="theme_selector"><?php esc_html_e( 'Selector CSS del tema', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="text" name="theme_selector" id="theme_selector" value="<?php echo esc_attr( $theme_selector ); ?>" class="regular-text code">
                        <p class="description"><?php esc_html_e( 'Elemento del DOM que lleva el atributo de modo (ej. [data-site-skin]). Ver instrucciones desplegables arriba.', 'osint-deck' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="theme_token_light"><?php esc_html_e( 'Token tema claro', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="text" name="theme_token_light" id="theme_token_light" value="<?php echo esc_attr( $theme_token_light ); ?>" class="regular-text code">
                        <p class="description"><?php esc_html_e( 'Valor del atributo cuando el sitio está en modo claro (ej. light).', 'osint-deck' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="theme_token_dark"><?php esc_html_e( 'Token tema oscuro', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="text" name="theme_token_dark" id="theme_token_dark" value="<?php echo esc_attr( $theme_token_dark ); ?>" class="regular-text code">
                        <p class="description"><?php esc_html_e( 'Valor del atributo cuando el sitio está en modo oscuro (ej. dark).', 'osint-deck' ); ?></p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="osint_deck_design_submit" class="button button-primary" value="<?php esc_attr_e( 'Guardar Cambios', 'osint-deck' ); ?>">
            </p>
        </form>
        <?php
    }

    /**
     * Pestaña Cartas de Asistencia (subpestañas Contacto / Prevención / Comunidad).
     */
    private function render_support_tab() {
        if ( isset( $_POST['osint_deck_assist_contact_submit'] ) ) {
            check_admin_referer( 'osint_deck_assist_contact' );
            $this->save_assist_contact_settings();
        }
        if ( isset( $_POST['osint_deck_assist_prevention_submit'] ) ) {
            check_admin_referer( 'osint_deck_assist_prevention' );
            $this->save_assist_prevention_settings();
        }
        if ( isset( $_POST['osint_deck_assist_community_submit'] ) ) {
            check_admin_referer( 'osint_deck_assist_community' );
            $this->save_assist_community_settings();
        }

        $assist_sub = isset( $_GET['assist_subtab'] ) ? sanitize_key( $_GET['assist_subtab'] ) : 'contact';
        if ( ! in_array( $assist_sub, array( 'contact', 'prevention', 'community' ), true ) ) {
            $assist_sub = 'contact';
        }

        $base_support = admin_url( 'admin.php?page=osint-deck-settings&tab=support' );

        $title         = get_option( 'osint_deck_help_card_title', 'Soporte OSINT Deck' );
        $desc          = get_option( 'osint_deck_help_card_desc', '¿Encontraste un error o necesitas reportar algo? Contactanos directamente.' );
        $buttons_json  = get_option( 'osint_deck_help_buttons', '[]' );
        $buttons       = json_decode( $buttons_json, true );
        if ( ! is_array( $buttons ) ) {
            $buttons = array();
        }

        $crisis_title   = get_option( 'osint_deck_crisis_card_title', 'Apoyo emocional' );
        $crisis_desc    = get_option( 'osint_deck_crisis_card_desc', 'Si estás en crisis o con ideas de hacerte daño, no estás solo/a. Estos recursos suelen ser gratuitos y confidenciales.' );
        $crisis_bt_json = get_option( 'osint_deck_crisis_buttons', '' );
        if ( ! is_string( $crisis_bt_json ) || '' === trim( $crisis_bt_json ) ) {
            $crisis_bt_json = wp_json_encode(
                array(
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
                )
            );
        }
        $crisis_buttons = json_decode( $crisis_bt_json, true );
        if ( ! is_array( $crisis_buttons ) ) {
            $crisis_buttons = array();
        }

        $community_disc_url = (string) apply_filters( 'osint_deck_community_discussions_url', 'https://osintdeck.github.io/discussions.html' );
        $community_title    = get_option( 'osint_deck_community_card_title', 'Sugerencias y comunidad' );
        $community_desc     = get_option(
            'osint_deck_community_card_desc',
            'Proponé herramientas nuevas, ideas de mejora o participá en la comunidad. Las conversaciones se gestionan en GitHub Discussions.'
        );
        $community_bt_json  = get_option( 'osint_deck_community_buttons', '' );
        if ( ! is_string( $community_bt_json ) || '' === trim( $community_bt_json ) ) {
            $community_bt_json = wp_json_encode(
                array(
                    array(
                        'label' => 'Sugerir una herramienta',
                        'url'   => $community_disc_url,
                        'icon'  => 'ri-add-box-line',
                    ),
                    array(
                        'label' => '💡 Compartir ideas',
                        'url'   => $community_disc_url,
                        'icon'  => 'ri-lightbulb-line',
                    ),
                    array(
                        'label' => '❓ Hacer preguntas',
                        'url'   => $community_disc_url,
                        'icon'  => 'ri-question-answer-line',
                    ),
                    array(
                        'label' => '🤝 Colaborar',
                        'url'   => $community_disc_url,
                        'icon'  => 'ri-team-line',
                    ),
                )
            );
        }
        $community_buttons = json_decode( $community_bt_json, true );
        if ( ! is_array( $community_buttons ) ) {
            $community_buttons = array();
        }

        $assist_button_styles = '
            .osint-assist-subwrap .osint-button-row{background:#f9f9f9;border:1px solid #ccc;padding:10px;margin-bottom:10px;border-radius:4px;display:flex;gap:10px;align-items:flex-start}
            .osint-assist-subwrap .osint-button-row .field-group{display:flex;flex-direction:column;gap:4px;flex:1}
            .osint-assist-subwrap .osint-button-row label{font-size:12px;font-weight:600;color:#666}
            .osint-assist-subwrap .osint-button-row input{width:100%}
            .osint-assist-subwrap .osint-remove-row{margin-top:20px!important;color:#b32d2e!important;border-color:#b32d2e!important}
            .osint-assist-subwrap .osint-remove-row:hover{background:#b32d2e!important;color:#fff!important}
        ';
        ?>
        <h2 style="margin-top:0;"><?php esc_html_e( 'Cartas de Asistencia', 'osint-deck' ); ?></h2>
        <p class="description" style="max-width:52rem;"><?php esc_html_e( 'Configurá las tarjetas especiales que el buscador puede mostrar por intención del usuario: ayuda general, recursos de bienestar / emergencia, o comunidad y sugerencias de herramientas.', 'osint-deck' ); ?></p>

        <div class="nav-tab-wrapper" style="border-bottom:1px solid #c3c4c7;padding:0;margin:1.25em 0 0;float:none;width:100%;max-width:52rem;">
            <a href="<?php echo esc_url( $base_support . '&assist_subtab=contact' ); ?>" class="nav-tab <?php echo 'contact' === $assist_sub ? 'nav-tab-active' : ''; ?>" style="margin-bottom:-1px;"><?php esc_html_e( 'Contacto y Soporte', 'osint-deck' ); ?></a>
            <a href="<?php echo esc_url( $base_support . '&assist_subtab=prevention' ); ?>" class="nav-tab <?php echo 'prevention' === $assist_sub ? 'nav-tab-active' : ''; ?>" style="margin-bottom:-1px;"><?php esc_html_e( 'Prevención / Emergencia', 'osint-deck' ); ?></a>
            <a href="<?php echo esc_url( $base_support . '&assist_subtab=community' ); ?>" class="nav-tab <?php echo 'community' === $assist_sub ? 'nav-tab-active' : ''; ?>" style="margin-bottom:-1px;"><?php esc_html_e( 'Comunidad y sugerencias', 'osint-deck' ); ?></a>
        </div>

        <div class="osint-assist-subwrap" style="max-width:52rem;margin-top:1.25em;">
        <?php if ( 'contact' === $assist_sub ) : ?>
        <form method="post" action="<?php echo esc_url( $base_support . '&assist_subtab=contact' ); ?>">
            <?php wp_nonce_field( 'osint_deck_assist_contact' ); ?>

            <h3><?php esc_html_e( 'Contacto y Soporte', 'osint-deck' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Configura la carta estándar de ayuda (se muestra cuando la búsqueda indica que el usuario pide ayuda o soporte).', 'osint-deck' ); ?></p>

            <table class="form-table">
                <tr>
                    <th><label for="help_card_title"><?php esc_html_e( 'Título', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="text" name="help_card_title" id="help_card_title" value="<?php echo esc_attr( $title ); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="help_card_desc"><?php esc_html_e( 'Descripción', 'osint-deck' ); ?></label></th>
                    <td>
                        <textarea name="help_card_desc" id="help_card_desc" rows="3" class="large-text code"><?php echo esc_textarea( $desc ); ?></textarea>
                    </td>
                </tr>
            </table>

            <h4><?php esc_html_e( 'Botones de acción', 'osint-deck' ); ?></h4>
            <p class="description"><?php esc_html_e( 'Enlaces o teléfonos (ej. tel:135) mostrados en la carta.', 'osint-deck' ); ?></p>

            <div id="osint-deck-buttons-container"></div>
            <button type="button" class="button" id="osint-add-button-row"><?php esc_html_e( 'Añadir botón', 'osint-deck' ); ?></button>
            <input type="hidden" name="help_buttons_json" id="help_buttons_json" value="<?php echo esc_attr( is_string( $buttons_json ) ? $buttons_json : wp_json_encode( $buttons ) ); ?>">

            <style><?php echo $assist_button_styles; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin CSS only ?></style>
            <script>
            jQuery(document).ready(function($) {
                var assistBtnI18n = <?php echo wp_json_encode( array(
                    'lblBtn'  => __( 'Texto del botón', 'osint-deck' ),
                    'lblUrl'  => __( 'URL', 'osint-deck' ),
                    'lblIcon' => __( 'Icono (RemixIcon)', 'osint-deck' ),
                    'phBtn'   => __( 'Ej: Contactar soporte', 'osint-deck' ),
                    'phUrl'   => __( 'https://… o tel:…', 'osint-deck' ),
                    'phIcon'  => 'ri-customer-service-2-fill',
                    'icons'   => __( 'Iconos', 'osint-deck' ),
                ) ); ?>;
                var container = $('#osint-deck-buttons-container');
                var jsonInput = $('#help_buttons_json');
                var buttons = <?php echo wp_json_encode( $buttons ); ?>;
                function renderRows() {
                    container.empty();
                    buttons.forEach(function(btn, index) {
                        var row = $('<div class="osint-button-row" data-index="' + index + '"></div>');
                        var fg1 = $('<div class="field-group"></div>');
                        fg1.append($('<label></label>').text(assistBtnI18n.lblBtn));
                        fg1.append($('<input type="text" class="btn-label" />').attr('placeholder', assistBtnI18n.phBtn).val(btn.label || ''));
                        var fg2 = $('<div class="field-group"></div>');
                        fg2.append($('<label></label>').text(assistBtnI18n.lblUrl));
                        fg2.append($('<input type="text" class="btn-url" />').attr('placeholder', assistBtnI18n.phUrl).val(btn.url || ''));
                        var fg3 = $('<div class="field-group" style="flex:0 0 150px;"></div>');
                        fg3.append($('<label></label>').text(assistBtnI18n.lblIcon));
                        fg3.append($('<input type="text" class="btn-icon" />').attr('placeholder', assistBtnI18n.phIcon).val(btn.icon || ''));
                        fg3.append($('<small></small>').append($('<a href="https://remixicon.com/" target="_blank" rel="noopener"></a>').text(assistBtnI18n.icons)));
                        row.append(fg1, fg2, fg3, $('<button type="button" class="button osint-remove-row"><span class="dashicons dashicons-trash"></span></button>'));
                        container.append(row);
                    });
                    updateJson();
                }
                function updateJson() {
                    var newButtons = [];
                    container.find('.osint-button-row').each(function() {
                        var row = $(this);
                        newButtons.push({
                            label: row.find('.btn-label').val(),
                            url: row.find('.btn-url').val(),
                            icon: row.find('.btn-icon').val()
                        });
                    });
                    jsonInput.val(JSON.stringify(newButtons));
                }
                $('#osint-add-button-row').on('click', function() {
                    buttons.push({ label: '', url: '', icon: '' });
                    renderRows();
                });
                container.on('click', '.osint-remove-row', function() {
                    var index = $(this).closest('.osint-button-row').data('index');
                    buttons.splice(index, 1);
                    renderRows();
                });
                container.on('input', 'input', function() { updateJson(); });
                renderRows();
            });
            </script>

            <p class="submit">
                <input type="submit" name="osint_deck_assist_contact_submit" class="button button-primary" value="<?php esc_attr_e( 'Guardar Cambios', 'osint-deck' ); ?>">
            </p>
        </form>

        <?php elseif ( 'prevention' === $assist_sub ) : ?>
        <form method="post" action="<?php echo esc_url( $base_support . '&assist_subtab=prevention' ); ?>">
            <?php wp_nonce_field( 'osint_deck_assist_prevention' ); ?>

            <h3><?php esc_html_e( 'Prevención / Emergencia', 'osint-deck' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Carta prioritaria cuando el clasificador Naive Bayes (datos de entrenamiento) o las reglas rápidas del buscador detectan intención de crisis o salud mental. Adaptá textos, botones y teléfonos a tu país o región.', 'osint-deck' ); ?></p>

            <table class="form-table">
                <tr>
                    <th><label for="crisis_card_title"><?php esc_html_e( 'Título de la carta', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="text" name="crisis_card_title" id="crisis_card_title" value="<?php echo esc_attr( $crisis_title ); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="crisis_card_desc"><?php esc_html_e( 'Descripción', 'osint-deck' ); ?></label></th>
                    <td>
                        <textarea name="crisis_card_desc" id="crisis_card_desc" rows="4" class="large-text"><?php echo esc_textarea( $crisis_desc ); ?></textarea>
                    </td>
                </tr>
            </table>

            <h4><?php esc_html_e( 'Botones de acción', 'osint-deck' ); ?></h4>
            <p class="description"><?php esc_html_e( 'Líneas de ayuda, sitios oficiales o tel:…', 'osint-deck' ); ?></p>
            <div id="osint-deck-crisis-buttons-container"></div>
            <button type="button" class="button" id="osint-add-crisis-button-row"><?php esc_html_e( 'Añadir botón', 'osint-deck' ); ?></button>
            <input type="hidden" name="crisis_buttons_json" id="crisis_buttons_json" value="<?php echo esc_attr( $crisis_bt_json ); ?>">

            <style><?php echo $assist_button_styles; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
            <script>
            jQuery(document).ready(function($) {
                var assistBtnI18n = <?php echo wp_json_encode( array(
                    'lblBtn'  => __( 'Texto del botón', 'osint-deck' ),
                    'lblUrl'  => __( 'URL', 'osint-deck' ),
                    'lblIcon' => __( 'Icono (RemixIcon)', 'osint-deck' ),
                    'phBtn'   => __( 'Ej: Línea de ayuda', 'osint-deck' ),
                    'phUrl'   => __( 'tel:… o https://…', 'osint-deck' ),
                    'phIcon'  => 'ri-phone-line',
                    'icons'   => __( 'Iconos', 'osint-deck' ),
                ) ); ?>;
                var container = $('#osint-deck-crisis-buttons-container');
                var jsonInput = $('#crisis_buttons_json');
                var buttons = <?php echo wp_json_encode( $crisis_buttons ); ?>;
                function renderRows() {
                    container.empty();
                    buttons.forEach(function(btn, index) {
                        var row = $('<div class="osint-button-row" data-index="' + index + '"></div>');
                        var fg1 = $('<div class="field-group"></div>');
                        fg1.append($('<label></label>').text(assistBtnI18n.lblBtn));
                        fg1.append($('<input type="text" class="btn-label" />').attr('placeholder', assistBtnI18n.phBtn).val(btn.label || ''));
                        var fg2 = $('<div class="field-group"></div>');
                        fg2.append($('<label></label>').text(assistBtnI18n.lblUrl));
                        fg2.append($('<input type="text" class="btn-url" />').attr('placeholder', assistBtnI18n.phUrl).val(btn.url || ''));
                        var fg3 = $('<div class="field-group" style="flex:0 0 150px;"></div>');
                        fg3.append($('<label></label>').text(assistBtnI18n.lblIcon));
                        fg3.append($('<input type="text" class="btn-icon" />').attr('placeholder', assistBtnI18n.phIcon).val(btn.icon || ''));
                        fg3.append($('<small></small>').append($('<a href="https://remixicon.com/" target="_blank" rel="noopener"></a>').text(assistBtnI18n.icons)));
                        row.append(fg1, fg2, fg3, $('<button type="button" class="button osint-remove-row"><span class="dashicons dashicons-trash"></span></button>'));
                        container.append(row);
                    });
                    updateJson();
                }
                function updateJson() {
                    var newButtons = [];
                    container.find('.osint-button-row').each(function() {
                        var row = $(this);
                        newButtons.push({
                            label: row.find('.btn-label').val(),
                            url: row.find('.btn-url').val(),
                            icon: row.find('.btn-icon').val()
                        });
                    });
                    jsonInput.val(JSON.stringify(newButtons));
                }
                $('#osint-add-crisis-button-row').on('click', function() {
                    buttons.push({ label: '', url: '', icon: '' });
                    renderRows();
                });
                container.on('click', '.osint-remove-row', function() {
                    var index = $(this).closest('.osint-button-row').data('index');
                    buttons.splice(index, 1);
                    renderRows();
                });
                container.on('input', 'input', function() { updateJson(); });
                renderRows();
            });
            </script>

            <p class="submit">
                <input type="submit" name="osint_deck_assist_prevention_submit" class="button button-primary" value="<?php esc_attr_e( 'Guardar Cambios', 'osint-deck' ); ?>">
            </p>
        </form>

        <?php else : ?>
        <form method="post" action="<?php echo esc_url( $base_support . '&assist_subtab=community' ); ?>">
            <?php wp_nonce_field( 'osint_deck_assist_community' ); ?>

            <h3><?php esc_html_e( 'Comunidad y sugerencias', 'osint-deck' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Carta que aparece cuando el usuario pide sugerir herramientas, proponer mejoras para el deck o participar en la comunidad. Por defecto los enlaces apuntan al portal de discusiones (GitHub); podés cambiarlos o añadir botones.', 'osint-deck' ); ?></p>
            <p class="description">
                <?php
                printf(
                    /* translators: %s: filter name */
                    esc_html__( 'La URL base por código sigue pudiendo sobreescribirse con el filtro %s si lo usás en el tema.', 'osint-deck' ),
                    '<code>osint_deck_community_discussions_url</code>'
                );
                ?>
            </p>

            <table class="form-table">
                <tr>
                    <th><label for="community_card_title"><?php esc_html_e( 'Título de la carta', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="text" name="community_card_title" id="community_card_title" value="<?php echo esc_attr( $community_title ); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="community_card_desc"><?php esc_html_e( 'Descripción', 'osint-deck' ); ?></label></th>
                    <td>
                        <textarea name="community_card_desc" id="community_card_desc" rows="4" class="large-text"><?php echo esc_textarea( $community_desc ); ?></textarea>
                    </td>
                </tr>
            </table>

            <h4><?php esc_html_e( 'Botones de acción', 'osint-deck' ); ?></h4>
            <p class="description"><?php esc_html_e( 'Enlaces hacia discusiones, issues o documentación.', 'osint-deck' ); ?></p>
            <div id="osint-deck-community-buttons-container"></div>
            <button type="button" class="button" id="osint-add-community-button-row"><?php esc_html_e( 'Añadir botón', 'osint-deck' ); ?></button>
            <input type="hidden" name="community_buttons_json" id="community_buttons_json" value="<?php echo esc_attr( $community_bt_json ); ?>">

            <style><?php echo $assist_button_styles; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
            <script>
            jQuery(document).ready(function($) {
                var assistBtnI18n = <?php echo wp_json_encode( array(
                    'lblBtn'  => __( 'Texto del botón', 'osint-deck' ),
                    'lblUrl'  => __( 'URL', 'osint-deck' ),
                    'lblIcon' => __( 'Icono (RemixIcon)', 'osint-deck' ),
                    'phBtn'   => __( 'Ej: Sugerir herramienta', 'osint-deck' ),
                    'phUrl'   => __( 'https://…', 'osint-deck' ),
                    'phIcon'  => 'ri-discuss-line',
                    'icons'   => __( 'Iconos', 'osint-deck' ),
                ) ); ?>;
                var container = $('#osint-deck-community-buttons-container');
                var jsonInput = $('#community_buttons_json');
                var buttons = <?php echo wp_json_encode( $community_buttons ); ?>;
                function renderRows() {
                    container.empty();
                    buttons.forEach(function(btn, index) {
                        var row = $('<div class="osint-button-row" data-index="' + index + '"></div>');
                        var fg1 = $('<div class="field-group"></div>');
                        fg1.append($('<label></label>').text(assistBtnI18n.lblBtn));
                        fg1.append($('<input type="text" class="btn-label" />').attr('placeholder', assistBtnI18n.phBtn).val(btn.label || ''));
                        var fg2 = $('<div class="field-group"></div>');
                        fg2.append($('<label></label>').text(assistBtnI18n.lblUrl));
                        fg2.append($('<input type="text" class="btn-url" />').attr('placeholder', assistBtnI18n.phUrl).val(btn.url || ''));
                        var fg3 = $('<div class="field-group" style="flex:0 0 150px;"></div>');
                        fg3.append($('<label></label>').text(assistBtnI18n.lblIcon));
                        fg3.append($('<input type="text" class="btn-icon" />').attr('placeholder', assistBtnI18n.phIcon).val(btn.icon || ''));
                        fg3.append($('<small></small>').append($('<a href="https://remixicon.com/" target="_blank" rel="noopener"></a>').text(assistBtnI18n.icons)));
                        row.append(fg1, fg2, fg3, $('<button type="button" class="button osint-remove-row"><span class="dashicons dashicons-trash"></span></button>'));
                        container.append(row);
                    });
                    updateJson();
                }
                function updateJson() {
                    var newButtons = [];
                    container.find('.osint-button-row').each(function() {
                        var row = $(this);
                        newButtons.push({
                            label: row.find('.btn-label').val(),
                            url: row.find('.btn-url').val(),
                            icon: row.find('.btn-icon').val()
                        });
                    });
                    jsonInput.val(JSON.stringify(newButtons));
                }
                $('#osint-add-community-button-row').on('click', function() {
                    buttons.push({ label: '', url: '', icon: '' });
                    renderRows();
                });
                container.on('click', '.osint-remove-row', function() {
                    var index = $(this).closest('.osint-button-row').data('index');
                    buttons.splice(index, 1);
                    renderRows();
                });
                container.on('input', 'input', function() { updateJson(); });
                renderRows();
            });
            </script>

            <p class="submit">
                <input type="submit" name="osint_deck_assist_community_submit" class="button button-primary" value="<?php esc_attr_e( 'Guardar Cambios', 'osint-deck' ); ?>">
            </p>
        </form>
        <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Guarda subpestaña Contacto y Soporte.
     */
    private function save_assist_contact_settings() {
        $title = isset( $_POST['help_card_title'] ) ? sanitize_text_field( wp_unslash( $_POST['help_card_title'] ) ) : '';
        $desc  = isset( $_POST['help_card_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['help_card_desc'] ) ) : '';
        $json  = isset( $_POST['help_buttons_json'] ) ? wp_unslash( $_POST['help_buttons_json'] ) : '[]';

        $decoded = json_decode( $json, true );
        if ( ! is_array( $decoded ) ) {
            $json = '[]';
        } else {
            foreach ( $decoded as &$btn ) {
                $btn['label'] = sanitize_text_field( $btn['label'] ?? '' );
                $btn['url']   = esc_url_raw( $btn['url'] ?? '' );
                $btn['icon']  = sanitize_html_class( $btn['icon'] ?? '' );
            }
            unset( $btn );
            $json = wp_json_encode( $decoded );
        }

        update_option( 'osint_deck_help_card_title', $title, false );
        update_option( 'osint_deck_help_card_desc', $desc, false );
        update_option( 'osint_deck_help_buttons', $json, false );

        add_settings_error( 'osint_deck', 'assist_contact_saved', __( 'Carta de contacto guardada.', 'osint-deck' ), 'success' );
        settings_errors( 'osint_deck' );
    }

    /**
     * Guarda subpestaña Prevención / Emergencia (texto y botones de la carta).
     */
    private function save_assist_prevention_settings() {
        $title = isset( $_POST['crisis_card_title'] ) ? sanitize_text_field( wp_unslash( $_POST['crisis_card_title'] ) ) : '';
        $desc  = isset( $_POST['crisis_card_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['crisis_card_desc'] ) ) : '';
        $json  = isset( $_POST['crisis_buttons_json'] ) ? wp_unslash( $_POST['crisis_buttons_json'] ) : '[]';

        $decoded = json_decode( $json, true );
        if ( ! is_array( $decoded ) ) {
            $json = '[]';
        } else {
            foreach ( $decoded as &$btn ) {
                $btn['label'] = sanitize_text_field( $btn['label'] ?? '' );
                $btn['url']   = esc_url_raw( $btn['url'] ?? '' );
                $btn['icon']  = sanitize_html_class( $btn['icon'] ?? '' );
            }
            unset( $btn );
            $json = wp_json_encode( $decoded );
        }

        update_option( 'osint_deck_crisis_card_title', $title, false );
        update_option( 'osint_deck_crisis_card_desc', $desc, false );
        update_option( 'osint_deck_crisis_buttons', $json, false );

        add_settings_error( 'osint_deck', 'assist_prevention_saved', __( 'Carta de prevención / emergencia guardada.', 'osint-deck' ), 'success' );
        settings_errors( 'osint_deck' );
    }

    /**
     * Guarda subpestaña Comunidad y sugerencias.
     */
    private function save_assist_community_settings() {
        $title = isset( $_POST['community_card_title'] ) ? sanitize_text_field( wp_unslash( $_POST['community_card_title'] ) ) : '';
        $desc  = isset( $_POST['community_card_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['community_card_desc'] ) ) : '';
        $json  = isset( $_POST['community_buttons_json'] ) ? wp_unslash( $_POST['community_buttons_json'] ) : '[]';

        $decoded = json_decode( $json, true );
        if ( ! is_array( $decoded ) ) {
            $json = '[]';
        } else {
            foreach ( $decoded as &$btn ) {
                $btn['label'] = sanitize_text_field( $btn['label'] ?? '' );
                $btn['url']   = esc_url_raw( $btn['url'] ?? '' );
                $btn['icon']  = sanitize_html_class( $btn['icon'] ?? '' );
            }
            unset( $btn );
            $json = wp_json_encode( $decoded );
        }

        update_option( 'osint_deck_community_card_title', $title, false );
        update_option( 'osint_deck_community_card_desc', $desc, false );
        update_option( 'osint_deck_community_buttons', $json, false );

        add_settings_error( 'osint_deck', 'assist_community_saved', __( 'Carta de comunidad guardada.', 'osint-deck' ), 'success' );
        settings_errors( 'osint_deck' );
    }

    /**
     * Render Data Tab
     */
    private function render_data_tab() {
        if ( isset( $_POST['osint_deck_purge_activity'] ) ) {
            check_admin_referer( 'osint_deck_purge_activity' );
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'No tenés permisos para realizar esta acción.', 'osint-deck' ) );
            }

            $want = array(
                'logs'           => ! empty( $_POST['osd_purge_logs'] ),
                'history'        => ! empty( $_POST['osd_purge_history'] ),
                'favorites_meta' => ! empty( $_POST['osd_purge_favorites'] ),
            );

            if ( ! $want['logs'] && ! $want['history'] && ! $want['favorites_meta'] ) {
                add_settings_error(
                    'osint_deck',
                    'purge_activity_none',
                    __( 'No seleccionaste ningún dato para borrar. Marcá al menos una opción.', 'osint-deck' ),
                    'warning'
                );
                settings_errors( 'osint_deck' );
            } else {
                $result = DeckActivityDataCleanup::purge_activity_data( $want );
                $parts = array();
                if ( $result['did_logs'] ) {
                    $parts[] = sprintf(
                        /* translators: %d: number of log rows removed. */
                        __( 'Logs: %d registros.', 'osint-deck' ),
                        (int) $result['logs_rows']
                    );
                }
                if ( $result['did_history'] ) {
                    $parts[] = sprintf(
                        /* translators: %d: number of history rows removed. */
                        __( 'Historial: %d filas.', 'osint-deck' ),
                        (int) $result['history_rows']
                    );
                }
                if ( $result['did_favorites_meta'] ) {
                    $parts[] = sprintf(
                        /* translators: %d: number of user-meta entries removed. */
                        __( 'Favoritos por usuario: %d entradas de meta.', 'osint-deck' ),
                        (int) $result['favorites_meta_rows']
                    );
                }
                $message = __( 'Limpieza completada.', 'osint-deck' ) . ' ' . implode( ' ', $parts );
                add_settings_error( 'osint_deck', 'purge_activity_ok', $message, 'success' );
                settings_errors( 'osint_deck' );
            }
        }

        // Handle reset submission
        if ( isset( $_POST['osint_deck_reset_db'] ) ) {
            check_admin_referer( 'osint_deck_reset_db' );
            
            // 1. Drop tables
            if ( method_exists( $this->category_repository, 'drop_table' ) ) {
                $this->category_repository->drop_table();
            }
            if ( method_exists( $this->tool_repository, 'drop_table' ) ) {
                $this->tool_repository->drop_table();
            }

            // 2. Re-install tables
            if ( method_exists( $this->category_repository, 'install' ) ) {
                $this->category_repository->install();
            }
            if ( method_exists( $this->tool_repository, 'install' ) ) {
                $this->tool_repository->install();
            }

            // 3. Seed default data (As promised in UI)
            $cat_result = array( 'imported' => 0, 'skipped' => 0 );
            if ( method_exists( $this->category_repository, 'seed_defaults' ) ) {
                $cat_result = $this->category_repository->seed_defaults();
            }
            
            $tool_result = array( 'imported' => 0, 'skipped' => 0 );
            if ( method_exists( $this->tool_repository, 'seed_defaults' ) ) {
                $tool_result = $this->tool_repository->seed_defaults();
            }

            // 4. Reset AI Model & Load Defaults
            if ( $this->classifier ) {
                $this->classifier->clear_all();
                
                // Load training data
                $json_file = OSINT_DECK_PLUGIN_DIR . 'data/training_data.json';
                $this->classifier->load_defaults_from_json( $json_file );
                
                // Train
                $this->classifier->train();
            }

            $message = sprintf( 
                __( 'Base de datos reinstalada y datos por defecto cargados. Categorías: %d, Herramientas: %d. Modelo AI reiniciado y entrenado.', 'osint-deck' ),
                $cat_result['imported'],
                $tool_result['imported']
            );
            
            add_settings_error( 'osint_deck', 'reset_success', $message, 'success' );
            
            // Show messages immediately
            settings_errors( 'osint_deck' );
        }

        // Handle seed submission
        if ( isset( $_POST['osint_deck_seed_data'] ) ) {
            check_admin_referer( 'osint_deck_seed_data' );
            
            $cat_result = array( 'imported' => 0, 'skipped' => 0 );
            if ( method_exists( $this->category_repository, 'seed_defaults' ) ) {
                $cat_result = $this->category_repository->seed_defaults();
            }
            
            $tool_result = array( 'imported' => 0, 'skipped' => 0, 'errors' => array() );
            if ( method_exists( $this->tool_repository, 'seed_defaults' ) ) {
                $tool_result = $this->tool_repository->seed_defaults();
            }

            $message = sprintf( 
                __( 'Importación completada. Categorías: %d importadas, %d saltadas. Herramientas: %d importadas, %d saltadas.', 'osint-deck' ),
                $cat_result['imported'],
                $cat_result['skipped'],
                $tool_result['imported'],
                $tool_result['skipped']
            );
            
            $errors = array();
            if ( ! empty( $cat_result['errors'] ) ) {
                $errors = array_merge( $errors, $cat_result['errors'] );
            }
            if ( ! empty( $tool_result['errors'] ) ) {
                $errors = array_merge( $errors, $tool_result['errors'] );
            }

            if ( ! empty( $errors ) ) {
                $message .= '<br>' . __( 'Errores:', 'osint-deck' ) . ' ' . implode( ', ', $errors );
                add_settings_error( 'osint_deck', 'seed_error', $message, 'warning' );
            } else {
                add_settings_error( 'osint_deck', 'seed_success', $message, 'success' );
            }
            
            // Show messages immediately
            settings_errors( 'osint_deck' );
        }

        // Import/Export section
        $this->import_export_manager->render();

        echo '<hr>';

        // Default Data Seeder section
        ?>
        <h2><?php _e( 'Datos por Defecto', 'osint-deck' ); ?></h2>
        <p><?php _e( 'Importar datos iniciales (Categorías y Herramientas) desde la carpeta /data del plugin. No sobrescribe datos existentes.', 'osint-deck' ); ?></p>
        
        <form method="post" action="">
            <?php wp_nonce_field( 'osint_deck_seed_data' ); ?>
            <p class="submit">
                <input type="submit" name="osint_deck_seed_data" class="button button-secondary" value="<?php _e( 'Importar Datos por Defecto', 'osint-deck' ); ?>">
            </p>
        </form>

        <hr>

        <h2><?php _e( 'Mantenimiento de Iconos', 'osint-deck' ); ?></h2>
        <p><?php _e( 'Utilidades para gestionar los iconos de las herramientas.', 'osint-deck' ); ?></p>

        <div class="notice notice-info inline osd-icon-maintain-hint" style="margin: 0 0 1em 0; padding: 10px 12px;">
            <p><strong><?php esc_html_e( 'Recomendaciones', 'osint-deck' ); ?></strong></p>
            <ul style="margin: 0.35em 0 0 1.2em; list-style: disc;">
                <li><?php esc_html_e( 'Formatos: PNG, WebP, SVG o ICO suelen verse bien como favicon.', 'osint-deck' ); ?></li>
                <li><?php esc_html_e( 'Tamaño: entre 32×32 px y 128×128 px (cuadrado); evitá imágenes muy pesadas (&gt;200 KB).', 'osint-deck' ); ?></li>
                <li><?php esc_html_e( 'Podés pegar la URL directa a la imagen (terminada en .png, .svg, etc.), no la página del sitio.', 'osint-deck' ); ?></li>
            </ul>
        </div>

        <table class="form-table">
            <tr>
                <th scope="row"><?php _e( 'Descarga automática', 'osint-deck' ); ?></th>
                <td>
                    <p class="description"><?php _e( 'Intenta descargar usando la URL que ya tiene guardada cada herramienta. Si esa URL es incorrecta o el servidor remoto falla, el error queda registrado en el listado; ahí podés indicar la URL correcta del archivo y aplicar el reemplazo.', 'osint-deck' ); ?></p>
                    <p>
                        <button type="button" id="osint-deck-force-icons" class="button button-secondary">
                            <?php _e( 'Forzar descarga de iconos', 'osint-deck' ); ?>
                        </button>
                    </p>
                    <div id="osint-deck-icons-result" class="osd-icon-maintain-result" role="status"></div>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Corregir URLs e iconos', 'osint-deck' ); ?></th>
                <td>
                    <p class="description"><?php _e( '“URL actual” es la que tiene guardada la herramienta (si es inválida o no se puede descargar, aparece el motivo en “Último error”). En “URL correcta del icono” pegá el enlace directo que sí funcione: al aplicar, se descarga y se actualiza el mazo con el archivo en tu servidor.', 'osint-deck' ); ?></p>
                    <p>
                        <button type="button" id="osint-deck-refresh-icon-list" class="button">
                            <?php _e( 'Actualizar listado', 'osint-deck' ); ?>
                        </button>
                        <button type="button" id="osint-deck-save-manual-icons" class="button button-primary">
                            <?php _e( 'Aplicar URL correcta y descargar', 'osint-deck' ); ?>
                        </button>
                    </p>
                    <div id="osint-deck-icon-table-wrap" class="osd-icon-table-wrap">
                        <p class="osd-icon-table-loading"><?php _e( 'Cargando…', 'osint-deck' ); ?></p>
                    </div>
                    <div id="osint-deck-manual-icons-result" class="osd-icon-maintain-result" role="status"></div>
                </td>
            </tr>
        </table>
        <?php
        $osd_icon_maintain = array(
            'nonce'   => wp_create_nonce( 'osint_deck_admin' ),
            'i18n'    => array(
                'confirmForce'   => __( '¿Intentar descargar todos los iconos remotos al servidor? Puede tardar unos segundos.', 'osint-deck' ),
                'processing'     => __( 'Procesando…', 'osint-deck' ),
                'loadingList'    => __( 'Cargando listado…', 'osint-deck' ),
                'noRemote'       => __( 'No hay herramientas con favicon remoto. Todo listo.', 'osint-deck' ),
                'connError'      => __( 'Error de conexión', 'osint-deck' ),
                'errorPrefix'    => __( 'Error:', 'osint-deck' ),
                'tableThTool'    => __( 'Herramienta', 'osint-deck' ),
                'tableThCurrent' => __( 'URL guardada (puede ser incorrecta)', 'osint-deck' ),
                'tableThLastErr' => __( 'Último error al descargar', 'osint-deck' ),
                'tableThManual'  => __( 'URL correcta del archivo (reemplazo)', 'osint-deck' ),
                'attemptContext' => __( 'Último intento con:', 'osint-deck' ),
                'manualPlaceholder' => __( 'https://…', 'osint-deck' ),
                'confirmManual'  => __( '¿Descargar y guardar el icono usando las URLs correctas indicadas? Las filas sin URL nueva se omiten.', 'osint-deck' ),
                'nothingManual'  => __( 'No completaste ninguna URL correcta.', 'osint-deck' ),
            ),
        );
        ?>
        <script>
        jQuery(document).ready(function($) {
            var cfg = <?php echo wp_json_encode( $osd_icon_maintain ); ?>;
            var $wrap = $('#osint-deck-icon-table-wrap');
            var lastFailures = {};

            function esc(s) {
                return $('<div>').text(s == null ? '' : String(s)).html();
            }

            function renderTable(items, failureMap) {
                failureMap = failureMap || {};
                if (!items || !items.length) {
                    $wrap.html('<p class="osd-icon-table-empty">' + esc(cfg.i18n.noRemote) + '</p>');
                    return;
                }
                var thTool = esc(cfg.i18n.tableThTool);
                var thCur = esc(cfg.i18n.tableThCurrent);
                var thErr = esc(cfg.i18n.tableThLastErr);
                var thMan = esc(cfg.i18n.tableThManual);
                var ph = esc(cfg.i18n.manualPlaceholder);
                var rows = items.map(function(it) {
                    var id = it.id;
                    var err = failureMap[id] || it.last_error || '';
                    var ctx = it.last_context_url || '';
                    var fav = it.favicon || '';
                    var errCell = err ? '<code class="osd-icon-err">' + esc(err) + '</code>' : '—';
                    if (ctx && ctx !== fav) {
                        errCell += '<div class="osd-icon-attempt-note description">' + esc(cfg.i18n.attemptContext) + ' <code>' + esc(ctx) + '</code></div>';
                    }
                    return '<tr data-tool-id="' + id + '">' +
                        '<td class="osd-icon-col-name"><strong>' + esc(it.name) + '</strong><br><span class="description">' + esc(it.slug) + '</span></td>' +
                        '<td class="osd-icon-col-url"><a href="' + esc(it.favicon) + '" target="_blank" rel="noopener noreferrer">' + esc(it.favicon) + '</a></td>' +
                        '<td class="osd-icon-col-err">' + errCell + '</td>' +
                        '<td class="osd-icon-col-manual"><input type="url" class="large-text osd-manual-icon-url" name="manual_icon_' + id + '" placeholder="' + ph + '" autocomplete="off"></td>' +
                        '</tr>';
                }).join('');
                $wrap.html('<table class="widefat striped osd-icon-maintain-table"><thead><tr><th>' + thTool + '</th><th>' + thCur + '</th><th>' + thErr + '</th><th>' + thMan + '</th></tr></thead><tbody>' + rows + '</tbody></table>');
            }

            function failureMapFromList(list) {
                var m = {};
                if (!list || !list.length) return m;
                list.forEach(function(f) {
                    if (f && f.id) m[f.id] = f.error || '';
                });
                return m;
            }

            function loadRemoteList(extraFailures) {
                $wrap.html('<p class="osd-icon-table-loading">' + esc(cfg.i18n.loadingList) + '</p>');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'osint_deck_list_remote_icons', nonce: cfg.nonce },
                    success: function(response) {
                        if (!response.success) {
                            $wrap.html('<p class="notice notice-error">' + esc(cfg.i18n.errorPrefix) + ' ' + esc(response.data && response.data.message ? response.data.message : '') + '</p>');
                            return;
                        }
                        var items = response.data.items || [];
                        var fails = failureMapFromList(extraFailures);
                        Object.keys(lastFailures).forEach(function(k) { if (!fails[k]) fails[k] = lastFailures[k]; });
                        renderTable(items, fails);
                    },
                    error: function() {
                        $wrap.html('<p class="notice notice-error">' + esc(cfg.i18n.connError) + '</p>');
                    }
                });
            }

            loadRemoteList([]);

            $('#osint-deck-refresh-icon-list').on('click', function() {
                lastFailures = {};
                loadRemoteList([]);
            });

            $('#osint-deck-force-icons').on('click', function() {
                var $btn = $(this);
                var $result = $('#osint-deck-icons-result');
                if (!window.confirm(cfg.i18n.confirmForce)) return;

                $btn.prop('disabled', true).text(cfg.i18n.processing);
                $result.removeClass('notice notice-success notice-warning').html(cfg.i18n.processing);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'osint_deck_force_download_icons',
                        nonce: cfg.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            lastFailures = {};
                            (response.data.failures || []).forEach(function(f) {
                                if (f && f.id) lastFailures[f.id] = f.error || '';
                            });
                            var cls = (response.data.failures && response.data.failures.length) ? 'notice notice-warning' : 'notice notice-success';
                            $result.addClass(cls).html(esc(response.data.message));
                            renderTable(response.data.remaining_remote || [], lastFailures);
                        } else {
                            $result.addClass('notice notice-error').html(esc(cfg.i18n.errorPrefix) + ' ' + esc(response.data && response.data.message ? response.data.message : ''));
                        }
                    },
                    error: function() {
                        $result.addClass('notice notice-error').html(esc(cfg.i18n.connError));
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Forzar descarga de iconos', 'osint-deck' ) ); ?>');
                    }
                });
            });

            $('#osint-deck-save-manual-icons').on('click', function() {
                var $btn = $(this);
                var $result = $('#osint-deck-manual-icons-result');
                if (!window.confirm(cfg.i18n.confirmManual)) return;

                var manual = {};
                $wrap.find('tr[data-tool-id]').each(function() {
                    var id = $(this).data('tool-id');
                    var url = $.trim($(this).find('.osd-manual-icon-url').val());
                    if (id && url) manual[id] = url;
                });

                if (!Object.keys(manual).length) {
                    window.alert(cfg.i18n.nothingManual);
                    return;
                }

                $btn.prop('disabled', true).text(cfg.i18n.processing);
                $result.removeClass('notice notice-success notice-error').html(cfg.i18n.processing);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'osint_deck_save_manual_icons',
                        nonce: cfg.nonce,
                        manual: JSON.stringify(manual)
                    },
                    success: function(response) {
                        if (response.success) {
                            lastFailures = {};
                            (response.data.failures || []).forEach(function(f) {
                                if (f && f.id) lastFailures[f.id] = f.error || '';
                            });
                            var cls = (response.data.failures && response.data.failures.length) ? 'notice notice-warning' : 'notice notice-success';
                            $result.addClass(cls).html(esc(response.data.message));
                            renderTable(response.data.remaining_remote || [], lastFailures);
                        } else {
                            $result.addClass('notice notice-error').html(esc(cfg.i18n.errorPrefix) + ' ' + esc(response.data && response.data.message ? response.data.message : ''));
                        }
                    },
                    error: function() {
                        $result.addClass('notice notice-error').html(esc(cfg.i18n.connError));
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Aplicar URL correcta y descargar', 'osint-deck' ) ); ?>');
                    }
                });
            });
        });
        </script>

        <hr>

        <h2><?php _e( 'Borrar datos de actividad del deck', 'osint-deck' ); ?></h2>
        <div class="notice notice-info inline" style="margin: 1em 0; padding: 12px;">
            <p><?php _e( 'Marcá solo lo que querés vaciar. Podés usar “Seleccionar todo” y después desmarcar lo que no quieras borrar. Los totales de clics/likes/favoritos globales en cada herramienta no cambian aunque borres favoritos personales.', 'osint-deck' ); ?></p>
            <p><strong><?php _e( 'Nunca se borra con esta herramienta:', 'osint-deck' ); ?></strong></p>
            <ul style="list-style: disc; margin-left: 1.5em;">
                <li><?php _e( 'Cuentas de WordPress.', 'osint-deck' ); ?></li>
                <li><?php _e( 'Herramientas ni categorías del catálogo.', 'osint-deck' ); ?></li>
                <li><?php _e( 'La configuración del plugin (integraciones, diseño, datos, etc.).', 'osint-deck' ); ?></li>
            </ul>
        </div>
        <?php
        $osd_purge_i18n = array(
            'noneSelected'  => __( 'Tenés que marcar al menos una opción antes de borrar.', 'osint-deck' ),
            'confirmIntro'   => __( 'Se van a eliminar los datos marcados. No se puede deshacer.', 'osint-deck' ),
            'confirmSuffix'  => __( '¿Continuar?', 'osint-deck' ),
            'labels'         => array(
                'logs'      => __( 'Registros de logs del plugin (tabla de depuración).', 'osint-deck' ),
                'history'   => __( 'Historial de actividad del deck (usuarios con sesión).', 'osint-deck' ),
                'favorites' => __( 'Listas de favoritos de todos los usuarios (meta; no borra usuarios).', 'osint-deck' ),
            ),
        );
        ?>
        <form method="post" action="" id="osd-purge-activity-form" class="osd-purge-activity-form">
            <?php wp_nonce_field( 'osint_deck_purge_activity' ); ?>
            <fieldset class="osd-purge-fieldset" style="border: 1px solid #c3c4c7; padding: 12px 16px; margin: 1em 0; background: #fff;">
                <legend style="font-weight: 600;"><?php _e( 'Qué borrar', 'osint-deck' ); ?></legend>
                <p style="margin: 0 0 10px;">
                    <label>
                        <input type="checkbox" id="osd-purge-select-all" />
                        <?php _e( 'Seleccionar todo', 'osint-deck' ); ?>
                    </label>
                </p>
                <ul style="list-style: none; margin: 0; padding: 0;">
                    <li style="margin: 6px 0;">
                        <label>
                            <input type="checkbox" class="osd-purge-item" name="osd_purge_logs" id="osd-purge-logs" value="1" />
                            <?php echo esc_html( $osd_purge_i18n['labels']['logs'] ); ?>
                        </label>
                    </li>
                    <li style="margin: 6px 0;">
                        <label>
                            <input type="checkbox" class="osd-purge-item" name="osd_purge_history" id="osd-purge-history" value="1" />
                            <?php echo esc_html( $osd_purge_i18n['labels']['history'] ); ?>
                        </label>
                    </li>
                    <li style="margin: 6px 0;">
                        <label>
                            <input type="checkbox" class="osd-purge-item" name="osd_purge_favorites" id="osd-purge-favorites" value="1" />
                            <?php echo esc_html( $osd_purge_i18n['labels']['favorites'] ); ?>
                        </label>
                    </li>
                </ul>
            </fieldset>
            <p class="submit">
                <input type="submit" name="osint_deck_purge_activity" class="button button-secondary" value="<?php esc_attr_e( 'Borrar lo seleccionado', 'osint-deck' ); ?>">
            </p>
        </form>
        <script>
        (function() {
            var form = document.getElementById('osd-purge-activity-form');
            if (!form) return;
            var all = document.getElementById('osd-purge-select-all');
            var items = form.querySelectorAll('.osd-purge-item');
            var i18n = <?php echo wp_json_encode( $osd_purge_i18n ); ?>;

            function syncMaster() {
                var n = items.length, c = 0;
                items.forEach(function(el) { if (el.checked) c++; });
                all.checked = c === n && n > 0;
                all.indeterminate = c > 0 && c < n;
            }

            all.addEventListener('change', function() {
                items.forEach(function(el) { el.checked = all.checked; });
                all.indeterminate = false;
            });
            items.forEach(function(el) { el.addEventListener('change', syncMaster); });
            syncMaster();

            form.addEventListener('submit', function(ev) {
                var chosen = [];
                if (document.getElementById('osd-purge-logs').checked) chosen.push(i18n.labels.logs);
                if (document.getElementById('osd-purge-history').checked) chosen.push(i18n.labels.history);
                if (document.getElementById('osd-purge-favorites').checked) chosen.push(i18n.labels.favorites);
                if (!chosen.length) {
                    ev.preventDefault();
                    alert(i18n.noneSelected);
                    return;
                }
                var msg = i18n.confirmIntro + '\n\n• ' + chosen.join('\n• ') + '\n\n' + i18n.confirmSuffix;
                if (!confirm(msg)) {
                    ev.preventDefault();
                }
            });
        })();
        </script>

        <hr>

        <h2><?php _e( 'Reinstalar Base de Datos', 'osint-deck' ); ?></h2>
        <p class="description" style="color: #b32d2e;">
            <?php _e( '¡CUIDADO! Esta acción eliminará TODOS los datos actuales (herramientas y categorías personalizadas) y volverá a cargar los datos por defecto.', 'osint-deck' ); ?>
        </p>
        
        <form method="post" action="" onsubmit="return confirm('<?php _e( '¿Estás SEGURO? Esto eliminará todos tus datos personalizados y no se puede deshacer.', 'osint-deck' ); ?>');">
            <?php wp_nonce_field( 'osint_deck_reset_db' ); ?>
            <p class="submit">
                <input type="submit" name="osint_deck_reset_db" class="button button-secondary" style="color: #b32d2e; border-color: #b32d2e;" value="<?php _e( 'Reinstalar Base de Datos', 'osint-deck' ); ?>">
            </p>
        </form>
        <?php
    }

    /**
     * Render TLDs Tab
     */
    private function render_tlds_tab() {
        $this->tld_manager_admin->render();
    }

    /**
     * Render Logs Tab
     */
    private function render_logs_tab() {
        if ( isset( $_POST['osint_deck_logs_settings_submit'] ) ) {
            check_admin_referer( 'osint_deck_logs_settings' );
            $this->save_logs_settings();
        }

        $log_retention   = (int) get_option( 'osint_deck_log_retention', 30 );
        $logging_enabled = (bool) get_option( 'osint_deck_logging_enabled', false );

        ?>
        <form method="post" action="" class="osint-deck-logs-settings" style="max-width:52rem; margin-bottom:20px;">
            <?php wp_nonce_field( 'osint_deck_logs_settings' ); ?>
            <h2 style="margin-top:0;"><?php esc_html_e( 'Opciones de registro', 'osint-deck' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="logging_enabled"><?php esc_html_e( 'Habilitar logs', 'osint-deck' ); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="logging_enabled" id="logging_enabled" value="1" <?php checked( $logging_enabled, true ); ?>>
                            <?php esc_html_e( 'Activar registro de errores y eventos del plugin en base de datos', 'osint-deck' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="log_retention"><?php esc_html_e( 'Retención de logs (días)', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="number" name="log_retention" id="log_retention" value="<?php echo esc_attr( $log_retention ); ?>" class="small-text" min="1" step="1">
                        <p class="description"><?php esc_html_e( 'Los registros más antiguos se eliminan automáticamente al correr la tarea programada diaria.', 'osint-deck' ); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="osint_deck_logs_settings_submit" class="button button-primary" value="<?php esc_attr_e( 'Guardar Cambios', 'osint-deck' ); ?>">
            </p>
        </form>

        <h2><?php esc_html_e( 'Registros', 'osint-deck' ); ?></h2>
        <?php

        // Pagination
        $page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $limit = 20;
        $offset = ( $page - 1 ) * $limit;
        
        // Filter
        $level = isset( $_GET['level'] ) ? sanitize_text_field( $_GET['level'] ) : '';

        // Get logs
        $logs = $this->logger->get_logs( $limit, $offset, $level );
        $total_logs = $this->logger->count_logs( $level );
        $total_pages = ceil( $total_logs / $limit );

        // Base URL for pagination
        $base_url = add_query_arg( array(
            'page' => 'osint-deck-settings',
            'tab'  => 'logs',
            'level' => $level
        ), admin_url( 'admin.php' ) );

        ?>
        <div class="osint-deck-logs">
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="osint-deck-settings">
                        <input type="hidden" name="tab" value="logs">
                        <select name="level">
                            <option value=""><?php _e( 'Todos los niveles', 'osint-deck' ); ?></option>
                            <option value="info" <?php selected( $level, 'info' ); ?>><?php _e( 'Info', 'osint-deck' ); ?></option>
                            <option value="error" <?php selected( $level, 'error' ); ?>><?php _e( 'Error', 'osint-deck' ); ?></option>
                            <option value="warning" <?php selected( $level, 'warning' ); ?>><?php _e( 'Warning', 'osint-deck' ); ?></option>
                            <option value="debug" <?php selected( $level, 'debug' ); ?>><?php _e( 'Debug', 'osint-deck' ); ?></option>
                        </select>
                        <input type="submit" class="button" value="<?php _e( 'Filtrar', 'osint-deck' ); ?>">
                    </form>
                </div>
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php printf( _n( '%s elemento', '%s elementos', $total_logs, 'osint-deck' ), number_format_i18n( $total_logs ) ); ?></span>
                    <?php
                    if ( $total_pages > 1 ) {
                        $page_links = paginate_links( array(
                            'base' => add_query_arg( 'paged', '%#%', $base_url ),
                            'format' => '',
                            'prev_text' => __( '&laquo;', 'osint-deck' ),
                            'next_text' => __( '&raquo;', 'osint-deck' ),
                            'total' => $total_pages,
                            'current' => $page
                        ) );
                        if ( $page_links ) {
                            echo '<span class="pagination-links">' . $page_links . '</span>';
                        }
                    }
                    ?>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-date" style="width: 150px;"><?php _e( 'Fecha', 'osint-deck' ); ?></th>
                        <th scope="col" class="manage-column column-level" style="width: 80px;"><?php _e( 'Nivel', 'osint-deck' ); ?></th>
                        <th scope="col" class="manage-column column-message"><?php _e( 'Mensaje', 'osint-deck' ); ?></th>
                        <th scope="col" class="manage-column column-context" style="width: 20%;"><?php _e( 'Contexto', 'osint-deck' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $logs ) ) : ?>
                        <?php foreach ( $logs as $log ) : ?>
                            <tr>
                                <td><?php echo esc_html( $log['created_at'] ); ?></td>
                                <td>
                                    <span class="osint-deck-log-level log-level-<?php echo esc_attr( $log['level'] ); ?>" 
                                          style="padding: 3px 8px; border-radius: 3px; font-weight: bold; font-size: 11px; text-transform: uppercase; 
                                          background: <?php echo $log['level'] === 'error' ? '#d63638' : ($log['level'] === 'warning' ? '#dba617' : ($log['level'] === 'debug' ? '#2271b1' : '#00a32a')); ?>; 
                                          color: #fff;">
                                        <?php echo esc_html( $log['level'] ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( $log['message'] ); ?></td>
                                <td>
                                    <?php 
                                    if ( ! empty( $log['context'] ) ) {
                                        echo '<pre style="margin: 0; white-space: pre-wrap; font-size: 10px; max-height: 100px; overflow: auto;">';
                                        echo esc_html( print_r( $log['context'], true ) );
                                        echo '</pre>';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4"><?php _e( 'No hay registros.', 'osint-deck' ); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render AI Training Tab
     */
    private function render_ai_training_tab() {
        // Handle clear submission
        if ( isset( $_POST['osint_deck_ai_clear'] ) ) {
            check_admin_referer( 'osint_deck_ai_clear' );
            
            if ( $this->classifier ) {
                $this->classifier->clear_all();
                add_settings_error( 'osint_deck', 'ai_cleared', __( 'Modelo y datos de entrenamiento eliminados.', 'osint-deck' ), 'success' );
            } else {
                 add_settings_error( 'osint_deck', 'ai_error', __( 'Clasificador no disponible.', 'osint-deck' ), 'error' );
            }
        }

        // Handle load defaults submission
        if ( isset( $_POST['osint_deck_ai_load'] ) ) {
            check_admin_referer( 'osint_deck_ai_load' );
            
            if ( $this->classifier ) {
                $json_file = OSINT_DECK_PLUGIN_DIR . 'data/training_data.json';
                $result = $this->classifier->load_defaults_from_json( $json_file );
                
                if ( $result['success'] ) {
                    $message = sprintf( 
                        __( 'Importación completada. %d muestras importadas, %d saltadas.', 'osint-deck' ), 
                        $result['imported'], 
                        $result['skipped'] 
                    );
                    add_settings_error( 'osint_deck', 'ai_loaded', $message, 'success' );
                    
                    // Retrain
                    $this->classifier->train();
                    add_settings_error( 'osint_deck', 'ai_trained', __( 'Modelo re-entrenado con los nuevos datos.', 'osint-deck' ), 'success' );
                    
                } else {
                    add_settings_error( 'osint_deck', 'ai_error', __( 'Error al cargar JSON: ', 'osint-deck' ) . $result['message'], 'error' );
                }
            } else {
                 add_settings_error( 'osint_deck', 'ai_error', __( 'Clasificador no disponible.', 'osint-deck' ), 'error' );
            }
        }

        // Display status
        $samples_count = 0;
        $model_status = 'No entrenado';
        
        if ( $this->classifier ) {
            $samples = $this->classifier->get_samples();
            $samples_count = count( $samples );
            
            // Check if model option exists
            $model = get_option( 'osint_deck_nb_model' );
            if ( ! empty( $model ) ) {
                $model_status = 'Entrenado';
            }
        }

        ?>
        <h2><?php _e( 'Entrenamiento AI', 'osint-deck' ); ?></h2>
        <p><?php _e( 'Gestiona los datos de entrenamiento para el clasificador de intenciones.', 'osint-deck' ); ?></p>
        
        <table class="form-table">
            <tr>
                <th><?php _e( 'Muestras de entrenamiento', 'osint-deck' ); ?></th>
                <td><?php echo $samples_count; ?></td>
            </tr>
            <tr>
                <th><?php _e( 'Estado del Modelo', 'osint-deck' ); ?></th>
                <td><?php echo $model_status; ?></td>
            </tr>
        </table>

        <hr>

        <h3><?php _e( 'Acciones', 'osint-deck' ); ?></h3>
        
        <form method="post" action="" style="display:inline-block; margin-right: 10px;">
            <?php wp_nonce_field( 'osint_deck_ai_load' ); ?>
            <input type="submit" name="osint_deck_ai_load" class="button button-primary" value="<?php _e( 'Cargar Defaults y Entrenar', 'osint-deck' ); ?>">
        </form>

        <form method="post" action="" style="display:inline-block;" onsubmit="return confirm('<?php _e( '¿Estás seguro de borrar todos los datos de entrenamiento?', 'osint-deck' ); ?>');">
            <?php wp_nonce_field( 'osint_deck_ai_clear' ); ?>
            <input type="submit" name="osint_deck_ai_clear" class="button button-secondary" value="<?php _e( 'Borrar Todo', 'osint-deck' ); ?>">
        </form>

        <hr>

        <h3><?php _e( 'Datos de Entrenamiento Actuales (DB)', 'osint-deck' ); ?></h3>
        <p class="description"><?php _e( 'Estos son los datos cargados actualmente en la base de datos que utiliza el modelo.', 'osint-deck' ); ?></p>
        <?php
        $json_data = '';
        if ( $this->classifier ) {
             $samples = $this->classifier->get_samples();
             $json_data = json_encode( $samples, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        }
        ?>
        <textarea style="width:100%; height: 300px; font-family: monospace; background: #f0f0f1;" readonly><?php echo esc_textarea( $json_data ); ?></textarea>
        <?php
    }

    /**
     * Guarda pestaña Diseño (sticky + tema).
     */
    private function save_design_settings() {
        $theme_mode = isset( $_POST['theme_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['theme_mode'] ) ) : 'auto';
        if ( ! in_array( $theme_mode, array( 'auto', 'light', 'dark' ), true ) ) {
            $theme_mode = 'auto';
        }
        $theme_selector    = isset( $_POST['theme_selector'] ) ? sanitize_text_field( wp_unslash( $_POST['theme_selector'] ) ) : '';
        $theme_token_light   = isset( $_POST['theme_token_light'] ) ? sanitize_text_field( wp_unslash( $_POST['theme_token_light'] ) ) : 'light';
        $theme_token_dark    = isset( $_POST['theme_token_dark'] ) ? sanitize_text_field( wp_unslash( $_POST['theme_token_dark'] ) ) : 'dark';
        $chatbar_sticky_top  = isset( $_POST['chatbar_sticky_top'] ) ? intval( wp_unslash( $_POST['chatbar_sticky_top'] ) ) : 0;
        if ( $chatbar_sticky_top < 0 ) {
            $chatbar_sticky_top = 0;
        }
        if ( $chatbar_sticky_top > 500 ) {
            $chatbar_sticky_top = 500;
        }
        $chatbar_sticky_enabled = isset( $_POST['chatbar_sticky_enabled'] )
            && '1' === sanitize_text_field( wp_unslash( $_POST['chatbar_sticky_enabled'] ) );

        update_option( 'osint_deck_theme_mode', $theme_mode, false );
        update_option( 'osint_deck_theme_selector', $theme_selector, false );
        update_option( 'osint_deck_theme_token_light', $theme_token_light, false );
        update_option( 'osint_deck_theme_token_dark', $theme_token_dark, false );
        update_option( 'osint_deck_chatbar_sticky_top', $chatbar_sticky_top, false );
        update_option( 'osint_deck_chatbar_sticky_enabled', $chatbar_sticky_enabled, false );

        add_settings_error( 'osint_deck', 'design_saved', __( 'Diseño guardado.', 'osint-deck' ), 'success' );
        settings_errors( 'osint_deck' );
    }

    /**
     * Guarda opciones de la pestaña Logs (activación y retención).
     */
    private function save_logs_settings() {
        $log_retention = isset( $_POST['log_retention'] ) ? intval( wp_unslash( $_POST['log_retention'] ) ) : 30;
        if ( $log_retention < 1 ) {
            $log_retention = 1;
        }
        $logging_enabled = isset( $_POST['logging_enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['logging_enabled'] ) );

        update_option( 'osint_deck_log_retention', $log_retention, false );
        update_option( 'osint_deck_logging_enabled', $logging_enabled, false );

        add_settings_error( 'osint_deck', 'logs_settings_saved', __( 'Opciones de logs guardadas.', 'osint-deck' ), 'success' );
        settings_errors( 'osint_deck' );
    }
}
