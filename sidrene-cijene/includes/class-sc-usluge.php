<?php
/**
 * Cjenik usluga.
 *
 * Usluge (rođendani, termini, najam, servisi) obično nisu WooCommerce
 * proizvodi, a Odluka za njih traži zaseban cjenik s pet polja.
 * Ovdje se vode kao vlastiti tip zapisa.
 *
 * @package SidreneCijene
 */

defined( 'ABSPATH' ) || exit;

class SC_Usluge {

	const TIP = 'sc_usluga';

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'registriraj_tip' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_kutija' ) );
		add_action( 'save_post_' . self::TIP, array( __CLASS__, 'spremi' ), 10, 2 );

		add_filter( 'manage_' . self::TIP . '_posts_columns', array( __CLASS__, 'stupci' ) );
		add_action( 'manage_' . self::TIP . '_posts_custom_column', array( __CLASS__, 'sadrzaj_stupca' ), 10, 2 );
	}

	public static function registriraj_tip(): void {
		register_post_type(
			self::TIP,
			array(
				'labels'            => array(
					'name'               => __( 'Usluge', 'sidrene-cijene' ),
					'singular_name'      => __( 'Usluga', 'sidrene-cijene' ),
					'add_new'            => __( 'Dodaj uslugu', 'sidrene-cijene' ),
					'add_new_item'       => __( 'Dodaj novu uslugu', 'sidrene-cijene' ),
					'edit_item'          => __( 'Uredi uslugu', 'sidrene-cijene' ),
					'search_items'       => __( 'Pretraži usluge', 'sidrene-cijene' ),
					'not_found'          => __( 'Nema unesenih usluga.', 'sidrene-cijene' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => false, // Prikazuje se pod izbornikom plugina.
				'supports'          => array( 'title' ),
				'capability_type'   => 'post',
				'map_meta_cap'      => true,
			)
		);
	}

	public static function meta_kutija(): void {
		add_meta_box(
			'sc_usluga_cijene',
			__( 'Cijene usluge', 'sidrene-cijene' ),
			array( __CLASS__, 'prikazi_kutiju' ),
			self::TIP,
			'normal',
			'high'
		);
	}

	/**
	 * Sadržaj meta kutije.
	 *
	 * @param WP_Post $post Zapis.
	 */
	public static function prikazi_kutiju( $post ): void {
		wp_nonce_field( 'sc_usluga_spremi', 'sc_usluga_nonce' );
		$cijena  = get_post_meta( $post->ID, '_sc_cijena', true );
		$sidrena = get_post_meta( $post->ID, '_sc_sidrena', true );
		$akcija  = get_post_meta( $post->ID, '_sc_akcija', true );
		$naziv_a = get_post_meta( $post->ID, '_sc_naziv_akcije', true );
		$valuta  = get_woocommerce_currency_symbol();
		?>
		<style>.sc-polje{margin:0 0 14px}.sc-polje label{display:block;font-weight:600;margin-bottom:4px}.sc-polje input[type=text]{width:220px}.sc-napomena{color:#666;font-size:12px;margin-top:3px}</style>

		<div class="sc-polje">
			<label for="sc_cijena"><?php esc_html_e( 'Aktualna maloprodajna cijena', 'sidrene-cijene' ); ?> (<?php echo esc_html( $valuta ); ?>)</label>
			<input type="text" id="sc_cijena" name="sc_cijena" value="<?php echo esc_attr( $cijena ); ?>" />
		</div>

		<div class="sc-polje">
			<label for="sc_sidrena"><?php esc_html_e( 'Sidrena cijena', 'sidrene-cijene' ); ?> (<?php echo esc_html( $valuta ); ?>)</label>
			<input type="text" id="sc_sidrena" name="sc_sidrena" value="<?php echo esc_attr( $sidrena ); ?>" />
			<p class="sc-napomena">
				<?php
				printf(
					/* translators: %s: reference date */
					esc_html__( 'Cijena koja je vrijedila %s, bez posebnih oblika prodaje. Ovaj iznos se NE mijenja kad mijenjaš aktualnu cijenu.', 'sidrene-cijene' ),
					esc_html( SC_Postavke::datum_za_prikaz() )
				);
				?>
			</p>
		</div>

		<div class="sc-polje">
			<label>
				<input type="checkbox" name="sc_akcija" value="1" <?php checked( $akcija, '1' ); ?> />
				<?php esc_html_e( 'Trenutno je primijenjen poseban oblik prodaje', 'sidrene-cijene' ); ?>
			</label>
		</div>

		<div class="sc-polje">
			<label for="sc_naziv_akcije"><?php esc_html_e( 'Naziv posebnog oblika prodaje', 'sidrene-cijene' ); ?></label>
			<input type="text" id="sc_naziv_akcije" name="sc_naziv_akcije" value="<?php echo esc_attr( $naziv_a ); ?>" />
			<p class="sc-napomena"><?php esc_html_e( 'Npr. Akcija, Sezonsko sniženje, Rasprodaja. Ostavi prazno ako nije na akciji.', 'sidrene-cijene' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Sprema meta podatke usluge.
	 *
	 * @param int     $post_id ID zapisa.
	 * @param WP_Post $post    Zapis.
	 */
	public static function spremi( $post_id, $post ): void {
		if ( ! isset( $_POST['sc_usluga_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sc_usluga_nonce'] ) ), 'sc_usluga_spremi' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$cijena  = isset( $_POST['sc_cijena'] ) ? wc_format_decimal( wc_clean( wp_unslash( $_POST['sc_cijena'] ) ) ) : '';
		$sidrena = isset( $_POST['sc_sidrena'] ) ? wc_format_decimal( wc_clean( wp_unslash( $_POST['sc_sidrena'] ) ) ) : '';

		// Ako sidrena nije upisana, prvi put je jednaka aktualnoj.
		if ( '' === $sidrena && '' !== $cijena ) {
			$sidrena = $cijena;
		}

		update_post_meta( $post_id, '_sc_cijena', $cijena );
		update_post_meta( $post_id, '_sc_sidrena', $sidrena );
		update_post_meta( $post_id, '_sc_akcija', isset( $_POST['sc_akcija'] ) ? '1' : '' );
		update_post_meta( $post_id, '_sc_naziv_akcije', isset( $_POST['sc_naziv_akcije'] ) ? sanitize_text_field( wp_unslash( $_POST['sc_naziv_akcije'] ) ) : '' );

		// Odluka traži osvježenje cjenika usluga kod svake promjene.
		SC_Cjenik::generiraj( 'usluge' );
	}

	/**
	 * Stupci na popisu usluga.
	 *
	 * @param array $stupci Postojeći stupci.
	 */
	public static function stupci( $stupci ): array {
		return array(
			'cb'       => $stupci['cb'] ?? '',
			'title'    => __( 'Usluga', 'sidrene-cijene' ),
			'sc_cij'   => __( 'Aktualna cijena', 'sidrene-cijene' ),
			'sc_sid'   => __( 'Sidrena cijena', 'sidrene-cijene' ),
			'sc_akc'   => __( 'Posebni oblik prodaje', 'sidrene-cijene' ),
			'date'     => __( 'Zadnja izmjena', 'sidrene-cijene' ),
		);
	}

	/**
	 * Sadržaj vlastitih stupaca.
	 *
	 * @param string $stupac  Ključ stupca.
	 * @param int    $post_id ID zapisa.
	 */
	public static function sadrzaj_stupca( $stupac, $post_id ): void {
		switch ( $stupac ) {
			case 'sc_cij':
				echo wp_kses_post( wc_price( (float) get_post_meta( $post_id, '_sc_cijena', true ) ) );
				break;
			case 'sc_sid':
				echo wp_kses_post( wc_price( (float) get_post_meta( $post_id, '_sc_sidrena', true ) ) );
				break;
			case 'sc_akc':
				$a = get_post_meta( $post_id, '_sc_akcija', true );
				echo $a ? esc_html( get_post_meta( $post_id, '_sc_naziv_akcije', true ) ?: __( 'DA', 'sidrene-cijene' ) ) : '—';
				break;
		}
	}

	/**
	 * Sve usluge pripremljene za cjenik.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function za_cjenik(): array {
		$zapisi = get_posts(
			array(
				'post_type'      => self::TIP,
				'post_status'    => 'publish',
				'numberposts'    => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$redci = array();
		foreach ( $zapisi as $z ) {
			$akcija = (bool) get_post_meta( $z->ID, '_sc_akcija', true );
			$redci[] = array(
				'naziv_usluge'                  => $z->post_title,
				'maloprodajna_cijena'           => number_format( (float) get_post_meta( $z->ID, '_sc_cijena', true ), 2, ',', '' ),
				'posebni_oblik_prodaje'         => $akcija ? 'DA' : 'NE',
				'naziv_posebnog_oblika_prodaje' => $akcija ? (string) get_post_meta( $z->ID, '_sc_naziv_akcije', true ) : '',
				'sidrena_cijena'                => number_format( (float) get_post_meta( $z->ID, '_sc_sidrena', true ), 2, ',', '' ),
			);
		}
		return $redci;
	}
}
