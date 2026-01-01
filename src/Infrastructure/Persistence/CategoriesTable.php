<?php
/**
 * Categories Table - Custom database table for categories
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Persistence;

/**
 * Class CategoriesTable
 * 
 * Manages custom database table for categories storage
 */
class CategoriesTable {

    /**
     * Table name (without prefix)
     */
    const TABLE_NAME = 'osint_deck_categories';

    /**
     * Get full table name with prefix
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Check if table exists
     *
     * @return bool
     */
    public static function table_exists() {
        global $wpdb;
        $table_name = self::get_table_name();
        $query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );
        return $wpdb->get_var( $query ) === $table_name;
    }

    /**
     * Create or update table structure
     *
     * @since 1.0.0
     * @return void
     */
    public static function create_table() {
        global $wpdb;
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(100) NOT NULL,
            group_name VARCHAR(100) NOT NULL,
            type VARCHAR(100) NOT NULL,
            label VARCHAR(255) NOT NULL,
            icon VARCHAR(50) DEFAULT '',
            color VARCHAR(7) DEFAULT '#475569',
            descripcion TEXT,
            fase_osint TEXT,
            data_types TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY idx_group (group_name),
            KEY idx_type (type)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'osint_deck_categories_table_version', '1.0' );
    }

    /**
     * Drop table (for uninstall/reset)
     *
     * @return void
     */
    public static function drop_table() {
        global $wpdb;
        $table_name = self::get_table_name();
        $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
        delete_option( 'osint_deck_categories_table_version' );
    }

    /**
     * Insert category
     *
     * @param array $data Category data.
     * @return int|false Category ID or false on failure.
     */
    public static function insert( $data ) {
        global $wpdb;
        $table_name = self::get_table_name();

        $defaults = array(
            'code'         => '',
            'group_name'   => '',
            'type'         => '',
            'label'        => '',
            'icon'         => '',
            'color'        => '#475569',
            'descripcion'  => '',
            'fase_osint'   => '',
            'data_types'   => '',
        );

        $data = wp_parse_args( $data, $defaults );

        // Convert arrays to CSV
        if ( is_array( $data['fase_osint'] ) ) {
            $data['fase_osint'] = implode( ',', $data['fase_osint'] );
        }
        if ( is_array( $data['data_types'] ) ) {
            $data['data_types'] = implode( ',', $data['data_types'] );
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'code'        => sanitize_text_field( $data['code'] ),
                'group_name'  => sanitize_text_field( $data['group'] ?? $data['group_name'] ),
                'type'        => sanitize_text_field( $data['type'] ),
                'label'       => sanitize_text_field( $data['label'] ),
                'icon'        => sanitize_text_field( $data['icon'] ),
                'color'       => sanitize_hex_color( $data['color'] ),
                'descripcion' => sanitize_textarea_field( $data['descripcion'] ),
                'fase_osint'  => sanitize_text_field( $data['fase_osint'] ),
                'data_types'  => sanitize_text_field( $data['data_types'] ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return $result !== false ? $wpdb->insert_id : false;
    }

    /**
     * Update category
     *
     * @param int   $id   Category ID.
     * @param array $data Category data.
     * @return bool True on success, false on failure.
     */
    public static function update( $id, $data ) {
        global $wpdb;
        $table_name = self::get_table_name();

        // Convert arrays to CSV
        if ( isset( $data['fase_osint'] ) && is_array( $data['fase_osint'] ) ) {
            $data['fase_osint'] = implode( ',', $data['fase_osint'] );
        }
        if ( isset( $data['data_types'] ) && is_array( $data['data_types'] ) ) {
            $data['data_types'] = implode( ',', $data['data_types'] );
        }

        $update_data = array();
        $format = array();

        $allowed_fields = array( 'code', 'group_name', 'type', 'label', 'icon', 'color', 'descripcion', 'fase_osint', 'data_types' );

        foreach ( $allowed_fields as $field ) {
            if ( isset( $data[$field] ) ) {
                if ( $field === 'color' ) {
                    $update_data[$field] = sanitize_hex_color( $data[$field] );
                } elseif ( $field === 'descripcion' ) {
                    $update_data[$field] = sanitize_textarea_field( $data[$field] );
                } else {
                    $update_data[$field] = sanitize_text_field( $data[$field] );
                }
                $format[] = '%s';
            }
        }

        if ( empty( $update_data ) ) {
            return false;
        }

        $result = $wpdb->update(
            $table_name,
            $update_data,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * Delete category
     *
     * @param int $id Category ID.
     * @return bool True on success, false on failure.
     */
    public static function delete( $id ) {
        global $wpdb;
        $table_name = self::get_table_name();

        $result = $wpdb->delete(
            $table_name,
            array( 'id' => $id ),
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * Get category by ID
     *
     * @param int $id Category ID.
     * @return array|null Category data or null.
     */
    public static function get_by_id( $id ) {
        global $wpdb;
        $table_name = self::get_table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $id ),
            ARRAY_A
        );

        return $row ? self::parse_row( $row ) : null;
    }

    /**
     * Get category by code
     *
     * @param string $code Category code.
     * @return array|null Category data or null.
     */
    public static function get_by_code( $code ) {
        global $wpdb;
        $table_name = self::get_table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table_name} WHERE code = %s", $code ),
            ARRAY_A
        );

        return $row ? self::parse_row( $row ) : null;
    }

    /**
     * Get all categories
     *
     * @param array $args Query arguments.
     * @return array Array of categories.
     */
    public static function get_all( $args = array() ) {
        global $wpdb;
        $table_name = self::get_table_name();

        $defaults = array(
            'orderby' => 'group_name, type',
            'order'   => 'ASC',
        );

        $args = wp_parse_args( $args, $defaults );

        $order = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

        $sql = "SELECT * FROM {$table_name} ORDER BY {$args['orderby']} {$order}";

        $rows = $wpdb->get_results( $sql, ARRAY_A );

        if ( ! $rows ) {
            return array();
        }

        return array_map( array( __CLASS__, 'parse_row' ), $rows );
    }

    /**
     * Parse database row
     *
     * @param array $row Database row.
     * @return array Parsed category.
     */
    private static function parse_row( $row ) {
        // Convert CSV to arrays
        if ( ! empty( $row['fase_osint'] ) ) {
            $row['fase_osint'] = array_map( 'trim', explode( ',', $row['fase_osint'] ) );
        } else {
            $row['fase_osint'] = array();
        }

        if ( ! empty( $row['data_types'] ) ) {
            $row['data_types'] = array_map( 'trim', explode( ',', $row['data_types'] ) );
        } else {
            $row['data_types'] = array();
        }

        return $row;
    }

    /**
     * Count categories
     *
     * @return int Number of categories.
     */
    public static function count() {
        global $wpdb;
        $table_name = self::get_table_name();

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
    }
}
