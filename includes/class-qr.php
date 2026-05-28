<?php
defined( 'ABSPATH' ) || exit;

/**
 * PTM_QR
 *
 * Minimal pure-PHP QR code generator that outputs an SVG string.
 * Implements QR Code version 1–10, error correction level M.
 *
 * Usage:
 *   $svg = PTM_QR::svg( 'https://example.com/ptm-score/abc123' );
 *   echo $svg; // inline SVG
 *
 * Based on the ISO/IEC 18004 standard Reed-Solomon encoding.
 * For URLs up to ~140 chars (covers any scorer token URL).
 */
class PTM_QR {

    /**
     * Returns an inline SVG QR code for the given text.
     *
     * @param string $text   The text/URL to encode.
     * @param int    $size   SVG canvas size in pixels (default 200).
     * @param int    $margin Quiet zone modules (default 4).
     * @return string  SVG markup.
     */
    public static function svg( string $text, int $size = 200, int $margin = 4 ): string {
        $matrix = self::generate_matrix( $text );
        if ( ! $matrix ) {
            return '<!-- QR generation failed -->';
        }

        $modules     = count( $matrix );
        $total       = $modules + $margin * 2;
        $cell        = $size / $total;
        $cell_r      = round( $cell, 4 );
        $canvas      = $size;

        $rects = '';
        for ( $row = 0; $row < $modules; $row++ ) {
            for ( $col = 0; $col < $modules; $col++ ) {
                if ( $matrix[ $row ][ $col ] ) {
                    $x = round( ( $col + $margin ) * $cell, 4 );
                    $y = round( ( $row + $margin ) * $cell, 4 );
                    $rects .= "<rect x=\"$x\" y=\"$y\" width=\"$cell_r\" height=\"$cell_r\"/>";
                }
            }
        }

        return "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 $canvas $canvas\" width=\"$canvas\" height=\"$canvas\">"
             . "<rect width=\"$canvas\" height=\"$canvas\" fill=\"white\"/>"
             . "<g fill=\"black\">$rects</g>"
             . "</svg>";
    }

    /**
     * Returns a data URI PNG-like SVG that can be used as <img src="..."/>.
     */
    public static function data_uri( string $text, int $size = 200 ): string {
        $svg = self::svg( $text, $size );
        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }

    // ── QR Matrix generation ──────────────────────────────────────────────────
    // Implements a functional subset of QR code spec sufficient for URLs
    // up to ~140 bytes at error correction level M.

    private static function generate_matrix( string $text ): ?array {
        $data = self::encode_data( $text );
        if ( ! $data ) return null;

        [ $version, $bits ] = $data;
        $size    = $version * 4 + 17;
        $matrix  = array_fill( 0, $size, array_fill( 0, $size, 0 ) );
        $mask_m  = array_fill( 0, $size, array_fill( 0, $size, false ) ); // true = reserved

        // Place structural patterns
        self::place_finder( $matrix, $mask_m, 0, 0 );
        self::place_finder( $matrix, $mask_m, $size - 7, 0 );
        self::place_finder( $matrix, $mask_m, 0, $size - 7 );
        self::place_timing( $matrix, $mask_m, $size );
        self::place_alignment( $matrix, $mask_m, $version, $size );
        self::place_dark_module( $matrix, $mask_m, $version );
        self::reserve_format( $mask_m, $size );

        // Place data bits using mask pattern 0 (i+j) % 2 == 0
        self::place_data( $matrix, $mask_m, $bits, $size );

        // Apply mask pattern 0
        self::apply_mask( $matrix, $mask_m, $size, 0 );

        // Place format info (EC level M + mask 0 = 101010000010010)
        self::place_format( $matrix, $size, 0b101010000010010 );

        return $matrix;
    }

