<?php
/**
 * TLD Manager Admin - Admin interface for TLD management
 *
 * @package OsintDeck
 */

namespace OsintDeck\Presentation\Admin;

use OsintDeck\Infrastructure\Service\TLDManager;

/**
 * Class TLDManagerAdmin
 * 
 * Handles TLD management in admin
 */
class TLDManagerAdmin {

    /**
     * TLD Manager Service
     *
     * @var TLDManager
     */
    private $tld_manager;

    /**
     * Constructor
     *
     * @param TLDManager $tld_manager TLD Manager Service.
     */
    public function __construct( TLDManager $tld_manager ) {
        $this->tld_manager = $tld_manager;
    }

    /**
     * Render TLD management page
     *
     * @return void
     */
    public function render() {
        // Handle actions
        if ( isset( $_POST['osint_deck_tld_update'] ) ) {
            check_admin_referer( 'osint_deck_tld_update' );
            $this->handle_update_from_iana();
        }

        if ( isset( $_POST['osint_deck_tld_add'] ) ) {
            check_admin_referer( 'osint_deck_tld_add' );
            $this->handle_add_custom();
        }

        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['tld'] ) ) {
            check_admin_referer( 'delete_tld_' . $_GET['tld'] );
            $this->handle_delete_custom( $_GET['tld'] );
        }

        $stats = $this->tld_manager->get_stats();
        $custom_tlds = $this->tld_manager->get_custom();
        ?>
        <div class="wrap">
            <h1><?php _e( 'Gestión de TLDs', 'osint-deck' ); ?></h1>

            <!-- Stats -->
            <div class="osint-deck-tld-stats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin:20px 0;">
                <div class="stat-card" style="background:white;border:1px solid #ccd0d4;border-radius:4px;padding:20px;text-align:center;">
                    <h3 style="font-size:48px;margin:0 0 10px 0;color:#2271b1;"><?php echo esc_html( $stats['iana_count'] ); ?></h3>
                    <p style="margin:0;color:#646970;"><?php _e( 'TLDs de IANA', 'osint-deck' ); ?></p>
                </div>
                <div class="stat-card" style="background:white;border:1px solid #ccd0d4;border-radius:4px;padding:20px;text-align:center;">
                    <h3 style="font-size:48px;margin:0 0 10px 0;color:#2271b1;"><?php echo esc_html( $stats['custom_count'] ); ?></h3>
                    <p style="margin:0;color:#646970;"><?php _e( 'TLDs Custom', 'osint-deck' ); ?></p>
                </div>
                <div class="stat-card" style="background:white;border:1px solid #ccd0d4;border-radius:4px;padding:20px;text-align:center;">
                    <h3 style="font-size:48px;margin:0 0 10px 0;color:#2271b1;"><?php echo esc_html( $stats['total_count'] ); ?></h3>
                    <p style="margin:0;color:#646970;"><?php _e( 'Total', 'osint-deck' ); ?></p>
                </div>
            </div>

            <?php if ( $stats['last_update'] ) : ?>
                <p>
                    <strong><?php _e( 'Última actualización:', 'osint-deck' ); ?></strong>
                    <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $stats['last_update'] ) ); ?>
                </p>
            <?php endif; ?>

            <!-- Update from IANA -->
            <div style="background:white;border:1px solid #ccd0d4;border-radius:4px;padding:20px;margin:20px 0;">
                <h2><?php _e( 'Actualizar desde IANA', 'osint-deck' ); ?></h2>
                <p><?php _e( 'Descarga la lista oficial de TLDs desde IANA. Esto se hace automáticamente cada semana, pero podés forzar la actualización manualmente.', 'osint-deck' ); ?></p>
                <form method="post">
                    <?php wp_nonce_field( 'osint_deck_tld_update' ); ?>
                    <p>
                        <input type="submit" name="osint_deck_tld_update" class="button button-primary" value="<?php _e( 'Actualizar desde IANA', 'osint-deck' ); ?>">
                    </p>
                </form>
                <p class="description">
                    <strong><?php _e( 'URL:', 'osint-deck' ); ?></strong>
                    <a href="https://data.iana.org/TLD/tlds-alpha-by-domain.txt" target="_blank">
                        https://data.iana.org/TLD/tlds-alpha-by-domain.txt
                    </a>
                </p>
            </div>

            <!-- Add Custom TLD -->
            <div style="background:white;border:1px solid #ccd0d4;border-radius:4px;padding:20px;margin:20px 0;">
                <h2><?php _e( 'Añadir TLD Custom', 'osint-deck' ); ?></h2>
                <p><?php _e( 'Añadí TLDs personalizados que no están en la lista de IANA (ej: TLDs internos, de desarrollo, etc.)', 'osint-deck' ); ?></p>
                <form method="post">
                    <?php wp_nonce_field( 'osint_deck_tld_add' ); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="custom_tld"><?php _e( 'TLD', 'osint-deck' ); ?></label></th>
                            <td>
                                <input type="text" name="custom_tld" id="custom_tld" class="regular-text" placeholder="local, internal, test" required>
                                <p class="description"><?php _e( 'Sin el punto inicial (ej: "local" en lugar de ".local")', 'osint-deck' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    <p>
                        <input type="submit" name="osint_deck_tld_add" class="button" value="<?php _e( 'Añadir TLD', 'osint-deck' ); ?>">
                    </p>
                </form>
            </div>

            <!-- Custom TLDs List -->
            <?php if ( ! empty( $custom_tlds ) ) : ?>
                <div style="background:white;border:1px solid #ccd0d4;border-radius:4px;padding:20px;margin:20px 0;">
                    <h2><?php _e( 'TLDs Custom', 'osint-deck' ); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e( 'TLD', 'osint-deck' ); ?></th>
                                <th><?php _e( 'Acciones', 'osint-deck' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $custom_tlds as $tld ) : ?>
                                <tr>
                                    <td><code>.<?php echo esc_html( $tld ); ?></code></td>
                                    <td>
                                        <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=osint-deck-tlds&action=delete&tld=' . urlencode( $tld ) ), 'delete_tld_' . $tld ); ?>" 
                                           onclick="return confirm('¿Estás seguro?')">
                                            <?php _e( 'Eliminar', 'osint-deck' ); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Info -->
            <div style="background:#f0f6fc;border:1px solid #c3d7ef;border-radius:4px;padding:20px;margin:20px 0;">
                <h3><?php _e( 'ℹ️ Información', 'osint-deck' ); ?></h3>
                <ul>
                    <li><?php _e( 'Los TLDs de IANA se actualizan automáticamente cada semana', 'osint-deck' ); ?></li>
                    <li><?php _e( 'Los TLDs custom se mantienen incluso después de actualizar desde IANA', 'osint-deck' ); ?></li>
                    <li><?php _e( 'La validación de dominios usa esta lista para evitar falsos positivos', 'osint-deck' ); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Handle update from IANA
     *
     * @return void
     */
    private function handle_update_from_iana() {
        $result = $this->tld_manager->update_from_iana();

        if ( $result['success'] ) {
            add_settings_error( 'osint_deck', 'tld_updated', $result['message'], 'success' );
        } else {
            add_settings_error( 'osint_deck', 'tld_update_failed', $result['message'], 'error' );
        }

        settings_errors( 'osint_deck' );
    }

    /**
     * Handle add custom TLD
     *
     * @return void
     */
    private function handle_add_custom() {
        $tld = isset( $_POST['custom_tld'] ) ? sanitize_text_field( $_POST['custom_tld'] ) : '';

        if ( empty( $tld ) ) {
            add_settings_error( 'osint_deck', 'tld_empty', __( 'El TLD no puede estar vacío', 'osint-deck' ), 'error' );
            settings_errors( 'osint_deck' );
            return;
        }

        $result = $this->tld_manager->add_custom( $tld );

        if ( $result ) {
            add_settings_error( 'osint_deck', 'tld_added', sprintf( __( 'TLD "%s" añadido', 'osint-deck' ), $tld ), 'success' );
        } else {
            add_settings_error( 'osint_deck', 'tld_exists', __( 'El TLD ya existe', 'osint-deck' ), 'error' );
        }
        settings_errors( 'osint_deck' );
    }

    /**
     * Handle delete custom TLD
     *
     * @param string $tld TLD to delete.
     */
    private function handle_delete_custom( $tld ) {
        $result = $this->tld_manager->remove_custom( $tld );

        if ( $result ) {
            add_settings_error( 'osint_deck', 'tld_deleted', sprintf( __( 'TLD "%s" eliminado', 'osint-deck' ), $tld ), 'success' );
        } else {
            add_settings_error( 'osint_deck', 'tld_delete_error', __( 'Error al eliminar TLD', 'osint-deck' ), 'error' );
        }
        settings_errors( 'osint_deck' );
    }
}
