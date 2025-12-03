<?php
/**
 * OSINT Deck - Rate limiting and abuse controls (frontend user).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OSD_Rate_Limit {
    const OPTION_QPM    = 'osd_rate_qpm';
    const OPTION_QPD    = 'osd_rate_qpd';
    const OPTION_REPORT = 'osd_rate_reports_day';
    const OPTION_COOLD  = 'osd_rate_cooldown';

    private static function ip_key( $ip, $fingerprint = '' ) {
        $base = trim( (string) ( $ip ?: '0.0.0.0' ) );
        $fp   = trim( (string) $fingerprint );
        return md5( $base . '|' . $fp );
    }

    private static function error( $code, $msg ) {
        return [
            'ok'   => false,
            'code' => $code,
            'msg'  => $msg,
        ];
    }

    /**
     * Checks if a given IP exceeds per-minute and per-day limits.
     *
     * @param string $ip
     * @param string $fingerprint
     * @return array { ok: bool, code?: string, msg?: string }
     */
    public static function check_queries( $ip, $fingerprint = '' ) {
        $ip_key   = self::ip_key( $ip, $fingerprint );
        $per_min  = max( 1, intval( get_option( self::OPTION_QPM, 60 ) ) );
        $per_day  = max( 0, intval( get_option( self::OPTION_QPD, 1000 ) ) );
        $cooldown = max( 5, intval( get_option( self::OPTION_COOLD, 60 ) ) );

        // Cooldown flag.
        $cool_key = "osd_cool_{$ip_key}";
        if ( get_transient( $cool_key ) ) {
            return self::error(
                'rate_limited',
                sprintf(
                    'Has alcanzado el limite de acciones. Espera %d segundos e intenta de nuevo.',
                    $cooldown
                )
            );
        }

        // Per-minute.
        $min_key = "osd_qpm_{$ip_key}";
        $min_cnt = intval( get_transient( $min_key ) );
        if ( $per_min > 0 && $min_cnt >= $per_min ) {
            set_transient( $cool_key, 1, max( 5, $cooldown ) );
            return self::error(
                'rate_limited',
                sprintf(
                    'Has alcanzado el limite de %d acciones por minuto. Espera e intenta de nuevo.',
                    $per_min
                )
            );
        }
        set_transient( $min_key, $min_cnt + 1, MINUTE_IN_SECONDS );

        // Per-day (optional, keeps legacy behavior).
        $day_key = "osd_qpd_{$ip_key}";
        $day_cnt = intval( get_transient( $day_key ) );
        if ( $per_day > 0 && $day_cnt >= $per_day ) {
            return self::error(
                'rate_limited',
                'Has alcanzado el limite diario de acciones.'
            );
        }
        set_transient( $day_key, $day_cnt + 1, DAY_IN_SECONDS );

        return [ 'ok' => true ];
    }

    /**
     * Checks if reporting is allowed (once per day per tool per IP).
     *
     * @param string $ip
     * @param string $tool_id
     * @param string $fingerprint
     * @return array { ok: bool, code?: string, msg?: string }
     */
    public static function check_report( $ip, $tool_id, $fingerprint = '' ) {
        $ip_key   = self::ip_key( $ip, $fingerprint );
        $tool_key = sanitize_key( $tool_id ?: 'tool' );
        $limit    = max( 1, intval( get_option( self::OPTION_REPORT, 1 ) ) );

        $key = "osd_report_{$tool_key}_{$ip_key}";
        $cnt = intval( get_transient( $key ) );
        if ( $limit > 0 && $cnt >= $limit ) {
            return self::error(
                'report_limit',
                'Solo puedes reportar esta herramienta una vez por dia.'
            );
        }
        set_transient( $key, $cnt + 1, DAY_IN_SECONDS );
        return [ 'ok' => true ];
    }
}
