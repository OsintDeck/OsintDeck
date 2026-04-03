<?php
/**
 * Tabla de historial de acciones de usuarios logueados con SSO Deck.
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Persistence;

/**
 * DDL historial.
 */
class UserHistoryTable {

    public const TABLE_NAME = 'osint_deck_user_history';

    /**
     * Nombre con prefijo.
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Crea la tabla si no existe.
     */
    public static function create_table() {
        global $wpdb;
        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(32) NOT NULL,
            tool_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            tool_name VARCHAR(191) NULL,
            query_snapshot TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_created (user_id, created_at),
            KEY idx_user_id (user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Elimina todas las filas de historial.
     *
     * @return void
     */
    public static function truncate_all() {
        global $wpdb;

        $table = self::get_table_name();
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla interno.
        $wpdb->query( "TRUNCATE TABLE `{$table}`" );
    }
}
