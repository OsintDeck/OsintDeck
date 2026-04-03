<?php
/**
 * Registro y consulta de historial por usuario.
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Persistence;

/**
 * Historial SSO.
 */
class UserHistory {

    /**
     * Inserta un evento.
     *
     * @param int         $user_id        ID usuario deck (osint_deck_sso_users).
     * @param string      $event_type     search|open_tool|like|favorite|report.
     * @param int|null    $tool_id        ID herramienta en tabla tools.
     * @param string|null $tool_name      Nombre legible.
     * @param string|null $query_snapshot Texto búsqueda (acotado).
     */
    public static function record( $user_id, $event_type, $tool_id = null, $tool_name = null, $query_snapshot = null ) {
        global $wpdb;
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            return false;
        }
        $table = UserHistoryTable::get_table_name();
        $type  = sanitize_key( $event_type );
        if ( '' === $type ) {
            return false;
        }
        $tid = null === $tool_id ? 0 : (int) $tool_id;
        if ( $tid < 0 ) {
            $tid = 0;
        }
        $tname = null === $tool_name ? null : sanitize_text_field( $tool_name );
        if ( $tname && strlen( $tname ) > 191 ) {
            $tname = substr( $tname, 0, 191 );
        }
        $q = null === $query_snapshot ? null : sanitize_text_field( $query_snapshot );
        if ( $q && strlen( $q ) > 2000 ) {
            $q = substr( $q, 0, 2000 );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return false !== $wpdb->insert(
            $table,
            array(
                'user_id'        => $user_id,
                'event_type'     => $type,
                'tool_id'        => $tid,
                'tool_name'      => null === $tool_name ? '' : (string) $tname,
                'query_snapshot' => null === $query_snapshot ? '' : (string) $q,
                'created_at'     => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%d', '%s', '%s', '%s' )
        );
    }

    /**
     * Lista últimos eventos del usuario.
     *
     * @param int $user_id ID usuario deck.
     * @param int $limit   Máximo filas.
     * @return array<int, array<string, mixed>>
     */
    public static function list_for_user( $user_id, $limit = 100 ) {
        global $wpdb;
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            return array();
        }
        $table = UserHistoryTable::get_table_name();
        $limit = max( 1, min( 500, (int) $limit ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, event_type, tool_id, tool_name, query_snapshot, created_at
                FROM {$table}
                WHERE user_id = %d
                ORDER BY id DESC
                LIMIT %d",
                $user_id,
                $limit
            ),
            ARRAY_A
        );

        return is_array( $rows ) ? $rows : array();
    }

    /**
     * Borra todo el historial del usuario.
     *
     * @param int $user_id ID usuario deck.
     * @return int Filas borradas.
     */
    public static function delete_for_user( $user_id ) {
        global $wpdb;
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            return 0;
        }
        $table = UserHistoryTable::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );
    }
}
