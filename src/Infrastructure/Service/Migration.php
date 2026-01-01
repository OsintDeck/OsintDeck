<?php
/**
 * Migration - Migrate data from post_type to custom table
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Service;

use OsintDeck\Domain\Repository\ToolRepositoryInterface;

/**
 * Class Migration
 * 
 * Handles migration from WordPress post_type to custom table
 */
class Migration {

    /**
     * Destination Repository
     *
     * @var ToolRepositoryInterface
     */
    private $dest_repo;

    /**
     * Source Repository
     *
     * @var ToolRepositoryInterface|null
     */
    private $source_repo;

    /**
     * Constructor
     *
     * @param ToolRepositoryInterface $dest_repo   Destination Repository.
     * @param ToolRepositoryInterface $source_repo Source Repository (optional).
     */
    public function __construct( ToolRepositoryInterface $dest_repo, ToolRepositoryInterface $source_repo = null ) {
        $this->dest_repo = $dest_repo;
        $this->source_repo = $source_repo;
    }

    /**
     * Migrate all tools from post_type to custom table
     *
     * @return array Migration results.
     */
    public function migrate_from_posts() {
        if ( ! $this->source_repo ) {
            return array(
                'success' => false,
                'message' => 'Source repository not provided',
            );
        }

        $tools = $this->source_repo->get_all_tools();

        $migrated = 0;
        $skipped = 0;
        $errors = array();

        foreach ( $tools as $tool ) {
            try {
                // Check if already exists in table
                $existing = $this->dest_repo->get_tool_by_slug( sanitize_title( $tool['name'] ) );
                
                if ( $existing ) {
                    $skipped++;
                    continue;
                }

                // Insert into table
                $result = $this->dest_repo->save_tool( $tool );

                if ( $result ) {
                    $migrated++;
                } else {
                    $errors[] = 'Failed to migrate: ' . $tool['name'];
                }
            } catch ( \Exception $e ) {
                $errors[] = $tool['name'] . ': ' . $e->getMessage();
            }
        }

        return array(
            'success'  => true,
            'migrated' => $migrated,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'message'  => sprintf(
                'Migrated %d tools, skipped %d (already exist), %d errors',
                $migrated,
                $skipped,
                count( $errors )
            ),
        );
    }

    /**
     * Migrate from legacy option format
     *
     * @return array Migration results.
     */
    public function migrate_from_option() {
        $json = get_option( 'osd_json_tools', '[]' );
        $tools = json_decode( $json, true );

        if ( ! is_array( $tools ) ) {
            return array(
                'success' => false,
                'message' => 'Invalid JSON in option',
            );
        }

        $migrated = 0;
        $skipped = 0;
        $errors = array();

        foreach ( $tools as $tool ) {
            if ( empty( $tool['name'] ) ) {
                continue;
            }

            try {
                // Check if already exists
                $existing = $this->dest_repo->get_tool_by_slug( sanitize_title( $tool['name'] ) );
                
                if ( $existing ) {
                    $skipped++;
                    continue;
                }

                // Insert into table
                $result = $this->dest_repo->save_tool( $tool );

                if ( $result ) {
                    $migrated++;
                } else {
                    $errors[] = 'Failed to migrate: ' . $tool['name'];
                }
            } catch ( \Exception $e ) {
                $errors[] = $tool['name'] . ': ' . $e->getMessage();
            }
        }

        return array(
            'success'  => true,
            'migrated' => $migrated,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'message'  => sprintf(
                'Migrated %d tools, skipped %d (already exist), %d errors',
                $migrated,
                $skipped,
                count( $errors )
            ),
        );
    }
}
