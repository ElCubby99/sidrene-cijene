<?php
/**
 * Javna stranica s poveznicama na objavljene cjenike.
 *
 * Odluka traži da cjenici budu javno dostupni i dohvatljivi automatiziranim
 * alatima. Zato plugin pri aktivaciji sam stvara stranicu sa shortcodeom
 * [sidrene_cijene_popis] i pamti njezin ID, da je korisnik ne mora raditi ručno.
 *
 * Stranica se stvara samo ako ne postoji. Deaktivacija je ne dira.
 *
 * @package SidreneCijene
 */

defined( 'ABSPATH' ) || exit;

class SC_Stranica {

	const OPCIJA = 'sc_stranica_id';
	const SLUG   = 'cjenik-podaci';

	public static function init(): void {
		add_action( 'admin_post_sc_napravi_stranicu', array( __CLASS__, 'rucno_stvaranje' ) );
	}

	/**
	 * ID postojeće javne stranice ili 0.
	 */
	public static function id(): int {
		$id = (int) get_option( self::OPCIJA, 0 );

		if ( $id > 0 && 'publish' === get_post_status( $id ) ) {
			return $id;
		}

		// Stranica je možda ručno napravljena prije instalacije — posvoji je.
		$postojeca = get_page_by_path( self::SLUG );
		if ( $postojeca && 'publish' === $postojeca->post_status ) {
			update_option( self::OPCIJA, $postojeca->ID );
			return (int) $postojeca->ID;
		}

		return 0;
	}

	/** Adresa javne stranice ili prazan string. */
	public static function url(): string {
		$id = self::id();
		return $id ? (string) get_permalink( $id ) : '';
	}

	/**
	 * Stvara stranicu ako je nema. Vraća ID.
	 */
	public static function osiguraj(): int {
		$postojeci = self::id();
		if ( $postojeci ) {
			return $postojeci;
		}

		$sadrzaj = "<!-- wp:paragraph --><p>"
			. esc_html__( 'Na ovoj stranici objavljujemo cjenike proizvoda i usluga u strojno čitljivom obliku (CSV i XML), u skladu s Odlukom o objavi cjenika proizvoda i usluga kao mjere izravne kontrole cijena (NN 101/2026).', 'sidrene-cijene' )
			. "</p><!-- /wp:paragraph -->\n"
			. "<!-- wp:shortcode -->[sidrene_cijene_popis]<!-- /wp:shortcode -->";

		$id = wp_insert_post(
			array(
				'post_title'   => __( 'Cjenik — strojno čitljivi podaci', 'sidrene-cijene' ),
				'post_name'    => self::SLUG,
				'post_content' => $sadrzaj,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);

		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}

		update_option( self::OPCIJA, (int) $id );
		return (int) $id;
	}

	/** Ručno stvaranje iz admin sučelja. */
	public static function rucno_stvaranje(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Nemate ovlasti.', 'sidrene-cijene' ) );
		}
		check_admin_referer( 'sc_napravi_stranicu' );

		$id = self::osiguraj();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'sidrene-cijene',
					'tab'       => 'cjenici',
					'sc_poruka' => rawurlencode(
						$id
							? __( 'Javna stranica s cjenicima je spremna.', 'sidrene-cijene' )
							: __( 'Stranicu nije bilo moguće stvoriti.', 'sidrene-cijene' )
					),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/** Briše stranicu — koristi se samo pri deinstalaciji s uključenim brisanjem. */
	public static function obrisi(): void {
		$id = (int) get_option( self::OPCIJA, 0 );
		if ( $id ) {
			wp_delete_post( $id, true );
		}
		delete_option( self::OPCIJA );
	}
}
