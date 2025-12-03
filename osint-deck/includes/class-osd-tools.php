<?php
/**
 * OSINT Deck - Tools helper (DB table + fallback JSON).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OSD_Tools {
    private static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'osint_deck_tools';
    }

    private static function table_exists() {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
    }

    /**
     * Create or update DB table structure.
     */
    public static function install_table() {
        global $wpdb;
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(191) NOT NULL,
            name VARCHAR(191) NOT NULL,
            category VARCHAR(191) NOT NULL DEFAULT '',
            access VARCHAR(64) NOT NULL DEFAULT '',
            color VARCHAR(16) NOT NULL DEFAULT '#333333',
            favicon VARCHAR(255) NOT NULL DEFAULT '',
            tags LONGTEXT NULL,
            info LONGTEXT NULL,
            cards LONGTEXT NULL,
            description TEXT NULL,
            meta LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY name_idx (name)
        ) {$charset};";
        dbDelta( $sql );
    }

    /**
     * Migrate option JSON into DB table if table is empty.
     */
    public static function maybe_migrate_from_option() {
        if ( ! self::table_exists() ) {
            return;
        }
        $list = self::raw_from_table();
        if ( ! empty( $list ) ) {
            return;
        }
        $stored = get_option( OSD_OPTION_TOOLS, '[]' );
        $data   = [];
        if ( is_string( $stored ) ) {
            $data = json_decode( $stored, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $maybe = maybe_unserialize( $stored );
                $data  = is_array( $maybe ) ? $maybe : [];
            }
        } elseif ( is_array( $stored ) ) {
            $data = $stored;
        }
        if ( empty( $data ) || ! is_array( $data ) ) {
            return;
        }
        foreach ( $data as $tool ) {
            self::upsert_raw( $tool );
        }
        self::sync_option_from_table();
    }

    private static function encode_json( $value ) {
        return wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }

    /**
     * Insert/update tool in DB table.
     *
     * @param array $tool
     */
    public static function upsert_raw( array $tool ) {
        if ( ! self::table_exists() ) {
            return;
        }
        global $wpdb;
        $table = self::table_name();

        $name = sanitize_text_field( $tool['name'] ?? '' );
        if ( $name === '' ) {
            return;
        }
        $slug = sanitize_title( $name );

        $row = [
            'slug'        => $slug,
            'name'        => $name,
            'category'    => sanitize_text_field( $tool['category'] ?? '' ),
            'access'      => sanitize_text_field( $tool['access'] ?? '' ),
            'color'       => sanitize_text_field( $tool['color'] ?? '#333333' ),
            'favicon'     => esc_url_raw( $tool['favicon'] ?? '' ),
            'tags'        => self::encode_json( isset( $tool['tags'] ) ? $tool['tags'] : [] ),
            'info'        => self::encode_json( isset( $tool['info'] ) ? $tool['info'] : [] ),
            'cards'       => self::encode_json( isset( $tool['cards'] ) ? $tool['cards'] : [] ),
            'description' => sanitize_text_field( $tool['desc'] ?? ( $tool['description'] ?? '' ) ),
            'meta'        => self::encode_json( isset( $tool['meta'] ) ? $tool['meta'] : [] ),
            'updated_at'  => current_time( 'mysql' ),
        ];

        $exists = $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s LIMIT 1", $slug )
        );

        if ( $exists ) {
            $wpdb->update(
                $table,
                $row,
                [ 'slug' => $slug ],
                [ '%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ],
                [ '%s' ]
            );
        } else {
            $row['created_at'] = current_time( 'mysql' );
            $wpdb->insert(
                $table,
                $row,
                [ '%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ]
            );
        }
    }

    /**
     * Delete tool by name/slug.
     */
    public static function delete_raw( $name ) {
        if ( ! self::table_exists() ) {
            return;
        }
        global $wpdb;
        $slug  = sanitize_title( $name );
        $table = self::table_name();
        $wpdb->delete( $table, [ 'slug' => $slug ], [ '%s' ] );
    }

    /**
     * Return raw tools list from DB table.
     */
    private static function raw_from_table() {
        if ( ! self::table_exists() ) {
            return [];
        }
        global $wpdb;
        $table = self::table_name();
        $rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A );
        if ( ! is_array( $rows ) || ! $rows ) {
            return [];
        }
        return array_map(
            function( $row ) {
                return [
                    'name'     => $row['name'],
                    'category' => $row['category'],
                    'access'   => $row['access'],
                    'color'    => $row['color'],
                    'favicon'  => $row['favicon'],
                    'tags'     => $row['tags'] ? json_decode( $row['tags'], true ) : [],
                    'info'     => $row['info'] ? json_decode( $row['info'], true ) : [],
                    'cards'    => $row['cards'] ? json_decode( $row['cards'], true ) : [],
                    'desc'     => $row['description'],
                    'meta'     => $row['meta'] ? json_decode( $row['meta'], true ) : [],
                ];
            },
            $rows
        );
    }

    /**
     * Raw list (DB first, fallback option).
     */
    public static function raw_list() {
        $data = self::raw_from_table();
        if ( ! empty( $data ) ) {
            return $data;
        }
        $stored = get_option( OSD_OPTION_TOOLS, '[]' );
        if ( is_string( $stored ) ) {
            $data = json_decode( $stored, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $maybe = maybe_unserialize( $stored );
                $data  = is_array( $maybe ) ? $maybe : [];
            }
        } elseif ( is_array( $stored ) ) {
            $data = $stored;
        } else {
            $data = [];
        }
        return is_array( $data ) ? $data : [];
    }

    /**
     * Sync option JSON from table (for compatibility).
     */
    public static function sync_option_from_table() {
        $list = self::raw_from_table();
        if ( empty( $list ) ) {
            return;
        }
        update_option(
            OSD_OPTION_TOOLS,
            wp_json_encode(
                array_values( $list ),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            )
        );
    }

    /**
     * Parse and normalize tools for frontend.
     *
     * @return array
     */
    public static function parse() {
        $data = self::raw_list();

        // Case: JSON as object {"0": {...}, "1": {...}}.
        if ( isset( $data['0'] ) && is_array( $data['0'] ) ) {
            $data = array_values( $data );
        }

        $out  = [];
        $seen = [];

        foreach ( $data as $tool ) {
            if ( ! is_array( $tool ) ) {
                continue;
            }

            $name = trim( (string) ( $tool['name'] ?? '' ) );
            if ( $name === '' ) {
                continue;
            }

            $key = strtolower( $name );
            if ( isset( $seen[ $key ] ) ) {
                continue;
            }
            $seen[ $key ] = true;

            $cards = isset( $tool['cards'] ) && is_array( $tool['cards'] ) ? $tool['cards'] : [];
            if ( empty( $cards ) ) {
                continue;
            }

            $primary = $cards[0];

            $tool_id = sanitize_title( $name );
            $meta    = [];
            if ( class_exists( 'OSD_Metrics' ) ) {
                $meta = OSD_Metrics::meta_for( $tool_id );
            }

            $out[] = [
                'id'       => $tool_id,
                'name'     => $name,
                'category' => (string) ( $tool['category'] ?? 'General' ),
                'access'   => (string) ( $tool['access']   ?? ''      ),
                'color'    => (string) ( $tool['color']    ?? '#333'  ),
                'favicon'  => (string) ( $tool['favicon']  ?? ''      ),
                'tags'     => isset( $tool['tags'] ) && is_array( $tool['tags'] )
                    ? array_values( array_map( 'strval', $tool['tags'] ) )
                    : [],
                'desc'     => (string) ( $tool['desc'] ?? ( $primary['desc'] ?? '' ) ),
                'info'     => $tool['info'] ?? [],
                'primary'  => [
                    'title' => (string) ( $primary['title'] ?? 'Abrir' ),
                    'desc'  => (string) ( $primary['desc']  ?? ''      ),
                    'url'   => (string) ( $primary['url']   ?? '#'     ),
                ],
                'cards'    => array_map(
                    function( $c ) {
                        $card = [
                            'title' => (string) ( $c['title'] ?? '' ),
                            'desc'  => (string) ( $c['desc']  ?? '' ),
                            'url'   => (string) ( $c['url']   ?? '' ),
                        ];

                        if ( isset( $c['category'] ) ) {
                            $card['category'] = (string) $c['category'];
                        }

                        if ( isset( $c['tags'] ) && is_array( $c['tags'] ) ) {
                            $card['tags'] = array_values( array_map( 'strval', $c['tags'] ) );
                        }

                        return $card;
                    },
                    $cards
                ),
                'meta'     => $meta,
            ];
        }

        usort(
            $out,
            function( $a, $b ) {
                return strnatcasecmp( $a['name'], $b['name'] );
            }
        );

        return $out;
    }
}

// Backwards-compatible wrapper.
if ( ! function_exists( 'osd_parse_tools' ) ) {
    function osd_parse_tools() {
        return OSD_Tools::parse();
    }
}
