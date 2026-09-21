<?php
/**
 * Administratorsko sučelje.
 *
 * @package SidreneCijene
 */

defined( 'ABSPATH' ) || exit;

class SC_Admin {

	const STRANICA = 'sidrene-cijene';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'izbornik' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'skripte' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SC_FILE ), array( __CLASS__, 'poveznice' ) );
	}

	public static function izbornik(): void {
		add_menu_page(
			__( 'Sidrene cijene', 'sidrene-cijene' ),
			__( 'Sidrene cijene', 'sidrene-cijene' ),
			'manage_woocommerce',
			self::STRANICA,
			array( __CLASS__, 'prikazi' ),
			'dashicons-tag',
			57
		);

		add_submenu_page(
			self::STRANICA,
			__( 'Usluge', 'sidrene-cijene' ),
			__( 'Usluge', 'sidrene-cijene' ),
			'manage_woocommerce',
			'edit.php?post_type=' . SC_Usluge::TIP
		);
	}

	/**
	 * Poveznice ispod naziva plugina.
	 *
	 * @param array $poveznice Postojeće poveznice.
	 */
	public static function poveznice( $poveznice ): array {
		array_unshift(
			$poveznice,
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::STRANICA ) ) . '">' . esc_html__( 'Postavke', 'sidrene-cijene' ) . '</a>'
		);
		return $poveznice;
	}

	/**
	 * Učitavanje skripti samo na stranici plugina.
	 *
	 * @param string $kuka Identifikator stranice.
	 */
	public static function skripte( $kuka ): void {
		if ( false === strpos( (string) $kuka, self::STRANICA ) ) {
			return;
		}
		wp_enqueue_script( 'jquery' );
		wp_localize_script(
			'jquery',
			'SC_AJAX',
			array(
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'sc_zakljucavanje' ),
				'i18n'  => array(
					'potvrda_otkljucaj' => __( 'Ovime se brišu sve zaključane sidrene cijene. Nastaviti?', 'sidrene-cijene' ),
					'potvrda_zakljucaj' => sprintf(
						/* translators: %s: reference date */
						__( 'Sidrene cijene bit će trajno zamrznute na trenutne redovne cijene, kao stanje od %s Ovo mijenja podatke u bazi. Nastaviti?', 'sidrene-cijene' ),
						SC_Postavke::datum_za_prikaz()
					),
					'gotovo'            => __( 'Gotovo.', 'sidrene-cijene' ),
					'radim'             => __( 'Obrada u tijeku…', 'sidrene-cijene' ),
				),
			)
		);
	}

	/* ----------------------------------------------------------- prikaz */

	public static function prikazi(): void {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'pregled'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabovi = array(
			'pregled'  => __( 'Pregled', 'sidrene-cijene' ),
			'cjenici'  => __( 'Cjenici', 'sidrene-cijene' ),
			'postavke' => __( 'Postavke', 'sidrene-cijene' ),
			'pomoc'    => __( 'Upute', 'sidrene-cijene' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sidrene cijene', 'sidrene-cijene' ); ?></h1>

			<?php if ( isset( $_GET['sc_poruka'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php echo esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['sc_poruka'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				</p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabovi as $kljuc => $naziv ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::STRANICA . '&tab=' . $kljuc ) ); ?>"
					   class="nav-tab <?php echo $tab === $kljuc ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $naziv ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div style="margin-top:18px">
			<?php
			switch ( $tab ) {
				case 'cjenici':
					self::tab_cjenici();
					break;
				case 'postavke':
					self::tab_postavke();
					break;
				case 'pomoc':
					self::tab_pomoc();
					break;
				default:
					self::tab_pregled();
			}
			?>
			</div>
		</div>
		<?php
	}

	/* ---------------------------------------------------------- pregled */

	private static function tab_pregled(): void {
		$status = SC_Zakljucavanje::status();
		$brojke = SC_Pregled::brojke();
		$filtar = isset( $_GET['filtar'] ) ? sanitize_key( wp_unslash( $_GET['filtar'] ) ) : 'sve'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$str    = isset( $_GET['pstr'] ) ? (int) $_GET['pstr'] : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$podaci = SC_Pregled::dohvati( $str, $filtar );
		?>
		<div class="sc-kartice" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:22px">
			<?php
			self::kartica( __( 'Proizvoda u katalogu', 'sidrene-cijene' ), (string) $brojke['ukupno'], '' );
			self::kartica( __( 'Zaključanih sidrenih cijena', 'sidrene-cijene' ), $status['zakljucano'] . ' / ' . $status['ukupno'], $status['nezakljucano'] > 0 ? 'upozorenje' : 'ok' );
			self::kartica( __( 'Bez sidrene cijene', 'sidrene-cijene' ), (string) $brojke['bez_sidrene'], $brojke['bez_sidrene'] > 0 ? 'greska' : 'ok' );
			self::kartica( __( 'Bez barkoda', 'sidrene-cijene' ), (string) $brojke['bez_barkoda'], $brojke['bez_barkoda'] > 0 ? 'upozorenje' : 'ok' );
			?>
		</div>

		<div class="card" style="max-width:none;padding:16px 20px">
			<h2 style="margin-top:0"><?php esc_html_e( 'Zaključavanje sidrenih cijena', 'sidrene-cijene' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: reference date */
					esc_html__( 'Dok sidrena cijena nije zaključana, računa se iz redovne cijene — pa se pomakne čim promijeniš cijenu. Zaključavanje upisuje iznos u bazu i zamrzava ga na stanje od %s', 'sidrene-cijene' ),
					'<strong>' . esc_html( SC_Postavke::datum_za_prikaz() ) . '</strong>'
				);
				?>
			</p>
			<div style="background:#fcf9e8;border-left:4px solid #dba617;padding:12px 16px;margin:14px 0">
				<label style="display:flex;gap:9px;align-items:flex-start;cursor:pointer">
					<input type="checkbox" id="sc-potvrda" style="margin-top:3px" />
					<span>
						<strong><?php
						printf(
							/* translators: %s: reference date */
							esc_html__( 'Potvrđujem da su cijene u mojoj trgovini one koje su vrijedile na dan %s', 'sidrene-cijene' ),
							esc_html( SC_Postavke::datum_za_prikaz() )
						);
						?></strong><br>
						<span style="color:#646970;font-size:12px">
							<?php esc_html_e( 'Ako su cijene u međuvremenu mijenjane, zaključavanje će zamrznuti pogrešan iznos. Prvo ispravi te proizvode ili im ručno upiši sidrenu cijenu. Preporuka: napravi sigurnosnu kopiju baze.', 'sidrene-cijene' ); ?>
						</span>
					</span>
				</label>
			</div>
			<p>
				<button class="button button-primary" id="sc-zakljucaj" disabled><?php esc_html_e( 'Zaključaj sidrene cijene', 'sidrene-cijene' ); ?></button>
				<button class="button" id="sc-otkljucaj"><?php esc_html_e( 'Poništi zaključavanje', 'sidrene-cijene' ); ?></button>
				<span id="sc-napredak" style="margin-left:12px;font-weight:600"></span>
			</p>
			<div id="sc-traka" style="display:none;height:8px;background:#e0e0e0;border-radius:4px;overflow:hidden;max-width:420px">
				<div id="sc-traka-ispuna" style="height:100%;width:0;background:#2271b1;transition:width .25s"></div>
			</div>
		</div>

		<h2 style="margin-top:26px"><?php esc_html_e( 'Proizvodi', 'sidrene-cijene' ); ?></h2>
		<ul class="subsubsub">
			<?php
			$filtri = array(
				'sve'          => __( 'Svi', 'sidrene-cijene' ),
				'razlika'      => __( 'Cijena se razlikuje od sidrene', 'sidrene-cijene' ),
				'nezakljucano' => __( 'Nezaključano', 'sidrene-cijene' ),
				'bez_sidrene'  => __( 'Bez sidrene cijene', 'sidrene-cijene' ),
				'bez_barkoda'  => __( 'Bez barkoda', 'sidrene-cijene' ),
			);
			$zadnji = array_key_last( $filtri );
			foreach ( $filtri as $k => $naziv ) :
				?>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::STRANICA . '&filtar=' . $k ) ); ?>"
					   class="<?php echo $filtar === $k ? 'current' : ''; ?>"><?php echo esc_html( $naziv ); ?></a>
					<?php echo $k !== $zadnji ? ' |' : ''; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<table class="wp-list-table widefat fixed striped" style="margin-top:8px">
			<thead>
				<tr>
					<th style="width:32%"><?php esc_html_e( 'Proizvod', 'sidrene-cijene' ); ?></th>
					<th><?php esc_html_e( 'Šifra', 'sidrene-cijene' ); ?></th>
					<th><?php esc_html_e( 'Aktualna', 'sidrene-cijene' ); ?></th>
					<th><?php esc_html_e( 'Sidrena', 'sidrene-cijene' ); ?></th>
					<th><?php esc_html_e( 'Barkod', 'sidrene-cijene' ); ?></th>
					<th><?php esc_html_e( 'Status', 'sidrene-cijene' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $podaci['redci'] ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'Nema proizvoda za prikaz.', 'sidrene-cijene' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $podaci['redci'] as $r ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( (string) $r['url'] ); ?>"><?php echo esc_html( $r['naziv'] ); ?></a></td>
					<td><code><?php echo esc_html( (string) $r['sku'] ); ?></code></td>
					<td><?php echo null === $r['aktualna'] ? '—' : wp_kses_post( wc_price( $r['aktualna'] ) ); ?></td>
					<td><?php echo null === $r['sidrena'] ? '<span style="color:#d63638">—</span>' : wp_kses_post( SC_Cijena::formatiraj( $r['sidrena'] ) ); ?></td>
					<td><?php echo '' === $r['barkod'] ? '<span style="color:#996800">—</span>' : '<code>' . esc_html( $r['barkod'] ) . '</code>'; ?></td>
					<td>
						<?php
						if ( $r['zakljucana'] ) {
							echo '<span style="color:#0a7d28">' . esc_html__( 'zaključano', 'sidrene-cijene' ) . '</span>';
						} else {
							echo '<span style="color:#996800">' . esc_html__( 'računa se', 'sidrene-cijene' ) . '</span>';
						}
						if ( $r['na_akciji'] ) {
							echo ' · <span style="color:#2271b1">' . esc_html__( 'akcija', 'sidrene-cijene' ) . '</span>';
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $podaci['stranica_ukupno'] > 1 ) : ?>
			<p style="margin-top:12px">
				<?php
				for ( $i = 1; $i <= $podaci['stranica_ukupno']; $i++ ) {
					$aktivna = $i === $podaci['stranica'];
					printf(
						'<a href="%s" class="button %s" style="margin-right:4px">%d</a>',
						esc_url( admin_url( 'admin.php?page=' . self::STRANICA . '&filtar=' . $filtar . '&pstr=' . $i ) ),
						$aktivna ? 'button-primary' : '',
						$i
					);
				}
				?>
			</p>
		<?php endif; ?>

		<script>
		jQuery(function($){
			function serija(){
				$.post(SC_AJAX.url,{action:'sc_zakljucaj_seriju',nonce:SC_AJAX.nonce},function(o){
					if(!o||!o.success){ $('#sc-napredak').text('Greška.'); return; }
					var d=o.data, preostalo=d.preostalo;
					$('#sc-napredak').text(SC_AJAX.i18n.radim+' preostalo: '+preostalo);
					var ukupno=window.__scUkupno||(preostalo+d.obradeno+d.preskoceno);
					window.__scUkupno=ukupno;
					$('#sc-traka-ispuna').css('width',Math.round(100*(ukupno-preostalo)/Math.max(1,ukupno))+'%');
					if(preostalo>0){ serija(); }
					else { $('#sc-napredak').text(SC_AJAX.i18n.gotovo); setTimeout(function(){location.reload();},900); }
				});
			}
			$('#sc-potvrda').on('change',function(){
				$('#sc-zakljucaj').prop('disabled',!$(this).is(':checked'));
			});
			$('#sc-zakljucaj').on('click',function(e){
				e.preventDefault();
				if(!$('#sc-potvrda').is(':checked')) return;
				if(!confirm(SC_AJAX.i18n.potvrda_zakljucaj)) return;
				window.__scUkupno=null;
				$('#sc-traka').show(); $(this).prop('disabled',true); $('#sc-potvrda').prop('disabled',true); serija();
			});
			$('#sc-otkljucaj').on('click',function(e){
				e.preventDefault();
				if(!confirm(SC_AJAX.i18n.potvrda_otkljucaj)) return;
				$.post(SC_AJAX.url,{action:'sc_otkljucaj_sve',nonce:SC_AJAX.nonce},function(){ location.reload(); });
			});
		});
		</script>
		<?php
	}

	/**
	 * Jedna brojčana kartica.
	 *
	 * @param string $naslov    Naslov.
	 * @param string $vrijednost Vrijednost.
	 * @param string $stanje    ok|upozorenje|greska|''.
	 */
	private static function kartica( string $naslov, string $vrijednost, string $stanje ): void {
		$boje = array(
			'ok'         => '#0a7d28',
			'upozorenje' => '#996800',
			'greska'     => '#d63638',
		);
		$boja = $boje[ $stanje ] ?? '#1d2327';
		printf(
			'<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:12px 16px;min-width:180px">
				<div style="font-size:12px;color:#646970;margin-bottom:4px">%s</div>
				<div style="font-size:22px;font-weight:600;color:%s">%s</div>
			</div>',
			esc_html( $naslov ),
			esc_attr( $boja ),
			esc_html( $vrijednost )
		);
	}

	/* ---------------------------------------------------------- cjenici */

	private static function tab_cjenici(): void {
		$popis    = SC_Cjenik::popis();
		$sljedeci = wp_next_scheduled( SC_Cjenik::DOGADAJ );
		?>
		<div class="card" style="max-width:none;padding:16px 20px">
			<h2 style="margin-top:0"><?php esc_html_e( 'Objava cjenika', 'sidrene-cijene' ); ?></h2>
			<p>
				<?php esc_html_e( 'Javna mapa:', 'sidrene-cijene' ); ?>
				<code><a href="<?php echo esc_url( SC_Cjenik::url_mape() ); ?>" target="_blank" rel="noopener"><?php echo esc_html( SC_Cjenik::url_mape() ); ?></a></code>
			</p>
			<p>
				<?php esc_html_e( 'Sljedeće automatsko osvježenje:', 'sidrene-cijene' ); ?>
				<strong><?php echo $sljedeci ? esc_html( wp_date( 'd.m.Y. H:i', $sljedeci ) ) : esc_html__( 'nije zakazano', 'sidrene-cijene' ); ?></strong>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'sc_generiraj' ); ?>
				<input type="hidden" name="action" value="sc_generiraj" />
				<button class="button button-primary"><?php esc_html_e( 'Generiraj cjenike sada', 'sidrene-cijene' ); ?></button>
			</form>
		</div>

		<h2 style="margin-top:26px"><?php esc_html_e( 'Arhiva', 'sidrene-cijene' ); ?>
			<span style="font-weight:400;font-size:13px;color:#646970">
				(<?php printf( esc_html__( 'čuva se %d dana', 'sidrene-cijene' ), (int) SC_Postavke::get( 'dana_arhive' ) ); ?>)
			</span>
		</h2>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr>
				<th style="width:55%"><?php esc_html_e( 'Datoteka', 'sidrene-cijene' ); ?></th>
				<th><?php esc_html_e( 'Objavljeno', 'sidrene-cijene' ); ?></th>
				<th><?php esc_html_e( 'Veličina', 'sidrene-cijene' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( empty( $popis ) ) : ?>
				<tr><td colspan="3"><?php esc_html_e( 'Još nema generiranih cjenika.', 'sidrene-cijene' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $popis as $d ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( $d['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $d['naziv'] ); ?></a></td>
					<td><?php echo esc_html( wp_date( 'd.m.Y. H:i', $d['vrijeme'] ) ); ?></td>
					<td><?php echo esc_html( size_format( $d['velicina'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/* --------------------------------------------------------- postavke */

	private static function tab_postavke(): void {
		$p = SC_Postavke::sve();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'sc_grupa_postavki' ); ?>
			<table class="form-table" role="presentation">
				<tr><th colspan="2"><h2 style="margin:0"><?php esc_html_e( 'Podaci o obvezniku', 'sidrene-cijene' ); ?></h2></th></tr>
				<?php
				self::polje( 'tvrtka', __( 'Naziv tvrtke', 'sidrene-cijene' ), $p['tvrtka'] );
				self::polje( 'oib', __( 'OIB', 'sidrene-cijene' ), $p['oib'] );
				self::polje( 'adresa', __( 'Adresa prodajnog objekta', 'sidrene-cijene' ), $p['adresa'], __( 'Npr. Josipa Lončara 1, 10090 Zagreb', 'sidrene-cijene' ) );
				self::polje( 'oblik_objekta', __( 'Oblik prodajnog objekta', 'sidrene-cijene' ), $p['oblik_objekta'], __( 'Npr. WEBSHOP, PRODAVAONICA, SALON.', 'sidrene-cijene' ) );
				self::polje( 'oznaka_objekta', __( 'Oznaka objekta', 'sidrene-cijene' ), $p['oznaka_objekta'] );
				self::polje( 'broj_pohrane', __( 'Broj pohrane', 'sidrene-cijene' ), $p['broj_pohrane'] );
				?>

				<tr><th colspan="2"><h2 style="margin:18px 0 0"><?php esc_html_e( 'Sidrena cijena', 'sidrene-cijene' ); ?></h2></th></tr>
				<tr>
					<th scope="row"><label for="sc_referentni_datum"><?php esc_html_e( 'Referentni datum', 'sidrene-cijene' ); ?></label></th>
					<td>
						<input type="date" id="sc_referentni_datum" name="<?php echo esc_attr( SC_Postavke::OPCIJA ); ?>[referentni_datum]" value="<?php echo esc_attr( $p['referentni_datum'] ); ?>" />
						<p class="description">
							<?php
							printf(
								/* translators: 1: general date, 2: earlier date */
								esc_html__( '%1$s za većinu proizvoda i usluga. %2$s za hranu, piće, kozmetiku, sredstva za čišćenje, toaletne potrepštine i proizvode za kućanstvo koji su već bili obuhvaćeni ranijom odlukom.', 'sidrene-cijene' ),
								'<strong>' . esc_html( gmdate( 'd.m.Y.', strtotime( SC_DATUM_OPCI ) ) ) . '</strong>',
								'<strong>' . esc_html( gmdate( 'd.m.Y.', strtotime( SC_DATUM_RANIJI ) ) ) . '</strong>'
							);
							?>
						</p>
					</td>
				</tr>
				<?php
				self::polje( 'tekst_oznake', __( 'Tekst oznake na trgovini', 'sidrene-cijene' ), $p['tekst_oznake'] );
				self::potvrda( 'prikazi_na_shopu', __( 'Prikazuj sidrenu cijenu na trgovini', 'sidrene-cijene' ), $p['prikazi_na_shopu'] );
				?>

				<tr><th colspan="2"><h2 style="margin:18px 0 0"><?php esc_html_e( 'Cjenik', 'sidrene-cijene' ); ?></h2></th></tr>
				<?php
				self::polje( 'jedinica_mjere', __( 'Zadana jedinica mjere', 'sidrene-cijene' ), $p['jedinica_mjere'] );
				self::polje( 'naziv_akcije', __( 'Zadani naziv posebnog oblika prodaje', 'sidrene-cijene' ), $p['naziv_akcije'] );
				?>
				<tr>
					<th scope="row"><label for="sc_vrijeme"><?php esc_html_e( 'Vrijeme dnevnog osvježenja', 'sidrene-cijene' ); ?></label></th>
					<td>
						<input type="time" id="sc_vrijeme" name="<?php echo esc_attr( SC_Postavke::OPCIJA ); ?>[vrijeme_osvjezenja]" value="<?php echo esc_attr( $p['vrijeme_osvjezenja'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Odluka traži osvježenje najkasnije do 08:00 za tekući radni dan.', 'sidrene-cijene' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sc_dana"><?php esc_html_e( 'Čuvanje arhive (dana)', 'sidrene-cijene' ); ?></label></th>
					<td><input type="number" min="1" max="365" id="sc_dana" name="<?php echo esc_attr( SC_Postavke::OPCIJA ); ?>[dana_arhive]" value="<?php echo esc_attr( (string) $p['dana_arhive'] ); ?>" /></td>
				</tr>

				<tr><th colspan="2"><h2 style="margin:18px 0 0"><?php esc_html_e( 'Uklanjanje', 'sidrene-cijene' ); ?></h2></th></tr>
				<?php self::potvrda( 'brisi_pri_uklanjanju', __( 'Obriši sve podatke plugina pri deinstalaciji', 'sidrene-cijene' ), $p['brisi_pri_uklanjanju'] ); ?>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Tekstualno polje postavke.
	 *
	 * @param string $kljuc  Ključ.
	 * @param string $oznaka Oznaka.
	 * @param string $vrijednost Vrijednost.
	 * @param string $opis   Opis.
	 */
	private static function polje( string $kljuc, string $oznaka, string $vrijednost, string $opis = '' ): void {
		printf(
			'<tr><th scope="row"><label for="sc_%1$s">%2$s</label></th><td>
				<input type="text" class="regular-text" id="sc_%1$s" name="%3$s[%1$s]" value="%4$s" />
				%5$s
			</td></tr>',
			esc_attr( $kljuc ),
			esc_html( $oznaka ),
			esc_attr( SC_Postavke::OPCIJA ),
			esc_attr( $vrijednost ),
			$opis ? '<p class="description">' . esc_html( $opis ) . '</p>' : ''
		);
	}

	/**
	 * Potvrdni okvir postavke.
	 *
	 * @param string $kljuc  Ključ.
	 * @param string $oznaka Oznaka.
	 * @param string $vrijednost da|ne.
	 */
	private static function potvrda( string $kljuc, string $oznaka, string $vrijednost ): void {
		printf(
			'<tr><th scope="row">%2$s</th><td><label>
				<input type="hidden" name="%3$s[%1$s]" value="ne" />
				<input type="checkbox" name="%3$s[%1$s]" value="da" %4$s /> %2$s
			</label></td></tr>',
			esc_attr( $kljuc ),
			esc_html( $oznaka ),
			esc_attr( SC_Postavke::OPCIJA ),
			checked( $vrijednost, 'da', false )
		);
	}

	/* ------------------------------------------------------------ upute */

	private static function tab_pomoc(): void {
		?>
		<div class="card" style="max-width:820px;padding:18px 22px">
			<h2 style="margin-top:0"><?php esc_html_e( 'Redoslijed postavljanja', 'sidrene-cijene' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Ispuni podatke o obvezniku u kartici Postavke.', 'sidrene-cijene' ); ?></li>
				<li><?php esc_html_e( 'Provjeri da su cijene u trgovini one koje su vrijedile na referentni datum.', 'sidrene-cijene' ); ?></li>
				<li><strong><?php esc_html_e( 'Klikni „Zaključaj sidrene cijene” u kartici Pregled.', 'sidrene-cijene' ); ?></strong></li>
				<li><?php esc_html_e( 'Unesi usluge (rođendani, termini, najam) pod Usluge.', 'sidrene-cijene' ); ?></li>
				<li><?php esc_html_e( 'Klikni „Generiraj cjenike sada” i provjeri datoteke.', 'sidrene-cijene' ); ?></li>
				<li><?php esc_html_e( 'Objavi adresu mape s cjenicima na stranici, npr. u podnožju.', 'sidrene-cijene' ); ?></li>
			</ol>

			<h2><?php esc_html_e( 'Što nakon promjene cijene', 'sidrene-cijene' ); ?></h2>
			<p><?php esc_html_e( 'Ništa. Zaključana sidrena cijena ostaje nepromijenjena, a aktualna se povlači automatski. Ako neki proizvod treba drukčiju sidrenu cijenu, upiši je ručno u polju na proizvodu.', 'sidrene-cijene' ); ?></p>

			<h2><?php esc_html_e( 'Što plugin ne radi', 'sidrene-cijene' ); ?></h2>
			<ul style="list-style:disc;margin-left:20px">
				<li><?php esc_html_e( 'Ne postavlja barkodove — njih upisuješ u WooCommerceu (polje GTIN, UPC, EAN, ISBN).', 'sidrene-cijene' ); ?></li>
				<li><?php esc_html_e( 'Ne mijenja cijene u letcima, na plakatima i u oglasima — obveza isticanja vrijedi i ondje.', 'sidrene-cijene' ); ?></li>
				<li><?php esc_html_e( 'Ne zamjenjuje najnižu cijenu u 30 dana kod akcija — to je zasebna obveza iz Zakona o zaštiti potrošača.', 'sidrene-cijene' ); ?></li>
			</ul>

			<h2 style="margin-top:26px;color:#8a6d00"><?php esc_html_e( 'Odricanje od odgovornosti', 'sidrene-cijene' ); ?></h2>
			<p style="background:#fcf9e8;border-left:4px solid #dba617;padding:10px 14px">
				<?php esc_html_e( 'Ovaj plugin nije pravni savjet i ne jamči usklađenost s propisima. Odgovornost za točnost cijena i usklađenost poslovanja snosi korisnik. Plugin ne pokriva isticanje cijena izvan mrežne stranice niti zamjenjuje obvezu isticanja najniže cijene u 30 dana kod akcija. Autor nije povezan s nadležnim tijelima. Za tumačenje propisa obratite se knjigovođi ili pravnom savjetniku.', 'sidrene-cijene' ); ?>
			</p>

			<h2><?php esc_html_e( 'Shortcode za ručno unesene cijene', 'sidrene-cijene' ); ?></h2>
			<p><code>[sidrena_cijena iznos="240,00"]</code> — <?php esc_html_e( 'ispisuje redak sa sidrenom cijenom uz cijenu upisanu u stranicu.', 'sidrene-cijene' ); ?></p>
			<p><code>[sidrena_cijena id="123"]</code> — <?php esc_html_e( 'povlači sidrenu cijenu proizvoda po ID-u.', 'sidrene-cijene' ); ?></p>
		</div>
		<?php
	}
}
