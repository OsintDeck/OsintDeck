<?php
/**
 * Sesión de usuario OSINT Deck vía cookie firmada (Google SSO en front).
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Auth;

/**
 * Cookie osd_user: deck_user_id.signature (usuario en tabla osint_deck_sso_users, no wp_users).
 */
class OsintUserSession {

    public const COOKIE_NAME = 'osd_user';

    /**
     * ID en osint_deck_sso_users o 0.
     */
    public static function get_user_id() {
        if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            return 0;
        }
        $parts = explode( '.', sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ) );
        if ( count( $parts ) !== 2 ) {
            return 0;
        }
        $user_id = (int) $parts[0];
        $sig     = $parts[1];
        if ( $user_id <= 0 ) {
            return 0;
        }
        $expected = hash_hmac( 'sha256', (string) $user_id, wp_salt( 'auth' ) );
        if ( ! hash_equals( $expected, $sig ) ) {
            return 0;
        }
        return $user_id;
    }

    /**
     * Establece cookie httpOnly ~7 días.
     *
     * @param int $user_id ID fila en osint_deck_sso_users.
     */
    public static function set_cookie( $user_id ) {
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            return;
        }
        $sig   = hash_hmac( 'sha256', (string) $user_id, wp_salt( 'auth' ) );
        $value = $user_id . '.' . $sig;
        $expire = time() + 7 * DAY_IN_SECONDS;
        setcookie( self::COOKIE_NAME, $value, $expire, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
    }

    /**
     * Borra la cookie de sesión del deck.
     */
    public static function clear_cookie() {
        setcookie( self::COOKIE_NAME, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
    }
}
