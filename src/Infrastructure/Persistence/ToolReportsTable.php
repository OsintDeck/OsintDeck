<?php
/**
 * Tabla de reportes de usuarios sobre herramientas.
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Persistence;

/**
 * DDL para reportes (toggle, mensaje opcional, resolución admin).
 */
class ToolReportsTable {

    public const TABLE_NAME = 'osint_deck_tool_reports';

    /**
     * @return string Nombre con prefijo wp_.
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Crea o actualiza la tabla.
     */
    public static function create_table() {
        global $wpdb;
        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tool_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            fp_hash CHAR(32) NOT NULL DEFAULT '',
            message TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY idx_tool_status (tool_id, status),
            KEY idx_user_tool_open (user_id, tool_id, status),
            KEY idx_fp_tool_open (fp_hash, tool_id, status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
