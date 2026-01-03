<?php
/**
 * Icon Manager Service
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Service;

/**
 * Class IconManager
 * 
 * Handles downloading and managing tool icons
 */
class IconManager {

    /**
     * Download icon from URL and save locally
     *
     * @param string $url Remote URL of the icon.
     * @param string $slug Tool slug/name to use for filename.
     * @return string Local URL of the icon or original URL if failed.
     */
    public function download_icon( $url, $slug ) {
        $default_icon = OSINT_DECK_PLUGIN_URL . 'assets/images/default-icon.svg';

        if ( empty( $url ) ) {
            return $default_icon;
        }
        
        // If already local, return as is
        $upload_dir = wp_upload_dir();
        if ( strpos( $url, $upload_dir['baseurl'] ) !== false ) {
            return $url;
        }

        // Validate URL
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return $default_icon;
        }

        // Get file content
        $response = wp_remote_get( $url, array( 'timeout' => 10 ) );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
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
        $filename = sanitize_title( $slug ) . '.' . $ext;
        
        // Prepare directory
        $basedir = $upload_dir['basedir'] . '/osint-deck/icons';
        if ( ! file_exists( $basedir ) ) {
            wp_mkdir_p( $basedir );
        }

        // Save file
        $file_path = $basedir . '/' . $filename;
        
        // If file exists, we might want to overwrite or skip. 
        // User said "avoid duplicates" but also "rename to tool name".
        // If we rename to tool name, we are effectively deduplicating by name.
        // Let's overwrite to ensure fresh icon if re-imported.
        if ( file_put_contents( $file_path, $body ) === false ) {
            return $default_icon;
        }

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
