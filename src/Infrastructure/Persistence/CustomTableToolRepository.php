<?php
namespace OsintDeck\Infrastructure\Persistence;

use OsintDeck\Domain\Repository\ToolRepositoryInterface;

/**
 * Class CustomTableToolRepository
 * 
 * Repository implementation using Custom Database Table
 */
class CustomTableToolRepository implements ToolRepositoryInterface {

    /**
     * Get all tools
     *
     * @return array
     */
    public function get_all_tools() {
        return ToolsTable::get_all();
    }

    /**
     * Get tool by ID
     *
     * @param int $id Tool ID.
     * @return array|null
     */
    public function get_tool_by_id( $id ) {
        return ToolsTable::get_by_id( $id );
    }

    /**
     * Get tool by slug
     *
     * @param string $slug Tool slug.
     * @return array|null
     */
    public function get_tool_by_slug( $slug ) {
        return ToolsTable::get_by_slug( $slug );
    }

    /**
     * Search tools
     *
     * @param string $query Search query.
     * @return array
     */
    public function search_tools( $query ) {
        return ToolsTable::search( $query );
    }

    /**
     * Increment click count
     * 
     * @param int $id Tool ID.
     * @return bool
     */
    public function increment_clicks( $id ) {
        $tool = ToolsTable::get_by_id( $id );
        if ( ! $tool ) {
            return false;
        }

        if ( ! isset( $tool['stats']['clicks'] ) ) {
            $tool['stats']['clicks'] = 0;
        }
        $tool['stats']['clicks']++;

        return (bool) ToolsTable::upsert( $tool );
    }

    /**
     * Increment like count
     * 
     * @param int $id Tool ID.
     * @return bool
     */
    public function increment_likes( $id ) {
        $tool = ToolsTable::get_by_id( $id );
        if ( ! $tool ) {
            return false;
        }

        if ( ! isset( $tool['stats']['likes'] ) ) {
            $tool['stats']['likes'] = 0;
        }
        $tool['stats']['likes']++;

        return (bool) ToolsTable::upsert( $tool );
    }

    /**
     * Increment report count
     * 
     * @param int $id Tool ID.
     * @return bool
     */
    public function increment_reports( $id ) {
        $tool = ToolsTable::get_by_id( $id );
        if ( ! $tool ) {
            return false;
        }

        if ( ! isset( $tool['stats']['reports'] ) ) {
            $tool['stats']['reports'] = 0;
        }
        $tool['stats']['reports']++;

        return (bool) ToolsTable::upsert( $tool );
    }

    /**
     * Delete tool
     *
     * @param int $id Tool ID.
     * @return bool
     */
    public function delete_tool( $id ) {
        return ToolsTable::delete( $id );
    }

    /**
     * Install repository storage (create tables)
     *
     * @return void
     */
    public function install() {
        ToolsTable::create_table();
    }

    /**
     * Import tool from JSON data
     *
     * @param array $data Tool data from JSON.
     * @return int|false|\WP_Error ID on success, false/error on failure.
     */
    public function import_from_json( $data ) {
        if ( empty( $data['name'] ) ) {
            return new \WP_Error( 'invalid_data', 'Tool name is required' );
        }

        // Map JSON fields to DB fields if needed
        // Assuming JSON structure matches DB structure for now, 
        // but handling specific fields like 'slug' generation if missing

        if ( empty( $data['slug'] ) ) {
            $data['slug'] = sanitize_title( $data['name'] );
        }

        // Ensure stats are initialized if missing
        if ( empty( $data['stats'] ) ) {
            $data['stats'] = array(
                'clicks'  => 0,
                'likes'   => 0,
                'reports' => 0,
            );
        }

        return ToolsTable::upsert( $data );
    }

    /**
     * Export tool to JSON data (array)
     *
     * @param int $id Tool ID.
     * @return array|false Tool data or false if not found.
     */
    public function export_to_json( $id ) {
        $tool = ToolsTable::get_by_id( $id );
        if ( ! $tool ) {
            return false;
        }

        // Remove DB metadata
        unset( $tool['_db_id'] );
        unset( $tool['_db_slug'] );
        unset( $tool['_db_created_at'] );
        unset( $tool['_db_updated_at'] );

        return $tool;
    }

    /**
     * Save tool
     *
     * @param array $data Tool data.
     * @return int|false ID on success, false on failure.
     */
    public function save_tool( $data ) {
        return ToolsTable::upsert( $data );
    }

    /**
     * Count tools
     *
     * @return int
     */
    public function count_tools() {
        return ToolsTable::count();
    }

    /**
     * Seed default tools from JSON
     *
     * @return array Result of seeding.
     */
    public function seed_defaults() {
        $files = array(
            'defaults' => OSINT_DECK_PLUGIN_DIR . 'data/tools-defaults.json',
            'dorks'    => OSINT_DECK_PLUGIN_DIR . 'data/tools-dorks.json',
        );

        $results = array(
            'success' => true,
            'imported' => 0,
            'skipped' => 0,
            'errors' => array(),
        );

        foreach ( $files as $type => $file ) {
            if ( ! file_exists( $file ) ) {
                $results['errors'][] = "File not found: $type";
                continue;
            }

            $json = file_get_contents( $file );
            $tools = json_decode( $json, true );

            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $results['errors'][] = "Invalid JSON in $type: " . json_last_error_msg();
                continue;
            }

            if ( ! is_array( $tools ) ) {
                $results['errors'][] = "Invalid data format in $type";
                continue;
            }

            foreach ( $tools as $tool ) {
                // Ensure slug is generated
                if ( empty( $tool['slug'] ) && ! empty( $tool['name'] ) ) {
                    $tool['slug'] = sanitize_title( $tool['name'] );
                }

                // Check if exists
                $existing = $this->get_tool_by_slug( $tool['slug'] );
                if ( $existing ) {
                    $results['skipped']++;
                    continue;
                }

                $res = $this->save_tool( $tool );
                if ( $res ) {
                    $results['imported']++;
                } else {
                    global $wpdb;
                    $results['errors'][] = "Failed to save tool: " . ( $tool['name'] ?? 'Unknown' ) . " - DB Error: " . $wpdb->last_error;
                }
            }
        }

        return $results;
    }
}
