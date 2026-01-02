<?php
namespace OsintDeck\Infrastructure\Persistence;

use OsintDeck\Domain\Repository\CategoryRepositoryInterface;
use OsintDeck\Infrastructure\Persistence\CategoriesTable;

/**
 * Class CustomTableCategoryRepository
 * 
 * Implementation of CategoryRepositoryInterface using custom database table
 */
class CustomTableCategoryRepository implements CategoryRepositoryInterface {

    /**
     * Get all categories
     *
     * @return array
     */
    public function get_all_categories() {
        return CategoriesTable::get_all();
    }

    /**
     * Get category by ID
     *
     * @param int $id Category ID.
     * @return array|null
     */
    public function get_category_by_id( $id ) {
        return CategoriesTable::get_by_id( $id );
    }

    /**
     * Get category by code
     *
     * @param string $code Category code.
     * @return array|null
     */
    public function get_category_by_code( $code ) {
        return CategoriesTable::get_by_code( $code );
    }

    /**
     * Save category (create or update)
     *
     * @param array $data Category data.
     * @return bool|int ID on insert, true on update, false on failure.
     */
    public function save_category( $data ) {
        if ( ! empty( $data['id'] ) ) {
            return CategoriesTable::update( $data['id'], $data );
        } else {
            return CategoriesTable::insert( $data );
        }
    }

    /**
     * Delete category
     *
     * @param int $id Category ID.
     * @return bool
     */
    public function delete_category( $id ) {
        return CategoriesTable::delete( $id );
    }

    /**
     * Install repository storage (create tables)
     *
     * @return void
     */
    public function install() {
        CategoriesTable::create_table();
    }

    /**
     * Uninstall repository storage (drop tables)
     *
     * @return void
     */
    public function drop_table() {
        CategoriesTable::drop_table();
    }

    /**
     * Count categories
     *
     * @return int
     */
    public function count_categories() {
        return CategoriesTable::count();
    }

    /**
     * Seed default categories from JSON
     *
     * @return array Result of seeding.
     */
    public function seed_defaults() {
        $json_file = OSINT_DECK_PLUGIN_DIR . 'data/categories-defaults.json';
        
        if ( ! file_exists( $json_file ) ) {
            return array(
                'success' => false,
                'message' => 'Category JSON file not found',
            );
        }

        $json = file_get_contents( $json_file );
        $categories = json_decode( $json, true );

        if ( ! is_array( $categories ) ) {
            return array(
                'success' => false,
                'message' => 'Invalid JSON',
            );
        }

        $imported = 0;
        $skipped = 0;
        $errors = array();

        foreach ( $categories as $cat ) {
            if ( empty( $cat['code'] ) ) {
                continue;
            }

            // Check if already exists
            $existing = $this->get_category_by_code( $cat['code'] );
            
            if ( $existing ) {
                $skipped++;
                continue;
            }

            // Insert
            $result = $this->save_category( $cat );
            
            if ( $result ) {
                $imported++;
            } else {
                global $wpdb;
                $errors[] = "Failed to save category: " . $cat['code'] . " - DB Error: " . $wpdb->last_error;
            }
        }

        return array(
            'success'  => true,
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'message'  => sprintf( 'Imported %d categories, skipped %d', $imported, $skipped ),
        );
    }
}
