<?php
/**
 * Pantalla Métricas y reportes (Chart.js + filtros).
 *
 * @package OsintDeck
 */

namespace OsintDeck\Presentation\Admin;

use OsintDeck\Domain\Repository\ToolRepositoryInterface;
use OsintDeck\Domain\Repository\CategoryRepositoryInterface;

/**
 * Class MetricsScreen
 */
class MetricsScreen {

    /**
     * @var ToolRepositoryInterface
     */
    private $tool_repository;

    /**
     * @var CategoryRepositoryInterface
     */
    private $category_repository;

    /**
     * @param ToolRepositoryInterface     $tool_repository     Repo.
     * @param CategoryRepositoryInterface $category_repository Repo categorías.
     */
    public function __construct( ToolRepositoryInterface $tool_repository, CategoryRepositoryInterface $category_repository ) {
        $this->tool_repository     = $tool_repository;
        $this->category_repository = $category_repository;
    }

    /**
     * Hooks.
     *
     * @return void
     */
    public function init() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_charts' ) );
    }

    /**
     * Encola Chart.js solo en esta página.
     *
     * @param string $hook Hook admin.
     * @return void
     */
    public function enqueue_charts( $hook ) {
        // Slug estable; si otro plugin altera el hook, alcanza con el sufijo de esta página.
        if ( ! is_string( $hook ) || strpos( $hook, 'osint-deck-metrics' ) === false ) {
            return;
        }

        $chart_path = plugin_dir_path( OSINT_DECK_PLUGIN_FILE ) . 'assets/vendor/chart.js/chart.umd.min.js';
        if ( is_readable( $chart_path ) ) {
            $chart_url = plugin_dir_url( OSINT_DECK_PLUGIN_FILE ) . 'assets/vendor/chart.js/chart.umd.min.js';
        } else {
            /* Respaldo por si falta el archivo en el paquete */
            $chart_url = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
        }

        wp_enqueue_script(
            'osint-deck-chartjs',
            $chart_url,
            array(),
            '4.4.1',
            true
        );
        wp_enqueue_script(
            'osint-deck-dashboard-charts',
            plugin_dir_url( OSINT_DECK_PLUGIN_FILE ) . 'assets/js/admin-dashboard-charts.js',
            array( 'osint-deck-chartjs' ),
            defined( 'OSINT_DECK_VERSION' ) ? OSINT_DECK_VERSION : '1',
            true
        );

        $filtered = $this->get_filtered_tools();
        $summary  = $this->get_filter_summary_strings();

        wp_localize_script(
            'osint-deck-dashboard-charts',
            'osintDeckDashboardCharts',
            DashboardChartsData::build_from_tools(
                $filtered,
                $this->category_repository,
                array(
                    'filtered_count' => count( $filtered ),
                    'total_before'   => count( $this->tool_repository->get_all_tools() ),
                    'summary_lines'  => $summary,
                )
            )
        );
    }

    /**
     * Lee filtros desde la query.
     *
     * @return array<string, string>
     */
    private function get_request_filters() {
        return array(
            'category'     => isset( $_GET['m_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['m_cat'] ) ) : '',
            'status'       => isset( $_GET['m_status'] ) ? sanitize_text_field( wp_unslash( $_GET['m_status'] ) ) : '',
            'created_from' => isset( $_GET['m_from'] ) ? sanitize_text_field( wp_unslash( $_GET['m_from'] ) ) : '',
            'created_to'   => isset( $_GET['m_to'] ) ? sanitize_text_field( wp_unslash( $_GET['m_to'] ) ) : '',
            'search'       => isset( $_GET['m_s'] ) ? sanitize_text_field( wp_unslash( $_GET['m_s'] ) ) : '',
        );
    }

    /**
     * @return array<int, array>
     */
    private function get_filtered_tools() {
        return $this->filter_tools_list( $this->tool_repository->get_all_tools() );
    }

    /**
     * @param array<int, array> $all Herramientas completas.
     * @return array<int, array>
     */
    private function filter_tools_list( array $all ) {
        $filters  = $this->get_request_filters();
        $cats     = $this->category_repository->get_all_categories();

        $filter_cat_label = '';
        foreach ( $cats as $c ) {
            if ( ( $c['code'] ?? '' ) === $filters['category'] ) {
                $filter_cat_label = $c['label'] ?? $c['name'] ?? '';
                break;
            }
        }

        return array_values(
            array_filter(
                $all,
                function ( $tool ) use ( $filters, $filter_cat_label ) {
                    return $this->tool_matches_filters( $tool, $filters, $filter_cat_label );
                }
            )
        );
    }

    /**
     * @param array  $tool             Herramienta.
     * @param array  $filters          Filtros request.
     * @param string $filter_cat_label Etiqueta de categoría seleccionada.
     * @return bool
     */
    private function tool_matches_filters( array $tool, array $filters, $filter_cat_label ) {
        if ( $filters['search'] !== '' ) {
            $name = $tool['name'] ?? '';
            if ( stripos( $name, $filters['search'] ) === false ) {
                return false;
            }
        }

        if ( $filters['status'] !== '' ) {
            $st = $tool['preview_status'] ?? 'unaudited';
            if ( $st !== $filters['status'] ) {
                return false;
            }
        }

        if ( $filters['category'] !== '' ) {
            $tool_cat = $tool['category'] ?? '';
            $match    = ( strcasecmp( $tool_cat, $filters['category'] ) === 0 )
                || ( $filter_cat_label && strcasecmp( $tool_cat, $filter_cat_label ) === 0 );

            if ( ! $match && ! empty( $tool['cards'] ) ) {
                foreach ( $tool['cards'] as $card ) {
                    $c_cat  = $card['category'] ?? '';
                    $c_code = $card['category_code'] ?? '';
                    if ( strcasecmp( (string) $c_cat, $filters['category'] ) === 0
                        || strcasecmp( (string) $c_code, $filters['category'] ) === 0
                        || ( $filter_cat_label && strcasecmp( (string) $c_cat, $filter_cat_label ) === 0 ) ) {
                        $match = true;
                        break;
                    }
                }
            }

            if ( ! $match ) {
                return false;
            }
        }

        if ( $filters['created_from'] !== '' || $filters['created_to'] !== '' ) {
            $raw = $tool['_db_created_at'] ?? '';
            $d   = strlen( $raw ) >= 10 ? substr( $raw, 0, 10 ) : '';
            if ( '' === $d ) {
                return false;
            }
            if ( $filters['created_from'] !== '' && $d < $filters['created_from'] ) {
                return false;
            }
            if ( $filters['created_to'] !== '' && $d > $filters['created_to'] ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Líneas de texto para el resumen de filtros activos.
     *
     * @return array<int, string>
     */
    private function get_filter_summary_strings() {
        $f     = $this->get_request_filters();
        $lines = array();
        if ( $f['category'] !== '' ) {
            foreach ( $this->category_repository->get_all_categories() as $c ) {
                if ( ( $c['code'] ?? '' ) === $f['category'] ) {
                    $lines[] = sprintf(
                        /* translators: %s: category label */
                        __( 'Categoría: %s', 'osint-deck' ),
                        $c['label'] ?? $c['name'] ?? $f['category']
                    );
                    break;
                }
            }
        }
        if ( $f['status'] !== '' ) {
            $labels = array(
                'unaudited' => __( 'No auditado', 'osint-deck' ),
                'ok'        => __( 'OK', 'osint-deck' ),
                'blocked'   => __( 'Bloqueado', 'osint-deck' ),
            );
            $lines[] = sprintf( __( 'Estado preview: %s', 'osint-deck' ), $labels[ $f['status'] ] ?? $f['status'] );
        }
        if ( $f['search'] !== '' ) {
            $lines[] = sprintf( __( 'Nombre contiene: «%s»', 'osint-deck' ), $f['search'] );
        }
        if ( $f['created_from'] !== '' || $f['created_to'] !== '' ) {
            $lines[] = sprintf(
                __( 'Alta en BD: desde %1$s hasta %2$s', 'osint-deck' ),
                $f['created_from'] !== '' ? $f['created_from'] : '—',
                $f['created_to'] !== '' ? $f['created_to'] : '—'
            );
        }

        return $lines;
    }

    /**
     * Salida HTML.
     *
     * @return void
     */
    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tenés permisos para ver esta página.', 'osint-deck' ) );
        }

        $filters         = $this->get_request_filters();
        $categories      = $this->category_repository->get_all_categories();
        $has_any_filter  = $filters['category'] !== '' || $filters['status'] !== '' || $filters['search'] !== ''
            || $filters['created_from'] !== '' || $filters['created_to'] !== '';
        ?>
        <div class="wrap osint-deck-admin-wrap osint-deck-metrics-page">
            <h1><?php esc_html_e( 'Métricas y reportes', 'osint-deck' ); ?></h1>
            <p class="osint-deck-dashboard-intro">
                <?php esc_html_e( 'Estadísticas acumuladas por herramienta (clics, me gusta, favoritos, reportes). Podés acotar el universo con filtros; los totales siguen siendo históricos en cada registro, no hay series por día.', 'osint-deck' ); ?>
            </p>

            <form method="get" class="osint-deck-filters osint-deck-metrics-filters" aria-label="<?php esc_attr_e( 'Filtrar métricas', 'osint-deck' ); ?>">
                <input type="hidden" name="page" value="osint-deck-metrics">

                <div class="osint-deck-filters__row">
                    <div class="osint-deck-filters__field">
                        <label for="m-cat" class="osint-deck-filters__label"><?php esc_html_e( 'Categoría', 'osint-deck' ); ?></label>
                        <select name="m_cat" id="m-cat">
                            <option value=""><?php esc_html_e( 'Todas', 'osint-deck' ); ?></option>
                            <?php foreach ( $categories as $cat ) : ?>
                                <?php
                                $code  = $cat['code'] ?? '';
                                $label = $cat['label'] ?? $cat['name'] ?? $code;
                                ?>
                                <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $filters['category'], $code ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="osint-deck-filters__field">
                        <label for="m-status" class="osint-deck-filters__label"><?php esc_html_e( 'Preview', 'osint-deck' ); ?></label>
                        <select name="m_status" id="m-status">
                            <option value=""><?php esc_html_e( 'Todos', 'osint-deck' ); ?></option>
                            <option value="ok" <?php selected( $filters['status'], 'ok' ); ?>><?php esc_html_e( 'OK', 'osint-deck' ); ?></option>
                            <option value="blocked" <?php selected( $filters['status'], 'blocked' ); ?>><?php esc_html_e( 'Bloqueado', 'osint-deck' ); ?></option>
                            <option value="unaudited" <?php selected( $filters['status'], 'unaudited' ); ?>><?php esc_html_e( 'No auditado', 'osint-deck' ); ?></option>
                        </select>
                    </div>

                    <div class="osint-deck-filters__field osint-deck-filters__field--tool-name">
                        <label for="m-s" class="osint-deck-filters__label"><?php esc_html_e( 'Nombre', 'osint-deck' ); ?></label>
                        <div class="osint-metrics-name-field">
                            <input
                                type="search"
                                name="m_s"
                                id="m-s"
                                class="regular-text"
                                value="<?php echo esc_attr( $filters['search'] ); ?>"
                                placeholder="<?php esc_attr_e( 'Escribí parte del nombre…', 'osint-deck' ); ?>"
                                autocomplete="off"
                                autocorrect="off"
                                autocapitalize="off"
                                spellcheck="false"
                                aria-autocomplete="list"
                                aria-expanded="false"
                                aria-controls="osint-metrics-m-s-listbox"
                                title="<?php esc_attr_e( 'Al menos 2 caracteres: aparecen sugerencias para elegir el nombre exacto.', 'osint-deck' ); ?>"
                            >
                            <ul id="osint-metrics-m-s-listbox" class="osint-metrics-suggest" role="listbox" hidden aria-hidden="true"></ul>
                        </div>
                    </div>
                </div>

                <div class="osint-deck-filters__row">
                    <div class="osint-deck-filters__field osint-deck-filters__field--date">
                        <label for="m-from" class="osint-deck-filters__label"><?php esc_html_e( 'Alta desde', 'osint-deck' ); ?></label>
                        <input type="date" name="m_from" id="m-from" class="osint-deck-filters__date" value="<?php echo esc_attr( $filters['created_from'] ); ?>">
                    </div>
                    <div class="osint-deck-filters__field osint-deck-filters__field--date">
                        <label for="m-to" class="osint-deck-filters__label"><?php esc_html_e( 'Alta hasta', 'osint-deck' ); ?></label>
                        <input type="date" name="m_to" id="m-to" class="osint-deck-filters__date" value="<?php echo esc_attr( $filters['created_to'] ); ?>">
                    </div>

                    <div class="osint-deck-filters__actions">
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Aplicar filtros', 'osint-deck' ); ?></button>
                        <?php if ( $has_any_filter ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck-metrics' ) ); ?>" class="button"><?php esc_html_e( 'Limpiar', 'osint-deck' ); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="description osint-deck-filters__dates-hint">
                    <?php esc_html_e( 'Por fecha de creación del registro en la base (no por mes de clics).', 'osint-deck' ); ?>
                </p>
            </form>

            <?php
            $all_tools      = $this->tool_repository->get_all_tools();
            $filtered_tools = $this->filter_tools_list( $all_tools );
            $total_all      = count( $all_tools );
            ?>

            <div class="osint-deck-metrics-summary osint-card-panel">
                <p class="osint-deck-metrics-count">
                    <?php
                    printf(
                        /* translators: 1: filtered count, 2: total tools */
                        esc_html__( 'Mostrando %1$d herramientas (de %2$d totales) según los filtros.', 'osint-deck' ),
                        count( $filtered_tools ),
                        $total_all
                    );
                    ?>
                </p>
                <?php if ( ! empty( $this->get_filter_summary_strings() ) ) : ?>
                    <ul class="osint-deck-metrics-filter-list">
                        <?php foreach ( $this->get_filter_summary_strings() as $line ) : ?>
                            <li><?php echo esc_html( $line ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p class="description osint-deck-metrics-scope">
                    <?php esc_html_e( 'Los totales y gráficos usan las estadísticas guardadas en cada herramienta (histórico acumulado). Los clics se muestran aparte: suelen ser órdenes de magnitud mayores y en un mismo gráfico de barras “aplastan” el resto.', 'osint-deck' ); ?>
                </p>
            </div>

            <?php $kpis = DashboardChartsData::aggregate_engagement_totals( $filtered_tools ); ?>
            <section class="osint-metrics-kpis" aria-label="<?php esc_attr_e( 'Resumen de totales', 'osint-deck' ); ?>">
                <div class="osint-kpi-card osint-kpi-card--clicks">
                    <span class="osint-kpi-card__value"><?php echo esc_html( number_format_i18n( (int) $kpis['clicks'] ) ); ?></span>
                    <span class="osint-kpi-card__label"><?php esc_html_e( 'Clics (total)', 'osint-deck' ); ?></span>
                </div>
                <div class="osint-kpi-card osint-kpi-card--likes">
                    <span class="osint-kpi-card__value"><?php echo esc_html( number_format_i18n( (int) $kpis['likes'] ) ); ?></span>
                    <span class="osint-kpi-card__label"><?php esc_html_e( 'Me gusta', 'osint-deck' ); ?></span>
                </div>
                <div class="osint-kpi-card osint-kpi-card--favorites">
                    <span class="osint-kpi-card__value"><?php echo esc_html( number_format_i18n( (int) $kpis['favorites'] ) ); ?></span>
                    <span class="osint-kpi-card__label"><?php esc_html_e( 'Favoritos', 'osint-deck' ); ?></span>
                </div>
                <div class="osint-kpi-card osint-kpi-card--reports">
                    <span class="osint-kpi-card__value"><?php echo esc_html( number_format_i18n( (int) $kpis['reports'] ) ); ?></span>
                    <span class="osint-kpi-card__label"><?php esc_html_e( 'Reportes', 'osint-deck' ); ?></span>
                </div>
            </section>

            <div class="osint-deck-charts-grid osint-deck-charts-grid--metrics">
                <div class="osint-chart-card osint-chart-card--doughnut osint-chart-card--panel">
                    <h2 class="osint-chart-card__title"><?php esc_html_e( 'Distribución por categoría', 'osint-deck' ); ?></h2>
                    <p class="osint-chart-card__hint"><?php esc_html_e( 'Cantidad de herramientas en cada categoría (con el filtro actual).', 'osint-deck' ); ?></p>
                    <div class="osint-chart-canvas-wrap osint-chart-canvas-wrap--doughnut">
                        <canvas id="osint-chart-categories" aria-label="<?php esc_attr_e( 'Gráfico de herramientas por categoría', 'osint-deck' ); ?>"></canvas>
                    </div>
                </div>
                <div class="osint-chart-card osint-chart-card--panel osint-chart-card--topclicks">
                    <h2 class="osint-chart-card__title"><?php esc_html_e( 'Top 10 por clics', 'osint-deck' ); ?></h2>
                    <p class="osint-chart-card__hint"><?php esc_html_e( 'Solo clics: escala propia por herramienta.', 'osint-deck' ); ?></p>
                    <div class="osint-chart-canvas-wrap osint-chart-canvas-wrap--topclicks">
                        <canvas id="osint-chart-clicks" aria-label="<?php esc_attr_e( 'Gráfico de clics por herramienta', 'osint-deck' ); ?>"></canvas>
                    </div>
                </div>
                <div class="osint-chart-card osint-chart-card--wide osint-chart-card--panel">
                    <h2 class="osint-chart-card__title"><?php esc_html_e( 'Otras interacciones (totales)', 'osint-deck' ); ?></h2>
                    <p class="osint-chart-card__hint"><?php esc_html_e( 'Me gusta, favoritos y reportes en la misma escala para comparar sin mezclar con los clics.', 'osint-deck' ); ?></p>
                    <div class="osint-chart-canvas-wrap osint-chart-canvas-wrap--comparable">
                        <canvas id="osint-chart-engagement-soft" aria-label="<?php esc_attr_e( 'Gráfico de me gusta, favoritos y reportes', 'osint-deck' ); ?>"></canvas>
                    </div>
                </div>
            </div>

            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=osint-deck' ) ); ?>" class="button"><?php esc_html_e( '← Volver al dashboard', 'osint-deck' ); ?></a>
            </p>
        </div>
        <?php
    }
}
