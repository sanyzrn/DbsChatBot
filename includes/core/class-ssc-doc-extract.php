<?php
/**
 * Plain-text extraction from uploaded documents (DOCX and PDF), without
 * external services or binaries.
 *
 * DOCX: the document XML inside the ZIP container.
 * PDF: a compact reader for text-based PDFs: classic and compressed object
 * streams, FlateDecode, fonts with ToUnicode maps (how Persian and other
 * non-Latin text is stored), and the text operators Tj, TJ, ' and ".
 * Scanned PDFs (images) have no text; they are reported as such.
 *
 * @package SmartSupportChatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SSC_Doc_Extract
 */
class SSC_Doc_Extract {

	/**
	 * Extract text from a file by extension.
	 *
	 * @param string $path File path.
	 * @param string $ext  Extension (docx|pdf).
	 * @return string|WP_Error
	 */
	public static function file( $path, $ext ) {
		$ext = strtolower( (string) $ext );
		if ( 'docx' === $ext ) {
			$text = self::docx( $path );
		} elseif ( 'pdf' === $ext ) {
			$text = self::pdf( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local upload.
		} else {
			return new WP_Error( 'ssc_doc_type', __( 'Unsupported document type.', 'nexachat-ai' ) );
		}
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		$text = self::tidy( $text );
		// Real text has letters; a scanned PDF yields nothing (or noise).
		if ( preg_match_all( '/\p{L}/u', $text ) < 20 ) {
			return new WP_Error( 'ssc_doc_empty', __( 'No readable text was found. If this is a scanned document, upload a text version (Word, or a PDF saved from a text editor).', 'nexachat-ai' ) );
		}
		return $text;
	}

	/**
	 * Normalize whitespace and Arabic presentation forms.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function tidy( $text ) {
		$text = (string) $text;
		// PDFs often map Persian/Arabic glyphs to their presentation forms.
		if ( class_exists( 'Normalizer' ) && preg_match( '/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text ) ) {
			$text = preg_replace_callback(
				'/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]+/u',
				function ( $m ) {
					$n = Normalizer::normalize( $m[0], Normalizer::FORM_KC );
					return false === $n ? $m[0] : $n;
				},
				$text
			);
		}
		$text = str_replace( array( "\r\n", "\r", "\u{00A0}" ), array( "\n", "\n", ' ' ), $text );
		$text = preg_replace( "/[ \t]+/u", ' ', $text );
		$text = preg_replace( "/ *\n */u", "\n", $text );
		$text = preg_replace( "/\n{3,}/u", "\n\n", $text );
		return trim( (string) $text );
	}

	/*
	 * --------------------------------------------------------------
	 * DOCX.
	 * --------------------------------------------------------------
	 */

