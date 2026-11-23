<?php
/**
 * KRS Wyniki — SEO: dane strukturalne (organizacja, wydarzenia, FAQ), FAQ na stronę główną,
 * ustawienia Rank Math dla stron.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Telefon ligi do danych strukturalnych i FAQ.
 * Ustaw w wp-config.php, np.: define( 'KRS_PHONE', '+48 000 000 000' );
 * Pusta wartość = telefon nie jest wyświetlany.
 */
if ( ! defined( 'KRS_PHONE' ) ) {
	define( 'KRS_PHONE', '' );
}

function krs_seo_org() {
	$logo = wp_get_attachment_url( 5001 );
	$org  = array(
		'@type'       => 'SportsOrganization',
		'@id'         => home_url( '/#organizacja' ),
		'name'        => 'KartRace Series',
		'alternateName' => 'Amatorska liga kartingowa KartRace Series',
		'description' => 'Amatorska liga kartingowa dla każdego — wyścigi kartingowe na torach w Warszawie i okolicach.',
		'url'         => home_url( '/' ),
		'logo'        => $logo,
		'image'       => $logo,
		'sport'       => 'Karting',
		'telephone'   => KRS_PHONE,
		'areaServed'  => array( '@type' => 'City', 'name' => 'Warszawa' ),
		'sameAs'      => array(
			'https://www.facebook.com/profile.php?id=61584256544468',
			'https://www.instagram.com/kartrace_series/',
			'https://www.tiktok.com/@kartraceseries',
			'https://www.youtube.com/@KartRaceSeries',
		),
	);
	if ( '' === KRS_PHONE ) {
		unset( $org['telephone'] );
	}
	return $org;
}

function krs_seo_events() {
	if ( ! function_exists( 'krs_e_all' ) ) {
		return array();
	}
	$out = array();
	foreach ( krs_e_all() as $e ) {
		if ( ! $e['ts'] ) {
			continue;
		}
		$t     = krs_t_get( $e['track'] );
		$name  = 'runda' === $e['type'] ? 'KartRace Series CUP — Runda ' . $e['round'] : $e['title'];
		$ev    = array(
			'@type'               => 'SportsEvent',
			'name'                => $name . ( $t ? ' — ' . $t['title'] : '' ),
			'sport'               => 'Karting',
			'startDate'           => wp_date( 'c', $e['ts'] ),
			'eventStatus'         => 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
			'url'                 => home_url( '/kalendarz/#wydarzenie-' . $e['id'] ),
			'organizer'           => array( '@id' => home_url( '/#organizacja' ) ),
			'description'         => 'Wyścig amatorskiej ligi kartingowej KartRace Series' . ( $t ? ' na torze ' . $t['title'] : '' ) . '.',
		);
		if ( $t ) {
			$ev['location'] = array(
				'@type'   => 'Place',
				'name'    => $t['title'],
				'address' => array( '@type' => 'PostalAddress', 'addressLocality' => trim( explode( '·', $t['city'] )[0] ), 'addressCountry' => 'PL' ),
			);
		}
		$out[] = $ev;
	}
	return $out;
}

function krs_seo_faq() {
	$tracks = array();
	if ( function_exists( 'krs_t_get' ) ) {
		foreach ( get_posts( array( 'post_type' => 'krs_tor', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'menu_order', 'order' => 'ASC' ) ) as $id ) {
			$tracks[] = get_the_title( $id );
		}
	}
	return array(
		array( 'Czym jest KartRace Series?', 'KartRace Series to amatorska liga kartingowa dla każdego. Organizujemy wyścigi kartingowe na torach w Warszawie i okolicach, a wyniki z każdej rundy sumują się w klasyfikacji generalnej sezonu.' ),
		array( 'Kto może wystartować w lidze kartingowej?', 'Startować może każdy, kto ma co najmniej 150 cm wzrostu, wypełni zgłoszenie, zaakceptuje regulamin i opłaci wpisowe. Nie musisz jechać całego sezonu — duża część osób jeździ okazjonalnie.' ),
		array( 'Na jakich torach kartingowych się ścigacie?', $tracks ? 'W sezonie 2026 ścigaliśmy się na torach: ' . implode( ', ', $tracks ) . '.' : 'Na torach kartingowych w Warszawie i okolicach.' ),
		array( 'Jak zapisać się na zawody?', 'Napisz do nas na Facebooku (Messenger)' . ( KRS_PHONE ? ' albo zadzwoń: ' . KRS_PHONE : '' ) . '. Każda runda ma też swoje wydarzenie na Facebooku, gdzie publikujemy szczegóły.' ),
		array( 'Gdzie sprawdzę wyniki i klasyfikację?', 'Na stronie Klasyfikacja — znajdziesz tam klasyfikację generalną oraz wyniki każdej rundy z miejscami, punktami i najszybszymi okrążeniami. Wyniki aktualizują się automatycznie.' ),
	);
}

