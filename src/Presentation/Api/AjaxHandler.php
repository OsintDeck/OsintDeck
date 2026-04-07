<?php
/**
 * AJAX Handler - Handles all AJAX requests
 *
 * @package OsintDeck
 */

namespace OsintDeck\Presentation\Api;

use OsintDeck\Domain\Service\DecisionEngine;
use OsintDeck\Domain\Repository\ToolRepositoryInterface;
use OsintDeck\Infrastructure\Auth\OsintUserSession;
use OsintDeck\Infrastructure\Persistence\UserHistory;
use OsintDeck\Infrastructure\Persistence\UserFavorites;
use OsintDeck\Infrastructure\Persistence\UserLikes;
use OsintDeck\Infrastructure\Persistence\ToolReports;
use OsintDeck\Infrastructure\Persistence\ReportThanks;
use OsintDeck\Infrastructure\Persistence\DeckUsers;
use OsintDeck\Infrastructure\Security\Turnstile;
use OsintDeck\Infrastructure\Service\IconFetchFailureStore;
use OsintDeck\Infrastructure\Service\IconManager;

/**
 * Class AjaxHandler
 * 
 * Handles AJAX requests for search and interactions
 */
class AjaxHandler {

    /**
     * Decision Engine instance
     *
     * @var DecisionEngine
     */
    private $decision_engine;

    /**
     * Tool Repository instance
     *
     * @var ToolRepositoryInterface
     */
    private $tool_repository;

    /**
     * Constructor
     * 
     * @param ToolRepositoryInterface $tool_repository
     * @param DecisionEngine $decision_engine
     */
    public function __construct( ToolRepositoryInterface $tool_repository, DecisionEngine $decision_engine ) {
        $this->tool_repository = $tool_repository;
        $this->decision_engine = $decision_engine;
    }

    /**
     * Cloudflare Turnstile (si está configurado).
     *
     * @return void
     */
    private function verify_turnstile_or_exit() {
        $result = Turnstile::verify_request();
        if ( is_wp_error( $result ) ) {
            wp_send_json_error(
                array(
                    'message' => $result->get_error_message(),
                    'code'    => $result->get_error_code(),
                )
            );
        }
    }

    /**
     * Initialize AJAX hooks
     *
     * @return void
     */
    public function init() {
        // Public AJAX actions
        add_action( 'wp_ajax_osint_deck_search', array( $this, 'handle_search' ) );
        add_action( 'wp_ajax_nopriv_osint_deck_search', array( $this, 'handle_search' ) );

        add_action( 'wp_ajax_osint_deck_track_click', array( $this, 'handle_track_click' ) );
        add_action( 'wp_ajax_nopriv_osint_deck_track_click', array( $this, 'handle_track_click' ) );

        add_action( 'wp_ajax_osint_deck_check_iframe', array( $this, 'handle_check_iframe' ) );
        add_action( 'wp_ajax_nopriv_osint_deck_check_iframe', array( $this, 'handle_check_iframe' ) );

        add_action( 'wp_ajax_osint_deck_report_block', array( $this, 'handle_report_block' ) );
        add_action( 'wp_ajax_nopriv_osint_deck_report_block', array( $this, 'handle_report_block' ) );

        // Admin AJAX actions
        add_action( 'wp_ajax_osint_deck_force_download_icons', array( $this, 'handle_force_download_icons' ) );
        add_action( 'wp_ajax_osint_deck_list_remote_icons', array( $this, 'handle_list_remote_icons' ) );
        add_action( 'wp_ajax_osint_deck_save_manual_icons', array( $this, 'handle_save_manual_icons' ) );
        add_action( 'wp_ajax_osint_deck_import_tool', array( $this, 'handle_import_tool' ) );
        add_action( 'wp_ajax_osint_deck_export_tool', array( $this, 'handle_export_tool' ) );
        add_action( 'wp_ajax_osint_deck_metrics_tool_suggest', array( $this, 'handle_metrics_tool_suggest' ) );

        add_action( 'wp_ajax_osint_deck_auth_google', array( $this, 'handle_auth_google' ) );
        add_action( 'wp_ajax_nopriv_osint_deck_auth_google', array( $this, 'handle_auth_google' ) );
        add_action( 'wp_ajax_osint_deck_get_user', array( $this, 'handle_get_user' ) );
        add_action( 'wp_ajax_nopriv_osint_deck_get_user', array( $this, 'handle_get_user' ) );
        add_action( 'wp_ajax_osint_deck_logout', array( $this, 'handle_logout' ) );
        add_action( 'wp_ajax_nopriv_osint_deck_logout', array( $this, 'handle_logout' ) );

        add_action( 'wp_ajax_osint_deck_get_history', array( $this, 'handle_get_history' ) );
        add_action( 'wp_ajax_nopriv_osint_deck_get_history', array( $this, 'handle_get_history' ) );
        add_action( 'wp_ajax_osint_deck_clear_history', array( $this, 'handle_clear_history' ) );
        add_action( 'wp_ajax_nopriv_osint_deck_clear_history', array( $this, 'handle_clear_history' ) );
        add_action( 'wp_ajax_osint_deck_delete_my_account', array( $this, 'handle_delete_my_account' ) );
        add_action( 'wp_ajax_nopriv_osint_deck_delete_my_account', array( $this, 'handle_delete_my_account' ) );

        add_action( 'wp_ajax_osint_deck_clear_favorites', array( $this, 'handle_clear_favorites' ) );
        add_action( 'wp_ajax_nopriv_osint_deck_clear_favorites', array( $this, 'handle_clear_favorites' ) );

        add_action( 'wp_ajax_osint_deck_report_state', array( $this, 'handle_report_state' ) );
        add_action( 'wp_ajax_nopriv_osint_deck_report_state', array( $this, 'handle_report_state' ) );
        add_action( 'wp_ajax_osint_deck_dismiss_report_thanks', array( $this, 'handle_dismiss_report_thanks' ) );
        add_action( 'wp_ajax_nopriv_osint_deck_dismiss_report_thanks', array( $this, 'handle_dismiss_report_thanks' ) );
    }

