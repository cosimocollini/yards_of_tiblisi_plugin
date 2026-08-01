<?php
/**
 * Gestisce la cache dell'elenco di ID dei progetti pubblicati.
 *
 * Perché serve: con 1000-1500 post, fare ORDER BY RAND() in SQL ad ogni
 * richiesta della griglia significa una scansione + filesort completo
 * della tabella ad ogni refresh. Invece:
 *
 * 1. Teniamo in cache (transient, idealmente su object cache persistente
 *    come Redis/Memcached se il tuo hosting lo supporta) il semplice
 *    array di ID pubblicati.
 * 2. La cache si invalida solo quando un progetto viene pubblicato,
 *    aggiornato o cestinato (hook su transition_post_status).
 * 3. Il sorteggio random degli ID avviene in PHP (array_rand), non in SQL.
 * 4. La query finale dei post usa WHERE ID IN (...) che è velocissima
 *    anche su tabelle con decine di migliaia di righe.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yards_Archive_Cache {

	const TRANSIENT_KEY = 'yards_archive_published_ids';
	const CACHE_TTL      = DAY_IN_SECONDS; // si invalida comunque su ogni save/trash

	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'maybe_invalidate' ), 10, 3 );
	}

	/**
	 * Invalida la cache quando un progetto cambia stato (pubblicato, cestinato, ecc.).
	 */
	public static function maybe_invalidate( $new_status, $old_status, $post ) {
		if ( $post->post_type !== YARDS_ARCHIVE_CPT ) {
			return;
		}
		if ( $new_status === 'publish' || $old_status === 'publish' ) {
			delete_transient( self::TRANSIENT_KEY );
		}
	}

	/**
	 * Ritorna l'array di ID pubblicati, rigenerandolo se necessario.
	 *
	 * @return int[]
	 */
	public static function get_published_ids() {

		$ids = get_transient( self::TRANSIENT_KEY );

		if ( false !== $ids && is_array( $ids ) && ! empty( $ids ) ) {
			return $ids;
		}

		// Query leggera: solo gli ID, niente JOIN su postmeta/termini.
		$ids = get_posts(
			array(
				'post_type'      => YARDS_ARCHIVE_CPT,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true, // evita la SELECT COUNT inutile
			)
		);

		set_transient( self::TRANSIENT_KEY, $ids, self::CACHE_TTL );

		return $ids;
	}
}
