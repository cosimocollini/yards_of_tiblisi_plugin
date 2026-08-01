<?php
/**
 * Registrazione del Custom Post Type "progetto".
 *
 * Usiamo i campi nativi di WP dove possibile:
 * - post_title       -> titolo
 * - post_content     -> descrizione lunga
 * - post_excerpt     -> descrizione breve
 * - featured image   -> immagine principale (thumbnail nativa, già ottimizzata)
 *
 * Niente campi custom per questi, per restare leggeri e compatibili
 * con cache/CDN/srcset automatici di WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yards_Archive_CPT {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	public static function register_post_type() {

		$labels = array(
			'name'               => __( 'Progetti', 'mio-archivio' ),
			'singular_name'      => __( 'Progetto', 'mio-archivio' ),
			'add_new_item'       => __( 'Aggiungi nuovo progetto', 'mio-archivio' ),
			'edit_item'          => __( 'Modifica progetto', 'mio-archivio' ),
			'all_items'          => __( 'Tutti i progetti', 'mio-archivio' ),
			'search_items'       => __( 'Cerca progetti', 'mio-archivio' ),
			'not_found'          => __( 'Nessun progetto trovato', 'mio-archivio' ),
			'menu_name'          => __( 'Progetti', 'mio-archivio' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => true,
			'has_archive'         => true,
			'show_in_rest'        => true, // necessario per Gutenberg e per query REST nativa
			'menu_icon'           => 'dashicons-format-image',
			'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
			'rewrite'             => array( 'slug' => 'progetti' ),
			'capability_type'     => 'post',
			'hierarchical'        => false,
			// Con 1000-1500 post: niente bisogno di custom capability o post_type non pubblico,
			// restiamo su query standard di WP che scalano bene fino a decine di migliaia di righe.
		);

		register_post_type( YARDS_ARCHIVE_CPT, $args );
	}
}
