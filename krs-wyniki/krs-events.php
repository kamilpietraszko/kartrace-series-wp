<?php
/**
 * KRS Wyniki — wydarzenia (kalendarz) i tory.
 * Jedno źródło prawdy: Wydarzenia i Tory w panelu WordPress. Kalendarz, Tory,
 * „Następna runda" i nagłówki rund w klasyfikacji budują się z tych danych.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ */
/* Typy treści                                                         */
/* ------------------------------------------------------------------ */

add_action(
	'init',
	function () {
		register_post_type(
			'krs_event',
			array(
				'labels'       => array(
					'name'          => 'Wydarzenia KRS',
					'singular_name' => 'Wydarzenie',
					'add_new'       => 'Dodaj wydarzenie',
					'add_new_item'  => 'Dodaj wydarzenie',
					'edit_item'     => 'Edytuj wydarzenie',
					'all_items'     => 'Wszystkie wydarzenia',
					'menu_name'     => 'Wydarzenia KRS',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => false,
				'menu_icon'    => 'dashicons-flag',
				'supports'     => array( 'title' ),
			)
		);
		register_post_type(
			'krs_tor',
			array(
				'labels'       => array(
					'name'          => 'Tory',
					'singular_name' => 'Tor',
					'add_new'       => 'Dodaj tor',
					'add_new_item'  => 'Dodaj tor',
					'edit_item'     => 'Edytuj tor',
					'all_items'     => 'Tory',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=krs_event',
				'show_in_rest' => false,
				'supports'     => array( 'title' ),
			)
		);
	}
);

/* ------------------------------------------------------------------ */
/* Dane pomocnicze                                                     */
/* ------------------------------------------------------------------ */

function krs_e_types() {
	return array(
		'runda'     => 'Runda KartRace Series CUP',
		'endurance' => 'Endurance',
		'trackday'  => 'Track Day',
		'inne'      => 'Inne wydarzenie',
	);
}

function krs_e_get( $id ) {
	$start = get_post_meta( $id, '_krs_start', true );
	try {
		$ts = $start ? ( new DateTime( $start, wp_timezone() ) )->getTimestamp() : 0;
	} catch ( \Exception $e ) {
		$ts = 0;
	}
	return array(
		'id'    => $id,
		'title' => get_the_title( $id ),
		'start' => $start,
		'ts'    => $ts,
		'type'  => get_post_meta( $id, '_krs_type', true ),
		'round' => (int) get_post_meta( $id, '_krs_round', true ),
		'fb'    => get_post_meta( $id, '_krs_fb', true ),
		'track' => (int) get_post_meta( $id, '_krs_track', true ),
	);
}

function krs_e_all() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$ids   = get_posts(
		array(
			'post_type'      => 'krs_event',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	$cache = array_map( 'krs_e_get', $ids );
	usort(
		$cache,
		function ( $a, $b ) {
			return $a['ts'] <=> $b['ts'];
		}
	);
	return $cache;
}

function krs_t_get( $id ) {
	if ( ! $id || 'krs_tor' !== get_post_type( $id ) ) {
		return null;
	}
	$p = get_post( $id );
	return array(
		'id'     => $id,
		'title'  => $p->post_title,
		'slug'   => $p->post_name ? $p->post_name : sanitize_title( $p->post_title ),
		'city'   => get_post_meta( $id, '_krs_city', true ),
		'images' => array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $id, '_krs_images', true ) ) ) ),
		'srcl'   => get_post_meta( $id, '_krs_src_label', true ),
		'srcu'   => get_post_meta( $id, '_krs_src_url', true ),
		'order'  => (int) $p->menu_order,
	);
}

function krs_t_url( $t ) {
	return home_url( '/tory/#tor-' . $t['slug'] );
}

/** Rundy, które mają dane w arkuszu. */
function krs_e_rounds_with_data() {
	$data = get_option( KRS_W_OPT_DATA );
	$out  = array();
	if ( ! empty( $data['rounds'] ) ) {
		foreach ( $data['rounds'] as $rd ) {
			$out[ (int) $rd['no'] ] = true;
		}
	}
	return $out;
}

