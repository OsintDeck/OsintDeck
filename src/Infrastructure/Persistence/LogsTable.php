<?php
/**
 * Logs Table - Custom database table for OSINT Deck logs
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Persistence;

/**
 * Class LogsTable
 * 
 * Manages custom database table for logs storage
 */
class LogsTable {

    /**
     * Table name (without prefix)
     */
    const TABLE_NAME = 'osint_deck_logs';

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
            message TEXT NOT NULL,
            level VARCHAR(20) DEFAULT 'info',
            context LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_level (level),
            KEY idx_created (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Store table version
        update_option( 'osint_deck_logs_table_version', '1.0' );
    }

    /**
     * Insert log
     *
     * @param string $message Log message.
     * @param string $level Log level (info, error, warning, debug).
     * @param array $context Additional context data.
     * @return int|false Log ID or false on failure.
     */
    public static function insert( $message, $level = 'info', $context = array() ) {
        global $wpdb;
        $table_name = self::get_table_name();

        $result = $wpdb->insert(
            $table_name,
            array(
                'message'    => $message,
                'level'      => $level,
                'context'    => wp_json_encode( $context ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s' )
        );

        return $result !== false ? $wpdb->insert_id : false;
    }

    /**
     * Get logs
     *
     * @param int $limit Number of logs to retrieve.
     * @param int $offset Offset.
     * @param string $level Filter by level (optional).
     * @return array Array of logs.
     */
    public static function get_logs( $limit = 100, $offset = 0, $level = '' ) {
        global $wpdb;
        $table_name = self::get_table_name();

        $sql = "SELECT * FROM {$table_name}";
        $params = array();

        if ( ! empty( $level ) ) {
            $sql .= " WHERE level = %s";
            $params[] = $level;
        }

        $sql .= " ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $query = $wpdb->prepare( $sql, $params );
        $rows = $wpdb->get_results( $query, ARRAY_A );

        if ( ! $rows ) {
            return array();
        }

        return array_map( array( __CLASS__, 'parse_row' ), $rows );
    }

    /**
     * Delete old logs
     *
     * @param int $days Retention days.
     * @return int|false Number of rows deleted.
     */
    public static function delete_old_logs( $days ) {
        global $wpdb;
        $table_name = self::get_table_name();

        $query = $wpdb->prepare(
            "DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        );

        return $wpdb->query( $query );
    }

    /**
     * Count logs
     *
     * @param string $level Filter by level (optional).
     * @return int Number of logs.
     */
    public static function count( $level = '' ) {
        global $wpdb;
        $table_name = self::get_table_name();

        if ( ! empty( $level ) ) {
            return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE level = %s", $level ) );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
    }

    /**
     * Parse database row
     *
     * @param array $row Database row.
     * @return array Parsed data.
     */
    private static function parse_row( $row ) {
        $context = json_decode( $row['context'], true );
        if ( ! is_array( $context ) ) {
            $context = array();
        }

        return array(
            'id'         => (int) $row['id'],
            'message'    => $row['message'],
            'level'      => $row['level'],
            'context'    => $context,
            'created_at' => $row['created_at'],
        );
    }
}
