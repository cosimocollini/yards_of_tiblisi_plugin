<?php
/**
 * Campi custom di testo per il CPT "progetto".
 *
 * Qui usiamo una meta box nativa (niente ACF) per restare a zero dipendenze.
 * Se preferisci un'interfaccia admin più ricca puoi sostituire questo file
 * con la configurazione equivalente in ACF: la logica di salvataggio/lettura
 * dei meta resta concettualmente la stessa.
 *
 * Aggiungi/togli campi modificando l'array self::FIELDS qui sotto.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yards_Archive_Meta_Fields {

	/**
	 * Definisci qui i tuoi campi custom: key => etichetta.
	 * Esempi tipici per un archivio progetti: anno, cliente, location, strumenti usati.
	 */
	const FIELDS = array(
		'mio_anno'       => 'Anno',
		'mio_cliente'    => 'Cliente',
		'mio_location'   => 'Location',
		'mio_strumenti'  => 'Strumenti usati',
	);

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . YARDS_ARCHIVE_CPT, array( __CLASS__, 'save_meta' ) );
		add_action( 'init', array( __CLASS__, 'register_meta_for_rest' ) );
	}

	public static function add_meta_box() {
		add_meta_box(
			'yards_archive_meta_box',
			__( 'Dettagli progetto', 'yards-archive' ),
			array( __CLASS__, 'render_meta_box' ),
			YARDS_ARCHIVE_CPT,
			'normal',
			'default'
		);
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'yards_archive_save_meta', 'yards_archive_meta_nonce' );

		foreach ( self::FIELDS as $key => $label ) {
			$value = get_post_meta( $post->ID, $key, true );
			?>
			<p>
				<label for="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><br>
				<input
					type="text"
					id="<?php echo esc_attr( $key ); ?>"
					name="<?php echo esc_attr( $key ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					style="width:100%;"
				/>
			</p>
			<?php
		}
	}

	public static function save_meta( $post_id ) {

		if ( ! isset( $_POST['yards_archive_meta_nonce'] ) ||
			! wp_verify_nonce( $_POST['yards_archive_meta_nonce'], 'yards_archive_save_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( self::FIELDS as $key => $label ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}
	}

	/**
	 * Espone i meta nella REST API (utile se in futuro vuoi leggerli
	 * via JS o in app esterne, e per coerenza con show_in_rest del CPT).
	 */
	public static function register_meta_for_rest() {
		foreach ( self::FIELDS as $key => $label ) {
			register_post_meta(
				YARDS_ARCHIVE_CPT,
				$key,
				array(
					'type'         => 'string',
					'single'       => true,
					'show_in_rest' => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback' => function() {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}
}
