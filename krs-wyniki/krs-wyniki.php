<?php
/**
 * Plugin Name: KRS Wyniki
 * Description: Klasyfikacja generalna i wyniki rund KartRace Series pobierane automatycznie z arkusza Excel na OneDrive. Shortcode: [krs_klasyfikacja]
 * Version: 2.1.0
 * Author: SCS Web
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KRS_W_VER', '2.1.0' );
define( 'KRS_W_OPT_SET', 'krs_wyniki_settings' );
define( 'KRS_W_OPT_DATA', 'krs_wyniki_data' );
define( 'KRS_W_OPT_LOG', 'krs_wyniki_log' );
define( 'KRS_W_FRESH', 'krs_wyniki_fresh' );

require_once __DIR__ . '/krs-events.php';
require_once __DIR__ . '/krs-seo.php';

/* ------------------------------------------------------------------ */
/* Ustawienia                                                          */
/* ------------------------------------------------------------------ */

function krs_w_defaults() {
	return array(
		'url'    => '', // link do arkusza ustawia się w panelu: Ustawienia → KRS Wyniki
		'direct' => '',
		'ttl'    => 15,
	);
}

function krs_w_settings() {
	$s = get_option( KRS_W_OPT_SET, array() );
	return wp_parse_args( is_array( $s ) ? $s : array(), krs_w_defaults() );
}

/* ------------------------------------------------------------------ */
/* Pobieranie arkusza                                                  */
/* ------------------------------------------------------------------ */

function krs_w_b64url( $s ) {
	return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' );
}

function krs_w_candidates( $set ) {
	$c = array();
	if ( ! empty( $set['direct'] ) ) {
		$c[] = $set['direct'];
	}
	$u = trim( $set['url'] );
	if ( $u ) {
		$c[] = add_query_arg( 'download', '1', $u );
		$c[] = 'https://api.onedrive.com/v1.0/shares/u!' . krs_w_b64url( $u ) . '/root/content';
		// Długi link (onedrive.live.com/...redeem=...) -> link 1drv.ms zakodowany w parametrze redeem.
		$q = wp_parse_url( $u, PHP_URL_QUERY );
		if ( $q ) {
			parse_str( $q, $qa );
			if ( ! empty( $qa['redeem'] ) ) {
				$short = base64_decode( strtr( $qa['redeem'], '-_', '+/' ) );
				if ( $short ) {
					$c[] = add_query_arg( 'download', '1', $short );
					$c[] = 'https://api.onedrive.com/v1.0/shares/u!' . krs_w_b64url( $short ) . '/root/content';
				}
			}
			if ( ! empty( $qa['resid'] ) ) {
				$d = 'https://onedrive.live.com/download?resid=' . rawurlencode( $qa['resid'] );
				if ( ! empty( $qa['e'] ) ) {
					$d .= '&e=' . rawurlencode( $qa['e'] );
				}
				$c[] = $d;
			}
		}
	}
	return array_values( array_unique( $c ) );
}

function krs_w_download( &$diag ) {
	$set  = krs_w_settings();
	$diag = array();
	foreach ( krs_w_candidates( $set ) as $url ) {
		$r = wp_remote_get(
			$url,
			array(
				'timeout'     => 30,
				'redirection' => 10,
				'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36',
				'headers'     => array( 'Accept' => '*/*' ),
			)
		);
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( is_wp_error( $r ) ) {
			$diag[] = $host . ': błąd ' . $r->get_error_message();
			continue;
		}
		$code = wp_remote_retrieve_response_code( $r );
		$body = wp_remote_retrieve_body( $r );
		$type = wp_remote_retrieve_header( $r, 'content-type' );
		$diag[] = $host . ': HTTP ' . $code . ', ' . ( is_string( $type ) ? $type : '' ) . ', ' . strlen( $body ) . ' B';
		if ( 200 === (int) $code && 'PK' === substr( $body, 0, 2 ) ) {
			return $body;
		}
	}
	return false;
}

/* ------------------------------------------------------------------ */
/* Czytanie XLSX                                                       */
/* ------------------------------------------------------------------ */

function krs_w_col( $ref ) {
	$letters = preg_replace( '/[^A-Z]/', '', strtoupper( $ref ) );
	$n       = 0;
	for ( $i = 0; $i < strlen( $letters ); $i++ ) {
		$n = $n * 26 + ( ord( $letters[ $i ] ) - 64 );
	}
	return $n - 1;
}

function krs_w_xml( $str ) {
	if ( false === $str || '' === $str ) {
		return false;
	}
	$prev = libxml_use_internal_errors( true );
	$x    = simplexml_load_string( $str );
	libxml_use_internal_errors( $prev );
	return $x;
}

function krs_w_num( $v ) {
	if ( ! is_numeric( $v ) ) {
		return $v;
	}
	$f = (float) $v;
	if ( abs( $f - round( $f ) ) < 1e-9 ) {
		return (string) (int) round( $f );
	}
	return rtrim( rtrim( number_format( $f, 3, '.', '' ), '0' ), '.' );
}

