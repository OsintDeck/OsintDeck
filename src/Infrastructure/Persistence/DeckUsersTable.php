<?php
/**
 * Tabla de usuarios SSO del deck (aislada de wp_users).
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Persistence;

/**
 * DDL usuarios deck.
 */
class DeckUsersTable {

    public const TABLE_NAME = 'osint_deck_sso_users';

    /**
     * @return string Con prefijo wp_.
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
            google_sub VARCHAR(191) NOT NULL,
            user_email VARCHAR(191) NOT NULL,
            display_name VARCHAR(191) NOT NULL DEFAULT '',
            avatar_url TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_google_sub (google_sub),
            KEY idx_email (user_email(191))
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
