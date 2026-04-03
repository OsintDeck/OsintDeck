<?php
/**
 * Me gusta por visitante anónimo (transient por huella fp del cliente).
 *
 * @package OsintDeck
 */

namespace OsintDeck\Infrastructure\Persistence;

/**
 * Lista de tool_id que el visitante ya marcó con me gusta (misma lógica que UserLikes).
 */
class AnonymousLikesStore {

    const TRANSIENT_TTL = 31536000;

    /**
     * @param string $fp Huella enviada por el JS (POST fp).
     * @return string Clave de transient.
     */
    private static function transient_key( $fp ) {
        $fp = is_string( $fp ) ? $fp : '';
        return 'osd_anon_lk_v1_' . md5( $fp );
    }

    /**
     * @param string $fp Huella.
     * @return int[]
     */
    public static function get_tool_ids( $fp ) {
        $fp = is_string( $fp ) ? trim( $fp ) : '';
        if ( $fp === '' ) {
            return array();
        }
        $raw = get_transient( self::transient_key( $fp ) );
        if ( ! is_array( $raw ) ) {
            return array();
        }
        $out = array();
        foreach ( $raw as $id ) {
            $id = (int) $id;
            if ( $id > 0 ) {
                $out[] = $id;
            }
        }
        return array_values( array_unique( $out ) );
    }

    /**
     * @param string $fp Huella.
     * @param int[]  $ids IDs únicos.
     */
    private static function set_tool_ids( $fp, array $ids ) {
        $fp = is_string( $fp ) ? trim( $fp ) : '';
        if ( $fp === '' ) {
            return;
        }
        $clean = array_values(
            array_unique(
                array_filter(
                    array_map( 'intval', $ids ),
                    static function ( $id ) {
                        return $id > 0;
                    }
                )
            )
        );
        set_transient( self::transient_key( $fp ), $clean, self::TRANSIENT_TTL );
    }

    /**
     * @param string $fp Huella.
     * @param int    $tool_id Tool DB id.
     */
    public static function has_liked( $fp, $tool_id ) {
        $tool_id = (int) $tool_id;
        if ( $tool_id <= 0 ) {
            return false;
        }
        return in_array( $tool_id, self::get_tool_ids( $fp ), true );
    }

    /**
     * @param string $fp Huella.
     * @param int    $tool_id Tool DB id.
     * @return bool True si se añadió.
     */
    public static function add( $fp, $tool_id ) {
        $tool_id = (int) $tool_id;
        if ( self::has_liked( $fp, $tool_id ) ) {
            return false;
        }
        $ids   = self::get_tool_ids( $fp );
        $ids[] = $tool_id;
        self::set_tool_ids( $fp, $ids );
        return true;
    }

    /**
     * @param string $fp Huella.
     * @param int    $tool_id Tool DB id.
     * @return bool True si estaba y se quitó.
     */
    public static function remove( $fp, $tool_id ) {
        $tool_id = (int) $tool_id;
        if ( ! self::has_liked( $fp, $tool_id ) ) {
            return false;
        }
        $filtered = array();
        foreach ( self::get_tool_ids( $fp ) as $id ) {
            if ( (int) $id !== $tool_id ) {
                $filtered[] = (int) $id;
            }
        }
        self::set_tool_ids( $fp, $filtered );
        return true;
    }
}
