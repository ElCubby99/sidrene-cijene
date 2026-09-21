<?php
/**
 * Deinstalacija.
 *
 * Podaci se brišu samo ako je ta opcija izričito uključena u postavkama.
 *
 * @package SidreneCijene
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$sc_postavke = get_option( 'sc_postavke', array() );

if ( ! is_array( $sc_postavke ) || ( $sc_postavke['brisi_pri_uklanjanju'] ?? 'ne' ) !== 'da' ) {
	return;
}

global $wpdb;

// Meta podaci proizvoda i varijacija.
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_sidrena_cijena' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_sc_preskoceno' ) );  // phpcs:ignore WordPress.DB.SlowDBQuery

// Usluge.
$sc_usluge = get_posts(
	array(
		'post_type'   => 'sc_usluga',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);
foreach ( $sc_usluge as $sc_id ) {
	wp_delete_post( $sc_id, true );
}

// Opcije.
delete_option( 'sc_postavke' );
delete_option( 'sc_verzija' );
delete_option( 'sc_zadnje_generiranje_proizvodi' );
delete_option( 'sc_zadnje_generiranje_usluge' );

// Zakazani posao.
wp_clear_scheduled_hook( 'sc_dnevno_osvjezenje' );

// Objavljeni cjenici.
$sc_upload = wp_upload_dir();
$sc_mapa   = trailingslashit( $sc_upload['basedir'] ) . 'sidrene-cijene';
if ( is_dir( $sc_mapa ) ) {
	foreach ( (array) glob( trailingslashit( $sc_mapa ) . '*' ) as $sc_datoteka ) {
		if ( is_file( $sc_datoteka ) ) {
			wp_delete_file( $sc_datoteka );
		}
	}
	@rmdir( $sc_mapa ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}
