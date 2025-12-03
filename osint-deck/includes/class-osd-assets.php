<?php
/**
 * OSINT Deck - Frontend assets loader.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OSD_Assets {
    public static function register() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin' ] );
    }

    /**
     * Enqueue admin assets.
     */
    public static function enqueue_admin() {
        wp_enqueue_style(
            'osd-admin-css',
            OSD_PLUGIN_URL . 'assets/css/osd-admin.css',
            [],
            OSD_VERSION
        );
    }

    /**
     * Enqueue frontend assets only when shortcode is present.
     */
    public static function enqueue_frontend() {
        if ( ! is_singular() ) {
            return;
        }

        global $post;
        if ( ! isset( $post->post_content ) ) {
            return;
        }

        if ( ! has_shortcode( $post->post_content, 'osint_deck' ) ) {
            return;
        }

        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style(
            'osd-remixicon',
            'https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css',
            [],
            '3.5.0'
        );

        wp_enqueue_style(
            'osd-bootstrap-5',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
            [],
            '5.3.2'
        );

        wp_enqueue_style(
            'osd-frontend-css',
            OSD_PLUGIN_URL . 'assets/css/osd-frontend.css',
            [ 'osd-bootstrap-5' ],
            OSD_VERSION
        );
        self::inject_theme_colors();

        wp_enqueue_script(
            'osd-gsap',
            'https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js',
            [],
            '3.12.5',
            true
        );

        wp_enqueue_script(
            'osd-bootstrap-5',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js',
            [],
            '5.3.2',
            true
        );

        wp_enqueue_script(
            'osd-deck',
            OSD_PLUGIN_URL . 'assets/js/osint-deck.js',
            [ 'osd-gsap', 'osd-bootstrap-5' ],
            OSD_VERSION,
            true
        );
    }

    /**
     * Override CSS variables from admin-configured colors.
     */
    private static function inject_theme_colors() {
        $stored = get_option( OSD_OPTION_THEME_COLORS, [] );
        if ( ! is_array( $stored ) ) {
            $stored = [];
        }
        $defaults = [
            'dark'  => [
                'bg'       => '#0a0c0f',
                'card'     => '#16181d',
                'border'   => '#23252a',
                'ink'      => '#f2f4f8',
                'ink_sub'  => '#a9b0bb',
                'accent'   => '#00ffe0',
                'muted'    => '#9ca3af',
                'btn_bg'   => '#00ffe0',
                'btn_text' => '#0a0c0f',
            ],
            'light' => [
                'bg'       => '#f8f9fb',
                'card'     => '#ffffff',
                'border'   => '#d7dbe1',
                'ink'      => '#111111',
                'ink_sub'  => '#5f6672',
                'accent'   => '#111111',
                'muted'    => '#9aa1ac',
                'btn_bg'   => '#111111',
                'btn_text' => '#ffffff',
            ],
        ];
        $colors = [
            'dark'  => wp_parse_args( $stored['dark'] ?? [], $defaults['dark'] ),
            'light' => wp_parse_args( $stored['light'] ?? [], $defaults['light'] ),
        ];

        $darkSelectors  = ':root, .osint-wrap[data-theme="dark"], [data-theme="dark"], body[data-theme="dark"], [data-site-skin="dark"]';
        $dark  = sprintf(
            '%s{--osint-bg:%1$s;--osint-card:%2$s;--osint-border:%3$s;--osint-ink:%4$s;--osint-ink-sub:%5$s;--osint-accent:%6$s;--osint-muted:%7$s;--osint-btn-bg:%8$s;--osint-btn-text:%9$s;}',
            $darkSelectors,
            esc_html( $colors['dark']['bg'] ),
            esc_html( $colors['dark']['card'] ),
            esc_html( $colors['dark']['border'] ),
            esc_html( $colors['dark']['ink'] ),
            esc_html( $colors['dark']['ink_sub'] ),
            esc_html( $colors['dark']['accent'] ),
            esc_html( $colors['dark']['muted'] ),
            esc_html( $colors['dark']['btn_bg'] ),
            esc_html( $colors['dark']['btn_text'] )
        );
        $lightSelectors = '.osint-wrap[data-theme="light"], [data-theme="light"], body[data-theme="light"], [data-site-skin="light"]';
        $light = sprintf(
            '%s{--osint-bg:%1$s;--osint-card:%2$s;--osint-border:%3$s;--osint-ink:%4$s;--osint-ink-sub:%5$s;--osint-accent:%6$s;--osint-muted:%7$s;--osint-btn-bg:%8$s;--osint-btn-text:%9$s;}',
            $lightSelectors,
            esc_html( $colors['light']['bg'] ),
            esc_html( $colors['light']['card'] ),
            esc_html( $colors['light']['border'] ),
            esc_html( $colors['light']['ink'] ),
            esc_html( $colors['light']['ink_sub'] ),
            esc_html( $colors['light']['accent'] ),
            esc_html( $colors['light']['muted'] ),
            esc_html( $colors['light']['btn_bg'] ),
            esc_html( $colors['light']['btn_text'] )
        );

        wp_add_inline_style( 'osd-frontend-css', $dark . $light );
    }
}

// Backwards-compatible wrapper.
if ( ! function_exists( 'osd_enqueue_assets' ) ) {
    function osd_enqueue_assets() {
        return OSD_Assets::enqueue_frontend();
    }
}