add_action(
	'wp_head',
	function () {
		$graph = array( krs_seo_org() );
		if ( is_front_page() || is_page( array( 'kalendarz', 'tory' ) ) ) {
			$graph = array_merge( $graph, krs_seo_events() );
		}
		if ( is_front_page() ) {
			$faq = array(
				'@type'      => 'FAQPage',
				'mainEntity' => array(),
			);
			foreach ( krs_seo_faq() as $q ) {
				$faq['mainEntity'][] = array(
					'@type'          => 'Question',
					'name'           => $q[0],
					'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $q[1] ),
				);
			}
			$graph[] = $faq;
		}
		echo "\n" . '<script type="application/ld+json" class="krs-schema">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	},
	30
);

/** Sekcja FAQ na stronę główną: [krs_faq] */
add_shortcode(
	'krs_faq',
	function () {
		$h = '<div class="krs-faq">';
		foreach ( krs_seo_faq() as $q ) {
			$h .= '<details class="krs-faq-item"><summary><h3>' . esc_html( $q[0] ) . '</h3></summary><p>' . esc_html( $q[1] ) . '</p></details>';
		}
		return $h . '</div>';
	}
);

/** Ustawienia SEO stron (tylko administrator): tytuły/opisy Rank Math, grafika OG, ikona. */
add_action(
	'wp_ajax_krs_seo',
	function () {
		check_ajax_referer( 'krs_sideload' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Brak uprawnień', 403 );
		}
		$raw  = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '';
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			wp_send_json_error( 'Złe dane' );
		}
		$done = array();
		if ( ! empty( $data['pages'] ) ) {
			foreach ( $data['pages'] as $id => $m ) {
				$id = absint( $id );
				if ( ! $id || 'page' !== get_post_type( $id ) ) {
					continue;
				}
				foreach ( array( 'title' => 'rank_math_title', 'description' => 'rank_math_description', 'focus' => 'rank_math_focus_keyword' ) as $k => $meta ) {
					if ( isset( $m[ $k ] ) ) {
						update_post_meta( $id, $meta, sanitize_text_field( $m[ $k ] ) );
					}
				}
				$done[] = $id;
			}
		}
		if ( ! empty( $data['og'] ) ) {
			$og = absint( $data['og'] );
			$o  = get_option( 'rank-math-options-titles', array() );
			if ( ! is_array( $o ) ) {
				$o = array();
			}
			$o['open_graph_image']      = wp_get_attachment_url( $og );
			$o['open_graph_image_id']   = $og;
			$o['knowledgegraph_type']   = 'company';
			$o['knowledgegraph_name']   = 'KartRace Series';
			$o['website_name']          = 'KartRace Series';
			$o['knowledgegraph_logo']   = wp_get_attachment_url( 5001 );
			$o['knowledgegraph_logo_id'] = 5001;
			$o['social_url_facebook']   = 'https://www.facebook.com/profile.php?id=61584256544468';
			$o['social_additional_profiles'] = "https://www.instagram.com/kartrace_series/\nhttps://www.tiktok.com/@kartraceseries\nhttps://www.youtube.com/@KartRaceSeries";
			update_option( 'rank-math-options-titles', $o );
			$done[] = 'og';
		}
		if ( ! empty( $data['icon'] ) ) {
			update_option( 'site_icon', absint( $data['icon'] ) );
			$done[] = 'icon';
		}
		if ( isset( $data['tagline'] ) ) {
			update_option( 'blogdescription', sanitize_text_field( $data['tagline'] ) );
			$done[] = 'tagline';
		}
		if ( ! empty( $data['privacy'] ) ) {
			update_option( 'wp_page_for_privacy_policy', absint( $data['privacy'] ) );
			$done[] = 'privacy';
		}
		wp_send_json_success( $done );
	}
);