    /**
     * Handle iframe check AJAX request
     * 
     * @return void
     */
    public function handle_check_iframe() {
        check_ajax_referer( 'osint_deck_public', 'nonce' );

        $this->verify_turnstile_or_exit();

        $urls = isset( $_POST['urls'] ) ? json_decode( stripslashes( $_POST['urls'] ), true ) : array();
        
        if ( empty( $urls ) || ! is_array( $urls ) ) {
            wp_send_json_error( array( 'message' => 'No URLs provided' ) );
        }

        // Limit to 20 URLs to prevent abuse
        $urls = array_slice( $urls, 0, 20 );
        $results = array();

        foreach ( $urls as $url_raw ) {
            if ( ! is_string( $url_raw ) ) {
                continue;
            }
            $url_key   = $url_raw;
            $url       = esc_url_raw( trim( $url_raw ) );
            $validated = ( $url !== '' && wp_http_validate_url( $url ) );

            if ( ! $validated ) {
                $results[ $url_key ] = false;
                continue;
            }

            // Check cache first (v2 to flush old cache)
            $cache_key = 'osd_iframe_v2_' . md5( $url );
            $cached = get_transient( $cache_key );
            
            if ( $cached !== false ) {
                $results[ $url_key ] = (bool) $cached;
                continue;
            }

            // wp_safe_remote_* bloquea localhost y RFC1918; eso rompe preview en intranet.
            // Usamos wp_remote_*: solo http(s) válido (arriba), nonce y Turnstile reducen abuso.
            // sslverify por defecto false (TLS roto en red interna / certs propios).
            // Endurecer TLS: add_filter( 'osint_deck_iframe_check_sslverify', '__return_true' );
            $args = array(
                'timeout'     => 5, // Increased timeout slightly
                'redirection' => 5, // Follow more redirects
                'httpversion' => '1.1',
                'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', // Modern UA
                'sslverify'   => (bool) apply_filters( 'osint_deck_iframe_check_sslverify', false, $url ),
            );
            
            $response = wp_remote_head( $url, $args );
            
            // If HEAD fails (some servers block it), try GET with range
            if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) === 405 ) {
                $args['headers'] = array( 'Range' => 'bytes=0-10' ); // Request tiny part
                $response = wp_remote_get( $url, $args );
            }

            if ( is_wp_error( $response ) ) {
                // If we can't reach it, assume it won't load
                $results[ $url_key ] = false;
                set_transient( $cache_key, 0, 12 * HOUR_IN_SECONDS );
                continue;
            }

            $headers = wp_remote_retrieve_headers( $response );
            $can_embed = true;

