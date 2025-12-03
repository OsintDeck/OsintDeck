<?php
/**
 * OSINT Deck - Metrics and badges.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OSD_Metrics {
    const OPTION_METRICS           = 'osd_tool_metrics';
    const OPTION_POPULAR_THRESHOLD = 'osd_metric_popular_threshold';
    const OPTION_NEW_DAYS          = 'osd_metric_new_days';
    const CRON_HOOK                = 'osd_metrics_daily';

    /**
     * Get stored metrics array.
     *
     * @return array
     */
    public static function all() {
        $raw = get_option( self::OPTION_METRICS, '[]' );
        if ( is_array( $raw ) ) {
            return $raw;
        }
        $data = json_decode( (string) $raw, true );
        return is_array( $data ) ? $data : [];
    }

    /**
     * Save metrics array.
     *
     * @param array $data
     */
    public static function save( array $data ) {
        update_option(
            self::OPTION_METRICS,
            wp_json_encode(
                $data,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        );
    }

    private static function bump_counter( array $counts, $days_to_keep = 30 ) {
        $day = current_time( 'Y-m-d' );
        $counts[ $day ] = isset( $counts[ $day ] ) ? intval( $counts[ $day ] ) + 1 : 1;

        // Cleanup old days.
        $cutoff = strtotime( "-{$days_to_keep} days" );
        foreach ( $counts as $k => $v ) {
            if ( strtotime( $k ) < $cutoff ) {
                unset( $counts[ $k ] );
            }
        }
        return $counts;
    }

    /**
     * Schedule a daily rebuild of metrics from logs.
     */
    public static function register_cron() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
        }
        add_action( self::CRON_HOOK, [ __CLASS__, 'rebuild_from_logs' ] );
    }

    /**
     * Rebuild metrics from logs (last 30 days).
     */
    public static function rebuild_from_logs() {
        global $wpdb;

        $table  = $wpdb->prefix . ( defined( 'OSD_LOG_TABLE' ) ? OSD_LOG_TABLE : 'osd_logs' );
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table
            )
        );
        if ( $exists !== $table ) {
            return;
        }

        $since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - DAY_IN_SECONDS * 30 );
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT action, tool, input_type, created_at
                 FROM {$table}
                 WHERE created_at >= %s
                   AND action IN ('USER-CLICK-TOOL','USER-REPORT-TOOL')",
                $since
            ),
            ARRAY_A
        );

        $base = self::all();
        $data = [];
        foreach ( $base as $tool_id => $row ) {
            $data[ $tool_id ] = [
                'created_at'      => isset( $row['created_at'] ) ? intval( $row['created_at'] ) : current_time( 'timestamp' ),
                'clicks'          => [],
                'reports'         => [],
                'last_input_type' => isset( $row['last_input_type'] ) ? $row['last_input_type'] : '',
            ];
        }
        foreach ( $rows as $row ) {
            $tool_id = sanitize_title( $row['tool'] ?? '' );
            if ( ! $tool_id ) {
                continue;
            }
            if ( ! isset( $data[ $tool_id ] ) ) {
                $data[ $tool_id ] = [
                    'created_at'      => current_time( 'timestamp' ),
                    'clicks'          => [],
                    'reports'         => [],
                    'last_input_type' => '',
                ];
            }
            $day_key = substr( $row['created_at'], 0, 10 );
            if ( $row['action'] === 'USER-CLICK-TOOL' ) {
                $data[ $tool_id ]['clicks'][ $day_key ] = intval( $data[ $tool_id ]['clicks'][ $day_key ] ?? 0 ) + 1;
                if ( ! empty( $row['input_type'] ) ) {
                    $data[ $tool_id ]['last_input_type'] = sanitize_text_field( $row['input_type'] );
                }
            } elseif ( $row['action'] === 'USER-REPORT-TOOL' ) {
                $data[ $tool_id ]['reports'][ $day_key ] = intval( $data[ $tool_id ]['reports'][ $day_key ] ?? 0 ) + 1;
            }
        }

        if ( ! empty( $data ) ) {
            self::save( $data );
        }
    }

    /**
     * Register a click for a tool.
     *
     * @param string $tool_id
     * @param string $input_type
     */
    public static function bump_click( $tool_id, $input_type = '' ) {
        if ( ! $tool_id ) {
            return;
        }
        $data = self::all();
        if ( ! isset( $data[ $tool_id ] ) || ! is_array( $data[ $tool_id ] ) ) {
            $data[ $tool_id ] = [
                'created_at' => current_time( 'timestamp' ),
                'clicks'     => [],
                'reports'    => [],
            ];
        }
        $data[ $tool_id ]['clicks'] = self::bump_counter( $data[ $tool_id ]['clicks'] ?? [] );
        $data[ $tool_id ]['last_input_type'] = sanitize_text_field( $input_type );
        self::save( $data );
    }

    /**
     * Register a report for a tool.
     *
     * @param string $tool_id
     */
    public static function bump_report( $tool_id ) {
        if ( ! $tool_id ) {
            return;
        }
        $data = self::all();
        if ( ! isset( $data[ $tool_id ] ) || ! is_array( $data[ $tool_id ] ) ) {
            $data[ $tool_id ] = [
                'created_at' => current_time( 'timestamp' ),
                'clicks'     => [],
                'reports'    => [],
            ];
        }
        $data[ $tool_id ]['reports'] = self::bump_counter( $data[ $tool_id ]['reports'] ?? [] );
        self::save( $data );
    }

    private static function sum_window( $counts, $days = 7 ) {
        if ( ! is_array( $counts ) ) {
            return 0;
        }
        $sum    = 0;
        $cutoff = strtotime( "-{$days} days" );
        foreach ( $counts as $k => $v ) {
            if ( strtotime( $k ) >= $cutoff ) {
                $sum += intval( $v );
            }
        }
        return $sum;
    }

    /**
     * Compute meta/badges for a tool.
     *
     * @param string $tool_id
     * @return array
     */
    public static function meta_for( $tool_id ) {
        $data     = self::all();
        $toolData = $data[ $tool_id ] ?? [];
        $created  = isset( $toolData['created_at'] ) ? intval( $toolData['created_at'] ) : current_time( 'timestamp' );

        $clicks7  = self::sum_window( $toolData['clicks'] ?? [] );
        $reports7 = self::sum_window( $toolData['reports'] ?? [] );

        $popular_threshold = max( 1, intval( get_option( self::OPTION_POPULAR_THRESHOLD, 100 ) ) );
        $new_days          = max( 1, intval( get_option( self::OPTION_NEW_DAYS, 30 ) ) );

        $badges = [];
        $is_new = ( current_time( 'timestamp' ) - $created ) <= DAY_IN_SECONDS * $new_days;
        if ( $is_new ) {
            $badges[] = 'Nueva';
        }
        if ( $clicks7 >= $popular_threshold ) {
            $badges[] = 'Popular';
        }
        if ( $reports7 > 0 ) {
            $badges[] = 'Reportada';
        }
        if ( ! empty( $toolData['last_input_type'] ) ) {
            $badges[] = 'Recomendada';
        }

        return [
            'badges'          => $badges,
            'is_new'          => $is_new,
            'reported'        => $reports7 > 0,
            'clicks_7d'       => $clicks7,
            'reports_7d'      => $reports7,
            'last_input_type' => isset( $toolData['last_input_type'] ) ? $toolData['last_input_type'] : '',
            'created_at'      => $created,
        ];
    }
}
