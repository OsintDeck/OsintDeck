<?php
/**
 * Import/Export - Handle tool import/export
 *
 * @package OsintDeck
 */

namespace OsintDeck\Presentation\Admin;

use OsintDeck\Domain\Repository\ToolRepositoryInterface;
use OsintDeck\Domain\Repository\CategoryRepositoryInterface;
use OsintDeck\Infrastructure\Service\IconManager;
use OsintDeck\Infrastructure\Service\Logger;
use OsintDeck\Infrastructure\Service\DeckFullBackup;

/**
 * Class ImportExport
 * 
 * Handles import/export functionality
 */
class ImportExport {

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
     * Icon Manager
     *
     * @var IconManager
     */
    private $icon_manager;

    /**
     * Logger
     *
     * @var Logger|null
     */
    private $logger;

    /**
     * Constructor
     *
     * @param ToolRepositoryInterface $tool_repository Tool Repository.
     * @param CategoryRepositoryInterface $category_repository Category Repository.
     * @param Logger|null $logger Logger.
     */
    public function __construct( ToolRepositoryInterface $tool_repository, CategoryRepositoryInterface $category_repository, Logger $logger = null ) {
        $this->tool_repository = $tool_repository;
        $this->category_repository = $category_repository;
        $this->logger = $logger;
        $this->icon_manager = new IconManager( $logger );
    }

    /**
     * Render import/export page
     *
     * @return void
     */
    public function render() {
        // Handle import
        if ( isset( $_POST['osint_deck_import_submit'] ) ) {
            check_admin_referer( 'osint_deck_import' );
            $this->handle_import();
        }

        // Import backup completo (.zip)
        if ( isset( $_POST['osint_deck_full_backup_import_submit'] ) ) {
            check_admin_referer( 'osint_deck_full_backup_import' );
            $this->handle_full_backup_import();
        }

        $export_url      = wp_nonce_url( admin_url( 'admin.php?page=osint-deck-settings&tab=data&action=export' ), 'osint_deck_export' );
        $export_full_url = wp_nonce_url( admin_url( 'admin.php?page=osint-deck-settings&tab=data&action=export_full' ), 'osint_deck_export_full' );

        ?>
        <div class="wrap osint-deck-admin-wrap">
            <h1><?php _e( 'Importar/Exportar Herramientas', 'osint-deck' ); ?></h1>

            <div class="osint-grid-2">
                <!-- Import -->
                <div class="osint-card-panel">
                    <h2><?php _e( 'Importar Herramientas', 'osint-deck' ); ?></h2>
                    <p><?php _e( 'Importá herramientas desde un archivo JSON o pegá el JSON directamente:', 'osint-deck' ); ?></p>

                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'osint_deck_import' ); ?>

                        <h4><?php esc_html_e( 'Opción 1: subir archivo JSON', 'osint-deck' ); ?></h4>
                        <input type="file" name="import_file" accept=".json,application/json">

                        <h4 class="osint-import-mt"><?php esc_html_e( 'Opción 2: pegar JSON', 'osint-deck' ); ?></h4>
                        <textarea name="import_json" rows="10" class="osint-json-textarea" placeholder='[{"name":"Tool Name","category":"codigo-cat",...}]'></textarea>

                        <p class="submit">
                            <input type="submit" name="osint_deck_import_submit" class="button button-primary" value="<?php esc_attr_e( 'Importar', 'osint-deck' ); ?>">
                        </p>
                    </form>
                </div>

                <!-- Export -->
                <div class="osint-card-panel">
                    <h3><?php esc_html_e( 'Backup / exportar', 'osint-deck' ); ?></h3>
                    <p><?php esc_html_e( 'Descarga un único archivo JSON con todas las herramientas (copia de seguridad ligera).', 'osint-deck' ); ?></p>

                    <p>
                        <strong><?php esc_html_e( 'Total de herramientas:', 'osint-deck' ); ?></strong>
                        <?php echo esc_html( (string) $this->tool_repository->count_tools() ); ?>
                    </p>

                    <p>
                        <a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary">
                            <?php esc_html_e( 'Descargar backup JSON', 'osint-deck' ); ?>
                        </a>
                    </p>

                    <p class="description" style="margin-top:1em;">
                        <?php esc_html_e( 'Backup completo (ZIP): categorías, herramientas, usuarios SSO, favoritos, likes, reportes, modelo de clasificador y carpeta de iconos en uploads. No incluye tablas de logs ni historial de eventos.', 'osint-deck' ); ?>
                    </p>
                    <p>
                        <a href="<?php echo esc_url( $export_full_url ); ?>" class="button button-secondary">
                            <?php esc_html_e( 'Descargar backup completo (ZIP)', 'osint-deck' ); ?>
                        </a>
                    </p>