/** Zwraca [ nazwa_arkusza => [ wiersz => [ kolumna => tekst ] ] ] */
function krs_w_read_xlsx( $bytes, &$err ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		$err = 'Brak rozszerzenia PHP zip (ZipArchive).';
		return false;
	}
	$tmp = tempnam( get_temp_dir(), 'krs' );
	if ( ! $tmp ) {
		$err = 'Brak katalogu tymczasowego.';
		return false;
	}
	file_put_contents( $tmp, $bytes );
	$z = new ZipArchive();
	if ( true !== $z->open( $tmp ) ) {
		$err = 'Nie da się otworzyć pliku xlsx.';
		@unlink( $tmp );
		return false;
	}
	$ss  = array();
	$sx  = krs_w_xml( $z->getFromName( 'xl/sharedStrings.xml' ) );
	if ( $sx ) {
		foreach ( $sx->si as $si ) {
			$t = '';
			if ( isset( $si->t ) ) {
				$t .= (string) $si->t;
			}
			foreach ( $si->r as $r ) {
				$t .= (string) $r->t;
			}
			$ss[] = $t;
		}
	}
	$wb   = krs_w_xml( $z->getFromName( 'xl/workbook.xml' ) );
	$rels = krs_w_xml( $z->getFromName( 'xl/_rels/workbook.xml.rels' ) );
	if ( ! $wb || ! $rels ) {
		$err = 'Niepoprawna struktura xlsx.';
		$z->close();
		@unlink( $tmp );
		return false;
	}
	$map = array();
	foreach ( $rels->Relationship as $rel ) {
		$t = (string) $rel['Target'];
		$t = ltrim( $t, '/' );
		if ( 0 !== strpos( $t, 'xl/' ) ) {
			$t = 'xl/' . $t;
		}
		$map[ (string) $rel['Id'] ] = $t;
	}
	$out = array();
	$wb->registerXPathNamespace( 'm', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' );
	foreach ( $wb->sheets->sheet as $sh ) {
		$name = (string) $sh['name'];
		$rid  = (string) $sh->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )->id;
		if ( empty( $map[ $rid ] ) ) {
			continue;
		}
		$x = krs_w_xml( $z->getFromName( $map[ $rid ] ) );
		if ( ! $x ) {
			continue;
		}
		$rows = array();
		foreach ( $x->sheetData->row as $row ) {
			$rn = (int) $row['r'];
			foreach ( $row->c as $c ) {
				$ref = (string) $c['r'];
				$t   = (string) $c['t'];
				$v   = '';
				if ( 's' === $t ) {
					$i = (int) $c->v;
					$v = isset( $ss[ $i ] ) ? $ss[ $i ] : '';
				} elseif ( 'inlineStr' === $t ) {
					$v = (string) $c->is->t;
					foreach ( $c->is->r as $r ) {
						$v .= (string) $r->t;
					}
				} else {
					$v = isset( $c->v ) ? (string) $c->v : '';
					if ( 'str' !== $t && 'b' !== $t && 'e' !== $t ) {
						$v = krs_w_num( $v );
					}
				}
				$v = trim( preg_replace( '/\s+/u', ' ', $v ) );
				if ( '' !== $v ) {
					$rows[ $rn ][ krs_w_col( $ref ) ] = $v;
				}
			}
		}
		ksort( $rows );
		$out[ $name ] = $rows;
	}
	$z->close();
	@unlink( $tmp );
	return $out;
}

function krs_w_pick_num( $d ) {
	foreach ( $d as $v ) {
		if ( preg_match( '/^\d+$/', $v ) ) {
			return $v;
		}
	}
	return '';
}

function krs_w_key( $name ) {
	$n = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );
	return trim( preg_replace( '/\s+/u', ' ', $n ) );
}

/** Szuka kolumny w nagłówku. */
function krs_w_find( $hdr, $re ) {
	foreach ( $hdr as $c => $v ) {
		if ( preg_match( $re, $v ) ) {
			return $c;
		}
	}
	return null;
}

