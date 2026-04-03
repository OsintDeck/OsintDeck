<?php
/**
 * Crea un issue en GitHub cuando un administrador marca una herramienta como reparada.
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Service;

/**
 * Opcional: requiere repo, token con permiso issues:write y la opción activada en Ajustes.
 */
class GitHubRepairIssueNotifier {

    public const OPTION_ENABLED = 'osint_deck_github_repair_issue_enabled';
    public const OPTION_REPO    = 'osint_deck_github_repair_issue_repo';
    public const OPTION_TOKEN   = 'osint_deck_github_repair_issue_token';

    /** Si es «1», no mostrar el aviso de colaboración en Reportes (solo cuando GitHub no está configurado). */
    public const OPTION_COLLAB_NUDGE_DISMISSED = 'osint_deck_github_collab_nudge_dismissed';

    /**
     * @return bool
     */
    public static function is_enabled() {
        return (bool) get_option( self::OPTION_ENABLED, false );
    }

    /**
     * Token: opción en BD o variable de entorno OSINT_DECK_GITHUB_ISSUES_TOKEN.
     *
     * @return string
     */
    public static function resolve_token() {
        $t = (string) get_option( self::OPTION_TOKEN, '' );
        $t = trim( $t );
        if ( $t !== '' ) {
            return $t;
        }
        if ( function_exists( 'getenv' ) ) {
            $e = getenv( 'OSINT_DECK_GITHUB_ISSUES_TOKEN' );
            if ( is_string( $e ) && $e !== '' ) {
                return $e;
            }
        }
        return '';
    }

    /**
     * owner/repo normalizado.
     *
     * @return string
     */
    public static function resolve_repo() {
        $r = trim( (string) get_option( self::OPTION_REPO, '' ), " \t\n\r\0\x0B/" );
        return $r;
    }

    /**
     * @return bool
     */
    public static function is_configured() {
        if ( ! self::is_enabled() ) {
            return false;
        }
        $repo  = self::resolve_repo();
        $token = self::resolve_token();
        if ( $repo === '' || $token === '' ) {
            return false;
        }
        return (bool) preg_match( '#^[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+$#', $repo );
    }

    /**
     * Mostrar aviso en Reportes para invitar a configurar GitHub (colaboración voluntaria).
     *
     * @return bool
     */
    public static function should_show_collaboration_nudge() {
        if ( self::is_configured() ) {
            return false;
        }
        return '1' !== (string) get_option( self::OPTION_COLLAB_NUDGE_DISMISSED, '' );
    }

    /**
     * El administrador pulsó «No volver a mostrar» en Reportes; permite reactivar el aviso desde Ajustes.
     */
    public static function is_collaboration_nudge_dismissed() {
        return '1' === (string) get_option( self::OPTION_COLLAB_NUDGE_DISMISSED, '' );
    }

    /**
     * Vuelve a mostrar el aviso de colaboración en Reportes (si GitHub sigue sin estar configurado).
     */
    public static function reset_collaboration_nudge_dismissal() {
        delete_option( self::OPTION_COLLAB_NUDGE_DISMISSED );
    }

    /**
     * Igual que export_to_json: listo para importar en otra instalación (sin meta interna).
     *
     * @param array<string, mixed> $tool Herramienta cruda desde BD.
     * @return array<string, mixed>
     */
    public static function tool_to_export_payload( array $tool ) {
        $out = $tool;
        foreach ( array( '_db_id', '_db_slug', '_db_created_at', '_db_updated_at' ) as $k ) {
            unset( $out[ $k ] );
        }
        return $out;
    }

