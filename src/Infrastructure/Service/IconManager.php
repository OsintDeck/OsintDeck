<?php
/**
 * Icon Manager Service
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Service;

use OsintDeck\Infrastructure\Service\Logger;

/**
 * Class IconManager
 * 
 * Handles downloading and managing tool icons
 */
class IconManager {

    /**
     * Logger
     *
     * @var Logger|null
     */
    private $logger;

    /**
     * Constructor
     *
     * @param Logger|null $logger Logger instance.
     */
    public function __construct( Logger $logger = null ) {
        $this->logger = $logger;
    }

    /**
     * Log message
     *
     * @param string $message Message.
     * @param string $level Level.
     * @return void
     */
    private function log( $message, $level = 'info' ) {
        if ( $this->logger ) {
            if ( $level === 'error' ) {
                $this->logger->error( $message );
            } else {
                $this->logger->info( $message );
            }
        } else {
            // Fallback to error_log
            error_log( 'OSINT Deck: ' . $message );
        }
    }

    /**
     * Get default icon URL
     * 
     * @return string URL of default icon (uploaded or plugin asset).
     */
    private function get_default_icon_url() {
        $upload_dir = wp_upload_dir();
        
        // Try to use uploaded default icon if available
        if ( ! isset( $upload_dir['error'] ) || empty( $upload_dir['error'] ) ) {
            $uploaded_default_path = $upload_dir['basedir'] . '/osint-deck/icons/default-icon.svg';
            if ( file_exists( $uploaded_default_path ) ) {
                return $upload_dir['baseurl'] . '/osint-deck/icons/default-icon.svg';
            }
        }
        
        // Fallback to plugin asset
        return OSINT_DECK_PLUGIN_URL . 'assets/images/default-icon.svg';
    }

    /**
     * Download icon from URL and save locally
     *
     * @param string $url Remote URL of the icon.
     * @param string $slug Tool slug/name to use for filename.
     * @return string Local URL of the icon or original URL if failed.
     */
    public function download_icon( $url, $slug ) {
        $default_icon = $this->get_default_icon_url();

        if ( empty( $url ) ) {
            return $default_icon;
        }
        
        // If it's the old plugin default icon, replace with new one
        if ( strpos( $url, 'assets/images/default-icon.svg' ) !== false && strpos( $url, OSINT_DECK_PLUGIN_URL ) !== false ) {
            return $default_icon;
        }
        
        // If already local, return as is
        $upload_dir = wp_upload_dir();
        if ( isset( $upload_dir['error'] ) && ! empty( $upload_dir['error'] ) ) {
            $this->log( 'Upload directory error: ' . $upload_dir['error'], 'error' );
            return $default_icon;
        }

        // Prepare directory immediately
        $upload_basedir = $upload_dir['basedir'];
        $osint_deck_dir = $upload_basedir . '/osint-deck';
        $icons_dir = $osint_deck_dir . '/icons';

        if ( ! file_exists( $icons_dir ) ) {
            $this->log( 'Icons directory does not exist, attempting to create: ' . $icons_dir, 'info' );
            if ( ! wp_mkdir_p( $icons_dir ) ) {
                $this->log( 'Failed to create icons directory: ' . $icons_dir, 'error' );
                // Don't return yet, we might still be able to use default, but let's note it.
            } else {
                $this->log( 'Icons directory created successfully: ' . $icons_dir, 'info' );
            }
        }

        if ( strpos( $url, $upload_dir['baseurl'] ) !== false ) {
            return $url;
        }

        // Validate URL
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return $default_icon;
        }

        // Get file content
        $this->log( 'Downloading icon from ' . $url, 'info' );
        $response = wp_remote_get( $url, array( 
            'timeout' => 15, // Increased timeout
            'sslverify' => false // Keep false for now to ensure compatibility
        ) );

        if ( is_wp_error( $response ) ) {
            $this->log( 'Icon download failed for ' . $url . ' - ' . $response->get_error_message(), 'error' );
            return $default_icon;
        }
        
        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
            $this->log( 'Icon download failed for ' . $url . ' - HTTP ' . wp_remote_retrieve_response_code( $response ), 'error' );
            return $default_icon; // Return default on failure
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return $default_icon;
        }

        // Determine extension
        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        $ext = $this->get_extension_from_mime( $content_type );
        
        if ( ! $ext ) {
            $path = parse_url( $url, PHP_URL_PATH );
            $ext = pathinfo( $path, PATHINFO_EXTENSION );
        }
        
        if ( ! $ext ) {
            $ext = 'png'; // Default fallback
        }

        // Prepare filename
        $safe_slug = sanitize_title( $slug );
        if ( empty( $safe_slug ) ) {
            $safe_slug = 'tool-' . uniqid();
        }
        $filename = $safe_slug . '.' . $ext;
        
        // Save file
        $file_path = $icons_dir . '/' . $filename;
        
        // If file exists, we might want to overwrite or skip. 
        // User said "avoid duplicates" but also "rename to tool name".
        // If we rename to tool name, we are effectively deduplicating by name.
        // Let's overwrite to ensure fresh icon if re-imported.
        if ( file_put_contents( $file_path, $body ) === false ) {
            $this->log( 'Failed to save icon file to ' . $file_path, 'error' );
            return $default_icon;
        }

        $this->log( 'Icon saved successfully to ' . $file_path, 'info' );
        // Return local URL
        return $upload_dir['baseurl'] . '/osint-deck/icons/' . $filename;
    }

    /**
     * Get extension from mime type
     *
     * @param string $mime Mime type.
     * @return string|null Extension or null.
     */
    private function get_extension_from_mime( $mime ) {
        // Remove charset if present
        $mime_parts = explode( ';', $mime );
        $mime = trim( $mime_parts[0] );

        $map = array(
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/x-icon' => 'ico',
            'image/vnd.microsoft.icon' => 'ico',
            'image/svg+xml' => 'svg',
        );
        return isset( $map[$mime] ) ? $map[$mime] : null;
    }
}