function krs_w_parse( $sheets ) {
	$data = array(
		'general' => array(),
		'rounds'  => array(),
	);
	foreach ( $sheets as $name => $rows ) {
		$rowNums = array_keys( $rows );
		// ---- klasyfikacja generalna
		if ( preg_match( '/klasyfikacja/i', $name ) ) {
			foreach ( $rowNums as $idx => $rn ) {
				$r = $rows[ $rn ];
				$kc = krs_w_find( $r, '/^kierowca$/iu' );
				if ( null === $kc ) {
					continue;
				}
				$pc = krs_w_find( $r, '/punkt/iu' );
				$mc = krs_w_find( $r, '/miejsce/iu' );
				for ( $j = $idx + 1; $j < count( $rowNums ); $j++ ) {
					$d = $rows[ $rowNums[ $j ] ];
					if ( empty( $d[ $kc ] ) ) {
						break;
					}
					$data['general'][] = array(
						'n' => $d[ $kc ],
						's' => null !== $pc && isset( $d[ $pc ] ) ? $d[ $pc ] : '',
						'p' => null !== $mc && isset( $d[ $mc ] ) ? $d[ $mc ] : '',
					);
				}
				break;
			}
			continue;
		}
		// ---- rundy
		if ( ! preg_match( '/runda\s*(\d+)/iu', $name, $m ) ) {
			continue;
		}
		$no    = (int) $m[1];
		$round = array(
			'no'     => $no,
			'title'  => '',
			'note'   => '',
			'groups' => array(),
		);
		foreach ( $rowNums as $idx => $rn ) {
			$r = $rows[ $rn ];
			foreach ( $r as $v ) {
				if ( preg_match( '/nieklasyfikowan/iu', $v ) ) {
					$round['note'] = $v;
				}
			}
			if ( '' === $round['title'] ) {
				foreach ( $r as $v ) {
					if ( preg_match( '/^runda\s*\d+\s*[-–]/iu', $v ) ) {
						$round['title'] = $v;
						break;
					}
				}
			}
			$kc = krs_w_find( $r, '/^kierowca$/iu' );
			if ( null === $kc ) {
				continue;
			}
			// wiersz nad nagłówkiem: nazwa grupy + etykiety (Kwalifikacje / Trening)
			$above = $idx > 0 ? $rows[ $rowNums[ $idx - 1 ] ] : array();
			$gname = '';
			$cols  = array();
			foreach ( $r as $c => $h ) {
				$label = $h;
				if ( isset( $above[ $c ] ) && ! preg_match( '/fina|grupa|wy[sś]cig/iu', $above[ $c ] ) ) {
					$label = rtrim( $above[ $c ], ': ' ) . ' — ' . $h;
				}
				$cols[ $c ] = $label;
			}
			foreach ( $above as $c => $v ) {
				if ( preg_match( '/fina|grupa|wy[sś]cig/iu', $v ) ) {
					$gname = rtrim( $v, ': ' );
					break;
				}
			}
			$list = array();
			for ( $j = $idx + 1; $j < count( $rowNums ); $j++ ) {
				$d = $rows[ $rowNums[ $j ] ];
				if ( empty( $d[ $kc ] ) || preg_match( '/^kierowca$/iu', $d[ $kc ] ) || preg_match( '/^(fina[łl]|grupa|wy[sś]cig)\b/iu', $d[ $kc ] ) ) {
					break;
				}
				$nx = isset( $rowNums[ $j + 1 ] ) ? $rows[ $rowNums[ $j + 1 ] ] : array();
				if ( null !== krs_w_find( $nx, '/^kierowca$/iu' ) && ! preg_match( '/^\d+$/', (string) krs_w_pick_num( $d ) ) && preg_match( '/:$/', $d[ $kc ] ) ) {
					break;
				}
				$item = array();
				foreach ( $cols as $c => $label ) {
					$item[ $label ] = isset( $d[ $c ] ) ? $d[ $c ] : '';
				}
				$maxc  = max( array_keys( $cols ) );
				$extra = array();
				foreach ( $d as $c => $v ) {
					if ( $c > $maxc + 0 && '' !== $v ) {
						$extra[] = $v;
					}
				}
				if ( $extra ) {
					$item['_note'] = implode( ' ', $extra );
				}
				$list[] = $item;
			}
			if ( $list ) {
				$round['groups'][] = array(
					'name' => $gname ? $gname : 'Wyniki',
					'cols' => array_values( $cols ),
					'rows' => $list,
				);
			}
		}
		if ( $round['groups'] || $round['note'] ) {
			$data['rounds'][ $no ] = $round;
		}
	}
	ksort( $data['rounds'] );
	$data['rounds'] = array_values( $data['rounds'] );
	return $data;
}

/* ------------------------------------------------------------------ */
/* Odświeżanie                                                         */
/* ------------------------------------------------------------------ */

function krs_w_refresh( $force = false ) {
	try {
		return krs_w_refresh_inner( $force );
	} catch ( \Throwable $e ) {
		delete_transient( 'krs_wyniki_lock' );
		set_transient( KRS_W_FRESH, 1, 5 * MINUTE_IN_SECONDS );
		update_option( KRS_W_OPT_LOG, array( 'time' => time(), 'ok' => false, 'msg' => 'Błąd: ' . $e->getMessage(), 'diag' => array() ), false );
		return false;
	}
}

