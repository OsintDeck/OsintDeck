<?php
// Bloquear acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ============================================================
 * OSINT Deck — Logs unificados (admin + usuario)
 * Tabla: wp_osd_logs
 * ============================================================
 *
 *   id           BIGINT, PK
 *   created_at   DATETIME
 *   actor_type   'admin' | 'user' | 'system'
 *   actor_id     BIGINT (ID de usuario WP) o 0
 *   ip           VARCHAR(64)
 *   action       VARCHAR(64)
 *   tool         VARCHAR(191)
 *   input_type   VARCHAR(64)
 *   input_value  TEXT
 *   meta         LONGTEXT (JSON)
 */

if ( ! defined( 'OSD_LOG_TABLE' ) ) {
    define( 'OSD_LOG_TABLE', 'osd_logs' );
}

/**
 * ============================================================
 * Obtener IP del usuario
 * ============================================================
 */
function osd_get_ip() {
    foreach ( [ 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $key ) {
        if ( ! empty( $_SERVER[ $key ] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
            $ip = explode( ',', $ip )[0];
            return trim( $ip );
        }
    }
    return '0.0.0.0';
}

/**
 * ============================================================
 * Crear / actualizar tabla de logs (activación del plugin)
 * ============================================================
 */
function osd_logs_install() {
    global $wpdb;

    $table_name      = $wpdb->prefix . OSD_LOG_TABLE;
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL,
        actor_type VARCHAR(20) NOT NULL DEFAULT 'system',
        actor_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        ip VARCHAR(64) NOT NULL DEFAULT '',
        action VARCHAR(64) NOT NULL DEFAULT '',
        tool VARCHAR(191) NOT NULL DEFAULT '',
        input_type VARCHAR(64) NOT NULL DEFAULT '',
        input_value TEXT NULL,
        meta LONGTEXT NULL,
        PRIMARY KEY  (id),
        KEY idx_action (action),
        KEY idx_tool (tool),
        KEY idx_actor (actor_type, actor_id),
        KEY idx_created (created_at)
    ) {$charset_collate};";

    dbDelta( $sql );
}

/**
 * ============================================================
 * Opcional: eliminar tabla de logs en uninstall
 * ============================================================
 */
function osd_logs_uninstall() {
    global $wpdb;
    $table_name = $wpdb->prefix . OSD_LOG_TABLE;
    $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
}

/**
 * ============================================================
 * Lista blanca de eventos permitidos
 * ============================================================
 */
function osd_logs_allowed_events() {
    return [
        // Usuario (frontend)
        'USER-SEARCH',
        'USER-CLICK-TOOL',
        'USER-REPORT-TOOL',
        'USER-COPY-URL',
        'USER-OPEN-CARD',

        // Admin (panel)
        'ADMIN-SAVE-JSON',
        'ADMIN-SAVE-THEME',
        'ADMIN-IMPORT-JSON',
        'ADMIN-EXPORT-JSON',
        'ADMIN-CREATE-TOOL',
        'ADMIN-UPDATE-TOOL',
        'ADMIN-DELETE-TOOL',
    ];
}

/**
 * ============================================================
 * Rate limit (anti-flood) — 3 logs por segundo por IP
 * ============================================================
 */
function osd_logs_rate_limit_check() {
    $ip  = osd_get_ip();
    $key = 'osd_rate_' . md5( $ip );

    $count = intval( get_transient( $key ) );

    if ( $count > 3 ) {
        return false;
    }

    set_transient( $key, $count + 1, 1 ); // expira en 1 segundo
    return true;
}

/**
 * ============================================================
 * Función general de log
 * ============================================================
 */
function osd_log_event( array $args ) {
    global $wpdb;

    if ( empty( $args['action'] ) ) {
        return;
    }

    $allowed = osd_logs_allowed_events();
    if ( ! in_array( $args['action'], $allowed, true ) ) {
        return; // ignorar eventos no permitidos
    }

    $table_name = $wpdb->prefix . OSD_LOG_TABLE;

    $defaults = [
        'action'      => '',
        'actor_type'  => 'system',
        'actor_id'    => 0,
        'tool'        => '',
        'input_type'  => '',
        'input_value' => '',
        'meta'        => [],
    ];

    $data = array_merge( $defaults, $args );

    $action     = sanitize_text_field( $data['action'] );
    $actor_type = sanitize_text_field( $data['actor_type'] );
    $actor_id   = intval( $data['actor_id'] );
    $tool       = sanitize_text_field( $data['tool'] );
    $input_type = sanitize_text_field( $data['input_type'] );
    $input_val  = is_string( $data['input_value'] ) ? $data['input_value'] : '';
    $ip         = osd_get_ip();

    $meta_json = '';
    if ( ! empty( $data['meta'] ) && is_array( $data['meta'] ) ) {
        $meta_json = wp_json_encode(
            $data['meta'],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    $wpdb->insert(
        $table_name,
        [
            'created_at'  => current_time( 'mysql' ),
            'actor_type'  => $actor_type,
            'actor_id'    => $actor_id,
            'ip'          => $ip,
            'action'      => $action,
            'tool'        => $tool,
            'input_type'  => $input_type,
            'input_value' => $input_val,
            'meta'        => $meta_json,
        ],
        [ '%s','%s','%d','%s','%s','%s','%s','%s','%s' ]
    );
}

/**
 * ============================================================
 * Helpers para admin
 * ============================================================
 */
function osd_log_admin( string $action, string $tool = '', array $meta = [] ) {
    $user_id = get_current_user_id();

    osd_log_event([
        'action'      => $action,
        'actor_type'  => 'admin',
        'actor_id'    => $user_id ?: 0,
        'tool'        => $tool,
        'input_type'  => '',
        'input_value' => '',
        'meta'        => $meta,
    ]);
}

/**
 * ============================================================
 * Helpers para usuario (frontend)
 * ============================================================
 */
function osd_log_user(
    string $action,
    string $tool         = '',
    string $input_type   = '',
    string $input_value  = '',
    array  $meta         = []
) {
    $user_id = get_current_user_id();

    osd_log_event([
        'action'      => $action,
        'actor_type'  => 'user',
        'actor_id'    => $user_id ?: 0,
        'tool'        => $tool,
        'input_type'  => $input_type,
        'input_value' => $input_value,
        'meta'        => $meta,
    ]);
}

add_action( 'wp_ajax_nopriv_osd_log_user_event', 'osd_ajax_log_user_event' );
add_action( 'wp_ajax_osd_log_user_event',        'osd_ajax_log_user_event' );

function osd_ajax_log_user_event() {

    if ( ! osd_logs_rate_limit_check() ) {
        wp_send_json_error( [ 'msg' => 'Too many events' ], 429 );
    }

    $event      = sanitize_text_field( wp_unslash( $_POST['event'] ?? '' ) );
    $tool       = sanitize_text_field( wp_unslash( $_POST['tool'] ?? '' ) );
    $input_type = sanitize_text_field( wp_unslash( $_POST['input_type'] ?? '' ) );
    $input_val  = sanitize_textarea_field( wp_unslash( $_POST['input_value'] ?? '' ) );
    $meta_raw   = wp_unslash( $_POST['meta'] ?? '' );

    if ( $event === '' ) {
        wp_send_json_error( [ 'msg' => 'Missing event' ], 400 );
    }

    $allowed = osd_logs_allowed_events();
    if ( ! in_array( $event, $allowed, true ) ) {
        wp_send_json_error( [ 'msg' => 'Invalid event' ], 400 );
    }

    $meta = [];
    if ( $meta_raw ) {
        $tmp = json_decode( $meta_raw, true );
        if ( is_array( $tmp ) ) {
            $meta = $tmp;
        }
    }

    osd_log_user( $event, $tool, $input_type, $input_val, $meta );

    wp_send_json_success( [ 'ok' => true ] );
}