    private static function encode_data( string $text ): ?array {
        $byte_data = array_values( unpack( 'C*', $text ) );
        $len       = count( $byte_data );

        // Select version based on data capacity (EC level M, byte mode)
        // Version: 1=16, 2=28, 3=44, 4=64, 5=86, 6=108, 7=124, 8=154, 9=182, 10=216
        $capacities = [ 16, 28, 44, 64, 86, 108, 124, 154, 182, 216 ];
        $version    = null;
        foreach ( $capacities as $v => $cap ) {
            if ( $len <= $cap ) { $version = $v + 1; break; }
        }
        if ( ! $version ) return null; // text too long

        // Total codewords and error correction codewords per version (EC M)
        $ec_info = [
            1  => [ 26,  10,  1,  16 ],
            2  => [ 44,  16,  1,  28 ],
            3  => [ 70,  26,  2,  22 ],
            4  => [ 100, 36,  2,  32 ],
            5  => [ 134, 48,  2,  43 ],
            6  => [ 172, 64,  4,  27 ],
            7  => [ 196, 72,  4,  31 ],
            8  => [ 242, 88,  2,  38 ],
            9  => [ 292, 110, 3,  36 ],
            10 => [ 346, 130, 4,  43 ],
        ];

        [ $total_cw, $ec_cw, $blocks, $dc_per_block ] = $ec_info[ $version ];
        $data_cw = $total_cw - $ec_cw;

        // Build data bit stream: mode indicator (0100) + char count + data + terminator
        $bits = '';
        $bits .= '0100'; // byte mode
        $count_bits = $version < 10 ? 8 : 16;
        $bits .= str_pad( decbin( $len ), $count_bits, '0', STR_PAD_LEFT );
        foreach ( $byte_data as $b ) {
            $bits .= str_pad( decbin( $b ), 8, '0', STR_PAD_LEFT );
        }
        // Terminator + padding to byte boundary
        $bits .= '0000';
        while ( strlen( $bits ) % 8 !== 0 ) $bits .= '0';
        // Pad to required data codewords
        $pad_bytes = [ '11101100', '00010001' ];
        $pi = 0;
        while ( strlen( $bits ) < $data_cw * 8 ) {
            $bits .= $pad_bytes[ $pi % 2 ];
            $pi++;
        }

        // Convert to codeword array
        $data_codewords = [];
        for ( $i = 0; $i < $data_cw; $i++ ) {
            $data_codewords[] = bindec( substr( $bits, $i * 8, 8 ) );
        }

        // Reed-Solomon error correction
        $all_codewords = self::interleave_blocks( $data_codewords, $version, $ec_info[ $version ] );

        // Remainder bits (version-specific)
        $remainder_bits = [ 0,7,7,7,7,7,0,0,0,0 ];
        $rem = $remainder_bits[ $version - 1 ];

        // Build final bit stream
        $final_bits = '';
        foreach ( $all_codewords as $cw ) {
            $final_bits .= str_pad( decbin( $cw ), 8, '0', STR_PAD_LEFT );
        }
        $final_bits .= str_repeat( '0', $rem );

        return [ $version, $final_bits ];
    }

    private static function interleave_blocks( array $data_cw, int $version, array $ec_info ): array {
        [ $total_cw, $ec_cw, $blocks, $dc_per_block ] = $ec_info;
        $data_total = $total_cw - $ec_cw;

        // Split data into blocks
        $block_data = [];
        $offset = 0;
        $extra  = $data_total % $blocks;
        for ( $b = 0; $b < $blocks; $b++ ) {
            $blen          = $dc_per_block + ( $b >= ( $blocks - $extra ) ? 1 : 0 );
            $block_data[]  = array_slice( $data_cw, $offset, $blen );
            $offset       += $blen;
        }

        // Generate EC codewords for each block
        $ec_per_block = (int) ( $ec_cw / $blocks );
        $block_ec     = [];
        foreach ( $block_data as $block ) {
            $block_ec[] = self::rs_generate( $block, $ec_per_block );
        }

        // Interleave data blocks
        $result   = [];
        $max_data = max( array_map( 'count', $block_data ) );
        for ( $i = 0; $i < $max_data; $i++ ) {
            foreach ( $block_data as $block ) {
                if ( isset( $block[ $i ] ) ) $result[] = $block[ $i ];
            }
        }
        // Interleave EC blocks
        for ( $i = 0; $i < $ec_per_block; $i++ ) {
            foreach ( $block_ec as $block ) {
                if ( isset( $block[ $i ] ) ) $result[] = $block[ $i ];
            }
        }

        return $result;
    }

