<?php
/**
 * Shortcodes - Frontend shortcodes for OSINT Deck
 *
 * @package OsintDeck
 */

namespace OsintDeck\Presentation\Frontend;

use OsintDeck\Domain\Repository\ToolRepositoryInterface;

/**
 * Class Shortcodes
 * 
 * Handles all shortcodes for the plugin
 */
class Shortcodes {

    /**
     * Tool Repository
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
     * Initialize shortcodes
     *
     * @return void
     */
    public function init() {
        add_shortcode( 'osint_deck_search', array( $this, 'render_search' ) );
        add_shortcode( 'osint_deck', array( $this, 'render_search' ) ); // Legacy compatibility
        add_shortcode( 'osint_deck_cards', array( $this, 'render_cards' ) );
    }

    /**
     * Render search shortcode
     *
     * Usage: [osint_deck_search] or [osint_deck]
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_search( $atts ) {
        $atts = shortcode_atts( array(
            'category' => '',
            'access'   => '',
            'limit'    => 20,
        ), $atts, 'osint_deck' );

        // Get all tools from repository
        $all_tools = $this->tool_repository->get_all_tools();
        $tools = array();

        // Filter by category/access if specified
        foreach ( $all_tools as $t ) {
            if ( $atts['category'] && ! empty( $t['category'] ) ) {
                if ( strcasecmp( $t['category'], $atts['category'] ) !== 0 ) {
                    continue;
                }
            }
            if ( $atts['access'] && ! empty( $t['info']['acceso'] ) ) {
                if ( strcasecmp( $t['info']['acceso'], $atts['access'] ) !== 0 ) {
                    continue;
                }
            }
            $tools[] = $t;
        }

        $limit = intval( $atts['limit'] );
        if ( $limit > 0 ) {
            $tools = array_slice( $tools, 0, $limit );
        }

        // Generate unique ID
        $uid = 'osint-' . wp_generate_password( 6, false, false );
        
        // Config for JavaScript
        $config = array(
            'uid'           => $uid,
            'themeMode'     => get_option( 'osint_deck_theme_mode', 'auto' ),
            'themeSelector' => get_option( 'osint_deck_theme_selector', '[data-site-skin]' ),
            'tokenLight'    => get_option( 'osint_deck_theme_token_light', 'light' ),
            'tokenDark'     => get_option( 'osint_deck_theme_token_dark', 'dark' ),
        );

        ob_start();
        ?>
        <div id="<?php echo esc_attr( $uid ); ?>"
             class="osint-wrap"
             data-tools='<?php echo esc_attr( wp_json_encode( $tools ) ); ?>'
             data-config='<?php echo esc_attr( wp_json_encode( $config ) ); ?>'>
        </div>

        <script>
            window.OSINT_DECK_DATA = window.OSINT_DECK_DATA || {};
            window.OSINT_DECK_DATA["<?php echo esc_js( $uid ); ?>"] = <?php echo wp_json_encode( $config ); ?>;
            window.OSINT_DECK_AJAX = window.OSINT_DECK_AJAX || {
                url: "<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>",
                nonce: "<?php echo esc_js( wp_create_nonce( 'osd_user_event' ) ); ?>"
            };
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render cards shortcode
     *
     * Usage: [osint_deck_cards category="dns" limit="10"]
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_cards( $atts ) {
        $atts = shortcode_atts( array(
            'category' => '',
            'tag'      => '',
            'limit'    => 12,
            'orderby'  => 'title',
            'order'    => 'ASC',
        ), $atts, 'osint_deck_cards' );

        // Get all tools and filter manually (since repository doesn't support advanced filtering yet)
        // This aligns with Clean Architecture by abstracting the data source
        $all_tools = $this->tool_repository->get_all_tools();
        $filtered_tools = array();

        foreach ( $all_tools as $tool ) {
            // Filter by category
            if ( ! empty( $atts['category'] ) ) {
                if ( empty( $tool['category'] ) || strcasecmp( $tool['category'], $atts['category'] ) !== 0 ) {
                    continue;
                }
            }

            // Filter by tag (global tags)
            if ( ! empty( $atts['tag'] ) ) {
                $has_tag = false;
                if ( ! empty( $tool['tags_global'] ) ) {
                    foreach ( $tool['tags_global'] as $t ) {
                        if ( strcasecmp( $t, $atts['tag'] ) === 0 ) {
                            $has_tag = true;
                            break;
                        }
                    }
                }
                if ( ! $has_tag ) {
                    continue;
                }
            }

            $filtered_tools[] = $tool;
        }

        // Sort
        $orderby = $atts['orderby'];
        $order = strtoupper( $atts['order'] );
        
        usort( $filtered_tools, function( $a, $b ) use ( $orderby, $order ) {
            $val_a = $a['name'] ?? '';
            $val_b = $b['name'] ?? '';
            
            if ( $orderby === 'clicks' ) {
                $val_a = $a['stats']['clicks'] ?? 0;
                $val_b = $b['stats']['clicks'] ?? 0;
            }

            if ( $val_a == $val_b ) return 0;

            if ( $order === 'DESC' ) {
                return ( $val_a < $val_b ) ? 1 : -1;
            } else {
                return ( $val_a < $val_b ) ? -1 : 1;
            }
        });

        // Limit
        if ( intval( $atts['limit'] ) > 0 ) {
            $filtered_tools = array_slice( $filtered_tools, 0, intval( $atts['limit'] ) );
        }

        ob_start();
        ?>
        <div class="osint-deck-cards-grid">
            <?php
            if ( ! empty( $filtered_tools ) ) {
                foreach ( $filtered_tools as $tool ) {
                    $this->render_single_card( $tool );
                }
            } else {
                echo '<p>' . __( 'No se encontraron herramientas', 'osint-deck' ) . '</p>';
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single card
     *
     * @param array $tool Tool data.
     * @return void
     */
    private function render_single_card( $tool ) {
        if ( ! $tool ) {
            return;
        }

        $tool_id = $tool['_db_id'] ?? 0;
        $favicon = $tool['favicon'] ?? '';
        $name = $tool['name'] ?? '';
        $badges = $tool['badges'] ?? array();

        ?>
        <div class="osint-card" data-tool-id="<?php echo esc_attr( $tool_id ); ?>">
            <div class="osint-card-header">
                <?php if ( $favicon ) : ?>
                    <img src="<?php echo esc_url( $favicon ); ?>" class="osint-card-icon" alt="<?php echo esc_attr( $name ); ?>">
                <?php endif; ?>
                <h3 class="osint-card-title"><?php echo esc_html( $name ); ?></h3>
            </div>

            <?php if ( ! empty( $tool['osint_context']['uso_principal'] ) ) : ?>
                <p class="osint-card-description"><?php echo esc_html( $tool['osint_context']['uso_principal'] ); ?></p>
            <?php endif; ?>

            <?php if ( ! empty( $tool['tags_global'] ) ) : ?>
                <div class="osint-card-tags">
                    <?php foreach ( $tool['tags_global'] as $tag ) : ?>
                        <span class="osint-card-tag"><?php echo esc_html( $tag ); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $badges ) ) : ?>
                <div class="osint-card-badges">
                    <?php if ( ! empty( $badges['popular'] ) ) : ?>
                        <span class="osint-badge popular"><?php _e( 'Popular', 'osint-deck' ); ?></span>
                    <?php endif; ?>
                    <?php if ( ! empty( $badges['new'] ) ) : ?>
                        <span class="osint-badge new"><?php _e( 'Nuevo', 'osint-deck' ); ?></span>
                    <?php endif; ?>
                    <?php if ( ! empty( $badges['verified'] ) ) : ?>
                        <span class="osint-badge verified"><?php _e( 'Verificado', 'osint-deck' ); ?></span>
                    <?php endif; ?>
                    <?php if ( ! empty( $badges['recommended'] ) ) : ?>
                        <span class="osint-badge recommended"><?php _e( 'Recomendado', 'osint-deck' ); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
