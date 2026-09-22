<?php
/**
 * Prikaz sidrene cijene u Elementor „Price Table" widgetima.
 *
 * Mnoge stranice s cjenikom usluga rađene su Elementorovim price-table
 * widgetom. Ovdje se iznos očita iz renderiranog widgeta, potraži među
 * unesenim uslugama i ispod cijene doda redak sa sidrenom cijenom.
 *
 * Ako iznos ne odgovara nijednoj unesenoj usluzi, ne ispisuje se ništa —
 * bolje prazno nego netočan podatak.
 *
 * @package SidreneCijene
 */

defined( 'ABSPATH' ) || exit;

class SC_Elementor {

	public static function init(): void {
		if ( 'da' !== SC_Postavke::get( 'elementor_cijene' ) ) {
			return;
		}
		add_filter( 'elementor/widget/render_content', array( __CLASS__, 'dopuni_widget' ), 20, 2 );
		add_action( 'wp_head', array( __CLASS__, 'stil' ) );
	}

	public static function stil(): void {
		echo '<style>.sc-sidrena-usluga{display:block;margin:.35em 0 0;font-size:14px;line-height:1.45;color:#6b6b6b;text-align:center}.sc-sidrena-usluga strong{color:#3a3a3a}</style>';
	}

	/**
	 * Dopuna renderiranog widgeta.
	 *
	 * @param string $html   Renderirani HTML.
	 * @param object $widget Widget.
	 * @return string
	 */
	public static function dopuni_widget( $html, $widget ) {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) ) {
			return $html;
		}
		if ( 'price-table' !== $widget->get_name() ) {
			return $html;
		}
		if ( false === strpos( $html, 'elementor-price-table__price' ) ) {
			return $html;
		}

		return preg_replace_callback(
			'#<div class="elementor-price-table__price">(.*?)</div>#s',
			array( __CLASS__, 'obradi_cijenu' ),
			$html,
			1
		);
	}

	/**
	 * Za jedan blok s cijenom vrati original plus redak sa sidrenom cijenom.
	 *
	 * @param array $podudaranje Rezultat regexa.
	 * @return string
	 */
	private static function obradi_cijenu( array $podudaranje ): string {
		$iznos = self::iznos_iz_htmla( $podudaranje[1] );
		if ( null === $iznos ) {
			return $podudaranje[0];
		}

		$sidrena = self::sidrena_za_iznos( $iznos );
		if ( null === $sidrena ) {
			return $podudaranje[0];
		}

		$redak = sprintf(
			'<span class="sc-sidrena-usluga">%1$s (%2$s):<br><strong>%3$s</strong></span>',
			esc_html( SC_Postavke::get( 'tekst_oznake' ) ),
			esc_html( SC_Postavke::datum_za_prikaz() ),
			wp_kses_post( wc_price( $sidrena ) )
		);

		return $podudaranje[0] . $redak;
	}

	/**
	 * Izvuče brojčani iznos iz HTML-a Elementorovog bloka s cijenom.
	 *
	 * @param string $html Unutrašnjost bloka.
	 * @return float|null
	 */
	private static function iznos_iz_htmla( string $html ): ?float {
		$cijeli = '';
		$dio    = '';

		if ( preg_match( '#elementor-price-table__integer-part[^>]*>\s*([\d.,\s]+?)\s*<#s', $html, $m ) ) {
			$cijeli = preg_replace( '/[^\d]/', '', $m[1] );
		}
		if ( preg_match( '#elementor-price-table__fractional-part[^>]*>\s*(\d+)\s*<#s', $html, $m ) ) {
			$dio = $m[1];
		}
		if ( '' === $cijeli ) {
			return null;
		}
		return (float) ( $cijeli . ( '' !== $dio ? '.' . $dio : '' ) );
	}

	/**
	 * Pronađe uslugu s tom aktualnom cijenom i vrati njezinu sidrenu cijenu.
	 *
	 * @param float $iznos Iznos s widgeta.
	 * @return float|null
	 */
	private static function sidrena_za_iznos( float $iznos ): ?float {
		static $mapa = null;

		if ( null === $mapa ) {
			$mapa   = array();
			$zapisi = get_posts(
				array(
					'post_type'   => SC_Usluge::TIP,
					'post_status' => 'publish',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			);
			foreach ( $zapisi as $id ) {
				$cijena  = (float) get_post_meta( $id, '_sc_cijena', true );
				$sidrena = (float) get_post_meta( $id, '_sc_sidrena', true );
				if ( $cijena > 0 && $sidrena > 0 ) {
					$mapa[ (string) round( $cijena, 2 ) ] = $sidrena;
				}
			}
		}

		$kljuc = (string) round( $iznos, 2 );
		return $mapa[ $kljuc ] ?? null;
	}
}
