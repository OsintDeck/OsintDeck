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
        $tool_id = isset( $_POST['tool_id'] ) ? intval( $_POST['tool_id'] ) : 0;
        $fp = isset( $_POST['fp'] ) ? sanitize_text_field( $_POST['fp'] ) : '';

        if ( empty( $event ) ) {
            wp_send_json( array( 'ok' => false, 'message' => 'Event type required' ) );
        }

        // Get tool
        $tool = null;
        if ( $tool_id ) {
            $tool = $this->tool_repository->get_tool_by_id( $tool_id );
        }

        if ( ! $tool && ! empty( $tool_name ) ) {
            // Try to find by name
            $tool = $this->tool_repository->get_tool_by_slug( sanitize_title( $tool_name ) );
            if ( ! $tool ) {
                $all_tools = $this->tool_repository->get_all_tools();
                foreach ( $all_tools as $t ) {
                    if ( strcasecmp( $t['name'], $tool_name ) === 0 ) {
                        $tool = $t;
                        break;
                    }
                }
            }
        }

        if ( ! $tool ) {
            wp_send_json( array( 'ok' => false, 'message' => 'Tool not found' ) );
        }

        // Handle different event types
        switch ( $event ) {
            case 'click':
            case 'click_tool':
                $count = $this->track_click( $tool, $fp );
                wp_send_json( array( 'ok' => true, 'count' => $count ) );
                break;

            case 'like':
                $count = $this->track_like( $tool, $fp );
                wp_send_json( array( 'ok' => true, 'count' => $count ) );
                break;

            case 'favorite':
                $count = $this->track_favorite( $tool, $fp );
                wp_send_json( array( 'ok' => true, 'count' => $count ) );
                break;

            case 'report':
            case 'report_tool':
                $count = $this->track_report( $tool, $fp );
                wp_send_json( array( 'ok' => true, 'count' => $count, 'msg' => 'Reporte enviado' ) );
                break;
                
            default:
                wp_send_json( array( 'ok' => false, 'message' => 'Invalid event' ) );
                break;
        }
    }

    /**
     * Track click event
     *
     * @param array  $tool Tool data.
     * @param string $fp   Fingerprint.
     * @return int New count.
     */
    private function track_click( $tool, $fp ) {
        if ( empty( $tool['_db_id'] ) ) {
            return 0;
        }

        return $this->tool_repository->increment_clicks( $tool['_db_id'] );
    }

    /**
     * Track like event
     *
     * @param array  $tool Tool data.
     * @param string $fp   Fingerprint.
     * @return int New count.
     */
    private function track_like( $tool, $fp ) {
        if ( empty( $tool['_db_id'] ) ) {
            return 0;
        }

        return $this->tool_repository->increment_likes( $tool['_db_id'] );
    }

    /**
     * Track favorite event
     *
     * @param array  $tool Tool data.
     * @param string $fp   Fingerprint.
     * @return int New count.
     */
    private function track_favorite( $tool, $fp ) {
        if ( empty( $tool['_db_id'] ) ) {
            return 0;
        }

        return $this->tool_repository->increment_favorites( $tool['_db_id'] );
    }

    /**
     * Track report event
     *
     * @param array  $tool Tool data.
     * @param string $fp   Fingerprint.
     * @return int New count.
     */
    private function track_report( $tool, $fp ) {
        if ( empty( $tool['_db_id'] ) ) {
            return 0;
        }

        return $this->tool_repository->increment_reports( $tool['_db_id'] );
    }
}
