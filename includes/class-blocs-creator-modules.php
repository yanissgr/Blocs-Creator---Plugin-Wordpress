<?php
/**
 * Les modules : ce qu'un site demande presque toujours, et qu'on ne veut plus
 * réécrire à chaque projet.
 *
 * Maintenance, bandeau cookies, brouillons des formulaires, pas de page
 * d'auteur, pas de recherche, bouton « Modifier la page », nom des blocs sur
 * une page d'exemple. Chacun vit dans son dossier, `modules/<identifiant>/`,
 * et n'est chargé que s'il est activé :
 *
 *   - par une case de Blocs Creator › Réglages › Modules ;
 *   - ou par le thème, qui l'impose :
 *         add_theme_support( 'blocs-creator-modules', array( 'maintenance', 'cookies' ) );
 *     Un module imposé ne se décoche pas à l'écran : le thème en a besoin.
 *
 * Désactivé, un module ne charge pas une ligne : rien n'est ajouté au site,
 * ses réglages restent en base pour le jour où on le réactive.
 *
 * Chargés à `after_setup_theme` (priorité 20) : le thème a déjà dit ce qu'il
 * impose, et tous les crochets dont les modules ont besoin sont encore à venir.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Le registre des modules.
 */
class Blocs_Creator_Modules {

	/**
	 * Option : les modules cochés.
	 */
	const OPTION = 'blocs_creator_modules';

	/**
	 * Les modules chargés.
	 *
	 * @var array<string, bool>
	 */
	private static $charges = array();

	/**
	 * Branche les hooks.
	 */
	public static function demarrer() {
		add_action( 'after_setup_theme', array( __CLASS__, 'charger' ), 20 );
	}

	/**
	 * Le catalogue des modules.
	 *
	 * @return array<string, array{nom: string, description: string, fichier: string, reglages: string}>
	 */
	public static function catalogue() {
		$modules = array(
			'maintenance'     => array(
				'nom'         => __( 'Mode maintenance', 'blocs-creator' ),
				'description' => __( 'Ferme le site aux visiteurs le temps d’un chantier : une page d’attente servie en 503 (Google repasse plus tard), un interrupteur dans la barre d’outils. Les personnes connectées qui modifient le site le voient normalement.', 'blocs-creator' ),
				'reglages'    => 'options-general.php?page=blocs-creator-maintenance',
			),
			'cookies'         => array(
				'nom'         => __( 'Bandeau cookies', 'blocs-creator' ),
				'description' => __( 'Google Analytics (Site Kit) attend l’accord du visiteur : ses balises ne s’exécutent qu’après « Accepter ». Un petit bandeau en bas à gauche, « Refuser » aussi simple qu’« Accepter », le choix gardé six mois. Un lien vers #gerer-les-cookies le rouvre.', 'blocs-creator' ),
				'reglages'    => 'options-general.php?page=blocs-creator-cookies',
			),
			'brouillons'      => array(
				'nom'         => __( 'Brouillons des formulaires', 'blocs-creator' ),
				'description' => __( 'Les réponses d’un formulaire marqué data-bc-brouillon sont gardées sur l’appareil du visiteur : il les retrouve si la page se recharge ou se ferme. Avec le bandeau cookies, seulement après son accord.', 'blocs-creator' ),
				'reglages'    => '',
			),
			'sans-auteurs'    => array(
				'nom'         => __( 'Pas de page d’auteur', 'blocs-creator' ),
				'description' => __( 'Les pages d’auteur (/author/…, ?author=) mènent à la page des articles, sortent du plan du site, et l’API ne liste plus les comptes aux visiteurs : l’identifiant de connexion de qui écrit ne fuit plus.', 'blocs-creator' ),
				'reglages'    => '',
			),
			'sans-recherche'  => array(
				'nom'         => __( 'Pas de recherche', 'blocs-creator' ),
				'description' => __( 'Une adresse de recherche (?s=) mène à la page 404 sans interroger la base, le bloc « Rechercher » disparaît de l’éditeur et du site. Pour un site qui n’en a pas l’usage — et que les robots arrosent de spam.', 'blocs-creator' ),
				'reglages'    => '',
			),
			'bouton-modifier' => array(
				'nom'         => __( 'Bouton « Modifier la page »', 'blocs-creator' ),
				'description' => __( 'Un bouton flottant, en bas à droite du site, pour les personnes connectées qui peuvent modifier ce qu’elles regardent. Les visiteurs ne le voient jamais.', 'blocs-creator' ),
				'reglages'    => '',
			),
			'noms-blocs'      => array(
				'nom'         => __( 'Nom des blocs sur une page d’exemple', 'blocs-creator' ),
				'description' => __( 'Sur les pages qui le demandent (case « Afficher le nom des blocs » dans l’éditeur), chaque bloc porte une étiquette : sa famille, son nom tel qu’on le cherche dans l’éditeur, ce qu’il contient. Pour la page qui montre à la rédaction tous les blocs du site.', 'blocs-creator' ),
				'reglages'    => '',
			),
		);

		foreach ( $modules as $slug => &$module ) {
			$module['fichier'] = BLOCS_CREATOR_DIR . 'modules/' . $slug . '/module.php';
		}
		unset( $module );

		/**
		 * Filtre le catalogue des modules.
		 *
		 * Un module ajouté ici donne `nom`, `description`, `fichier` (chemin
		 * absolu du PHP à charger) et `reglages` (adresse de son écran,
		 * relative à wp-admin, ou '').
		 *
		 * @param array $modules Les modules, par identifiant.
		 */
		return (array) apply_filters( 'blocs_creator_catalogue_modules', $modules );
	}

