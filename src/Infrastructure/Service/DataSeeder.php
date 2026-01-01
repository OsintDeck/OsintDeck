<?php
/**
 * Data Seeder Service
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Service;

use OsintDeck\Domain\Repository\CategoryRepositoryInterface;
use OsintDeck\Domain\Repository\ToolRepositoryInterface;

/**
 * Class DataSeeder
 * 
 * Orchestrates the seeding of default data
 */
class DataSeeder {

    /**
     * Category Repository
     *
     * @var CategoryRepositoryInterface
     */
    private $category_repository;

    /**
     * Tool Repository
     *
     * @var ToolRepositoryInterface
     */
    private $tool_repository;

    /**
     * Constructor
     *
     * @param CategoryRepositoryInterface $category_repository Category Repository.
     * @param ToolRepositoryInterface $tool_repository Tool Repository.
     */
    public function __construct( CategoryRepositoryInterface $category_repository, ToolRepositoryInterface $tool_repository ) {
        $this->category_repository = $category_repository;
        $this->tool_repository = $tool_repository;
    }

    /**
     * Seed all default data
     *
     * @return array Results of seeding
     */
    public function seed_all() {
        // Seed Categories
        $cat_result = $this->category_repository->seed_defaults();

        // Seed Tools
        $tool_result = $this->tool_repository->seed_defaults();

        return array(
            'success' => ( $cat_result['success'] ?? false ) && ( $tool_result['success'] ?? false ),
            'categories' => $cat_result,
            'tools' => $tool_result,
        );
    }
}
