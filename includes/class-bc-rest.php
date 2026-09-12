<?php
/**
 * Les routes REST de l'éditeur.
 *
 * L'aperçu d'un bloc généré passe par la route native de WordPress
 * (`/wp/v2/block-renderer/…`) : c'est le même code que le site, il ne peut
 * donc pas en diverger. Restent deux besoins que le cœur ne couvre pas bien :
 *
 *   - chercher une publication dans un type de contenu qui n'est pas exposé en
 *     REST — le cas de beaucoup de types déclarés à la main ;
 *   - lister les termes d'une taxonomie, pour la même raison.
 *
 * Les deux routes sont réservées à qui peut éditer, et ne renvoient que le
 * strict nécessaire : un identifiant, un libellé.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Routes REST du plugin.
 */
class BC_Rest {

	/**
	 * Espace de noms des routes.
	 */
	const NAMESPACE = 'blocs-creator/v1';

	/**
	 * Branche les hooks.
	 */
	public static function demarrer() {
		add_action( 'rest_api_init', array( __CLASS__, 'declarer' ) );
	}

	/**
	 * Déclare les routes.
	 */
	public static function declarer() {
		register_rest_route(
			self::NAMESPACE,
			'/publications',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'publications' ),
				'permission_callback' => array( __CLASS__, 'peut_editer' ),
				'args'                => array(
					'types'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'recherche' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'inclure'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'nombre'    => array(
						'type'    => 'integer',
						'default' => 30,
						'minimum' => 1,
						'maximum' => 100,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/termes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'termes' ),
				'permission_callback' => array( __CLASS__, 'peut_editer' ),
				'args'                => array(
					'taxonomie' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'recherche' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Seuls ceux qui écrivent des pages ont besoin de ces routes.
	 *
	 * @return bool
	 */
	public static function peut_editer() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Retourne des publications, filtrées par type et par recherche.
	 *
	 * @param WP_REST_Request $requete La requête.
	 * @return WP_REST_Response
	 */
	public static function publications( $requete ) {
		$types = array_filter( array_map( 'sanitize_key', explode( ',', (string) $requete['types'] ) ) );

		if ( empty( $types ) ) {
			$types = array_values( get_post_types( array( 'public' => true ), 'names' ) );
			$types = array_diff( $types, array( 'attachment' ) );
		}

		$args = array(
			'post_type'              => $types,
			'post_status'            => array( 'publish', 'private', 'draft' ),
			'posts_per_page'         => (int) $requete['nombre'],
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$inclure = array_filter( array_map( 'absint', explode( ',', (string) $requete['inclure'] ) ) );

		// Les identifiants déjà choisis doivent revenir même s'ils ne
		// correspondent pas à la recherche en cours : sans quoi le champ
		// afficherait « publication inconnue » dès qu'on tape une lettre.
		if ( ! empty( $inclure ) ) {
			$args['post__in'] = $inclure;
			$args['orderby']  = 'post__in';
		} elseif ( '' !== $requete['recherche'] ) {
			$args['s'] = (string) $requete['recherche'];
		}

		$resultats = array();

		foreach ( ( new WP_Query( $args ) )->posts as $post ) {
			$type = get_post_type_object( $post->post_type );

			$resultats[] = array(
				'id'     => $post->ID,
				'titre'  => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				'type'   => $post->post_type,
				'statut' => $post->post_status,
				'label'  => $type ? $type->labels->singular_name : $post->post_type,
			);
		}

		return rest_ensure_response( $resultats );
	}

	/**
	 * Retourne les termes d'une taxonomie.
	 *
	 * @param WP_REST_Request $requete La requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function termes( $requete ) {
		$taxonomie = (string) $requete['taxonomie'];

		if ( ! taxonomy_exists( $taxonomie ) ) {
			return new WP_Error(
				'bc_taxonomie_inconnue',
				__( 'Cette taxonomie n\'existe pas.', 'blocs-creator' ),
				array( 'status' => 404 )
			);
		}

		$termes = get_terms(
			array(
				'taxonomy'   => $taxonomie,
				'hide_empty' => false,
				'number'     => 200,
				'search'     => (string) $requete['recherche'],
			)
		);

		if ( is_wp_error( $termes ) ) {
			return $termes;
		}

		$resultats = array();

		foreach ( $termes as $terme ) {
			$resultats[] = array(
				'id'    => $terme->term_id,
				'titre' => html_entity_decode( $terme->name, ENT_QUOTES, 'UTF-8' ),
				'compte' => (int) $terme->count,
			);
		}

		return rest_ensure_response( $resultats );
	}
}
