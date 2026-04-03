<?php
/**
 * User Events - Handle user interaction tracking
 *
 * @package OsintDeck
 */

namespace OsintDeck\Presentation\Api;

use OsintDeck\Domain\Repository\ToolRepositoryInterface;
use OsintDeck\Infrastructure\Auth\OsintUserSession;
use OsintDeck\Infrastructure\Persistence\UserHistory;
use OsintDeck\Infrastructure\Persistence\UserFavorites;
use OsintDeck\Infrastructure\Persistence\UserLikes;
use OsintDeck\Infrastructure\Persistence\AnonymousLikesStore;
use OsintDeck\Infrastructure\Persistence\ToolReports;
use OsintDeck\Infrastructure\Security\Turnstile;

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
        check_ajax_referer( 'osint_deck_public', 'nonce' );

        $ts = Turnstile::verify_request();
        if ( is_wp_error( $ts ) ) {
            wp_send_json(
                array(
                    'ok'      => false,
                    'message' => $ts->get_error_message(),
                    'code'    => $ts->get_error_code(),
                )
            );
        }

        $event = isset( $_POST['event'] ) ? sanitize_text_field( $_POST['event'] ) : '';
        $tool_name = isset( $_POST['tool'] ) ? sanitize_text_field( $_POST['tool'] ) : '';
        $tool_id_raw = isset( $_POST['tool_id'] ) ? sanitize_text_field( $_POST['tool_id'] ) : '';
        $tool_id = intval( $tool_id_raw );
        $fp      = isset( $_POST['fp'] ) ? sanitize_text_field( wp_unslash( $_POST['fp'] ) ) : '';
        $report_message_raw = isset( $_POST['report_message'] ) ? wp_unslash( $_POST['report_message'] ) : '';
        $report_message     = sanitize_textarea_field( $report_message_raw );
        $report_message     = wp_strip_all_tags( $report_message );
        $report_message     = str_replace( "\0", '', $report_message );
        if ( function_exists( 'mb_substr' ) ) {
            $report_message = mb_substr( $report_message, 0, 2000 );
        } else {
            $report_message = substr( $report_message, 0, 2000 );
        }

        if ( empty( $event ) ) {
            wp_send_json( array( 'ok' => false, 'message' => 'Event type required' ) );
        }

        // Get tool
        $tool = null;
        if ( $tool_id ) {
            $tool = $this->tool_repository->get_tool_by_id( $tool_id );
        }

        if ( ! $tool && ! empty( $tool_id_raw ) ) {
             // Try to use tool_id_raw as slug if it wasn't a valid int ID
             $tool = $this->tool_repository->get_tool_by_slug( sanitize_title( $tool_id_raw ) );
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

        $hist_uid = OsintUserSession::get_user_id();
        $hist_tid = ! empty( $tool['_db_id'] ) ? (int) $tool['_db_id'] : 0;
        $hist_name = isset( $tool['name'] ) ? (string) $tool['name'] : '';

        // Handle different event types
        switch ( $event ) {
            case 'click':
            case 'click_tool':
                $count = $this->track_click( $tool, $fp );
                if ( $hist_uid ) {
                    $hist_q = $this->request_history_query_snapshot();
                    UserHistory::record( $hist_uid, 'open_tool', $hist_tid, $hist_name, $hist_q );
                }
                wp_send_json( array( 'ok' => true, 'count' => $count ) );
                break;

            case 'like':
                $like_result = $this->toggle_like( $tool, $fp );
                if ( empty( $like_result['ok'] ) ) {
                    wp_send_json(
                        array(
                            'ok'      => false,
                            'message' => isset( $like_result['message'] )
                                ? (string) $like_result['message']
                                : __( 'No se pudo actualizar el me gusta.', 'osint-deck' ),
                        )
                    );
                }
                if ( $hist_uid && ! empty( $like_result['added'] ) ) {
                    UserHistory::record( $hist_uid, 'like', $hist_tid, $hist_name, null );
                }
                wp_send_json(
                    array(
                        'ok'      => true,
                        'count'   => (int) $like_result['count'],
                        'liked'   => ! empty( $like_result['liked'] ),
                        'added'   => ! empty( $like_result['added'] ),
                        'removed' => ! empty( $like_result['removed'] ),
                    )
                );
                break;

            case 'favorite':
                if ( ! OsintUserSession::get_user_id() ) {
                    wp_send_json(
                        array(
                            'ok'      => false,
                            'message' => __( 'Tenés que iniciar sesión con tu cuenta para usar favoritos.', 'osint-deck' ),
                        )
                    );
                }
                $result = $this->toggle_favorite( $tool );
                if ( $hist_uid && $result['added'] ) {
                    UserHistory::record( $hist_uid, 'favorite', $hist_tid, $hist_name, null );
                }
                wp_send_json(
                    array(
                        'ok'        => true,
                        'count'     => $result['count'],
                        'favorited' => $result['favorited'],
                        'added'     => $result['added'],
                        'removed'   => $result['removed'],
                    )
                );
                break;

            case 'report':
            case 'report_tool':
                $rep_result = $this->toggle_report( $tool, $fp, $report_message );
                if ( empty( $rep_result['ok'] ) ) {
                    wp_send_json(
                        array(
                            'ok'      => false,
                            'message' => isset( $rep_result['message'] )
                                ? (string) $rep_result['message']
                                : __( 'No se pudo procesar el reporte.', 'osint-deck' ),
                        )
                    );
                }
                if ( $hist_uid && ! empty( $rep_result['added'] ) ) {
                    UserHistory::record( $hist_uid, 'report', $hist_tid, $hist_name, null );
                }
                wp_send_json(
                    array(
                        'ok'        => true,
                        'count'     => (int) $rep_result['count'],
                        'reported'  => ! empty( $rep_result['reported'] ),
                        'added'     => ! empty( $rep_result['added'] ),
                        'removed'   => ! empty( $rep_result['removed'] ),
                    )
                );
                break;
                
            default:
                wp_send_json( array( 'ok' => false, 'message' => 'Invalid event' ) );
                break;
        }
    }

    /**
     * Consulta en el campo del deck (opcional) enviada con el evento.
     *
     * @return string|null
     */
    private function request_history_query_snapshot() {
        if ( isset( $_POST['query_snapshot'] ) && is_string( $_POST['query_snapshot'] ) ) {
            $q = sanitize_text_field( wp_unslash( $_POST['query_snapshot'] ) );
            return $q !== '' ? $q : null;
        }
        if ( isset( $_POST['input_value'] ) && is_string( $_POST['input_value'] ) ) {
            $q = sanitize_text_field( wp_unslash( $_POST['input_value'] ) );
            return $q !== '' ? $q : null;
        }
        return null;
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
     * Un me gusta por herramienta por usuario o por huella anónima; quitar revierte el contador global.
     *
     * @param array  $tool Tool data.
     * @param string $fp   Fingerprint (visitante sin sesión).
     * @return array{ok: bool, count?: int, liked?: bool, added?: bool, removed?: bool, message?: string}
     */
    private function toggle_like( $tool, $fp ) {
        $tid = ! empty( $tool['_db_id'] ) ? (int) $tool['_db_id'] : 0;
        if ( $tid <= 0 ) {
            return array(
                'ok'      => true,
                'count'   => 0,
                'liked'   => false,
                'added'   => false,
                'removed' => false,
            );
        }

        $uid = (int) OsintUserSession::get_user_id();

        if ( $uid > 0 ) {
            if ( UserLikes::is_liked( $uid, $tid ) ) {
                UserLikes::remove( $uid, $tid );
                $count = $this->tool_repository->decrement_likes( $tid );
                return array(
                    'ok'      => true,
                    'count'   => $count,
                    'liked'   => false,
                    'added'   => false,
                    'removed' => true,
                );
            }
            UserLikes::add( $uid, $tid );
            $count = $this->tool_repository->increment_likes( $tid );
            return array(
                'ok'      => true,
                'count'   => $count,
                'liked'   => true,
                'added'   => true,
                'removed' => false,
            );
        }

        $fp = is_string( $fp ) ? trim( $fp ) : '';
        if ( $fp === '' ) {
            return array(
                'ok'      => false,
                'message' => __( 'Tu navegador no envió la señal anónima; recargá la página e intentá de nuevo.', 'osint-deck' ),
            );
        }

        if ( AnonymousLikesStore::has_liked( $fp, $tid ) ) {
            AnonymousLikesStore::remove( $fp, $tid );
            $count = $this->tool_repository->decrement_likes( $tid );
            return array(
                'ok'      => true,
                'count'   => $count,
                'liked'   => false,
                'added'   => false,
                'removed' => true,
            );
        }

        AnonymousLikesStore::add( $fp, $tid );
        $count = $this->tool_repository->increment_likes( $tid );
        return array(
            'ok'      => true,
            'count'   => $count,
            'liked'   => true,
            'added'   => true,
            'removed' => false,
        );
    }

    /**
     * Alterna favorito solo para usuario autenticado (cookie SSO).
     *
     * @param array $tool Tool data.
     * @return array{count:int, favorited:bool, added:bool, removed:bool}
     */
    private function toggle_favorite( $tool ) {
        $tid = ! empty( $tool['_db_id'] ) ? (int) $tool['_db_id'] : 0;
        if ( (int) $tid <= 0 ) {
            return array(
                'count'     => 0,
                'favorited' => false,
                'added'     => false,
                'removed'   => false,
            );
        }

        $uid = OsintUserSession::get_user_id();
        if ( ! $uid ) {
            return array(
                'count'     => 0,
                'favorited' => false,
                'added'     => false,
                'removed'   => false,
            );
        }

        if ( UserFavorites::is_favorite( $uid, $tid ) ) {
            UserFavorites::remove( $uid, $tid );
            $count = $this->tool_repository->decrement_favorites( $tid );
            return array(
                'count'     => $count,
                'favorited' => false,
                'added'     => false,
                'removed'   => true,
            );
        }

        UserFavorites::add( $uid, $tid );
        $count = $this->tool_repository->increment_favorites( $tid );
        return array(
            'count'     => $count,
            'favorited' => true,
            'added'     => true,
            'removed'   => false,
        );
    }

    /**
     * Reporte con toggle: un reporte activo por usuario o huella; comentario solo si hay sesión.
     *
     * @param array  $tool Tool data.
     * @param string $fp   Fingerprint.
     * @param string $message Mensaje opcional (solo usuarios logueados).
     * @return array{ok: bool, count?: int, reported?: bool, added?: bool, removed?: bool, message?: string}
     */
    private function toggle_report( $tool, $fp, $message ) {
        $tid = ! empty( $tool['_db_id'] ) ? (int) $tool['_db_id'] : 0;
        if ( $tid <= 0 ) {
            return array(
                'ok'       => true,
                'count'    => 0,
                'reported' => false,
                'added'    => false,
                'removed'  => false,
            );
        }

        $uid     = (int) OsintUserSession::get_user_id();
        $fp_raw  = is_string( $fp ) ? trim( $fp ) : '';
        $fp_hash = ToolReports::fp_hash( $fp_raw );

        $existing = ToolReports::find_open_row( $tid, $uid, $uid > 0 ? '' : $fp_hash );

        if ( is_array( $existing ) && ! empty( $existing['id'] ) ) {
            if ( ! ToolReports::delete_open_by_id( (int) $existing['id'] ) ) {
                return array(
                    'ok'      => false,
                    'message' => __( 'No se pudo quitar el reporte.', 'osint-deck' ),
                );
            }
            $count = $this->tool_repository->decrement_reports( $tid );
            return array(
                'ok'       => true,
                'count'    => $count,
                'reported' => false,
                'added'    => false,
                'removed'  => true,
            );
        }

        if ( $uid <= 0 && $fp_raw === '' ) {
            return array(
                'ok'      => false,
                'message' => __( 'Tu navegador no envió la señal anónima; recargá la página e intentá de nuevo.', 'osint-deck' ),
            );
        }

        $msg_for_db = '';
        if ( $uid > 0 && $message !== '' ) {
            $msg_for_db = $message;
        }

        $ins = ToolReports::insert_open( $tid, $uid, $fp_hash, $msg_for_db );
        if ( ! $ins ) {
            return array(
                'ok'      => false,
                'message' => __( 'No se pudo guardar el reporte.', 'osint-deck' ),
            );
        }
        $count = $this->tool_repository->increment_reports( $tid );
        return array(
            'ok'       => true,
            'count'    => $count,
            'reported' => true,
            'added'    => true,
            'removed'  => false,
        );
    }
}
