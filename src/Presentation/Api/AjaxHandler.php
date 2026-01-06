<?php
/**
 * AJAX Handler - Handles all AJAX requests
 *
 * @package OsintDeck
 */

namespace OsintDeck\Presentation\Api;

use OsintDeck\Domain\Service\DecisionEngine;
use OsintDeck\Domain\Repository\ToolRepositoryInterface;

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
        add_action( 'wp_ajax_osint_deck_import_tool', array( $this, 'handle_import_tool' ) );
        add_action( 'wp_ajax_osint_deck_export_tool', array( $this, 'handle_export_tool' ) );

        add_action( 'wp_ajax_osint_deck_auth_google', array( $this, 'handle_auth_google' ) );
        add_action( 'wp_ajax_nopriv_osint_deck_auth_google', array( $this, 'handle_auth_google' ) );
        add_action( 'wp_ajax_osint_deck_get_user', array( $this, 'handle_get_user' ) );
        add_action( 'wp_ajax_nopriv_osint_deck_get_user', array( $this, 'handle_get_user' ) );
        add_action( 'wp_ajax_osint_deck_logout', array( $this, 'handle_logout' ) );
        add_action( 'wp_ajax_nopriv_osint_deck_logout', array( $this, 'handle_logout' ) );
    }

    /**
     * Handle iframe check AJAX request
     * 
     * @return void
     */
    public function handle_check_iframe() {
        // Allow public access, but verify nonce if present for security
        if ( isset( $_POST['nonce'] ) ) {
            check_ajax_referer( 'osint_deck_public', 'nonce' );
        }

        $urls = isset( $_POST['urls'] ) ? json_decode( stripslashes( $_POST['urls'] ), true ) : array();
        
        if ( empty( $urls ) || ! is_array( $urls ) ) {
            wp_send_json_error( array( 'message' => 'No URLs provided' ) );
        }

        // Limit to 20 URLs to prevent abuse
        $urls = array_slice( $urls, 0, 20 );
        $results = array();

        foreach ( $urls as $url ) {
            // Check cache first (v2 to flush old cache)
            $cache_key = 'osd_iframe_v2_' . md5( $url );
            $cached = get_transient( $cache_key );
            
            if ( $cached !== false ) {
                $results[$url] = (bool) $cached;
                continue;
            }

            // Perform HEAD request
            $args = array(
                'timeout'     => 5, // Increased timeout slightly
                'redirection' => 5, // Follow more redirects
                'httpversion' => '1.1',
                'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', // Modern UA
                'sslverify'   => false,
            );
            
            $response = wp_remote_head( $url, $args );
            
            // If HEAD fails (some servers block it), try GET with range
            if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) === 405 ) {
                $args['headers'] = array( 'Range' => 'bytes=0-10' ); // Request tiny part
                $response = wp_remote_get( $url, $args );
            }

            if ( is_wp_error( $response ) ) {
                // If we can't reach it, assume it won't load
                $results[$url] = false;
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

            $results[$url] = $can_embed;
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

        $query = isset( $_POST['query'] ) ? sanitize_text_field( $_POST['query'] ) : '';

        if ( empty( $query ) ) {
            wp_send_json_error( array( 'message' => __( 'Query vacío', 'osint-deck' ) ) );
        }

        $results = $this->decision_engine->process_search( $query );

        wp_send_json_success( $results );
    }

    /**
     * Handle click tracking
     *
     * @return void
     */
    public function handle_track_click() {
        check_ajax_referer( 'osint_deck_public', 'nonce' );

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
        if ( isset( $_POST['nonce'] ) ) {
            check_ajax_referer( 'osint_deck_public', 'nonce' );
        }

        $url = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';
        if ( ! $url ) {
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

        // Initialize services
        $logger = new \OsintDeck\Infrastructure\Service\Logger();
        $icon_manager = new \OsintDeck\Infrastructure\Service\IconManager( $logger );

        // Get all tools
        $tools = $this->tool_repository->get_all_tools();
        $updated_count = 0;
        $failed_count = 0;

        foreach ( $tools as $tool ) {
            if ( empty( $tool['favicon'] ) ) {
                continue;
            }

            // Check if it's already a local file (in uploads)
            $upload_dir = wp_upload_dir();
            if ( strpos( $tool['favicon'], $upload_dir['baseurl'] ) !== false ) {
                continue;
            }

            // If it's a remote URL, try to download
            if ( filter_var( $tool['favicon'], FILTER_VALIDATE_URL ) ) {
                $new_icon = $icon_manager->download_icon( $tool['favicon'], $tool['name'] );
                
                if ( $new_icon !== $tool['favicon'] ) {
                    $tool['favicon'] = $new_icon;
                    if ( $this->tool_repository->save_tool( $tool ) ) {
                        $updated_count++;
                    } else {
                        $failed_count++;
                    }
                }
            }
        }

        wp_send_json_success( array(
            'message' => sprintf( __( 'Iconos actualizados: %d. Fallidos: %d', 'osint-deck' ), $updated_count, $failed_count ),
            'updated' => $updated_count
        ) );
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

    private function set_user_cookie( $user_id ) {
        $name = 'osd_user';
        $sig = hash_hmac( 'sha256', (string) $user_id, wp_salt( 'auth' ) );
        $value = $user_id . '.' . $sig;
        $expire = time() + 7 * DAY_IN_SECONDS;
        setcookie( $name, $value, $expire, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
    }

    private function read_user_cookie() {
        $name = 'osd_user';
        if ( empty( $_COOKIE[ $name ] ) ) {
            return 0;
        }
        $parts = explode( '.', $_COOKIE[ $name ] );
        if ( count( $parts ) !== 2 ) {
            return 0;
        }
        $user_id = intval( $parts[0] );
        $sig = $parts[1];
        $expected = hash_hmac( 'sha256', (string) $user_id, wp_salt( 'auth' ) );
        if ( ! hash_equals( $expected, $sig ) ) {
            return 0;
        }
        return $user_id;
    }

    public function handle_auth_google() {
        check_ajax_referer( 'osint_deck_public', 'nonce' );
        $enabled = (bool) get_option( 'osint_deck_sso_enabled', false );
        if ( ! $enabled ) {
            wp_send_json_error( array( 'message' => __( 'SSO deshabilitado', 'osint-deck' ) ) );
        }

        $client_id = get_option( 'osint_deck_google_client_id', '' );
        $id_token = isset( $_POST['id_token'] ) ? sanitize_text_field( $_POST['id_token'] ) : '';
        if ( empty( $id_token ) ) {
            wp_send_json_error( array( 'message' => __( 'Token inválido', 'osint-deck' ) ) );
        }

        $resp = wp_remote_get( 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode( $id_token ), array( 'timeout' => 10, 'sslverify' => false ) );
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

        $email = sanitize_email( $body['email'] );
        $name = isset( $body['name'] ) ? sanitize_text_field( $body['name'] ) : '';
        $picture = isset( $body['picture'] ) ? esc_url_raw( $body['picture'] ) : '';
        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
            $login = sanitize_user( current( explode( '@', $email ) ), true );
            $login = $login ? $login : 'user' . wp_rand( 1000, 9999 );
            $user_id = wp_insert_user( array(
                'user_login' => $login,
                'user_pass'  => wp_generate_password( 20 ),
                'user_email' => $email,
                'display_name' => $name ? $name : $login,
            ) );
            if ( is_wp_error( $user_id ) ) {
                wp_send_json_error( array( 'message' => __( 'No se pudo crear usuario', 'osint-deck' ) ) );
            }
            $user = get_user_by( 'id', $user_id );
        }
        if ( $picture ) {
            update_user_meta( $user->ID, 'osint_deck_avatar', $picture );
        }
        $this->set_user_cookie( $user->ID );

        $favorites = get_user_meta( $user->ID, 'osd_favorites', true );
        if ( ! is_array( $favorites ) ) {
            $favorites = array();
        }

        wp_send_json_success( array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'avatar' => get_user_meta( $user->ID, 'osint_deck_avatar', true ),
            'favorites' => $favorites,
        ) );
    }

    public function handle_get_user() {
        if ( isset( $_POST['nonce'] ) ) {
            check_ajax_referer( 'osint_deck_public', 'nonce' );
        }
        $user_id = $this->read_user_cookie();
        if ( ! $user_id ) {
            wp_send_json_success( array( 'logged_in' => false ) );
        }
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            wp_send_json_success( array( 'logged_in' => false ) );
        }

        $favorites = get_user_meta( $user->ID, 'osd_favorites', true );
        if ( ! is_array( $favorites ) ) {
            $favorites = array();
        }

        wp_send_json_success( array(
            'logged_in' => true,
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'avatar' => get_user_meta( $user->ID, 'osint_deck_avatar', true ),
            'favorites' => $favorites,
        ) );
    }

    public function handle_logout() {
        check_ajax_referer( 'osint_deck_public', 'nonce' );
        $name = 'osd_user';
        setcookie( $name, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        wp_send_json_success();
    }
}
