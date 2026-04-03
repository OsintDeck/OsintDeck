<?php
/**
 * Backup completo (datos del plugin, JSON, uploads) sin logs ni historial de eventos.
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Service;

use OsintDeck\Domain\Repository\CategoryRepositoryInterface;
use OsintDeck\Domain\Repository\ToolRepositoryInterface;
use OsintDeck\Domain\Service\NaiveBayesClassifier;
use OsintDeck\Infrastructure\Persistence\CategoriesTable;
use OsintDeck\Infrastructure\Persistence\DeckUsersTable;
use OsintDeck\Infrastructure\Persistence\SsoReportThanksPendingTable;
use OsintDeck\Infrastructure\Persistence\SsoToolFavoritesTable;
use OsintDeck\Infrastructure\Persistence\SsoToolLikesTable;
use OsintDeck\Infrastructure\Persistence\ToolReportsTable;
use OsintDeck\Infrastructure\Persistence\ToolsTable;

/**
 * Export/import ZIP con payload.json + carpeta uploads/osint-deck opcional.
 */
class DeckFullBackup {

    public const PAYLOAD_FORMAT_VERSION = 1;

    public const PAYLOAD_FILENAME = 'payload.json';

    public const ZIP_UPLOAD_PREFIX = 'uploads/osint-deck';

    /**
     * Arma el array serializable (sin tablas de logs ni user_history).
     *
     * @param ToolRepositoryInterface     $tool_repository     Repositorio de herramientas.
     * @param CategoryRepositoryInterface $category_repository Repositorio de categorías.
     * @return array<string, mixed>
     */
    public function build_payload( ToolRepositoryInterface $tool_repository, CategoryRepositoryInterface $category_repository ) {
        $categories = $category_repository->get_all_categories();

        $tools_export   = array();
        $tool_slug_ids  = array();
        foreach ( $tool_repository->get_all_tools() as $tool ) {
            if ( empty( $tool['_db_id'] ) ) {
                continue;
            }
            $clean = $tool_repository->export_to_json( (int) $tool['_db_id'] );
            if ( ! $clean ) {
                continue;
            }
            $tools_export[] = $clean;
            $tool_slug_ids[] = array(
                'slug' => isset( $clean['slug'] ) ? (string) $clean['slug'] : sanitize_title( $clean['name'] ?? '' ),
                'id'   => (int) $tool['_db_id'],
            );
        }

        return array(
            'backup_format_version' => self::PAYLOAD_FORMAT_VERSION,
            'plugin_version'        => defined( 'OSINT_DECK_VERSION' ) ? OSINT_DECK_VERSION : '',
            'created_at'            => gmdate( 'c' ),
            'categories'            => $categories,
            'tools'                 => $tools_export,
            'tool_slug_ids'         => $tool_slug_ids,
            'sso_users'             => self::dump_table_rows( DeckUsersTable::get_table_name() ),
            'sso_favorites'         => self::dump_table_rows( SsoToolFavoritesTable::get_table_name() ),
            'sso_likes'             => self::dump_table_rows( SsoToolLikesTable::get_table_name() ),
            'sso_report_thanks'     => self::dump_table_rows( SsoReportThanksPendingTable::get_table_name() ),
            'tool_reports'          => self::dump_table_rows( ToolReportsTable::get_table_name() ),
            'classifier'            => array(
                'model'   => get_option( NaiveBayesClassifier::OPTION_MODEL, null ),
                'samples' => get_option( NaiveBayesClassifier::OPTION_SAMPLES, array() ),
            ),
        );
    }

    /**
     * Envía un .zip por HTTP y termina.
     *
     * @param ToolRepositoryInterface     $tool_repository     Repo herramientas.
     * @param CategoryRepositoryInterface $category_repository Repo categorías.
     * @return void
     */
    public function stream_zip_download( ToolRepositoryInterface $tool_repository, CategoryRepositoryInterface $category_repository ) {
        if ( ! class_exists( '\ZipArchive' ) ) {
            wp_die( esc_html__( 'PHP ZipArchive no está disponible en este servidor. No se puede generar el backup completo hasta que extension=zip esté habilitada.', 'osint-deck' ) );
        }

        $payload  = $this->build_payload( $tool_repository, $category_repository );
        $json     = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

        $uploads = wp_upload_dir();
        $icons_base = '';
        if ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) {
            $icons_base = trailingslashit( $uploads['basedir'] ) . 'osint-deck';
        }

        $tmp_zip = wp_tempnam( 'osint-deck-full-backup.zip' );
        if ( ! $tmp_zip || ! is_writable( dirname( $tmp_zip ) ) ) {
            wp_die( esc_html__( 'No se pudo crear un archivo temporal para el backup.', 'osint-deck' ) );
        }

        @unlink( $tmp_zip );

