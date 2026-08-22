<?php
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
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_random_grid_request' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'exclude' => array(
						'required'          => false,
						'default'           => array(),
						'sanitize_callback' => function ( $param ) {
							return array_values(
								array_filter(
									array_map( 'absint', (array) $param )
								)
							);
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

		register_rest_route(
			'yards-archive/v1',
			'/archive',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_archive_request' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'search' => array(
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'tag' => array(
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'category' => array(
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'author' => array(
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'page' => array(
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'required'          => false,
						'default'           => 30,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public static function handle_random_grid_request( WP_REST_Request $request ) {
		$exclude = $request->get_param( 'exclude' );
		$count   = max( 1, min( 20, (int) $request->get_param( 'count' ) ) );

		$all_ids       = Yards_Archive_Cache::get_published_ids();
		$available_ids = array_values( array_diff( $all_ids, $exclude ) );

		if ( empty( $available_ids ) ) {
			return new WP_REST_Response( array(), 200 );
		}

		$pick_count  = min( $count, count( $available_ids) );
		$random_keys = (array) array_rand( $available_ids, $pick_count );
		$random_ids  = array();

		foreach ( $random_keys as $key ) {
			$random_ids[] = $available_ids[ $key ];
		}

		$query = new WP_Query(
			array(
				'post_type'      => YARDS_ARCHIVE_CPT,
				'post_status'    => 'publish',
				'post__in'       => $random_ids,
				'orderby'        => 'post__in',
				'posts_per_page' => $pick_count,
				'no_found_rows'  => true,
			)
		);

		$results = array();

		foreach ( $query->posts as $post ) {
			$image_url = get_the_post_thumbnail_url( $post->ID, 'medium_large' );
			$category  = self::get_primary_category( $post->ID );

			$results[] = array(
				'id'             => $post->ID,
				'title'          => get_the_title( $post ),
				'image'          => $image_url ? $image_url : '',
				'link'           => get_permalink( $post ),
				'category_title' => $category['title'],
				'category_link'  => $category['link'],
			);
		}

		return new WP_REST_Response( $results, 200 );
	}

	public static function handle_archive_request( WP_REST_Request $request ) {
		$search   = trim( (string) $request->get_param( 'search' ) );
		$tag_id   = absint( $request->get_param( 'tag' ) );
		$cat_id   = absint( $request->get_param( 'category' ) );
		$author   = absint( $request->get_param( 'author' ) );
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page = max( 1, min( 50, absint( $request->get_param( 'per_page' ) ) ) );

		$args = array(
			'post_type'              => YARDS_ARCHIVE_CPT,
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => false,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => true,
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		if ( $author > 0 ) {
			$args['author'] = $author;
		}

		$tax_query = array();

		if ( $cat_id > 0 ) {
			$tax_query[] = array(
				'taxonomy' => 'category',
				'field'    => 'term_id',
				'terms'    => $cat_id,
			);
		}

		if ( $tag_id > 0 ) {
			$tax_query[] = array(
				'taxonomy' => 'post_tag',
				'field'    => 'term_id',
				'terms'    => $tag_id,
			);
		}

		if ( ! empty( $tax_query ) ) {
			$tax_query['relation'] = 'AND';
			$args['tax_query']     = $tax_query;
		}

		$query   = new WP_Query( $args );
		$results = array();

		foreach ( $query->posts as $post ) {
			$category   = self::get_primary_category( $post->ID );
			$image_url  = get_the_post_thumbnail_url( $post->ID, 'medium_large' );
			$author_id  = (int) $post->post_author;
			$author_obj = get_userdata( $author_id );

			$results[] = array(
				'id'             => $post->ID,
				'title'          => get_the_title( $post ),
				'link'           => get_permalink( $post ),
				'image'          => $image_url ? $image_url : '',
				'author'         => array(
					'id'   => $author_id,
					'name' => $author_obj ? $author_obj->display_name : '',
				),
				'category'       => array(
					'id'    => $category['id'],
					'title' => $category['title'],
					'link'  => $category['link'],
				),
			);
		}

		return new WP_REST_Response(
			array(
				'items'      => $results,
				'pagination' => array(
					'page'     => $page,
					'per_page' => $per_page,
					'total'    => (int) $query->found_posts,
					'pages'    => (int) $query->max_num_pages,
				),
			),
			200
		);
	}

	private static function get_primary_category( $post_id ) {
		$categories = get_the_category( $post_id );

		if ( empty( $categories ) || is_wp_error( $categories ) ) {
			return array(
				'id'    => 0,
				'title' => '',
				'link'  => '',
			);
		}

		$category = $categories[0];
		$link     = get_category_link( $category->term_id );

		return array(
			'id'    => (int) $category->term_id,
			'title' => $category->name,
			'link'  => is_wp_error( $link ) ? '' : $link,
		);
	}
}