function krs_w_refresh_inner( $force = false ) {
	if ( ! $force && get_transient( KRS_W_FRESH ) ) {
		return true;
	}
	if ( ! $force && get_transient( 'krs_wyniki_lock' ) ) {
		return false;
	}
	set_transient( 'krs_wyniki_lock', 1, 60 );
	$set  = krs_w_settings();
	$diag = array();
	$log  = array( 'time' => time(), 'ok' => false, 'msg' => '', 'diag' => array() );
	$bytes = krs_w_download( $diag );
	$log['diag'] = $diag;
	if ( false === $bytes ) {
		$log['msg'] = 'Nie udało się pobrać arkusza.';
		update_option( KRS_W_OPT_LOG, $log, false );
		set_transient( KRS_W_FRESH, 1, 5 * MINUTE_IN_SECONDS );
		delete_transient( 'krs_wyniki_lock' );
		return false;
	}
	$err    = '';
	$sheets = krs_w_read_xlsx( $bytes, $err );
	if ( false === $sheets ) {
		$log['msg'] = $err;
		update_option( KRS_W_OPT_LOG, $log, false );
		set_transient( KRS_W_FRESH, 1, 5 * MINUTE_IN_SECONDS );
		delete_transient( 'krs_wyniki_lock' );
		return false;
	}
	$data = krs_w_parse( $sheets );
	if ( empty( $data['general'] ) && empty( $data['rounds'] ) ) {
		$log['msg'] = 'Arkusz pobrany, ale nie znaleziono danych (arkusze: ' . implode( ', ', array_keys( $sheets ) ) . ').';
		update_option( KRS_W_OPT_LOG, $log, false );
		delete_transient( 'krs_wyniki_lock' );
		return false;
	}
	$data['fetched'] = time();
	$data['sheets']  = array_keys( $sheets );
	update_option( KRS_W_OPT_DATA, $data, false );
	$log['ok']  = true;
	$log['msg'] = 'OK: ' . count( $data['general'] ) . ' zawodników w klasyfikacji, rundy z danymi: ' . count( $data['rounds'] ) . '.';
	update_option( KRS_W_OPT_LOG, $log, false );
	set_transient( KRS_W_FRESH, 1, max( 5, (int) $set['ttl'] ) * MINUTE_IN_SECONDS );
	delete_transient( 'krs_wyniki_lock' );
	return true;
}

// cron
add_filter(
	'cron_schedules',
	function ( $s ) {
		$s['krs_15min'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => 'Co 15 minut (KRS Wyniki)',
		);
		return $s;
	}
);
add_action( 'krs_wyniki_cron', function () { krs_w_refresh( true ); } );
register_activation_hook(
	__FILE__,
	function () {
		if ( ! wp_next_scheduled( 'krs_wyniki_cron' ) ) {
			wp_schedule_event( time() + 60, 'krs_15min', 'krs_wyniki_cron' );
		}
	}
);
register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'krs_wyniki_cron' );
	}
);
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'krs_wyniki_cron' ) ) {
			wp_schedule_event( time() + 60, 'krs_15min', 'krs_wyniki_cron' );
		}
	}
);

/* ------------------------------------------------------------------ */
/* Wyświetlanie                                                        */
/* ------------------------------------------------------------------ */

function krs_w_pick( $row, $re ) {
	foreach ( $row as $k => $v ) {
		if ( preg_match( $re, $k ) ) {
			return $v;
		}
	}
	return '';
}

function krs_w_fallback_meta() {
	return array(
		1  => array( '31.01.2026', 'Pole Position Bemowo' ),
		2  => array( '22.02.2026', 'Pole Position Janki' ),
		3  => array( '27.03.2026', 'A1Karting Warszawa' ),
		4  => array( '12.04.2026', 'Pole Position Janki' ),
		5  => array( '15.05.2026', 'Pole Position Warszawa' ),
		6  => array( '19.06.2026', 'Kartingowy PGE Narodowy' ),
		7  => array( '24.07.2026', 'Pole Position Janki' ),
		8  => array( '21.08.2026', 'A1Karting Warszawa' ),
		9  => array( '27.09.2026', 'Pole Position Bemowo' ),
		10 => array( '30.10.2026', 'S8 Race Nadarzyn' ),
		11 => array( '30.11.2026', 'Racing Center Warsaw' ),
	);
}

function krs_w_round_meta( $round ) {
	$title = $round['title'];
	$fb    = krs_w_fallback_meta();
	$date  = isset( $fb[ $round['no'] ] ) ? $fb[ $round['no'] ][0] : '';
	$track = isset( $fb[ $round['no'] ] ) ? $fb[ $round['no'] ][1] : '';
	if ( $title ) {
		$parts = array_map( 'trim', preg_split( '/\s+[-–]\s+/u', $title ) );
		if ( count( $parts ) >= 3 ) {
			$date  = $parts[1];
			$track = implode( ' - ', array_slice( $parts, 2 ) );
		} elseif ( 2 === count( $parts ) ) {
			$track = $parts[1];
		}
	}
	return array( $date, $track );
}

