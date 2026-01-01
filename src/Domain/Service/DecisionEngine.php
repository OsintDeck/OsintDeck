<?php
/**
 * Decision Engine - Determines mode and filters cards
 *
 * @package OsintDeck
 */

namespace OsintDeck\Domain\Service;

use OsintDeck\Domain\Repository\ToolRepositoryInterface;

/**
 * Class DecisionEngine
 * 
 * Main logic engine for OSINT Deck
 */
class DecisionEngine {

    /**
     * Operation modes
     */
    const MODE_CATALOG = 'catalog';
    const MODE_INVESTIGATION = 'investigation';

    /**
     * Input Parser instance
     *
     * @var InputParser
     */
    private $input_parser;

    /**
     * Tool Repository instance
     *
     * @var ToolRepositoryInterface
     */
    private $tool_repository;

    /**
     * Constructor
     * 
     * @param ToolRepositoryInterface $tool_repository
     * @param InputParser $input_parser
     */
    public function __construct( ToolRepositoryInterface $tool_repository, InputParser $input_parser ) {
        $this->input_parser = $input_parser;
        $this->tool_repository = $tool_repository;
    }

    /**
     * Determine operation mode based on inputs
     *
     * @param array $inputs Detected inputs.
     * @return string MODE_CATALOG or MODE_INVESTIGATION.
     */
    public function determine_mode( $inputs ) {
        return empty( $inputs ) ? self::MODE_CATALOG : self::MODE_INVESTIGATION;
    }

    /**
     * Process search query
     *
     * @param string $query Search query.
     * @return array Results with mode, inputs, and relevant cards/tools.
     */
    public function process_search( $query ) {
        // Parse inputs
        $inputs = $this->input_parser->parse( $query );

        // Determine mode
        $mode = $this->determine_mode( $inputs );

        if ( $mode === self::MODE_CATALOG ) {
            return $this->process_catalog_mode( $query );
        } else {
            return $this->process_investigation_mode( $query, $inputs );
        }
    }

    /**
     * Process catalog mode
     *
     * @param string $query Search query.
     * @return array Results.
     */
    private function process_catalog_mode( $query ) {
        $tools = $this->tool_repository->get_all_tools();
        $results = array();

        foreach ( $tools as $tool ) {
            // Search in tool name, tags, categories
            if ( $this->matches_search( $tool, $query ) ) {
                // Get cards with type 'none'
                $none_cards = $this->filter_none_cards( $tool['cards'] );
                
                if ( ! empty( $none_cards ) || empty( $query ) ) {
                    $results[] = array(
                        'tool'  => $tool,
                        'cards' => $none_cards,
                    );
                }
            }
        }

        return array(
            'mode'    => self::MODE_CATALOG,
            'inputs'  => array(),
            'query'   => $query,
            'results' => $results,
        );
    }

    /**
     * Process investigation mode
     *
     * @param string $query Search query.
     * @param array  $inputs Detected inputs.
     * @return array Results.
     */
    private function process_investigation_mode( $query, $inputs ) {
        $tools = $this->tool_repository->get_all_tools();
        $results = array();

        // Get input types
        $input_types = array_column( $inputs, 'type' );

        foreach ( $tools as $tool ) {
            // Get compatible cards
            $compatible_cards = $this->filter_compatible_cards( $tool['cards'], $input_types );

            if ( ! empty( $compatible_cards ) ) {
                $results[] = array(
                    'tool'  => $tool,
                    'cards' => $compatible_cards,
                );
            }
        }

        return array(
            'mode'    => self::MODE_INVESTIGATION,
            'inputs'  => $inputs,
            'query'   => $query,
            'results' => $results,
        );
    }

    /**
     * Check if tool matches search query
     *
     * @param array  $tool Tool data.
     * @param string $query Search query.
     * @return bool True if matches.
     */
    private function matches_search( $tool, $query ) {
        if ( empty( $query ) ) {
            return true;
        }

        $query = strtolower( $query );

        // Search in name
        if ( stripos( $tool['name'], $query ) !== false ) {
            return true;
        }

        // Search in tags
        if ( ! empty( $tool['tags_global'] ) ) {
            foreach ( $tool['tags_global'] as $tag ) {
                if ( stripos( $tag, $query ) !== false ) {
                    return true;
                }
            }
        }

        // Search in categories
        if ( ! empty( $tool['categories'] ) ) {
            foreach ( $tool['categories'] as $category ) {
                if ( stripos( $category, $query ) !== false ) {
                    return true;
                }
            }
        }

        // Search in OSINT context
        if ( ! empty( $tool['osint_context']['uso_principal'] ) ) {
            if ( stripos( $tool['osint_context']['uso_principal'], $query ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filter cards with type 'none'
     *
     * @param array $cards All cards.
     * @return array Filtered cards.
     */
    private function filter_none_cards( $cards ) {
        if ( empty( $cards ) ) {
            return array();
        }

        return array_filter( $cards, function( $card ) {
            $types = $card['input']['types'] ?? array();
            return in_array( 'none', $types, true );
        } );
    }

    /**
     * Filter compatible cards based on input types
     *
     * @param array $cards All cards.
     * @param array $input_types Detected input types.
     * @return array Compatible cards.
     */
    private function filter_compatible_cards( $cards, $input_types ) {
        if ( empty( $cards ) ) {
            return array();
        }

        $compatible = array();

        foreach ( $cards as $card ) {
            $card_types = $card['input']['types'] ?? array();

            // Skip 'none' cards in investigation mode
            if ( in_array( 'none', $card_types, true ) ) {
                continue;
            }

            // Check if any input type matches card types
            $intersection = array_intersect( $input_types, $card_types );

            if ( ! empty( $intersection ) ) {
                $compatible[] = $card;
            }
        }

        return $compatible;
    }

    /**
     * Get relevant tools for specific input types
     *
     * @param array $input_types Input types.
     * @return array Relevant tools.
     */
    public function get_relevant_tools( $input_types ) {
        $tools = $this->tool_repository->get_all_tools();
        $relevant = array();

        foreach ( $tools as $tool ) {
            $compatible_cards = $this->filter_compatible_cards( $tool['cards'], $input_types );

            if ( ! empty( $compatible_cards ) ) {
                $relevant[] = $tool;
            }
        }

        return $relevant;
    }

    /**
     * Get relevant cards for specific input types
     *
     * @param array $input_types Input types.
     * @return array Relevant cards with tool info.
     */
    public function get_relevant_cards( $input_types ) {
        $tools = $this->tool_repository->get_all_tools();
        $cards = array();

        foreach ( $tools as $tool ) {
            $compatible_cards = $this->filter_compatible_cards( $tool['cards'], $input_types );

            foreach ( $compatible_cards as $card ) {
                $cards[] = array(
                    'card'      => $card,
                    'tool_id'   => $tool['id'],
                    'tool_name' => $tool['name'],
                    'favicon'   => $tool['favicon'],
                );
            }
        }

        return $cards;
    }
}
