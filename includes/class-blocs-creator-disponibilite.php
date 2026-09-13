<?php
/**
 * Quels blocs l'éditeur a le droit de proposer.
 *
 * WordPress arrive avec une centaine de blocs, chaque extension en ajoute les
 * siens, et l'inséreur finit par proposer trois façons de faire un bouton. Un
 * site qui a ses propres blocs n'a pas besoin des trois : il a besoin qu'on
 * puisse écarter ce qui ne sert pas, pour que ce qui sert se trouve.
 *
 * Deux règles tiennent cet écran :
 *
 *   1. ON RANGE EN NÉGATIF. C'est la liste de ce qu'on retire qui est
 *      enregistrée, jamais celle de ce qu'on garde. Un bloc qui arrive demain
 *      avec une nouvelle extension est donc disponible d'emblée, plutôt que
 *      d'être absent sans que personne ne comprenne pourquoi.
 *   2. ON NE SE COUPE PAS UN BRAS. Les blocs du plugin, et ceux qu'un bloc
 *      posé dans une page utilise, ne se décochent pas : l'écran le dit et la
 *      case reste grise.
 *
 * Retirer un bloc ne touche à aucune page : les blocs déjà posés continuent de
 * s'afficher et de se modifier. C'est l'inséreur qui ne les propose plus.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Filtrage des blocs proposés par l'éditeur.
 */
class Blocs_Creator_Disponibilite {

	/**
	 * Branche les hooks.
	 */
	public static function demarrer() {
		add_filter( 'allowed_block_types_all', array( __CLASS__, 'filtrer' ), 20, 2 );
	}

	/**
	 * Retire de l'éditeur les blocs mis de côté.
	 *
	 * @param bool|array              $permis   Liste permise, ou true pour « tous ».
	 * @param WP_Block_Editor_Context $contexte Contexte de l'éditeur.
	 * @return bool|array
	 */
	public static function filtrer( $permis, $contexte = null ) {
		$ecartes = self::ecartes();

		if ( empty( $ecartes ) ) {
			return $permis;
		}

		// Une autre extension a déjà réduit la liste : on retire des siens,
		// on n'y remet rien. Le plus restrictif gagne, c'est la seule façon
		// de composer sans se marcher dessus.
		if ( is_array( $permis ) ) {
			return array_values( array_diff( $permis, $ecartes ) );
		}

		$tous = array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() );

		return array_values( array_diff( $tous, $ecartes ) );
	}

	/**
	 * Retourne les noms des blocs mis de côté, ceux qu'on protège exclus.
	 *
	 * @return array<int, string>
	 */
	public static function ecartes() {
		$ecartes = (array) blocs_creator()->reglages->get( 'blocs_desactives' );

		if ( empty( $ecartes ) ) {
			return array();
		}

		return array_values( array_diff( $ecartes, self::proteges() ) );
	}

	/**
	 * Retourne les blocs qu'on refuse de retirer.
	 *
	 * Ceux du plugin, d'abord : les retirer reviendrait à désinstaller le
	 * plugin par une case à cocher. Et les enfants d'un bloc conteneur
	 * ensuite : un bloc « Cartes » sans son bloc « Carte » est un bloc vide
	 * qu'on ne peut plus remplir.
	 *
	 * @return array<int, string>
	 */
	public static function proteges() {
		$proteges = array();

		foreach ( blocs_creator()->registre->tous() as $bloc ) {
			$proteges[] = (string) $bloc['nom'];
		}

		foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $nom => $type ) {
			if ( ! empty( $type->parent ) || ! empty( $type->ancestor ) ) {
				$proteges[] = (string) $nom;
			}
		}

		/**
		 * Filtre les blocs qu'on refuse de retirer de l'éditeur.
		 *
		 * @param array $proteges Noms de blocs.
		 */
		return array_values( array_unique( apply_filters( 'blocs_creator_blocs_proteges', $proteges ) ) );
	}

	/* ------------------------------------------------------------------ *
	 * Inventaire
	 * ------------------------------------------------------------------ */

	/**
	 * Retourne tous les blocs enregistrés, groupés par provenance.
	 *
	 * La provenance se lit dans l'espace de noms : `core/` est WordPress,
	 * `woocommerce/` est WooCommerce. Ce n'est pas une déduction hasardeuse —
	 * c'est la convention que Gutenberg impose à tout le monde, et c'est la
	 * seule information que le registre des blocs porte réellement.
	 *
	 * @return array<string, array{titre:string, blocs:array<int, array>}>
	 */
	public static function inventaire() {
		$groupes    = array();
		$proteges   = self::proteges();
		$ecartes    = (array) blocs_creator()->reglages->get( 'blocs_desactives' );
		$enregistres = WP_Block_Type_Registry::get_instance()->get_all_registered();
		$usages     = Blocs_Creator_Usage::compter_plusieurs( array_keys( $enregistres ) );

		foreach ( $enregistres as $nom => $type ) {
			$nom    = (string) $nom;
			$espace = explode( '/', $nom )[0];

			if ( ! isset( $groupes[ $espace ] ) ) {
				$groupes[ $espace ] = array(
					'titre' => self::titre_espace( $espace ),
					'blocs' => array(),
				);
			}

			$groupes[ $espace ]['blocs'][] = array(
				'nom'         => $nom,
				'titre'       => (string) ( $type->title ? $type->title : $nom ),
				'description' => (string) $type->description,
				'protege'     => in_array( $nom, $proteges, true ),
				'ecarte'      => in_array( $nom, $ecartes, true ),
				'usage'       => (int) ( $usages[ $nom ] ?? 0 ),
			);
		}

		foreach ( $groupes as &$groupe ) {
			usort(
				$groupe['blocs'],
				static function ( $a, $b ) {
					return strcasecmp( $a['titre'], $b['titre'] );
				}
			);
		}

		unset( $groupe );

		// WordPress d'abord — c'est le gros du volume, et c'est là qu'on vient
		// faire le ménage —, les nôtres ensuite, le reste par ordre d'espace.
		uksort(
			$groupes,
			static function ( $a, $b ) {
				$rang = static function ( $espace ) {
					if ( 'core' === $espace ) {
						return 0;
					}

					return $espace === blocs_creator()->reglages->get( 'espace' ) ? 1 : 2;
				};

				$ecart = $rang( $a ) <=> $rang( $b );

				return 0 !== $ecart ? $ecart : strcasecmp( $a, $b );
			}
		);

		return $groupes;
	}

	/**
	 * Rend lisible un espace de noms de bloc.
	 *
	 * @param string $espace L'espace de noms.
	 * @return string
	 */
	private static function titre_espace( $espace ) {
		if ( 'core' === $espace ) {
			return __( 'WordPress', 'blocs-creator' );
		}

		foreach ( blocs_creator()->packs() as $pack ) {
			if ( sanitize_title( $pack['nom'] ) === $espace ) {
				return sprintf(
					/* translators: %s: nom du pack. */
					__( 'Pack %s', 'blocs-creator' ),
					$pack['nom']
				);
			}
		}

		if ( $espace === blocs_creator()->reglages->get( 'espace' ) ) {
			return __( 'Vos blocs', 'blocs-creator' );
		}

		return ucfirst( str_replace( '-', ' ', $espace ) );
	}
}
