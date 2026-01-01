<?php
namespace OsintDeck\Domain\Repository;

/**
 * Interface CategoryRepositoryInterface
 * 
 * Defines contract for category storage and retrieval
 */
interface CategoryRepositoryInterface {
    public function get_all_categories();
    public function get_category_by_id( $id );
    public function get_category_by_code( $code );
    public function save_category( $data );
    public function delete_category( $id );
    public function count_categories();
    public function seed_defaults();
}
