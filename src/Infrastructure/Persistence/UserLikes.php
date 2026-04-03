<?php
/**
 * Me gusta por usuario SSO (tabla propia; no wp_users).
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Persistence;

/**
 * IDs con like por deck_user_id.
 */
class UserLikes {

    const META_KEY = 'osint_deck_liked_tool_ids';

    /**
     * @param int $deck_user_id ID deck.
     * @return int[]
     */
    public static function get_tool_ids( $deck_user_id ) {
        global $wpdb;
        $deck_user_id = (int) $deck_user_id;
        if ( $deck_user_id <= 0 ) {
            return array();
        }
        $table = SsoToolLikesTable::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT tool_id FROM {$table} WHERE deck_user_id = %d ORDER BY tool_id ASC",
                $deck_user_id
            )
        );

        if ( ! is_array( $rows ) ) {
            return array();
        }
        $out = array();
        foreach ( $rows as $id ) {
            $id = (int) $id;
            if ( $id > 0 ) {
                $out[] = $id;
            }
        }
        return array_values( array_unique( $out ) );
    }

    /**
     * @param int $deck_user_id ID deck.
     * @param int $tool_id      Tool DB id.
     */
    public static function is_liked( $deck_user_id, $tool_id ) {
        $tool_id = (int) $tool_id;
        if ( $tool_id <= 0 ) {
            return false;
        }
        return in_array( $tool_id, self::get_tool_ids( $deck_user_id ), true );
    }

    /**
     * @param int $deck_user_id ID deck.
     * @param int $tool_id      Tool DB id.
     * @return bool True si se añadió.
     */
    public static function add( $deck_user_id, $tool_id ) {
        global $wpdb;
        $deck_user_id = (int) $deck_user_id;
        $tool_id      = (int) $tool_id;
        if ( $deck_user_id <= 0 || $tool_id <= 0 ) {
            return false;
        }
        if ( self::is_liked( $deck_user_id, $tool_id ) ) {
            return false;
        }
        $table = SsoToolLikesTable::get_table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return false !== $wpdb->insert(
            $table,
            array(
                'deck_user_id' => $deck_user_id,
                'tool_id'      => $tool_id,
            ),
            array( '%d', '%d' )
        );
    }

    /**
     * @param int $deck_user_id ID deck.
     * @param int $tool_id      Tool DB id.
     * @return bool True si se quitó.
     */
    public static function remove( $deck_user_id, $tool_id ) {
        global $wpdb;
        $deck_user_id = (int) $deck_user_id;
        $tool_id      = (int) $tool_id;
        if ( $deck_user_id <= 0 || $tool_id <= 0 ) {
            return false;
        }
        $table = SsoToolLikesTable::get_table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return (bool) $wpdb->delete(
            $table,
            array(
                'deck_user_id' => $deck_user_id,
                'tool_id'      => $tool_id,
            ),
            array( '%d', '%d' )
        );
    }
}
