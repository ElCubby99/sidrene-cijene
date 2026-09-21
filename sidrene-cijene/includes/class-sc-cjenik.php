<?php
/**
 * Generiranje i objava cjenika u XML i CSV formatu.
 *
 * Odluka o objavi cjenika (NN 101/2026):
 *  - trgovac osvježava cjenik proizvoda jednom dnevno, najkasnije do 08:00
 *  - pružatelj usluge osvježava kod svake promjene
 *  - objavljeni cjenici moraju ostati dostupni 30 dana
 *  - podaci moraju biti dohvatljivi automatiziranim alatima
 *
 * @package SidreneCijene
 */

defined( 'ABSPATH' ) || exit;

class SC_Cjenik {

	const DOGADAJ = 'sc_dnevno_osvjezenje';
	const MAPA    = 'sidrene-cijene';

	/** Polja cjenika proizvoda, redoslijedom iz Odluke. */
	const POLJA_PROIZVODI = array(
		'naziv_proizvoda',
		'sifra_proizvoda',
		'marka',
		'jedinica_mjere',
		'cijena_za_jedinicu_mjere',
		'maloprodajna_cijena',
		'posebni_oblik_prodaje',
		'naziv_posebnog_oblika_prodaje',
		'sidrena_cijena',
		'barkod',
		'dostupnost',
	);

	/** Polja cjenika usluga. */
	const POLJA_USLUGE = array(
		'naziv_usluge',
		'maloprodajna_cijena',
		'posebni_oblik_prodaje',
		'naziv_posebnog_oblika_prodaje',
		'sidrena_cijena',
	);

	public static function init(): void {
		add_action( self::DOGADAJ, array( __CLASS__, 'generiraj_sve' ) );
		add_action( 'admin_post_sc_generiraj', array( __CLASS__, 'rucno_generiranje' ) );
	}

	/* ------------------------------------------------------------ raspored */

	/**
	 * Zakazuje dnevno osvježenje.
	 *
	 * @param string|null $vrijeme Vrijeme u obliku HH:MM.
	 */
	public static function zakazi( ?string $vrijeme = null ): void {
		if ( wp_next_scheduled( self::DOGADAJ ) ) {
			return;
		}
		$vrijeme = $vrijeme ?: SC_Postavke::get( 'vrijeme_osvjezenja' );
		$prvi    = self::sljedece_izvodjenje( (string) $vrijeme );
		wp_schedule_event( $prvi, 'daily', self::DOGADAJ );
	}

	public static function odzakazi(): void {
		$sljedeci = wp_next_scheduled( self::DOGADAJ );
		while ( $sljedeci ) {
			wp_unschedule_event( $sljedeci, self::DOGADAJ );
			$sljedeci = wp_next_scheduled( self::DOGADAJ );
		}
	}

	/**
	 * Vremenska oznaka sljedećeg izvođenja u lokalnoj vremenskoj zoni.
	 *
	 * @param string $vrijeme HH:MM.
	 */
	private static function sljedece_izvodjenje( string $vrijeme ): int {
		[ $sat, $min ] = array_pad( explode( ':', $vrijeme ), 2, '0' );
		$zona  = wp_timezone();
		$sada  = new DateTime( 'now', $zona );
		$cilj  = new DateTime( 'now', $zona );
		$cilj->setTime( (int) $sat, (int) $min, 0 );
		if ( $cilj <= $sada ) {
			$cilj->modify( '+1 day' );
		}
		return $cilj->getTimestamp();
	}

	/* -------------------------------------------------------------- mapa */

	/** Puna putanja mape s cjenicima. */
	public static function putanja_mape(): string {
		$upload = wp_upload_dir();
		return trailingslashit( $upload['basedir'] ) . self::MAPA;
	}

	/** Javna adresa mape s cjenicima. */
	public static function url_mape(): string {
		$upload = wp_upload_dir();
		return trailingslashit( $upload['baseurl'] ) . self::MAPA;
	}

