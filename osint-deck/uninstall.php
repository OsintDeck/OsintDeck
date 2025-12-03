<?php
/**
 * Fired when the plugin is uninstalled.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Ensure core is available.
if ( ! class_exists( 'OSD_Core' ) ) {
    require_once __DIR__ . '/osint-deck.php';
}

if ( class_exists( 'OSD_Core' ) ) {
    OSD_Core::uninstall();
}