    // Galois field GF(256) with primitive polynomial x^8+x^4+x^3+x^2+1 = 285
    private static function rs_generate( array $data, int $ec_count ): array {
        // Generator polynomial coefficients (alpha powers)
        static $gen_polys = [];
        if ( ! isset( $gen_polys[ $ec_count ] ) ) {
            $gen = [1];
            for ( $i = 0; $i < $ec_count; $i++ ) {
                $gen = self::gf_poly_mul( $gen, [ 1, self::gf_pow( 2, $i ) ] );
            }
            $gen_polys[ $ec_count ] = $gen;
        }
        $gen = $gen_polys[ $ec_count ];

        $msg = array_merge( $data, array_fill( 0, $ec_count, 0 ) );
        for ( $i = 0; $i < count( $data ); $i++ ) {
            $coef = $msg[ $i ];
            if ( $coef !== 0 ) {
                for ( $j = 1; $j < count( $gen ); $j++ ) {
                    $msg[ $i + $j ] ^= self::gf_mul( $gen[ $j ], $coef );
                }
            }
        }
        return array_slice( $msg, count( $data ) );
    }

    private static function gf_pow( int $x, int $power ): int {
        static $exp = null;
        if ( $exp === null ) self::gf_init();
        return self::$gf_exp[ $power % 255 ];
    }
    private static function gf_mul( int $x, int $y ): int {
        if ( $x === 0 || $y === 0 ) return 0;
        static $log = null;
        if ( $log === null ) self::gf_init();
        return self::$gf_exp[ ( self::$gf_log[ $x ] + self::$gf_log[ $y ] ) % 255 ];
    }
    private static function gf_poly_mul( array $p, array $q ): array {
        $r = array_fill( 0, count( $p ) + count( $q ) - 1, 0 );
        foreach ( $p as $j => $pj ) {
            foreach ( $q as $i => $qi ) {
                $r[ $i + $j ] ^= self::gf_mul( $pj, $qi );
            }
        }
        return $r;
    }

    private static array $gf_exp = [];
    private static array $gf_log = [];
    private static function gf_init(): void {
        self::$gf_exp = array_fill( 0, 512, 0 );
        self::$gf_log = array_fill( 0, 256, 0 );
        $x = 1;
        for ( $i = 0; $i < 255; $i++ ) {
            self::$gf_exp[ $i ]       = $x;
            self::$gf_log[ $x ]       = $i;
            $x *= 2;
            if ( $x > 255 ) $x ^= 285;
        }
        for ( $i = 255; $i < 512; $i++ ) {
            self::$gf_exp[ $i ] = self::$gf_exp[ $i - 255 ];
        }
    }

    // ── Pattern placement ─────────────────────────────────────────────────────

    private static function place_finder( array &$m, array &$mask, int $row, int $col ): void {
        $pat = [
            [1,1,1,1,1,1,1],
            [1,0,0,0,0,0,1],
            [1,0,1,1,1,0,1],
            [1,0,1,1,1,0,1],
            [1,0,1,1,1,0,1],
            [1,0,0,0,0,0,1],
            [1,1,1,1,1,1,1],
        ];
        $size = count( $m );
        for ( $r = -1; $r <= 7; $r++ ) {
            for ( $c = -1; $c <= 7; $c++ ) {
                $mr = $row + $r;
                $mc = $col + $c;
                if ( $mr < 0 || $mr >= $size || $mc < 0 || $mc >= $size ) continue;
                $m[ $mr ][ $mc ]    = ( $r >= 0 && $r <= 6 && $c >= 0 && $c <= 6 ) ? $pat[$r][$c] : 0;
                $mask[ $mr ][ $mc ] = true;
            }
        }
    }

    private static function place_timing( array &$m, array &$mask, int $size ): void {
        for ( $i = 8; $i < $size - 8; $i++ ) {
            $v = $i % 2 === 0 ? 1 : 0;
            $m[6][$i] = $v; $mask[6][$i] = true;
            $m[$i][6] = $v; $mask[$i][6] = true;
        }
    }

