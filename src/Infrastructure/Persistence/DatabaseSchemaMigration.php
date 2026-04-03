<?php
/**
 * Migración de esquema (tools/categorías) y creación de tablas SSO (sin copiar wp_users).
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Persistence;

/**
 * Versiones: v2 tablas tools/categorías; v3 usuarios SSO propios.
 */
class DatabaseSchemaMigration {

    public const SCHEMA_VERSION = 3;

    /**
     * Ejecuta migraciones pendientes (osint_deck_db_schema_version).
     *
     * @return void
     */
    public static function maybe_run() {
        $current = (int) get_option( 'osint_deck_db_schema_version', 0 );

        if ( $current < 2 ) {
            self::migrate_tools_table();
            self::migrate_categories_v2_duplicate();
            self::remove_obsolete_side_tables();
            update_option( 'osint_deck_db_schema_version', 2, false );
            $current = 2;
        }

        if ( $current < 3 ) {
            self::ensure_sso_tables();
            update_option( 'osint_deck_db_schema_version', 3, false );
        }
    }

    /**
     * Crea tablas SSO vacías (sin migrar datos de WordPress).
     *
     * @return void
     */
    private static function ensure_sso_tables() {
        DeckUsersTable::create_table();
        SsoToolFavoritesTable::create_table();
        SsoToolLikesTable::create_table();
        SsoReportThanksPendingTable::create_table();
    }

    /**
     * @param string $full_name Nombre completo incl. prefijo.
     */
    private static function table_exists( $full_name ) {
        global $wpdb;

        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_name ) ) === $full_name;
    }

    /**
     * @param string $base_name Sin prefijo (ej. osint_deck_tools_legacy_archive).
     */
    private static function unique_table_name( $base_name ) {
        global $wpdb;

        $candidate = $wpdb->prefix . $base_name;
        $n         = 0;
        while ( self::table_exists( $candidate ) ) {
            $n++;
            $candidate = $wpdb->prefix . $base_name . '_' . $n;
        }

        return $candidate;
    }

    /**
     * @return void
     */
    private static function migrate_tools_table() {
        global $wpdb;

        $final = $wpdb->prefix . 'osint_deck_tools';
        $v2    = $wpdb->prefix . 'osint_deck_tools_v2';

        if ( ! self::table_exists( $v2 ) ) {
            return;
        }

        if ( ! self::table_exists( $final ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "RENAME TABLE `{$v2}` TO `{$final}`" );
            return;
        }

        $backup = self::unique_table_name( 'osint_deck_tools_legacy_archive' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "RENAME TABLE `{$final}` TO `{$backup}`" );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "RENAME TABLE `{$v2}` TO `{$final}`" );
    }

    /**
     * @return void
     */
    private static function migrate_categories_v2_duplicate() {
        global $wpdb;

        $final = $wpdb->prefix . 'osint_deck_categories';
        $v2    = $wpdb->prefix . 'osint_deck_categories_v2';

        if ( ! self::table_exists( $v2 ) ) {
            return;
        }

        if ( self::table_exists( $final ) ) {
            $backup = self::unique_table_name( 'osint_deck_categories_v2_archive' );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "RENAME TABLE `{$v2}` TO `{$backup}`" );
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "RENAME TABLE `{$v2}` TO `{$final}`" );
    }

    /**
     * @return void
     */
    private static function remove_obsolete_side_tables() {
        global $wpdb;

        $obsolete = array(
            $wpdb->prefix . 'osint_deck_favorites_v2',
            $wpdb->prefix . 'osint_deck_interactions_v2',
        );

        foreach ( $obsolete as $t ) {
            if ( ! self::table_exists( $t ) ) {
                continue;
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$t}`" );

            if ( $count > 0 ) {
                $backup = self::unique_table_name( str_replace( $wpdb->prefix, '', $t ) . '_rows_backup' );
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query( "RENAME TABLE `{$t}` TO `{$backup}`" );
                continue;
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "DROP TABLE `{$t}`" );
        }
    }
}
