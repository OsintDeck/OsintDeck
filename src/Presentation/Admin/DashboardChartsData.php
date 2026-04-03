<?php
/**
 * Agrupa datos del listado de herramientas para gráficos del dashboard.
 *
 * @package OsintDeck
 */

namespace OsintDeck\Presentation\Admin;

use OsintDeck\Domain\Repository\ToolRepositoryInterface;
use OsintDeck\Domain\Repository\CategoryRepositoryInterface;

/**
 * Class DashboardChartsData
 */
class DashboardChartsData {

    /**
     * @param ToolRepositoryInterface     $tool_repository     Repo herramientas.
     * @param CategoryRepositoryInterface $category_repository Repo categorías.
     * @return array<string, mixed> Datos listos para wp_localize_script.
     */
    public static function build( ToolRepositoryInterface $tool_repository, CategoryRepositoryInterface $category_repository ) {
        $tools = $tool_repository->get_all_tools();
        $n     = count( $tools );
        return self::build_from_tools(
            $tools,
            $category_repository,
            array(
                'filtered_count' => $n,
                'total_before'   => $n,
                'summary_lines'  => array(),
            )
        );
    }

    /**
     * Construye el payload Chart.js a partir de un array de herramientas ya filtradas.
     *
     * @param array<int, array>           $tools              Herramientas.
     * @param CategoryRepositoryInterface $category_repository Repo categorías (etiquetas).
     * @param array<string, mixed>       $meta               filtered_count, total_before, summary_lines.
     * @return array<string, mixed>
     */
    public static function build_from_tools( array $tools, CategoryRepositoryInterface $category_repository, array $meta = array() ) {
        $categories = $category_repository->get_all_categories();

        $code_to_label = array();
        foreach ( $categories as $row ) {
            $code = isset( $row['code'] ) ? (string) $row['code'] : '';
            if ( '' !== $code ) {
                $code_to_label[ $code ] = isset( $row['label'] ) ? (string) $row['label'] : ( isset( $row['name'] ) ? (string) $row['name'] : $code );
            }
        }

        $by_cat          = array();
        $clicks_per_tool = array();

        $totals          = self::aggregate_engagement_totals( $tools );
        $total_clicks    = $totals['clicks'];
        $total_likes     = $totals['likes'];
        $total_favs      = $totals['favorites'];
        $total_reports   = $totals['reports'];

        foreach ( $tools as $t ) {
            $code = self::resolve_category_bucket( $t, $code_to_label );
            if ( ! isset( $by_cat[ $code ] ) ) {
                $by_cat[ $code ] = 0;
            }
            $by_cat[ $code ]++;

            $st = isset( $t['stats'] ) && is_array( $t['stats'] ) ? $t['stats'] : array();
            $c  = isset( $st['clicks'] ) ? (int) $st['clicks'] : 0;

            $name = isset( $t['name'] ) ? (string) $t['name'] : '';
            if ( '' !== $name ) {
                $clicks_per_tool[] = array(
                    'name'   => $name,
                    'clicks' => $c,
                );
            }
        }

        usort(
            $clicks_per_tool,
            static function ( $a, $b ) {
                return $b['clicks'] <=> $a['clicks'];
            }
        );
        $clicks_per_tool = array_slice( $clicks_per_tool, 0, 10 );

        arsort( $by_cat, SORT_NUMERIC );
        $cat_labels = array();
        $cat_counts = array();
        $max_slices = 10;
        $idx        = 0;
        $others     = 0;
        foreach ( $by_cat as $code => $n ) {
            if ( $idx < $max_slices ) {
                if ( '' === $code ) {
                    $cat_labels[] = __( 'Sin categoría', 'osint-deck' );
                } else {
                    $cat_labels[] = isset( $code_to_label[ $code ] ) ? $code_to_label[ $code ] : $code;
                }
                $cat_counts[] = (int) $n;
                $idx++;
            } else {
                $others += (int) $n;
            }
        }
        if ( $others > 0 ) {
            $cat_labels[] = __( 'Otros', 'osint-deck' );
            $cat_counts[] = $others;
        }

        $top_labels = array();
        $top_clicks = array();
        foreach ( $clicks_per_tool as $row ) {
            $top_labels[] = $row['name'];
            $top_clicks[] = $row['clicks'];
        }

        $summary_lines = isset( $meta['summary_lines'] ) && is_array( $meta['summary_lines'] ) ? $meta['summary_lines'] : array();

        return array(
            'i18n'         => array(
                'titleCategories'         => __( 'Herramientas por categoría', 'osint-deck' ),
                'titleTopClicks'        => __( 'Top 10 por clics', 'osint-deck' ),
                'titleEngagementSoft'   => __( 'Otras interacciones (totales)', 'osint-deck' ),
                'subtitleEngagementSoft'=> __( 'Sin clics: escala comparable entre me gusta, favoritos y reportes.', 'osint-deck' ),
                'empty'                 => __( 'Todavía no hay datos suficientes para este gráfico.', 'osint-deck' ),
                'labelClicks'           => __( 'Clics', 'osint-deck' ),
                'labelLikes'            => __( 'Me gusta', 'osint-deck' ),
                'labelFavorites'        => __( 'Favoritos', 'osint-deck' ),
                'labelReports'          => __( 'Reportes', 'osint-deck' ),
            ),
            'meta'         => array(
                'filtered_count' => (int) ( $meta['filtered_count'] ?? count( $tools ) ),
                'total_before'   => (int) ( $meta['total_before'] ?? count( $tools ) ),
                'summary_lines'  => $summary_lines,
            ),
            'kpis'         => $totals,
            'categories'   => array(
                'labels' => $cat_labels,
                'counts' => $cat_counts,
            ),
            'topClicks'    => array(
                'labels' => $top_labels,
                'counts' => $top_clicks,
            ),
            'engagementComparable' => array(
                'labels' => array(
                    __( 'Me gusta', 'osint-deck' ),
                    __( 'Favoritos', 'osint-deck' ),
                    __( 'Reportes', 'osint-deck' ),
                ),
                'values' => array( $total_likes, $total_favs, $total_reports ),
            ),
            'hasCategoryData'         => count( $cat_counts ) > 0 && array_sum( $cat_counts ) > 0,
            'hasClicksData'           => count( $top_clicks ) > 0 && array_sum( $top_clicks ) > 0,
            'hasEngagementComparable' => ( $total_likes + $total_favs + $total_reports ) > 0,
        );
    }

