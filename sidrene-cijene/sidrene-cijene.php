<?php
/**
 * Plugin Name:       Sidrene cijene
 * Plugin URI:        https://geekgarden.hr/
 * Description:       Isticanje sidrene (dodatne) cijene i objava cjenika u XML/CSV formatu, prema Odlukama iz NN 101/2026 koje se primjenjuju od 01.10.2026.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Geek Garden d.o.o.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sidrene-cijene
 * Domain Path:       /languages
 * WC requires at least: 7.0
 *
 * @package SidreneCijene
 */

defined( 'ABSPATH' ) || exit;

define( 'SC_VERSION', '1.0.0' );
define( 'SC_FILE', __FILE__ );
define( 'SC_PATH', plugin_dir_path( __FILE__ ) );
define( 'SC_URL', plugin_dir_url( __FILE__ ) );

/** Referentni datumi iz Odluke. */
define( 'SC_DATUM_OPCI', '2026-09-10' );   // Novoobuhvaćeni proizvodi i usluge.
define( 'SC_DATUM_RANIJI', '2025-05-02' ); // Hrana, piće, kozmetika, sredstva za čišćenje, toaletne potrepštine, kućanstvo.

require_once SC_PATH . 'includes/class-sc-postavke.php';
require_once SC_PATH . 'includes/class-sc-cijena.php';
require_once SC_PATH . 'includes/class-sc-prikaz.php';
require_once SC_PATH . 'includes/class-sc-zakljucavanje.php';
require_once SC_PATH . 'includes/class-sc-usluge.php';
require_once SC_PATH . 'includes/class-sc-cjenik.php';
require_once SC_PATH . 'includes/class-sc-pregled.php';
require_once SC_PATH . 'includes/class-sc-admin.php';

/**
 * Glavna klasa.
 */
final class Sidrene_Cijene {

	/** @var Sidrene_Cijene|null */
	private static $instanca = null;

	public static function instanca(): Sidrene_Cijene {
		if ( null === self::$instanca ) {
			self::$instanca = new self();
		}
		return self::$instanca;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'pokreni' ) );
		add_action( 'before_woocommerce_init', array( $this, 'hpos_kompatibilnost' ) );
	}

	/**
	 * Deklaracija kompatibilnosti s WooCommerce HPOS pohranom narudžbi.
	 */
	public function hpos_kompatibilnost(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				SC_FILE,
				true
			);
		}
	}

	public function pokreni(): void {
		load_plugin_textdomain( 'sidrene-cijene', false, dirname( plugin_basename( SC_FILE ) ) . '/languages' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'upozorenje_woocommerce' ) );
			return;
		}

		SC_Postavke::init();
		SC_Prikaz::init();
		SC_Zakljucavanje::init();
		SC_Usluge::init();
		SC_Cjenik::init();

		if ( is_admin() ) {
			SC_Admin::init();
		}
	}

	public function upozorenje_woocommerce(): void {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Plugin „Sidrene cijene” traži aktivan WooCommerce.', 'sidrene-cijene' );
		echo '</p></div>';
	}

	/** Aktivacija: mapa za cjenike, zaštita od izlistavanja, raspored. */
	public static function aktivacija(): void {
		SC_Cjenik::pripremi_mapu();
		SC_Cjenik::zakazi();
		add_option( 'sc_verzija', SC_VERSION );
	}

	/** Deaktivacija: ukloni zakazane poslove. Podaci ostaju. */
	public static function deaktivacija(): void {
		SC_Cjenik::odzakazi();
	}
}

register_activation_hook( SC_FILE, array( 'Sidrene_Cijene', 'aktivacija' ) );
register_deactivation_hook( SC_FILE, array( 'Sidrene_Cijene', 'deaktivacija' ) );

Sidrene_Cijene::instanca();
