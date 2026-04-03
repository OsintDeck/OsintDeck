<?php
/**
 * Cloudflare Turnstile (verificación servidor).
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Security;

/**
 * Turnstile público (AJAX deck).
 */
class Turnstile {

    /**
     * ¿Hay site key + secret y está habilitado?
     */
    public static function is_enabled(): bool {
        if ( ! (bool) get_option( 'osint_deck_turnstile_enabled', false ) ) {
            return false;
        }
        return self::get_site_key() !== '' && self::get_secret_key() !== '';
    }

    public static function get_site_key(): string {
        return (string) get_option( 'osint_deck_turnstile_site_key', '' );
    }

    public static function get_secret_key(): string {
        return (string) get_option( 'osint_deck_turnstile_secret_key', '' );
    }

    /**
     * Token enviado por el cliente (nombre compatible con form POST).
     */
    public static function get_token_from_request(): string {
        $keys = array( 'cf_turnstile_response', 'cf-turnstile-response' );
        foreach ( $keys as $key ) {
            if ( ! empty( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ) {
                return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
            }
        }
        return '';
    }

    /**
     * @return true|\WP_Error
     */
    public static function verify_request() {
        if ( ! self::is_enabled() ) {
            return true;
        }

        $token = self::get_token_from_request();
        if ( $token === '' ) {
            return new \WP_Error(
                'turnstile_required',
                __( 'Se requiere verificación. Completá el captcha e intentá de nuevo.', 'osint-deck' )
            );
        }

        $secret = self::get_secret_key();
        $response = wp_remote_post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            array(
                'timeout' => 12,
                'body'    => array(
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => self::remote_ip(),
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new \WP_Error(
                'turnstile_verify_failed',
                __( 'No se pudo contactar el servicio de verificación. Probá más tarde.', 'osint-deck' )
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code !== 200 || ! is_array( $body ) || empty( $body['success'] ) ) {
            return new \WP_Error(
                'turnstile_invalid',
                __( 'La verificación no fue válida. Intentá de nuevo.', 'osint-deck' )
            );
        }

        return true;
    }

    private static function remote_ip(): string {
        if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
            return '';
        }
        return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
    }
}