    /**
     * Totales de stats agregadas (mismo criterio que los gráficos de métricas).
     *
     * @param array<int, array> $tools Herramientas filtradas o todas.
     * @return array{clicks: int, likes: int, favorites: int, reports: int}
     */
    public static function aggregate_engagement_totals( array $tools ) {
        $out = array(
            'clicks'    => 0,
            'likes'     => 0,
            'favorites' => 0,
            'reports'   => 0,
        );

        foreach ( $tools as $t ) {
            $st = isset( $t['stats'] ) && is_array( $t['stats'] ) ? $t['stats'] : array();
            $out['clicks']    += isset( $st['clicks'] ) ? (int) $st['clicks'] : 0;
            $out['likes']     += isset( $st['likes'] ) ? (int) $st['likes'] : 0;
            $out['favorites'] += isset( $st['favorites'] ) ? (int) $st['favorites'] : 0;
            $out['reports']   += isset( $st['reports'] ) ? (int) $st['reports'] : 0;
        }

        return $out;
    }

    /**
     * Clave de categoría para agrupar (código preferido).
     *
     * @param array $tool          Herramienta.
     * @param array $code_to_label Mapa código => etiqueta.
     * @return string Código o cadena vacía si no se pudo resolver.
     */
    private static function resolve_category_bucket( array $tool, array $code_to_label ) {
        if ( ! empty( $tool['category'] ) ) {
            $c = (string) $tool['category'];
            if ( isset( $code_to_label[ $c ] ) ) {
                return $c;
            }
            foreach ( $code_to_label as $code => $label ) {
                if ( strcasecmp( $label, $c ) === 0 ) {
                    return $code;
                }
            }
            return $c;
        }
        if ( ! empty( $tool['cards'][0]['category_code'] ) ) {
            return (string) $tool['cards'][0]['category_code'];
        }
        if ( ! empty( $tool['cards'][0]['category'] ) ) {
            $maybe = (string) $tool['cards'][0]['category'];
            if ( isset( $code_to_label[ $maybe ] ) ) {
                return $maybe;
            }
            foreach ( $code_to_label as $code => $label ) {
                if ( strcasecmp( $label, $maybe ) === 0 ) {
                    return $code;
                }
            }
            return $maybe;
        }
        return '';
    }
}