/** Wydarzenie przypisane do rundy N (do nagłówków klasyfikacji). */
function krs_e_by_round( $n ) {
	foreach ( krs_e_all() as $e ) {
		if ( 'runda' === $e['type'] && (int) $e['round'] === (int) $n ) {
			return $e;
		}
	}
	return null;
}

function krs_e_label( $e ) {
	switch ( $e['type'] ) {
		case 'runda':
			return 'Runda ' . $e['round'] . ' · KartRace Series CUP';
		case 'endurance':
			return 'Endurance · ' . $e['title'];
		case 'trackday':
			return 'Track Day · ' . $e['title'];
	}
	return $e['title'];
}

function krs_e_date( $e, $with_time = true ) {
	if ( ! $e['ts'] ) {
		return '';
	}
	$days = array( 1 => 'pn', 2 => 'wt', 3 => 'śr', 4 => 'czw', 5 => 'pt', 6 => 'sb', 7 => 'nd' );
	$s    = wp_date( 'd.m.Y', $e['ts'] ) . ' (' . $days[ (int) wp_date( 'N', $e['ts'] ) ] . ')';
	$t    = wp_date( 'H:i', $e['ts'] );
	if ( $with_time && '00:00' !== $t ) {
		$s .= ', ' . $t;
	}
	return $s;
}

function krs_e_is_future( $e ) {
	// wydarzenie „trwa" jeszcze 6 godzin po starcie
	return $e['ts'] && time() < $e['ts'] + 6 * HOUR_IN_SECONDS;
}

function krs_e_assets() {
	wp_enqueue_style( 'krs-wyniki', plugins_url( 'krs-wyniki.css', __FILE__ ), array(), KRS_W_VER );
}

function krs_e_signup_url() {
	return 'https://m.me/61584256544468';
}

/* ------------------------------------------------------------------ */
/* Shortcody                                                           */
/* ------------------------------------------------------------------ */

function krs_e_card( $e, $data_rounds ) {
	$t    = krs_t_get( $e['track'] );
	$fut  = krs_e_is_future( $e );
	$h    = '<article class="krs-ev' . ( $fut ? ' is-next' : '' ) . '" id="wydarzenie-' . (int) $e['id'] . '">';
	$h   .= '<div class="krs-ev-main"><p class="krs-label' . ( $fut ? ' is-red' : '' ) . '">' . esc_html( krs_e_label( $e ) ) . '</p>';
	if ( $t ) {
		$h .= '<h3><a href="' . esc_url( krs_t_url( $t ) ) . '" title="Zobacz tor">' . esc_html( $t['title'] ) . '</a></h3>';
		if ( $t['city'] ) {
			$h .= '<p class="krs-ev-city">' . esc_html( $t['city'] ) . '</p>';
		}
	} else {
		$h .= '<h3>' . esc_html( $e['title'] ) . '</h3>';
	}
	$h .= '<p class="krs-ev-date">' . esc_html( krs_e_date( $e ) ) . '</p></div>';
	$h .= '<div class="krs-ev-side"><span class="krs-badge' . ( $fut ? ' red' : '' ) . '">' . ( $fut ? 'Nadchodząca' : 'Zakończona' ) . '</span>';
	if ( $e['fb'] ) {
		$h .= '<a class="krs-btn krs-btn-outline" href="' . esc_url( $e['fb'] ) . '" target="_blank" rel="noopener">Wydarzenie na FB</a>';
	}
	if ( $fut ) {
		$h .= '<a class="krs-btn krs-btn-red" href="' . esc_url( krs_e_signup_url() ) . '" target="_blank" rel="noopener">Zapisz się na zawody</a>';
	}
	if ( 'runda' === $e['type'] && ! empty( $data_rounds[ $e['round'] ] ) ) {
		$h .= '<a class="krs-btn krs-btn-line" href="' . esc_url( home_url( '/klasyfikacja/#runda-' . $e['round'] ) ) . '">Klasyfikacja rundy</a>';
	}
	$h .= '</div></article>';
	return $h;
}

