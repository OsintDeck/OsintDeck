<?php
/**
 * User Events - Handle user interaction tracking
 *
 * @package OsintDeck
 */

namespace OsintDeck\Presentation\Api;

use OsintDeck\Domain\Repository\ToolRepositoryInterface;

/**
 * Class UserEvents
 * 
 * Handles AJAX requests for user event tracking
 */
class UserEvents {

    /**
     * Tool Repository instance
     *
     * @var ToolRepositoryInterface
     */
    private $tool_repository;

    /**
     * Constructor
     *
     * @param ToolRepositoryInterface $tool_repository Tool Repository.
     */
    public function __construct( ToolRepositoryInterface $tool_repository ) {
        $this->tool_repository = $tool_repository;
    }

    /**
     * Initialize hooks
     *
     * @return void
     */
    public function init() {
        add_action( 'wp_ajax_osd_user_event', array( $this, 'handle_event' ) );
        add_action( 'wp_ajax_nopriv_osd_user_event', array( $this, 'handle_event' ) );
    }

    /**
     * Handle user event AJAX request
     *
     * @return void
     */
    public function handle_event() {
        check_ajax_referer( 'osd_user_event', 'nonce' );

        $event = isset( $_POST['event'] ) ? sanitize_text_field( $_POST['event'] ) : '';
        $tool_name = isset( $_POST['tool'] ) ? sanitize_text_field( $_POST['tool'] ) : '';
        $fp = isset( $_POST['fp'] ) ? sanitize_text_field( $_POST['fp'] ) : '';

        if ( empty( $event ) ) {
            wp_send_json( array( 'ok' => false, 'message' => 'Event type required' ) );
        }

        // Get tool by name
        $tool = $this->tool_repository->get_tool_by_slug( sanitize_title( $tool_name ) );

        if ( ! $tool && ! empty( $tool_name ) ) {
            // Try to find by name
            $all_tools = $this->tool_repository->get_all_tools();
            foreach ( $all_tools as $t ) {
                if ( strcasecmp( $t['name'], $tool_name ) === 0 ) {
                    $tool = $t;
                    break;
                }
            }
        }

        // Handle different event types
        switch ( $event ) {
            case 'click':
                if ( $tool ) {
                    $this->track_click( $tool, $fp );
                }
                wp_send_json( array( 'ok' => true ) );
                break;

            case 'like':
                if ( $tool ) {
                    $this->track_like( $tool, $fp );
                }
                wp_send_json( array( 'ok' => true ) );
                break;

            case 'report':
                if ( $tool ) {
                    $this->track_report( $tool, $fp );
                }
                wp_send_json( array( 'ok' => true ) );
                break;

            default:
                wp_send_json( array( 'ok' => false, 'message' => 'Unknown event type' ) );
        }
    }

    /**
     * Track click event
     *
     * @param array  $tool Tool data.
     * @param string $fp   Fingerprint.
     * @return void
     */
    private function track_click( $tool, $fp ) {
        if ( empty( $tool['_db_id'] ) ) {
            return;
        }

        $this->tool_repository->increment_clicks( $tool['_db_id'] );
    }

    /**
     * Track like event
     *
     * @param array  $tool Tool data.
     * @param string $fp   Fingerprint.
     * @return void
     */
    private function track_like( $tool, $fp ) {
        if ( empty( $tool['_db_id'] ) ) {
            return;
        }

        $this->tool_repository->increment_likes( $tool['_db_id'] );
    }

    /**
     * Track report event
     *
     * @param array  $tool Tool data.
     * @param string $fp   Fingerprint.
     * @return void
     */
    private function track_report( $tool, $fp ) {
        if ( empty( $tool['_db_id'] ) ) {
            return;
        }

        $this->tool_repository->increment_reports( $tool['_db_id'] );
    }
}
