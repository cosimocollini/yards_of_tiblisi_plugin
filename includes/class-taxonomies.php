<?php
/**
 * Taxonomy custom dedicate al CPT "progetto".
 * Separate da category/post_tag standard per restare semanticamente puliti
 * e per non mischiare l'archivio con eventuali articoli di blog.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yards_Archive_Taxonomies {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ) );
	}

	public static function register_taxonomies() {

		// Taxonomy "Categoria progetto" (gerarchica, come le category standard).
		register_taxonomy(
			'progetto_categoria',
			YARDS_ARCHIVE_CPT,
			array(
				'labels'            => array(
					'name'          => __( 'Categorie progetto', 'yards-archive' ),
					'singular_name' => __( 'Categoria progetto', 'yards-archive' ),
				),
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'public'            => true,
				'rewrite'           => array( 'slug' => 'progetti-categoria' ),
			)
		);

		// Taxonomy "Tag progetto" (non gerarchica, come i tag standard).
		register_taxonomy(
			'progetto_tag',
			YARDS_ARCHIVE_CPT,
			array(
				'labels'            => array(
					'name'          => __( 'Tag progetto', 'yards-archive' ),
					'singular_name' => __( 'Tag progetto', 'yards-archive' ),
				),
				'hierarchical'      => false,
				'show_in_rest'      => true,
				'public'            => true,
				'rewrite'           => array( 'slug' => 'progetti-tag' ),
			)
		);
	}
}
