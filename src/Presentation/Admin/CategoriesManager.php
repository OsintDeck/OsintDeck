<?php
/**
 * Categories Manager - Admin interface for categories
 *
 * @package OsintDeck
 */

namespace OsintDeck\Presentation\Admin;

use OsintDeck\Domain\Repository\CategoryRepositoryInterface;

/**
 * Class CategoriesManager
 * 
 * Handles categories CRUD in admin
 */
class CategoriesManager {

    /**
     * Category Repository
     *
     * @var CategoryRepositoryInterface
     */
    private $category_repository;

    /**
     * Constructor
     *
     * @param CategoryRepositoryInterface $category_repository Category Repository.
     */
    public function __construct( CategoryRepositoryInterface $category_repository ) {
        $this->category_repository = $category_repository;
    }

    /**
     * Render categories page
     *
     * @return void
     */
    public function render() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

        // Handle form submissions
        if ( isset( $_POST['osint_deck_category_submit'] ) ) {
            check_admin_referer( 'osint_deck_category' );
            $this->handle_save();
            $action = 'list';
        }

        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && $id ) {
            check_admin_referer( 'delete_category_' . $id );
            $this->handle_delete( $id );
            $action = 'list';
        }

        switch ( $action ) {
            case 'add':
                $this->render_form();
                break;
            case 'edit':
                $this->render_form( $id );
                break;
            default:
                $this->render_list();
        }
    }

    /**
     * Render categories list
     *
     * @return void
     */
    private function render_list() {
        $categories = $this->category_repository->get_all_categories();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e( 'Categorías', 'osint-deck' ); ?></h1>
            <a href="<?php echo admin_url( 'admin.php?page=osint-deck-categories&action=add' ); ?>" class="page-title-action">
                <?php _e( 'Añadir Nueva', 'osint-deck' ); ?>
            </a>
            <hr class="wp-header-end">

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Código', 'osint-deck' ); ?></th>
                        <th><?php _e( 'Grupo', 'osint-deck' ); ?></th>
                        <th><?php _e( 'Tipo', 'osint-deck' ); ?></th>
                        <th><?php _e( 'Label', 'osint-deck' ); ?></th>
                        <th><?php _e( 'Color', 'osint-deck' ); ?></th>
                        <th><?php _e( 'Acciones', 'osint-deck' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $categories ) ) : ?>
                        <tr>
                            <td colspan="6"><?php _e( 'No hay categorías', 'osint-deck' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $categories as $cat ) : ?>
                            <tr>
                                <td><code><?php echo esc_html( $cat['code'] ); ?></code></td>
                                <td><?php echo esc_html( $cat['group_name'] ); ?></td>
                                <td><?php echo esc_html( $cat['type'] ); ?></td>
                                <td><?php echo esc_html( $cat['label'] ); ?></td>
                                <td>
                                    <span style="display:inline-block;width:20px;height:20px;background-color:<?php echo esc_attr( $cat['color'] ); ?>;border:1px solid #ccc;border-radius:3px;"></span>
                                    <?php echo esc_html( $cat['color'] ); ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url( 'admin.php?page=osint-deck-categories&action=edit&id=' . $cat['id'] ); ?>">
                                        <?php _e( 'Editar', 'osint-deck' ); ?>
                                    </a>
                                    |
                                    <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=osint-deck-categories&action=delete&id=' . $cat['id'] ), 'delete_category_' . $cat['id'] ); ?>" 
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
        <?php
    }

    /**
     * Render add/edit form
     *
     * @param int $id Category ID for edit, 0 for add.
     * @return void
     */
    private function render_form( $id = 0 ) {
        $category = $id ? $this->category_repository->get_category_by_id( $id ) : null;
        $is_edit = ! empty( $category );

        $defaults = array(
            'code'        => '',
            'group_name'  => '',
            'type'        => '',
            'label'       => '',
            'icon'        => '',
            'color'       => '#475569',
            'descripcion' => '',
            'fase_osint'  => array(),
            'data_types'  => array(),
        );

        $data = $is_edit ? wp_parse_args( $category, $defaults ) : $defaults;
        ?>
        <div class="wrap">
            <h1><?php echo $is_edit ? __( 'Editar Categoría', 'osint-deck' ) : __( 'Nueva Categoría', 'osint-deck' ); ?></h1>
            
            <form method="post" action="<?php echo admin_url( 'admin.php?page=osint-deck-categories' ); ?>">
                <?php wp_nonce_field( 'osint_deck_category' ); ?>
                <input type="hidden" name="category_id" value="<?php echo esc_attr( $id ); ?>">
                
                <table class="form-table">
                    <tr>
                        <th><label for="code"><?php _e( 'Código', 'osint-deck' ); ?> *</label></th>
                        <td>
                            <input type="text" name="code" id="code" value="<?php echo esc_attr( $data['code'] ); ?>" class="regular-text" required>
                            <p class="description"><?php _e( 'Ej: INFRA__DNS__LOOKUP', 'osint-deck' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="group_name"><?php _e( 'Grupo', 'osint-deck' ); ?> *</label></th>
                        <td>
                            <input type="text" name="group_name" id="group_name" value="<?php echo esc_attr( $data['group_name'] ); ?>" class="regular-text" required>
                            <p class="description"><?php _e( 'Ej: Infraestructura, Seguridad, Correo', 'osint-deck' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="type"><?php _e( 'Tipo', 'osint-deck' ); ?> *</label></th>
                        <td>
                            <input type="text" name="type" id="type" value="<?php echo esc_attr( $data['type'] ); ?>" class="regular-text" required>
                            <p class="description"><?php _e( 'Ej: DNS Lookup, WHOIS, MX Records', 'osint-deck' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="label"><?php _e( 'Label', 'osint-deck' ); ?> *</label></th>
                        <td>
                            <input type="text" name="label" id="label" value="<?php echo esc_attr( $data['label'] ); ?>" class="regular-text" required>
                            <p class="description"><?php _e( 'Nombre visible para el usuario', 'osint-deck' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="icon"><?php _e( 'Icono', 'osint-deck' ); ?></label></th>
                        <td>
                            <input type="text" name="icon" id="icon" value="<?php echo esc_attr( $data['icon'] ); ?>" class="regular-text">
                            <p class="description"><?php _e( 'Nombre del icono (ej: server, mail, shield)', 'osint-deck' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="color"><?php _e( 'Color', 'osint-deck' ); ?></label></th>
                        <td>
                            <input type="color" name="color" id="color" value="<?php echo esc_attr( $data['color'] ); ?>">
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="osint_deck_category_submit" class="button button-primary" value="<?php _e( 'Guardar Categoría', 'osint-deck' ); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle save
     */
    private function handle_save() {
        $id = isset( $_POST['category_id'] ) ? intval( $_POST['category_id'] ) : 0;
        
        $data = array(
            'id'          => $id,
            'code'        => sanitize_text_field( $_POST['code'] ),
            'group_name'  => sanitize_text_field( $_POST['group_name'] ),
            'type'        => sanitize_text_field( $_POST['type'] ),
            'label'       => sanitize_text_field( $_POST['label'] ),
            'icon'        => sanitize_text_field( $_POST['icon'] ),
            'color'       => sanitize_hex_color( $_POST['color'] ),
        );

        $result = $this->category_repository->save_category( $data );

        if ( $result ) {
            add_settings_error( 'osint_deck', 'category_saved', __( 'Categoría guardada', 'osint-deck' ), 'success' );
        } else {
            add_settings_error( 'osint_deck', 'category_error', __( 'Error al guardar categoría', 'osint-deck' ), 'error' );
        }
        settings_errors( 'osint_deck' );
    }

    /**
     * Handle delete
     *
     * @param int $id Category ID.
     */
    private function handle_delete( $id ) {
        $result = $this->category_repository->delete_category( $id );

        if ( $result ) {
            add_settings_error( 'osint_deck', 'category_deleted', __( 'Categoría eliminada', 'osint-deck' ), 'success' );
        } else {
            add_settings_error( 'osint_deck', 'category_error', __( 'Error al eliminar categoría', 'osint-deck' ), 'error' );
        }
        settings_errors( 'osint_deck' );
    }
}
