<?php
/**
 * AI Training Manager - Admin interface for Naive Bayes
 *
 * @package OsintDeck
 */

namespace OsintDeck\Presentation\Admin;

use OsintDeck\Domain\Service\NaiveBayesClassifier;

/**
 * Class AiTrainingManager
 * 
 * Handles AI Training interface
 */
class AiTrainingManager {

    /**
     * Classifier Service
     *
     * @var NaiveBayesClassifier
     */
    private $classifier;

    /**
     * Constructor
     *
     * @param NaiveBayesClassifier $classifier
     */
    public function __construct( NaiveBayesClassifier $classifier ) {
        $this->classifier = $classifier;
    }

    /**
     * Render the admin page
     */
    public function render() {
        $this->handle_actions();

        $samples = $this->classifier->get_samples();
        
        // Reverse for display (newest first)
        $display_samples = array_reverse( $samples, true );
        ?>
        <div class="wrap">
            <h1><?php _e( 'Entrenamiento IA (Naive Bayes)', 'osint-deck' ); ?></h1>
            
            <div class="osint-deck-ai-grid">
                <!-- Training Form -->
                <div class="card" style="max-width: 100%; padding: 20px;">
                    <h2><?php _e( 'Agregar Datos de Entrenamiento', 'osint-deck' ); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'osint_ai_add_sample', 'osint_ai_nonce' ); ?>
                        <input type="hidden" name="action" value="add_sample">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="sample_text"><?php _e( 'Texto de Entrada', 'osint-deck' ); ?></label></th>
                                <td>
                                    <input type="text" name="sample_text" id="sample_text" class="regular-text" placeholder="Ej: buscar ip 8.8.8.8" required>
                                    <p class="description"><?php _e( 'La frase o query que escribiría el usuario.', 'osint-deck' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="sample_category"><?php _e( 'Intención / Tipo Detectado', 'osint-deck' ); ?></label></th>
                                <td>
                                    <select name="sample_category" id="sample_category" required>
                                        <option value="">-- Seleccionar --</option>
                                        <option value="ipv4">IP (v4)</option>
                                        <option value="domain">Dominio</option>
                                        <option value="email">Email</option>
                                        <option value="username">Usuario</option>
                                        <option value="hash">Hash</option>
                                        <option value="phone">Teléfono</option>
                                        <option value="wallet">Cripto Wallet</option>
                                        <option value="leaks">Leaks / Brechas</option>
                                        <option value="reputation">Reputación</option>
                                        <option value="vuln">Vulnerabilidades</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button( __( 'Agregar Muestra', 'osint-deck' ), 'primary', 'submit_sample' ); ?>
                    </form>
                </div>

                <!-- Control Panel -->
                <div class="card" style="max-width: 100%; padding: 20px; margin-top: 20px; background: #f0f6fc;">
                    <h2><?php _e( 'Modelo y Pruebas', 'osint-deck' ); ?></h2>
                    
                    <div style="display: flex; gap: 20px; align-items: flex-start;">
                        <!-- Train Action -->
                        <div>
                            <form method="post" action="">
                                <?php wp_nonce_field( 'osint_ai_train', 'osint_ai_nonce' ); ?>
                                <input type="hidden" name="action" value="train_model">
                                <p><?php _e( 'Entrenar el modelo recalcula las probabilidades basadas en las muestras actuales.', 'osint-deck' ); ?></p>
                                <?php submit_button( __( 'Entrenar Modelo Ahora', 'osint-deck' ), 'secondary', 'train_model' ); ?>
                            </form>
                        </div>

                        <!-- Test Action -->
                        <div style="flex-grow: 1; border-left: 1px solid #ddd; padding-left: 20px;">
                            <form method="post" action="">
                                <?php wp_nonce_field( 'osint_ai_test', 'osint_ai_nonce' ); ?>
                                <input type="hidden" name="action" value="test_model">
                                <label for="test_text"><strong><?php _e( 'Probar Predicción:', 'osint-deck' ); ?></strong></label>
                                <div style="display: flex; gap: 10px; margin-top: 5px;">
                                    <input type="text" name="test_text" id="test_text" class="regular-text" placeholder="Escribe algo para probar...">
                                    <button type="submit" class="button"><?php _e( 'Predecir', 'osint-deck' ); ?></button>
                                </div>
                            </form>
                            <?php if ( isset( $_GET['prediction'] ) ) : ?>
                                <div style="margin-top: 10px; padding: 10px; background: #fff; border: 1px solid #46b450; border-left-width: 4px;">
                                    <strong><?php _e( 'Resultado:', 'osint-deck' ); ?></strong> <?php echo esc_html( $_GET['prediction'] ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;">
                    
                    <!-- Load Defaults -->
                    <div>
                         <h3><?php _e( 'Cargar Datos Iniciales', 'osint-deck' ); ?></h3>
                         <p class="description"><?php _e( 'Si la base de datos está vacía, puedes cargar un conjunto de frases predefinidas (JSON).', 'osint-deck' ); ?></p>
                         <form method="post" action="" style="margin-top: 10px;">
                            <?php wp_nonce_field( 'osint_ai_load_defaults', 'osint_ai_nonce' ); ?>
                            <input type="hidden" name="action" value="load_defaults">
                            <?php submit_button( __( 'Importar Dataset Predeterminado', 'osint-deck' ), 'secondary', 'load_defaults', false ); ?>
                        </form>
                    </div>
                </div>

                <!-- Samples List -->
                <h2 style="margin-top: 30px;"><?php _e( 'Datos de Entrenamiento Existentes', 'osint-deck' ); ?> (<?php echo count($samples); ?>)</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e( 'Texto', 'osint-deck' ); ?></th>
                            <th><?php _e( 'Categoría', 'osint-deck' ); ?></th>
                            <th style="width: 100px;"><?php _e( 'Acciones', 'osint-deck' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $display_samples ) ) : ?>
                            <tr><td colspan="3"><?php _e( 'No hay datos aún.', 'osint-deck' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $display_samples as $index => $sample ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $sample['text'] ); ?></td>
                                    <td><span class="osint-badge"><?php echo esc_html( $sample['category'] ); ?></span></td>
                                    <td>
                                        <form method="post" action="" style="display:inline;">
                                            <?php wp_nonce_field( 'osint_ai_delete', 'osint_ai_nonce' ); ?>
                                            <input type="hidden" name="action" value="delete_sample">
                                            <input type="hidden" name="sample_index" value="<?php echo esc_attr( $index ); ?>">
                                            <button type="submit" class="button-link-delete" onclick="return confirm('¿Borrar?');"><?php _e( 'Borrar', 'osint-deck' ); ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <style>
            .osint-badge {
                background: #2271b1;
                color: #fff;
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 11px;
                text-transform: uppercase;
            }
        </style>
        <?php
    }

