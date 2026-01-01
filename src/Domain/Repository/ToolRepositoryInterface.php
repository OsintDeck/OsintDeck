<?php
namespace OsintDeck\Domain\Repository;

/**
 * Interface ToolRepositoryInterface
 * 
 * Defines contract for tool storage and retrieval
 */
interface ToolRepositoryInterface {
    public function get_all_tools();
    public function get_tool_by_id( $id );
    public function get_tool_by_slug( $slug );
    public function search_tools( $query );
    public function increment_clicks( $id );
    public function increment_likes( $id );
    public function increment_reports( $id );
    public function delete_tool( $id );
    public function save_tool( $data );
    public function import_from_json( $json_data );
    public function count_tools();
    public function export_to_json( $id );
    public function seed_defaults();
}
