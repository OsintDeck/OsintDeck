<?php
/**
 * Usuarios Google SSO del deck (sin wp_users).
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Persistence;

/**
 * Alta, lectura y baja de usuarios deck.
 */
class DeckUsers {

    /**
     * Crea o actualiza usuario a partir del token Google (sub obligatorio).
     *
     * @param string $google_sub Subject del id_token.
     * @param string $email      Email verificado.
     * @param string $name       Nombre para mostrar.
     * @param string $picture    URL avatar o ''.
     * @return int               id en osint_deck_sso_users.
     */
    public static function upsert_from_google( $google_sub, $email, $name, $picture ) {
        global $wpdb;
        $google_sub = is_string( $google_sub ) ? sanitize_text_field( $google_sub ) : '';
        $email      = sanitize_email( $email );
        $name       = is_string( $name ) ? sanitize_text_field( $name ) : '';
        $picture    = is_string( $picture ) ? esc_url_raw( $picture ) : '';

        if ( '' === $google_sub || ! is_email( $email ) ) {
            return 0;
        }

        $table = DeckUsersTable::get_table_name();
        $now   = current_time( 'mysql' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE google_sub = %s LIMIT 1",
                $google_sub
            ),
            ARRAY_A
        );

        if ( is_array( $row ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->update(
                $table,
                array(
                    'user_email'   => $email,
                    'display_name' => $name ? $name : $row['display_name'],
                    'avatar_url'   => $picture !== '' ? $picture : $row['avatar_url'],
                    'updated_at'   => $now,
                ),
                array( 'id' => (int) $row['id'] ),
                array( '%s', '%s', '%s', '%s' ),
                array( '%d' )
            );
            return (int) $row['id'];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $table,
            array(
                'google_sub'   => $google_sub,
                'user_email'   => $email,
                'display_name' => $name,
                'avatar_url'   => $picture,
                'created_at'   => $now,
                'updated_at'   => $now,
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * @param int $deck_user_id ID tabla deck.
     * @return array<string, mixed>|null
     */
    public static function get_by_id( $deck_user_id ) {
        global $wpdb;
        $deck_user_id = (int) $deck_user_id;
        if ( $deck_user_id <= 0 ) {
            return null;
        }
        $table = DeckUsersTable::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $deck_user_id ),
            ARRAY_A
        );

        return is_array( $row ) ? $row : null;
    }

    /**
     * Elimina usuario deck y datos asociados en tablas del plugin (no toca wp_users).
     *
     * @param int $deck_user_id ID.
     * @return void
     */
    public static function delete_cascade( $deck_user_id ) {
        global $wpdb;
        $deck_user_id = (int) $deck_user_id;
        if ( $deck_user_id <= 0 ) {
            return;
        }

        $fav = SsoToolFavoritesTable::get_table_name();
        $lik = SsoToolLikesTable::get_table_name();
        $thx = SsoReportThanksPendingTable::get_table_name();
        $rep = ToolReportsTable::get_table_name();
        $usr = DeckUsersTable::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->delete( $fav, array( 'deck_user_id' => $deck_user_id ), array( '%d' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->delete( $lik, array( 'deck_user_id' => $deck_user_id ), array( '%d' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->delete( $thx, array( 'deck_user_id' => $deck_user_id ), array( '%d' ) );

        UserHistory::delete_for_user( $deck_user_id );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->delete( $rep, array( 'user_id' => $deck_user_id ), array( '%d' ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->delete( $usr, array( 'id' => $deck_user_id ), array( '%d' ) );
    }
}