    private static function place_alignment( array &$m, array &$mask, int $version, int $size ): void {
        // Alignment pattern centers per version (we only handle up to v10)
        $centers = [
            1 => [],
            2 => [6,18], 3 => [6,22], 4 => [6,26], 5 => [6,30],
            6 => [6,34], 7 => [6,22,38], 8 => [6,24,42], 9 => [6,26,46], 10 => [6,28,50],
        ];
        $c = $centers[ $version ] ?? [];
        if ( empty( $c ) ) return;
        foreach ( $c as $row ) {
            foreach ( $c as $col ) {
                // Skip positions that overlap finder patterns
                if ( $row <= 8 && $col <= 8 ) continue;
                if ( $row <= 8 && $col >= $size - 8 ) continue;
                if ( $row >= $size - 8 && $col <= 8 ) continue;
                for ( $r = -2; $r <= 2; $r++ ) {
                    for ( $cc = -2; $cc <= 2; $cc++ ) {
                        $v = ( abs($r) === 2 || abs($cc) === 2 ) ? 1 : ( $r === 0 && $cc === 0 ? 1 : 0 );
                        $m[ $row+$r ][ $col+$cc ]    = $v;
                        $mask[ $row+$r ][ $col+$cc ] = true;
                    }
                }
            }
        }
    }

    private static function place_dark_module( array &$m, array &$mask, int $version ): void {
        $row = $version * 4 + 9;
        $m[ $row ][8]    = 1;
        $mask[ $row ][8] = true;
    }

    private static function reserve_format( array &$mask, int $size ): void {
        for ( $i = 0; $i <= 8; $i++ ) {
            $mask[8][$i]         = true;
            $mask[$i][8]         = true;
            $mask[8][$size-1-$i] = true;
            $mask[$size-1-$i][8] = true;
        }
    }

    private static function place_data( array &$m, array $mask, string $bits, int $size ): void {
        $bit_idx = 0;
        $len     = strlen( $bits );
        $col     = $size - 1;
        $going_up = true;

        while ( $col >= 0 ) {
            if ( $col === 6 ) $col--; // Skip timing column
            for ( $delta_row = 0; $delta_row < $size; $delta_row++ ) {
                $row = $going_up ? $size - 1 - $delta_row : $delta_row;
                for ( $delta_col = 0; $delta_col <= 1; $delta_col++ ) {
                    $c = $col - $delta_col;
                    if ( ! $mask[ $row ][ $c ] ) {
                        $m[ $row ][ $c ] = $bit_idx < $len ? (int) $bits[ $bit_idx++ ] : 0;
                    }
                }
            }
            $going_up = ! $going_up;
            $col -= 2;
        }
    }

    private static function apply_mask( array &$m, array $mask, int $size, int $pattern ): void {
        for ( $r = 0; $r < $size; $r++ ) {
            for ( $c = 0; $c < $size; $c++ ) {
                if ( $mask[$r][$c] ) continue;
                $flip = false;
                switch ( $pattern ) {
                    case 0: $flip = ( $r + $c ) % 2 === 0; break;
                    case 1: $flip = $r % 2 === 0; break;
                    case 2: $flip = $c % 3 === 0; break;
                    case 3: $flip = ( $r + $c ) % 3 === 0; break;
                }
                if ( $flip ) $m[$r][$c] ^= 1;
            }
        }
    }

    private static function place_format( array &$m, int $size, int $format_bits ): void {
        $format = str_pad( decbin( $format_bits ), 15, '0', STR_PAD_LEFT );
        $positions = [
            [8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],
            [7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8],
        ];
        foreach ( $positions as $i => $pos ) {
            $m[ $pos[0] ][ $pos[1] ] = (int) $format[ $i ];
        }
        // Mirror copy
        $mirror = [
            [$size-1,8],[$size-2,8],[$size-3,8],[$size-4,8],[$size-5,8],[$size-6,8],[$size-7,8],
            [8,$size-8],[8,$size-7],[8,$size-6],[8,$size-5],[8,$size-4],[8,$size-3],[8,$size-2],[8,$size-1],
        ];
        foreach ( $mirror as $i => $pos ) {
            if ( $i < 15 ) $m[ $pos[0] ][ $pos[1] ] = (int) $format[ $i ];
        }
    }
}