            // Check X-Frame-Options (Robust check)
            if ( isset( $headers['x-frame-options'] ) ) {
                $xfo = strtoupper( is_array( $headers['x-frame-options'] ) ? $headers['x-frame-options'][0] : $headers['x-frame-options'] );
                if ( strpos( $xfo, 'DENY' ) !== false || strpos( $xfo, 'SAMEORIGIN' ) !== false ) {
                    $can_embed = false;
                }
            }

            // Check CSP
            if ( $can_embed && isset( $headers['content-security-policy'] ) ) {
                $csp = is_array( $headers['content-security-policy'] ) ? $headers['content-security-policy'][0] : $headers['content-security-policy'];
                // Simple check for frame-ancestors
                if ( stripos( $csp, 'frame-ancestors' ) !== false ) {
                    // If frame-ancestors is present, it likely restricts embedding unless we are listed
                    // Parsing full CSP is complex, so we assume restrictive if present and not containing '*'
                    if ( stripos( $csp, 'frame-ancestors *' ) === false && stripos( $csp, "frame-ancestors 'self'" ) !== false ) {
                         $can_embed = false;
                    }
                    // Also catch explicit 'none' or just absence of wildcard if strict
                    if ( stripos( $csp, "frame-ancestors 'none'" ) !== false ) {
                         $can_embed = false;
                    }
                }
            }