function krs_w_render() {
	krs_w_refresh( false );
	$data = get_option( KRS_W_OPT_DATA );
	if ( empty( $data ) || ( empty( $data['general'] ) && empty( $data['rounds'] ) ) ) {
		return '<p class="krs-upd">Klasyfikacja jest chwilowo niedostępna. Spróbuj ponownie za kilka minut.</p>';
	}
	wp_enqueue_style( 'krs-wyniki', plugins_url( 'krs-wyniki.css', __FILE__ ), array(), KRS_W_VER );
	wp_enqueue_script( 'krs-share', plugins_url( 'krs-share.js', __FILE__ ), array(), KRS_W_VER, true );
	wp_enqueue_script( 'krs-wyniki', plugins_url( 'krs-wyniki.js', __FILE__ ), array( 'krs-share' ), KRS_W_VER, true );

	// indeks: zawodnik -> wyniki rund
	$per = array();
	foreach ( $data['rounds'] as $rd ) {
		list( $date, $track ) = krs_w_round_meta( $rd );
		foreach ( $rd['groups'] as $g ) {
			foreach ( $g['rows'] as $row ) {
				$name = krs_w_pick( $row, '/^kierowca$/iu' );
				if ( '' === $name ) {
					continue;
				}
				$per[ krs_w_key( $name ) ][] = array(
					'no'    => $rd['no'],
					'track' => $track,
					'group' => $g['name'],
					'place' => krs_w_pick( $row, '/^miejsce$/iu' ),
					'pts'   => krs_w_pick( $row, '/^punkty$/iu' ),
					'best'  => krs_w_pick( $row, '/^best lap$/iu' ),
				);
			}
		}
	}

	$gen = array();
	foreach ( $data['general'] as $r ) {
		$gen[ krs_w_key( $r['n'] ) ] = $r;
	}
	$share = function ( $o ) {
		return '<button type="button" class="krs-share" data-krs-share="' . esc_attr( wp_json_encode( $o ) ) . '" aria-label="Udostępnij grafikę: ' . esc_attr( $o['n'] ) . '" title="Dodaj na swoje social media"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M18 16.1c-.8 0-1.5.3-2 .8l-7.1-4.1c.1-.3.1-.5.1-.8s0-.5-.1-.8L16 7.1c.5.5 1.2.9 2 .9 1.7 0 3-1.3 3-3s-1.3-3-3-3-3 1.3-3 3c0 .3 0 .5.1.8L8 9.9C7.5 9.4 6.8 9 6 9c-1.7 0-3 1.3-3 3s1.3 3 3 3c.8 0 1.5-.3 2-.8l7.1 4.2c-.1.2-.1.4-.1.7 0 1.6 1.3 2.9 2.9 2.9s2.9-1.3 2.9-2.9-1.2-2.9-2.8-2.9z"/></svg><span>Udostępnij</span></button>';
	};
	$bg   = wp_get_attachment_url( 5057 );
	$logo = wp_get_attachment_url( 5001 );
	$h  = '<div class="krs-res" data-krs-res data-krs-bg="' . esc_url( $bg ) . '" data-krs-logo="' . esc_url( $logo ) . '">';
	$h .= '<nav class="krs-tabs" aria-label="Wybór klasyfikacji"><a href="#generalna" data-krs-tab="generalna">Generalna</a>';
	foreach ( $data['rounds'] as $rd ) {
		$h .= '<a href="#runda-' . (int) $rd['no'] . '" data-krs-tab="runda-' . (int) $rd['no'] . '">Runda ' . (int) $rd['no'] . '</a>';
	}
	$h .= '</nav>';

	// --- generalna
	$g  = $data['general'];
	$h .= '<section class="krs-panel" id="generalna" data-krs-panel="generalna">';
	if ( count( $g ) >= 3 ) {
		$h .= '<div class="krs-podium">';
		foreach ( array( 1, 0, 2 ) as $i ) {
			$h .= '<div class="p' . ( $i + 1 ) . '"><div class="n">' . esc_html( $g[ $i ]['p'] ? $g[ $i ]['p'] : $i + 1 ) . '</div><div class="who">' . esc_html( $g[ $i ]['n'] ) . '</div><div class="pt">' . esc_html( $g[ $i ]['s'] ) . ' PKT</div></div>';
		}
		$h .= '</div>';
	}
	$h .= '<p class="krs-hint">Kliknij zawodnika, aby zobaczyć jego wyniki z każdej rundy.</p>';
	$h .= '<div class="krs-table"><div class="row head"><span class="p">Poz</span><span class="d">Kierowca</span><span class="s">Punkty</span><span class="sh"></span></div>';
	foreach ( $g as $r ) {
		$k   = krs_w_key( $r['n'] );
		$res = isset( $per[ $k ] ) ? $per[ $k ] : array();
		$h  .= '<details class="krs-drv" id="kierowca-' . esc_attr( sanitize_title( $r['n'] ) ) . '"><summary class="row"><span class="p">' . esc_html( $r['p'] ) . '</span><span class="d">' . esc_html( $r['n'] ) . '</span><span class="s">' . esc_html( $r['s'] ) . '</span>' . $share( array( 'n' => $r['n'], 'p' => $r['p'], 's' => $r['s'], 'sc' => 'general' ) ) . '</summary>';
		$h  .= '<div class="krs-drv-body">';
		if ( $res ) {
			$h .= '<table><thead><tr><th>Runda</th><th>Tor</th><th>Grupa</th><th>Miejsce</th><th>Punkty</th><th>Best lap</th></tr></thead><tbody>';
			foreach ( $res as $x ) {
				$h .= '<tr><td><a href="#runda-' . (int) $x['no'] . '">R' . (int) $x['no'] . '</a></td><td>' . esc_html( $x['track'] ) . '</td><td>' . esc_html( $x['group'] ) . '</td><td>' . esc_html( $x['place'] ) . '</td><td>' . esc_html( $x['pts'] ) . '</td><td>' . esc_html( $x['best'] ) . '</td></tr>';
			}
			$h .= '</tbody></table>';
		} else {
			$h .= '<p>Brak wyników rund w arkuszu.</p>';
		}
		$h .= '</div></details>';
	}
	$h .= '</div></section>';

	// --- rundy
	foreach ( $data['rounds'] as $rd ) {
		list( $date, $track ) = krs_w_round_meta( $rd );
		$ev  = function_exists( 'krs_e_by_round' ) ? krs_e_by_round( $rd['no'] ) : null;
		$tr  = $ev ? krs_t_get( $ev['track'] ) : null;
		if ( $ev && $ev['ts'] ) {
			$date = wp_date( 'd.m.Y', $ev['ts'] );
		}
		$id  = 'runda-' . (int) $rd['no'];
		$h  .= '<section class="krs-panel" id="' . $id . '" data-krs-panel="' . $id . '">';
		$h  .= '<div class="krs-rhead"><span class="krs-label">Runda ' . (int) $rd['no'] . ( $date ? ' · ' . esc_html( $date ) : '' ) . '</span><h2>' . ( $tr ? '<a href="' . esc_url( krs_t_url( $tr ) ) . '" title="Zobacz tor">' . esc_html( $track ? $track : $tr['title'] ) . '</a>' : esc_html( $track ? $track : 'Runda ' . (int) $rd['no'] ) ) . '</h2>';
		if ( $ev ) {
			$h .= '<p class="krs-rlinks">' . ( $tr ? '<a href="' . esc_url( krs_t_url( $tr ) ) . '">Tor: ' . esc_html( $tr['title'] ) . ' →</a>' : '' ) . '<a href="' . esc_url( home_url( '/kalendarz/#wydarzenie-' . $ev['id'] ) ) . '">Kalendarz →</a>' . ( $ev['fb'] ? '<a href="' . esc_url( $ev['fb'] ) . '" target="_blank" rel="noopener">Wydarzenie na FB →</a>' : '' ) . '</p>';
		}
		$h  .= '</div>';
		if ( ! empty( $rd['note'] ) && empty( $rd['groups'] ) ) {
			$h .= '<div class="krs-note">' . esc_html( $rd['note'] ) . '</div>';
		}
		foreach ( $rd['groups'] as $gr ) {
			$h .= '<h3 class="krs-mark">' . esc_html( $gr['name'] ) . '</h3><div class="krs-scroll"><table class="krs-rtable"><thead><tr>';
			$cols = array();
			foreach ( $gr['cols'] as $c ) {
				$has = preg_match( '/^(kierowca|miejsce)$/iu', $c );
				foreach ( $gr['rows'] as $row ) {
					if ( isset( $row[ $c ] ) && '' !== $row[ $c ] ) {
						$has = true;
						break;
					}
				}
				if ( $has ) {
					$cols[] = $c;
				}
			}
			// kolejność: Miejsce, Kierowca, reszta
			$order = array();
			foreach ( $cols as $c ) {
				if ( preg_match( '/^miejsce$/iu', $c ) ) {
					$order[] = $c;
				}
			}
			foreach ( $cols as $c ) {
				if ( preg_match( '/^kierowca$/iu', $c ) ) {
					$order[] = $c;
				}
			}
			foreach ( $cols as $c ) {
				if ( ! in_array( $c, $order, true ) ) {
					$order[] = $c;
				}
			}
			foreach ( $order as $c ) {
				$h .= '<th>' . esc_html( preg_match( '/^miejsce$/iu', $c ) ? 'Poz' : $c ) . '</th>';
			}
			$h .= '<th class="sh"></th></tr></thead><tbody>';
			foreach ( $gr['rows'] as $ri => $row ) {
				$dn  = krs_w_pick( $row, '/^kierowca$/iu' );
				$gk  = krs_w_key( $dn );
				$odd = ( 0 === $ri % 2 ) ? ' odd' : '';
				$h  .= '<tr class="krs-click' . $odd . '" tabindex="0" aria-expanded="false" title="Pokaż miejsce w klasyfikacji generalnej">';
				foreach ( $order as $c ) {
					$cls = preg_match( '/^kierowca$/iu', $c ) ? ' class="d"' : ( preg_match( '/^miejsce$/iu', $c ) ? ' class="p"' : ( preg_match( '/^punkty$/iu', $c ) ? ' class="s"' : '' ) );
					$h  .= '<td' . $cls . '>' . esc_html( isset( $row[ $c ] ) ? $row[ $c ] : '' ) . '</td>';
				}
				$h .= '<td class="sh">' . $share( array( 'n' => $dn, 'p' => krs_w_pick( $row, '/^miejsce$/iu' ), 's' => krs_w_pick( $row, '/^punkty$/iu' ), 'b' => krs_w_pick( $row, '/^best lap$/iu' ), 'sc' => 'round', 'r' => (int) $rd['no'], 'd' => $date, 't' => $track ? $track : ( $tr ? $tr['title'] : '' ), 'g' => $gr['name'] ) ) . '</td>';
				$h .= '</tr><tr class="krs-genrow' . $odd . '" hidden><td colspan="' . ( count( $order ) + 1 ) . '">';
				if ( isset( $gen[ $gk ] ) ) {
					$h .= '<span class="krs-label">Klasyfikacja generalna:</span> <strong class="krs-genpos">' . esc_html( $gen[ $gk ]['p'] ) . '. miejsce</strong> <span class="krs-genpts">' . esc_html( $gen[ $gk ]['s'] ) . ' pkt</span> <a class="krs-more" href="#kierowca-' . esc_attr( sanitize_title( $gen[ $gk ]['n'] ) ) . '">Wszystkie wyniki →</a>';
				} else {
					$h .= '<span class="krs-label">Brak zawodnika w klasyfikacji generalnej.</span>';
				}
				$h .= '</td></tr>';
			}
			$h .= '</tbody></table></div>';
			$notes = array();
			foreach ( $gr['rows'] as $row ) {
				if ( ! empty( $row['_note'] ) ) {
					$notes[] = '<li><strong>' . esc_html( krs_w_pick( $row, '/^kierowca$/iu' ) ) . ':</strong> ' . esc_html( $row['_note'] ) . '</li>';
				}
			}
			if ( $notes ) {
				$h .= '<ul class="krs-notes">' . implode( '', $notes ) . '</ul>';
			}
		}
		$h .= '</section>';
	}
	$h .= '<p class="krs-upd">Dane z arkusza KartRace Series · ostatnia aktualizacja: ' . esc_html( wp_date( 'j.m.Y, H:i', (int) $data['fetched'] ) ) . '</p>';
	$h .= '</div>';
	return $h;
}
add_shortcode( 'krs_klasyfikacja', 'krs_w_render' );