        $zip = new \ZipArchive();
        if ( true !== $zip->open( $tmp_zip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
            wp_die( esc_html__( 'No se pudo crear el archivo ZIP.', 'osint-deck' ) );
        }

        $zip->addFromString( self::PAYLOAD_FILENAME, $json );

        if ( $icons_base !== '' && is_dir( $icons_base ) ) {
            $this->zip_add_directory( $zip, $icons_base, self::ZIP_UPLOAD_PREFIX );
        }

        $zip->close();

        nocache_headers();
        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="osint-deck-full-backup-' . gmdate( 'Y-m-d-His' ) . '.zip"' );
        header( 'Content-Length: ' . (string) filesize( $tmp_zip ) );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
        readfile( $tmp_zip );
        @unlink( $tmp_zip );
        exit;
    }

    /**
     * Restaura desde un ZIP subido. Borra datos actuales de las tablas incluidas y vuelve a insertar.
     *
     * @param string                      $zip_path            Ruta absoluta al .zip temporal.
     * @param ToolRepositoryInterface     $tool_repository     Repo herramientas.
     * @param CategoryRepositoryInterface $category_repository Repo categorías.
     * @return array{ok: bool, message: string}
     */
    public function import_from_zip( $zip_path, ToolRepositoryInterface $tool_repository, CategoryRepositoryInterface $category_repository ) {
        if ( ! class_exists( '\ZipArchive' ) ) {
            return array(
                'ok'      => false,
                'message' => __( 'PHP ZipArchive no está disponible.', 'osint-deck' ),
            );
        }

        if ( ! is_readable( $zip_path ) ) {
            return array(
                'ok'      => false,
                'message' => __( 'No se pudo leer el archivo subido.', 'osint-deck' ),
            );
        }

        $temp = trailingslashit( get_temp_dir() ) . 'osint-deck-restore-' . wp_generate_password( 12, false );

        if ( ! wp_mkdir_p( $temp ) ) {
            return array(
                'ok'      => false,
                'message' => __( 'No se pudo crear carpeta temporal.', 'osint-deck' ),
            );
        }

        $zip = new \ZipArchive();
        if ( true !== $zip->open( $zip_path ) ) {
            $this->remove_directory( $temp );
            return array(
                'ok'      => false,
                'message' => __( 'No es un archivo ZIP válido.', 'osint-deck' ),
            );
        }
        $zip->extractTo( $temp );
        $zip->close();

        $payload_file = $temp . '/' . self::PAYLOAD_FILENAME;
        if ( ! is_readable( $payload_file ) ) {
            $this->remove_directory( $temp );
            return array(
                'ok'      => false,
                'message' => __( 'El ZIP no contiene payload.json (backup OSINT Deck).', 'osint-deck' ),
            );
        }

        $payload = json_decode( file_get_contents( $payload_file ), true );
        if ( ! is_array( $payload ) || (int) ( $payload['backup_format_version'] ?? 0 ) !== self::PAYLOAD_FORMAT_VERSION ) {
            $this->remove_directory( $temp );
            return array(
                'ok'      => false,
                'message' => __( 'Formato de backup no reconocido o versión incompatible.', 'osint-deck' ),
            );
        }

        $result = $this->restore_payload( $payload, $tool_repository, $category_repository );

        $upload_src = $temp . '/' . self::ZIP_UPLOAD_PREFIX;
        if ( is_dir( $upload_src ) ) {
            $uploads = wp_upload_dir();
            if ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) {
                $dest = trailingslashit( $uploads['basedir'] ) . 'osint-deck';
                wp_mkdir_p( $dest );
                $this->copy_directory( $upload_src, $dest );
            }
        }

        $this->remove_directory( $temp );

