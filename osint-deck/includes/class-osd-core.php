<?php
/**
 * OSINT Deck - Core bootstrap.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OSD_Core {
    /**
     * Singleton.
     *
     * @return OSD_Core
     */
    public static function init() {
        static $instance = null;

        if ( $instance === null ) {
            $instance = new self();
        }

        return $instance;
    }

    private function __construct() {
        $this->load_dependencies();
        if ( class_exists( 'OSD_Tools' ) ) {
            OSD_Tools::install_table();
            OSD_Tools::maybe_migrate_from_option();
        }
        $this->register_components();
    }

    /**
     * Load required files.
     */
    private function load_dependencies() {
        require_once OSD_PLUGIN_DIR . 'includes/class-osd-tools.php';
        require_once OSD_PLUGIN_DIR . 'includes/class-osd-shortcode.php';
        require_once OSD_PLUGIN_DIR . 'includes/class-osd-assets.php';
        require_once OSD_PLUGIN_DIR . 'includes/class-osd-rate-limit.php';
        require_once OSD_PLUGIN_DIR . 'includes/class-osd-metrics.php';
        require_once OSD_PLUGIN_DIR . 'includes/class-osd-tld.php';

        // Logs are optional.
        if ( file_exists( OSD_PLUGIN_DIR . 'includes/osd-logs.php' ) ) {
            require_once OSD_PLUGIN_DIR . 'includes/osd-logs.php';
        }

        // Admin only.
        if ( is_admin() ) {
            $admin_loader = OSD_PLUGIN_DIR . 'includes/admin/class-osd-admin.php';
            if ( file_exists( $admin_loader ) ) {
                require_once $admin_loader;
                $admin_ui = OSD_PLUGIN_DIR . 'includes/admin/class-osd-admin-ui.php';
                if ( file_exists( $admin_ui ) ) {
                    require_once $admin_ui;
                    if ( class_exists( 'OSD_Admin_UI' ) ) {
                        OSD_Admin_UI::register();
                    }
                }
                if ( class_exists( 'OSD_Admin' ) ) {
                    OSD_Admin::load();
                }
            } elseif ( file_exists( OSD_PLUGIN_DIR . 'includes/osd-admin.php' ) ) {
                require_once OSD_PLUGIN_DIR . 'includes/osd-admin.php';
            }
        }

        // Frontend AJAX for user events.
        $events_file = OSD_PLUGIN_DIR . 'includes/class-osd-user-events.php';
        if ( file_exists( $events_file ) ) {
            require_once $events_file;
            if ( class_exists( 'OSD_User_Events' ) ) {
                OSD_User_Events::register();
            }
        }
    }

    /**
     * Register WP hooks.
     */
    private function register_components() {
        OSD_Shortcode::register();
        OSD_Assets::register();

        if ( class_exists( 'OSD_Metrics' ) ) {
            OSD_Metrics::register_cron();
        }
        if ( class_exists( 'OSD_TLD' ) ) {
            OSD_TLD::register_cron();
        }
    }

    /**
     * Activation hook.
     */
    public static function activate() {
        // Basic options.
        if ( get_option( OSD_OPTION_TOOLS ) === false ) {
            add_option( OSD_OPTION_TOOLS, '[]' ); // Empty JSON.
        }
        if ( get_option( OSD_OPTION_THEME_MODE ) === false ) {
            add_option( OSD_OPTION_THEME_MODE, 'auto' );
        }
        if ( get_option( OSD_OPTION_THEME_SELECTOR ) === false ) {
            add_option( OSD_OPTION_THEME_SELECTOR, '[data-site-skin]' );
        }
        if ( get_option( OSD_OPTION_THEME_TOKEN_LIGHT ) === false ) {
            add_option( OSD_OPTION_THEME_TOKEN_LIGHT, 'light' );
        }
        if ( get_option( OSD_OPTION_THEME_TOKEN_DARK ) === false ) {
            add_option( OSD_OPTION_THEME_TOKEN_DARK, 'dark' );
        }
        if ( get_option( OSD_OPTION_THEME_COLORS ) === false ) {
            add_option(
                OSD_OPTION_THEME_COLORS,
                [
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
                ]
            );
        }

        // Tabla de herramientas + migración desde opción.
        if ( class_exists( 'OSD_Tools' ) ) {
            OSD_Tools::install_table();
            OSD_Tools::maybe_migrate_from_option();
        }

        if ( class_exists( 'OSD_TLD' ) ) {
            OSD_TLD::seed_from_local();
            OSD_TLD::register_cron();
        }
        // Rate limit defaults.
        if ( get_option( OSD_Rate_Limit::OPTION_QPM ) === false ) {
            add_option( OSD_Rate_Limit::OPTION_QPM, 60 );
        }
        if ( get_option( OSD_Rate_Limit::OPTION_QPD ) === false ) {
            add_option( OSD_Rate_Limit::OPTION_QPD, 1000 );
        }
        if ( get_option( OSD_Rate_Limit::OPTION_COOLD ) === false ) {
            add_option( OSD_Rate_Limit::OPTION_COOLD, 60 );
        }
        if ( get_option( OSD_Rate_Limit::OPTION_REPORT ) === false ) {
            add_option( OSD_Rate_Limit::OPTION_REPORT, 1 );
        }

        if ( get_option( 'osd_tool_metrics' ) === false ) {
            add_option( 'osd_tool_metrics', '[]' );
        }
        if ( get_option( OSD_Metrics::OPTION_POPULAR_THRESHOLD ) === false ) {
            add_option( OSD_Metrics::OPTION_POPULAR_THRESHOLD, 100 );
        }
        if ( get_option( OSD_Metrics::OPTION_NEW_DAYS ) === false ) {
            add_option( OSD_Metrics::OPTION_NEW_DAYS, 30 );
        }
        if ( class_exists( 'OSD_Metrics' ) ) {
            OSD_Metrics::register_cron();
        }

        // Logs table (if available).
        $logs_file = OSD_PLUGIN_DIR . 'includes/osd-logs.php';
        if ( file_exists( $logs_file ) ) {
            require_once $logs_file;
            if ( function_exists( 'osd_logs_install' ) ) {
                osd_logs_install();
            }
        }
    }

    /**
     * Deactivation hook.
     */
    public static function deactivate() {
        if ( class_exists( 'OSD_Metrics' ) ) {
            wp_clear_scheduled_hook( OSD_Metrics::CRON_HOOK );
        }
        if ( class_exists( 'OSD_TLD' ) ) {
            wp_clear_scheduled_hook( OSD_TLD::CRON_HOOK );
        }
    }

    /**
     * Uninstall hook.
     */
    public static function uninstall() {
        delete_option( OSD_OPTION_TOOLS );
        delete_option( OSD_OPTION_THEME_MODE );
        delete_option( OSD_OPTION_THEME_SELECTOR );
        delete_option( OSD_OPTION_THEME_TOKEN_LIGHT );
        delete_option( OSD_OPTION_THEME_TOKEN_DARK );
        delete_option( 'osd_tool_metrics' );
        delete_option( OSD_Metrics::OPTION_POPULAR_THRESHOLD );
        delete_option( OSD_Metrics::OPTION_NEW_DAYS );

        $logs_file = OSD_PLUGIN_DIR . 'includes/osd-logs.php';
        if ( file_exists( $logs_file ) ) {
            require_once $logs_file;
            if ( function_exists( 'osd_logs_uninstall' ) ) {
                osd_logs_uninstall();
            }
        }
    }
}
