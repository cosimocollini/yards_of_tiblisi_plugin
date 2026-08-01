<?php
/**
 * Endpoint REST: GET /wp-json/yards-archive/v1/random-grid
 *
 * Parametri accettati (query string):
 * - exclude[]   array di ID da escludere (le celle già "locked" in frontend)
 * - count       quante immagini servono (default 8, cioè le celle libere)
 *
 * Ritorna un array di oggetti { id, title, image, link } pronti da
 * disegnare lato JS, senza dover fare query SQL pesanti ad ogni chiamata.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yards_Archive_REST_API {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			'yards-archive/v1',
			'/random-grid',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_request' ),
				'permission_callback' => '__return_true', // endpoint pubblico, dati già pubblici
				'args'                => array(
					'exclude' => array(
						'required'          => false,
						'default'           => array(),
						'sanitize_callback' => function( $param ) {
							return array_map( 'absint', (array) $param );
						},
					),
					'count' => array(
						'required'          => false,
						'default'           => 8,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public static function handle_request( WP_REST_Request $request ) {

		$exclude = $request->get_param( 'exclude' );
		$count   = max( 1, min( 20, (int) $request->get_param( 'count' ) ) ); // hard cap di sicurezza

		// 1. Prendi l'elenco (cachato) di tutti gli ID pubblicati.
		$all_ids = Yards_Archive_Cache::get_published_ids();

		// 2. Togli quelli già bloccati in frontend.
		$available_ids = array_values( array_diff( $all_ids, $exclude ) );

		if ( empty( $available_ids ) ) {
			return new WP_REST_Response( array(), 200 );
		}

		// 3. Sorteggio random in PHP, niente ORDER BY RAND() in SQL.
		$pick_count = min( $count, count( $available_ids ) );
		$random_keys = (array) array_rand( $available_ids, $pick_count );
		$random_ids  = array();
		foreach ( $random_keys as $key ) {
			$random_ids[] = $available_ids[ $key ];
		}

		// 4. Query mirata solo sugli ID sorteggiati: WHERE ID IN (...).
		$query = new WP_Query(
			array(
				'post_type'      => YARDS_ARCHIVE_CPT,
				'post_status'    => 'publish',
				'post__in'       => $random_ids,
				'orderby'        => 'post__in', // mantiene l'ordine random scelto sopra
				'posts_per_page' => $pick_count,
				'no_found_rows'  => true,
			)
		);

		$results = array();

		foreach ( $query->posts as $post ) {
			$image_url = get_the_post_thumbnail_url( $post->ID, 'medium_large' );

			$results[] = array(
				'id'    => $post->ID,
				'title' => get_the_title( $post ),
				'image' => $image_url ? $image_url : '',
				'link'  => get_permalink( $post ),
			);
		}

		return new WP_REST_Response( $results, 200 );
	}
}
