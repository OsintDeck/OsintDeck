<?php
/**
 * Input Parser - Detects and validates OSINT input types
 *
 * @package OsintDeck
 */

namespace OsintDeck\Domain\Service;

use OsintDeck\Infrastructure\Service\TLDManager;

/**
 * Class InputParser
 * 
 * Detects and validates different types of OSINT inputs
 */
class InputParser {

    /**
     * TLD Manager
     *
     * @var TLDManager
     */
    private $tld_manager;

    /**
     * Supported input types
     */
    const TYPE_DOMAIN = 'domain';
    const TYPE_IP = 'ip';
    const TYPE_URL = 'url';
    const TYPE_EMAIL = 'email';
    const TYPE_HASH = 'hash';
    const TYPE_ASN = 'asn';
    const TYPE_HOST = 'host';
    const TYPE_HEADERS = 'headers';
    const TYPE_PHONE = 'phone';
    const TYPE_USERNAME = 'username';
    const TYPE_WALLET = 'wallet';
    const TYPE_TX_HASH = 'tx_hash';
    const TYPE_NONE = 'none';

    /**
     * Regex patterns for detection
     *
     * @var array
     */
    private $patterns = array();

    /**
     * Constructor - Initialize patterns
     * 
     * @param TLDManager $tld_manager TLD Manager instance.
     */
    public function __construct( TLDManager $tld_manager ) {
        $this->tld_manager = $tld_manager;
        $this->init_patterns();
    }

    /**
     * Initialize regex patterns
     *
     * @return void
     */
    private function init_patterns() {
        $this->patterns = array(
            self::TYPE_EMAIL => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
            self::TYPE_IP    => '/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b|(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}/',
            self::TYPE_URL   => '/https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&\/\/=]*)/',
            self::TYPE_DOMAIN => '/\b(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]\b/i',
            self::TYPE_HASH  => '/\b[a-fA-F0-9]{32}\b|\b[a-fA-F0-9]{40}\b|\b[a-fA-F0-9]{64}\b/',
            self::TYPE_ASN   => '/\bAS\d{1,10}\b/i',
            self::TYPE_PHONE => '/\+?[1-9]\d{1,14}/',
            self::TYPE_USERNAME => '/@[a-zA-Z0-9_]{1,15}\b/',
            // Bitcoin wallet
            self::TYPE_WALLET => '/\b[13][a-km-zA-HJ-NP-Z1-9]{25,34}\b|bc1[a-z0-9]{39,59}\b/',
        );
    }

    /**
     * Parse text and detect all inputs
     *
     * @param string $text Input text.
     * @return array Array of detected inputs.
     */
    public function parse( $text ) {
        $detected = array();

        // Detect each type
        foreach ( $this->patterns as $type => $pattern ) {
            if ( preg_match_all( $pattern, $text, $matches ) ) {
                foreach ( $matches[0] as $match ) {
                    // Additional validation
                    if ( $this->validate( $match, $type ) ) {
                        $detected[] = array(
                            'type'  => $type,
                            'value' => $this->normalize( $match, $type ),
                            'raw'   => $match,
                        );
                    }
                }
            }
        }

        // Remove duplicates
        $detected = $this->remove_duplicates( $detected );

        // Remove overlapping matches (e.g., domain inside URL)
        $detected = $this->remove_overlapping( $detected );

        return $detected;
    }

    /**
     * Detect type of a single value
     *
     * @param string $value Value to detect.
     * @return string|null Detected type or null.
     */
    public function detect_type( $value ) {
        foreach ( $this->patterns as $type => $pattern ) {
            if ( preg_match( $pattern, $value ) && $this->validate( $value, $type ) ) {
                return $type;
            }
        }
        return null;
    }

