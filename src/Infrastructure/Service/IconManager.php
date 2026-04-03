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
     * Download icon from URL and save locally (import / legado: ante fallo usa icono por defecto).
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

        $attempt = $this->attempt_remote_icon_download( $url, $slug );
        if ( $attempt['ok'] ) {
            return $attempt['url'];
        }

        return $default_icon;
    }

    /**
     * Descarga un favicon remoto y lo guarda en uploads/osint-deck/icons.
     * No asigna icono por defecto si falla (para mantener la URL en la herramienta y reintentar).
     *
     * @param string $url  URL http(s) o ya alojada en uploads.
     * @param string $slug Slug para nombre de archivo.
     * @return array{ok: bool, url: ?string, error: ?string} url solo si ok; error código o mensaje corto en inglés para logs.
     */
    public function attempt_remote_icon_download( $url, $slug ) {
        $upload_dir = wp_upload_dir();
        if ( isset( $upload_dir['error'] ) && ! empty( $upload_dir['error'] ) ) {
            $this->log( 'Upload directory error: ' . $upload_dir['error'], 'error' );
            return array(
                'ok'    => false,
                'url'   => null,
                'error' => 'upload_dir',
            );
        }

        if ( strpos( $url, $upload_dir['baseurl'] ) !== false ) {
            return array(
                'ok'    => true,
                'url'   => $url,
                'error' => null,
            );
        }

        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return array(
                'ok'    => false,
                'url'   => null,
                'error' => 'invalid_url',
            );
        }

        $upload_basedir = $upload_dir['basedir'];
        $icons_dir      = $upload_basedir . '/osint-deck/icons';

        if ( ! file_exists( $icons_dir ) ) {
            $this->log( 'Icons directory does not exist, attempting to create: ' . $icons_dir, 'info' );
            if ( ! wp_mkdir_p( $icons_dir ) ) {
                $this->log( 'Failed to create icons directory: ' . $icons_dir, 'error' );
                return array(
                    'ok'    => false,
                    'url'   => null,
                    'error' => 'mkdir_failed',
                );
            }
            $this->log( 'Icons directory created successfully: ' . $icons_dir, 'info' );
        }

        $this->log( 'Downloading icon from ' . $url, 'info' );
        $response = wp_remote_get(
            $url,
            array(
                'timeout'   => 15,
                'sslverify' => false,
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->log( 'Icon download failed for ' . $url . ' - ' . $response->get_error_message(), 'error' );
            return array(
                'ok'    => false,
                'url'   => null,
                'error' => 'request: ' . $response->get_error_message(),
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            $this->log( 'Icon download failed for ' . $url . ' - HTTP ' . $code, 'error' );
            return array(
                'ok'    => false,
                'url'   => null,
                'error' => 'http_' . (string) $code,
            );
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return array(
                'ok'    => false,
                'url'   => null,
                'error' => 'empty_body',
            );
        }

        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        $ext          = $this->get_extension_from_mime( is_string( $content_type ) ? $content_type : '' );

        if ( ! $ext ) {
            $path = parse_url( $url, PHP_URL_PATH );
            $ext  = $path ? pathinfo( (string) $path, PATHINFO_EXTENSION ) : '';
        }

        if ( ! $ext ) {
            $ext = 'png';
        }

        $safe_slug = sanitize_title( $slug );
        if ( empty( $safe_slug ) ) {
            $safe_slug = 'tool-' . uniqid();
        }
        $filename  = $safe_slug . '.' . $ext;
        $file_path = $icons_dir . '/' . $filename;

        if ( file_put_contents( $file_path, $body ) === false ) {
            $this->log( 'Failed to save icon file to ' . $file_path, 'error' );
            return array(
                'ok'    => false,
                'url'   => null,
                'error' => 'save_failed',
            );
        }

        $this->log( 'Icon saved successfully to ' . $file_path, 'info' );

        return array(
            'ok'    => true,
            'url'   => $upload_dir['baseurl'] . '/osint-deck/icons/' . $filename,
            'error' => null,
        );
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
