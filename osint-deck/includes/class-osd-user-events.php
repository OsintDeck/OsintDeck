<?php
/**
 * OSINT Deck - Frontend user events (AJAX).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OSD_User_Events {
    const NONCE_ACTION = 'osd_user_event';

    public static function register() {
        add_action( 'wp_ajax_nopriv_osd_user_event', [ __CLASS__, 'handle' ] );
        add_action( 'wp_ajax_osd_user_event', [ __CLASS__, 'handle' ] );
        add_action( 'wp_ajax_nopriv_osd_check_tld', [ __CLASS__, 'check_tld' ] );
        add_action( 'wp_ajax_osd_check_tld', [ __CLASS__, 'check_tld' ] );
    }

    private static function respond_error( $code, $msg, $status = 400 ) {
        wp_send_json(
            [
                'ok'   => false,
                'code' => $code,
                'msg'  => $msg,
            ],
            $status
        );
    }

    public static function handle() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), self::NONCE_ACTION ) ) {
            self::respond_error( 'invalid_nonce', 'Nonce invalido', 403 );
        }

        $event      = sanitize_text_field( wp_unslash( $_POST['event'] ?? '' ) );
        $tool_id    = sanitize_text_field( wp_unslash( $_POST['tool_id'] ?? '' ) );
        $input_type = sanitize_text_field( wp_unslash( $_POST['input_type'] ?? '' ) );
        $input_val  = sanitize_textarea_field( wp_unslash( $_POST['input_value'] ?? '' ) );
        $ip         = function_exists( 'osd_get_ip' ) ? osd_get_ip() : '0.0.0.0';
        $fp         = isset( $_POST['fp'] ) ? sanitize_text_field( wp_unslash( $_POST['fp'] ) ) : '';

        $rate = OSD_Rate_Limit::check_queries( $ip, $fp );
        if ( ! $rate['ok'] ) {
            self::respond_error(
                $rate['code'] ?? 'rate_limited',
                $rate['msg'] ?? 'Has alcanzado el limite de uso. Intenta de nuevo pronto.',
                429
            );
        }

        switch ( $event ) {
            case 'report_tool':
                $rep = OSD_Rate_Limit::check_report( $ip, $tool_id, $fp );
                if ( ! $rep['ok'] ) {
                    self::respond_error(
                        $rep['code'] ?? 'report_limit',
                        $rep['msg'] ?? 'Solo puedes reportar esta herramienta una vez por dia.',
                        429
                    );
                }
                OSD_Metrics::bump_report( $tool_id );
                if ( function_exists( 'osd_log_user' ) ) {
                    osd_log_user( 'USER-REPORT-TOOL', $tool_id, $input_type, $input_val, [] );
                }
                break;

            case 'click_tool':
                OSD_Metrics::bump_click( $tool_id, $input_type );
                if ( function_exists( 'osd_log_user' ) ) {
                    osd_log_user( 'USER-CLICK-TOOL', $tool_id, $input_type, $input_val, [] );
                }
                break;

            default:
                self::respond_error( 'invalid_event', 'Evento no valido', 400 );
        }

        $meta = OSD_Metrics::meta_for( $tool_id );

        wp_send_json(
            [
                'ok'   => true,
                'meta' => $meta,
            ]
        );
    }

    /**
     * AJAX: validar TLD de un dominio (offline, sin API).
     */
    public static function check_tld() {
        $domain = sanitize_text_field( wp_unslash( $_REQUEST['domain'] ?? '' ) );
        $ok     = false;
        $suggest = '';
        if ( class_exists( 'OSD_TLD' ) && $domain ) {
            $ok = OSD_TLD::is_valid_domain( $domain );
            if ( ! $ok && method_exists( 'OSD_TLD', 'suggest_domain' ) ) {
                $suggest = OSD_TLD::suggest_domain( $domain );
            }
        }
        wp_send_json_success(
            [
                'valid'      => $ok,
                'suggestion' => $suggest,
            ]
        );
    }
}
