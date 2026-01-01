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

        // Admin AJAX actions
        add_action( 'wp_ajax_osint_deck_import_tool', array( $this, 'handle_import_tool' ) );
        add_action( 'wp_ajax_osint_deck_export_tool', array( $this, 'handle_export_tool' ) );
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
}