add_shortcode(
	'krs_kalendarz',
	function () {
		krs_e_assets();
		$all  = krs_e_all();
		$dr   = krs_e_rounds_with_data();
		$next = array();
		$past = array();
		foreach ( $all as $e ) {
			if ( krs_e_is_future( $e ) ) {
				$next[] = $e;
			} else {
				$past[] = $e;
			}
		}
		$past = array_reverse( $past );
		$h    = '<div class="krs-cal">';
		$h   .= '<h2 class="krs-mark">Najbliższe</h2>';
		if ( $next ) {
			foreach ( $next as $e ) {
				$h .= krs_e_card( $e, $dr );
			}
		} else {
			$h .= '<p class="krs-empty">Kolejne wydarzenia wkrótce — śledź nas na <a href="https://www.facebook.com/profile.php?id=61584256544468" target="_blank" rel="noopener">Facebooku</a>.</p>';
		}
		if ( $past ) {
			$h .= '<h2 class="krs-mark krs-mt">Minione wydarzenia</h2>';
			foreach ( $past as $e ) {
				$h .= krs_e_card( $e, $dr );
			}
		}
		return $h . '</div>';
	}
);

add_shortcode(
	'krs_tory',
	function () {
		krs_e_assets();
		$dr  = krs_e_rounds_with_data();
		$ids = get_posts(
			array(
				'post_type'      => 'krs_tor',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			)
		);
		$by  = array();
		foreach ( krs_e_all() as $e ) {
			$by[ $e['track'] ][] = $e;
		}
		$h = '<div class="krs-tracks">';
		foreach ( $ids as $id ) {
			$t  = krs_t_get( $id );
			$h .= '<div class="krs-track" id="tor-' . esc_attr( $t['slug'] ) . '">';
			if ( $t['city'] ) {
				$h .= '<div class="city">' . esc_html( $t['city'] ) . '</div>';
			}
			$h .= '<h3>' . esc_html( $t['title'] ) . '</h3>';
			$h .= '<p class="krs-track-desc">Wyścigi kartingowe ligi KartRace Series na torze kartingowym ' . esc_html( $t['title'] ) . ( $t['city'] ? ' (' . esc_html( trim( explode( '·', $t['city'] )[0] ) ) . ')' : '' ) . '.</p><ul>';
			foreach ( isset( $by[ $id ] ) ? $by[ $id ] : array() as $e ) {
				$name = 'runda' === $e['type'] ? 'Runda ' . $e['round'] : $e['title'];
				$fut  = krs_e_is_future( $e );
				if ( 'runda' === $e['type'] && ! empty( $dr[ $e['round'] ] ) ) {
					$url = home_url( '/klasyfikacja/#runda-' . $e['round'] );
					$tip = 'Klasyfikacja rundy';
				} else {
					$url = home_url( '/kalendarz/#wydarzenie-' . $e['id'] );
					$tip = 'Zobacz w kalendarzu';
				}
				$h .= '<li><a href="' . esc_url( $url ) . '" title="' . esc_attr( $tip ) . '">' . esc_html( $name ) . ( $fut ? ' <em>(nadchodząca)</em>' : '' ) . ' <b>→</b></a><span>' . esc_html( $e['ts'] ? wp_date( 'd.m.Y', $e['ts'] ) : '' ) . '</span></li>';
			}
			$h .= '</ul>';
			if ( $t['images'] ) {
				$h .= '<div class="krs-layouts">';
				foreach ( $t['images'] as $im ) {
					$full = wp_get_attachment_url( $im );
					$mid  = wp_get_attachment_image_url( $im, 'large' );
					$meta = wp_get_attachment_metadata( $im );
					$tall = ! empty( $meta['height'] ) && ! empty( $meta['width'] ) && $meta['height'] > $meta['width'] * 1.6;
					$h   .= '<figure class="krs-layout' . ( $tall ? ' tall' : '' ) . '"><a href="' . esc_url( $full ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $mid ? $mid : $full ) . '" alt="' . esc_attr( get_post_meta( $im, '_wp_attachment_image_alt', true ) ) . '" loading="lazy"></a></figure>';
				}
				$h .= '</div>';
			}
			if ( $t['srcl'] ) {
				$h .= '<p class="src">Grafika toru: ' . ( $t['srcu'] ? '<a href="' . esc_url( $t['srcu'] ) . '" target="_blank" rel="noopener">' . esc_html( $t['srcl'] ) . '</a>' : esc_html( $t['srcl'] ) ) . '</p>';
			}
			$h .= '</div>';
		}
		return $h . '</div>';
	}
);

