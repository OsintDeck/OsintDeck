<?php
/**
 * Tools Manager - Admin interface for tools
 *
 * @package OsintDeck
 */

namespace OsintDeck\Presentation\Admin;

use OsintDeck\Domain\Repository\ToolRepositoryInterface;
use OsintDeck\Domain\Repository\CategoryRepositoryInterface;

/**
 * Class ToolsManager
 * 
 * Handles tools CRUD in admin
 */
class ToolsManager {

    /**
     * Tool Repository
     *
     * @var ToolRepositoryInterface
     */
    private $tool_repository;

    /**
     * Category Repository
     *
     * @var CategoryRepositoryInterface
     */
    private $category_repository;

    /**
     * Constructor
     *
     * @param ToolRepositoryInterface $tool_repository Tool Repository.
     * @param CategoryRepositoryInterface $category_repository Category Repository.
     */
    public function __construct( ToolRepositoryInterface $tool_repository, CategoryRepositoryInterface $category_repository ) {
        $this->tool_repository = $tool_repository;
        $this->category_repository = $category_repository;
    }

    /**
     * Render tools page
     *
     * @return void
     */
    public function render() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

        // Handle form submission
        if ( isset( $_POST['osint_deck_tool_submit'] ) ) {
            check_admin_referer( 'osint_deck_tool' );
            $this->handle_save();
            $action = 'list';
        }

        // Handle delete
        if ( $action === 'delete' && $id ) {
            check_admin_referer( 'delete_tool_' . $id );
            $this->tool_repository->delete_tool( $id );
            add_settings_error( 'osint_deck', 'tool_deleted', __( 'Herramienta eliminada', 'osint-deck' ), 'success' );
            settings_errors( 'osint_deck' );
            $action = 'list';
        }

        // Handle quick status update
        if ( $action === 'quick_status' && $id ) {
            check_admin_referer( 'quick_status_' . $id );
            $status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
            if ( in_array( $status, ['unaudited', 'ok', 'blocked'] ) ) {
                $tool = $this->tool_repository->get_tool_by_id( $id );
                if ( $tool ) {
                    $tool['preview_status'] = $status;
                    $this->tool_repository->save_tool( $tool );
                    add_settings_error( 'osint_deck', 'status_updated', sprintf( __( 'Estado actualizado a: %s', 'osint-deck' ), $status ), 'success' );
                }
            }
            settings_errors( 'osint_deck' );
            $action = 'list';
        }

