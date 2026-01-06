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
        $allowed_tabs = array( 'general', 'data', 'tlds', 'logs', 'support', 'auth' );
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        
        if ( ! in_array( $active_tab, $allowed_tabs ) ) {
            $active_tab = 'general';
        }
        ?>
        <div class="wrap">
            <h1><?php _e( 'Configuración OSINT Deck', 'osint-deck' ); ?></h1>
            
            <?php settings_errors( 'osint_deck' ); ?>

            <h2 class="nav-tab-wrapper">
                <a href="?page=osint-deck-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php _e( 'General', 'osint-deck' ); ?></a>
                <a href="?page=osint-deck-settings&tab=support" class="nav-tab <?php echo $active_tab == 'support' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Soporte / Ayuda', 'osint-deck' ); ?></a>
                <a href="?page=osint-deck-settings&tab=data" class="nav-tab <?php echo $active_tab == 'data' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Datos', 'osint-deck' ); ?></a>
                <a href="?page=osint-deck-settings&tab=tlds" class="nav-tab <?php echo $active_tab == 'tlds' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Dominios / TLDs', 'osint-deck' ); ?></a>
                <a href="?page=osint-deck-settings&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Logs', 'osint-deck' ); ?></a>
                <a href="?page=osint-deck-settings&tab=auth" class="nav-tab <?php echo $active_tab == 'auth' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Autenticación', 'osint-deck' ); ?></a>
            </h2>

            <div class="tab-content">
                <?php
                switch ( $active_tab ) {
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
     * Render Auth Tab
     */
    private function render_auth_tab() {
        if ( isset( $_POST['osint_deck_auth_submit'] ) ) {
            check_admin_referer( 'osint_deck_auth' );
            $enabled = isset( $_POST['sso_enabled'] ) ? (bool) $_POST['sso_enabled'] : false;
            $client_id = isset( $_POST['google_client_id'] ) ? sanitize_text_field( $_POST['google_client_id'] ) : '';
            update_option( 'osint_deck_sso_enabled', $enabled, false );
            update_option( 'osint_deck_google_client_id', $client_id, false );
            add_settings_error( 'osint_deck', 'auth_saved', __( 'Configuración guardada.', 'osint-deck' ), 'updated' );
        }

        $enabled = (bool) get_option( 'osint_deck_sso_enabled', false );
        $client_id = get_option( 'osint_deck_google_client_id', '' );
        $origin_url = site_url();
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'osint_deck_auth' ); ?>

            <h2><?php _e( 'Autenticación (SSO)', 'osint-deck' ); ?></h2>
            <p><?php _e( 'Configura el inicio de sesión con Google para permitir a los usuarios guardar favoritos y reportar herramientas.', 'osint-deck' ); ?></p>

            <div class="card" style="max-width: 100%; margin-top: 20px; padding: 0;">
                <h3 class="hndle" style="padding: 15px; margin: 0; border-bottom: 1px solid #ccd0d4; background: #f9f9f9;">
                    <?php _e( 'Guía de Configuración Rápida', 'osint-deck' ); ?>
                </h3>
                <div class="inside" style="padding: 15px;">
                    <ol style="margin-top: 0;">
                        <li>
                            <?php _e( 'Ve a la', 'osint-deck' ); ?> 
                            <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer">
                                <strong>Google Cloud Console (Credenciales)</strong> <span class="dashicons dashicons-external"></span>
                            </a>.
                        </li>
                        <li><?php _e( 'Crea un nuevo proyecto o selecciona uno existente.', 'osint-deck' ); ?></li>
                        <li><?php _e( 'Haz clic en <strong>"Crear Credenciales"</strong> > <strong>"ID de cliente de OAuth"</strong>.', 'osint-deck' ); ?></li>
                        <li><?php _e( 'Si es necesario, configura la "Pantalla de consentimiento de OAuth" (tipo: Externo).', 'osint-deck' ); ?></li>
                        <li><?php _e( 'En tipo de aplicación, selecciona <strong>"Aplicación web"</strong>.', 'osint-deck' ); ?></li>
                        <li>
                            <?php _e( 'En <strong>"Orígenes autorizados de JavaScript"</strong>, agrega exactamente esta URL:', 'osint-deck' ); ?>
                            <br>
                            <code style="display: inline-block; background: #f0f0f1; padding: 5px 10px; margin: 5px 0; border: 1px solid #ccc; user-select: all;">
                                <?php echo esc_url( $origin_url ); ?>
                            </code>
                            <br>
                            <small class="description"><?php _e( 'Es importante que no tenga barra al final (/)', 'osint-deck' ); ?></small>
                        </li>
                        <li>
                            <?php _e( 'En <strong>"URI de redireccionamiento autorizados"</strong>, no es estrictamente necesario para el botón "Sign In With Google", pero puedes agregar la misma URL por compatibilidad:', 'osint-deck' ); ?>
                            <br>
                            <code style="display: inline-block; background: #f0f0f1; padding: 5px 10px; margin: 5px 0; border: 1px solid #ccc; user-select: all;">
                                <?php echo esc_url( $origin_url ); ?>
                            </code>
                        </li>
                        <li><?php _e( 'Haz clic en "Crear" y copia el <strong>"ID de cliente"</strong> generado.', 'osint-deck' ); ?></li>
                    </ol>
                </div>
            </div>

            <table class="form-table">
                <tr>
                    <th><label for="sso_enabled"><?php _e( 'Habilitar SSO', 'osint-deck' ); ?></label></th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="sso_enabled" id="sso_enabled" value="1" <?php checked( $enabled, true ); ?>>
                            <span class="slider round"></span>
                        </label>
                        <span class="description" style="vertical-align: super; margin-left: 5px;"><?php _e( 'Activar inicio de sesión con Google', 'osint-deck' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th><label for="google_client_id"><?php _e( 'Google Client ID', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="text" name="google_client_id" id="google_client_id" value="<?php echo esc_attr( $client_id ); ?>" class="regular-text code">
                        <p class="description"><?php _e( 'Pega aquí el ID de cliente que obtuviste en el paso 8 (termina en .apps.googleusercontent.com).', 'osint-deck' ); ?></p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="osint_deck_auth_submit" class="button button-primary" value="<?php _e( 'Guardar Cambios', 'osint-deck' ); ?>">
            </p>
        </form>
        <?php
    }

    /**
     * Render General Tab
     */
    private function render_general_tab() {
        // Handle form submission
        if ( isset( $_POST['osint_deck_settings_submit'] ) ) {
            check_admin_referer( 'osint_deck_settings' );
            $this->save_settings();
        }

        $theme_mode = get_option( 'osint_deck_theme_mode', 'auto' );
        $theme_selector = get_option( 'osint_deck_theme_selector', '[data-site-skin]' );
        $theme_token_light = get_option( 'osint_deck_theme_token_light', 'light' );
        $theme_token_dark = get_option( 'osint_deck_theme_token_dark', 'dark' );
        $help_url = get_option( 'osint_deck_help_url', 'https://osint.com.ar/OsintDeck-Ayuda' );
        $log_retention = get_option( 'osint_deck_log_retention', 30 );
        $logging_enabled = get_option( 'osint_deck_logging_enabled', false );
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'osint_deck_settings' ); ?>

            <h2><?php _e( 'General', 'osint-deck' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="help_url"><?php _e( 'URL de Ayuda', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="url" name="help_url" id="help_url" value="<?php echo esc_url( $help_url ); ?>" class="regular-text">
                        <p class="description"><?php _e( 'Página a la que se enviará al usuario cuando pida ayuda (ej: "ayuda", "como usar").', 'osint-deck' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="logging_enabled"><?php _e( 'Habilitar Logs', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="checkbox" name="logging_enabled" id="logging_enabled" value="1" <?php checked( $logging_enabled, true ); ?>>
                        <p class="description"><?php _e( 'Activar sistema de registro de errores y eventos.', 'osint-deck' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="log_retention"><?php _e( 'Retención de Logs (días)', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="number" name="log_retention" id="log_retention" value="<?php echo esc_attr( $log_retention ); ?>" class="small-text" min="1">
                        <p class="description"><?php _e( 'Cantidad de días que se guardarán los logs antes de ser eliminados automáticamente.', 'osint-deck' ); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php _e( 'Sistema de Temas', 'osint-deck' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="theme_mode"><?php _e( 'Modo de Tema', 'osint-deck' ); ?></label></th>
                    <td>
                        <select name="theme_mode" id="theme_mode">
                            <option value="auto" <?php selected( $theme_mode, 'auto' ); ?>><?php _e( 'Auto (sincronizar con sitio)', 'osint-deck' ); ?></option>
                            <option value="light" <?php selected( $theme_mode, 'light' ); ?>><?php _e( 'Siempre claro', 'osint-deck' ); ?></option>
                            <option value="dark" <?php selected( $theme_mode, 'dark' ); ?>><?php _e( 'Siempre oscuro', 'osint-deck' ); ?></option>
                        </select>
                        <p class="description"><?php _e( 'Modo "Auto" sincroniza con el tema del sitio', 'osint-deck' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="theme_selector"><?php _e( 'Selector CSS del Tema', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="text" name="theme_selector" id="theme_selector" value="<?php echo esc_attr( $theme_selector ); ?>" class="regular-text">
                        <p class="description"><?php _e( 'Selector CSS del elemento que contiene el atributo de tema (ej: [data-site-skin])', 'osint-deck' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="theme_token_light"><?php _e( 'Token Tema Claro', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="text" name="theme_token_light" id="theme_token_light" value="<?php echo esc_attr( $theme_token_light ); ?>" class="regular-text">
                        <p class="description"><?php _e( 'Valor del atributo para tema claro (ej: light)', 'osint-deck' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="theme_token_dark"><?php _e( 'Token Tema Oscuro', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="text" name="theme_token_dark" id="theme_token_dark" value="<?php echo esc_attr( $theme_token_dark ); ?>" class="regular-text">
                        <p class="description"><?php _e( 'Valor del atributo para tema oscuro (ej: dark)', 'osint-deck' ); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php _e( 'Estadísticas', 'osint-deck' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php _e( 'Total de Herramientas', 'osint-deck' ); ?></th>
                    <td><?php echo $this->tool_repository->count_tools(); ?></td>
                </tr>
                <tr>
                    <th><?php _e( 'Total de Categorías', 'osint-deck' ); ?></th>
                    <td><?php echo $this->category_repository->count_categories(); ?></td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="osint_deck_settings_submit" class="button button-primary" value="<?php _e( 'Guardar Cambios', 'osint-deck' ); ?>">
            </p>
        </form>
        <?php
    }

    /**
     * Render Support Tab
     */
    private function render_support_tab() {
        if ( isset( $_POST['osint_deck_support_submit'] ) ) {
            check_admin_referer( 'osint_deck_support' );
            $this->save_support_settings();
        }

        $title = get_option( 'osint_deck_help_card_title', 'Soporte OSINT Deck' );
        $desc = get_option( 'osint_deck_help_card_desc', '¿Encontraste un error o necesitas reportar algo? Contactanos directamente.' );
        $buttons_json = get_option( 'osint_deck_help_buttons', '[]' );
        
        // Ensure valid JSON
        $buttons = json_decode( $buttons_json, true );
        if ( ! is_array( $buttons ) ) {
            $buttons = array();
        }
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'osint_deck_support' ); ?>

            <h2><?php _e( 'Configuración de Tarjeta de Ayuda', 'osint-deck' ); ?></h2>
            <p><?php _e( 'Personaliza la tarjeta que aparece cuando el usuario busca "ayuda".', 'osint-deck' ); ?></p>

            <table class="form-table">
                <tr>
                    <th><label for="help_card_title"><?php _e( 'Título', 'osint-deck' ); ?></label></th>
                    <td>
                        <input type="text" name="help_card_title" id="help_card_title" value="<?php echo esc_attr( $title ); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="help_card_desc"><?php _e( 'Descripción', 'osint-deck' ); ?></label></th>
                    <td>
                        <textarea name="help_card_desc" id="help_card_desc" rows="3" class="large-text code"><?php echo esc_textarea( $desc ); ?></textarea>
                    </td>
                </tr>
            </table>

            <h3><?php _e( 'Botones de Acción', 'osint-deck' ); ?></h3>
            <p class="description"><?php _e( 'Agrega botones a la tarjeta de ayuda. Arrastra para reordenar (próximamente).', 'osint-deck' ); ?></p>
            
            <div id="osint-deck-buttons-container">
                <!-- Buttons will be rendered here by JS -->
            </div>

            <button type="button" class="button" id="osint-add-button-row"><?php _e( 'Añadir Botón', 'osint-deck' ); ?></button>

            <!-- Hidden input to store JSON -->
            <input type="hidden" name="help_buttons_json" id="help_buttons_json" value="<?php echo esc_attr( $buttons_json ); ?>">

            <style>
                .osint-button-row {
                    background: #f9f9f9;
                    border: 1px solid #ccc;
                    padding: 10px;
                    margin-bottom: 10px;
                    border-radius: 4px;
                    display: flex;
                    gap: 10px;
                    align-items: flex-start;
                }
                .osint-button-row .field-group {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                    flex: 1;
                }
                .osint-button-row label {
                    font-size: 12px;
                    font-weight: 600;
                    color: #666;
                }
                .osint-button-row input {
                    width: 100%;
                }
                .osint-remove-row {
                    margin-top: 20px !important;
                    color: #b32d2e !important;
                    border-color: #b32d2e !important;
                }
                .osint-remove-row:hover {
                    background: #b32d2e !important;
                    color: white !important;
                }
            </style>

            <script>
            jQuery(document).ready(function($) {
                var container = $('#osint-deck-buttons-container');
                var jsonInput = $('#help_buttons_json');
                var buttons = <?php echo json_encode( $buttons ); ?>;

                function renderRows() {
                    container.empty();
                    buttons.forEach(function(btn, index) {
                        var row = $('<div class="osint-button-row" data-index="' + index + '"></div>');
                        
                        row.append(`
                            <div class="field-group">
                                <label><?php _e( 'Texto del Botón', 'osint-deck' ); ?></label>
                                <input type="text" class="btn-label" value="${btn.label || ''}" placeholder="Ej: Contactar Soporte">
                            </div>
                            <div class="field-group">
                                <label><?php _e( 'URL', 'osint-deck' ); ?></label>
                                <input type="text" class="btn-url" value="${btn.url || ''}" placeholder="https://...">
                            </div>
                            <div class="field-group" style="flex: 0 0 150px;">
                                <label><?php _e( 'Icono (RemixIcon)', 'osint-deck' ); ?></label>
                                <input type="text" class="btn-icon" value="${btn.icon || ''}" placeholder="ri-customer-service-2-fill">
                                <small><a href="https://remixicon.com/" target="_blank">Ver Iconos</a></small>
                            </div>
                            <button type="button" class="button osint-remove-row"><span class="dashicons dashicons-trash"></span></button>
                        `);
                        
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

                container.on('input', 'input', function() {
                    updateJson();
                });

                renderRows();
            });
            </script>

            <p class="submit">
                <input type="submit" name="osint_deck_support_submit" class="button button-primary" value="<?php _e( 'Guardar Cambios', 'osint-deck' ); ?>">
            </p>
        </form>
        <?php
    }

    /**
     * Save Support Settings
     */
    private function save_support_settings() {
        $title = isset( $_POST['help_card_title'] ) ? sanitize_text_field( $_POST['help_card_title'] ) : '';
        $desc = isset( $_POST['help_card_desc'] ) ? sanitize_textarea_field( $_POST['help_card_desc'] ) : '';
        $json = isset( $_POST['help_buttons_json'] ) ? wp_unslash( $_POST['help_buttons_json'] ) : '[]';

        // Validate JSON
        $decoded = json_decode( $json, true );
        if ( ! is_array( $decoded ) ) {
            $json = '[]';
        } else {
            // Sanitize individual fields
            foreach ( $decoded as &$btn ) {
                $btn['label'] = sanitize_text_field( $btn['label'] ?? '' );
                $btn['url'] = esc_url_raw( $btn['url'] ?? '' );
                $btn['icon'] = sanitize_html_class( $btn['icon'] ?? '' );
            }
            $json = json_encode( $decoded );
        }

        update_option( 'osint_deck_help_card_title', $title );
        update_option( 'osint_deck_help_card_desc', $desc );
        update_option( 'osint_deck_help_buttons', $json );

        add_settings_error( 'osint_deck', 'settings_saved', __( 'Configuración de soporte guardada', 'osint-deck' ), 'success' );
        settings_errors( 'osint_deck' );
    }

    /**
     * Render Data Tab
     */
    private function render_data_tab() {
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
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e( 'Descargar Iconos Faltantes', 'osint-deck' ); ?></th>
                <td>
                    <button type="button" id="osint-deck-force-icons" class="button button-secondary">
                        <?php _e( 'Forzar descarga de iconos', 'osint-deck' ); ?>
                    </button>
                    <p class="description"><?php _e( 'Intenta descargar iconos para herramientas que aún usan enlaces remotos (http/https).', 'osint-deck' ); ?></p>
                    <div id="osint-deck-icons-result" style="margin-top: 10px; font-weight: bold;"></div>
                </td>
            </tr>
        </table>
        <script>
        jQuery(document).ready(function($) {
            $('#osint-deck-force-icons').on('click', function() {
                var $btn = $(this);
                var $result = $('#osint-deck-icons-result');
                
                if (!confirm('<?php _e( '¿Deseas intentar descargar los iconos faltantes? Esto puede tardar unos segundos.', 'osint-deck' ); ?>')) {
                    return;
                }

                $btn.prop('disabled', true).text('<?php _e( 'Procesando...', 'osint-deck' ); ?>');
                $result.html('<?php _e( 'Analizando herramientas...', 'osint-deck' ); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'osint_deck_force_download_icons',
                        nonce: '<?php echo wp_create_nonce( 'osint_deck_admin' ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<span style="color: green;">' + response.data.message + '</span>');
                        } else {
                            $result.html('<span style="color: red;">Error: ' + (response.data.message || 'Unknown error') + '</span>');
                        }
                    },
                    error: function() {
                        $result.html('<span style="color: red;"><?php _e( 'Error de conexión', 'osint-deck' ); ?></span>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('<?php _e( 'Forzar descarga de iconos', 'osint-deck' ); ?>');
                    }
                });
            });
        });
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
     * Save settings
     *
     * @return void
     */
    private function save_settings() {
        $theme_mode = isset( $_POST['theme_mode'] ) ? sanitize_text_field( $_POST['theme_mode'] ) : 'auto';
        $theme_selector = isset( $_POST['theme_selector'] ) ? sanitize_text_field( $_POST['theme_selector'] ) : '';
        $theme_token_light = isset( $_POST['theme_token_light'] ) ? sanitize_text_field( $_POST['theme_token_light'] ) : 'light';
        $theme_token_dark = isset( $_POST['theme_token_dark'] ) ? sanitize_text_field( $_POST['theme_token_dark'] ) : 'dark';
        $help_url = isset( $_POST['help_url'] ) ? esc_url_raw( $_POST['help_url'] ) : 'https://osint.com.ar/OsintDeck-Ayuda';
        $log_retention = isset( $_POST['log_retention'] ) ? intval( $_POST['log_retention'] ) : 30;
        $logging_enabled = isset( $_POST['logging_enabled'] ) ? (bool) $_POST['logging_enabled'] : false;

        update_option( 'osint_deck_theme_mode', $theme_mode );
        update_option( 'osint_deck_theme_selector', $theme_selector );
        update_option( 'osint_deck_theme_token_light', $theme_token_light );
        update_option( 'osint_deck_theme_token_dark', $theme_token_dark );
        update_option( 'osint_deck_help_url', $help_url );
        update_option( 'osint_deck_log_retention', $log_retention );
        update_option( 'osint_deck_logging_enabled', $logging_enabled );

        add_settings_error( 'osint_deck', 'settings_saved', __( 'Configuración guardada', 'osint-deck' ), 'success' );
        settings_errors( 'osint_deck' );
    }
}