/** Lista rund z danymi — do przycisków w kalendarzu: [krs_runda_link nr="3"] */
add_shortcode(
	'krs_runda_link',
	function ( $atts ) {
		$a    = shortcode_atts( array( 'nr' => 0, 'tekst' => 'Klasyfikacja rundy' ), $atts );
		$data = get_option( KRS_W_OPT_DATA );
		$ok   = false;
		if ( ! empty( $data['rounds'] ) ) {
			foreach ( $data['rounds'] as $rd ) {
				if ( (int) $rd['no'] === (int) $a['nr'] ) {
					$ok = true;
				}
			}
		}
		if ( ! $ok ) {
			return '';
		}
		return '<a class="krs-rlink" href="' . esc_url( home_url( '/klasyfikacja/#runda-' . (int) $a['nr'] ) ) . '">' . esc_html( $a['tekst'] ) . '</a>';
	}
);


/** Top 3 klasyfikacji generalnej na stronę główną: [krs_liderzy] */
add_shortcode(
	'krs_liderzy',
	function () {
		krs_w_refresh( false );
		$data = get_option( KRS_W_OPT_DATA );
		if ( empty( $data['general'] ) ) {
			return '';
		}
		$h = '<ol class="krs-leaders">';
		foreach ( array_slice( $data['general'], 0, 3 ) as $i => $r ) {
			$h .= '<li><span class="pos">0' . ( $i + 1 ) . '</span><span class="name">' . esc_html( $r['n'] ) . '</span><span class="pts">' . esc_html( $r['s'] ) . ' PKT</span></li>';
		}
		$h .= '</ol><p style="margin-top:1.2rem"><a class="krs-more" href="' . esc_url( home_url( '/klasyfikacja/' ) ) . '">Pełna tabela →</a></p>';
		return $h;
	}
);


