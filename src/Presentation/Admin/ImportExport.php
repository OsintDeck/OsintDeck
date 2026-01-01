<?php
/**
 * Import/Export - Handle tool import/export
 *
 * @package OsintDeck
 */

namespace OsintDeck\Presentation\Admin;

use OsintDeck\Domain\Repository\ToolRepositoryInterface;
use OsintDeck\Domain\Repository\CategoryRepositoryInterface;
use OsintDeck\Infrastructure\Service\Migration;

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
     * Constructor
     *
     * @param ToolRepositoryInterface $tool_repository Tool Repository.
     * @param CategoryRepositoryInterface $category_repository Category Repository.
     */
    public function __construct( ToolRepositoryInterface $tool_repository, CategoryRepositoryInterface $category_repository ) {
        $this->tool_repository = $tool_repository;
        $this->category_repository = $category_repository;
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

        // Handle export
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'export' ) {
            check_admin_referer( 'osint_deck_export' );
            $this->handle_export();
            exit;
        }

        // Handle seed categories
        if ( isset( $_POST['osint_deck_seed_submit'] ) ) {
            check_admin_referer( 'osint_deck_seed' );
            $this->handle_seed();
        }

        // Handle migration
        if ( isset( $_POST['osint_deck_migrate_submit'] ) ) {
            check_admin_referer( 'osint_deck_migrate' );
            $this->handle_migration();
        }

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

                        <h3><?php _e( 'Opción 1: Subir archivo JSON', 'osint-deck' ); ?></h3>
                        <input type="file" name="import_file" accept=".json">

                        <h3 style="margin-top:20px;"><?php _e( 'Opción 2: Pegar JSON', 'osint-deck' ); ?></h3>
                        <textarea name="import_json" rows="10" class="osint-json-textarea" placeholder='[{"name":"Tool Name",...}]'></textarea>

                        <p class="submit">
                            <input type="submit" name="osint_deck_import_submit" class="button button-primary" value="<?php _e( 'Importar', 'osint-deck' ); ?>">
                        </p>
                    </form>
                </div>

                <!-- Export -->
                <div class="osint-card-panel">
                    <h2><?php _e( 'Exportar Herramientas', 'osint-deck' ); ?></h2>
                    <p><?php _e( 'Exportá todas las herramientas a un archivo JSON:', 'osint-deck' ); ?></p>

                    <p>
                        <strong><?php _e( 'Total de herramientas:', 'osint-deck' ); ?></strong>
                        <?php echo $this->tool_repository->count_tools(); ?>
                    </p>

                    <p>
                        <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=osint-deck-import-export&action=export' ), 'osint_deck_export' ); ?>" class="button button-primary">
                            <?php _e( 'Descargar JSON', 'osint-deck' ); ?>
                        </a>
                    </p>

                    <div class="osint-import-export-actions">
                        <h3><?php _e( 'Scripts de Utilidad', 'osint-deck' ); ?></h3>
                        
                        <form method="post" style="margin-bottom:10px;">
                            <?php wp_nonce_field( 'osint_deck_seed' ); ?>
                            <input type="submit" name="osint_deck_seed_submit" class="button" value="<?php _e( 'Crear Herramientas de Prueba', 'osint-deck' ); ?>" onclick="return confirm('<?php _e( '¿Estás seguro? Esto importará las herramientas por defecto.', 'osint-deck' ); ?>');">
                        </form>

                        <form method="post">
                            <?php wp_nonce_field( 'osint_deck_migrate' ); ?>
                            <input type="submit" name="osint_deck_migrate_submit" class="button" value="<?php _e( 'Migrar desde Legacy', 'osint-deck' ); ?>" onclick="return confirm('<?php _e( '¿Estás seguro? Esto migrará las herramientas desde la opción antigua.', 'osint-deck' ); ?>');">
                        </form>
                    </div>
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

            // Use repository import logic which handles upsert
            $result = $this->tool_repository->import_from_json( $tool );

            if ( ! is_wp_error( $result ) && $result !== false ) {
                $imported++;
            } else {
                $errors[] = "Failed to import {$tool['name']}";
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
     * Handle export
     */
    private function handle_export() {
        $tools = $this->tool_repository->get_all_tools();
        
        // Clean up internal fields
        $export_tools = array();
        foreach ( $tools as $tool ) {
            $clean_tool = $this->tool_repository->export_to_json( $tool['_db_id'] );
            if ( $clean_tool ) {
                $export_tools[] = $clean_tool;
            }
        }

        $json = json_encode( $export_tools, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        
        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename="osint-deck-tools-' . date('Y-m-d') . '.json"' );
        echo $json;
        exit;
    }

    /**
     * Handle seed
     */
    private function handle_seed() {
        // Implement seeding logic here if needed, or rely on repository
        add_settings_error( 'osint_deck', 'seed_info', __( 'Funcionalidad de seed manual no implementada completamente aquí.', 'osint-deck' ), 'info' );
        settings_errors( 'osint_deck' );
    }

    /**
     * Handle migration
     */
    private function handle_migration() {
        $migration = new Migration( $this->tool_repository );
        $result = $migration->migrate_from_option();

        if ( $result['success'] ) {
             add_settings_error( 'osint_deck', 'migrate_success', $result['message'], 'success' );
        } else {
             add_settings_error( 'osint_deck', 'migrate_error', $result['message'], 'error' );
        }
        settings_errors( 'osint_deck' );
    }
}