    /**
     * Validate a value for a specific type
     *
     * @param string $value Value to validate.
     * @param string $type Type to validate against.
     * @return bool True if valid.
     */
    public function validate( $value, $type ) {
        if ( ! is_string( $value ) ) {
            return false;
        }
        switch ( $type ) {
            case self::TYPE_EMAIL:
                return filter_var( $value, FILTER_VALIDATE_EMAIL ) !== false;

            case self::TYPE_IP:
                return filter_var( $value, FILTER_VALIDATE_IP ) !== false;

            case self::TYPE_URL:
                return filter_var( $value, FILTER_VALIDATE_URL ) !== false;

            case self::TYPE_DOMAIN:
                // Exclude IPs and URLs
                if ( filter_var( $value, FILTER_VALIDATE_IP ) !== false ) {
                    return false;
                }
                if ( strpos( $value, 'http://' ) === 0 || strpos( $value, 'https://' ) === 0 ) {
                    return false;
                }
                // Basic domain validation
                if ( ! preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i', $value ) ) {
                    return false;
                }
                
                // Validate TLD
                return $this->validate_tld( $value );

            case self::TYPE_HASH:
                $len = strlen( $value );
                return in_array( $len, array( 32, 40, 64 ), true ) && ctype_xdigit( $value );

            case self::TYPE_ASN:
                return preg_match( '/^AS\\d{1,10}$/i', $value );

            case self::TYPE_PHONE:
                // Basic validation
                $clean = preg_replace( '/[^0-9+]/', '', $value );
                return strlen( $clean ) >= 10 && strlen( $clean ) <= 15;

            case self::TYPE_USERNAME:
                return preg_match( '/^@[a-zA-Z0-9_]{1,15}$/', $value );

            case self::TYPE_WALLET:
                // Basic Bitcoin address validation
                return preg_match( '/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $value ) ||
                       preg_match( '/^bc1[a-z0-9]{39,59}$/', $value );

            default:
                return true;
        }
    }

    /**
     * Validate TLD against known TLDs
     *
     * @param string $domain Domain to validate.
     * @return bool True if TLD is valid.
     */
    private function validate_tld( $domain ) {
        // Extract TLD
        $parts = explode( '.', strtolower( $domain ) );
        if ( count( $parts ) < 2 ) {
            return false;
        }
        
        $tld = end( $parts );
        
        // Use TLDManager
        return $this->tld_manager->is_valid( $tld );
    }

    /**
     * Normalize a value according to its type
     *
     * @param string $value Value to normalize.
     * @param string $type Type of value.
     * @return string Normalized value.
     */
    public function normalize( $value, $type ) {
        switch ( $type ) {
            case self::TYPE_EMAIL:
                return strtolower( trim( $value ) );

            case self::TYPE_DOMAIN:
                return strtolower( trim( $value ) );

            case self::TYPE_URL:
                return trim( $value );

            case self::TYPE_IP:
                return trim( $value );

            case self::TYPE_HASH:
                return strtolower( trim( $value ) );

            case self::TYPE_ASN:
                return strtoupper( trim( $value ) );

            case self::TYPE_PHONE:
                // Remove spaces and dashes
                return preg_replace( '/[\s-]/', '', $value );

            case self::TYPE_USERNAME:
                return trim( $value );

            case self::TYPE_WALLET:
                return trim( $value );

            default:
                return trim( $value );
        }
    }

    /**
     * Remove duplicate detections
     *
     * @param array $detected Detected inputs.
     * @return array Unique inputs.
     */
    private function remove_duplicates( $detected ) {
        $unique = array();
        $seen = array();

        foreach ( $detected as $item ) {
            $key = $item['type'] . ':' . $item['value'];
            if ( ! isset( $seen[ $key ] ) ) {
                $unique[] = $item;
                $seen[ $key ] = true;
            }
        }

        return $unique;
    }

    /**
     * Remove overlapping matches
     * For example, if we detect both a URL and a domain, keep only the URL
     *
     * @param array $detected Detected inputs.
     * @return array Filtered inputs.
     */
    private function remove_overlapping( $detected ) {
        $priority = array(
            self::TYPE_URL      => 1,
            self::TYPE_EMAIL    => 2,
            self::TYPE_IP       => 3,
            self::TYPE_DOMAIN   => 4,
            self::TYPE_HASH     => 5,
            self::TYPE_ASN      => 6,
            self::TYPE_PHONE    => 7,
            self::TYPE_USERNAME => 8,
            self::TYPE_WALLET   => 9,
        );

        // Sort by priority
        usort( $detected, function( $a, $b ) use ( $priority ) {
            $pa = $priority[ $a['type'] ] ?? 99;
            $pb = $priority[ $b['type'] ] ?? 99;
            return $pa - $pb;
        } );

        $filtered = array();
        $used_values = array();

        foreach ( $detected as $item ) {
            $overlap = false;

            // Check if this value is contained in any higher-priority value
            foreach ( $used_values as $used ) {
                if ( strpos( $used, $item['raw'] ) !== false ) {
                    $overlap = true;
                    break;
                }
            }

            if ( ! $overlap ) {
                $filtered[] = $item;
                $used_values[] = $item['raw'];
            }
        }

        return $filtered;
    }

    /**
     * Get all supported types
     *
     * @return array Array of type constants.
     */
    public function get_supported_types() {
        return array(
            self::TYPE_DOMAIN,
            self::TYPE_IP,
            self::TYPE_URL,
            self::TYPE_EMAIL,
            self::TYPE_HASH,
            self::TYPE_ASN,
            self::TYPE_HOST,
            self::TYPE_HEADERS,
            self::TYPE_PHONE,
            self::TYPE_USERNAME,
            self::TYPE_WALLET,
            self::TYPE_TX_HASH,
            self::TYPE_NONE,
        );
    }

    /**
     * Get human-readable label for type
     *
     * @param string $type Type constant.
     * @return string Human-readable label.
     */
    public function get_type_label( $type ) {
        $labels = array(
            self::TYPE_DOMAIN   => __( 'Dominio', 'osint-deck' ),
            self::TYPE_IP       => __( 'Dirección IP', 'osint-deck' ),
            self::TYPE_URL      => __( 'URL', 'osint-deck' ),
            self::TYPE_EMAIL    => __( 'Email', 'osint-deck' ),
            self::TYPE_HASH     => __( 'Hash', 'osint-deck' ),
            self::TYPE_ASN      => __( 'ASN', 'osint-deck' ),
            self::TYPE_HOST     => __( 'Host', 'osint-deck' ),
            self::TYPE_HEADERS  => __( 'Headers de Email', 'osint-deck' ),
            self::TYPE_PHONE    => __( 'Teléfono', 'osint-deck' ),
            self::TYPE_USERNAME => __( 'Usuario', 'osint-deck' ),
            self::TYPE_WALLET   => __( 'Wallet Crypto', 'osint-deck' ),
            self::TYPE_TX_HASH  => __( 'Transaction Hash', 'osint-deck' ),
            self::TYPE_NONE     => __( 'Ninguno', 'osint-deck' ),
        );

        return $labels[ $type ] ?? $type;
    }
}