    /**
     * @param array<string, mixed>      $tool         Herramienta actual (p. ej. tras editar; misma forma que get_tool_by_id).
     * @param array<int, array<string, mixed>> $report_rows Filas abiertas antes de resolver.
     * @param int                       $resolved_count Cantidad de actores notificados (gracias).
     * @param string                    $admin_label  Quién cerró (login o display).
     * @param string                    $admin_note   Nota opcional del administrador (qué se hizo).
     * @return array{ok: bool, url?: string, error?: string}
     */
    public static function notify_repair( array $tool, array $report_rows, $resolved_count, $admin_label, $admin_note = '' ) {
        if ( ! self::is_configured() ) {
            return array( 'ok' => false, 'error' => 'not_configured' );
        }

        $repo  = self::resolve_repo();
        $token = self::resolve_token();

        $name = isset( $tool['name'] ) ? (string) $tool['name'] : '';
        if ( $name === '' ) {
            $name = isset( $tool['_db_id'] ) ? 'Tool #' . (int) $tool['_db_id'] : '—';
        }
        $slug  = isset( $tool['_db_slug'] ) ? (string) $tool['_db_slug'] : ( isset( $tool['slug'] ) ? (string) $tool['slug'] : '' );
        $tid   = isset( $tool['_db_id'] ) ? (int) $tool['_db_id'] : 0;
        $site  = home_url( '/' );
        $ver   = defined( 'OSINT_DECK_VERSION' ) ? (string) OSINT_DECK_VERSION : '';

        $title = sprintf(
            /* translators: 1: tool name, 2: site host */
            __( '[OSINT Deck] Reparada: %1$s (%2$s)', 'osint-deck' ),
            $name,
            wp_parse_url( $site, PHP_URL_HOST ) ? (string) wp_parse_url( $site, PHP_URL_HOST ) : $site
        );

        $admin_note = is_string( $admin_note ) ? trim( $admin_note ) : '';

        $lines   = array();
        $lines[] = __( 'Herramienta marcada como **reparada** desde Reportes en WordPress.', 'osint-deck' );
        $lines[] = '';
        $lines[] = '| ' . __( 'Campo', 'osint-deck' ) . ' | ' . __( 'Valor', 'osint-deck' ) . ' |';
        $lines[] = '| --- | --- |';
        $lines[] = '| ' . __( 'Sitio', 'osint-deck' ) . ' | ' . $site . ' |';
        $lines[] = '| OSINT Deck | `' . $ver . '` |';
        $lines[] = '| ' . __( 'Herramienta', 'osint-deck' ) . ' | ' . $name . ' |';
        if ( $slug !== '' ) {
            $lines[] = '| slug | `' . $slug . '` |';
        }
        if ( $tid > 0 ) {
            $edit = admin_url( 'admin.php?page=osint-deck-tools&action=edit&id=' . $tid );
            $lines[] = '| ' . __( 'ID local / edición', 'osint-deck' ) . ' | ' . $tid . ' — ' . $edit . ' |';
        }
        $lines[] = '| ' . __( 'Administrador', 'osint-deck' ) . ' | ' . $admin_label . ' |';
        $lines[] = '| ' . __( 'Reportes cerrados', 'osint-deck' ) . ' | ' . count( $report_rows ) . ' |';
        $lines[] = '| ' . __( 'Agradecimientos enviados', 'osint-deck' ) . ' | ' . (int) $resolved_count . ' |';
        $lines[] = '';

        if ( array() !== $report_rows ) {
            $lines[] = '### ' . __( 'Lo que reportaron los usuarios', 'osint-deck' );
            $lines[] = '';
            foreach ( $report_rows as $r ) {
                $created = isset( $r['created_at'] ) ? (string) $r['created_at'] : '';
                $uid     = isset( $r['user_id'] ) ? (int) $r['user_id'] : 0;
                $msg     = isset( $r['message'] ) ? trim( (string) $r['message'] ) : '';
                if ( $uid > 0 ) {
                    $u   = get_userdata( $uid );
                    $who = $u ? $u->user_login : (string) $uid;
                } else {
                    $who = __( 'Anónimo', 'osint-deck' );
                }
                $lines[] = '- **' . $created . '** · ' . $who;
                $lines[] = $msg !== '' ? '  - ' . $msg : '  - _' . __( '(sin mensaje de texto)', 'osint-deck' ) . '_';
            }
            $lines[] = '';
        }

        if ( $admin_note !== '' ) {
            $lines[] = '### ' . __( 'Nota del administrador (cómo se resolvió)', 'osint-deck' );
            $lines[] = '';
            $lines[] = $admin_note;
            $lines[] = '';
        }

        $export = self::tool_to_export_payload( $tool );
        $json   = wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( ! is_string( $json ) ) {
            $json = '{}';
        }
        $max_json = 52000;
        if ( strlen( $json ) > $max_json ) {
            $json = substr( $json, 0, $max_json ) . "\n\n/* … " . __( 'JSON truncado (muy grande). Exportá la herramienta desde el listado si necesitás el archivo completo.', 'osint-deck' ) . ' */';
        }

        $lines[] = '### ' . __( 'Estado actual de la herramienta (JSON para importar)', 'osint-deck' );
        $lines[] = '';
        $lines[] = __( 'Mismo formato que importación / backup: pegá esto en otra instalación o en el repo de datos.', 'osint-deck' );
        $lines[] = '';
        $lines[] = '```json';
        $lines[] = $json;
        $lines[] = '```';
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '_' . __( 'Issue generado por OSINT Deck.', 'osint-deck' ) . '_';

        $body = implode( "\n", $lines );
        $gh_max = 65000;
        if ( strlen( $body ) > $gh_max ) {
            $body = substr( $body, 0, $gh_max ) . "\n\n…\n_" . __( 'Cuerpo truncado (límite de GitHub). Revisá el JSON en el sitio o exportá la herramienta manualmente.', 'osint-deck' ) . '_';
        }

        $api_base = 'https://api.github.com';
        if ( defined( 'OSINT_DECK_GITHUB_API_URL' ) && is_string( OSINT_DECK_GITHUB_API_URL ) && OSINT_DECK_GITHUB_API_URL !== '' ) {
            $api_base = rtrim( OSINT_DECK_GITHUB_API_URL, '/' );
        }
        $url = $api_base . '/repos/' . $repo . '/issues';

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/vnd.github+json',
                    'Content-Type'  => 'application/json',
                    'User-Agent'    => 'OSINT-Deck-WordPress',
                ),
                'body'    => wp_json_encode(
                    array(
                        'title' => $title,
                        'body'  => $body,
                    )
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            $err = $response->get_error_message();
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( '[OSINT Deck] GitHub repair issue: ' . $err );
            return array( 'ok' => false, 'error' => $err );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );

        if ( $code < 200 || $code >= 300 ) {
            $msg = is_array( $data ) && ! empty( $data['message'] ) ? (string) $data['message'] : ( 'HTTP ' . $code );
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( '[OSINT Deck] GitHub repair issue API: ' . $msg );
            return array( 'ok' => false, 'error' => $msg );
        }

        $html_url = is_array( $data ) && ! empty( $data['html_url'] ) ? (string) $data['html_url'] : '';

        return array(
            'ok'  => true,
            'url' => $html_url,
        );
    }
}