	/** Stvara mapu i indeks; ne blokira robote jer Odluka traži strojni pristup. */
	public static function pripremi_mapu(): void {
		$mapa = self::putanja_mape();
		if ( ! file_exists( $mapa ) ) {
			wp_mkdir_p( $mapa );
		}
		// Namjerno BEZ .htaccess zabrane — datoteke moraju biti javno čitljive.
		$indeks = trailingslashit( $mapa ) . 'index.html';
		if ( ! file_exists( $indeks ) ) {
			file_put_contents( $indeks, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}

	/* --------------------------------------------------------- imenovanje */

	/**
	 * Naziv datoteke prema shemi iz Odluke.
	 *
	 * @param string $vrsta     PROIZVODI ili USLUGE.
	 * @param string $nastavak  csv ili xml.
	 * @param int    $vrijeme   Vremenska oznaka.
	 */
	public static function naziv_datoteke( string $vrsta, string $nastavak, int $vrijeme ): string {
		$oblik   = strtoupper( (string) SC_Postavke::get( 'oblik_objekta' ) );
		$adresa  = SC_Postavke::adresa_za_datoteku();
		$oznaka  = (string) SC_Postavke::get( 'oznaka_objekta' );
		$pohrana = (string) SC_Postavke::get( 'broj_pohrane' );
		$zig     = wp_date( 'dmY-Hi', $vrijeme );

		return sprintf( '%s_%s_%s_%s_%s_%s.%s', $oblik, $adresa, $oznaka, $pohrana, $vrsta, $zig, $nastavak );
	}

	/* --------------------------------------------------------- generiranje */

	/** Generira oba cjenika. */
	public static function generiraj_sve(): array {
		$p = self::generiraj( 'proizvodi' );
		$u = self::generiraj( 'usluge' );
		self::ocisti_arhivu();
		return array( 'proizvodi' => $p, 'usluge' => $u );
	}

	/**
	 * Generira jedan cjenik.
	 *
	 * @param string $vrsta proizvodi ili usluge.
	 * @return array{datoteke:string[],redaka:int}
	 */
	public static function generiraj( string $vrsta ): array {
		self::pripremi_mapu();

		if ( 'usluge' === $vrsta ) {
			$redci = SC_Usluge::za_cjenik();
			$polja = self::POLJA_USLUGE;
			$oznaka_vrste = 'USLUGE';
			$korijen = 'cjenik_usluga';
			$cvor    = 'usluga';
		} else {
			$redci = self::redci_proizvoda();
			$polja = self::POLJA_PROIZVODI;
			$oznaka_vrste = 'PROIZVODI';
			$korijen = 'cjenik_proizvoda';
			$cvor    = 'proizvod';
		}

		$vrijeme = time();
		$mapa    = trailingslashit( self::putanja_mape() );

		$csv = $mapa . self::naziv_datoteke( $oznaka_vrste, 'csv', $vrijeme );
		$xml = $mapa . self::naziv_datoteke( $oznaka_vrste, 'xml', $vrijeme );

		self::zapisi_csv( $csv, $redci, $polja );
		self::zapisi_xml( $xml, $redci, $polja, $korijen, $cvor, $vrijeme );

		update_option( 'sc_zadnje_generiranje_' . $vrsta, $vrijeme );

		return array(
			'datoteke' => array( basename( $csv ), basename( $xml ) ),
			'redaka'   => count( $redci ),
		);
	}

	/**
	 * Redci cjenika proizvoda.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function redci_proizvoda(): array {
		$idevi = wc_get_products(
			array(
				'status' => array( 'publish' ),
				'limit'  => -1,
				'return' => 'ids',
			)
		);

		$jedinica = (string) SC_Postavke::get( 'jedinica_mjere' );
		$naziv_akcije_zadani = (string) SC_Postavke::get( 'naziv_akcije' );
		$redci = array();

		foreach ( $idevi as $id ) {
			$proizvod = wc_get_product( $id );
			if ( ! $proizvod ) {
				continue;
			}

			// Varijabilni proizvod se u cjenik upisuje po varijacijama.
			if ( $proizvod->is_type( 'variable' ) ) {
				foreach ( $proizvod->get_children() as $var_id ) {
					$var = wc_get_product( $var_id );
					if ( $var ) {
						$redci[] = self::redak_proizvoda( $var, $jedinica, $naziv_akcije_zadani, $proizvod );
					}
				}
				continue;
			}

			$redci[] = self::redak_proizvoda( $proizvod, $jedinica, $naziv_akcije_zadani );
		}

		return array_values( array_filter( $redci ) );
	}

	/**
	 * Jedan redak cjenika proizvoda.
	 *
	 * @param WC_Product      $proizvod  Proizvod ili varijacija.
	 * @param string          $jedinica  Jedinica mjere.
	 * @param string          $naziv_akc Zadani naziv posebnog oblika prodaje.
	 * @param WC_Product|null $nadredeni Nadređeni proizvod za varijacije.
	 * @return array<string,string>
	 */
	private static function redak_proizvoda( WC_Product $proizvod, string $jedinica, string $naziv_akc, ?WC_Product $nadredeni = null ): array {
		$aktualna = SC_Cijena::aktualna( $proizvod );
		$sidrena  = SC_Cijena::dohvati( $proizvod );
		$akcija   = SC_Cijena::na_akciji( $proizvod );

		$naziv = $proizvod->get_name();
		if ( $nadredeni && method_exists( $proizvod, 'get_attribute_summary' ) ) {
			$dodatak = $proizvod->get_attribute_summary();
			if ( $dodatak ) {
				$naziv = $nadredeni->get_name() . ' – ' . $dodatak;
			}
		}

		$izvor_marke = $nadredeni ?: $proizvod;
		$marke       = wp_get_post_terms( $izvor_marke->get_id(), array( 'product_brand', 'pa_brand', 'pwb-brand' ), array( 'fields' => 'names' ) );
		$marka       = is_wp_error( $marke ) ? '' : implode( ', ', $marke );

		$barkod = '';
		if ( method_exists( $proizvod, 'get_global_unique_id' ) ) {
			$barkod = (string) $proizvod->get_global_unique_id();
		}
		if ( '' === $barkod ) {
			$barkod = (string) $proizvod->get_meta( '_gtin' );
		}

		return array(
			'naziv_proizvoda'               => wp_strip_all_tags( $naziv ),
			'sifra_proizvoda'               => $proizvod->get_sku() ?: (string) $proizvod->get_id(),
			'marka'                         => $marka,
			'jedinica_mjere'                => $jedinica,
			'cijena_za_jedinicu_mjere'      => null === $aktualna ? '' : number_format( $aktualna, 2, ',', '' ),
			'maloprodajna_cijena'           => null === $aktualna ? '' : number_format( $aktualna, 2, ',', '' ),
			'posebni_oblik_prodaje'         => $akcija ? 'DA' : 'NE',
			'naziv_posebnog_oblika_prodaje' => $akcija ? $naziv_akc : '',
			'sidrena_cijena'                => SC_Cijena::za_cjenik( $sidrena ),
			'barkod'                        => $barkod,
			'dostupnost'                    => $proizvod->is_in_stock() ? 'DOSTUPNO' : 'NEDOSTUPNO',
		);
	}

	/* ------------------------------------------------------------- zapis */

	/**
	 * Zapis CSV datoteke.
	 *
	 * @param string $putanja Putanja.
	 * @param array  $redci   Redci.
	 * @param array  $polja   Polja.
	 */
	private static function zapisi_csv( string $putanja, array $redci, array $polja ): void {
		$f = fopen( $putanja, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $f ) {
			return;
		}
		fwrite( $f, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $f, $polja, ';' );
		foreach ( $redci as $r ) {
			$red = array();
			foreach ( $polja as $p ) {
				$red[] = $r[ $p ] ?? '';
			}
			fputcsv( $f, $red, ';' );
		}
		fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * Zapis XML datoteke.
	 *
	 * @param string $putanja Putanja.
	 * @param array  $redci   Redci.
	 * @param array  $polja   Polja.
	 * @param string $korijen Korijenski element.
	 * @param string $cvor    Element stavke.
	 * @param int    $vrijeme Vremenska oznaka.
	 */
	private static function zapisi_xml( string $putanja, array $redci, array $polja, string $korijen, string $cvor, int $vrijeme ): void {
		$x = new XMLWriter();
		$x->openMemory();
		$x->setIndent( true );
		$x->startDocument( '1.0', 'UTF-8' );
		$x->startElement( $korijen );

		$x->startElement( 'zaglavlje' );
		$x->writeElement( 'tvrtka', (string) SC_Postavke::get( 'tvrtka' ) );
		$x->writeElement( 'oib', (string) SC_Postavke::get( 'oib' ) );
		$x->writeElement( 'oblik_objekta', (string) SC_Postavke::get( 'oblik_objekta' ) );
		$x->writeElement( 'adresa', (string) SC_Postavke::get( 'adresa' ) );
		$x->writeElement( 'oznaka_objekta', (string) SC_Postavke::get( 'oznaka_objekta' ) );
		$x->writeElement( 'broj_pohrane', (string) SC_Postavke::get( 'broj_pohrane' ) );
		$x->writeElement( 'referentni_datum', (string) SC_Postavke::get( 'referentni_datum' ) );
		$x->writeElement( 'vrijeme_objave', wp_date( 'c', $vrijeme ) );
		$x->writeElement( 'valuta', get_woocommerce_currency() );
		$x->endElement();

		$x->startElement( 'stavke' );
		foreach ( $redci as $r ) {
			$x->startElement( $cvor );
			foreach ( $polja as $p ) {
				$x->writeElement( $p, (string) ( $r[ $p ] ?? '' ) );
			}
			$x->endElement();
		}
		$x->endElement();

		$x->endElement();
		$x->endDocument();

		file_put_contents( $putanja, $x->outputMemory() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/* ------------------------------------------------------------ arhiva */

	/** Briše datoteke starije od zadanog broja dana. */
	public static function ocisti_arhivu(): int {
		$dana  = (int) SC_Postavke::get( 'dana_arhive' );
		$mapa  = trailingslashit( self::putanja_mape() );
		$prag  = time() - ( $dana * DAY_IN_SECONDS );
		$broj  = 0;

		foreach ( (array) glob( $mapa . '*.{csv,xml}', GLOB_BRACE ) as $datoteka ) {
			if ( filemtime( $datoteka ) < $prag ) {
				wp_delete_file( $datoteka );
				$broj++;
			}
		}
		return $broj;
	}

	/**
	 * Popis objavljenih cjenika, najnoviji prvi.
	 *
	 * @param int $koliko Najviše koliko datoteka.
	 * @return array<int,array{naziv:string,url:string,vrijeme:int,velicina:int}>
	 */
	public static function popis( int $koliko = 200 ): array {
		$mapa  = trailingslashit( self::putanja_mape() );
		$url   = trailingslashit( self::url_mape() );
		$popis = array();

		foreach ( (array) glob( $mapa . '*.{csv,xml}', GLOB_BRACE ) as $datoteka ) {
			$popis[] = array(
				'naziv'    => basename( $datoteka ),
				'url'      => $url . rawurlencode( basename( $datoteka ) ),
				'vrijeme'  => (int) filemtime( $datoteka ),
				'velicina' => (int) filesize( $datoteka ),
			);
		}
		usort(
			$popis,
			static function ( $a, $b ) {
				return $b['vrijeme'] <=> $a['vrijeme'];
			}
		);
		return array_slice( $popis, 0, $koliko );
	}

	/** Ručno pokretanje iz admin sučelja. */
	public static function rucno_generiranje(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Nemate ovlasti.', 'sidrene-cijene' ) );
		}
		check_admin_referer( 'sc_generiraj' );

		$rezultat = self::generiraj_sve();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'sidrene-cijene',
					'tab'       => 'cjenici',
					'sc_poruka' => rawurlencode(
						sprintf(
							/* translators: 1: product rows, 2: service rows */
							__( 'Cjenici su generirani. Proizvoda: %1$d, usluga: %2$d.', 'sidrene-cijene' ),
							$rezultat['proizvodi']['redaka'],
							$rezultat['usluge']['redaka']
						)
					),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
