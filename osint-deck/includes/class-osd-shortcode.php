<?php
/**
 * OSINT Deck - Shortcode handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OSD_Shortcode {
    public static function register() {
        add_shortcode( 'osint_deck', [ __CLASS__, 'render' ] );
    }

    /**
     * Render shortcode output.
     *
     * @param array $atts Shortcode attributes.
     *
     * @return string
     */
    public static function render( $atts ) {
        $atts = shortcode_atts(
            [
                'category' => '',
                'access'   => '',
                'limit'    => 20,
            ],
            $atts,
            'osint_deck'
        );

        $all   = osd_parse_tools();
        $tools = [];

        foreach ( $all as $t ) {
            if ( $atts['category'] && strcasecmp( $t['category'], $atts['category'] ) !== 0 ) {
                continue;
            }
            if ( $atts['access'] && strcasecmp( $t['access'], $atts['access'] ) !== 0 ) {
                continue;
            }
            $tools[] = $t;
        }

        $limit = intval( $atts['limit'] );
        if ( $limit > 0 ) {
            $tools = array_slice( $tools, 0, $limit );
        }

        $uid = 'osint-' . wp_generate_password( 6, false, false );
        $config = [
            'uid'           => $uid,
            'themeMode'     => (string) get_option( OSD_OPTION_THEME_MODE, 'auto' ),
            'themeSelector' => (string) get_option( OSD_OPTION_THEME_SELECTOR, '[data-site-skin]' ),
            'tokenLight'    => (string) get_option( OSD_OPTION_THEME_TOKEN_LIGHT, 'light' ),
            'tokenDark'     => (string) get_option( OSD_OPTION_THEME_TOKEN_DARK,  'dark'  ),
        ];

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
}

// Backwards-compatible wrapper.
if ( ! function_exists( 'osd_shortcode' ) ) {
    function osd_shortcode( $atts ) {
        return OSD_Shortcode::render( $atts );
    }
}
