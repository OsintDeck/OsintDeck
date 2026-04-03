<?php
/**
 * Limpieza de datos de actividad del deck sin borrar herramientas ni usuarios WP.
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Persistence;

/**
 * Purga tablas de telemetría del plugin y favoritos en user meta (por opciones).
 */
class DeckActivityDataCleanup {

    /**
     * Ejecuta solo las purgas solicitadas.
     *
     * @param array{logs?: bool, history?: bool, favorites_meta?: bool} $what Claves en booleano.
     * @return array{
     *   logs_rows: int,
     *   history_rows: int,
     *   favorites_meta_rows: int,
     *   did_logs: bool,
     *   did_history: bool,
     *   did_favorites_meta: bool
     * }
     */
    public static function purge_activity_data( array $what ) {
        global $wpdb;

        $opts = wp_parse_args(
            $what,
            array(
                'logs'            => false,
                'history'         => false,
                'favorites_meta'  => false,
            )
        );

        $logs_rows          = 0;
        $history_rows       = 0;
        $fav_meta_rows      = 0;
        $did_logs           = false;
        $did_history        = false;
        $did_favorites_meta = false;

        if ( ! empty( $opts['logs'] ) && LogsTable::table_exists() ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $logs_rows = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . LogsTable::get_table_name() );
            LogsTable::truncate_all();
            $did_logs = true;
        }

        if ( ! empty( $opts['history'] ) ) {
            $hist_table = UserHistoryTable::get_table_name();
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hist_table ) ) === $hist_table ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $history_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$hist_table}`" );
                UserHistoryTable::truncate_all();
                $did_history = true;
            }
        }

        if ( ! empty( $opts['favorites_meta'] ) ) {
            $fav_meta_rows = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s",
                    UserFavorites::META_KEY
                )
            );
            delete_metadata( 'user', 0, UserFavorites::META_KEY, '', true );
            $did_favorites_meta = true;
        }

        return array(
            'logs_rows'           => $logs_rows,
            'history_rows'        => $history_rows,
            'favorites_meta_rows' => $fav_meta_rows,
            'did_logs'            => $did_logs,
            'did_history'         => $did_history,
            'did_favorites_meta'  => $did_favorites_meta,
        );
    }
}