                    <form method="post" enctype="multipart/form-data" class="osint-full-backup-import-form" style="margin-top:1em;">
                        <?php wp_nonce_field( 'osint_deck_full_backup_import' ); ?>
                        <label for="osint_full_backup_zip"><strong><?php esc_html_e( 'Importar backup completo (ZIP)', 'osint-deck' ); ?></strong></label>
                        <p>
                            <input type="file" name="full_backup_zip" id="osint_full_backup_zip" accept=".zip,application/zip">
                        </p>
                        <p class="submit">
                            <input type="submit" name="osint_deck_full_backup_import_submit" class="button button-primary" value="<?php esc_attr_e( 'Restaurar desde ZIP', 'osint-deck' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Esto reemplazará categorías, herramientas, datos SSO, reportes e iconos del backup. Los logs y el historial de eventos no se tocan. ¿Continuar?', 'osint-deck' ) ); ?>">
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle import
     *
     * @return void
     */
    private function handle_import() {
        $json = '';

        // From file
        if ( ! empty( $_FILES['import_file']['tmp_name'] ) ) {
            $json = file_get_contents( $_FILES['import_file']['tmp_name'] );
        }
        // From textarea
        elseif ( ! empty( $_POST['import_json'] ) ) {
            $json = stripslashes( $_POST['import_json'] );
        }

        if ( empty( $json ) ) {
            add_settings_error( 'osint_deck', 'import_empty', __( 'No se proporcionó ningún JSON', 'osint-deck' ), 'error' );
            settings_errors( 'osint_deck' );
            return;
        }

        $tools = json_decode( $json, true );

        if ( ! is_array( $tools ) ) {
            $error_msg = __( 'JSON inválido', 'osint-deck' );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $error_msg .= ': ' . json_last_error_msg();
            }
            add_settings_error( 'osint_deck', 'import_invalid', $error_msg, 'error' );
            settings_errors( 'osint_deck' );
            return;
        }

        // If single tool, wrap in array
        if ( isset( $tools['name'] ) ) {
            $tools = array( $tools );
        }

        $this->process_import( $tools );
    }

    /**
     * Process import of tools
     *
     * @param array $tools Array of tools to import.
     * @return void
     */
    private function process_import( $tools ) {
        $imported = 0;
        $updated = 0;
        $errors = array();

        if ( $this->logger ) {
            $this->logger->info( 'Starting import of ' . count( $tools ) . ' tools.' );
        }

        foreach ( $tools as $tool ) {
            if ( empty( $tool['name'] ) ) {
                $errors[] = 'Tool without name skipped';
                continue;
            }

            // Validation: Check if category exists
            $category_code = isset( $tool['category'] ) ? $tool['category'] : '';
            if ( empty( $category_code ) ) {
                $errors[] = sprintf( __( 'Herramienta "%s" omitida: falta categoría.', 'osint-deck' ), $tool['name'] );
                continue;
            }

            if ( ! $this->category_repository->get_category_by_code( $category_code ) ) {
                 $errors[] = sprintf( __( 'Herramienta "%s" omitida: categoría "%s" no existe.', 'osint-deck' ), $tool['name'], $category_code );
                 continue;
            }

            // Download icon if present
            if ( ! empty( $tool['favicon'] ) ) {
                $tool['favicon'] = $this->icon_manager->download_icon( $tool['favicon'], $tool['name'] );
            }

            // Use repository import logic which handles upsert
            $result = $this->tool_repository->import_from_json( $tool );

            if ( ! is_wp_error( $result ) && $result !== false ) {
                $imported++;
            } else {
                $errors[] = "Failed to import {$tool['name']}";
            }
        }

        if ( $this->logger ) {
            $this->logger->info( "Import completed. Imported: $imported, Updated: $updated, Errors: " . count( $errors ) );
            if ( ! empty( $errors ) ) {
                $this->logger->error( 'Import errors: ' . implode( '; ', $errors ) );
            }
        }

        if ( $imported > 0 ) {
             add_settings_error( 'osint_deck', 'import_success', sprintf( __( 'Se importaron %d herramientas.', 'osint-deck' ), $imported ), 'success' );
        }
        
        if ( ! empty( $errors ) ) {
            add_settings_error( 'osint_deck', 'import_errors', sprintf( __( 'Errores: %s', 'osint-deck' ), implode( ', ', $errors ) ), 'error' );
        }
        
        settings_errors( 'osint_deck' );
    }

    /**
     * Envía la descarga JSON y termina la petición. Llamar solo antes de enviar HTML.
     *
     * @return void
     */
    public function stream_export_download() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permisos para exportar herramientas.', 'osint-deck' ) );
        }

        $tools = $this->tool_repository->get_all_tools();

        $export_tools = array();
        foreach ( $tools as $tool ) {
            if ( empty( $tool['_db_id'] ) ) {
                continue;
            }
            $clean_tool = $this->tool_repository->export_to_json( (int) $tool['_db_id'] );
            if ( $clean_tool ) {
                $export_tools[] = $clean_tool;
            }
        }

        $json = wp_json_encode( $export_tools, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="osint-deck-tools-' . gmdate( 'Y-m-d' ) . '.json"' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $json;
        exit;
    }

    /**
     * Envía ZIP con payload + uploads/osint-deck.
     *
     * @return void
     */
    public function stream_full_backup_download() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permisos para exportar.', 'osint-deck' ) );
        }

        $backup = new DeckFullBackup();
        $backup->stream_zip_download( $this->tool_repository, $this->category_repository );
    }

    /**
     * Restaura desde archivo ZIP subido en el mismo formulario de datos.
     *
     * @return void
     */
    private function handle_full_backup_import() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permisos para importar.', 'osint-deck' ) );
        }

        if ( empty( $_FILES['full_backup_zip']['tmp_name'] ) || ! is_uploaded_file( $_FILES['full_backup_zip']['tmp_name'] ) ) {
            add_settings_error( 'osint_deck', 'full_backup_no_file', __( 'No se subió ningún archivo ZIP.', 'osint-deck' ), 'error' );
            settings_errors( 'osint_deck' );
            return;
        }

        $backup = new DeckFullBackup();
        $result = $backup->import_from_zip( $_FILES['full_backup_zip']['tmp_name'], $this->tool_repository, $this->category_repository );

        if ( ! empty( $result['ok'] ) ) {
            add_settings_error( 'osint_deck', 'full_backup_ok', $result['message'], 'success' );
        } else {
            add_settings_error( 'osint_deck', 'full_backup_err', $result['message'], 'error' );
        }
        settings_errors( 'osint_deck' );
    }
}
