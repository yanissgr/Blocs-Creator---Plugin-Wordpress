<?php
/**
 * Les définitions qui voyagent avec le thème.
 *
 * Les champs d'un bloc vivent en base ; son dessin, dans le thème. Un site
 * qu'on met en ligne par FTP, qu'on versionne avec Git ou qu'on fait écrire
 * par une IA a donc besoin que les définitions vivent AUSSI dans le thème,
 * dans un fichier qu'on lit, qu'on compare et qu'on recopie :
 *
 *     wp-content/themes/<thème>/blocs-creator/definitions.json
 *
 * C'est le même format que l'export de l'écran Outils. Le plugin s'en sert de
 * trois façons :
 *
 *   - à l'activation du thème, il installe les blocs qui manquent — jamais il
 *     n'écrase un bloc existant, c'est peut-être vous qui l'avez modifié ;
 *   - l'écran Outils montre ce qui diffère entre le fichier et la base, et
 *     recopie dans un sens ou dans l'autre ;
 *   - WP-CLI fait la même chose en ligne de commande
 *     (`wp blocs-creator definitions …`), et une migration du thème peut
 *     appeler blocs_creator_importer_definitions() pour pousser une nouvelle
 *     version d'un bloc sur un site en ligne.
 *
 * Tout ce qui entre repasse par Blocs_Creator_Definition::normaliser().
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Le fichier de définitions du thème.
 */
class Blocs_Creator_Theme {

	/**
	 * Chemin du fichier, relatif au thème.
	 */
	const FICHIER = 'blocs-creator/definitions.json';

	/**
	 * Branche les hooks.
	 */
	public static function demarrer() {
		add_action( 'after_switch_theme', array( __CLASS__, 'installer' ) );
	}

	/**
	 * Le chemin du fichier : celui du thème enfant s'il en a un, sinon celui
	 * du thème parent.
	 *
	 * @return string Chemin absolu (le fichier peut ne pas exister).
	 */
	public static function fichier() {
		/**
		 * Filtre le chemin du fichier de définitions du thème.
		 *
		 * @param string $chemin Chemin absolu.
		 */
		return (string) apply_filters( 'blocs_creator_fichier_definitions', get_theme_file_path( self::FICHIER ) );
	}

	/**
	 * Le thème a-t-il un fichier de définitions ?
	 *
	 * @return bool
	 */
	public static function a_un_fichier() {
		return is_readable( self::fichier() );
	}

