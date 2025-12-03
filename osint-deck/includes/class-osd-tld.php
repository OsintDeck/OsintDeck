<?php
/**
 * OSINT Deck - TLD validator (offline).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OSD_TLD {
    const OPTION_TLDS   = 'osd_valid_tlds';
    const CRON_HOOK     = 'osd_refresh_tlds_weekly';
    const LOCAL_FILE    = 'mnt/data/tlds-alpha-by-domain.txt';
    const REMOTE_SOURCE = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';

    private static function local_file_path() {
        return trailingslashit( OSD_PLUGIN_DIR ) . self::LOCAL_FILE;
    }

    /**
     * Register weekly cron to refresh TLDs.
     */
    public static function register_cron() {
        add_filter(
            'cron_schedules',
            static function( $schedules ) {
                if ( ! isset( $schedules['weekly'] ) ) {
                    $schedules['weekly'] = [
                        'interval' => WEEK_IN_SECONDS,
                        'display'  => 'OSINT Deck weekly',
                    ];
                }
                return $schedules;
            }
        );
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK );
        }
        add_action( self::CRON_HOOK, [ __CLASS__, 'refresh_remote' ] );
    }

    /**
     * Read bundled file and seed option (used on activation).
     */
    public static function seed_from_local() {
        $file = self::local_file_path();
        if ( ! file_exists( $file ) ) {
            $fallback = OSD_PLUGIN_DIR . 'assets/data/tlds-alpha-by-domain.txt';
            $file     = file_exists( $fallback ) ? $fallback : '';
        }
        if ( ! $file || ! file_exists( $file ) ) {
            return;
        }
        $body = file_get_contents( $file );
        $tlds = self::parse_list( $body );
        if ( $tlds ) {
            self::store( $tlds );
        }
    }

    /**
     * Fetch IANA list via HTTP and refresh option + local copy.
     */
    public static function refresh_remote() {
        $resp = wp_remote_get( self::REMOTE_SOURCE, [ 'timeout' => 15 ] );
        if ( is_wp_error( $resp ) ) {
            return;
        }
        if ( intval( wp_remote_retrieve_response_code( $resp ) ) !== 200 ) {
            return;
        }
        $body = wp_remote_retrieve_body( $resp );
        $tlds = self::parse_list( $body );
        if ( empty( $tlds ) ) {
            return;
        }

        // Write local copy (best effort).
        $file = self::local_file_path();
        $dir  = dirname( $file );
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        if ( is_writable( $dir ) ) {
            file_put_contents( $file, $body );
        }

        self::store( $tlds );
    }

    private static function parse_list( $raw ) {
        $lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
        $out   = [];
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( $line === '' || strpos( $line, '#' ) === 0 ) {
                continue;
            }
            $out[] = strtolower( $line );
        }
        return array_values( array_unique( $out ) );
    }

    private static function store( array $tlds ) {
        update_option(
            self::OPTION_TLDS,
            [
                'list'       => array_fill_keys( $tlds, true ),
                'updated_at' => time(),
            ],
            false
        );
    }

    private static function get_tld_map() {
        $stored = get_option( self::OPTION_TLDS );
        if ( isset( $stored['list'] ) && is_array( $stored['list'] ) ) {
            return $stored['list'];
        }

        // Fallback to bundled file.
        self::seed_from_local();
        $fresh = get_option( self::OPTION_TLDS );
        if ( isset( $fresh['list'] ) && is_array( $fresh['list'] ) ) {
            return $fresh['list'];
        }
        $default = [ 'com' => true, 'net' => true, 'org' => true, 'info' => true, 'io' => true ];
        return $default;
    }

    /**
     * Lightweight domain suggestion for typos (ej: gmial.com -> gmail.com).
     *
     * @param string $domain
     * @return string Suggested domain or empty string.
     */
    public static function suggest_domain( $domain ) {
        $raw = trim( (string) $domain );
        if ( $raw === '' ) {
            return '';
        }

        // Extraer dominio si viene con usuario.
        if ( strpos( $raw, '@' ) !== false ) {
            $parts = explode( '@', $raw );
            $raw   = end( $parts );
        }

        $raw = strtolower( $raw );
        $raw = preg_replace( '#^https?://#', '', $raw );
        $raw = rtrim( $raw, '.' );

        if ( self::is_valid_domain( $raw ) ) {
            return '';
        }

        $common = [
            'gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com',
            'protonmail.com', 'icloud.com', 'aol.com', 'live.com',
            'gmx.com', 'yandex.com', 'fastmail.com'
        ];

        $best      = '';
        $bestScore = PHP_INT_MAX;
        foreach ( $common as $candidate ) {
            $dist = levenshtein( $raw, $candidate );
            if ( $dist < $bestScore ) {
                $bestScore = $dist;
                $best      = $candidate;
            }
        }

        // Solo sugerir si es razonablemente cercano.
        if ( $best !== '' && $bestScore <= 2 ) {
            return $best;
        }

        return '';
    }

    /**
     * Validate if a domain has a known TLD (offline).
     *
     * @param string $domain
     * @return bool
     */
    public static function is_valid_domain( $domain ) {
        $domain = strtolower( trim( (string) $domain ) );
        if ( $domain === '' || strpos( $domain, '.' ) === false ) {
            return false;
        }
        $domain = rtrim( $domain, '.' );
        if ( function_exists( 'idn_to_ascii' ) ) {
            $ascii = idn_to_ascii( $domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );
            if ( $ascii ) {
                $domain = $ascii;
            }
        }
        $parts = explode( '.', $domain );
        $tld   = strtolower( end( $parts ) );
        $list  = self::get_tld_map();
        return isset( $list[ $tld ] );
    }
}

if ( ! function_exists( 'osd_is_valid_domain' ) ) {
    function osd_is_valid_domain( $domain ) {
        return OSD_TLD::is_valid_domain( $domain );
    }
}