        switch ( $action ) {
            case 'add':
                $this->render_form();
                break;
            case 'edit':
                $this->render_form( $id );
                break;
            case 'view':
                $this->render_view( $id );
                break;
            default:
                $this->render_list();
        }
    }

    /**
     * Render tools list
     *
     * @return void
     */
    private function render_list() {
        $tools = $this->tool_repository->get_all_tools();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e( 'Herramientas OSINT', 'osint-deck' ); ?></h1>
            <a href="<?php echo admin_url( 'admin.php?page=osint-deck-import-export' ); ?>" class="page-title-action">
                <?php _e( 'Importar Herramienta', 'osint-deck' ); ?>
            </a>
            <hr class="wp-header-end">

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Nombre', 'osint-deck' ); ?></th>
                        <th><?php _e( 'Categoría', 'osint-deck' ); ?></th>
                        <th><?php _e( 'Preview', 'osint-deck' ); ?></th>
                        <th><?php _e( 'Cards', 'osint-deck' ); ?></th>
                        <th><?php _e( 'Clicks', 'osint-deck' ); ?></th>
                        <th><?php _e( 'Reportes', 'osint-deck' ); ?></th>
                        <th><?php _e( 'Badges', 'osint-deck' ); ?></th>
                        <th><?php _e( 'Acciones', 'osint-deck' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $tools ) ) : ?>
                        <tr>
                            <td colspan="6">
                                <?php _e( 'No hay herramientas.', 'osint-deck' ); ?>
                                <a href="<?php echo admin_url( 'admin.php?page=osint-deck-import-export' ); ?>">
                                    <?php _e( 'Importar herramientas', 'osint-deck' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $tools as $tool ) : ?>
                            <?php
                            $cards_count = isset( $tool['cards'] ) ? count( $tool['cards'] ) : 0;
                            $clicks = isset( $tool['stats']['clicks'] ) ? $tool['stats']['clicks'] : 0;
                            $badges = isset( $tool['badges'] ) ? $tool['badges'] : array();
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $tool['name'] ); ?></strong>
                                    <?php if ( ! empty( $tool['favicon'] ) ) : ?>
                                        <br><img src="<?php echo esc_url( $tool['favicon'] ); ?>" width="16" height="16" alt="">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $tool['category'] ?? '-' ); ?></td>
                                <td>
                                    <?php 
                                    $status = $tool['preview_status'] ?? 'unaudited';
                                    $status_labels = array(
                                        'unaudited' => __( 'No auditado', 'osint-deck' ),
                                        'ok'        => __( 'OK', 'osint-deck' ),
                                        'blocked'   => __( 'Bloqueado', 'osint-deck' ),
                                    );
                                    $status_colors = array(
                                        'unaudited' => '#72777c',
                                        'ok'        => '#46b450',
                                        'blocked'   => '#dc3232',
                                    );
                                    
                                    // Primary URL for preview
                                    $preview_url = $tool['url'] ?? '';
                                    if ( empty( $preview_url ) && ! empty( $tool['cards'][0]['url'] ) ) {
                                        $preview_url = $tool['cards'][0]['url'];
                                    }
                                    ?>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <?php if ( ! empty( $preview_url ) ) : ?>
                                            <a href="javascript:void(0);" class="button button-small osint-preview-trigger" data-url="<?php echo esc_url( $preview_url ); ?>" data-title="<?php echo esc_attr( $tool['name'] ); ?>" title="<?php _e( 'Probar en Modal (Admin)', 'osint-deck' ); ?>">
                                                <span class="dashicons dashicons-visibility" style="line-height: 26px;"></span>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <div style="display: flex; flex-direction: column;">
                                            <span style="color: <?php echo esc_attr( $status_colors[$status] ?? 'grey' ); ?>; font-weight: bold; font-size: 11px;">
                                                <?php echo esc_html( $status_labels[$status] ?? $status ); ?>
                                            </span>
                                            
                                            <div class="row-actions visible" style="position: static; padding: 2px 0 0;">
                                                <?php if ( $status !== 'ok' ) : ?>
                                                    <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=osint-deck-tools&action=quick_status&status=ok&id=' . $tool['_db_id'] ), 'quick_status_' . $tool['_db_id'] ); ?>" style="color: #46b450;">OK</a>
                                                <?php endif; ?>
                                                <?php if ( $status !== 'ok' && $status !== 'blocked' ) : ?> | <?php endif; ?>
                                                <?php if ( $status !== 'blocked' ) : ?>
                                                    <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=osint-deck-tools&action=quick_status&status=blocked&id=' . $tool['_db_id'] ), 'quick_status_' . $tool['_db_id'] ); ?>" style="color: #dc3232;">Block</a>
                                                <?php endif; ?>
                                                <?php if ( $status !== 'unaudited' ) : ?>
                                                    | <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=osint-deck-tools&action=quick_status&status=unaudited&id=' . $tool['_db_id'] ), 'quick_status_' . $tool['_db_id'] ); ?>" style="color: #72777c;">Reset</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo esc_html( $cards_count ); ?></td>
                                <td><?php echo esc_html( $clicks ); ?></td>
                                <td><?php echo esc_html( $tool['stats']['reports'] ?? 0 ); ?></td>
                                <td>
                                    <?php if ( ! empty( $badges['popular'] ) ) : ?>
                                        <span class="dashicons dashicons-star-filled" title="Popular"></span>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $badges['new'] ) ) : ?>
                                        <span class="dashicons dashicons-plus-alt" title="Nuevo"></span>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $badges['verified'] ) ) : ?>
                                        <span class="dashicons dashicons-yes-alt" title="Verificado"></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url( 'admin.php?page=osint-deck-tools&action=view&id=' . $tool['_db_id'] ); ?>">
                                        <?php _e( 'Ver', 'osint-deck' ); ?>
                                    </a>
                                    |
                                    <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=osint-deck-tools&action=delete&id=' . $tool['_db_id'] ), 'delete_tool_' . $tool['_db_id'] ); ?>" 
                                       onclick="return confirm('¿Estás seguro?')">
                                        <?php _e( 'Eliminar', 'osint-deck' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Admin Preview Modal -->
        <div id="osint-admin-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:99999; justify-content:center; align-items:center;">
            <div style="background:#fff; width:90%; height:90%; display:flex; flex-direction:column; border-radius:4px; box-shadow:0 4px 12px rgba(0,0,0,0.5);">
                <div style="padding:10px 15px; border-bottom:1px solid #ddd; display:flex; justify-content:space-between; align-items:center; background:#f0f0f1;">
                    <h3 id="osint-modal-title" style="margin:0;"><?php _e( 'Vista Previa', 'osint-deck' ); ?></h3>
                    <div style="display:flex; gap:10px;">
                        <a id="osint-modal-external" href="#" target="_blank" class="button button-secondary"><?php _e( 'Abrir en nueva pestaña', 'osint-deck' ); ?></a>
                        <button id="osint-modal-close" class="button button-link"><span class="dashicons dashicons-no-alt" style="font-size:24px;"></span></button>
                    </div>
                </div>
                <div style="flex:1; position:relative; background:#fafafa;">
                    <iframe id="osint-modal-iframe" src="" style="width:100%; height:100%; border:none; display:block;" sandbox="allow-same-origin allow-scripts allow-forms allow-popups"></iframe>
                    <div id="osint-modal-loading" style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:#666;">
                        <span class="spinner is-active" style="float:none; margin:0;"></span> <?php _e( 'Cargando...', 'osint-deck' ); ?>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Move modal to body to avoid z-index issues
            if ($('#osint-admin-modal').parent().is('.wrap') || $('#osint-admin-modal').parent().is('.postbox')) {
                $('#osint-admin-modal').appendTo('body');
            }

            const modal = $('#osint-admin-modal');
            const iframe = $('#osint-modal-iframe');
            const title = $('#osint-modal-title');
            const external = $('#osint-modal-external');
            const closeBtn = $('#osint-modal-close');
            const loading = $('#osint-modal-loading');
            
            // Open Modal using delegation
            $(document).on('click', '.osint-preview-trigger', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const btn = $(this);
                const url = btn.data('url');
                const name = btn.data('title');
                
                if (!url) return;

                title.text(name || 'Vista Previa');
                external.attr('href', url);
                
                // Show modal and loading
                modal.css('display', 'flex');
                loading.show();
                iframe.css('opacity', '0');
                
                // Load iframe
                iframe.attr('src', url);
                iframe.on('load', function() {
                    loading.hide();
                    iframe.css('opacity', '1');
                });
            });

            // Close Modal
            function closeModal() {
                modal.hide();
                iframe.attr('src', 'about:blank');
            }

            closeBtn.on('click', closeModal);
            
            modal.on('click', function(e) {
                if (e.target === this) closeModal();
            });

            // ESC key to close
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && modal.is(':visible')) {
                    closeModal();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Render tool view
     *
     * @param int $id Tool ID.
     * @return void
     */
    private function render_view( $id ) {
        $tool = $this->tool_repository->get_tool_by_id( $id );

        if ( ! $tool ) {
            echo '<div class="wrap"><p>' . __( 'Herramienta no encontrada', 'osint-deck' ) . '</p></div>';
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $tool['name'] ); ?></h1>
            <a href="<?php echo admin_url( 'admin.php?page=osint-deck-tools' ); ?>" class="page-title-action">
                <?php _e( '← Volver', 'osint-deck' ); ?>
            </a>
            <hr>

            <h2><?php _e( 'Información General', 'osint-deck' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php _e( 'Nombre', 'osint-deck' ); ?></th>
                    <td><?php echo esc_html( $tool['name'] ); ?></td>
                </tr>
                <tr>
                    <th><?php _e( 'Favicon', 'osint-deck' ); ?></th>
                    <td>
                        <?php if ( ! empty( $tool['favicon'] ) ) : ?>
                            <img src="<?php echo esc_url( $tool['favicon'] ); ?>" width="32" height="32" alt="">
                            <br><?php echo esc_html( $tool['favicon'] ); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e( 'Categoría', 'osint-deck' ); ?></th>
                    <td><?php echo esc_html( $tool['category'] ?? '-' ); ?></td>
                </tr>
                <tr>
                    <th><?php _e( 'Estado Preview', 'osint-deck' ); ?></th>
                    <td>
                        <?php 
                        $status = $tool['preview_status'] ?? 'unaudited';
                        $status_labels = array(
                            'unaudited' => __( 'No auditado', 'osint-deck' ),
                            'ok'        => __( 'OK', 'osint-deck' ),
                            'blocked'   => __( 'Bloqueado', 'osint-deck' ),
                        );
                        echo esc_html( $status_labels[$status] ?? $status ); 
                        ?>
                    </td>
                </tr>
                <?php if ( ! empty( $tool['tags_global'] ) ) : ?>
                <tr>
                    <th><?php _e( 'Tags', 'osint-deck' ); ?></th>
                    <td><?php echo esc_html( implode( ', ', $tool['tags_global'] ) ); ?></td>
                </tr>
                <?php endif; ?>
            </table>
            
            <!-- Cards logic and edit form would go here -->
            <p>
                <a href="<?php echo admin_url( 'admin.php?page=osint-deck-tools&action=edit&id=' . $tool['_db_id'] ); ?>" class="button button-primary">
                    <?php _e( 'Editar Herramienta', 'osint-deck' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Render add/edit form
     * 
     * @param int $id Tool ID.
     */
    private function render_form( $id = 0 ) {
        // UX: Check if categories exist
        $categories = $this->category_repository->get_all_categories();
        
        if ( empty( $categories ) ) {
            ?>
            <div class="wrap">
                <h1><?php echo $id ? __( 'Editar Herramienta', 'osint-deck' ) : __( 'Nueva Herramienta', 'osint-deck' ); ?></h1>
                <div class="notice notice-error">
                    <p>
                        <?php _e( 'No existen categorías creadas. Debes crear al menos una categoría antes de gestionar herramientas.', 'osint-deck' ); ?>
                        <a href="<?php echo admin_url( 'admin.php?page=osint-deck-categories&action=add' ); ?>" class="button button-secondary">
                            <?php _e( 'Crear Categoría', 'osint-deck' ); ?>
                        </a>
                    </p>
                </div>
            </div>
            <?php
            return;
        }

        $tool = $id ? $this->tool_repository->get_tool_by_id( $id ) : null;
        $is_edit = ! empty( $tool );

        $defaults = array(
            'name'        => '',
            'description' => '',
            'category'    => '',
            'url'         => '',
            'favicon'     => '',
            'tags_global' => array(),
            'preview_status' => 'unaudited',
        );

        $data = $is_edit ? wp_parse_args( $tool, $defaults ) : $defaults;
        
        ?>
        <div class="wrap">
            <h1><?php echo $is_edit ? __( 'Editar Herramienta', 'osint-deck' ) : __( 'Nueva Herramienta', 'osint-deck' ); ?></h1>
            
            <form method="post" action="<?php echo admin_url( 'admin.php?page=osint-deck-tools' ); ?>">
                <?php wp_nonce_field( 'osint_deck_tool' ); ?>
                <input type="hidden" name="tool_id" value="<?php echo esc_attr( $id ); ?>">
                
                <table class="form-table">
                    <tr>
                        <th><label for="name"><?php _e( 'Nombre', 'osint-deck' ); ?> *</label></th>
                        <td>
                            <input type="text" name="name" id="name" value="<?php echo esc_attr( $data['name'] ); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="category"><?php _e( 'Categoría', 'osint-deck' ); ?> *</label></th>
                        <td>
                            <select name="category" id="category" required class="regular-text">
                                <option value=""><?php _e( '-- Seleccionar Categoría --', 'osint-deck' ); ?></option>
                                <?php foreach ( $categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat['code'] ); ?>" <?php selected( $data['category'], $cat['code'] ); ?>>
                                        <?php echo esc_html( $cat['name'] ); ?> (<?php echo esc_html( $cat['code'] ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e( 'Selecciona la categoría a la que pertenece esta herramienta.', 'osint-deck' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="preview_status"><?php _e( 'Estado Preview', 'osint-deck' ); ?></label></th>
                        <td>
                            <select name="preview_status" id="preview_status" class="regular-text">
                                <option value="unaudited" <?php selected( $data['preview_status'], 'unaudited' ); ?>><?php _e( 'No auditado', 'osint-deck' ); ?></option>
                                <option value="ok" <?php selected( $data['preview_status'], 'ok' ); ?>><?php _e( 'OK', 'osint-deck' ); ?></option>
                                <option value="blocked" <?php selected( $data['preview_status'], 'blocked' ); ?>><?php _e( 'Bloqueado', 'osint-deck' ); ?></option>
                            </select>
                            <p class="description"><?php _e( 'Estado de la previsualización en iframe.', 'osint-deck' ); ?></p>
                        </td>
                    </tr>
                    <!-- More fields as needed -->
                </table>

                <p class="submit">
                    <input type="submit" name="osint_deck_tool_submit" class="button button-primary" value="<?php _e( 'Guardar Herramienta', 'osint-deck' ); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle save
     */
    private function handle_save() {
        $id = isset( $_POST['tool_id'] ) ? intval( $_POST['tool_id'] ) : 0;
        $category_code = sanitize_text_field( $_POST['category'] );

        // Validation: Check if category exists
        if ( empty( $category_code ) ) {
            add_settings_error( 'osint_deck', 'tool_error', __( 'La categoría es obligatoria.', 'osint-deck' ), 'error' );
            settings_errors( 'osint_deck' );
            return;
        }

        if ( ! $this->category_repository->get_category_by_code( $category_code ) ) {
            add_settings_error( 'osint_deck', 'tool_error', __( 'La categoría seleccionada no existe.', 'osint-deck' ), 'error' );
            settings_errors( 'osint_deck' );
            return;
        }
        
        $data = array(
            '_db_id'      => $id, // ID for update
            'name'        => sanitize_text_field( $_POST['name'] ),
            'category'    => $category_code,
            'preview_status' => isset( $_POST['preview_status'] ) ? sanitize_text_field( $_POST['preview_status'] ) : 'unaudited',
            // Add other fields
        );

        // If ID is 0, unset it so it inserts
        if ( $id === 0 ) {
            unset( $data['_db_id'] );
        }

        $result = $this->tool_repository->save_tool( $data );

        if ( $result ) {
            add_settings_error( 'osint_deck', 'tool_saved', __( 'Herramienta guardada', 'osint-deck' ), 'success' );
        } else {
            add_settings_error( 'osint_deck', 'tool_error', __( 'Error al guardar herramienta', 'osint-deck' ), 'error' );
        }
        settings_errors( 'osint_deck' );
    }
}