	/**
	 * Lit les définitions du fichier, brutes.
	 *
	 * @return array<int, array>|WP_Error
	 */
	public static function lire() {
		if ( ! self::a_un_fichier() ) {
			return new WP_Error( 'bc_theme_sans_fichier', __( 'Le thème n\'a pas de fichier blocs-creator/definitions.json.', 'blocs-creator' ) );
		}

		$paquet = json_decode( (string) file_get_contents( self::fichier() ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( ! is_array( $paquet ) ) {
			return new WP_Error( 'bc_theme_illisible', __( 'Le fichier blocs-creator/definitions.json n\'est pas du JSON lisible.', 'blocs-creator' ) );
		}

		$blocs = isset( $paquet['blocs'] ) && is_array( $paquet['blocs'] ) ? $paquet['blocs'] : $paquet;

		return array_values(
			array_filter(
				(array) $blocs,
				static function ( $brut ) {
					return is_array( $brut ) && ! empty( $brut['titre'] );
				}
			)
		);
	}

	/**
	 * Les définitions en base, par nom de bloc.
	 *
	 * @return array<string, array>
	 */
	private static function en_base() {
		$par_nom = array();

		foreach ( Blocs_Creator_Definition::toutes() as $definition ) {
			$par_nom[ Blocs_Creator_Definition::nom( $definition ) ] = $definition;
		}

		return $par_nom;
	}

	/**
	 * Une définition, sous une forme qui se compare.
	 *
	 * @param array $definition Définition (brute ou chargée).
	 * @return string
	 */
	private static function empreinte( $definition ) {
		return (string) wp_json_encode( Blocs_Creator_Definition::vers_tableau( $definition ) );
	}

	/**
	 * Compare le fichier et la base.
	 *
	 * @return array{fichier: bool, blocs: array<string, array{titre: string, etat: string}>}|WP_Error
	 *         `etat` vaut `absent` (dans le fichier seulement), `different`,
	 *         `identique`, ou `base` (en base seulement).
	 */
	public static function etat() {
		$lus = self::lire();

		if ( is_wp_error( $lus ) ) {
			return $lus;
		}

		$base  = self::en_base();
		$blocs = array();

		foreach ( $lus as $brut ) {
			$definition = Blocs_Creator_Definition::normaliser( $brut );
			$nom        = Blocs_Creator_Definition::nom( $definition );

			if ( '' === $nom ) {
				continue;
			}

			if ( ! isset( $base[ $nom ] ) ) {
				$etat = 'absent';
			} else {
				$etat = self::empreinte( $definition ) === self::empreinte( $base[ $nom ] ) ? 'identique' : 'different';
			}

			$blocs[ $nom ] = array(
				'titre' => $definition['titre'],
				'etat'  => $etat,
			);

			unset( $base[ $nom ] );
		}

		foreach ( $base as $nom => $definition ) {
			$blocs[ $nom ] = array(
				'titre' => $definition['titre'],
				'etat'  => 'base',
			);
		}

		ksort( $blocs );

		return array(
			'fichier' => true,
			'blocs'   => $blocs,
		);
	}

	/**
	 * Importe les définitions du fichier en base.
	 *
	 * @param bool     $ecraser   Remplacer les définitions qui existent déjà.
	 * @param string[] $seulement Identifiants (slug) ou noms complets des seuls blocs à importer ; vide pour tous.
	 * @return array{importes: int, ignores: int, erreurs: string[]}
	 */
	public static function importer( $ecraser = false, $seulement = array() ) {
		$bilan = array(
			'importes' => 0,
			'ignores'  => 0,
			'erreurs'  => array(),
		);

		$lus = self::lire();

		if ( is_wp_error( $lus ) ) {
			$bilan['erreurs'][] = $lus->get_error_message();
			return $bilan;
		}

		$base      = self::en_base();
		$seulement = array_filter( array_map( 'trim', (array) $seulement ) );

		foreach ( $lus as $brut ) {
			$definition = Blocs_Creator_Definition::normaliser( $brut );
			$nom        = Blocs_Creator_Definition::nom( $definition );

			if ( $seulement && ! in_array( $definition['slug'], $seulement, true ) && ! in_array( $nom, $seulement, true ) ) {
				continue;
			}

			$existant = isset( $base[ $nom ] ) ? (int) $base[ $nom ]['id'] : 0;

			if ( $existant > 0 && ! $ecraser ) {
				++$bilan['ignores'];
				continue;
			}

			$resultat = Blocs_Creator_Definition::enregistrer( $definition, $existant );

			if ( is_wp_error( $resultat ) ) {
				$bilan['erreurs'][] = $definition['titre'] . ' : ' . $resultat->get_error_message();
				continue;
			}

			++$bilan['importes'];
		}

		if ( $bilan['importes'] > 0 ) {
			Blocs_Creator_Usage::vider_cache();
		}

		return $bilan;
	}

	/**
	 * Écrit les définitions de la base dans le fichier du thème.
	 *
	 * Le geste inverse de l'import : un bloc mis au point dans le
	 * back-office rejoint le thème, et voyage avec lui. Le fichier est écrit
	 * dans le thème enfant s'il y en a un.
	 *
	 * @param string[] $seulement Identifiants ou noms complets ; vide pour tous.
	 * @return string|WP_Error Chemin du fichier écrit.
	 */
	public static function exporter( $seulement = array() ) {
		$seulement = array_filter( array_map( 'trim', (array) $seulement ) );
		$gardes    = array();

		// Un export partiel ne retire pas du fichier les blocs qu'on n'a pas demandés.
		if ( $seulement && self::a_un_fichier() ) {
			$lus = self::lire();

			foreach ( is_wp_error( $lus ) ? array() : $lus as $brut ) {
				$definition = Blocs_Creator_Definition::normaliser( $brut );

				$gardes[ Blocs_Creator_Definition::nom( $definition ) ] = Blocs_Creator_Definition::vers_tableau( $definition );
			}
		}

		foreach ( Blocs_Creator_Definition::toutes() as $definition ) {
			$nom = Blocs_Creator_Definition::nom( $definition );

			if ( $seulement && ! in_array( $definition['slug'], $seulement, true ) && ! in_array( $nom, $seulement, true ) ) {
				continue;
			}

			$gardes[ $nom ] = Blocs_Creator_Definition::vers_tableau( $definition );
		}

		ksort( $gardes );
		$blocs = array_values( $gardes );

		if ( empty( $blocs ) ) {
			return new WP_Error( 'bc_theme_rien', __( 'Aucun bloc à écrire dans le thème.', 'blocs-creator' ) );
		}

		$paquet = array(
			'plugin'     => 'blocs-creator',
			'version'    => BLOCS_CREATOR_VERSION,
			'exporte_le' => gmdate( 'c' ),
			'site'       => home_url(),
			'blocs'      => $blocs,
		);

		$cible = trailingslashit( get_stylesheet_directory() ) . self::FICHIER;

		return Blocs_Creator_Gabarits::ecrire(
			$cible,
			wp_json_encode( $paquet, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n"
		);
	}

	/**
	 * À l'activation d'un thème, installe les blocs qui manquent.
	 */
	public static function installer() {
		if ( self::a_un_fichier() ) {
			self::importer( false );
		}
	}
}
