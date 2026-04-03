<?php
/**
 * Registro persistente de fallos al descargar favicons (para el mantenimiento en admin).
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Service;

use OsintDeck\Domain\Repository\ToolRepositoryInterface;

/**
 * option: osint_deck_icon_fetch_failures
 */
class IconFetchFailureStore {

    public const OPTION_KEY = 'osint_deck_icon_fetch_failures';

    /**
     * @return array<string, array{error: string, context_url: string, source: string, at: string}>
     */
    public static function get_raw() {
        $v = get_option( self::OPTION_KEY, array() );
        return is_array( $v ) ? $v : array();
    }

    /**
     * @param int    $tool_id      ID herramienta (_db_id).
     * @param string $context_url  URL que se intentó descargar (la guardada en el mazo o la manual).
     * @param string $error_message Mensaje ya traducido para mostrar en admin.
     * @param string $source        "auto" | "manual".
     * @return void
     */
    public static function record_failure( $tool_id, $context_url, $error_message, $source = 'auto' ) {
        $tool_id = (int) $tool_id;
        if ( $tool_id <= 0 ) {
            return;
        }

        $all                 = self::get_raw();
        $all[ (string) $tool_id ] = array(
            'error'        => (string) $error_message,
            'context_url'  => (string) $context_url,
            'source'       => $source === 'manual' ? 'manual' : 'auto',
            'at'           => gmdate( 'c' ),
        );
        update_option( self::OPTION_KEY, $all, false );
    }

    /**
     * @param int $tool_id ID herramienta.
     * @return void
     */
    public static function clear( $tool_id ) {
        $tool_id = (int) $tool_id;
        if ( $tool_id <= 0 ) {
            return;
        }

        $all = self::get_raw();
        $k   = (string) $tool_id;
        if ( isset( $all[ $k ] ) ) {
            unset( $all[ $k ] );
            update_option( self::OPTION_KEY, $all, false );
        }
    }

    /**
     * Elimina entradas si la herramienta ya no existe o el favicon ya está en uploads.
     *
     * @param ToolRepositoryInterface $repo Repositorio.
     * @return void
     */
    public static function prune_stale( ToolRepositoryInterface $repo ) {
        $upload = wp_upload_dir();
        $base   = ( ! isset( $upload['error'] ) || empty( $upload['error'] ) ) && ! empty( $upload['baseurl'] )
            ? (string) $upload['baseurl']
            : '';

        $all     = self::get_raw();
        $changed = false;

        foreach ( array_keys( $all ) as $tid ) {
            $id = (int) $tid;
            if ( $id <= 0 ) {
                unset( $all[ $tid ] );
                $changed = true;
                continue;
            }

            $tool = $repo->get_tool_by_id( $id );
            if ( ! $tool ) {
                unset( $all[ $tid ] );
                $changed = true;
                continue;
            }

            $fav = isset( $tool['favicon'] ) ? (string) $tool['favicon'] : '';
            if ( $base !== '' && $fav !== '' && strpos( $fav, $base ) !== false ) {
                unset( $all[ $tid ] );
                $changed = true;
            }
        }

        if ( $changed ) {
            update_option( self::OPTION_KEY, $all, false );
        }
    }

    /**
     * Añade a cada ítem last_error, last_context_url, last_error_source.
     *
     * @param array<int, array<string, mixed>> $items Desde collect_remote_icon_items.
     * @return array<int, array<string, mixed>>
     */
    public static function merge_into_items( array $items ) {
        $failures = self::get_raw();

        foreach ( $items as &$it ) {
            $id = isset( $it['id'] ) ? (string) (int) $it['id'] : '';
            if ( $id !== '' && isset( $failures[ $id ] ) ) {
                $f                   = $failures[ $id ];
                $it['last_error']    = isset( $f['error'] ) ? (string) $f['error'] : '';
                $it['last_context_url'] = isset( $f['context_url'] ) ? (string) $f['context_url'] : '';
                $it['last_error_source'] = isset( $f['source'] ) ? (string) $f['source'] : 'auto';
            } else {
                $it['last_error']       = '';
                $it['last_context_url'] = '';
                $it['last_error_source'] = '';
            }
        }
        unset( $it );

        return $items;
    }

    /**
     * Orden: primero las que tienen error registrado, luego por nombre.
     *
     * @param array<int, array<string, mixed>> $items Ítems con last_error ya fusionado.
     * @return array<int, array<string, mixed>>
     */
    public static function sort_errors_first( array $items ) {
        usort(
            $items,
            function ( $a, $b ) {
                $ae = ! empty( $a['last_error'] );
                $be = ! empty( $b['last_error'] );
                if ( $ae !== $be ) {
                    return $ae ? -1 : 1;
                }
                $an = isset( $a['name'] ) ? (string) $a['name'] : '';
                $bn = isset( $b['name'] ) ? (string) $b['name'] : '';
                return strcasecmp( $an, $bn );
            }
        );

        return $items;
    }
}
