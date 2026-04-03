<?php
/**
 * Cola “gracias” por usuario SSO (sustituye user_meta).
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Persistence;

/**
 * DDL pending thanks SSO.
 */
class SsoReportThanksPendingTable {

    public const TABLE_NAME = 'osint_deck_sso_report_thanks_pending';

    /**
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Crea la tabla.
     */
    public static function create_table() {
        global $wpdb;
        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            deck_user_id BIGINT UNSIGNED NOT NULL,
            tool_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (deck_user_id, tool_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
