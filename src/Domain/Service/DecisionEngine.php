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

        // Check for conversational inputs
        foreach ( $inputs as $input ) {
            if ( $input['type'] === InputParser::TYPE_GREETING ) {
                return $this->get_greeting_response( $query );
            }
            if ( $input['type'] === InputParser::TYPE_PROMO_NEWS ) {
                return $this->get_news_response( $query );
            }
            if ( $input['type'] === InputParser::TYPE_HELP ) {
                return $this->get_help_response( $query );
            }
            if ( $input['type'] === InputParser::TYPE_TOXIC ) {
                return $this->get_toxic_response( $query );
            }
        }

        // Determine mode
        $mode = $this->determine_mode( $inputs );

        if ( $mode === self::MODE_CATALOG ) {
            return $this->process_catalog_mode( $query );
        } else {
            return $this->process_investigation_mode( $query, $inputs );
        }
    }

    /**
     * Get greeting response
     *
     * @param string $query User query.
     * @return array Response structure.
     */
    private function get_greeting_response( $query ) {
        return array(
            'mode'    => self::MODE_CATALOG,
            'inputs'  => array(),
            'query'   => $query,
            'results' => array(
                array(
                    'tool' => array(
                        'name' => 'OSINT Deck AI',
                        'description' => 'Asistente Virtual',
                        'url' => '#',
                        'categories' => array('Asistente'),
                        'tags_global' => array('ai', 'help'),
                        'cards' => array(), // Empty for structure consistency
                        'osint_context' => array('uso_principal' => 'Ayuda'),
                    ),
                    'cards' => array(
                        array(
                            'id' => 'greeting-card',
                            'title' => '¡Hola!',
                            'description' => 'Buenos días. ¿Qué deseas investigar el día de hoy? Puedo ayudarte a buscar IPs, dominios, emails y más.',
                            'type' => 'info',
                            'tags' => array('greeting'),
                        )
                    )
                )
            )
        );
    }

    /**
     * Get news response
     *
     * @param string $query User query.
     * @return array Response structure.
     */
    private function get_news_response( $query ) {
        return array(
            'mode'    => self::MODE_CATALOG,
            'inputs'  => array(),
            'query'   => $query,
            'results' => array(
                array(
                    'tool' => array(
                        'name' => 'Noticias OSINT',
                        'description' => 'Últimas novedades',
                        'url' => 'https://osint.com.ar',
                        'categories' => array('Noticias'),
                        'tags_global' => array('news', 'osint'),
                        'cards' => array(),
                        'osint_context' => array('uso_principal' => 'Información'),
                    ),
                    'cards' => array(
                        array(
                            'id' => 'news-card',
                            'title' => 'Noticias y Novedades',
                            'description' => 'Puedes ver todas las noticias y novedades del mundo OSINT en nuestro sitio web oficial.',
                            'type' => 'link',
                            'url' => 'https://osint.com.ar',
                            'tags' => array('news'),
                        )
                    )
                )
            )
        );
    }

    /**
     * Get help response
     *
     * @param string $query User query.
     * @return array Response structure.
     */
    private function get_help_response( $query ) {
        $help_url = get_option( 'osint_deck_help_url', 'https://osint.com.ar/OsintDeck-Ayuda' );

        return array(
            'mode'    => self::MODE_CATALOG,
            'inputs'  => array(),
            'query'   => $query,
            'results' => array(
                array(
                    'tool' => array(
                        'name' => 'Ayuda OSINT Deck',
                        'description' => 'Centro de Ayuda',
                        'url' => $help_url,
                        'categories' => array('Ayuda'),
                        'tags_global' => array('help', 'support'),
                        'cards' => array(),
                        'osint_context' => array('uso_principal' => 'Soporte'),
                    ),
                    'cards' => array(
                        array(
                            'id' => 'help-card',
                            'title' => '¿Necesitas ayuda?',
                            'description' => 'Aquí tienes una guía completa sobre cómo utilizar OSINT Deck y qué tipo de búsquedas puedes realizar.',
                            'type' => 'link',
                            'url' => $help_url,
                            'tags' => array('help'),
                        )
                    )
                )
            )
        );
    }

    /**
     * Get toxic response
     *
     * @param string $query User query.
     * @return array Response structure.
     */
    private function get_toxic_response( $query ) {
        return array(
            'mode'    => self::MODE_CATALOG,
            'inputs'  => array(),
            'query'   => $query,
            'results' => array(
                array(
                    'tool' => array(
                        'name' => 'OSINT Deck AI',
                        'description' => 'Sistema de Seguridad',
                        'url' => '#',
                        'categories' => array('Seguridad'),
                        'tags_global' => array('security', 'filter'),
                        'cards' => array(),
                        'osint_context' => array('uso_principal' => 'Filtro'),
                    ),
                    'cards' => array(
                        array(
                            'id' => 'toxic-card',
                            'title' => 'Búsqueda no permitida',
                            'description' => 'Lo siento, pero no puedo procesar ese tipo de lenguaje o búsqueda. Esta herramienta está diseñada para investigaciones OSINT profesionales.',
                            'type' => 'info', // Could be 'warning' or 'error' if supported, defaulting to info
                            'tags' => array('warning'),
                        )
                    )
                )
            )
        );
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
                // Get all cards, not just 'none' type
                // This ensures we show tool capabilities even if they require input
                $cards = $tool['cards'] ?? array();
                
                // Always include matching tools
                $results[] = array(
                    'tool'  => $tool,
                    'cards' => $cards,
                );
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

        // Check for abstract intents fallback
        if ( empty( $results ) && ! empty( $input_types ) ) {
            $primary_type = $input_types[0];
            $abstract_intents = array('reputation', 'leaks', 'vuln', 'fraud', 'security');

            if ( in_array( $primary_type, $abstract_intents ) ) {
                // Get synonyms for the intent (e.g. reputation -> reputacion)
                $synonyms = $this->get_intent_synonyms( $primary_type );
                $all_results = array();
                $seen_tools = array();

                foreach ( $synonyms as $term ) {
                    $catalog_results = $this->process_catalog_mode( $term );
                    
                    if ( ! empty( $catalog_results['results'] ) ) {
                        foreach ( $catalog_results['results'] as $result ) {
                            $tool_name = $result['tool']['name'];
                            if ( ! in_array( $tool_name, $seen_tools ) ) {
                                $all_results[] = $result;
                                $seen_tools[] = $tool_name;
                            }
                        }
                    }
                }
                
                if ( ! empty( $all_results ) ) {
                    return array(
                        'mode'    => self::MODE_CATALOG,
                        'inputs'  => array(),
                        'query'   => $query,
                        'results' => $all_results,
                    );
                }
            }
        }

        // If no results but we have a valid detection (not just 'generic' or 'none')
        if ( empty( $results ) && ! empty( $input_types ) ) {
            $primary_type = $input_types[0];
            if ( $primary_type !== 'none' && $primary_type !== 'generic' ) {
                $results[] = array(
                    'tool' => array(
                        'name' => 'OSINT Deck AI',
                        'description' => 'Asistente',
                        'url' => '#',
                        'categories' => array('Sistema'),
                        'tags_global' => array('system'),
                        'cards' => array(),
                        'osint_context' => array('uso_principal' => 'Información'),
                    ),
                    'cards' => array(
                        array(
                            'id' => 'no-tools-found',
                            'title' => 'Sin herramientas',
                            'description' => sprintf( 'No hay herramientas disponibles para "%s".', $primary_type ),
                            'type' => 'info',
                            'tags' => array('info'),
                        )
                    )
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
     * Get synonyms for abstract intents
     * 
     * @param string $intent Intent name.
     * @return array Array of synonyms including the intent itself.
     */
    private function get_intent_synonyms( $intent ) {
        $synonyms = array( $intent );
        
        switch ( $intent ) {
            case 'reputation':
                $synonyms[] = 'reputacion';
                $synonyms[] = 'blacklist';
                break;
            case 'vuln':
                $synonyms[] = 'vulnerabilidad';
                $synonyms[] = 'exploit';
                $synonyms[] = 'cve';
                break;
            case 'leaks':
                $synonyms[] = 'breach';
                $synonyms[] = 'filtracion';
                $synonyms[] = 'password';
                break;
            case 'fraud':
                $synonyms[] = 'fraude';
                $synonyms[] = 'scam';
                break;
            case 'security':
                $synonyms[] = 'seguridad';
                $synonyms[] = 'proteccion';
                break;
        }
        
        return $synonyms;
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