            $results[ $url_key ] = $can_embed;
            set_transient( $cache_key, $can_embed ? 1 : 0, 24 * HOUR_IN_SECONDS );
        }

        wp_send_json_success( $results );
    }

    /**
     * Handle search AJAX request
     *
     * @return void
     */
    public function handle_search() {
        check_ajax_referer( 'osint_deck_public', 'nonce' );
        $this->verify_turnstile_or_exit();

        $query = isset( $_POST['query'] ) ? sanitize_text_field( $_POST['query'] ) : '';

        if ( empty( $query ) ) {
            wp_send_json_error( array( 'message' => __( 'Query vacío', 'osint-deck' ) ) );
        }

        $results = $this->decision_engine->process_search( $query );

        $uid = OsintUserSession::get_user_id();
        if ( $uid && strlen( $query ) > 2 ) {
            UserHistory::record( $uid, 'search', 0, '', $query );
        }

        wp_send_json_success( $results );
    }

    /**
     * Handle click tracking
     *
     * @return void
     */
    public function handle_track_click() {
        check_ajax_referer( 'osint_deck_public', 'nonce' );
        $this->verify_turnstile_or_exit();

        $tool_id = isset( $_POST['tool_id'] ) ? intval( $_POST['tool_id'] ) : 0;

        if ( ! $tool_id ) {
            wp_send_json_error();
        }

        $this->tool_repository->increment_clicks( $tool_id );

        wp_send_json_success();
    }

    /**
     * Handle report block
     * 
     * @return void
     */
    public function handle_report_block() {
        check_ajax_referer( 'osint_deck_public', 'nonce' );

        $this->verify_turnstile_or_exit();

        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
        if ( ! $url || ! wp_http_validate_url( $url ) ) {
            wp_send_json_error( array( 'message' => 'No URL provided' ) );
        }

        // Force update cache to blocked (false/0)
        $cache_key = 'osd_iframe_v2_' . md5( $url );
        set_transient( $cache_key, 0, 7 * 24 * HOUR_IN_SECONDS ); // Block for a week

        wp_send_json_success();
    }

    /**
     * Handle force download icons
     *
     * @return void
     */
    public function handle_force_download_icons() {
        check_ajax_referer( 'osint_deck_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'osint-deck' ) ) );
        }

        $logger       = new \OsintDeck\Infrastructure\Service\Logger();
        $icon_manager = new IconManager( $logger );

        $tools           = $this->tool_repository->get_all_tools();
        $updated_count   = 0;
        $save_failed     = 0;
        $download_failed = array();

        foreach ( $tools as $tool ) {
            if ( empty( $tool['favicon'] ) || empty( $tool['_db_id'] ) ) {
                continue;
            }

            $upload_dir = wp_upload_dir();
            if ( isset( $upload_dir['error'] ) && ! empty( $upload_dir['error'] ) ) {
                break;
            }
            if ( strpos( $tool['favicon'], $upload_dir['baseurl'] ) !== false ) {
                continue;
            }

            if ( ! filter_var( $tool['favicon'], FILTER_VALIDATE_URL ) ) {
                continue;
            }

            $slug = ! empty( $tool['slug'] ) ? (string) $tool['slug'] : sanitize_title( $tool['name'] );
            $r    = $icon_manager->attempt_remote_icon_download( $tool['favicon'], $slug );

            if ( ! $r['ok'] ) {
                $err_msg = $this->translate_icon_download_error( $r['error'] ?? '' );
                IconFetchFailureStore::record_failure( (int) $tool['_db_id'], (string) $tool['favicon'], $err_msg, 'auto' );
                $download_failed[] = array(
                    'id'      => (int) $tool['_db_id'],
                    'name'    => (string) ( $tool['name'] ?? '' ),
                    'slug'    => $slug,
                    'favicon' => (string) $tool['favicon'],
                    'error'   => $err_msg,
                );
                continue;
            }

            if ( $r['url'] === $tool['favicon'] ) {
                continue;
            }

            $previous_favicon = (string) $tool['favicon'];
            $tool['favicon']  = $r['url'];
            if ( $this->tool_repository->save_tool( $tool ) ) {
                IconFetchFailureStore::clear( (int) $tool['_db_id'] );
                $updated_count++;
            } else {
                $save_failed++;
                $save_err = __( 'No se pudo guardar la herramienta en la base de datos.', 'osint-deck' );
                IconFetchFailureStore::record_failure( (int) $tool['_db_id'], $previous_favicon, $save_err, 'auto' );
                $download_failed[] = array(
                    'id'      => (int) $tool['_db_id'],
                    'name'    => (string) ( $tool['name'] ?? '' ),
                    'slug'    => $slug,
                    'favicon' => $previous_favicon,
                    'error'   => $save_err,
                );
            }
        }

        $msg = sprintf(
            /* translators: 1: updated count, 2: download/save failures */
            __( 'Iconos descargados y guardados: %1$d. Sin resolver: %2$d.', 'osint-deck' ),
            $updated_count,
            count( $download_failed )
        );

        wp_send_json_success(
            array(
                'message'  => $msg,
                'updated'  => $updated_count,
                'failures' => $download_failed,
                'remaining_remote' => $this->finalize_remote_icon_items(),
            )
        );
    }

    /**
     * Lista herramientas cuyo favicon sigue siendo remoto.
     *
     * @return void
     */
    public function handle_list_remote_icons() {
        check_ajax_referer( 'osint_deck_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'osint-deck' ) ) );
        }

        wp_send_json_success(
            array(
                'items' => $this->finalize_remote_icon_items(),
            )
        );
    }

    /**
     * Descarga iconos usando URLs pegadas manualmente (mapa tool_id => url).
     *
     * @return void
     */
    public function handle_save_manual_icons() {
        check_ajax_referer( 'osint_deck_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'osint-deck' ) ) );
        }

        $raw  = isset( $_POST['manual'] ) ? wp_unslash( $_POST['manual'] ) : '';
        $map  = is_string( $raw ) ? json_decode( $raw, true ) : null;
        if ( ! is_array( $map ) ) {
            wp_send_json_error( array( 'message' => __( 'Datos inválidos.', 'osint-deck' ) ) );
        }

        $logger       = new \OsintDeck\Infrastructure\Service\Logger();
        $icon_manager = new IconManager( $logger );

        $updated = 0;
        $fail    = array();

        foreach ( $map as $id_key => $url_raw ) {
            $tool_id = (int) $id_key;
            $new_url = is_string( $url_raw ) ? esc_url_raw( trim( $url_raw ) ) : '';
            if ( $tool_id <= 0 || $new_url === '' ) {
                continue;
            }

            $tool = $this->tool_repository->get_tool_by_id( $tool_id );
            if ( ! $tool ) {
                $fail[] = array(
                    'id'    => $tool_id,
                    'error' => __( 'Herramienta no encontrada.', 'osint-deck' ),
                );
                continue;
            }

            $slug = ! empty( $tool['slug'] ) ? (string) $tool['slug'] : sanitize_title( $tool['name'] );
            $r    = $icon_manager->attempt_remote_icon_download( $new_url, $slug );

            if ( ! $r['ok'] ) {
                $err_msg = $this->translate_icon_download_error( $r['error'] ?? '' );
                IconFetchFailureStore::record_failure( $tool_id, $new_url, $err_msg, 'manual' );
                $fail[] = array(
                    'id'      => $tool_id,
                    'name'    => (string) ( $tool['name'] ?? '' ),
                    'error'   => $err_msg,
                    'favicon' => $new_url,
                );
                continue;
            }

            $tool['favicon'] = $r['url'];
            if ( $this->tool_repository->save_tool( $tool ) ) {
                IconFetchFailureStore::clear( $tool_id );
                $updated++;
            } else {
                $save_err = __( 'No se pudo guardar la herramienta.', 'osint-deck' );
                IconFetchFailureStore::record_failure( $tool_id, $new_url, $save_err, 'manual' );
                $fail[] = array(
                    'id'    => $tool_id,
                    'name'  => (string) ( $tool['name'] ?? '' ),
                    'error' => $save_err,
                );
            }
        }

        wp_send_json_success(
            array(
                'message' => sprintf(
                    /* translators: %d: number of tools updated */
                    __( 'Iconos aplicados desde URL manual: %d.', 'osint-deck' ),
                    $updated
                ),
                'updated' => $updated,
                'failures' => $fail,
                'remaining_remote' => $this->finalize_remote_icon_items(),
            )
        );
    }

    /**
     * Lista remota + errores persistidos + orden (fallos primero).
     *
     * @return array<int, array<string, mixed>>
     */
    private function finalize_remote_icon_items() {
        IconFetchFailureStore::prune_stale( $this->tool_repository );
        $items = $this->collect_remote_icon_items();
        $items = IconFetchFailureStore::merge_into_items( $items );
        return IconFetchFailureStore::sort_errors_first( $items );
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string, favicon: string}>
     */
    private function collect_remote_icon_items() {
        $upload_dir = wp_upload_dir();
        if ( isset( $upload_dir['error'] ) && ! empty( $upload_dir['error'] ) ) {
            return array();
        }

        $base = $upload_dir['baseurl'];
        $out  = array();

        foreach ( $this->tool_repository->get_all_tools() as $tool ) {
            if ( empty( $tool['favicon'] ) || empty( $tool['_db_id'] ) ) {
                continue;
            }
            $fav = (string) $tool['favicon'];
            if ( strpos( $fav, $base ) !== false ) {
                continue;
            }
            if ( ! preg_match( '#^https?://#i', $fav ) ) {
                continue;
            }
            $slug = ! empty( $tool['slug'] ) ? (string) $tool['slug'] : sanitize_title( $tool['name'] );
            $out[] = array(
                'id'      => (int) $tool['_db_id'],
                'name'    => (string) ( $tool['name'] ?? '' ),
                'slug'    => $slug,
                'favicon' => $fav,
            );
        }

        return $out;
    }

    /**
     * @param string $error Código o mensaje crudo de attempt_remote_icon_download.
     * @return string
     */
    private function translate_icon_download_error( $error ) {
        $error = (string) $error;
        if ( $error === 'upload_dir' ) {
            return __( 'No se puede escribir en la carpeta de uploads.', 'osint-deck' );
        }
        if ( $error === 'invalid_url' ) {
            return __( 'La URL no es válida.', 'osint-deck' );
        }
        if ( $error === 'mkdir_failed' ) {
            return __( 'No se pudo crear la carpeta de iconos.', 'osint-deck' );
        }
        if ( $error === 'empty_body' ) {
            return __( 'El servidor respondió sin contenido.', 'osint-deck' );
        }
        if ( $error === 'save_failed' ) {
            return __( 'No se pudo guardar el archivo en el servidor.', 'osint-deck' );
        }
        if ( preg_match( '/^http_(\d+)$/', $error, $m ) ) {
            return sprintf(
                /* translators: %s: HTTP status code */
                __( 'Error HTTP %s al descargar.', 'osint-deck' ),
                $m[1]
            );
        }
        if ( strpos( $error, 'request:' ) === 0 ) {
            return __( 'Error de red al descargar.', 'osint-deck' ) . ' ' . trim( substr( $error, 8 ) );
        }
        if ( $error !== '' ) {
            return $error;
        }
        return __( 'Error desconocido.', 'osint-deck' );
    }

    /**
     * Handle tool import
     *
     * @return void
     */
    public function handle_import_tool() {
        check_ajax_referer( 'osint_deck_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'osint-deck' ) ) );
        }

        $json_data = isset( $_POST['json_data'] ) ? json_decode( stripslashes( $_POST['json_data'] ), true ) : null;

        if ( ! $json_data ) {
            wp_send_json_error( array( 'message' => __( 'JSON inválido', 'osint-deck' ) ) );
        }

        $result = $this->tool_repository->import_from_json( $json_data );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'tool_id' => $result,
            'message' => __( 'Herramienta importada correctamente', 'osint-deck' ),
        ) );
    }

    /**
     * Handle tool export
     *
     * @return void
     */
    public function handle_export_tool() {
        check_ajax_referer( 'osint_deck_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'osint-deck' ) ) );
        }

        $tool_id = isset( $_POST['tool_id'] ) ? intval( $_POST['tool_id'] ) : 0;

        if ( ! $tool_id ) {
            wp_send_json_error( array( 'message' => __( 'ID de herramienta inválido', 'osint-deck' ) ) );
        }

        $json_data = $this->tool_repository->export_to_json( $tool_id );

        if ( ! $json_data ) {
            wp_send_json_error( array( 'message' => __( 'Herramienta no encontrada', 'osint-deck' ) ) );
        }

        wp_send_json_success( array(
            'json' => $json_data,
            'filename' => sanitize_title( $json_data['name'] ) . '.json',
        ) );
    }

    /**
     * Sugerencias de nombres de herramienta para el filtro de métricas (admin).
     *
     * @return void
     */
    public function handle_metrics_tool_suggest() {
        check_ajax_referer( 'osint_deck_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'osint-deck' ) ) );
        }

        $q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
        if ( '' === $q || mb_strlen( $q ) < 2 ) {
            wp_send_json_success( array( 'suggestions' => array() ) );
        }

        $needle = mb_strtolower( $q );
        $tools  = $this->tool_repository->get_all_tools();
        $rows   = array();

        foreach ( $tools as $t ) {
            $name = isset( $t['name'] ) ? trim( (string) $t['name'] ) : '';
            if ( '' === $name ) {
                continue;
            }
            $lower = mb_strtolower( $name );
            if ( false === mb_strpos( $lower, $needle, 0 ) ) {
                continue;
            }
            $starts = ( 0 === mb_strpos( $lower, $needle, 0 ) ) ? 0 : 1;
            $rows[] = array(
                'name'   => $name,
                'starts' => $starts,
                'len'    => mb_strlen( $name ),
            );
        }

        usort(
            $rows,
            static function ( $a, $b ) {
                if ( $a['starts'] !== $b['starts'] ) {
                    return $a['starts'] <=> $b['starts'];
                }
                if ( $a['len'] !== $b['len'] ) {
                    return $a['len'] <=> $b['len'];
                }
                return strcasecmp( $a['name'], $b['name'] );
            }
        );

        $suggestions = array();
        $seen        = array();
        foreach ( $rows as $row ) {
            $n = $row['name'];
            if ( isset( $seen[ $n ] ) ) {
                continue;
            }
            $seen[ $n ] = true;
            $suggestions[] = $n;
            if ( count( $suggestions ) >= 20 ) {
                break;
            }
        }

        wp_send_json_success( array( 'suggestions' => $suggestions ) );
    }

    public function handle_auth_google() {
        check_ajax_referer( 'osint_deck_public', 'nonce' );
        $this->verify_turnstile_or_exit();
        $enabled = (bool) get_option( 'osint_deck_sso_enabled', false );
        if ( ! $enabled ) {
            wp_send_json_error( array( 'message' => __( 'SSO deshabilitado', 'osint-deck' ) ) );
        }

        $client_id = get_option( 'osint_deck_google_client_id', '' );
        $id_token = isset( $_POST['id_token'] ) ? sanitize_text_field( $_POST['id_token'] ) : '';
        if ( empty( $id_token ) ) {
            wp_send_json_error( array( 'message' => __( 'Token inválido', 'osint-deck' ) ) );
        }

        $resp = wp_remote_get(
            'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode( $id_token ),
            array(
                'timeout'   => 10,
                'sslverify' => true,
            )
        );
        if ( is_wp_error( $resp ) ) {
            wp_send_json_error( array( 'message' => __( 'Error verificando token', 'osint-deck' ) ) );
        }
        $body = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( ! is_array( $body ) || empty( $body['aud'] ) || $body['aud'] !== $client_id ) {
            wp_send_json_error( array( 'message' => __( 'Cliente no coincide', 'osint-deck' ) ) );
        }
        if ( empty( $body['email'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Email no disponible', 'osint-deck' ) ) );
        }
        if ( empty( $body['sub'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Token sin identificador de cuenta (sub)', 'osint-deck' ) ) );
        }

        $email   = sanitize_email( $body['email'] );
        $name    = isset( $body['name'] ) ? sanitize_text_field( $body['name'] ) : '';
        $picture = isset( $body['picture'] ) ? esc_url_raw( $body['picture'] ) : '';
        $sub     = sanitize_text_field( (string) $body['sub'] );

        $deck_id = DeckUsers::upsert_from_google( $sub, $email, $name, $picture );
        if ( $deck_id <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'No se pudo registrar la sesión del deck', 'osint-deck' ) ) );
        }

        OsintUserSession::set_cookie( $deck_id );
        $deck = DeckUsers::get_by_id( $deck_id );

        wp_send_json_success( array(
            'id'      => $deck_id,
            'name'    => $deck ? $deck['display_name'] : $name,
            'email'   => $deck ? $deck['user_email'] : $email,
            'avatar'  => $deck && ! empty( $deck['avatar_url'] ) ? $deck['avatar_url'] : $picture,
            'favorite_tool_ids'      => UserFavorites::get_tool_ids( $deck_id ),
            'liked_tool_ids'         => UserLikes::get_tool_ids( $deck_id ),
            'reported_tool_ids'      => ToolReports::get_open_tool_ids_for_user( $deck_id ),
            'report_thanks_tool_ids' => ReportThanks::get_pending_for_user( $deck_id ),
        ) );
    }

    public function handle_get_user() {
        if ( isset( $_POST['nonce'] ) ) {
            check_ajax_referer( 'osint_deck_public', 'nonce' );
        }
        $deck_id = OsintUserSession::get_user_id();
        if ( ! $deck_id ) {
            wp_send_json_success( array( 'logged_in' => false ) );
        }
        $deck = DeckUsers::get_by_id( $deck_id );
        if ( ! $deck ) {
            OsintUserSession::clear_cookie();
            wp_send_json_success( array( 'logged_in' => false ) );
        }
        wp_send_json_success( array(
            'logged_in'              => true,
            'id'                     => (int) $deck['id'],
            'name'                   => $deck['display_name'],
            'email'                  => $deck['user_email'],
            'avatar'                 => $deck['avatar_url'],
            'favorite_tool_ids'      => UserFavorites::get_tool_ids( (int) $deck['id'] ),
            'liked_tool_ids'         => UserLikes::get_tool_ids( (int) $deck['id'] ),
            'reported_tool_ids'      => ToolReports::get_open_tool_ids_for_user( (int) $deck['id'] ),
            'report_thanks_tool_ids' => ReportThanks::get_pending_for_user( (int) $deck['id'] ),
        ) );
    }

    public function handle_logout() {
        check_ajax_referer( 'osint_deck_public', 'nonce' );
        $this->verify_turnstile_or_exit();
        OsintUserSession::clear_cookie();
        wp_send_json_success();
    }

    /**
     * Vacía todos los favoritos del usuario autenticado (cookie SSO).
     */
    public function handle_clear_favorites() {
        check_ajax_referer( 'osint_deck_public', 'nonce' );
        $this->verify_turnstile_or_exit();

        $user_id = OsintUserSession::get_user_id();
        if ( ! $user_id ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Tenés que iniciar sesión para vaciar favoritos.', 'osint-deck' ),
                )
            );
        }

        $cleared_ids = UserFavorites::clear_all( $user_id );

        $cleared_ids = array_values(
            array_unique(
                array_filter(
                    array_map( 'intval', $cleared_ids ),
                    static function ( $id ) {
                        return $id > 0;
                    }
                )
            )
        );

        foreach ( $cleared_ids as $tid ) {
            $this->tool_repository->decrement_favorites( $tid );
        }

        wp_send_json_success(
            array(
                'cleared'   => count( $cleared_ids ),
                'tool_ids'  => $cleared_ids,
            )
        );
    }

    /**
     * Historial de actividad (cookie SSO).
     */
    public function handle_get_history() {
        check_ajax_referer( 'osint_deck_public', 'nonce' );
        $user_id = OsintUserSession::get_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'No iniciaste sesión.', 'osint-deck' ) ) );
        }
        $rows = UserHistory::list_for_user( $user_id, 100 );
        $out  = array();
        foreach ( $rows as $r ) {
            $out[] = array(
                'id'             => (int) $r['id'],
                'event_type'     => $r['event_type'],
                'tool_id'        => (int) $r['tool_id'],
                'tool_name'      => $r['tool_name'],
                'query_snapshot' => $r['query_snapshot'],
                'created_at'     => $r['created_at'],
            );
        }
        wp_send_json_success( array( 'items' => $out ) );
    }

    /**
     * Borra todo el historial del usuario (no elimina la cuenta).
     */
    public function handle_clear_history() {
        check_ajax_referer( 'osint_deck_public', 'nonce' );
        $this->verify_turnstile_or_exit();
        $user_id = OsintUserSession::get_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'No iniciaste sesión.', 'osint-deck' ) ) );
        }
        $n = UserHistory::delete_for_user( $user_id );
        wp_send_json_success( array( 'deleted' => $n ) );
    }

    /**
     * Derecho al olvido: borra historial, usuario WP (si no es admin) y cookie.
     */
    public function handle_delete_my_account() {
        check_ajax_referer( 'osint_deck_public', 'nonce' );
        $this->verify_turnstile_or_exit();
        $confirm = isset( $_POST['confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) : '';
        if ( 'DELETE' !== $confirm ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Para confirmar, enviá la palabra DELETE tal como se indica.', 'osint-deck' ),
                )
            );
        }
        $terms_ok = isset( $_POST['terms_accepted'] ) ? sanitize_text_field( wp_unslash( $_POST['terms_accepted'] ) ) : '';
        if ( '1' !== $terms_ok ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Debés confirmar en el formulario la aceptación de los términos y condiciones para eliminar la cuenta.', 'osint-deck' ),
                )
            );
        }
        $deck_id = OsintUserSession::get_user_id();
        if ( ! $deck_id ) {
            wp_send_json_error( array( 'message' => __( 'No iniciaste sesión.', 'osint-deck' ) ) );
        }
        DeckUsers::delete_cascade( $deck_id );
        OsintUserSession::clear_cookie();
        wp_send_json_success();
    }

    /**
     * Reportes abiertos y cola de agradecimientos según sesión o huella.
     */
    public function handle_report_state() {
        check_ajax_referer( 'osint_deck_public', 'nonce' );

        $fp_raw = isset( $_POST['fp'] ) ? sanitize_text_field( wp_unslash( $_POST['fp'] ) ) : '';
        $uid    = (int) OsintUserSession::get_user_id();

        if ( $uid > 0 ) {
            wp_send_json_success(
                array(
                    'reported_tool_ids' => ToolReports::get_open_tool_ids_for_user( $uid ),
                    'thanks_tool_ids'    => ReportThanks::get_pending_for_user( $uid ),
                )
            );
        }

        $h = ToolReports::fp_hash( $fp_raw );
        wp_send_json_success(
            array(
                'reported_tool_ids' => ToolReports::get_open_tool_ids_for_fp_hash( $h ),
                'thanks_tool_ids'    => ReportThanks::get_pending_for_fp_hash( $h ),
            )
        );
    }

    /**
     * Limpia la cola de mensajes de “gracias” tras mostrarlos en el cliente.
     */
    public function handle_dismiss_report_thanks() {
        check_ajax_referer( 'osint_deck_public', 'nonce' );
        $this->verify_turnstile_or_exit();

        $raw = isset( $_POST['tool_ids'] ) ? wp_unslash( $_POST['tool_ids'] ) : '';
        $ids = json_decode( $raw, true );
        if ( ! is_array( $ids ) ) {
            $ids = array();
        }
        $ids = array_values(
            array_unique(
                array_filter(
                    array_map( 'intval', $ids ),
                    static function ( $id ) {
                        return $id > 0;
                    }
                )
            )
        );
        if ( array() === $ids ) {
            wp_send_json_success();
        }

        $fp_raw = isset( $_POST['fp'] ) ? sanitize_text_field( wp_unslash( $_POST['fp'] ) ) : '';
        $uid    = (int) OsintUserSession::get_user_id();
        ReportThanks::dismiss( $uid, ToolReports::fp_hash( $fp_raw ), $ids );
        wp_send_json_success();
    }
}
