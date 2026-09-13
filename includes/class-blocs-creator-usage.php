<?php
/**
 * Où un bloc est-il utilisé ?
 *
 * La question se pose au moment de renommer ou de supprimer un bloc. Y
 * répondre évite la mauvaise surprise classique : un identifiant changé, et
 * quatre pages qui affichent « Ce bloc contient du contenu inattendu ».
 *
 * La recherche est un LIKE sur le contenu des publications. C'est grossier,
 * mais c'est exact : un bloc dans une page y laisse toujours son commentaire
 * `<!-- wp:espace/slug `, espace compris — et c'est cet espace qui fait toute
 * la différence. Sans lui, « mes-blocs/carte » compterait aussi les « cartes »
 * et les « carte-libre ». Le résultat est mis en cache : une liste de blocs ne
 * doit pas coûter une requête par ligne à chaque affichage.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Comptage et localisation des utilisations d'un bloc.
 */
class Blocs_Creator_Usage {

	/**
	 * Préfixe des transients.
	 */
	const CACHE = 'bc_usage_';

	/**
	 * Durée du cache, en secondes.
	 */
	const DUREE = 6 * HOUR_IN_SECONDS;

	/**
	 * Retourne le motif à chercher dans le contenu d'une publication.
	 *
	 * L'espace final n'est pas décoratif : le sérialiseur de Gutenberg écrit
	 * toujours `<!-- wp:nom ` — avec attributs, sans attributs, ouvrant ou
	 * auto-fermant. C'est lui qui empêche « mes-blocs/carte » de compter les
	 * « mes-blocs/cartes ».
	 *
	 * @param string $nom Nom complet du bloc.
	 * @return string
	 */
	private static function aiguille( $nom ) {
		return '<!-- wp:' . $nom . ' ';
	}

	/**
	 * Compte d'un coup les publications qui portent chacun de ces blocs.
	 *
	 * L'écran de disponibilité pose la question pour cent vingt blocs à la
	 * fois. Cent vingt requêtes LIKE pour afficher une page de réglages, ce
	 * n'est pas raisonnable : on lit une fois les contenus qui portent des
	 * blocs, et on compte en PHP. Le résultat tient dans un seul cache.
	 *
	 * @param array<int, string> $noms Noms complets de blocs.
	 * @return array<string, int> Nombre de publications, par nom.
	 */
	public static function compter_plusieurs( $noms ) {
		$noms     = array_values( array_unique( array_map( 'strval', (array) $noms ) ) );
		$comptes  = array_fill_keys( $noms, 0 );
		$cle      = self::CACHE . 'lot_' . md5( implode( '|', $noms ) );
		$cache    = get_transient( $cle );

		if ( is_array( $cache ) ) {
			return array_merge( $comptes, $cache );
		}

		global $wpdb;

		$contenus = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT post_content FROM {$wpdb->posts}
			 WHERE post_content LIKE '%<!-- wp:%'
			   AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
			 LIMIT 5000"
		);

		foreach ( (array) $contenus as $contenu ) {
			foreach ( $noms as $nom ) {
				if ( str_contains( (string) $contenu, self::aiguille( $nom ) ) ) {
					++$comptes[ $nom ];
				}
			}
		}

		set_transient( $cle, $comptes, self::DUREE );

		return $comptes;
	}

	/**
	 * Compte les publications qui portent ce bloc.
	 *
	 * @param string $nom Nom complet du bloc.
	 * @return int
	 */
	public static function compter( $nom ) {
		$cle    = self::CACHE . md5( $nom );
		$cache  = get_transient( $cle );

		if ( false !== $cache ) {
			return (int) $cache;
		}

		global $wpdb;

		$aiguille = '%' . $wpdb->esc_like( self::aiguille( $nom ) ) . '%';

		$nombre = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts}
				 WHERE post_content LIKE %s
				   AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )",
				$aiguille
			)
		);

		set_transient( $cle, $nombre, self::DUREE );

		return $nombre;
	}

	/**
	 * Retourne les publications qui portent ce bloc.
	 *
	 * @param string $nom    Nom complet du bloc.
	 * @param int    $limite Nombre maximum de résultats.
	 * @return array<int, WP_Post>
	 */
	public static function publications( $nom, $limite = 50 ) {
		global $wpdb;

		$aiguille = '%' . $wpdb->esc_like( self::aiguille( $nom ) ) . '%';

		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_content LIKE %s
				   AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
				 ORDER BY post_modified DESC
				 LIMIT %d",
				$aiguille,
				max( 1, (int) $limite )
			)
		);

		$publications = array();

		foreach ( (array) $ids as $id ) {
			$post = get_post( (int) $id );

			if ( $post instanceof WP_Post ) {
				$publications[] = $post;
			}
		}

		return $publications;
	}

	/**
	 * Vide le cache des comptages.
	 *
	 * Appelé à chaque enregistrement de définition et à chaque activation :
	 * un compteur faux est pire qu'un compteur absent.
	 */
	public static function vider_cache() {
		global $wpdb;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				 WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::CACHE ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . self::CACHE ) . '%'
			)
		);

		// Les transients supprimés à la main restent dans le cache objet tant
		// qu'on ne le lui dit pas. La fonction est récente et facultative :
		// sans elle, le cache expire tout seul au bout de six heures.
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'options' );
		}
	}
}
