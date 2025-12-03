<?php
/**
 * OSINT Deck - Admin UI loader (enqueue assets).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OSD_Admin_UI {
    public static function register() {
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function enqueue( $hook ) {
        if ( $hook !== 'toplevel_page_osint-deck' ) {
            return;
        }

        wp_enqueue_style(
            'osd-admin-css',
            OSD_PLUGIN_URL . 'assets/admin/osd-admin.css',
            [],
            OSD_VERSION
        );

        // Chart.js para dashboard de métricas.
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            [],
            '4.4.1',
            true
        );

        wp_enqueue_script(
            'osd-admin-js',
            OSD_PLUGIN_URL . 'assets/admin/osd-admin.js',
            [ 'jquery', 'chartjs' ],
            OSD_VERSION,
            true
        );

        // Chart.js para dashboard de métricas.
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            [],
            '4.4.1',
            true
        );

        wp_localize_script(
            'osd-admin-js',
            'OSDAdmin',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'osd_admin_ajax' ),
                'version' => OSD_VERSION,
            ]
        );
    }
}
