<?php
/**
 * OSINT Deck - Admin loader (wrapper).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OSD_Admin {
    public static function load() {
        require_once OSD_PLUGIN_DIR . 'includes/osd-admin.php';
    }
}
