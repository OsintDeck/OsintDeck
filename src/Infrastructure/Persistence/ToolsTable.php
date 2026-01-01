<?php
/**
 * Tools Table - Custom database table for OSINT tools
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Persistence;

/**
 * Class ToolsTable
 * 
 * Manages custom database table for tools storage
 */
class ToolsTable {

    /**
     * Table name (without prefix)
     */
    const TABLE_NAME = 'osint_deck_tools';

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
     * @return void
     */
    public static function create_table() {
        global $wpdb;
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            data LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            UNIQUE KEY slug (slug),
            KEY idx_created (created_at),
            KEY idx_updated (updated_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Store table version
        update_option( 'osint_deck_table_version', '1.0' );
    }

    /**
     * Drop table (for uninstall)
     *
     * @return void
     */
    public static function drop_table() {
        global $wpdb;
        $table_name = self::get_table_name();
        $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
        delete_option( 'osint_deck_table_version' );
    }

    /**
     * Insert or update tool
     *
     * @param array $tool Tool data.
     * @return int|false Tool ID or false on failure.
     */
    public static function upsert( $tool ) {
        global $wpdb;
        $table_name = self::get_table_name();

        if ( empty( $tool['name'] ) ) {
            return false;
        }

        $name = sanitize_text_field( $tool['name'] );
        $slug = sanitize_title( $name );
        $data = wp_json_encode( $tool );

        // Check if exists
        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE slug = %s",
                $slug
            )
        );

        if ( $existing_id ) {
            // Update
            $wpdb->update(
                $table_name,
                array(
                    'name' => $name,
                    'data' => $data,
                ),
                array( 'id' => $existing_id ),
                array( '%s', '%s' ),
                array( '%d' )
            );

            // Always return ID on update (even if 0 rows affected)
            return (int) $existing_id;
        } else {
            // Insert
            $result = $wpdb->insert(
                $table_name,
                array(
                    'name'       => $name,
                    'slug'       => $slug,
                    'data'       => $data,
                    'created_at' => current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%s', '%s' )
            );

            return $result !== false ? $wpdb->insert_id : false;
        }
    }

    /**
     * Get tool by ID
     *
     * @param int $id Tool ID.
     * @return array|null Tool data or null.
     */
    public static function get_by_id( $id ) {
        global $wpdb;
        $table_name = self::get_table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        return self::parse_row( $row );
    }

    /**
     * Get tool by slug
     *
     * @param string $slug Tool slug.
     * @return array|null Tool data or null.
     */
    public static function get_by_slug( $slug ) {
        global $wpdb;
        $table_name = self::get_table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE slug = %s",
                $slug
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        return self::parse_row( $row );
    }

    /**
     * Get all tools
     *
     * @param array $args Query arguments.
     * @return array Array of tools.
     */
    public static function get_all( $args = array() ) {
        global $wpdb;
        $table_name = self::get_table_name();

        $defaults = array(
            'orderby' => 'name',
            'order'   => 'ASC',
            'limit'   => -1,
            'offset'  => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $orderby = in_array( $args['orderby'], array( 'name', 'created_at', 'updated_at' ), true )
            ? $args['orderby']
            : 'name';

        $order = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

        $sql = "SELECT * FROM {$table_name} ORDER BY {$orderby} {$order}";

        if ( $args['limit'] > 0 ) {
            $sql .= $wpdb->prepare( ' LIMIT %d', $args['limit'] );
            if ( $args['offset'] > 0 ) {
                $sql .= $wpdb->prepare( ' OFFSET %d', $args['offset'] );
            }
        }

        $rows = $wpdb->get_results( $sql, ARRAY_A );

        if ( ! $rows ) {
            return array();
        }

        return array_map( array( __CLASS__, 'parse_row' ), $rows );
    }

    /**
     * Delete tool by ID
     *
     * @param int $id Tool ID.
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
     * Delete tool by slug
     *
     * @param string $slug Tool slug.
     * @return bool True on success, false on failure.
     */
    public static function delete_by_slug( $slug ) {
        global $wpdb;
        $table_name = self::get_table_name();

        $result = $wpdb->delete(
            $table_name,
            array( 'slug' => $slug ),
            array( '%s' )
        );

        return $result !== false;
    }

    /**
     * Count tools
     *
     * @return int Number of tools.
     */
    public static function count() {
        global $wpdb;
        $table_name = self::get_table_name();

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
    }

    /**
     * Parse database row to tool array
     *
     * @param array $row Database row.
     * @return array Parsed tool data.
     */
    private static function parse_row( $row ) {
        $data = json_decode( $row['data'], true );

        if ( ! is_array( $data ) ) {
            $data = array();
        }

        // Add DB metadata
        $data['_db_id'] = (int) $row['id'];
        $data['_db_slug'] = $row['slug'];
        $data['_db_created_at'] = $row['created_at'];
        $data['_db_updated_at'] = $row['updated_at'];

        return $data;
    }

    /**
     * Search tools by name or tags
     *
     * @param string $query Search query.
     * @return array Array of matching tools.
     */
    public static function search( $query ) {
        global $wpdb;
        $table_name = self::get_table_name();

        $search = '%' . $wpdb->esc_like( $query ) . '%';

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table_name} 
            WHERE name LIKE %s 
            OR data LIKE %s 
            ORDER BY name ASC",
            $search,
            $search
        );

        $rows = $wpdb->get_results( $sql, ARRAY_A );

        if ( ! $rows ) {
            return array();
        }

        return array_map( array( __CLASS__, 'parse_row' ), $rows );
    }
}