/* Pobranie obrazka z adresu do biblioteki mediów (tylko administrator) */
add_action(
	'wp_ajax_krs_sideload',
	function () {
		check_ajax_referer( 'krs_sideload' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'Brak uprawnień', 403 );
		}
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$alt = isset( $_POST['alt'] ) ? sanitize_text_field( wp_unslash( $_POST['alt'] ) ) : '';
		$id  = media_sideload_image( $url, 0, $alt, 'id' );
		if ( is_wp_error( $id ) ) {
			wp_send_json_error( $id->get_error_message() );
		}
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		wp_send_json_success( array( 'id' => $id, 'url' => wp_get_attachment_url( $id ) ) );
	}
);


/** Przycisk zapisów widoczny tylko przed wydarzeniem: [krs_zapisy do="2026-11-30 19:25"] */
add_shortcode(
	'krs_zapisy',
	function ( $atts ) {
		$a = shortcode_atts(
			array(
				'do'    => '',
				'tekst' => 'Zapisz się na zawody',
				'link'  => 'https://m.me/61584256544468',
			),
			$atts
		);
		if ( '' === $a['do'] ) {
			return '';
		}
		try {
			$end = new DateTime( $a['do'], wp_timezone() );
		} catch ( \Exception $e ) {
			return '';
		}
		if ( time() >= $end->getTimestamp() ) {
			return '';
		}
		return '<a class="krs-signup" href="' . esc_url( $a['link'] ) . '" target="_blank" rel="noopener">' . esc_html( $a['tekst'] ) . '</a>';
	}
);