/** Kafel „Następna runda" na stronę główną. */
add_shortcode(
	'krs_nastepna',
	function () {
		krs_e_assets();
		$e = null;
		foreach ( krs_e_all() as $x ) {
			if ( krs_e_is_future( $x ) ) {
				$e = $x;
				break;
			}
		}
		if ( ! $e ) {
			return '<div class="krs-card krs-next"><p class="krs-label">Wkrótce</p><h4>Kolejna runda w przygotowaniu</h4><p class="krs-next-note">Śledź nas na Facebooku i Instagramie.</p></div>';
		}
		$t  = krs_t_get( $e['track'] );
		$h  = '<div class="krs-card krs-next" id="najblizsza-runda">';
		$h .= '<p class="krs-label">Tor:</p><div class="krs-next-top"><h4>' . ( $t ? '<a href="' . esc_url( krs_t_url( $t ) ) . '">' . esc_html( $t['title'] ) . '</a>' : esc_html( $e['title'] ) ) . '</h4><span class="krs-next-r">' . ( 'runda' === $e['type'] ? 'R' . (int) $e['round'] : '' ) . '</span></div>';
		$h .= '<p class="krs-label">Godz:</p><p class="krs-big">' . esc_html( wp_date( 'H:i', $e['ts'] ) ) . '</p>';
		$h .= '<p class="krs-label">Data:</p><p class="krs-big">' . esc_html( wp_date( 'd/m/Y', $e['ts'] ) ) . '</p></div>';
		$h .= '<p class="krs-next-note">' . esc_html( krs_e_label( $e ) ) . ( $t && $t['city'] ? ' — ' . esc_html( $t['title'] . ', ' . $t['city'] ) : '' ) . '.</p>';
		$h .= '<div class="krs-next-btns">';
		if ( $e['fb'] ) {
			$h .= '<a class="krs-btn krs-btn-outline" href="' . esc_url( $e['fb'] ) . '" target="_blank" rel="noopener">Wydarzenie na Facebooku</a>';
		}
		$h .= '<a class="krs-btn krs-btn-red" href="' . esc_url( krs_e_signup_url() ) . '" target="_blank" rel="noopener">Zapisz się na zawody</a></div>';
		return $h;
	}
);

/* ------------------------------------------------------------------ */
/* Panel: pola wydarzenia i toru                                       */
/* ------------------------------------------------------------------ */

add_action(
	'add_meta_boxes',
	function () {
		add_meta_box( 'krs_event_box', 'Szczegóły wydarzenia', 'krs_e_box', 'krs_event', 'normal', 'high' );
		add_meta_box( 'krs_tor_box', 'Szczegóły toru', 'krs_t_box', 'krs_tor', 'normal', 'high' );
	}
);

