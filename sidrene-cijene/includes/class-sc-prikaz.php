<?php
/**
 * Prikaz sidrene cijene na trgovini i polje u admin sučelju.
 *
 * @package SidreneCijene
 */

defined( 'ABSPATH' ) || exit;

class SC_Prikaz {

	public static function init(): void {
		if ( 'da' === SC_Postavke::get( 'prikazi_na_shopu' ) ) {
			add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'uz_cijenu' ), 20, 2 );
			add_filter( 'woocommerce_available_variation', array( __CLASS__, 'uz_varijaciju' ), 20, 3 );
		}

		add_shortcode( 'sidrena_cijena', array( __CLASS__, 'shortcode' ) );

		// Polje na proizvodu.
		add_action( 'woocommerce_product_options_pricing', array( __CLASS__, 'polje_proizvod' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'spremi_proizvod' ) );

		// Polje na varijaciji.
		add_action( 'woocommerce_variation_options_pricing', array( __CLASS__, 'polje_varijacija' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'spremi_varijaciju' ), 10, 2 );

		add_action( 'wp_head', array( __CLASS__, 'stil' ) );
	}

	/** Minimalni stil; tema ga može nadjačati. */
	public static function stil(): void {
		echo '<style>.sc-sidrena{display:block;font-size:.82em;line-height:1.5;color:#555;margin-top:.35em}.sc-sidrena .amount{font-weight:600}</style>';
	}

	/**
	 * HTML retka sa sidrenom cijenom.
	 *
	 * @param WC_Product $proizvod Proizvod.
	 */
	public static function html( $proizvod ): string {
		$vrijednost = SC_Cijena::dohvati( $proizvod );
		if ( null === $vrijednost ) {
			return '';
		}
		return sprintf(
			'<span class="sc-sidrena">%1$s (%2$s): <strong>%3$s</strong></span>',
			esc_html( SC_Postavke::get( 'tekst_oznake' ) ),
			esc_html( SC_Postavke::datum_za_prikaz() ),
			SC_Cijena::formatiraj( $vrijednost )
		);
	}

	/**
	 * Dodaje redak ispod cijene u katalogu i na stranici proizvoda.
	 *
	 * @param string     $html     Postojeći HTML cijene.
	 * @param WC_Product $proizvod Proizvod.
	 */
	public static function uz_cijenu( $html, $proizvod ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $html;
		}
		return $html . self::html( $proizvod );
	}

	/**
	 * Dodaje redak u podatke varijacije.
	 *
	 * @param array                $podaci    Podaci varijacije.
	 * @param WC_Product_Variable  $proizvod  Nadređeni proizvod.
	 * @param WC_Product_Variation $varijacija Varijacija.
	 */
	public static function uz_varijaciju( $podaci, $proizvod, $varijacija ) {
		$redak = self::html( $varijacija );
		if ( '' !== $redak ) {
			$podaci['price_html'] = ( $podaci['price_html'] ?? '' ) . $redak;
		}
		return $podaci;
	}

	/**
	 * [sidrena_cijena iznos="240,00"] — za ručno unesene cijene usluga na stranicama.
	 *
	 * @param array $atributi Atributi shortcodea.
	 */
	public static function shortcode( $atributi ): string {
		$a = shortcode_atts(
			array(
				'iznos'  => '',
				'id'     => '',
			),
			$atributi,
			'sidrena_cijena'
		);

		if ( '' !== $a['id'] ) {
			return self::html( wc_get_product( (int) $a['id'] ) );
		}
		if ( '' === $a['iznos'] ) {
			return '';
		}
		return sprintf(
			'<span class="sc-sidrena">%1$s (%2$s): <strong>%3$s&nbsp;%4$s</strong></span>',
			esc_html( SC_Postavke::get( 'tekst_oznake' ) ),
			esc_html( SC_Postavke::datum_za_prikaz() ),
			esc_html( $a['iznos'] ),
			esc_html( get_woocommerce_currency_symbol() )
		);
	}

	/** Polje u karticama cijena na proizvodu. */
	public static function polje_proizvod(): void {
		woocommerce_wp_text_input(
			array(
				'id'          => SC_Cijena::META,
				'label'       => sprintf(
					/* translators: %s: currency symbol */
					__( 'Sidrena cijena (%s)', 'sidrene-cijene' ),
					get_woocommerce_currency_symbol()
				),
				'desc_tip'    => true,
				'description' => sprintf(
					/* translators: %s: reference date */
					__( 'Maloprodajna cijena koja je vrijedila %s, bez posebnih oblika prodaje. Prazno = izračunaj iz redovne cijene.', 'sidrene-cijene' ),
					SC_Postavke::datum_za_prikaz()
				),
				'data_type'   => 'price',
			)
		);
	}

	/**
	 * Sprema polje s proizvoda.
	 *
	 * @param WC_Product $proizvod Proizvod.
	 */
	public static function spremi_proizvod( $proizvod ): void {
		if ( ! isset( $_POST[ SC_Cijena::META ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		$v = wc_clean( wp_unslash( $_POST[ SC_Cijena::META ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$proizvod->update_meta_data( SC_Cijena::META, wc_format_decimal( $v ) );
	}

	/**
	 * Polje na varijaciji.
	 *
	 * @param int     $petlja     Indeks.
	 * @param array   $podaci     Podaci varijacije.
	 * @param WP_Post $varijacija Objekt varijacije.
	 */
	public static function polje_varijacija( $petlja, $podaci, $varijacija ): void {
		woocommerce_wp_text_input(
			array(
				'id'            => SC_Cijena::META . '[' . $petlja . ']',
				'name'          => SC_Cijena::META . '[' . $petlja . ']',
				'value'         => get_post_meta( $varijacija->ID, SC_Cijena::META, true ),
				'label'         => sprintf(
					/* translators: %s: currency symbol */
					__( 'Sidrena cijena (%s)', 'sidrene-cijene' ),
					get_woocommerce_currency_symbol()
				),
				'data_type'     => 'price',
				'wrapper_class' => 'form-row form-row-full',
			)
		);
	}

	/**
	 * Sprema polje s varijacije.
	 *
	 * @param int $varijacija_id ID varijacije.
	 * @param int $petlja        Indeks.
	 */
	public static function spremi_varijaciju( $varijacija_id, $petlja ): void {
		if ( ! isset( $_POST[ SC_Cijena::META ][ $petlja ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		$v = wc_clean( wp_unslash( $_POST[ SC_Cijena::META ][ $petlja ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		update_post_meta( $varijacija_id, SC_Cijena::META, wc_format_decimal( $v ) );
	}
}
