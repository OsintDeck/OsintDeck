<?php
/**
 * Reportes de problemas por herramienta (sesión o huella anónima).
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Persistence;

/**
 * Un reporte “abierto” por actor (user_id o fp_hash); toggle retira.
 */
class ToolReports {

    /**
     * @param string $fp Huella cruda del cliente.
     */
    public static function fp_hash( $fp ) {
        $fp = is_string( $fp ) ? trim( $fp ) : '';
        return $fp === '' ? '' : md5( $fp );
    }

    /**
     * @param int    $tool_id Tool _db_id.
     * @param int    $user_id 0 si anónimo.
     * @param string $fp_hash md5(fp) o '' si solo usuario.
     */
    public static function find_open_row( $tool_id, $user_id, $fp_hash ) {
        global $wpdb;
        $table   = ToolReportsTable::get_table_name();
        $tool_id = (int) $tool_id;
        $user_id = (int) $user_id;
        $fp_hash = is_string( $fp_hash ) ? $fp_hash : '';

        if ( $user_id > 0 ) {
            return $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE tool_id = %d AND user_id = %d AND status = %s LIMIT 1",
                    $tool_id,
                    $user_id,
                    'open'
                ),
                ARRAY_A
            );
        }

        if ( $fp_hash === '' ) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE tool_id = %d AND user_id = 0 AND fp_hash = %s AND status = %s LIMIT 1",
                $tool_id,
                $fp_hash,
                'open'
            ),
            ARRAY_A
        );
    }

    /**
     * @param int         $tool_id Tool _db_id.
     * @param int         $user_id 0 anónimo.
     * @param string      $fp_hash md5(fp) para anónimo.
     * @param string|null $message Solo usuarios logueados.
     * @return int|false Insert id.
     */
    public static function insert_open( $tool_id, $user_id, $fp_hash, $message ) {
        global $wpdb;
        $table     = ToolReportsTable::get_table_name();
        $tool_id   = (int) $tool_id;
        $user_id   = (int) $user_id;
        $fp_hash   = is_string( $fp_hash ) ? substr( preg_replace( '/[^a-f0-9]/', '', strtolower( $fp_hash ) ), 0, 32 ) : '';
        $message = (string) $message;
        if ( $user_id <= 0 ) {
            $message = '';
        }

        $ok = $wpdb->insert(
            $table,
            array(
                'tool_id'    => $tool_id,
                'user_id'    => $user_id,
                'fp_hash'    => $user_id > 0 ? '' : $fp_hash,
                'message'    => $message,
                'status'     => 'open',
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s' )
        );

        return false !== $ok ? (int) $wpdb->insert_id : false;
    }

    /**
     * Elimina un reporte “open” (usuario se arrepintió).
     *
     * @param int $row_id PK.
     * @return bool
     */
    public static function delete_open_by_id( $row_id ) {
        global $wpdb;
        $table = ToolReportsTable::get_table_name();
        $row_id = (int) $row_id;
        $n       = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE id = %d AND status = %s",
                $row_id,
                'open'
            )
        );
        return $n !== false && $n > 0;
    }

    /**
     * Lista de tool_id con reporte abierto por usuario logueado.
     *
     * @param int $user_id User id.
     * @return int[]
     */
    public static function get_open_tool_ids_for_user( $user_id ) {
        global $wpdb;
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            return array();
        }
        $table = ToolReportsTable::get_table_name();
        $sql   = $wpdb->prepare(
            "SELECT DISTINCT tool_id FROM {$table} WHERE user_id = %d AND status = %s",
            $user_id,
            'open'
        );
        $rows = $wpdb->get_col( $sql );
        return array_map( 'intval', is_array( $rows ) ? $rows : array() );
    }

    /**
     * tool_id con reporte abierto para huella anónima (md5 del fp crudo).
     *
     * @param string $fp_hash Hash normalizado (32 hex).
     * @return int[]
     */
    public static function get_open_tool_ids_for_fp_hash( $fp_hash ) {
        global $wpdb;
        $fp_hash = is_string( $fp_hash ) ? substr( preg_replace( '/[^a-f0-9]/', '', strtolower( $fp_hash ) ), 0, 32 ) : '';
        if ( $fp_hash === '' ) {
            return array();
        }
        $table = ToolReportsTable::get_table_name();
        $rows  = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT tool_id FROM {$table} WHERE user_id = 0 AND fp_hash = %s AND status = %s",
                $fp_hash,
                'open'
            )
        );
        return array_map( 'intval', is_array( $rows ) ? $rows : array() );
    }

    /**
     * Cantidad de reportes abiertos (suma en filas, no stats JSON).
     */
    public static function count_open_total() {
        global $wpdb;
        $table = ToolReportsTable::get_table_name();
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'open' ) );
    }

    /**
     * Filas abiertas para el panel admin.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_all_open_rows() {
        global $wpdb;
        $table = ToolReportsTable::get_table_name();
        $sql   = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC",
            'open'
        );
        $rows = $wpdb->get_results( $sql, ARRAY_A );
        return is_array( $rows ) ? $rows : array();
    }

    /**
     * Filas abiertas solo para una herramienta (p. ej. antes de resolver y notificar).
     *
     * @param int $tool_id Tool _db_id.
     * @return array<int, array<string, mixed>>
     */
    public static function get_open_rows_for_tool( $tool_id ) {
        global $wpdb;
        $table   = ToolReportsTable::get_table_name();
        $tool_id = (int) $tool_id;
        $rows    = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE tool_id = %d AND status = %s ORDER BY created_at ASC",
                $tool_id,
                'open'
            ),
            ARRAY_A
        );
        return is_array( $rows ) ? $rows : array();
    }

    /**
     * Marca todos los abiertos de una herramienta como resueltos.
     *
     * @param int $tool_id Tool _db_id.
     * @return array<int, array{user_id: int, fp_hash: string}> Afectados (agradecimiento).
     */
    public static function resolve_all_open_for_tool( $tool_id ) {
        global $wpdb;
        $table   = ToolReportsTable::get_table_name();
        $tool_id = (int) $tool_id;
        $rows    = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id, fp_hash FROM {$table} WHERE tool_id = %d AND status = %s",
                $tool_id,
                'open'
            ),
            ARRAY_A
        );
        if ( ! is_array( $rows ) || array() === $rows ) {
            return array();
        }
        $now = current_time( 'mysql' );
        foreach ( $rows as $row ) {
            $wpdb->update(
                $table,
                array(
                    'status'      => 'resolved',
                    'resolved_at' => $now,
                ),
                array( 'id' => (int) $row['id'] ),
                array( '%s', '%s' ),
                array( '%d' )
            );
        }
        $thanks = array();
        foreach ( $rows as $row ) {
            $thanks[] = array(
                'user_id' => (int) $row['user_id'],
                'fp_hash' => isset( $row['fp_hash'] ) ? (string) $row['fp_hash'] : '',
            );
        }
        return $thanks;
    }

    /**
     * Cuenta filas con reporte abierto para una herramienta.
     */
    public static function count_open_for_tool( $tool_id ) {
        global $wpdb;
        $table = ToolReportsTable::get_table_name();
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE tool_id = %d AND status = %s",
                (int) $tool_id,
                'open'
            )
        );
    }
}