function krs_e_box( $post ) {
	wp_nonce_field( 'krs_e_save', 'krs_e_nonce' );
	$e      = krs_e_get( $post->ID );
	$tracks = get_posts( array( 'post_type' => 'krs_tor', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	$val    = $e['start'] ? str_replace( ' ', 'T', substr( $e['start'], 0, 16 ) ) : '';
	echo '<p style="color:#555">Wpisz to samo, co w wydarzeniu na Facebooku. Kalendarz, „Następna runda", Tory i nagłówki rund w klasyfikacji zaktualizują się same.</p>';
	echo '<table class="form-table"><tr><th><label for="krs_start">Data i godzina</label></th><td><input type="datetime-local" id="krs_start" name="krs_start" value="' . esc_attr( $val ) . '" required></td></tr>';
	echo '<tr><th><label for="krs_type">Rodzaj</label></th><td><select id="krs_type" name="krs_type">';
	foreach ( krs_e_types() as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $e['type'] ? $e['type'] : 'runda', $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><th><label for="krs_round">Numer rundy</label></th><td><input type="number" min="1" id="krs_round" name="krs_round" value="' . esc_attr( $e['round'] ? $e['round'] : '' ) . '" style="width:6em"><p class="description">Tylko dla rund CUP — musi się zgadzać z zakładką „Runda N" w arkuszu wyników.</p></td></tr>';
	echo '<tr><th><label for="krs_fb">Link do wydarzenia na Facebooku</label></th><td><input type="url" id="krs_fb" name="krs_fb" class="large-text" value="' . esc_attr( $e['fb'] ) . '" placeholder="https://www.facebook.com/events/..."></td></tr>';
	echo '<tr><th><label for="krs_track">Tor</label></th><td><select id="krs_track" name="krs_track"><option value="">— wybierz —</option>';
	foreach ( $tracks as $t ) {
		echo '<option value="' . (int) $t->ID . '"' . selected( $e['track'], $t->ID, false ) . '>' . esc_html( $t->post_title ) . '</option>';
	}
	echo '</select><p style="margin:.6em 0 .2em">albo <strong>nowy tor</strong> (doda się sam do zakładki Tory):</p><input type="text" name="krs_new_track" class="regular-text" placeholder="Nazwa toru"> <input type="text" name="krs_new_city" class="regular-text" placeholder="Miejscowość"></td></tr></table>';
}

add_action(
	'save_post_krs_event',
	function ( $id ) {
		if ( ! isset( $_POST['krs_e_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['krs_e_nonce'] ) ), 'krs_e_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		$start = isset( $_POST['krs_start'] ) ? sanitize_text_field( wp_unslash( $_POST['krs_start'] ) ) : '';
		update_post_meta( $id, '_krs_start', str_replace( 'T', ' ', $start ) );
		$type = isset( $_POST['krs_type'] ) ? sanitize_key( $_POST['krs_type'] ) : 'runda';
		update_post_meta( $id, '_krs_type', array_key_exists( $type, krs_e_types() ) ? $type : 'inne' );
		update_post_meta( $id, '_krs_round', isset( $_POST['krs_round'] ) ? absint( $_POST['krs_round'] ) : 0 );
		update_post_meta( $id, '_krs_fb', isset( $_POST['krs_fb'] ) ? esc_url_raw( wp_unslash( $_POST['krs_fb'] ) ) : '' );
		$track = isset( $_POST['krs_track'] ) ? absint( $_POST['krs_track'] ) : 0;
		$new   = isset( $_POST['krs_new_track'] ) ? sanitize_text_field( wp_unslash( $_POST['krs_new_track'] ) ) : '';
		if ( $new ) {
			$q      = new WP_Query( array( 'post_type' => 'krs_tor', 'title' => $new, 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true ) );
			$exists = $q->posts ? (int) $q->posts[0] : 0;
			if ( $exists ) {
				$track = $exists;
			} else {
				$track = wp_insert_post(
					array(
						'post_type'   => 'krs_tor',
						'post_status' => 'publish',
						'post_title'  => $new,
						'menu_order'  => 50,
					)
				);
				if ( $track && ! is_wp_error( $track ) ) {
					update_post_meta( $track, '_krs_city', isset( $_POST['krs_new_city'] ) ? sanitize_text_field( wp_unslash( $_POST['krs_new_city'] ) ) : '' );
				}
			}
		}
		update_post_meta( $id, '_krs_track', (int) $track );
	}
);

function krs_t_box( $post ) {
	wp_nonce_field( 'krs_t_save', 'krs_t_nonce' );
	wp_enqueue_media();
	$t = krs_t_get( $post->ID );
	echo '<table class="form-table"><tr><th><label for="krs_city">Miejscowość / opis</label></th><td><input type="text" id="krs_city" name="krs_city" class="regular-text" value="' . esc_attr( $t['city'] ) . '"></td></tr>';
	echo '<tr><th>Grafika nitki toru</th><td><input type="hidden" id="krs_images" name="krs_images" value="' . esc_attr( implode( ',', $t['images'] ) ) . '"><div id="krs_images_prev" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">';
	foreach ( $t['images'] as $im ) {
		echo wp_get_attachment_image( $im, 'thumbnail' );
	}
	echo '</div><button type="button" class="button" id="krs_images_btn">Wybierz grafikę</button> <button type="button" class="button-link" id="krs_images_clear">Usuń</button></td></tr>';
	echo '<tr><th><label for="krs_src_label">Źródło grafiki (podpis)</label></th><td><input type="text" id="krs_src_label" name="krs_src_label" class="regular-text" value="' . esc_attr( $t['srcl'] ) . '" placeholder="np. pole-position.pl"></td></tr>';
	echo '<tr><th><label for="krs_src_url">Źródło grafiki (link)</label></th><td><input type="url" id="krs_src_url" name="krs_src_url" class="large-text" value="' . esc_attr( $t['srcu'] ) . '"></td></tr></table>';
	echo '<script>jQuery(function($){var f;$("#krs_images_btn").on("click",function(e){e.preventDefault();if(f){f.open();return;}f=wp.media({title:"Grafika toru",multiple:true,library:{type:"image"}});f.on("select",function(){var ids=[],h="";f.state().get("selection").each(function(a){ids.push(a.id);var u=(a.attributes.sizes&&a.attributes.sizes.thumbnail)?a.attributes.sizes.thumbnail.url:a.attributes.url;h+="<img src=\""+u+"\" style=\"width:120px;height:auto\">";});$("#krs_images").val(ids.join(","));$("#krs_images_prev").html(h);});f.open();});$("#krs_images_clear").on("click",function(e){e.preventDefault();$("#krs_images").val("");$("#krs_images_prev").html("");});});</script>';
}

add_action(
	'save_post_krs_tor',
	function ( $id ) {
		if ( ! isset( $_POST['krs_t_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['krs_t_nonce'] ) ), 'krs_t_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		update_post_meta( $id, '_krs_city', isset( $_POST['krs_city'] ) ? sanitize_text_field( wp_unslash( $_POST['krs_city'] ) ) : '' );
		$ims = isset( $_POST['krs_images'] ) ? sanitize_text_field( wp_unslash( $_POST['krs_images'] ) ) : '';
		update_post_meta( $id, '_krs_images', implode( ',', array_filter( array_map( 'absint', explode( ',', $ims ) ) ) ) );
		update_post_meta( $id, '_krs_src_label', isset( $_POST['krs_src_label'] ) ? sanitize_text_field( wp_unslash( $_POST['krs_src_label'] ) ) : '' );
		update_post_meta( $id, '_krs_src_url', isset( $_POST['krs_src_url'] ) ? esc_url_raw( wp_unslash( $_POST['krs_src_url'] ) ) : '' );
	}
);

// Kolumny na liście wydarzeń
add_filter(
	'manage_krs_event_posts_columns',
	function ( $c ) {
		return array(
			'cb'        => $c['cb'],
			'title'     => 'Wydarzenie',
			'krs_date'  => 'Data',
			'krs_track' => 'Tor',
			'krs_round' => 'Runda',
		);
	}
);
add_action(
	'manage_krs_event_posts_custom_column',
	function ( $col, $id ) {
		$e = krs_e_get( $id );
		if ( 'krs_date' === $col ) {
			echo esc_html( krs_e_date( $e ) );
		} elseif ( 'krs_track' === $col ) {
			$t = krs_t_get( $e['track'] );
			echo esc_html( $t ? $t['title'] : '—' );
		} elseif ( 'krs_round' === $col ) {
			echo 'runda' === $e['type'] ? (int) $e['round'] : '—';
		}
	},
	10,
	2
);

/* ------------------------------------------------------------------ */
/* Jednorazowe wypełnienie danymi z Facebooka (22.11.2026)            */
/* ------------------------------------------------------------------ */

add_action(
	'wp_ajax_krs_seed',
	function () {
		check_ajax_referer( 'krs_sideload' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Brak uprawnień', 403 );
		}
		if ( get_posts( array( 'post_type' => 'krs_event', 'posts_per_page' => 1, 'post_status' => 'any', 'fields' => 'ids' ) ) ) {
			wp_send_json_error( 'Wydarzenia już istnieją' );
		}
		$tracks = array(
			'janki'    => array( 'Pole Position Janki', 'Janki k. Warszawy', '5114', 'pole-position.pl', 'https://pole-position.pl/tor-janki/gokarty-tor-kartingowy/', 1 ),
			'bemowo'   => array( 'Pole Position Bemowo', 'Warszawa', '5105', 'pole-position.pl', 'https://pole-position.pl/tor-bemowo/gokarty/', 2 ),
			'a1'       => array( 'A1Karting', 'Warszawa · Tor 1', '5106', 'a1karting.pl', 'https://a1karting.pl/tory/', 3 ),
			'narodowy' => array( 'Kartingowy PGE Narodowy', 'Warszawa', '5107', 'kartingowynarodowy.pl', 'https://kartingowynarodowy.pl/galeria/', 4 ),
			's8'       => array( 'S8 Race', 'Nadarzyn · gokarty elektryczne', '5115', 's8race.pl', 'https://s8race.pl/tor/', 5 ),
			'rcw'      => array( 'Racing Center Warsaw', 'Nadarzyn · układ RCW18', '5109', 'rcw.pl', 'https://rcw.pl/specyfikacja-toru/124', 6 ),
			'slomczyn' => array( 'Autodrom Słomczyn', 'Słomczyn 66, Grójec', '5110', 'autodromslomczyn.pl', 'https://autodromslomczyn.pl/plan-obiektu/', 7 ),
		);
		$tid    = array();
		foreach ( $tracks as $k => $t ) {
			$tid[ $k ] = wp_insert_post( array( 'post_type' => 'krs_tor', 'post_status' => 'publish', 'post_title' => $t[0], 'menu_order' => $t[5] ) );
			update_post_meta( $tid[ $k ], '_krs_city', $t[1] );
			update_post_meta( $tid[ $k ], '_krs_images', $t[2] );
			update_post_meta( $tid[ $k ], '_krs_src_label', $t[3] );
			update_post_meta( $tid[ $k ], '_krs_src_url', $t[4] );
		}
		$ev = array(
			array( 'KartRace Series CUP — Runda 1', '2026-01-31 00:00', 'runda', 1, '895872519662751', 'bemowo' ),
			array( 'KartRace Series CUP — Runda 2', '2026-02-22 00:00', 'runda', 2, '1430266265127152', 'janki' ),
			array( 'KartRace Series CUP — Runda 3', '2026-03-27 00:00', 'runda', 3, '1611384646778448', 'a1' ),
			array( 'KartRace Series CUP — Runda 4', '2026-04-12 00:00', 'runda', 4, '1638388127480532', 'janki' ),
			array( 'KartRace Series CUP — Runda 5', '2026-05-15 00:00', 'runda', 5, '35466000226348143', 'bemowo' ),
			array( 'KartRace Series CUP — Runda 6', '2026-06-19 00:00', 'runda', 6, '1749534496493130', 'narodowy' ),
			array( 'KartRace Series CUP — Runda 7', '2026-07-24 00:00', 'runda', 7, '2461155701054876', 'janki' ),
			array( 'KartRace Series CUP — Runda 8', '2026-08-21 00:00', 'runda', 8, '1058152537199990', 'a1' ),
			array( 'Endurance 3h', '2026-09-20 00:00', 'endurance', 0, '1489299396549163', 'janki' ),
			array( 'KartRace Series CUP — Runda 9', '2026-09-27 00:00', 'runda', 9, '960855573043034', 'bemowo' ),
			array( 'KartRace Series CUP — Runda 10', '2026-10-30 00:00', 'runda', 10, '1057047410623973', 's8' ),
			array( 'Open Kart Track Day', '2026-11-10 00:00', 'trackday', 0, '1089691253827677', 'slomczyn' ),
			array( 'KartRace Series CUP — Runda 11', '2026-11-30 19:25', 'runda', 11, '1506414828023963', 'rcw' ),
		);
		foreach ( $ev as $e ) {
			$id = wp_insert_post( array( 'post_type' => 'krs_event', 'post_status' => 'publish', 'post_title' => $e[0] ) );
			update_post_meta( $id, '_krs_start', $e[1] );
			update_post_meta( $id, '_krs_type', $e[2] );
			update_post_meta( $id, '_krs_round', $e[3] );
			update_post_meta( $id, '_krs_fb', 'https://www.facebook.com/events/' . $e[4] . '/' );
			update_post_meta( $id, '_krs_track', $tid[ $e[5] ] );
		}
		wp_send_json_success( array( 'tracks' => count( $tid ), 'events' => count( $ev ) ) );
	}
);
