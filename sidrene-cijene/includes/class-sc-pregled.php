<?php
/**
 * Pregled proizvoda: aktualna cijena naspram sidrene.
 *
 * @package SidreneCijene
 */

defined( 'ABSPATH' ) || exit;

class SC_Pregled {

	const PO_STRANICI = 50;

	/**
	 * Dohvat retka za prikaz.
	 *
	 * @param int $stranica Broj stranice.
	 * @param string $filtar sve|razlika|nezakljucano|bez_barkoda.
	 * @return array{redci:array,ukupno:int,stranica:int,stranica_ukupno:int}
	 */
	public static function dohvati( int $stranica = 1, string $filtar = 'sve' ): array {
		$idevi = wc_get_products(
			array(
				'status' => array( 'publish' ),
				'limit'  => -1,
				'return' => 'ids',
				'type'   => array_keys( wc_get_product_types() ),
			)
		);

		$redci = array();
		foreach ( $idevi as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p ) {
				continue;
			}

			$aktualna = SC_Cijena::aktualna( $p );
			$sidrena  = SC_Cijena::dohvati( $p );
			$zaklj    = SC_Cijena::zakljucana( $p );

			$barkod = method_exists( $p, 'get_global_unique_id' ) ? (string) $p->get_global_unique_id() : '';

			$razlika = false;
			if ( null !== $aktualna && null !== $sidrena && ! is_array( $sidrena ) ) {
				$razlika = abs( $aktualna - $sidrena ) > 0.005;
			}

			$redak = array(
				'id'        => $id,
				'naziv'     => $p->get_name(),
				'sku'       => $p->get_sku(),
				'tip'       => $p->get_type(),
				'aktualna'  => $aktualna,
				'sidrena'   => $sidrena,
				'zakljucana'=> $zaklj,
				'barkod'    => $barkod,
				'razlika'   => $razlika,
				'na_akciji' => SC_Cijena::na_akciji( $p ),
				'url'       => get_edit_post_link( $id, '' ),
			);

			switch ( $filtar ) {
				case 'razlika':
					if ( ! $razlika ) {
						continue 2;
					}
					break;
				case 'nezakljucano':
					if ( $zaklj ) {
						continue 2;
					}
					break;
				case 'bez_barkoda':
					if ( '' !== $barkod ) {
						continue 2;
					}
					break;
				case 'bez_sidrene':
					if ( null !== $sidrena ) {
						continue 2;
					}
					break;
			}

			$redci[] = $redak;
		}

		$ukupno   = count( $redci );
		$stranica = max( 1, $stranica );
		$odmak    = ( $stranica - 1 ) * self::PO_STRANICI;

		return array(
			'redci'           => array_slice( $redci, $odmak, self::PO_STRANICI ),
			'ukupno'          => $ukupno,
			'stranica'        => $stranica,
			'stranica_ukupno' => max( 1, (int) ceil( $ukupno / self::PO_STRANICI ) ),
		);
	}

	/**
	 * Kratke brojke za nadzornu ploču.
	 *
	 * @return array<string,int>
	 */
	public static function brojke(): array {
		$sve          = self::dohvati( 1, 'sve' );
		$bez_sidrene  = self::dohvati( 1, 'bez_sidrene' );
		$bez_barkoda  = self::dohvati( 1, 'bez_barkoda' );
		$razlika      = self::dohvati( 1, 'razlika' );

		return array(
			'ukupno'      => $sve['ukupno'],
			'bez_sidrene' => $bez_sidrene['ukupno'],
			'bez_barkoda' => $bez_barkoda['ukupno'],
			'razlika'     => $razlika['ukupno'],
		);
	}
}
