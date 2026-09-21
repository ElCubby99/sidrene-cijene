<?php
/**
 * Izračun sidrene cijene.
 *
 * Redoslijed izvora:
 *   1. Zaključana vrijednost u meta polju _sidrena_cijena  (mjerodavna)
 *   2. Izračun iz redovne cijene                            (dok nije zaključano)
 *
 * Odluka traži cijenu koja je vrijedila na referentni datum BEZ posebnih
 * oblika prodaje — zato se uvijek uzima REDOVNA, nikad akcijska cijena.
 *
 * @package SidreneCijene
 */

defined( 'ABSPATH' ) || exit;

class SC_Cijena {

	const META = '_sidrena_cijena';

	/**
	 * Sidrena cijena proizvoda.
	 *
	 * @param WC_Product|int $proizvod Proizvod ili ID.
	 * @return float|array{0:float,1:float}|null Iznos, raspon [min,max] ili null.
	 */
	public static function dohvati( $proizvod ) {
		if ( is_numeric( $proizvod ) ) {
			$proizvod = wc_get_product( $proizvod );
		}
		if ( ! $proizvod instanceof WC_Product ) {
			return null;
		}

		// 1. Zaključana vrijednost.
		$meta = $proizvod->get_meta( self::META );
		if ( '' !== $meta && null !== $meta && (float) $meta > 0 ) {
			return (float) $meta;
		}

		// 2. Izračun.
		return self::izracunaj( $proizvod );
	}

	/**
	 * Izračun iz redovnih cijena, bez gledanja u zaključanu vrijednost.
	 *
	 * @param WC_Product $proizvod Proizvod.
	 * @return float|array{0:float,1:float}|null
	 */
	public static function izracunaj( WC_Product $proizvod ) {

		if ( $proizvod->is_type( 'variable' ) && method_exists( $proizvod, 'get_variation_regular_price' ) ) {
			return self::raspon(
				$proizvod->get_variation_regular_price( 'min', true ),
				$proizvod->get_variation_regular_price( 'max', true )
			);
		}

		if ( $proizvod->is_type( 'bundle' ) && method_exists( $proizvod, 'get_bundle_regular_price' ) ) {
			return self::raspon(
				$proizvod->get_bundle_regular_price( 'min', true ),
				$proizvod->get_bundle_regular_price( 'max', true )
			);
		}

		if ( $proizvod->is_type( 'grouped' ) ) {
			$cijene = array();
			foreach ( $proizvod->get_children() as $dijete_id ) {
				$dijete = wc_get_product( $dijete_id );
				if ( $dijete ) {
					$r = (float) $dijete->get_regular_price();
					if ( $r > 0 ) {
						$cijene[] = $r;
					}
				}
			}
			return $cijene ? self::raspon( min( $cijene ), max( $cijene ) ) : null;
		}

		$redovna = $proizvod->get_regular_price();
		if ( '' === $redovna || null === $redovna ) {
			return null;
		}
		$redovna = (float) $redovna;

		return $redovna > 0 ? $redovna : null;
	}

	/**
	 * Vrati jedan iznos ako su min i max jednaki, inače raspon. Nula nikad.
	 *
	 * @param mixed $min Najmanja cijena.
	 * @param mixed $max Najveća cijena.
	 * @return float|array{0:float,1:float}|null
	 */
	private static function raspon( $min, $max ) {
		if ( '' === $min || null === $min ) {
			return null;
		}
		$min = (float) $min;
		$max = ( '' === $max || null === $max ) ? $min : (float) $max;

		if ( $min <= 0 ) {
			return null;
		}
		return ( abs( $min - $max ) < 0.005 ) ? $min : array( $min, $max );
	}

	/**
	 * Je li proizvodu sidrena cijena zaključana u bazi.
	 *
	 * @param WC_Product $proizvod Proizvod.
	 */
	public static function zakljucana( WC_Product $proizvod ): bool {
		$meta = $proizvod->get_meta( self::META );
		return '' !== $meta && null !== $meta && (float) $meta > 0;
	}

	/**
	 * Aktualna maloprodajna cijena (ona koja se stvarno naplaćuje).
	 *
	 * @param WC_Product $proizvod Proizvod.
	 * @return float|null
	 */
	public static function aktualna( WC_Product $proizvod ) {
		$c = $proizvod->get_price();
		if ( '' === $c || null === $c ) {
			return null;
		}
		return (float) $c;
	}

	/**
	 * Je li na proizvod trenutno primijenjen poseban oblik prodaje.
	 *
	 * @param WC_Product $proizvod Proizvod.
	 */
	public static function na_akciji( WC_Product $proizvod ): bool {
		return (bool) $proizvod->is_on_sale();
	}

	/**
	 * Formatirani iznos za ispis.
	 *
	 * @param float|array|null $vrijednost Iznos ili raspon.
	 */
	public static function formatiraj( $vrijednost ): string {
		if ( null === $vrijednost ) {
			return '';
		}
		if ( is_array( $vrijednost ) ) {
			return wc_price( $vrijednost[0] ) . ' &ndash; ' . wc_price( $vrijednost[1] );
		}
		return wc_price( $vrijednost );
	}

	/**
	 * Iznos kao čisti broj za cjenik (bez HTML-a, zarez kao decimalni znak).
	 *
	 * @param float|array|null $vrijednost Iznos ili raspon.
	 */
	public static function za_cjenik( $vrijednost ): string {
		if ( null === $vrijednost ) {
			return '';
		}
		if ( is_array( $vrijednost ) ) {
			return number_format( $vrijednost[0], 2, ',', '' ) . ' - ' . number_format( $vrijednost[1], 2, ',', '' );
		}
		return number_format( (float) $vrijednost, 2, ',', '' );
	}
}