    /**
     * Handle form actions
     */
    private function handle_actions() {
        if ( ! isset( $_POST['action'] ) || ! isset( $_POST['osint_ai_nonce'] ) ) {
            return;
        }

        if ( $_POST['action'] === 'add_sample' && check_admin_referer( 'osint_ai_add_sample', 'osint_ai_nonce' ) ) {
            $text = sanitize_text_field( $_POST['sample_text'] );
            $cat = sanitize_text_field( $_POST['sample_category'] );
            if ( $text && $cat ) {
                $this->classifier->add_sample( $text, $cat );
                echo '<div class="notice notice-success is-dismissible"><p>Muestra agregada.</p></div>';
            }
        }

        if ( $_POST['action'] === 'delete_sample' && check_admin_referer( 'osint_ai_delete', 'osint_ai_nonce' ) ) {
            $idx = intval( $_POST['sample_index'] );
            $this->classifier->delete_sample( $idx );
            echo '<div class="notice notice-success is-dismissible"><p>Muestra eliminada.</p></div>';
        }

        if ( $_POST['action'] === 'train_model' && check_admin_referer( 'osint_ai_train', 'osint_ai_nonce' ) ) {
            $res = $this->classifier->train();
            if ( $res['status'] === 'success' ) {
                echo '<div class="notice notice-success is-dismissible"><p>Modelo entrenado exitosamente. (Vocabulario: ' . $res['vocab'] . ')</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . $res['msg'] . '</p></div>';
            }
        }

        if ( $_POST['action'] === 'test_model' && check_admin_referer( 'osint_ai_test', 'osint_ai_nonce' ) ) {
            $text = sanitize_text_field( $_POST['test_text'] );
            $res = $this->classifier->predict( $text );
            
            // Redirect to avoid resubmission and show result in URL (simple approach)
            if ( $res && isset( $res['category'] ) ) {
                $confidence = 'N/A';
                if ( isset( $res['scores'] ) && is_array( $res['scores'] ) && isset( $res['scores'][$res['category']] ) ) {
                    $confidence = number_format( exp( $res['scores'][$res['category']] ), 4 );
                }
                echo '<div class="notice notice-info is-dismissible"><p>Predicción para "' . esc_html( $text ) . '": <strong>' . esc_html( $res['category'] ) . '</strong> (Confianza: ' . $confidence . ')</p></div>';
            } else {
                echo '<div class="notice notice-warning is-dismissible"><p>No se pudo realizar la predicción. Asegúrate de que el modelo esté entrenado.</p></div>';
            }
        }

        if ( $_POST['action'] === 'load_defaults' && check_admin_referer( 'osint_ai_load_defaults', 'osint_ai_nonce' ) ) {
            $json_file = OSINT_DECK_PLUGIN_DIR . 'data/training_data.json';
            if ( file_exists( $json_file ) ) {
                $content = file_get_contents( $json_file );
                $data = json_decode( $content, true );
                
                if ( is_array( $data ) ) {
                    $existing_samples = $this->classifier->get_samples();
                    // Create a simple signature map for existing samples to avoid duplicates
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
                                $this->classifier->add_sample( $item['text'], $item['category'] );
                                $existing_signatures[] = $sig; // Add to local list to avoid dupes within JSON itself
                                $count++;
                            } else {
                                $skipped++;
                            }
                        }
                    }
                    
                    $msg = sprintf( __( 'Se han importado %d muestras nuevas.', 'osint-deck' ), $count );
                    if ( $skipped > 0 ) {
                        $msg .= ' ' . sprintf( __( '(%d omitidas por duplicado)', 'osint-deck' ), $skipped );
                    }
                    
                    echo '<div class="notice notice-success is-dismissible"><p>' . $msg . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __( 'El archivo JSON no es válido.', 'osint-deck' ) . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __( 'No se encontró el archivo data/training_data.json.', 'osint-deck' ) . '</p></div>';
            }
        }
    }
}
