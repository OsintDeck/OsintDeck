<?php
/**
 * TLD Manager - Manage valid TLDs from IANA
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Service;

/**
 * Class TLDManager
 * 
 * Manages TLD list from IANA with auto-updates
 */
class TLDManager {

    /**
     * IANA TLD list URL
     */
    const IANA_TLD_URL = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';

    /**
     * Option name for TLDs
     */
    const OPTION_TLDS = 'osint_deck_tlds';

    /**
     * Option name for custom TLDs
     */
    const OPTION_CUSTOM_TLDS = 'osint_deck_custom_tlds';

    /**
     * Option name for last update
     */
    const OPTION_LAST_UPDATE = 'osint_deck_tlds_last_update';

    /**
     * Initialize hooks
     *
     * @return void
     */
    public function init() {
        // Schedule cron if not scheduled
        if ( ! wp_next_scheduled( 'osint_deck_update_tlds' ) ) {
            wp_schedule_event( time(), 'weekly', 'osint_deck_update_tlds' );
        }

        // Hook cron action
        add_action( 'osint_deck_update_tlds', array( $this, 'update_from_iana' ) );
    }

    /**
     * Get all valid TLDs (IANA + custom)
     *
     * @return array Array of valid TLDs.
     */
    public function get_all() {
        $iana_tlds = get_option( self::OPTION_TLDS, array() );
        $custom_tlds = get_option( self::OPTION_CUSTOM_TLDS, array() );

        // If empty, seed
        if ( empty( $iana_tlds ) ) {
            $this->seed_default();
            $iana_tlds = get_option( self::OPTION_TLDS, array() );
        }

        return array_unique( array_merge( $iana_tlds, $custom_tlds ) );
    }

    /**
     * Check if TLD is valid
     *
     * @param string $tld TLD to check.
     * @return bool True if valid.
     */
    public function is_valid( $tld ) {
        $tld = strtolower( trim( $tld, '.' ) );
        $all_tlds = $this->get_all();
        return in_array( $tld, $all_tlds, true );
    }

    /**
     * Update TLDs from IANA
     *
     * @return array Result of update.
     */
    public function update_from_iana() {
        $response = wp_remote_get( self::IANA_TLD_URL, array(
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $lines = explode( "\n", $body );
        $tlds = array();

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) || strpos( $line, '#' ) === 0 ) {
                continue;
            }
            $tlds[] = strtolower( $line );
        }

        if ( ! empty( $tlds ) ) {
            update_option( self::OPTION_TLDS, $tlds );
            update_option( self::OPTION_LAST_UPDATE, current_time( 'mysql' ) );
            
            return array(
                'success' => true,
                'count'   => count( $tlds ),
                'message' => sprintf( 'Updated %d TLDs', count( $tlds ) ),
            );
        }

        return array(
            'success' => false,
            'message' => 'No TLDs found',
        );
    }

    /**
     * Add custom TLD
     *
     * @param string $tld TLD to add.
     * @return bool True on success.
     */
    public function add_custom( $tld ) {
        $tld = strtolower( trim( $tld, '.' ) );
        if ( empty( $tld ) ) {
            return false;
        }

        $custom_tlds = get_option( self::OPTION_CUSTOM_TLDS, array() );
        
        if ( ! in_array( $tld, $custom_tlds, true ) ) {
            $custom_tlds[] = $tld;
            update_option( self::OPTION_CUSTOM_TLDS, $custom_tlds );
            return true;
        }

        return false;
    }

    /**
     * Delete custom TLD
     *
     * @param string $tld TLD to delete.
     * @return bool True on success.
     */
    public function delete_custom( $tld ) {
        if ( ! is_string( $tld ) ) {
            return false;
        }
        $tld = strtolower( trim( $tld, '.' ) );
        $custom_tlds = get_option( self::OPTION_CUSTOM_TLDS, array() );
        
        $key = array_search( $tld, $custom_tlds, true );
        if ( $key !== false ) {
            unset( $custom_tlds[$key] );
            update_option( self::OPTION_CUSTOM_TLDS, array_values( $custom_tlds ) );
            return true;
        }

        return false;
    }

    /**
     * Get TLD stats
     *
     * @return array
     */
    public function get_stats() {
        $iana_tlds = get_option( self::OPTION_TLDS, array() );
        $custom_tlds = get_option( self::OPTION_CUSTOM_TLDS, array() );

        return array(
            'iana_count'   => count( $iana_tlds ),
            'custom_count' => count( $custom_tlds ),
            'total_count'  => count( array_unique( array_merge( $iana_tlds, $custom_tlds ) ) ),
            'last_update'  => get_option( self::OPTION_LAST_UPDATE ),
        );
    }
    
    /**
     * Get custom TLDs
     *
     * @return array
     */
    public function get_custom() {
        return get_option( self::OPTION_CUSTOM_TLDS, array() );
    }

    /**
     * Seed default TLDs
     *
     * @return void
     */
    public function seed_default() {
        // Minimal seed in case IANA is unreachable
        $defaults = array( 'com', 'org', 'net', 'edu', 'gov', 'io', 'ar' );
        update_option( self::OPTION_TLDS, $defaults );
    }
}