        return $result;
    }

    /**
     * @param array<string, mixed>        $payload             Datos del JSON.
     * @param ToolRepositoryInterface     $tool_repository     Repo herramientas.
     * @param CategoryRepositoryInterface $category_repository Repo categorías.
     * @return array{ok: bool, message: string}
     */
    private function restore_payload( array $payload, ToolRepositoryInterface $tool_repository, CategoryRepositoryInterface $category_repository ) {
        global $wpdb;

        self::truncate_plugin_content_tables();

        if ( ! empty( $payload['categories'] ) && is_array( $payload['categories'] ) ) {
            foreach ( $payload['categories'] as $cat ) {
                if ( ! is_array( $cat ) || empty( $cat['code'] ) ) {
                    continue;
                }
                unset( $cat['id'] );
                $category_repository->save_category( $cat );
            }
        }

        if ( ! empty( $payload['tools'] ) && is_array( $payload['tools'] ) ) {
            foreach ( $payload['tools'] as $tool ) {
                if ( ! is_array( $tool ) || empty( $tool['name'] ) ) {
                    continue;
                }
                $tool_repository->import_from_json( $tool );
            }
        }

        $old_to_new_tool = array();
        if ( ! empty( $payload['tool_slug_ids'] ) && is_array( $payload['tool_slug_ids'] ) ) {
            foreach ( $payload['tool_slug_ids'] as $pair ) {
                if ( empty( $pair['slug'] ) || ! isset( $pair['id'] ) ) {
                    continue;
                }
                $slug = (string) $pair['slug'];
                $old  = (int) $pair['id'];
                $t    = $tool_repository->get_tool_by_slug( $slug );
                if ( $t && ! empty( $t['_db_id'] ) ) {
                    $old_to_new_tool[ $old ] = (int) $t['_db_id'];
                }
            }
        }

        $old_to_new_user = array();
        if ( ! empty( $payload['sso_users'] ) && is_array( $payload['sso_users'] ) ) {
            $table = DeckUsersTable::get_table_name();
            foreach ( $payload['sso_users'] as $row ) {
                if ( ! is_array( $row ) || empty( $row['google_sub'] ) ) {
                    continue;
                }
                $old_id = isset( $row['id'] ) ? (int) $row['id'] : 0;
                $ins    = $wpdb->insert(
                    $table,
                    array(
                        'google_sub'   => (string) $row['google_sub'],
                        'user_email'   => (string) ( $row['user_email'] ?? '' ),
                        'display_name' => (string) ( $row['display_name'] ?? '' ),
                        'avatar_url'   => isset( $row['avatar_url'] ) ? (string) $row['avatar_url'] : '',
                        'created_at'   => ! empty( $row['created_at'] ) ? (string) $row['created_at'] : current_time( 'mysql' ),
                        'updated_at'   => ! empty( $row['updated_at'] ) ? (string) $row['updated_at'] : current_time( 'mysql' ),
                    ),
                    array( '%s', '%s', '%s', '%s', '%s', '%s' )
                );
                if ( false !== $ins && $old_id > 0 ) {
                    $old_to_new_user[ $old_id ] = (int) $wpdb->insert_id;
                }
            }
        }

        self::restore_sso_pairs( SsoToolFavoritesTable::get_table_name(), $payload['sso_favorites'] ?? array(), $old_to_new_user, $old_to_new_tool, 'deck_user_id', 'tool_id' );
        self::restore_sso_pairs( SsoToolLikesTable::get_table_name(), $payload['sso_likes'] ?? array(), $old_to_new_user, $old_to_new_tool, 'deck_user_id', 'tool_id' );
        self::restore_sso_pairs( SsoReportThanksPendingTable::get_table_name(), $payload['sso_report_thanks'] ?? array(), $old_to_new_user, $old_to_new_tool, 'deck_user_id', 'tool_id' );

        if ( ! empty( $payload['tool_reports'] ) && is_array( $payload['tool_reports'] ) ) {
            $rtable = ToolReportsTable::get_table_name();
            foreach ( $payload['tool_reports'] as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }
                $old_tid = isset( $row['tool_id'] ) ? (int) $row['tool_id'] : 0;
                $tid     = $old_to_new_tool[ $old_tid ] ?? null;
                if ( ! $tid ) {
                    continue;
                }
                $old_uid = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;
                $new_uid = 0;
                if ( $old_uid > 0 ) {
                    $new_uid = (int) ( $old_to_new_user[ $old_uid ] ?? 0 );
                    if ( $new_uid <= 0 ) {
                        continue;
                    }
                }
                $ins_report = array(
                    'tool_id'    => $tid,
                    'user_id'    => $new_uid,
                    'fp_hash'    => isset( $row['fp_hash'] ) ? substr( preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $row['fp_hash'] ) ), 0, 32 ) : '',
                    'message'    => isset( $row['message'] ) ? (string) $row['message'] : '',
                    'status'     => ! empty( $row['status'] ) ? (string) $row['status'] : 'open',
                    'created_at' => ! empty( $row['created_at'] ) ? (string) $row['created_at'] : current_time( 'mysql' ),
                );
                $fmt_report = array( '%d', '%d', '%s', '%s', '%s', '%s' );
                if ( ! empty( $row['resolved_at'] ) ) {
                    $ins_report['resolved_at'] = (string) $row['resolved_at'];
                    $fmt_report[]              = '%s';
                }
                $wpdb->insert( $rtable, $ins_report, $fmt_report );
            }
        }

        if ( isset( $payload['classifier'] ) && is_array( $payload['classifier'] ) ) {
            update_option( NaiveBayesClassifier::OPTION_MODEL, $payload['classifier']['model'] ?? null, false );
            $samples = $payload['classifier']['samples'] ?? array();
            update_option( NaiveBayesClassifier::OPTION_SAMPLES, is_array( $samples ) ? $samples : array(), false );
        }

        return array(
            'ok'      => true,
            'message' => __( 'Backup completo importado correctamente (categorías, herramientas, SSO, reportes, clasificador e iconos).', 'osint-deck' ),
        );
    }

    /**
     * @param string               $table            Nombre con prefijo.
     * @param array<int, mixed>    $rows             Filas guardadas.
     * @param array<int, int>      $old_to_new_user  Mapa id usuario deck.
     * @param array<int, int>      $old_to_new_tool  Mapa id herramienta.
     * @param string               $user_col         Campo usuario.
     * @param string               $tool_col         Campo tool.
     * @return void
     */
    private static function restore_sso_pairs( $table, $rows, array $old_to_new_user, array $old_to_new_tool, $user_col, $tool_col ) {
        global $wpdb;

        if ( ! is_array( $rows ) || array() === $rows ) {
            return;
        }

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $ou = isset( $row[ $user_col ] ) ? (int) $row[ $user_col ] : 0;
            $ot = isset( $row[ $tool_col ] ) ? (int) $row[ $tool_col ] : 0;
            $nu = $old_to_new_user[ $ou ] ?? null;
            $nt = $old_to_new_tool[ $ot ] ?? null;
            if ( ! $nu || ! $nt ) {
                continue;
            }
            $wpdb->replace(
                $table,
                array(
                    $user_col => $nu,
                    $tool_col => $nt,
                ),
                array( '%d', '%d' )
            );
        }
    }

    /**
     * @return void
     */
    private static function truncate_plugin_content_tables() {
        global $wpdb;

        $tables = array(
            SsoToolFavoritesTable::get_table_name(),
            SsoToolLikesTable::get_table_name(),
            SsoReportThanksPendingTable::get_table_name(),
            ToolReportsTable::get_table_name(),
            ToolsTable::get_table_name(),
            CategoriesTable::get_table_name(),
            DeckUsersTable::get_table_name(),
        );

        foreach ( $tables as $t ) {
            if ( self::table_exists( $t ) ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query( "DELETE FROM `{$t}`" );
            }
        }
    }

    /**
     * @param string $full_name Tabla con prefijo.
     * @return bool
     */
    private static function table_exists( $full_name ) {
        global $wpdb;

        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_name ) ) === $full_name;
    }

    /**
     * @param string $full_name Tabla con prefijo.
     * @return array<int, array<string, mixed>>
     */
    private static function dump_table_rows( $full_name ) {
        global $wpdb;

        if ( ! self::table_exists( $full_name ) ) {
            return array();
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results( "SELECT * FROM `{$full_name}`", ARRAY_A );

        return is_array( $rows ) ? $rows : array();
    }

    /**
     * @param ZipArchive $zip         ZIP abierto.
     * @param string     $real_dir   Directorio absoluto en disco.
     * @param string     $zip_prefix Prefijo dentro del ZIP (sin barra final).
     * @return void
     */
    private function zip_add_directory( \ZipArchive $zip, $real_dir, $zip_prefix ) {
        $base = realpath( $real_dir );
        if ( false === $base || ! is_dir( $base ) ) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $base, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ( $iterator as $file ) {
            /** @var \SplFileInfo $file */
            if ( ! $file->isFile() ) {
                continue;
            }
            $path = $file->getRealPath();
            if ( false === $path ) {
                continue;
            }
            $rel = substr( $path, strlen( $base ) + 1 );
            if ( '' === $rel ) {
                continue;
            }
            $entry = $zip_prefix . '/' . str_replace( '\\', '/', $rel );
            $zip->addFile( $path, $entry );
        }
    }

    /**
     * @param string $src  Origen.
     * @param string $dest Destino.
     * @return void
     */
    private function copy_directory( $src, $dest ) {
        $src  = rtrim( wp_normalize_path( $src ), '/' );
        $dest = rtrim( wp_normalize_path( $dest ), '/' );

        if ( ! is_dir( $src ) ) {
            return;
        }

        wp_mkdir_p( $dest );

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $src, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $iterator as $item ) {
            /** @var \SplFileInfo $item */
            $sub = $iterator->getSubPathname();
            $sub = str_replace( '\\', '/', $sub );
            $target = $dest . '/' . $sub;
            if ( $item->isDir() ) {
                wp_mkdir_p( $target );
            } else {
                wp_mkdir_p( dirname( $target ) );
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
                @copy( $item->getPathname(), $target );
            }
        }
    }

    /**
     * @param string $dir Directorio temporal.
     * @return void
     */
    private function remove_directory( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $iterator as $item ) {
            /** @var \SplFileInfo $item */
            $path = $item->getPathname();
            if ( $item->isDir() ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
                @rmdir( $path );
            } else {
                wp_delete_file( $path );
            }
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
        @rmdir( $dir );
    }
}
