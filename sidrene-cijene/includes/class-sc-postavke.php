<?php
/**
 * Postavke plugina.
 *
 * @package SidreneCijene
 */

defined( 'ABSPATH' ) || exit;

class SC_Postavke {

	const OPCIJA = 'sc_postavke';

	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'registriraj' ) );
	}

	public static function zadane(): array {
		return array(
			'tvrtka'            => get_bloginfo( 'name' ),
			'oib'               => '',
			'oblik_objekta'     => 'WEBSHOP',
			'adresa'            => '',
			'oznaka_objekta'    => '1',
			'broj_pohrane'      => '1',
			'referentni_datum'  => SC_DATUM_OPCI,
			'vrijeme_osvjezenja'=> '07:30',
			'dana_arhive'       => 30,
			'prikazi_na_shopu'  => 'da',
			'tekst_oznake'      => __( 'Sidrena cijena', 'sidrene-cijene' ),
			'jedinica_mjere'    => 'kom',
			'naziv_akcije'      => __( 'Akcija', 'sidrene-cijene' ),
			'brisi_pri_uklanjanju' => 'ne',
		);
	}

	public static function sve(): array {
		$spremljeno = get_option( self::OPCIJA, array() );
		return wp_parse_args( is_array( $spremljeno ) ? $spremljeno : array(), self::zadane() );
	}

	/**
	 * Dohvat jedne postavke.
	 *
	 * @param string $kljuc Ključ.
	 * @return mixed
	 */
	public static function get( string $kljuc ) {
		$sve = self::sve();
		return $sve[ $kljuc ] ?? null;
	}

	public static function registriraj(): void {
		register_setting(
			'sc_grupa_postavki',
			self::OPCIJA,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'ocisti' ),
				'default'           => self::zadane(),
			)
		);
	}

	/**
	 * Čišćenje unosa.
	 *
	 * @param mixed $ulaz Neprovjereni unos.
	 * @return array
	 */
	public static function ocisti( $ulaz ): array {
		$ulaz  = is_array( $ulaz ) ? $ulaz : array();
		$izlaz = self::sve();

		$tekstualna = array( 'tvrtka', 'oib', 'oblik_objekta', 'adresa', 'oznaka_objekta', 'broj_pohrane', 'tekst_oznake', 'jedinica_mjere', 'naziv_akcije' );
		foreach ( $tekstualna as $k ) {
			if ( isset( $ulaz[ $k ] ) ) {
				$izlaz[ $k ] = sanitize_text_field( wp_unslash( $ulaz[ $k ] ) );
			}
		}

		if ( isset( $ulaz['referentni_datum'] ) ) {
			$d = sanitize_text_field( wp_unslash( $ulaz['referentni_datum'] ) );
			$izlaz['referentni_datum'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : SC_DATUM_OPCI;
		}

		if ( isset( $ulaz['vrijeme_osvjezenja'] ) ) {
			$v = sanitize_text_field( wp_unslash( $ulaz['vrijeme_osvjezenja'] ) );
			$izlaz['vrijeme_osvjezenja'] = preg_match( '/^\d{2}:\d{2}$/', $v ) ? $v : '07:30';
		}

		if ( isset( $ulaz['dana_arhive'] ) ) {
			$izlaz['dana_arhive'] = max( 1, min( 365, (int) $ulaz['dana_arhive'] ) );
		}

		foreach ( array( 'prikazi_na_shopu', 'brisi_pri_uklanjanju' ) as $k ) {
			if ( isset( $ulaz[ $k ] ) ) {
				$izlaz[ $k ] = ( 'da' === $ulaz[ $k ] ) ? 'da' : 'ne';
			}
		}

		// Promjena vremena osvježenja mijenja i raspored.
		SC_Cjenik::odzakazi();
		SC_Cjenik::zakazi( $izlaz['vrijeme_osvjezenja'] );

		return $izlaz;
	}

	/**
	 * Referentni datum u formatu za prikaz, npr. 10.09.2026.
	 */
	public static function datum_za_prikaz(): string {
		$d = self::get( 'referentni_datum' );
		$t = strtotime( $d );
		return $t ? gmdate( 'd.m.Y.', $t ) : $d;
	}

	/**
	 * Adresa pretvorena u oblik prikladan za naziv datoteke.
	 */
	public static function adresa_za_datoteku(): string {
		$a = self::get( 'adresa' );
		if ( '' === trim( (string) $a ) ) {
			$a = wp_parse_url( home_url(), PHP_URL_HOST );
		}
		$a = remove_accents( (string) $a );
		$a = preg_replace( '/[^A-Za-z0-9]+/', '-', $a );
		return strtoupper( trim( (string) $a, '-' ) );
	}
}
