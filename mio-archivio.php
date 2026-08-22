<?php
/**
 * Plugin Name: Yards Archive
 * Description: Archivio di progetti/immagini con CPT dedicato, taxonomy custom e griglia random con lock/unlock.
 * Version: 1.0.0
 * Author: Cosimo
 * Text Domain: yards-archive
 *
 * Struttura:
 * - includes/class-cpt.php          -> registrazione Custom Post Type "progetto"
 * - includes/class-taxonomies.php   -> taxonomy custom (categoria/tag dedicati)
 * - includes/class-meta-fields.php  -> campi custom di testo (meta box)
 * - includes/class-cache.php        -> cache degli ID pubblicati (transient/object cache)
 * - includes/class-rest-api.php     -> endpoint REST per la griglia random
 * - assets/grid.js + grid.css       -> frontend della griglia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Niente accesso diretto al file.
}

define( 'YARDS_ARCHIVE_VERSION', '1.0.0' );
define( 'YARDS_ARCHIVE_PATH', plugin_dir_path( __FILE__ ) );
define( 'YARDS_ARCHIVE_URL', plugin_dir_url( __FILE__ ) );
// define( 'YARDS_ARCHIVE_CPT', 'progetto' ); // slug del custom post type, cambialo se vuoi un nome diverso
define( 'YARDS_ARCHIVE_CPT', 'post' ); 

// Caricamento classi.
// require_once YARDS_ARCHIVE_PATH . 'includes/class-cpt.php';
// require_once YARDS_ARCHIVE_PATH . 'includes/class-taxonomies.php';
// require_once YARDS_ARCHIVE_PATH . 'includes/class-meta-fields.php';
require_once YARDS_ARCHIVE_PATH . 'includes/class-cache.php';
require_once YARDS_ARCHIVE_PATH . 'includes/class-rest-api.php';

/**
 * Inizializza tutti i moduli del plugin.
 */
function yards_archive_init() {
	// Yards_Archive_CPT::init();
	// Yards_Archive_Taxonomies::init();
	// Yards_Archive_Meta_Fields::init();
	Yards_Archive_Cache::init();
	Yards_Archive_REST_API::init();
}
add_action( 'plugins_loaded', 'yards_archive_init' );

/**
 * Carica gli asset (JS/CSS) della griglia SOLO dove serve.
 * Usa lo shortcode [yards_archive_grid] per stampare il container:
 * lo script si carica solo se quello shortcode è presente nel contenuto,
 * per non appesantire pagine che non lo usano.
 */
function yards_archive_enqueue_assets() {
	global $post;

	if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'yards_archive_grid' ) ) {
		return;
	}

	wp_enqueue_style(
		'yards-archive-grid',
		YARDS_ARCHIVE_URL . 'assets/grid.css',
		array(),
		YARDS_ARCHIVE_VERSION
	);

	wp_enqueue_script(
		'yards-archive-grid',
		YARDS_ARCHIVE_URL . 'assets/grid.js',
		array(),
		YARDS_ARCHIVE_VERSION,
		true // carica in footer
	);

	// Passa al JS l'URL della REST API e altre config, senza hardcodare nulla nel JS.
	wp_localize_script(
		'yards-archive-grid',
		'yardsArchiveConfig',
		array(
			'restUrl'      => esc_url_raw( rest_url( 'yards-archive/v1/random-grid' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'gridSize'     => 8, // 2x4 = 8 celle
			'refreshMs'    => 4000, // ogni quanto rimescola le celle non bloccate
		)
	);
}
add_action( 'wp_enqueue_scripts', 'yards_archive_enqueue_assets' );

/**
 * Shortcode che stampa il container della griglia.
 * Uso: [yards_archive_grid]
 */
function yards_archive_shortcode_grid() {
	return '<div id="yards-archive-grid" class="yards-archive-grid" aria-live="polite"></div>';
}
add_shortcode( 'yards_archive_grid', 'yards_archive_shortcode_grid' );

/**
 * Flush dei permalink all'attivazione/disattivazione, necessario perché
 * registriamo un CPT con il suo rewrite.
 */
function yards_archive_activate() {
	require_once YARDS_ARCHIVE_PATH . 'includes/class-cpt.php';
	require_once YARDS_ARCHIVE_PATH . 'includes/class-taxonomies.php';
	Yards_Archive_CPT::register_post_type();
	Yards_Archive_Taxonomies::register_taxonomies();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'yards_archive_activate' );

function yards_archive_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'yards_archive_deactivate' );