/* ------------------------------------------------------------------ */
/* Panel admina: Ustawienia → KRS Wyniki                               */
/* ------------------------------------------------------------------ */

add_action(
	'admin_menu',
	function () {
		add_options_page( 'KRS Wyniki', 'KRS Wyniki', 'manage_options', 'krs-wyniki', 'krs_w_admin_page' );
	}
);

add_action(
	'admin_post_krs_wyniki_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Brak uprawnień' );
		}
		check_admin_referer( 'krs_wyniki_save' );
		$set           = krs_w_settings();
		$set['url']    = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : $set['url'];
		$set['direct'] = isset( $_POST['direct'] ) ? esc_url_raw( wp_unslash( $_POST['direct'] ) ) : '';
		$set['ttl']    = isset( $_POST['ttl'] ) ? max( 5, (int) $_POST['ttl'] ) : 15;
		update_option( KRS_W_OPT_SET, $set );
		krs_w_refresh( true );
		wp_safe_redirect( admin_url( 'options-general.php?page=krs-wyniki&done=1' ) );
		exit;
	}
);

add_action(
	'admin_post_krs_wyniki_refresh',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Brak uprawnień' );
		}
		check_admin_referer( 'krs_wyniki_refresh' );
		krs_w_refresh( true );
		wp_safe_redirect( admin_url( 'options-general.php?page=krs-wyniki&done=1' ) );
		exit;
	}
);

function krs_w_admin_page() {
	$set  = krs_w_settings();
	$log  = get_option( KRS_W_OPT_LOG );
	$data = get_option( KRS_W_OPT_DATA );
	echo '<div class="wrap" data-krs-sideload="' . esc_attr( wp_create_nonce( 'krs_sideload' ) ) . '"><h1>KRS Wyniki</h1>';
	echo '<p>Wyniki na stronie <a href="' . esc_url( home_url( '/klasyfikacja/' ) ) . '">Klasyfikacja</a> są pobierane z arkusza Excel na OneDrive (tylko odczyt — arkusz nie jest zmieniany) i odświeżane automatycznie co kilkanaście minut.</p>';
	if ( $log ) {
		echo '<div class="notice ' . ( $log['ok'] ? 'notice-success' : 'notice-error' ) . '"><p><strong id="krs-status">' . esc_html( $log['msg'] ) . '</strong> (' . esc_html( wp_date( 'j.m.Y H:i:s', $log['time'] ) ) . ')</p>';
		if ( ! empty( $log['diag'] ) ) {
			echo '<ul id="krs-diag" style="margin-left:1.5em;list-style:disc">';
			foreach ( $log['diag'] as $d ) {
				echo '<li>' . esc_html( $d ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'krs_wyniki_save' );
	echo '<input type="hidden" name="action" value="krs_wyniki_save"><table class="form-table">';
	echo '<tr><th><label for="krs-url">Link do arkusza (udostępnianie OneDrive)</label></th><td><input id="krs-url" name="url" type="url" class="large-text" value="' . esc_attr( $set['url'] ) . '"></td></tr>';
	echo '<tr><th><label for="krs-direct">Link bezpośredni do pobrania (opcjonalnie)</label></th><td><input id="krs-direct" name="direct" type="url" class="large-text" value="' . esc_attr( $set['direct'] ) . '"><p class="description">Używany jako pierwszy, jeśli wpisany.</p></td></tr>';
	echo '<tr><th><label for="krs-ttl">Odświeżanie co (minut)</label></th><td><input id="krs-ttl" name="ttl" type="number" min="5" value="' . esc_attr( $set['ttl'] ) . '"></td></tr>';
	echo '</table>';
	submit_button( 'Zapisz i pobierz dane' );
	echo '</form>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'krs_wyniki_refresh' );
	echo '<input type="hidden" name="action" value="krs_wyniki_refresh">';
	submit_button( 'Odśwież teraz', 'secondary', 'krs-refresh' );
	echo '</form>';
	if ( $data && ! empty( $data['fetched'] ) ) {
		echo '<h2>Ostatnio pobrane dane</h2><p id="krs-summary">Pobrano: ' . esc_html( wp_date( 'j.m.Y H:i', $data['fetched'] ) ) . ' · zawodników w klasyfikacji: ' . count( $data['general'] ) . ' · rundy z danymi: ' . esc_html( implode( ', ', wp_list_pluck( $data['rounds'], 'no' ) ) ) . ' · arkusze: ' . esc_html( implode( ', ', (array) $data['sheets'] ) ) . '</p>';
	}
	echo '</div>';
}