	/**
	 * Text of a Word document (paragraphs, tables, line breaks).
	 *
	 * @param string $path File path.
	 * @return string|WP_Error
	 */
	public static function docx( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'ssc_doc_zip', __( 'This server cannot open Word files (the PHP zip extension is missing). Save the document as PDF or TXT and upload that.', 'nexachat-ai' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'ssc_doc_read', __( 'The file could not be opened. Is it a valid .docx document?', 'nexachat-ai' ) );
		}
		$xml = $zip->getFromName( 'word/document.xml' );
		$zip->close();
		if ( false === $xml || '' === $xml ) {
			return new WP_Error( 'ssc_doc_read', __( 'The file could not be opened. Is it a valid .docx document?', 'nexachat-ai' ) );
		}
		// Structure to line breaks, then drop the markup.
		$xml  = preg_replace( array( '/<w:tab\/>/', '/<w:br[^>]*\/>/', '/<\/w:p>/', '/<\/w:tc>/' ), array( "\t", "\n", "\n", "\t" ), $xml );
		$xml  = preg_replace( '/<w:instrText[^>]*>.*?<\/w:instrText>/s', '', $xml ); // Field codes are not text.
		$text = html_entity_decode( wp_strip_all_tags( $xml, false ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
		return $text;
	}

	/*
	 * --------------------------------------------------------------
	 * PDF.
	 * --------------------------------------------------------------
	 */

	/**
	 * Text of a text-based PDF.
	 *
	 * @param string $data PDF bytes.
	 * @return string|WP_Error
	 */
	public static function pdf( $data ) {
		if ( 0 !== strpos( ltrim( substr( $data, 0, 1024 ) ), '%PDF' ) ) {
			return new WP_Error( 'ssc_doc_read', __( 'This does not look like a PDF file.', 'nexachat-ai' ) );
		}
		if ( preg_match( '/\/Encrypt\s/', $data ) ) {
			return new WP_Error( 'ssc_doc_encrypted', __( 'This PDF is password-protected or encrypted. Remove the protection and upload it again.', 'nexachat-ai' ) );
		}
		$objects = self::pdf_objects( $data );
		if ( ! $objects ) {
			return new WP_Error( 'ssc_doc_read', __( 'The PDF could not be read.', 'nexachat-ai' ) );
		}

		$out   = array();
		$pages = self::pdf_pages( $objects );
		foreach ( $pages as $page ) {
			$fonts   = self::pdf_page_fonts( $objects, $page );
			$content = '';
			foreach ( self::pdf_refs( self::dict_value( $page['dict'], 'Contents' ) ) as $ref ) {
				if ( isset( $objects[ $ref ] ) ) {
					$content .= self::pdf_stream( $objects[ $ref ] ) . "\n";
				}
			}
			$out[] = self::pdf_text( $content, $fonts );
		}
		return implode( "\n\n", $out );
	}

	/**
	 * All objects: number => array( dict, stream ), including those packed in object streams.
	 *
	 * @param string $data PDF bytes.
	 * @return array
	 */
	protected static function pdf_objects( $data ) {
		$objects = array();
		if ( ! preg_match_all( '/(?<![0-9])(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj\b/s', $data, $matches, PREG_SET_ORDER ) ) {
			return $objects;
		}
		foreach ( $matches as $m ) {
			$body   = $m[3];
			$stream = '';
			if ( preg_match( '/^(.*?)\bstream\r?\n(.*)\bendstream\b/s', $body, $s ) ) {
				$body   = $s[1];
				$stream = preg_replace( '/\r?\n$/', '', $s[2] );
			}
			$objects[ (int) $m[1] ] = array(
				'dict'   => $body,
				'stream' => $stream,
			);
		}
		// Compressed object streams (PDF 1.5+).
		foreach ( $objects as $obj ) {
			if ( ! preg_match( '/\/Type\s*\/ObjStm\b/', $obj['dict'] ) ) {
				continue;
			}
			$decoded = self::pdf_stream( $obj );
			$count   = (int) self::dict_value( $obj['dict'], 'N' );
			$first   = (int) self::dict_value( $obj['dict'], 'First' );
			if ( ! $count || '' === $decoded ) {
				continue;
			}
			$header = preg_split( '/\s+/', trim( substr( $decoded, 0, $first ) ) );
			$pairs  = min( (int) floor( count( $header ) / 2 ), $count );
			for ( $p = 0; $p < $pairs; $p++ ) {
				$i     = 2 * $p;
				$num   = (int) $header[ $i ];
				$start = $first + (int) $header[ $i + 1 ];
				$end   = isset( $header[ $i + 3 ] ) ? $first + (int) $header[ $i + 3 ] : strlen( $decoded );
				if ( ! isset( $objects[ $num ] ) ) {
					$objects[ $num ] = array(
						'dict'   => substr( $decoded, $start, $end - $start ),
						'stream' => '',
					);
				}
			}
		}
		return $objects;
	}

	/**
	 * Decoded stream data (FlateDecode, ASCIIHex; others returned as-is).
	 *
	 * @param array $obj Object.
	 * @return string
	 */
	protected static function pdf_stream( $obj ) {
		$data = (string) $obj['stream'];
		if ( '' === $data ) {
			return '';
		}
		$filters = array();
		if ( preg_match( '/\/Filter\s*\[([^\]]*)\]/', $obj['dict'], $m ) ) {
			preg_match_all( '/\/(\w+)/', $m[1], $f );
			$filters = $f[1];
		} elseif ( preg_match( '/\/Filter\s*\/(\w+)/', $obj['dict'], $m ) ) {
			$filters = array( $m[1] );
		}
		foreach ( $filters as $filter ) {
			if ( 'FlateDecode' === $filter ) {
				$out = @gzuncompress( $data ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- corrupt streams are skipped.
				if ( false === $out ) {
					$out = @gzinflate( substr( $data, 2 ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- raw deflate fallback.
				}
				if ( false === $out ) {
					return '';
				}
				$data = $out;
			} elseif ( 'ASCIIHexDecode' === $filter ) {
				$data = (string) hex2bin( preg_replace( '/[^0-9A-Fa-f]/', '', rtrim( $data, '>' ) ) . ( strlen( preg_replace( '/[^0-9A-Fa-f]/', '', $data ) ) % 2 ? '0' : '' ) );
			} else {
				return ''; // Image or unsupported filter: not text.
			}
		}
		return $data;
	}

	/**
	 * Value of a key in a dictionary string (raw token, reference, or nested structure).
	 *
	 * @param string $dict Dictionary text.
	 * @param string $key  Key without slash.
	 * @return string
	 */
	protected static function dict_value( $dict, $key ) {
		$pos = strpos( $dict, '/' . $key );
		while ( false !== $pos ) {
			$after = substr( $dict, $pos + strlen( $key ) + 1, 1 );
			if ( '' === $after || ! preg_match( '/[A-Za-z0-9]/', $after ) ) {
				break; // Whole key, not a prefix of a longer name.
			}
			$pos = strpos( $dict, '/' . $key, $pos + 1 );
		}
		if ( false === $pos ) {
			return '';
		}
		$rest = ltrim( substr( $dict, $pos + strlen( $key ) + 1 ) );
		if ( preg_match( '/^(\d+\s+\d+\s+R)/', $rest, $m ) ) {
			return $m[1];
		}
		$open = substr( $rest, 0, 2 );
		if ( '<<' === $open || '[' === $rest[0] ) {
			// Balanced nested structure.
			$depth = 0;
			$len   = strlen( $rest );
			for ( $i = 0; $i < $len; $i++ ) {
				$two = substr( $rest, $i, 2 );
				if ( '<<' === $two ) {
					++$depth;
					++$i;
				} elseif ( '>>' === $two ) {
					--$depth;
					++$i;
					if ( 0 === $depth ) {
						return substr( $rest, 0, $i + 1 );
					}
				} elseif ( '[' === $rest[ $i ] ) {
					++$depth;
				} elseif ( ']' === $rest[ $i ] ) {
					--$depth;
					if ( 0 === $depth ) {
						return substr( $rest, 0, $i + 1 );
					}
				}
			}
			return $rest;
		}
		return preg_match( '/^([^\s\/<>\[\]]+|\/[^\s\/<>\[\]]+)/', $rest, $m ) ? $m[1] : '';
	}

	/**
	 * Object numbers referenced in a value ("12 0 R" or "[12 0 R 13 0 R]").
	 *
	 * @param string $value Value.
	 * @return int[]
	 */
	protected static function pdf_refs( $value ) {
		preg_match_all( '/(\d+)\s+\d+\s+R/', (string) $value, $m );
		return array_map( 'intval', $m[1] );
	}

	/**
	 * Resolve a value that may be a reference to an object's dictionary.
	 *
	 * @param array  $objects Objects.
	 * @param string $value   Value.
	 * @return string
	 */
	protected static function resolve( $objects, $value ) {
		if ( preg_match( '/^(\d+)\s+\d+\s+R$/', trim( (string) $value ), $m ) && isset( $objects[ (int) $m[1] ] ) ) {
			return $objects[ (int) $m[1] ]['dict'];
		}
		return (string) $value;
	}

	/**
	 * Pages in document order (page tree walk; falls back to every Page object).
	 *
	 * @param array $objects Objects.
	 * @return array[] Each: dict, resources (inherited).
	 */
	protected static function pdf_pages( $objects ) {
		$root = null;
		foreach ( $objects as $obj ) {
			if ( preg_match( '/\/Type\s*\/Catalog\b/', $obj['dict'] ) ) {
				$root = self::pdf_refs( self::dict_value( $obj['dict'], 'Pages' ) );
				break;
			}
		}
		$pages = array();
		$walk  = function ( $num, $inherited, $depth ) use ( &$walk, &$pages, $objects ) {
			if ( $depth > 50 || ! isset( $objects[ $num ] ) ) {
				return;
			}
			$dict      = $objects[ $num ]['dict'];
			$resources = self::dict_value( $dict, 'Resources' );
			$resources = '' !== $resources ? $resources : $inherited;
			if ( preg_match( '/\/Type\s*\/Pages\b/', $dict ) ) {
				foreach ( self::pdf_refs( self::dict_value( $dict, 'Kids' ) ) as $kid ) {
					$walk( $kid, $resources, $depth + 1 );
				}
			} elseif ( preg_match( '/\/Type\s*\/Page\b/', $dict ) ) {
				$pages[] = array(
					'dict'      => $dict,
					'resources' => $resources,
				);
			}
		};
		if ( $root ) {
			$walk( $root[0], '', 0 );
		}
		if ( ! $pages ) {
			foreach ( $objects as $obj ) {
				if ( preg_match( '/\/Type\s*\/Page\b/', $obj['dict'] ) ) {
					$pages[] = array(
						'dict'      => $obj['dict'],
						'resources' => self::dict_value( $obj['dict'], 'Resources' ),
					);
				}
			}
		}
		return $pages;
	}

	/**
	 * Fonts of a page: resource name => array( map, bytes ).
	 *
	 * @param array $objects Objects.
	 * @param array $page    Page.
	 * @return array
	 */
	protected static function pdf_page_fonts( $objects, $page ) {
		$resources = self::resolve( $objects, $page['resources'] );
		$font_dict = self::resolve( $objects, self::dict_value( $resources, 'Font' ) );
		$fonts     = array();
		if ( ! preg_match_all( '/\/([^\s\/<>\[\]]+)\s+(\d+)\s+\d+\s+R/', $font_dict, $m, PREG_SET_ORDER ) ) {
			return $fonts;
		}
		foreach ( $m as $f ) {
			$num = (int) $f[2];
			if ( ! isset( $objects[ $num ] ) ) {
				continue;
			}
			$font = $objects[ $num ]['dict'];
			$map  = array();
			$len  = 1;
			$tu   = self::pdf_refs( self::dict_value( $font, 'ToUnicode' ) );
			if ( $tu && isset( $objects[ $tu[0] ] ) ) {
				list( $map, $len ) = self::pdf_cmap( self::pdf_stream( $objects[ $tu[0] ] ) );
			}
			if ( preg_match( '/\/Subtype\s*\/Type0\b/', $font ) ) {
				$len = 2; // Composite (CID) fonts use two-byte codes.
			}
			$fonts[ $f[1] ] = array(
				'map'   => $map,
				'bytes' => $len,
			);
		}
		return $fonts;
	}

	/**
	 * ToUnicode CMap: code => UTF-8 text, and the code length in bytes.
	 *
	 * @param string $cmap CMap program.
	 * @return array array( map, bytes ).
	 */
	protected static function pdf_cmap( $cmap ) {
		$map   = array();
		$bytes = 1;
		if ( preg_match( '/begincodespacerange\s*<([0-9A-Fa-f]+)>/', $cmap, $m ) ) {
			$bytes = max( 1, (int) ( strlen( $m[1] ) / 2 ) );
		}
		$utf16 = function ( $hex ) {
			$bin = hex2bin( strlen( $hex ) % 2 ? '0' . $hex : $hex );
			return false === $bin ? '' : (string) mb_convert_encoding( $bin, 'UTF-8', 'UTF-16BE' );
		};
		if ( preg_match_all( '/beginbfchar(.*?)endbfchar/s', $cmap, $blocks ) ) {
			foreach ( $blocks[1] as $block ) {
				preg_match_all( '/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]*)>/', $block, $pairs, PREG_SET_ORDER );
				foreach ( $pairs as $p ) {
					$map[ hexdec( $p[1] ) ] = $utf16( $p[2] );
				}
			}
		}
		if ( preg_match_all( '/beginbfrange(.*?)endbfrange/s', $cmap, $blocks ) ) {
			foreach ( $blocks[1] as $block ) {
				preg_match_all( '/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*(<[0-9A-Fa-f]+>|\[[^\]]*\])/', $block, $ranges, PREG_SET_ORDER );
				foreach ( $ranges as $r ) {
					$lo = hexdec( $r[1] );
					$hi = min( hexdec( $r[2] ), $lo + 65535 );
					if ( '[' === $r[3][0] ) {
						preg_match_all( '/<([0-9A-Fa-f]+)>/', $r[3], $list );
						foreach ( $list[1] as $i => $hex ) {
							$map[ $lo + $i ] = $utf16( $hex );
						}
						continue;
					}
					$start = hexdec( trim( $r[3], '<>' ) );
					$width = strlen( trim( $r[3], '<>' ) );
					for ( $code = $lo; $code <= $hi; $code++ ) {
						$map[ $code ] = $utf16( str_pad( dechex( $start + $code - $lo ), $width, '0', STR_PAD_LEFT ) );
					}
				}
			}
		}
		return array( $map, $bytes );
	}

	/**
	 * Text of one content stream.
	 *
	 * Text runs are collected with their positions, grouped into lines and
	 * ordered by position. PDF glyphs are laid out visually, so right-to-left
	 * lines (Persian, Arabic, Hebrew) are turned back into logical order.
	 *
	 * @param string $content Content stream.
	 * @param array  $fonts   Page fonts.
	 * @return string
	 */
	protected static function pdf_text( $content, $fonts ) {
		$runs  = array();
		$font  = null;
		$size  = 12.0;
		$lead  = 0.0;
		$tm    = array( 1, 0, 0, 1, 0, 0 ); // Text matrix.
		$tlm   = $tm;                       // Line matrix.
		$stack = array();
		$len   = strlen( $content );
		$i     = 0;
		$emit  = function ( $text ) use ( &$runs, &$tm, &$size ) {
			if ( '' === $text ) {
				return;
			}
			$runs[] = array(
				'x'    => $tm[4],
				'y'    => $tm[5],
				'size' => max( 1.0, abs( $size * ( $tm[3] ? $tm[3] : $tm[0] ) ) ),
				't'    => $text,
			);
			// Rough advance so a following run on the same line keeps its order.
			$tm[4] += mb_strlen( $text ) * $size * 0.5 * ( $tm[0] ? $tm[0] : 1 );
		};
		$move  = function ( $tx, $ty ) use ( &$tm, &$tlm ) {
			$tlm = array( $tlm[0], $tlm[1], $tlm[2], $tlm[3], $tx * $tlm[0] + $ty * $tlm[2] + $tlm[4], $tx * $tlm[1] + $ty * $tlm[3] + $tlm[5] );
			$tm  = $tlm;
		};
		$nums  = function ( $stack, $count ) {
			$out = array();
			foreach ( array_slice( $stack, -$count ) as $item ) {
				$out[] = 'd' === $item[0] ? $item[1] : 0.0;
			}
			return array_pad( $out, $count, 0.0 );
		};
		while ( $i < $len ) {
			$c = $content[ $i ];
			if ( ctype_space( $c ) ) {
				++$i;
				continue;
			}
			if ( '%' === $c ) { // Comment.
				$nl = strpos( $content, "\n", $i );
				$i  = false === $nl ? $len : $nl + 1;
				continue;
			}
			if ( '(' === $c ) {
				list( $str, $i ) = self::pdf_literal( $content, $i );
				$stack[]         = array( 's', $str );
				continue;
			}
			if ( '<<' === substr( $content, $i, 2 ) ) { // Inline dictionaries (marked content): skip.
				$depth = 0;
				for ( ; $i < $len; $i++ ) {
					$two = substr( $content, $i, 2 );
					if ( '<<' === $two ) {
						++$depth;
						++$i;
					} elseif ( '>>' === $two ) {
						--$depth;
						++$i;
						if ( 0 === $depth ) {
							++$i;
							break;
						}
					}
				}
				continue;
			}
			if ( '<' === $c ) {
				$end     = strpos( $content, '>', $i );
				$end     = false === $end ? $len : $end;
				$hex     = preg_replace( '/[^0-9A-Fa-f]/', '', substr( $content, $i + 1, $end - $i - 1 ) );
				$stack[] = array( 's', (string) hex2bin( strlen( $hex ) % 2 ? $hex . '0' : $hex ) );
				$i       = $end + 1;
				continue;
			}
			if ( '[' === $c ) {
				$stack[] = array( '[' );
				++$i;
				continue;
			}
			if ( ']' === $c ) {
				$items = array();
				while ( $stack ) {
					$top = array_pop( $stack );
					if ( '[' === $top[0] ) {
						break;
					}
					array_unshift( $items, $top );
				}
				$stack[] = array( 'a', $items );
				++$i;
				continue;
			}
			// Token: number, name or operator.
			if ( ! preg_match( '/\G(\/[^\s\/<>\[\]()%]*|[^\s\/<>\[\]()%]+)/', $content, $m, 0, $i ) ) {
				++$i;
				continue;
			}
			$token = $m[1];
			$i    += strlen( $token );
			if ( '/' === $token[0] ) {
				$stack[] = array( 'n', substr( $token, 1 ) );
				continue;
			}
			if ( is_numeric( $token ) ) {
				$stack[] = array( 'd', (float) $token );
				continue;
			}
			switch ( $token ) {
				case 'BI': // Inline image: skip to EI.
					$end = strpos( $content, 'EI', $i );
					$i   = false === $end ? $len : $end + 2;
					break;
				case 'BT':
					$tm  = array( 1, 0, 0, 1, 0, 0 );
					$tlm = $tm;
					break;
				case 'Tf':
					$name = null;
					foreach ( $stack as $item ) {
						if ( 'n' === $item[0] ) {
							$name = $item[1];
						}
					}
					$font         = ( null !== $name && isset( $fonts[ $name ] ) ) ? $fonts[ $name ] : null;
					list( $size ) = $nums( $stack, 1 );
					$size         = $size ? $size : 12.0;
					break;
				case 'TL':
					list( $lead ) = $nums( $stack, 1 );
					break;
				case 'Td':
					list( $tx, $ty ) = $nums( $stack, 2 );
					$move( $tx, $ty );
					break;
				case 'TD':
					list( $tx, $ty ) = $nums( $stack, 2 );
					$lead            = -$ty;
					$move( $tx, $ty );
					break;
				case 'Tm':
					$tm  = $nums( $stack, 6 );
					$tlm = $tm;
					break;
				case 'T*':
					$move( 0, -( $lead ? $lead : $size * 1.2 ) );
					break;
				case 'Tj':
				case "'":
				case '"':
					if ( "'" === $token || '"' === $token ) {
						$move( 0, -( $lead ? $lead : $size * 1.2 ) );
					}
					$top = end( $stack );
					if ( $top && 's' === $top[0] ) {
						$emit( self::pdf_decode( $top[1], $font ) );
					}
					break;
				case 'TJ':
					$top = end( $stack );
					if ( $top && 'a' === $top[0] ) {
						$text = '';
						foreach ( $top[1] as $part ) {
							if ( 's' === $part[0] ) {
								$text .= self::pdf_decode( $part[1], $font );
							} elseif ( 'd' === $part[0] && $part[1] < -180 && '' !== $text && ' ' !== substr( $text, -1 ) ) {
								$text .= ' '; // A large negative kern is a word gap.
							}
						}
						$emit( $text );
					}
					break;
			}
			$stack = array();
		}
		return self::pdf_lines( $runs );
	}

	/**
	 * Runs → lines of text in reading order.
	 *
	 * @param array $runs Runs (x, y, size, t).
	 * @return string
	 */
	protected static function pdf_lines( $runs ) {
		$lines = array(); // In order of first appearance (streams draw top to bottom).
		foreach ( $runs as $run ) {
			$placed = false;
			foreach ( $lines as $k => $line ) {
				if ( abs( $line['y'] - $run['y'] ) <= max( 1.0, $run['size'] * 0.35 ) ) {
					$lines[ $k ]['runs'][] = $run;
					$placed                = true;
					break;
				}
			}
			if ( ! $placed ) {
				$lines[] = array(
					'y'    => $run['y'],
					'runs' => array( $run ),
				);
			}
		}
		$out = array();
		foreach ( $lines as $line ) {
			$runs = $line['runs'];
			usort(
				$runs,
				function ( $a, $b ) {
					return $a['x'] <=> $b['x'];
				}
			);
			// Generators that write real space glyphs need no guessing from gaps.
			$spaced = false;
			foreach ( $runs as $run ) {
				if ( false !== strpos( $run['t'], ' ' ) ) {
					$spaced = true;
					break;
				}
			}
			$visual = '';
			$prev   = null;
			foreach ( $runs as $run ) {
				if ( null !== $prev && ! $spaced ) {
					$gap = $run['x'] - $prev['end'];
					if ( $gap > $run['size'] * 0.6 && ' ' !== mb_substr( $visual, -1 ) && ' ' !== mb_substr( $run['t'], 0, 1 ) ) {
						$visual .= ' ';
					}
				}
				$visual .= $run['t'];
				$prev    = array( 'end' => $run['x'] + mb_strlen( $run['t'] ) * $run['size'] * 0.45 );
			}
			$out[] = self::visual_to_logical( $visual );
		}
		return implode( "\n", $out );
	}

	/**
	 * A line in visual (left-to-right drawing) order back to logical order.
	 *
	 * Lines with more right-to-left letters than left-to-right ones get the
	 * inverse of the bidi layout: runs in reverse order, right-to-left runs
	 * reversed character by character, Latin words and numbers kept intact.
	 *
	 * @param string $line Visual line.
	 * @return string
	 */
	public static function visual_to_logical( $line ) {
		$rtl = preg_match_all( '/[\x{0590}-\x{08FF}\x{FB1D}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $line );
		$ltr = preg_match_all( '/[A-Za-z\x{00C0}-\x{024F}]/u', $line );
		if ( ! $rtl || $rtl <= $ltr ) {
			return $line;
		}
		// Runs: strong LTR (Latin letters and digits with their joiners) vs everything else.
		preg_match_all( '/[A-Za-z0-9\x{00C0}-\x{024F}\x{06F0}-\x{06F9}\x{0660}-\x{0669}](?:[A-Za-z0-9\x{00C0}-\x{024F}\x{06F0}-\x{06F9}\x{0660}-\x{0669}.,:\/%\-+]*[A-Za-z0-9\x{00C0}-\x{024F}\x{06F0}-\x{06F9}\x{0660}-\x{0669}])?|[^A-Za-z0-9\x{00C0}-\x{024F}\x{06F0}-\x{06F9}\x{0660}-\x{0669}]+/u', $line, $m );
		$runs = array_reverse( $m[0] );
		foreach ( $runs as $k => $run ) {
			if ( ! preg_match( '/^[A-Za-z0-9\x{00C0}-\x{024F}\x{06F0}-\x{06F9}\x{0660}-\x{0669}]/u', $run ) ) {
				$chars = preg_split( '//u', $run, -1, PREG_SPLIT_NO_EMPTY );
				$chars = array_reverse( $chars );
				// Mirrored brackets swap when the direction flips.
				$runs[ $k ] = strtr(
					implode( '', $chars ),
					array(
						'(' => ')',
						')' => '(',
						'[' => ']',
						']' => '[',
						'«' => '»',
						'»' => '«',
					)
				);
			}
		}
		return implode( '', $runs );
	}

	/**
	 * Read a literal string "( … )" with escapes and nested parentheses.
	 *
	 * @param string $content Content.
	 * @param int    $i       Position of "(".
	 * @return array array( bytes, next position ).
	 */
	protected static function pdf_literal( $content, $i ) {
		$len   = strlen( $content );
		$depth = 0;
		$out   = '';
		for ( ++$i; $i < $len; $i++ ) {
			$c = $content[ $i ];
			if ( '\\' === $c ) {
				$n   = substr( $content, $i + 1, 1 );
				$map = array(
					'n'  => "\n",
					'r'  => "\r",
					't'  => "\t",
					'b'  => "\x08",
					'f'  => "\x0c",
					'('  => '(',
					')'  => ')',
					'\\' => '\\',
				);
				if ( isset( $map[ $n ] ) ) {
					$out .= $map[ $n ];
					++$i;
				} elseif ( preg_match( '/\G[0-7]{1,3}/', $content, $o, 0, $i + 1 ) ) {
					$out .= chr( octdec( $o[0] ) & 0xFF );
					$i   += strlen( $o[0] );
				} elseif ( "\n" === $n || "\r" === $n ) {
					++$i; // Line continuation.
				}
				continue;
			}
			if ( '(' === $c ) {
				++$depth;
			} elseif ( ')' === $c ) {
				if ( 0 === $depth ) {
					return array( $out, $i + 1 );
				}
				--$depth;
			}
			$out .= $c;
		}
		return array( $out, $len );
	}

	/**
	 * Bytes of a string operand to UTF-8 using the font's map.
	 *
	 * @param string     $bytes Bytes.
	 * @param array|null $font  Font (map, bytes).
	 * @return string
	 */
	protected static function pdf_decode( $bytes, $font ) {
		if ( $font && $font['map'] ) {
			$step = max( 1, (int) $font['bytes'] );
			$out  = '';
			$len  = strlen( $bytes );
			for ( $i = 0; $i + $step <= $len; $i += $step ) {
				$code = 1 === $step ? ord( $bytes[ $i ] ) : hexdec( bin2hex( substr( $bytes, $i, $step ) ) );
				$out .= isset( $font['map'][ $code ] ) ? $font['map'][ $code ] : '';
			}
			return $out;
		}
		if ( "\xFE\xFF" === substr( $bytes, 0, 2 ) ) {
			return (string) mb_convert_encoding( substr( $bytes, 2 ), 'UTF-8', 'UTF-16BE' );
		}
		// Simple fonts without a map: Latin-1 is the closest general guess.
		return (string) mb_convert_encoding( $bytes, 'UTF-8', 'ISO-8859-1' );
	}
}
