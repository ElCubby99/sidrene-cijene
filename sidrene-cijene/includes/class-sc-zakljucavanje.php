<?php
/**
 * Zaključavanje sidrenih cijena.
 *
 * Bez zaključavanja sidrena cijena se izračunava iz redovne, pa se pomakne
 * čim se redovna cijena promijeni — što je upravo ono što Odluka zabranjuje.
 * Zaključavanje upisuje iznos u meta polje i time ga zamrzava.
 *
 * Obrada ide u serijama preko AJAX-a da ne padne na velikim katalozima.
 *
 * @package SidreneCijene
 */

defined( 'ABSPATH' ) || exit;

class SC_Zakljucavanje {

	const SERIJA = 20;

	public static function init(): void {
		add_action( 'wp_ajax_sc_zakljucaj_seriju', array( __CLASS__, 'ajax_serija' ) );
		add_action( 'wp_ajax_sc_otkljucaj_sve', array( __CLASS__, 'ajax_otkljucaj' ) );
		add_action( 'wp_ajax_sc_status', array( __CLASS__, 'ajax_status' ) );
	}

	/**
	 * Svi ID-evi proizvoda i varijacija u katalogu.
	 *
	 * @return int[]
	 */
	public static function svi_idevi(): array {
		global $wpdb;
		$idevi = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_type IN ('product','product_variation')
			   AND post_status IN ('publish','private','draft','pending')
			 ORDER BY ID ASC"
		);
		return array_map( 'intval', (array) $idevi );
	}

	/**
	 * ID-evi kojima sidrena cijena još nije zaključana.
	 *
	 * @return int[]
	 */
	public static function nezakljucani(): array {
		global $wpdb;
		$meta  = SC_Cijena::META;
		// Preskočene (npr. nadređeni varijabilni proizvodi koji daju raspon)
		// izlaze iz reda, inače bi obrada u serijama išla u nedogled.
		$idevi = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} sp ON sp.post_id = p.ID AND sp.meta_key = '_sc_preskoceno'
				 WHERE p.post_type IN ('product','product_variation')
				   AND p.post_status IN ('publish','private','draft','pending')
				   AND ( m.meta_value IS NULL OR m.meta_value = '' OR m.meta_value = '0' )
				   AND sp.meta_value IS NULL
				 ORDER BY p.ID ASC",
				$meta
			)
		);
		return array_map( 'intval', (array) $idevi );
	}

	/**
	 * Zaključa jednu seriju. Vraća broj obrađenih i preostalih.
	 *
	 * @param int $koliko Veličina serije.
	 * @return array{obradeno:int,preostalo:int,preskoceno:int}
	 */
	public static function zakljucaj_seriju( int $koliko = self::SERIJA ): array {
		$preostali = self::nezakljucani();
		$dio       = array_slice( $preostali, 0, $koliko );

		$obradeno   = 0;
		$preskoceno = 0;

		foreach ( $dio as $id ) {
			$proizvod = wc_get_product( $id );
			if ( ! $proizvod ) {
				$preskoceno++;
				continue;
			}
			$vrijednost = SC_Cijena::izracunaj( $proizvod );

			// Raspon se ne može zamrznuti u jedan broj — nadređeni varijabilni
			// proizvod preskačemo, jer se sidrena čita s pojedine varijacije.
			if ( null === $vrijednost || is_array( $vrijednost ) ) {
				$preskoceno++;
				update_post_meta( $id, '_sc_preskoceno', 1 );
				continue;
			}

			update_post_meta( $id, SC_Cijena::META, wc_format_decimal( $vrijednost ) );
			delete_post_meta( $id, '_sc_preskoceno' );
			$obradeno++;
		}

		return array(
			'obradeno'   => $obradeno,
			'preskoceno' => $preskoceno,
			'preostalo'  => max( 0, count( $preostali ) - count( $dio ) ),
		);
	}

	/** AJAX: obradi seriju. */
	public static function ajax_serija(): void {
		check_ajax_referer( 'sc_zakljucavanje', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'poruka' => __( 'Nemate ovlasti.', 'sidrene-cijene' ) ), 403 );
		}
		wp_send_json_success( self::zakljucaj_seriju() );
	}

	/** AJAX: ukloni sve zaključane vrijednosti (povratak unatrag). */
	public static function ajax_otkljucaj(): void {
		check_ajax_referer( 'sc_zakljucavanje', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'poruka' => __( 'Nemate ovlasti.', 'sidrene-cijene' ) ), 403 );
		}
		global $wpdb;
		$obrisano = $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => SC_Cijena::META ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_sc_preskoceno' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		wp_cache_flush();
		wp_send_json_success( array( 'obrisano' => (int) $obrisano ) );
	}

	/** AJAX: koliko je zaključano, koliko nije. */
	public static function ajax_status(): void {
		check_ajax_referer( 'sc_zakljucavanje', 'nonce' );
		wp_send_json_success( self::status() );
	}

	/**
	 * Brojke za prikaz na nadzornoj stranici.
	 *
	 * @return array{ukupno:int,zakljucano:int,nezakljucano:int}
	 */
	public static function status(): array {
		$ukupno       = count( self::svi_idevi() );
		$nezakljucano = count( self::nezakljucani() );
		return array(
			'ukupno'       => $ukupno,
			'zakljucano'   => max( 0, $ukupno - $nezakljucano ),
			'nezakljucano' => $nezakljucano,
		);
	}
}
