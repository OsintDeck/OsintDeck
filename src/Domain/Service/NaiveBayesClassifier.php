<?php
/**
 * Naive Bayes Classifier Service
 *
 * @package OsintDeck
 */

namespace OsintDeck\Domain\Service;

/**
 * Class NaiveBayesClassifier
 * 
 * Simple implementation of Naive Bayes for text classification
 */
class NaiveBayesClassifier {

    /**
     * Option key for storing the model
     */
    const OPTION_MODEL = 'osint_deck_nb_model';

    /**
     * Option key for storing training samples
     */
    const OPTION_SAMPLES = 'osint_deck_nb_samples';

    /**
     * Model data
     *
     * @var array
     */
    private $model = null;

    /**
     * Stopwords (Spanish/English mix)
     *
     * @var array
     */
    private $stopwords = array(
        'de', 'la', 'que', 'el', 'en', 'y', 'a', 'los', 'del', 'se', 'las', 'por', 'un', 'para', 'con', 'no', 'una', 'su', 'al', 'lo', 'como',
        'the', 'is', 'at', 'which', 'on', 'and', 'a', 'an', 'in', 'to', 'of', 'for', 'it', 'that', 'this', 'with', 'from'
    );

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_model();
    }

    /**
     * Load model from database
     */
    private function load_model() {
        $this->model = get_option( self::OPTION_MODEL, null );
    }

    /**
     * Get all training samples
     *
     * @return array
     */
    public function get_samples() {
        return get_option( self::OPTION_SAMPLES, array() );
    }

    /**
     * Add a training sample
     *
     * @param string $text
     * @param string $category
     * @return void
     */
    public function add_sample( $text, $category ) {
        $samples = $this->get_samples();
        $samples[] = array(
            'text' => $text,
            'category' => $category
        );
        update_option( self::OPTION_SAMPLES, $samples );
    }

    /**
     * Delete a training sample by index
     * 
     * @param int $index
     * @return void
     */
    public function delete_sample( $index ) {
        $samples = $this->get_samples();
        if ( isset( $samples[$index] ) ) {
            array_splice( $samples, $index, 1 );
            update_option( self::OPTION_SAMPLES, $samples );
        }
    }

    /**
     * Clear all samples and model
     * 
     * @return void
     */
    public function clear_all() {
        update_option( self::OPTION_SAMPLES, array() );
        update_option( self::OPTION_MODEL, null );
        $this->model = null;
    }

    /**
     * Load defaults from JSON file
     * 
     * @param string $json_file Path to JSON file
     * @return array Result array with count of imported and skipped
     */
    public function load_defaults_from_json( $json_file ) {
        if ( ! file_exists( $json_file ) ) {
            return array( 'success' => false, 'message' => 'File not found' );
        }

        $content = file_get_contents( $json_file );
        $data = json_decode( $content, true );
        
        if ( ! is_array( $data ) ) {
            return array( 'success' => false, 'message' => 'Invalid JSON' );
        }

        $existing_samples = $this->get_samples();
        $existing_signatures = array();
        foreach ( $existing_samples as $s ) {
            $existing_signatures[] = md5( $s['text'] . '|' . $s['category'] );
        }

        $count = 0;
        $skipped = 0;

        foreach ( $data as $item ) {
            if ( isset( $item['text'] ) && isset( $item['category'] ) ) {
                $sig = md5( $item['text'] . '|' . $item['category'] );
                if ( ! in_array( $sig, $existing_signatures ) ) {
                    $this->add_sample( $item['text'], $item['category'] );
                    $existing_signatures[] = $sig;
                    $count++;
                } else {
                    $skipped++;
                }
            }
        }

        return array(
            'success' => true,
            'imported' => $count,
            'skipped' => $skipped
        );
    }

    /**
     * Train the model based on stored samples
     *
     * @return array Statistics of training
     */
    public function train() {
        $samples = $this->get_samples();
        if ( empty( $samples ) ) {
            return array( 'status' => 'error', 'msg' => 'No hay datos de entrenamiento.' );
        }

        $class_counts = array();
        $word_counts = array();
        $vocab = array();
        $total_docs = count( $samples );

        // Initialize
        foreach ( $samples as $sample ) {
            $cat = $sample['category'];
            if ( ! isset( $class_counts[$cat] ) ) {
                $class_counts[$cat] = 0;
                $word_counts[$cat] = array();
            }
            $class_counts[$cat]++;

            $tokens = $this->tokenize( $sample['text'] );
            foreach ( $tokens as $token ) {
                $vocab[$token] = true;
                if ( ! isset( $word_counts[$cat][$token] ) ) {
                    $word_counts[$cat][$token] = 0;
                }
                $word_counts[$cat][$token]++;
            }
        }

        // Calculate Priors and Likelihoods
        $model = array(
            'priors' => array(),
            'likelihoods' => array(),
            'vocab_size' => count( $vocab )
        );

        foreach ( $class_counts as $cat => $count ) {
            $model['priors'][$cat] = log( $count / $total_docs );
            
            $total_words_in_class = array_sum( $word_counts[$cat] );
            $model['likelihoods'][$cat] = array();

            foreach ( array_keys( $vocab ) as $term ) {
                $count_w_c = isset( $word_counts[$cat][$term] ) ? $word_counts[$cat][$term] : 0;
                // Laplace Smoothing (+1)
                $prob = log( ( $count_w_c + 1 ) / ( $total_words_in_class + count( $vocab ) ) );
                $model['likelihoods'][$cat][$term] = $prob;
            }
            // Default probability for unknown words in this class (smoothing only)
            $model['likelihoods'][$cat]['__unknown__'] = log( 1 / ( $total_words_in_class + count( $vocab ) ) );
        }

        update_option( self::OPTION_MODEL, $model );
        $this->model = $model;

        return array(
            'status' => 'success',
            'samples' => $total_docs,
            'classes' => count( $class_counts ),
            'vocab' => count( $vocab )
        );
    }

    /**
     * Predict category for text
     *
     * @param string $text
     * @return array|null Prediction result or null if no model
     */
    public function predict( $text ) {
        if ( ! $this->model ) {
            return null;
        }

        $tokens = $this->tokenize( $text );
        $scores = array();

        foreach ( $this->model['priors'] as $cat => $prior ) {
            $scores[$cat] = $prior;
            foreach ( $tokens as $token ) {
                if ( isset( $this->model['likelihoods'][$cat][$token] ) ) {
                    $scores[$cat] += $this->model['likelihoods'][$cat][$token];
                } else {
                    $scores[$cat] += $this->model['likelihoods'][$cat]['__unknown__'];
                }
            }
        }

        // Sort by score (highest first)
        arsort( $scores );
        $best_cat = array_key_first( $scores );

        return array(
            'category' => $best_cat,
            'scores' => $scores
        );
    }

    /**
     * Tokenize text
     *
     * @param string $text
     * @return array
     */
    private function tokenize( $text ) {
        $text = strtolower( $text );
        // Remove non-alphanumeric (keep spaces)
        $text = preg_replace( '/[^a-z0-9\s]/', '', $text );
        $tokens = explode( ' ', $text );
        
        $filtered = array();
        foreach ( $tokens as $t ) {
            $t = trim( $t );
            if ( ! empty( $t ) && ! in_array( $t, $this->stopwords ) && strlen($t) > 2 ) {
                $filtered[] = $t;
            }
        }
        return $filtered;
    }
}
