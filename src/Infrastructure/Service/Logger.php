<?php
/**
 * Logger Service
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Service;

use OsintDeck\Infrastructure\Persistence\LogsTable;

/**
 * Class Logger
 * 
 * Handles application logging
 */
class Logger {

    /**
     * Log info message
     *
     * @param string $message Message.
     * @param array $context Context.
     * @return void
     */
    public function info( $message, $context = array() ) {
        $this->log( $message, 'info', $context );
    }

    /**
     * Log error message
     *
     * @param string $message Message.
     * @param array $context Context.
     * @return void
     */
    public function error( $message, $context = array() ) {
        $this->log( $message, 'error', $context );
    }

    /**
     * Log warning message
     *
     * @param string $message Message.
     * @param array $context Context.
     * @return void
     */
    public function warning( $message, $context = array() ) {
        $this->log( $message, 'warning', $context );
    }

    /**
     * Log debug message
     *
     * @param string $message Message.
     * @param array $context Context.
     * @return void
     */
    public function debug( $message, $context = array() ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $this->log( $message, 'debug', $context );
        }
    }

    /**
     * Write log to database
     *
     * @param string $message Message.
     * @param string $level Level.
     * @param array $context Context.
     * @return void
     */
    private function log( $message, $level, $context ) {
        // Check if logging is enabled
        if ( ! get_option( 'osint_deck_logging_enabled', false ) ) {
            return;
        }

        // Ensure table exists (simple check, maybe optimize later)
        if ( ! LogsTable::table_exists() ) {
            LogsTable::create_table();
        }

        LogsTable::insert( $message, $level, $context );
    }

    /**
     * Get logs
     *
     * @param int $limit Limit.
     * @param int $offset Offset.
     * @param string $level Filter by level.
     * @return array
     */
    public function get_logs( $limit = 100, $offset = 0, $level = '' ) {
        if ( ! LogsTable::table_exists() ) {
            return array();
        }
        return LogsTable::get_logs( $limit, $offset, $level );
    }

    /**
     * Count logs
     *
     * @param string $level Filter by level.
     * @return int
     */
    public function count_logs( $level = '' ) {
        if ( ! LogsTable::table_exists() ) {
            return 0;
        }
        return LogsTable::count( $level );
    }

    /**
     * Clean old logs
     *
     * @return int Number of deleted logs.
     */
    public function clean_old_logs() {
        if ( ! LogsTable::table_exists() ) {
            return 0;
        }

        $days = get_option( 'osint_deck_log_retention', 30 ); // Default 30 days
        $days = intval( $days );
        if ( $days < 1 ) {
            $days = 30;
        }

        return LogsTable::delete_old_logs( $days );
    }
}
