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
        
        // Initialize sub-components for tabs
        $this->import_export_manager = new ImportExport( $tool_repository, $category_repository );
        $this->tld_manager_admin = new TLDManagerAdmin( $tld_manager );
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function render() {
        $allowed_tabs = array( 'general', 'data', 'tlds' );
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
                <a href="?page=osint-deck-settings&tab=data" class="nav-tab <?php echo $active_tab == 'data' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Datos', 'osint-deck' ); ?></a>
                <a href="?page=osint-deck-settings&tab=tlds" class="nav-tab <?php echo $active_tab == 'tlds' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Dominios / TLDs', 'osint-deck' ); ?></a>
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

        update_option( 'osint_deck_theme_mode', $theme_mode );
        update_option( 'osint_deck_theme_selector', $theme_selector );
        update_option( 'osint_deck_theme_token_light', $theme_token_light );
        update_option( 'osint_deck_theme_token_dark', $theme_token_dark );
        update_option( 'osint_deck_help_url', $help_url );

        add_settings_error( 'osint_deck', 'settings_saved', __( 'Configuración guardada', 'osint-deck' ), 'success' );
        settings_errors( 'osint_deck' );
    }
}
