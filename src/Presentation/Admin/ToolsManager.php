<?php
/**
 * Tools Manager - Admin interface for tools
 *
 * @package OsintDeck
 */

namespace OsintDeck\Presentation\Admin;

use OsintDeck\Domain\Repository\ToolRepositoryInterface;
use OsintDeck\Domain\Repository\CategoryRepositoryInterface;
use OsintDeck\Infrastructure\Service\IconManager;
use OsintDeck\Infrastructure\Service\Logger;

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
     * Icon Manager
     *
     * @var IconManager
     */
    private $icon_manager;

    /**
     * Logger
     *
     * @var Logger|null
     */
    private $logger;

    /**
     * Constructor
     *
     * @param ToolRepositoryInterface $tool_repository Tool Repository.
     * @param CategoryRepositoryInterface $category_repository Category Repository.
     * @param Logger|null $logger Logger.
     */
    public function __construct( ToolRepositoryInterface $tool_repository, CategoryRepositoryInterface $category_repository, Logger $logger = null ) {
        $this->tool_repository = $tool_repository;
        $this->category_repository = $category_repository;
        $this->logger = $logger;
        $this->icon_manager = new IconManager( $logger );
    }

    /**
     * Render tools page
     *
     * @return void
     */
    public function render() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

        // Handle form submission (manual)
        if ( isset( $_POST['osint_deck_tool_submit'] ) ) {
            check_admin_referer( 'osint_deck_tool' );
            $this->handle_save();
            $action = 'list';
        }

        // Nueva herramienta desde JSON (una sola)
        if ( isset( $_POST['osint_deck_tool_json_submit'] ) ) {
            check_admin_referer( 'osint_deck_tool_json' );
            if ( $this->handle_save_from_json() ) {
                $action = 'list';
            } else {
                $action = 'add';
            }
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
        $categories = $this->category_repository->get_all_categories();

        $category_map = array();
        foreach ( $categories as $c ) {
            $code  = $c['code'] ?? '';
            $label = $c['label'] ?? $c['name'] ?? $code;
            if ( $code ) {
                $category_map[ $code ] = $label;
            }
        }

        // Get filter parameters
        $filter_search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $filter_category = isset( $_GET['category'] ) ? sanitize_text_field( $_GET['category'] ) : '';
        $filter_status = isset( $_GET['preview_status'] ) ? sanitize_text_field( $_GET['preview_status'] ) : '';

        $filter_cat_label = '';
        foreach ( $categories as $c ) {
            if ( ( $c['code'] ?? '' ) === $filter_category ) {
                $filter_cat_label = $c['label'] ?? $c['name'] ?? '';
                break;
            }
        }

        // Apply filters
        if ( ! empty( $filter_search ) || ! empty( $filter_category ) || ! empty( $filter_status ) ) {
            $tools = array_filter( $tools, function( $tool ) use ( $filter_search, $filter_category, $filter_status, $filter_cat_label ) {
                // Search filter (Name)
                if ( ! empty( $filter_search ) ) {
                    if ( stripos( $tool['name'], $filter_search ) === false ) {
                        return false;
                    }
                }

                // Category filter (value = code; tool may store code or label)
                if ( ! empty( $filter_category ) ) {
                    $tool_cat = $tool['category'] ?? '';
                    $match    = ( strcasecmp( $tool_cat, $filter_category ) === 0 )
                        || ( $filter_cat_label && strcasecmp( $tool_cat, $filter_cat_label ) === 0 );

                    if ( ! $match && ! empty( $tool['cards'] ) ) {
                        foreach ( $tool['cards'] as $card ) {
                            $c_cat = $card['category'] ?? '';
                            $c_code = $card['category_code'] ?? '';
                            if ( strcasecmp( $c_cat, $filter_category ) === 0
                                || strcasecmp( (string) $c_code, $filter_category ) === 0
                                || ( $filter_cat_label && strcasecmp( $c_cat, $filter_cat_label ) === 0 ) ) {
                                $match = true;
                                break;
                            }
                        }
                    }

                    if ( ! $match ) {
                        return false;
                    }
                }

                // Status filter
                if ( ! empty( $filter_status ) ) {
                    $status = $tool['preview_status'] ?? 'unaudited';
                    if ( $status !== $filter_status ) {
                        return false;
                    }
                }

                return true;
            });
        }

        // Sorting
        $orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'name';
        $order   = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'asc';

        usort( $tools, function( $a, $b ) use ( $orderby, $order ) {
            $result = 0;
            switch ( $orderby ) {
                case 'name':
                    $result = strcasecmp( $a['name'], $b['name'] );
                    break;
                case 'category':
                    $cat_a = $a['category'] ?? '';
                    if ( empty( $cat_a ) && ! empty( $a['cards'] ) ) {
                        $cats = [];
                        foreach($a['cards'] as $c) if(!empty($c['category'])) $cats[] = $c['category'];
                        if(!empty($cats)) $cat_a = implode(', ', array_unique($cats));
                    }

                    $cat_b = $b['category'] ?? '';
                    if ( empty( $cat_b ) && ! empty( $b['cards'] ) ) {
                        $cats = [];
                        foreach($b['cards'] as $c) if(!empty($c['category'])) $cats[] = $c['category'];
                        if(!empty($cats)) $cat_b = implode(', ', array_unique($cats));
                    }

                    $result = strcasecmp( $cat_a, $cat_b );
                    break;
                case 'preview_status':
                    $status_a = $a['preview_status'] ?? 'unaudited';
                    $status_b = $b['preview_status'] ?? 'unaudited';
                    $result = strcasecmp( $status_a, $status_b );
                    break;
                case 'cards':
                    $count_a = isset( $a['cards'] ) ? count( $a['cards'] ) : 0;
                    $count_b = isset( $b['cards'] ) ? count( $b['cards'] ) : 0;
                    $result = $count_a - $count_b;
                    break;
                case 'clicks':
                    $clicks_a = isset( $a['stats']['clicks'] ) ? $a['stats']['clicks'] : 0;
                    $clicks_b = isset( $b['stats']['clicks'] ) ? $b['stats']['clicks'] : 0;
                    $result = $clicks_a - $clicks_b;
                    break;
                case 'reports':
                    $reports_a = isset( $a['stats']['reports'] ) ? $a['stats']['reports'] : 0;
                    $reports_b = isset( $b['stats']['reports'] ) ? $b['stats']['reports'] : 0;
                    $result = $reports_a - $reports_b;
                    break;
            }
            return ( $order === 'asc' ) ? $result : -$result;
        });

        // Helper for sort links
        $get_sort_link = function( $column, $title ) use ( $orderby, $order ) {
            $new_order = ( $orderby === $column && $order === 'asc' ) ? 'desc' : 'asc';
            $current_url = remove_query_arg( array( 'orderby', 'order' ) );
            $url = add_query_arg( array(
                'orderby' => $column,
                'order'   => $new_order
            ), $current_url );
            
            $icon = '';
            if ( $orderby === $column ) {
                $icon = ( $order === 'asc' ) ? ' <span class="dashicons dashicons-arrow-up-alt2" style="font-size:12px;width:12px;height:12px;vertical-align:text-top;"></span>' : ' <span class="dashicons dashicons-arrow-down-alt2" style="font-size:12px;width:12px;height:12px;vertical-align:text-top;"></span>';
            }
            
            return '<a href="' . esc_url( $url ) . '" style="color:#32373c;text-decoration:none;font-weight:bold;">' . $title . $icon . '</a>';
        };
        ?>
        <div class="wrap osint-deck-admin-wrap osint-deck-tools-page">
            <?php settings_errors( 'osint_deck' ); ?>
            <h1 class="wp-heading-inline"><?php _e( 'Herramientas OSINT', 'osint-deck' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-tools&action=add' ) ); ?>" class="page-title-action">
                <?php _e( 'Añadir nueva', 'osint-deck' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-settings&tab=data' ) ); ?>" class="page-title-action">
                <?php _e( 'Importar / exportar', 'osint-deck' ); ?>
            </a>
            <hr class="wp-header-end">

            <form method="get" class="osint-deck-filters" aria-label="<?php esc_attr_e( 'Filtrar herramientas', 'osint-deck' ); ?>">
                <input type="hidden" name="page" value="osint-deck-tools">

                <div class="osint-deck-filters__row">
                    <div class="osint-deck-filters__field">
                        <label for="filter-search" class="osint-deck-filters__label"><?php _e( 'Buscar', 'osint-deck' ); ?></label>
                        <input type="search" id="filter-search" name="s" class="regular-text" value="<?php echo esc_attr( $filter_search ); ?>" placeholder="<?php esc_attr_e( 'Nombre…', 'osint-deck' ); ?>">
                    </div>

                    <div class="osint-deck-filters__field">
                        <label for="filter-category" class="osint-deck-filters__label"><?php _e( 'Categoría', 'osint-deck' ); ?></label>
                        <select name="category" id="filter-category">
                            <option value=""><?php _e( 'Todas', 'osint-deck' ); ?></option>
                            <?php foreach ( $categories as $cat ) : ?>
                                <?php
                                $cat_code  = isset( $cat['code'] ) ? $cat['code'] : '';
                                $cat_label = isset( $cat['label'] ) ? $cat['label'] : ( isset( $cat['name'] ) ? $cat['name'] : $cat_code );
                                ?>
                                <option value="<?php echo esc_attr( $cat_code ); ?>" <?php selected( $filter_category, $cat_code ); ?>>
                                    <?php echo esc_html( $cat_label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="osint-deck-filters__field">
                        <label for="filter-status" class="osint-deck-filters__label"><?php _e( 'Preview', 'osint-deck' ); ?></label>
                        <select name="preview_status" id="filter-status">
                            <option value=""><?php _e( 'Todos', 'osint-deck' ); ?></option>
                            <option value="ok" <?php selected( $filter_status, 'ok' ); ?>><?php _e( 'OK', 'osint-deck' ); ?></option>
                            <option value="blocked" <?php selected( $filter_status, 'blocked' ); ?>><?php _e( 'Bloqueado', 'osint-deck' ); ?></option>
                            <option value="unaudited" <?php selected( $filter_status, 'unaudited' ); ?>><?php _e( 'No auditado', 'osint-deck' ); ?></option>
                        </select>
                    </div>

                    <div class="osint-deck-filters__actions">
                        <button type="submit" class="button button-primary"><?php _e( 'Aplicar filtros', 'osint-deck' ); ?></button>
                        <?php if ( ! empty( $filter_search ) || ! empty( $filter_category ) || ! empty( $filter_status ) ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-tools' ) ); ?>" class="button"><?php _e( 'Limpiar', 'osint-deck' ); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <div class="osint-deck-table-wrap">
            <table class="wp-list-table widefat fixed striped osint-deck-list-table">
                <thead>
                    <tr>
                        <th class="column-primary osint-deck-col-name"><?php echo $get_sort_link( 'name', __( 'Nombre', 'osint-deck' ) ); ?></th>
                        <th class="osint-deck-col-cat"><?php echo $get_sort_link( 'category', __( 'Categoría', 'osint-deck' ) ); ?></th>
                        <th class="osint-deck-col-preview"><?php echo $get_sort_link( 'preview_status', __( 'Preview', 'osint-deck' ) ); ?></th>
                        <th class="osint-deck-col-num"><?php echo $get_sort_link( 'cards', __( 'Cards', 'osint-deck' ) ); ?></th>
                        <th class="osint-deck-col-num"><?php echo $get_sort_link( 'clicks', __( 'Clicks', 'osint-deck' ) ); ?></th>
                        <th class="osint-deck-col-num"><?php echo $get_sort_link( 'reports', __( 'Reportes', 'osint-deck' ) ); ?></th>
                        <th class="osint-deck-col-badges"><?php _e( 'Badges', 'osint-deck' ); ?></th>
                        <th class="osint-deck-col-actions"><?php _e( 'Acciones', 'osint-deck' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $tools ) ) : ?>
                        <tr>
                            <td colspan="8">
                                <p class="osint-deck-empty">
                                    <?php _e( 'No hay herramientas que coincidan.', 'osint-deck' ); ?>
                                </p>
                                <p>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-settings&tab=data' ) ); ?>" class="button button-primary">
                                        <?php _e( 'Importar desde JSON', 'osint-deck' ); ?>
                                    </a>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-tools&action=add' ) ); ?>" class="button">
                                        <?php _e( 'Crear herramienta simple', 'osint-deck' ); ?>
                                    </a>
                                </p>
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
                                <td class="column-primary osint-deck-col-name">
                                    <div class="osint-deck-tool-name">
                                        <?php if ( ! empty( $tool['favicon'] ) ) : ?>
                                            <span class="osint-deck-tool-name__icon">
                                                <img src="<?php echo esc_url( $tool['favicon'] ); ?>" width="20" height="20" alt="" loading="lazy" decoding="async">
                                            </span>
                                        <?php endif; ?>
                                        <span class="osint-deck-tool-name__label"><strong><?php echo esc_html( $tool['name'] ); ?></strong></span>
                                    </div>
                                </td>
                                <td class="osint-deck-col-cat">
                                    <?php 
                                    $category_display = $tool['category'] ?? '';
                                    if ( empty( $category_display ) && ! empty( $tool['cards'] ) ) {
                                        $card_cats = array();
                                        foreach ( $tool['cards'] as $card ) {
                                            if ( ! empty( $card['category'] ) ) {
                                                $card_cats[] = $card['category'];
                                            } elseif ( ! empty( $card['category_code'] ) ) {
                                                $code = $card['category_code'];
                                                $card_cats[] = isset( $category_map[ $code ] ) ? $category_map[ $code ] : $code;
                                            }
                                        }
                                        if ( ! empty( $card_cats ) ) {
                                            $category_display = implode( ', ', array_unique( $card_cats ) );
                                        }
                                    }
                                    echo esc_html( $category_display ?: '-' ); 
                                    ?>
                                </td>
                                <td class="osint-deck-col-preview">
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

                                    $preview_url = $tool['url'] ?? '';
                                    if ( empty( $preview_url ) && ! empty( $tool['cards'][0]['url'] ) ) {
                                        $preview_url = $tool['cards'][0]['url'];
                                    }
                                    ?>
                                    <div class="osint-deck-status-cell">
                                        <div class="osint-deck-status-cell__top">
                                            <?php if ( ! empty( $preview_url ) ) : ?>
                                                <button type="button" class="button button-small osint-deck-preview-btn osint-preview-trigger" data-url="<?php echo esc_url( $preview_url ); ?>" data-title="<?php echo esc_attr( $tool['name'] ); ?>" title="<?php esc_attr_e( 'Vista previa en modal', 'osint-deck' ); ?>">
                                                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                                    <span class="screen-reader-text"><?php esc_html_e( 'Vista previa', 'osint-deck' ); ?></span>
                                                </button>
                                            <?php else : ?>
                                                <span class="osint-deck-preview-placeholder" title="<?php esc_attr_e( 'Sin URL para previsualizar', 'osint-deck' ); ?>">—</span>
                                            <?php endif; ?>
                                            <span class="osint-deck-status-pill" data-status="<?php echo esc_attr( $status ); ?>" style="--osint-status-color: <?php echo esc_attr( $status_colors[ $status ] ?? '#72777c' ); ?>;">
                                                <?php echo esc_html( $status_labels[ $status ] ?? $status ); ?>
                                            </span>
                                        </div>
                                        <div class="osint-deck-quick-status" role="group" aria-label="<?php esc_attr_e( 'Cambiar estado de vista previa', 'osint-deck' ); ?>">
                                            <?php if ( $status !== 'ok' ) : ?>
                                                <a class="osint-deck-quick-status__link osint-deck-quick-status__ok" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=osint-deck-tools&action=quick_status&status=ok&id=' . $tool['_db_id'] ), 'quick_status_' . $tool['_db_id'] ) ); ?>"><?php esc_html_e( 'OK', 'osint-deck' ); ?></a>
                                            <?php endif; ?>
                                            <?php if ( $status !== 'blocked' ) : ?>
                                                <?php if ( $status !== 'ok' ) : ?><span class="osint-deck-quick-status__sep">·</span><?php endif; ?>
                                                <a class="osint-deck-quick-status__link osint-deck-quick-status__block" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=osint-deck-tools&action=quick_status&status=blocked&id=' . $tool['_db_id'] ), 'quick_status_' . $tool['_db_id'] ) ); ?>"><?php esc_html_e( 'Bloquear', 'osint-deck' ); ?></a>
                                            <?php endif; ?>
                                            <?php if ( $status !== 'unaudited' ) : ?>
                                                <span class="osint-deck-quick-status__sep">·</span>
                                                <a class="osint-deck-quick-status__link osint-deck-quick-status__reset" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=osint-deck-tools&action=quick_status&status=unaudited&id=' . $tool['_db_id'] ), 'quick_status_' . $tool['_db_id'] ) ); ?>"><?php esc_html_e( 'Reset', 'osint-deck' ); ?></a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="osint-deck-col-num"><?php echo esc_html( $cards_count ); ?></td>
                                <td class="osint-deck-col-num"><?php echo esc_html( $clicks ); ?></td>
                                <td class="osint-deck-col-num"><?php echo esc_html( isset( $tool['stats']['reports'] ) ? (int) $tool['stats']['reports'] : 0 ); ?></td>
                                <td class="osint-deck-badges-cell osint-deck-col-badges">
                                    <?php
                                    $has_badge = false;
                                    echo '<div class="osint-deck-badges-row" role="list">';
                                    if ( ! empty( $badges['popular'] ) ) {
                                        $has_badge = true;
                                        echo '<span class="osint-deck-badge-icon" role="listitem"><span class="dashicons dashicons-star-filled" title="' . esc_attr__( 'Popular: destacada en el catálogo.', 'osint-deck' ) . '"></span></span>';
                                    }
                                    if ( ! empty( $badges['new'] ) ) {
                                        $has_badge = true;
                                        echo '<span class="osint-deck-badge-icon" role="listitem"><span class="dashicons dashicons-plus-alt" title="' . esc_attr__( 'Nuevo: etiqueta de novedad en el front.', 'osint-deck' ) . '"></span></span>';
                                    }
                                    if ( ! empty( $badges['verified'] ) ) {
                                        $has_badge = true;
                                        echo '<span class="osint-deck-badge-icon" role="listitem"><span class="dashicons dashicons-yes-alt" title="' . esc_attr__( 'Verificado: revisión manual.', 'osint-deck' ) . '"></span></span>';
                                    }
                                    if ( ! empty( $badges['recommended'] ) ) {
                                        $has_badge = true;
                                        echo '<span class="osint-deck-badge-icon" role="listitem"><span class="dashicons dashicons-heart" title="' . esc_attr__( 'Recomendado: sugerida según contexto.', 'osint-deck' ) . '"></span></span>';
                                    }
                                    if ( ! $has_badge ) {
                                        echo '<span class="osint-deck-muted" role="presentation">—</span>';
                                    }
                                    echo '</div>';
                                    ?>
                                </td>
                                <td class="osint-deck-row-actions osint-deck-col-actions">
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-tools&action=edit&id=' . $tool['_db_id'] ) ); ?>">
                                        <?php _e( 'Editar', 'osint-deck' ); ?>
                                    </a>
                                    <span class="osint-deck-action-sep">|</span>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-tools&action=view&id=' . $tool['_db_id'] ) ); ?>">
                                        <?php _e( 'Ver', 'osint-deck' ); ?>
                                    </a>
                                    <span class="osint-deck-action-sep">|</span>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=osint-deck-tools&action=delete&id=' . $tool['_db_id'] ), 'delete_tool_' . $tool['_db_id'] ) ); ?>"
                                       class="osint-deck-action-delete"
                                       onclick="return confirm('<?php echo esc_js( __( '¿Eliminar esta herramienta?', 'osint-deck' ) ); ?>')">
                                        <?php _e( 'Eliminar', 'osint-deck' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Admin Preview Modal -->
        <div id="osint-admin-modal" class="osint-admin-modal" style="display:none;" aria-hidden="true">
            <div class="osint-admin-modal__dialog">
                <div class="osint-admin-modal__head">
                    <h3 id="osint-modal-title" class="osint-admin-modal__title"><?php _e( 'Vista previa', 'osint-deck' ); ?></h3>
                    <div class="osint-admin-modal__head-actions">
                        <a id="osint-modal-external" href="#" target="_blank" rel="noopener noreferrer" class="button button-secondary"><?php _e( 'Abrir en nueva pestaña', 'osint-deck' ); ?></a>
                        <button type="button" id="osint-modal-close" class="button osint-admin-modal__close" aria-label="<?php esc_attr_e( 'Cerrar', 'osint-deck' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
                    </div>
                </div>
                <div class="osint-admin-modal__body">
                    <iframe id="osint-modal-iframe" title="<?php esc_attr_e( 'Vista previa', 'osint-deck' ); ?>"></iframe>
                    <div id="osint-modal-loading" class="osint-admin-modal__loading">
                        <span class="spinner is-active"></span> <?php _e( 'Cargando…', 'osint-deck' ); ?>
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
                <?php
                $view_primary_url = ! empty( $tool['url'] ) ? $tool['url'] : '';
                if ( '' === $view_primary_url && ! empty( $tool['cards'][0]['url'] ) ) {
                    $view_primary_url = $tool['cards'][0]['url'];
                }
                ?>
                <?php if ( $view_primary_url ) : ?>
                <tr>
                    <th><?php esc_html_e( 'URL principal', 'osint-deck' ); ?></th>
                    <td><a href="<?php echo esc_url( $view_primary_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $view_primary_url ); ?></a></td>
                </tr>
                <?php endif; ?>
                <?php $card_n = isset( $tool['cards'] ) ? count( $tool['cards'] ) : 0; ?>
                <tr>
                    <th><?php esc_html_e( 'Cards en JSON', 'osint-deck' ); ?></th>
                    <td><?php echo esc_html( (string) $card_n ); ?></td>
                </tr>
            </table>
            </div>

            <p class="osint-deck-view-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-tools&action=edit&id=' . (int) $tool['_db_id'] ) ); ?>" class="button button-primary button-large">
                    <?php esc_html_e( 'Editar herramienta', 'osint-deck' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-settings&tab=data' ) ); ?>" class="button button-large"><?php esc_html_e( 'Importar / exportar JSON', 'osint-deck' ); ?></a>
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
            'info'        => array(
                'tipo'     => '',
                'acceso'   => '',
                'licencia' => '',
            ),
        );

        $data = $is_edit ? wp_parse_args( $tool, $defaults ) : $defaults;

        $open_json_tab     = ( ! $is_edit && isset( $_POST['osint_deck_tool_json_submit'] ) );
        $tool_json_prefill = ( $open_json_tab && isset( $_POST['tool_json'] ) ) ? wp_unslash( $_POST['tool_json'] ) : '';
        
        // Ensure nested array exists if merged from DB but empty
        if ( ! isset( $data['info'] ) || ! is_array( $data['info'] ) ) {
            $data['info'] = $defaults['info'];
        }

        // UX: Auto-fill from cards if top-level data is missing
        if ( empty( $data['description'] ) && ! empty( $data['desc'] ) ) {
            $data['description'] = $data['desc'];
        }
        if ( empty( $data['description'] ) && ! empty( $data['cards'][0]['desc'] ) ) {
            $data['description'] = $data['cards'][0]['desc'];
        }
        if ( empty( $data['url'] ) && ! empty( $data['cards'][0]['url'] ) ) {
            $data['url'] = $data['cards'][0]['url'];
        }
        if ( empty( $data['category'] ) && ! empty( $data['cards'][0]['category_code'] ) ) {
            $data['category'] = $data['cards'][0]['category_code'];
        } elseif ( empty( $data['category'] ) && ! empty( $data['cards'][0]['category'] ) ) {
             $data['category'] = $data['cards'][0]['category'];
        }
        if ( empty( $data['tags_global'] ) && ! empty( $data['cards'][0]['tags'] ) ) {
            $data['tags_global'] = $data['cards'][0]['tags'];
        }
        
        ?>
        <div class="wrap osint-deck-admin-wrap osint-deck-tool-form">
            <?php settings_errors( 'osint_deck' ); ?>
            <p class="osint-deck-form-nav">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-tools' ) ); ?>" class="button button-secondary">&larr; <?php esc_html_e( 'Volver al listado', 'osint-deck' ); ?></a>
            </p>
            <h1><?php echo $is_edit ? esc_html( __( 'Editar herramienta', 'osint-deck' ) ) : esc_html( __( 'Nueva herramienta', 'osint-deck' ) ); ?></h1>
            <p class="osint-deck-form-lead">
                <?php
                if ( $is_edit ) {
                    echo esc_html__( 'Los cambios se fusionan con el JSON guardado: no se pierden las «cards» ni el historial de estadísticas.', 'osint-deck' );
                } else {
                    echo esc_html__( 'Completá el formulario o pegá un único objeto JSON (o un array de un elemento). La categoría debe existir en el sitio (código). Para varias herramientas a la vez usá Configuración → Datos.', 'osint-deck' );
                }
                ?>
            </p>

            <?php if ( ! $is_edit ) : ?>
            <div class="osint-deck-tool-mode-nav nav-tab-wrapper wp-clearfix" role="tablist">
                <button type="button" role="tab" class="nav-tab nav-tab-active" id="osint-tab-manual" aria-selected="true" data-osint-panel="manual">
                    <?php esc_html_e( 'Formulario', 'osint-deck' ); ?>
                </button>
                <button type="button" role="tab" class="nav-tab" id="osint-tab-json" aria-selected="false" data-osint-panel="json">
                    <?php esc_html_e( 'Pegar JSON', 'osint-deck' ); ?>
                </button>
            </div>

            <div id="osint-deck-panel-manual" class="osint-deck-tool-panel is-active" role="tabpanel" aria-labelledby="osint-tab-manual">
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-tools' ) ); ?>" class="osint-deck-card-panel" id="osint-deck-tool-form-manual">
                <?php wp_nonce_field( 'osint_deck_tool' ); ?>
                <input type="hidden" name="tool_id" value="<?php echo esc_attr( $id ); ?>">

                <h2 class="osint-deck-form-section-title"><?php esc_html_e( 'Datos principales', 'osint-deck' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="name"><?php _e( 'Nombre', 'osint-deck' ); ?> *</label></th>
                        <td>
                            <input type="text" name="name" id="name" value="<?php echo esc_attr( $data['name'] ); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="description"><?php _e( 'Descripción', 'osint-deck' ); ?></label></th>
                        <td>
                            <textarea name="description" id="description" rows="5" class="large-text"><?php echo esc_textarea( $data['description'] ); ?></textarea>
                            <p class="description"><?php _e( 'Breve descripción de la herramienta.', 'osint-deck' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="url"><?php _e( 'URL', 'osint-deck' ); ?></label></th>
                        <td>
                            <input type="url" name="url" id="url" value="<?php echo esc_attr( $data['url'] ); ?>" class="regular-text">
                            <p class="description"><?php _e( 'Enlace principal de la herramienta.', 'osint-deck' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="favicon"><?php _e( 'Icono (Favicon)', 'osint-deck' ); ?></label></th>
                        <td>
                            <input type="text" name="favicon" id="favicon" value="<?php echo esc_attr( $data['favicon'] ); ?>" class="regular-text">
                            <p class="description"><?php _e( 'URL del icono. Si es remoto, se intentará descargar al guardar.', 'osint-deck' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="category"><?php _e( 'Categoría', 'osint-deck' ); ?> *</label></th>
                        <td>
                            <select name="category" id="category" required class="regular-text">
                                <option value=""><?php _e( '-- Seleccionar Categoría --', 'osint-deck' ); ?></option>
                                <?php foreach ( $categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat['code'] ); ?>" <?php selected( $data['category'], $cat['code'] ); ?>>
                                        <?php echo esc_html( $cat['label'] ); ?> (<?php echo esc_html( $cat['code'] ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e( 'Selecciona la categoría a la que pertenece esta herramienta.', 'osint-deck' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tags_global"><?php _e( 'Tags', 'osint-deck' ); ?></label></th>
                        <td>
                            <input type="text" name="tags_global" id="tags_global" value="<?php echo esc_attr( implode( ', ', $data['tags_global'] ) ); ?>" class="regular-text">
                            <p class="description"><?php _e( 'Separados por comas (ej: email, search, free).', 'osint-deck' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row" colspan="2"><h2 class="osint-deck-form-section-title"><?php esc_html_e( 'Metadatos (filtros del buscador)', 'osint-deck' ); ?></h2></th>
                    </tr>
                    
                    <tr>
                        <th><label for="info_tipo"><?php _e( 'Tipo', 'osint-deck' ); ?></label></th>
                        <td>
                            <input type="text" name="info[tipo]" id="info_tipo" value="<?php echo esc_attr( $data['info']['tipo'] ?? '' ); ?>" class="regular-text">
                            <p class="description"><?php _e( 'Ej: Web, API, Browser Extension', 'osint-deck' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="info_acceso"><?php _e( 'Acceso', 'osint-deck' ); ?></label></th>
                        <td>
                            <input type="text" name="info[acceso]" id="info_acceso" value="<?php echo esc_attr( $data['info']['acceso'] ?? '' ); ?>" class="regular-text">
                            <p class="description"><?php _e( 'Ej: Free, Freemium, Paid, Registration', 'osint-deck' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="info_licencia"><?php _e( 'Licencia', 'osint-deck' ); ?></label></th>
                        <td>
                            <input type="text" name="info[licencia]" id="info_licencia" value="<?php echo esc_attr( $data['info']['licencia'] ?? '' ); ?>" class="regular-text">
                            <p class="description"><?php _e( 'Ej: Open Source, Proprietary', 'osint-deck' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row" colspan="2"><h2 class="osint-deck-form-section-title"><?php esc_html_e( 'Vista previa embebida', 'osint-deck' ); ?></h2></th>
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

                <p class="submit osint-deck-form-actions">
                    <input type="submit" name="osint_deck_tool_submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Guardar herramienta', 'osint-deck' ); ?>">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-tools' ) ); ?>" class="button button-large"><?php esc_html_e( 'Cancelar', 'osint-deck' ); ?></a>
                </p>
            </form>

            <?php if ( ! $is_edit ) : ?>
            </div>

            <div id="osint-deck-panel-json" class="osint-deck-tool-panel" role="tabpanel" aria-labelledby="osint-tab-json" hidden>
                <div class="osint-deck-card-panel osint-deck-json-panel">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-tools&action=add' ) ); ?>">
                        <?php wp_nonce_field( 'osint_deck_tool_json' ); ?>
                        <h2 class="osint-deck-form-section-title"><?php esc_html_e( 'Pegar JSON de una herramienta', 'osint-deck' ); ?></h2>
                        <p class="description">
                            <?php esc_html_e( 'Pegá el mismo formato que exporta «Descargar backup JSON» (un objeto) o un array con un solo elemento. Tiene que incluir «name» y «category» (código) o una «category_code» en la primera card.', 'osint-deck' ); ?>
                        </p>
                        <textarea name="tool_json" id="osint-deck-tool-json" rows="16" class="large-text code osint-json-textarea" placeholder="{&quot;name&quot;:&quot;…&quot;, &quot;category&quot;:&quot;codigo&quot;, …}"><?php echo esc_textarea( $tool_json_prefill ); ?></textarea>
                        <p>
                            <button type="button" class="button" id="osint-deck-json-preview-btn">
                                <?php esc_html_e( 'Validar y previsualizar', 'osint-deck' ); ?>
                            </button>
                        </p>
                        <div id="osint-deck-json-preview" class="osint-deck-json-preview" hidden></div>

                        <p class="submit osint-deck-form-actions">
                            <button type="submit" name="osint_deck_tool_json_submit" class="button button-primary button-large" value="1">
                                <?php esc_html_e( 'Validar en servidor y guardar', 'osint-deck' ); ?>
                            </button>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-tools' ) ); ?>" class="button button-large"><?php esc_html_e( 'Cancelar', 'osint-deck' ); ?></a>
                        </p>
                    </form>
                </div>
            </div>

            <script>
            (function($) {
                var openJsonOnLoad = <?php echo $open_json_tab ? 'true' : 'false'; ?>;
                function panel(panelId) {
                    var manual = $('#osint-deck-panel-manual');
                    var jsonP = $('#osint-deck-panel-json');
                    var tabM = $('#osint-tab-manual');
                    var tabJ = $('#osint-tab-json');
                    if (panelId === 'json') {
                        manual.removeClass('is-active').attr('hidden', 'hidden');
                        jsonP.addClass('is-active').removeAttr('hidden');
                        tabM.removeClass('nav-tab-active').attr('aria-selected', 'false');
                        tabJ.addClass('nav-tab-active').attr('aria-selected', 'true');
                    } else {
                        jsonP.removeClass('is-active').attr('hidden', 'hidden');
                        manual.addClass('is-active').removeAttr('hidden');
                        tabJ.removeClass('nav-tab-active').attr('aria-selected', 'false');
                        tabM.addClass('nav-tab-active').attr('aria-selected', 'true');
                    }
                }
                $(document).on('click', '.osint-deck-tool-mode-nav [data-osint-panel]', function(e) {
                    e.preventDefault();
                    panel($(this).data('osint-panel'));
                });
                $('#osint-deck-json-preview-btn').on('click', function() {
                    var raw = $('#osint-deck-tool-json').val().trim();
                    var box = $('#osint-deck-json-preview');
                    if (!raw) {
                        box.removeAttr('hidden').removeClass('notice-success').addClass('notice notice-error inline').html('<p><?php echo esc_js( __( 'Escribí o pegá JSON primero.', 'osint-deck' ) ); ?></p>');
                        return;
                    }
                    var data;
                    try {
                        data = JSON.parse(raw);
                    } catch (err) {
                        box.removeAttr('hidden').removeClass('notice-success').addClass('notice notice-error inline').html('<p><strong><?php echo esc_js( __( 'JSON inválido', 'osint-deck' ) ); ?></strong> — ' + err.message + '</p>');
                        return;
                    }
                    var tool = data;
                    if (Array.isArray(data)) {
                        if (data.length !== 1) {
                            box.removeAttr('hidden').removeClass('notice-success').addClass('notice notice-error inline').html('<p><?php echo esc_js( __( 'Tiene que ser un objeto o un array con exactamente un elemento. Para importar varias herramientas usá Configuración → Datos.', 'osint-deck' ) ); ?></p>');
                            return;
                        }
                        tool = data[0];
                    }
                    if (!tool || typeof tool !== 'object') {
                        box.removeAttr('hidden').addClass('notice notice-error inline').html('<p><?php echo esc_js( __( 'Estructura no reconocida.', 'osint-deck' ) ); ?></p>');
                        return;
                    }
                    var cat = tool.category || (tool.cards && tool.cards[0] && (tool.cards[0].category_code || tool.cards[0].category)) || '';
                    var cards = tool.cards && tool.cards.length ? tool.cards.length : 0;
                    var summary = {
                        name: tool.name || '',
                        category: cat,
                        cards: cards,
                        preview_status: tool.preview_status || '',
                        url: tool.url || (tool.cards && tool.cards[0] && tool.cards[0].url) || ''
                    };
                    var ok = summary.name && cat;
                    var cls = ok ? 'notice-success' : 'notice-warning';
                    var msg = ok
                        ? '<p><strong><?php echo esc_js( __( 'Sintaxis OK.', 'osint-deck' ) ); ?></strong> <?php echo esc_js( __( 'Al guardar se validará la categoría en el servidor.', 'osint-deck' ) ); ?></p>'
                        : '<p><strong><?php echo esc_js( __( 'Falta nombre o categoría', 'osint-deck' ) ); ?></strong></p>';
                    var $pre = $('<pre/>').css({ whiteSpace: 'pre-wrap', margin: '.5em 0 0' }).text(JSON.stringify(summary, null, 2));
                    box.removeAttr('hidden').removeClass('notice-error notice-warning notice-success').addClass('notice inline ' + cls).html(msg).append($pre);
                });
                if (openJsonOnLoad) {
                    panel('json');
                }
            })(jQuery);
            </script>
            <?php endif; ?>

        </div>
        <?php
    }

    /**
     * Handle save
     */
    private function handle_save() {
        $id             = isset( $_POST['tool_id'] ) ? intval( $_POST['tool_id'] ) : 0;
        $category_code  = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
        $existing       = ( $id > 0 ) ? $this->tool_repository->get_tool_by_id( $id ) : null;

        if ( $id > 0 && ! $existing ) {
            add_settings_error( 'osint_deck', 'tool_error', __( 'No se encontró la herramienta a editar.', 'osint-deck' ), 'error' );
            settings_errors( 'osint_deck' );
            return;
        }

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

        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        if ( '' === $name ) {
            add_settings_error( 'osint_deck', 'tool_error', __( 'El nombre es obligatorio.', 'osint-deck' ), 'error' );
            settings_errors( 'osint_deck' );
            return;
        }
        $description  = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
        $url_raw      = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
        $favicon_in   = isset( $_POST['favicon'] ) ? esc_url_raw( wp_unslash( $_POST['favicon'] ) ) : '';
        $preview_st   = isset( $_POST['preview_status'] ) ? sanitize_text_field( wp_unslash( $_POST['preview_status'] ) ) : 'unaudited';
        $tags_raw     = isset( $_POST['tags_global'] ) ? sanitize_text_field( wp_unslash( $_POST['tags_global'] ) ) : '';
        $tags_global  = array_filter( array_map( 'trim', explode( ',', $tags_raw ) ) );
        $info_in      = ( isset( $_POST['info'] ) && is_array( $_POST['info'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['info'] ) ) : array();

        $favicon = $favicon_in;
        if ( ! empty( $favicon_in ) && ! empty( $name ) ) {
            $favicon = $this->icon_manager->download_icon( $favicon_in, sanitize_title( $name ) );
        } elseif ( empty( $favicon_in ) && $existing && ! empty( $existing['favicon'] ) ) {
            $favicon = $existing['favicon'];
        }

        $desc_plain = wp_strip_all_tags( $description );

        if ( $existing ) {
            $data = $existing;
            $data['_db_id']         = $id;
            $data['name']           = $name;
            $data['description']    = $description;
            $data['desc']           = $desc_plain;
            $data['category']       = $category_code;
            $data['url']            = $url_raw;
            $data['favicon']        = $favicon;
            $data['preview_status'] = $preview_st;
            $data['tags_global']    = $tags_global;
            $data['info']           = array_merge( $existing['info'] ?? array(), $info_in );
            if ( ! isset( $data['stats'] ) || ! is_array( $data['stats'] ) ) {
                $data['stats'] = array(
                    'clicks'     => 0,
                    'likes'      => 0,
                    'reports'    => 0,
                    'favorites'  => 0,
                );
            }
            $data['slug'] = ! empty( $existing['slug'] )
                ? $existing['slug']
                : sanitize_title( $name );
            if ( '' === $data['slug'] ) {
                $data['slug'] = sanitize_title( $name );
            }
        } else {
            $data = array(
                'name'             => $name,
                'slug'             => sanitize_title( $name ),
                'description'      => $description,
                'desc'             => $desc_plain,
                'category'         => $category_code,
                'url'              => $url_raw,
                'favicon'          => $favicon,
                'preview_status'   => $preview_st,
                'tags_global'      => $tags_global,
                'info'             => $info_in,
                'cards'            => array(),
                'stats'            => array(
                    'clicks'    => 0,
                    'likes'     => 0,
                    'reports'   => 0,
                    'favorites' => 0,
                ),
                'badges'           => array(),
            );
        }

        $result = $this->tool_repository->save_tool( $data );

        if ( $result ) {
            add_settings_error( 'osint_deck', 'tool_saved', __( 'Herramienta guardada correctamente.', 'osint-deck' ), 'success' );
        } else {
            add_settings_error( 'osint_deck', 'tool_error', __( 'No se pudo guardar la herramienta.', 'osint-deck' ), 'error' );
        }
        settings_errors( 'osint_deck' );
    }

    /**
     * Crea una herramienta nueva desde JSON (solo pantalla «Añadir»).
     *
     * @return bool True si se persistió correctamente.
     */
    private function handle_save_from_json() {
        $raw = isset( $_POST['tool_json'] ) ? wp_unslash( $_POST['tool_json'] ) : '';
        $raw = trim( $raw );

        if ( '' === $raw ) {
            add_settings_error( 'osint_deck', 'tool_json_empty', __( 'Pegá el JSON de la herramienta antes de guardar.', 'osint-deck' ), 'error' );
            return false;
        }

        $parsed = json_decode( $raw, true );
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            add_settings_error( 'osint_deck', 'tool_json_parse', __( 'JSON inválido: ', 'osint-deck' ) . json_last_error_msg(), 'error' );
            return false;
        }

        $is_numeric_list = is_array( $parsed ) && array() !== $parsed && array_keys( $parsed ) === range( 0, count( $parsed ) - 1 );

        if ( $is_numeric_list ) {
            if ( count( $parsed ) !== 1 ) {
                add_settings_error( 'osint_deck', 'tool_json_many', __( 'Solo se admite un objeto o [un solo objeto]. Para varias herramientas usá Configuración → Datos.', 'osint-deck' ), 'error' );
                return false;
            }
            $tool = $parsed[0];
        } elseif ( is_array( $parsed ) && isset( $parsed['name'] ) ) {
            $tool = $parsed;
        } else {
            add_settings_error( 'osint_deck', 'tool_json_shape', __( 'El JSON tiene que ser un objeto con «name» o un array de un solo elemento.', 'osint-deck' ), 'error' );
            return false;
        }

        if ( ! is_array( $tool ) || empty( $tool['name'] ) ) {
            add_settings_error( 'osint_deck', 'tool_json_name', __( 'Falta el campo «name».', 'osint-deck' ), 'error' );
            return false;
        }

        foreach ( array( '_db_id', '_db_slug', '_db_created_at', '_db_updated_at' ) as $meta_key ) {
            unset( $tool[ $meta_key ] );
        }

        $category = isset( $tool['category'] ) ? sanitize_text_field( $tool['category'] ) : '';
        if ( '' === $category && ! empty( $tool['cards'][0]['category_code'] ) ) {
            $category = sanitize_text_field( $tool['cards'][0]['category_code'] );
        }
        if ( '' === $category && ! empty( $tool['cards'][0]['category'] ) ) {
            $maybe = sanitize_text_field( $tool['cards'][0]['category'] );
            if ( $this->category_repository->get_category_by_code( $maybe ) ) {
                $category = $maybe;
            }
        }

        if ( '' === $category ) {
            add_settings_error( 'osint_deck', 'tool_json_cat', __( 'Falta «category» (código) o «category_code» en la primera card, o un código válido en la card.', 'osint-deck' ), 'error' );
            return false;
        }

        if ( ! $this->category_repository->get_category_by_code( $category ) ) {
            add_settings_error(
                'osint_deck',
                'tool_json_cat_unknown',
                sprintf(
                    /* translators: %s: category code or label attempted */
                    __( 'La categoría «%s» no existe. Creala en Categorías o usá el código exacto.', 'osint-deck' ),
                    $category
                ),
                'error'
            );
            return false;
        }

        $tool['category'] = $category;

        $allowed_preview = array( 'unaudited', 'ok', 'blocked' );
        if ( empty( $tool['preview_status'] ) || ! in_array( $tool['preview_status'], $allowed_preview, true ) ) {
            $tool['preview_status'] = 'unaudited';
        }

        if ( ! isset( $tool['stats'] ) || ! is_array( $tool['stats'] ) ) {
            $tool['stats'] = array(
                'clicks'    => 0,
                'likes'     => 0,
                'reports'   => 0,
                'favorites' => 0,
            );
        } else {
            $tool['stats'] = array_merge(
                array(
                    'clicks'    => 0,
                    'likes'     => 0,
                    'reports'   => 0,
                    'favorites' => 0,
                ),
                $tool['stats']
            );
        }

        if ( ! isset( $tool['badges'] ) || ! is_array( $tool['badges'] ) ) {
            $tool['badges'] = array();
        }

        if ( ! isset( $tool['cards'] ) || ! is_array( $tool['cards'] ) ) {
            $tool['cards'] = array();
        }

        if ( ! isset( $tool['tags_global'] ) || ! is_array( $tool['tags_global'] ) ) {
            $tool['tags_global'] = array();
        }

        if ( ! isset( $tool['info'] ) || ! is_array( $tool['info'] ) ) {
            $tool['info'] = array();
        }

        if ( ! empty( $tool['description'] ) && empty( $tool['desc'] ) ) {
            $tool['desc'] = wp_strip_all_tags( $tool['description'] );
        } elseif ( ! empty( $tool['desc'] ) && empty( $tool['description'] ) ) {
            $tool['description'] = $tool['desc'];
        }

        if ( ! empty( $tool['favicon'] ) && is_string( $tool['favicon'] ) ) {
            $tool['favicon'] = $this->icon_manager->download_icon( esc_url_raw( $tool['favicon'] ), sanitize_title( $tool['name'] ) );
        }

        $result = $this->tool_repository->import_from_json( $tool );

        if ( is_wp_error( $result ) ) {
            add_settings_error( 'osint_deck', 'tool_json_err', $result->get_error_message(), 'error' );
            return false;
        }

        if ( ! $result ) {
            add_settings_error( 'osint_deck', 'tool_json_fail', __( 'No se pudo guardar la herramienta.', 'osint-deck' ), 'error' );
            return false;
        }

        add_settings_error( 'osint_deck', 'tool_saved_json', __( 'Herramienta creada desde JSON correctamente.', 'osint-deck' ), 'success' );
        return true;
    }
}