	/**
	 * Les modules cochés à l'écran.
	 *
	 * @return string[]
	 */
	public static function coches() {
		return array_values( array_intersect( (array) get_option( self::OPTION, array() ), array_keys( self::catalogue() ) ) );
	}

	/**
	 * Le thème impose-t-il ce module ?
	 *
	 * @param string $slug Identifiant du module.
	 * @return bool
	 */
	public static function impose( $slug ) {
		$support = get_theme_support( 'blocs-creator-modules' );

		return is_array( $support ) && in_array( $slug, (array) ( $support[0] ?? array() ), true );
	}

	/**
	 * Le module est-il actif ?
	 *
	 * @param string $slug Identifiant du module.
	 * @return bool
	 */
	public static function actif( $slug ) {
		$actif = isset( self::catalogue()[ $slug ] ) && ( in_array( $slug, self::coches(), true ) || self::impose( $slug ) );

		/**
		 * Filtre l'activation d'un module.
		 *
		 * @param bool   $actif Le module est-il actif.
		 * @param string $slug  Identifiant du module.
		 */
		return (bool) apply_filters( 'blocs_creator_module_actif', $actif, $slug );
	}

	/**
	 * Écrit les modules cochés.
	 *
	 * @param string[] $slugs Identifiants.
	 */
	public static function enregistrer( $slugs ) {
		$connus = array_keys( self::catalogue() );
		$slugs  = array_values( array_unique( array_intersect( array_map( 'sanitize_key', (array) $slugs ), $connus ) ) );

		update_option( self::OPTION, $slugs, true );
	}

	/**
	 * Charge les modules actifs.
	 */
	public static function charger() {
		foreach ( self::catalogue() as $slug => $module ) {
			if ( isset( self::$charges[ $slug ] ) || ! self::actif( $slug ) || ! is_readable( (string) $module['fichier'] ) ) {
				continue;
			}

			require_once $module['fichier'];
			self::$charges[ $slug ] = true;
		}
	}

	/**
	 * Le module est-il chargé pour cette requête ?
	 *
	 * @param string $slug Identifiant du module.
	 * @return bool
	 */
	public static function charge( $slug ) {
		return isset( self::$charges[ $slug ] );
	}

	/**
	 * L'adresse d'un fichier d'un module.
	 *
	 * @param string $slug   Identifiant du module.
	 * @param string $chemin Chemin dans le dossier du module.
	 * @return string
	 */
	public static function url( $slug, $chemin ) {
		return BLOCS_CREATOR_URL . 'modules/' . $slug . '/' . ltrim( $chemin, '/' );
	}

	/**
	 * La version d'un fichier d'un module, d'après sa date.
	 *
	 * @param string $slug   Identifiant du module.
	 * @param string $chemin Chemin dans le dossier du module.
	 * @return string
	 */
	public static function version( $slug, $chemin ) {
		return Blocs_Creator_Plugin::version_fichier( 'modules/' . $slug . '/' . ltrim( $chemin, '/' ) );
	}
}
