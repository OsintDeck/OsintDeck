<?php
/**
 * Cola de reportes abiertos y acción “marcar reparado”.
 *
 * @package OsintDeck
 */

namespace OsintDeck\Presentation\Admin;

use OsintDeck\Domain\Repository\ToolRepositoryInterface;
use OsintDeck\Infrastructure\Persistence\ReportThanks;
use OsintDeck\Infrastructure\Persistence\ToolReports;
use OsintDeck\Infrastructure\Service\GitHubRepairIssueNotifier;

/**
 * Pantalla admin para reportes de usuarios.
 */
class ToolReportsAdmin {

    /**
     * @param ToolRepositoryInterface $tool_repository Repo herramientas (nombres).
     */
    public static function render_page( ToolRepositoryInterface $tool_repository ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_GET['osint_deck_dismiss_github_nudge'], $_GET['_wpnonce'] )
            && '1' === (string) $_GET['osint_deck_dismiss_github_nudge'] ) {
            if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'osint_deck_dismiss_github_nudge' ) ) {
                update_option( GitHubRepairIssueNotifier::OPTION_COLLAB_NUDGE_DISMISSED, '1', false );
                wp_safe_redirect( admin_url( 'admin.php?page=osint-deck-tool-reports' ) );
                exit;
            }
        }

        if ( isset( $_POST['osint_deck_resolve_reports'] ) && check_admin_referer( 'osint_deck_resolve_reports' ) ) {
            $tool_id = isset( $_POST['tool_id'] ) ? (int) $_POST['tool_id'] : 0;
            if ( $tool_id > 0 ) {
                $snapshot_rows = ToolReports::get_open_rows_for_tool( $tool_id );
                $repair_note   = isset( $_POST['repair_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['repair_note'] ) ) : '';
                $send_github   = ! empty( $_POST['report_to_developer'] ) && GitHubRepairIssueNotifier::is_configured();

                $notified = ToolReports::resolve_all_open_for_tool( $tool_id );
                foreach ( $notified as $actor ) {
                    ReportThanks::enqueue(
                        (int) $actor['user_id'],
                        (string) $actor['fp_hash'],
                        $tool_id
                    );
                    $tool_repository->decrement_reports( $tool_id );
                }

                $tool_latest = $tool_repository->get_tool_by_id( $tool_id );
                $tool_latest = is_array( $tool_latest ) ? $tool_latest : array( '_db_id' => $tool_id );

                $notice  = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Reportes de esa herramienta marcados como reparados. Los usuarios verán un mensaje de agradecimiento.', 'osint-deck' ) . '</p>';
                $u       = wp_get_current_user();
                $who     = $u && $u->exists() ? $u->user_login : __( 'admin', 'osint-deck' );
                $gh_res  = array( 'ok' => false );

                if ( $send_github ) {
                    $gh_res = GitHubRepairIssueNotifier::notify_repair( $tool_latest, $snapshot_rows, count( $notified ), $who, $repair_note );
                    if ( ! empty( $gh_res['ok'] ) && ! empty( $gh_res['url'] ) ) {
                        $notice .= '<p>' . esc_html__( 'Información enviada al desarrollador (issue en GitHub):', 'osint-deck' )
                            . ' <a href="' . esc_url( $gh_res['url'] ) . '" target="_blank" rel="noopener noreferrer">'
                            . esc_html__( 'Abrir issue', 'osint-deck' ) . '</a></p>';
                    } elseif ( empty( $gh_res['ok'] ) ) {
                        $err = isset( $gh_res['error'] ) ? (string) $gh_res['error'] : '';
                        $notice .= '<p>' . esc_html(
                            sprintf(
                                /* translators: %s: error message */
                                __( 'Marcaste «informar al desarrollador», pero GitHub respondió con error: %s', 'osint-deck' ),
                                $err !== '' ? $err : __( 'error desconocido', 'osint-deck' )
                            )
                        ) . '</p>';
                    }
                } elseif ( ! empty( $_POST['report_to_developer'] ) && ! GitHubRepairIssueNotifier::is_configured() ) {
                    $notice .= '<p>' . esc_html__( 'Marcaste informar al desarrollador, pero GitHub no está configurado todavía. Configuralo en Ajustes → General.', 'osint-deck' ) . '</p>';
                }

                $notice .= '</div>';
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $notice;
            }
        }

        $rows = ToolReports::get_all_open_rows();
        $gh_ok = GitHubRepairIssueNotifier::is_configured();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Reportes', 'osint-deck' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Aquí ves lo que los usuarios marcaron como problema. Al cerrar un reporte, quien lo envió recibe un agradecimiento en el deck.', 'osint-deck' ); ?>
            </p>

            <?php if ( GitHubRepairIssueNotifier::should_show_collaboration_nudge() ) : ?>
                <?php
                $settings_github = admin_url( 'admin.php?page=osint-deck-settings&tab=auth#osint-deck-github-section' );
                $dismiss_url     = wp_nonce_url(
                    admin_url( 'admin.php?page=osint-deck-tool-reports&osint_deck_dismiss_github_nudge=1' ),
                    'osint_deck_dismiss_github_nudge'
                );
                ?>
                <div class="notice notice-info" style="max-width:52rem;">
                    <p><strong><?php esc_html_e( '¿Querés colaborar con quien mantiene OSINT Deck?', 'osint-deck' ); ?></strong></p>
                    <p>
                        <?php esc_html_e( 'Si configuráis un token de GitHub (solo quien administra el sitio, cuenta propia), al marcar una herramienta como reparada podés elegir si enviar un issue con los mensajes de los usuarios, tu nota y el JSON de la herramienta. Es siempre opcional y se hace caso por caso.', 'osint-deck' ); ?>
                    </p>
                    <p>
                        <?php esc_html_e( 'Gracias por mejorar el ecosistema y por ayudar a que las herramientas lleguen corregidas a otros sitios.', 'osint-deck' ); ?>
                    </p>
                    <p>
                        <a href="<?php echo esc_url( $settings_github ); ?>" class="button button-primary"><?php esc_html_e( 'Ir a Ajustes → General (sección GitHub)', 'osint-deck' ); ?></a>
                        <a href="<?php echo esc_url( $dismiss_url ); ?>" class="button"><?php esc_html_e( 'No volver a mostrar este aviso', 'osint-deck' ); ?></a>
                    </p>
                </div>
            <?php endif; ?>

            <p class="description">
                <?php
                if ( $gh_ok ) {
                    esc_html_e( 'GitHub está listo: al marcar reparada podés tildar «Informar al desarrollador» solo cuando quieras crear el issue.', 'osint-deck' );
                } else {
                    esc_html_e( 'Sin GitHub configurado no se envía nada al exterior; el cierre de reportes sigue funcionando igual.', 'osint-deck' );
                }
                ?>
            </p>

            <?php if ( empty( $rows ) ) : ?>
                <p><?php esc_html_e( 'No hay reportes abiertos.', 'osint-deck' ); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Herramienta', 'osint-deck' ); ?></th>
                            <th><?php esc_html_e( 'Origen', 'osint-deck' ); ?></th>
                            <th><?php esc_html_e( 'Mensaje', 'osint-deck' ); ?></th>
                            <th><?php esc_html_e( 'Fecha', 'osint-deck' ); ?></th>
                            <th><?php esc_html_e( 'Acción', 'osint-deck' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $r ) : ?>
                            <?php
                            $tid = (int) $r['tool_id'];
                            $tool = $tool_repository->get_tool_by_id( $tid );
                            $tname = $tool && ! empty( $tool['name'] ) ? $tool['name'] : '#' . $tid;
                            $uid   = (int) $r['user_id'];
                            if ( $uid > 0 ) {
                                $u      = get_userdata( $uid );
                                $origin = $u ? sprintf( /* translators: %s: email */ __( 'Usuario: %s', 'osint-deck' ), $u->user_email ) : (string) $uid;
                            } else {
                                $origin = __( 'Anónimo', 'osint-deck' );
                            }
                            $msg = isset( $r['message'] ) ? trim( (string) $r['message'] ) : '';
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $tname ); ?></strong></td>
                                <td><?php echo esc_html( $origin ); ?></td>
                                <td><?php echo $msg === '' ? '—' : esc_html( $msg ); ?></td>
                                <td><?php echo esc_html( isset( $r['created_at'] ) ? (string) $r['created_at'] : '' ); ?></td>
                                <td>
                                    <form method="post" class="osint-deck-repair-form" style="max-width:28rem;" onsubmit="return confirm('<?php echo esc_js( __( '¿Marcar esta herramienta como reparada para todos los reportes abiertos de la misma?', 'osint-deck' ) ); ?>');">
                                        <?php wp_nonce_field( 'osint_deck_resolve_reports' ); ?>
                                        <input type="hidden" name="tool_id" value="<?php echo esc_attr( (string) $tid ); ?>" />
                                        <p class="description" style="margin:0 0 6px;">
                                            <label for="repair-note-<?php echo esc_attr( (string) $tid ); ?>">
                                                <?php
                                                if ( $gh_ok ) {
                                                    esc_html_e( 'Nota sobre la reparación (opcional; se incluye en el issue si informás al desarrollador)', 'osint-deck' );
                                                } else {
                                                    esc_html_e( 'Nota interna sobre la reparación (opcional)', 'osint-deck' );
                                                }
                                                ?>
                                            </label>
                                        </p>
                                        <textarea name="repair_note" id="repair-note-<?php echo esc_attr( (string) $tid ); ?>" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Ej.: URL actualizada, card 2 corregida…', 'osint-deck' ); ?>"></textarea>
                                        <?php if ( $gh_ok ) : ?>
                                            <p style="margin:10px 0 6px;">
                                                <label>
                                                    <input type="checkbox" name="report_to_developer" value="1" />
                                                    <?php esc_html_e( 'Informar al desarrollador (crear issue en GitHub con mensajes de usuarios, esta nota y el JSON actual de la herramienta)', 'osint-deck' ); ?>
                                                </label>
                                            </p>
                                            <p class="description" style="margin:0 0 8px;">
                                                <?php esc_html_e( 'Solo si marcás esta casilla se envía algo a GitHub. Cada reparación decidís vos.', 'osint-deck' ); ?>
                                            </p>
                                        <?php endif; ?>
                                        <p style="margin:8px 0 0;">
                                            <button type="submit" name="osint_deck_resolve_reports" class="button button-primary">
                                                <?php esc_html_e( 'Marcar reparada', 'osint-deck' ); ?>
                                            </button>
                                            <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-tools&action=edit&id=' . $tid ) ); ?>">
                                                <?php esc_html_e( 'Editar herramienta', 'osint-deck' ); ?>
                                            </a>
                                        </p>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
