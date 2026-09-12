<?php
/**
 * Le rendu : appeler le gabarit, et se retirer.
 *
 * Tout bloc généré passe par `rendre()`. Le travail y tient en quatre gestes :
 * retrouver la définition, nettoyer et préparer les valeurs, empiler le
 * contexte, inclure le fichier. Le gabarit écrit ensuite ce qu'il veut.
 *
 * Le contexte est une pile, et non une simple variable, parce qu'un bloc peut
 * en contenir un autre : quand un gabarit fait `echo bc_contenu()`, le rendu
 * des blocs imbriqués s'exécute à l'intérieur du sien. Sans pile, l'enfant
 * écraserait les champs du parent, qui reprendrait la main avec les mauvais.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rendu serveur des blocs générés.
 */
class BC_Rendu {

	/**
	 * Pile des contextes de rendu.
	 *
	 * @var array<int, array>
	 */
	private static $pile = array();

	/**
	 * Branche les hooks.
	 */
	public static function demarrer() {
		// Rien à brancher pour l'instant : le rendu est appelé par Gutenberg
		// via le render_callback posé au moment de l'enregistrement.
	}

	/**
	 * Rend un bloc généré.
	 *
	 * @param array    $attributes Attributs du bloc.
	 * @param string   $content    Blocs imbriqués, déjà rendus.
	 * @param WP_Block $block      Instance du bloc.
	 * @return string
	 */
	public static function rendre( $attributes, $content = '', $block = null ) {
		$nom = $block instanceof WP_Block ? $block->name : '';

		$definition = blocs_creator()->registre->definition( $nom );

		if ( null === $definition ) {
			return '';
		}

		$gabarit = BC_Gabarits::chemin_rendu( $definition );

		if ( ! file_exists( $gabarit ) ) {
			return self::rappel(
				sprintf(
					/* translators: %s: nom du bloc. */
					__( 'Le bloc « %s » n\'a pas de fichier de rendu.', 'blocs-creator' ),
					$definition['titre']
				)
			);
		}

		$champs = self::preparer_champs( $definition, (array) $attributes );

		self::$pile[] = array(
			'definition' => $definition,
			'champs'     => $champs,
			'attributs'  => (array) $attributes,
			'contenu'    => (string) $content,
			'bloc'       => $block,
		);

		ob_start();

		/*
		 * Le gabarit est inclus dans une fonction anonyme : il ne voit que les
		 * variables qu'on lui passe, et rien de la mécanique du plugin. Leurs
		 * noms font partie du contrat — ce sont ceux que WordPress emploie
		 * pour les blocs à `render` ($attributes, $content, $block), plus
		 * $champs et $bloc, qui n'ont d'équivalent nulle part ailleurs.
		 */
		( static function ( $bc_gabarit, $attributes, $content, $block, $champs, $bloc ) {
			include $bc_gabarit;
		} )(
			$gabarit,
			(array) $attributes,
			(string) $content,
			$block,
			$champs,
			$definition
		);

		array_pop( self::$pile );

		return (string) ob_get_clean();
	}

	/**
	 * Nettoie puis prépare les valeurs d'un bloc.
	 *
	 * @param array $definition La définition.
	 * @param array $attributs  Attributs bruts.
	 * @return array<string, mixed>
	 */
	public static function preparer_champs( $definition, $attributs ) {
		$champs = array();

		foreach ( $definition['champs'] as $champ ) {
			if ( ! BC_Champs::porte_valeur( $champ['type'] ) ) {
				continue;
			}

			$cle    = $champ['cle'];
			$valeur = array_key_exists( $cle, $attributs ) ? $attributs[ $cle ] : null;
			$valeur = BC_Champs::assainir_valeur( $champ, $valeur );

			$champs[ $cle ] = BC_Champs::preparer( $champ, $valeur );
		}

		/**
		 * Filtre les valeurs passées au gabarit.
		 *
		 * @param array $champs     Valeurs préparées, par clé.
		 * @param array $definition La définition.
		 * @param array $attributs  Attributs bruts.
		 */
		return apply_filters( 'blocs_creator_champs', $champs, $definition, $attributs );
	}

	/* ------------------------------------------------------------------ *
	 * Contexte courant
	 * ------------------------------------------------------------------ */

	/**
	 * Retourne le contexte de rendu courant.
	 *
	 * @return array|null
	 */
	public static function courant() {
		$taille = count( self::$pile );

		return $taille > 0 ? self::$pile[ $taille - 1 ] : null;
	}

	/**
	 * Retourne la valeur préparée d'un champ.
	 *
	 * @param string $cle    Clé du champ.
	 * @param mixed  $defaut Valeur de repli.
	 * @return mixed
	 */
	public static function champ( $cle, $defaut = null ) {
		$contexte = self::courant();

		if ( null === $contexte ) {
			return $defaut;
		}

		return array_key_exists( $cle, $contexte['champs'] ) ? $contexte['champs'][ $cle ] : $defaut;
	}

	/**
	 * Retourne la valeur brute d'un champ, telle qu'enregistrée.
	 *
	 * @param string $cle    Clé du champ.
	 * @param mixed  $defaut Valeur de repli.
	 * @return mixed
	 */
	public static function brut( $cle, $defaut = null ) {
		$contexte = self::courant();

		if ( null === $contexte ) {
			return $defaut;
		}

		return array_key_exists( $cle, $contexte['attributs'] ) ? $contexte['attributs'][ $cle ] : $defaut;
	}

	/**
	 * Retourne le contenu des blocs imbriqués.
	 *
	 * @return string
	 */
	public static function contenu() {
		$contexte = self::courant();

		return null === $contexte ? '' : $contexte['contenu'];
	}

	/**
	 * Retourne la définition du bloc en cours de rendu.
	 *
	 * @return array|null
	 */
	public static function definition() {
		$contexte = self::courant();

		return null === $contexte ? null : $contexte['definition'];
	}

	/**
	 * Retourne un rappel visible des seuls utilisateurs qui peuvent corriger.
	 *
	 * Un bloc à moitié rempli ne doit ni disparaître en silence — la rédaction
	 * croirait l'avoir supprimé — ni s'afficher cassé devant le visiteur.
	 *
	 * @param string $message Ce qui manque.
	 * @return string
	 */
	public static function rappel( $message ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}

		return sprintf(
			'<p class="bc-rappel">%s</p>',
			esc_html( $message )
		);
	}
}
