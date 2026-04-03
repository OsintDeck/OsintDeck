<?php
/**
 * Cola de “gracias” cuando el admin marca una herramienta como reparada.
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Persistence;

/**
 * Tabla para usuarios SSO logueados; transient por fp_hash para anónimos.
 */
class ReportThanks {

    const USER_META_KEY = 'osint_deck_pending_report_thanks';

    const TRANSIENT_PREFIX = 'osd_rep_thx_';

    const TRANSIENT_TTL = 31536000;

    /**
     * @param int    $deck_user_id 0 = solo fp.
     * @param string $fp_hash      md5(fp).
     * @param int    $tool_id      Tool id.
     */
    public static function enqueue( $deck_user_id, $fp_hash, $tool_id ) {
        $tool_id = (int) $tool_id;
        if ( $tool_id <= 0 ) {
            return;
        }
        $deck_user_id = (int) $deck_user_id;
        $fp_hash      = is_string( $fp_hash ) ? substr( preg_replace( '/[^a-f0-9]/', '', strtolower( $fp_hash ) ), 0, 32 ) : '';

        if ( $deck_user_id > 0 ) {
            global $wpdb;
            $table = SsoReportThanksPendingTable::get_table_name();
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->replace(
                $table,
                array(
                    'deck_user_id' => $deck_user_id,
                    'tool_id'      => $tool_id,
                ),
                array( '%d', '%d' )
            );
            return;
        }

        if ( $fp_hash === '' ) {
            return;
        }

        $key  = self::TRANSIENT_PREFIX . $fp_hash;
        $list = get_transient( $key );
        $list = is_array( $list ) ? array_map( 'intval', $list ) : array();
        if ( ! in_array( $tool_id, $list, true ) ) {
            $list[] = $tool_id;
            set_transient( $key, $list, self::TRANSIENT_TTL );
        }
    }

    /**
     * @param int $deck_user_id ID usuario deck.
     * @return int[]
     */
    public static function get_pending_for_user( $deck_user_id ) {
        global $wpdb;
        $deck_user_id = (int) $deck_user_id;
        if ( $deck_user_id <= 0 ) {
            return array();
        }
        $table = SsoReportThanksPendingTable::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT tool_id FROM {$table} WHERE deck_user_id = %d ORDER BY tool_id ASC",
                $deck_user_id
            )
        );

        return is_array( $rows ) ? array_values( array_unique( array_map( 'intval', $rows ) ) ) : array();
    }

    /**
     * @param string $fp_hash  md5(fp).
     * @return int[]
     */
    public static function get_pending_for_fp_hash( $fp_hash ) {
        $fp_hash = is_string( $fp_hash ) ? substr( preg_replace( '/[^a-f0-9]/', '', strtolower( $fp_hash ) ), 0, 32 ) : '';
        if ( $fp_hash === '' ) {
            return array();
        }
        $list = get_transient( self::TRANSIENT_PREFIX . $fp_hash );
        return is_array( $list ) ? array_values( array_unique( array_map( 'intval', $list ) ) ) : array();
    }

    /**
     * @param int    $deck_user_id Usuario deck o 0.
     * @param string $fp_hash      Para anónimo.
     * @param int[]  $tool_ids     Ids a quitar.
     */
    public static function dismiss( $deck_user_id, $fp_hash, array $tool_ids ) {
        $tool_ids = array_values(
            array_unique(
                array_filter(
                    array_map( 'intval', $tool_ids ),
                    static function ( $id ) {
                        return $id > 0;
                    }
                )
            )
        );
        if ( array() === $tool_ids ) {
            return;
        }
        $deck_user_id = (int) $deck_user_id;
        $fp_hash      = is_string( $fp_hash ) ? substr( preg_replace( '/[^a-f0-9]/', '', strtolower( $fp_hash ) ), 0, 32 ) : '';

        if ( $deck_user_id > 0 ) {
            global $wpdb;
            $table = SsoReportThanksPendingTable::get_table_name();
            foreach ( $tool_ids as $tid ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->delete(
                    $table,
                    array(
                        'deck_user_id' => $deck_user_id,
                        'tool_id'      => (int) $tid,
                    ),
                    array( '%d', '%d' )
                );
            }
            return;
        }

        if ( $fp_hash === '' ) {
            return;
        }

        $key     = self::TRANSIENT_PREFIX . $fp_hash;
        $pending = self::get_pending_for_fp_hash( $fp_hash );
        $pending = array_values( array_diff( $pending, $tool_ids ) );
        if ( array() === $pending ) {
            delete_transient( $key );
        } else {
            set_transient( $key, $pending, self::TRANSIENT_TTL );
        }
    }
}
