<?php
/**
 * Bootstrap du plugin.
 *
 * Une seule instance, un seul point de chargement. Les classes sont requises
 * ici plutôt que par un autoloader : à quinze fichiers, une liste explicite se
 * lit mieux qu'une convention de nommage.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Le plugin.
 */
final class Blocs_Creator_Plugin {

	/**
	 * Instance unique.
	 *
	 * @var Blocs_Creator_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Le registre des blocs.
	 *
	 * @var Blocs_Creator_Registre
	 */
	public $registre;

	/**
	 * Les réglages.
	 *
	 * @var Blocs_Creator_Reglages
	 */
	public $reglages;

	/**
	 * Les packs chargés.
	 *
	 * @var array<string, array>
	 */
	private $packs = array();

	/**
	 * Retourne l'instance unique.
	 *
	 * @return Blocs_Creator_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructeur privé : on passe par instance().
	 */
	private function __construct() {}

	/**
	 * Charge les fichiers et branche les hooks.
	 */
	public function demarrer() {
		$this->charger();

		$this->reglages = new Blocs_Creator_Reglages();
		$this->registre = new Blocs_Creator_Registre();

		/*
		 * Aucun chargement de domaine de traduction ici : depuis WordPress 4.6,
		 * les traductions du dépôt officiel se chargent seules, et depuis 6.7
		 * WordPress va chercher `languages/` du plugin au premier __() venu.
		 * Le faire nous-mêmes n'ajouterait qu'un avertissement.
		 */
		add_action( 'plugins_loaded', array( $this, 'charger_packs' ), 20 );
		add_action( 'admin_init', array( $this, 'mettre_a_jour' ), 20 );

		$this->reglages->demarrer();
		$this->registre->demarrer();

		Blocs_Creator_Rendu::demarrer();
		Blocs_Creator_Rest::demarrer();

		Blocs_Creator_Animations::demarrer();
		Blocs_Creator_Disponibilite::demarrer();

		if ( is_admin() ) {
			( new Blocs_Creator_Admin() )->demarrer();
		}
	}

	/**
	 * Requiert les fichiers du plugin.
	 */
	private function charger() {
		$fichiers = array(
			'includes/class-blocs-creator-reglages.php',
			'includes/class-blocs-creator-champs.php',
			'includes/class-blocs-creator-definition.php',
			'includes/class-blocs-creator-registre.php',
			'includes/class-blocs-creator-rendu.php',
			'includes/class-blocs-creator-gabarits.php',
			'includes/class-blocs-creator-usage.php',
			'includes/class-blocs-creator-adoption.php',
			'includes/class-blocs-creator-disponibilite.php',
			'includes/class-blocs-creator-animations.php',
			'includes/class-blocs-creator-diagnostic.php',
			'includes/class-blocs-creator-rest.php',
			'includes/fonctions.php',
		);

		if ( is_admin() ) {
			$fichiers[] = 'admin/class-blocs-creator-admin.php';
			$fichiers[] = 'admin/class-blocs-creator-ecran-definition.php';
			$fichiers[] = 'admin/class-blocs-creator-outils.php';
		}

		foreach ( $fichiers as $fichier ) {
			require_once BLOCS_CREATOR_DIR . $fichier;
		}
	}

	/**
	 * Charge les packs de blocs codés.
	 *
	 * Un pack est un dossier de `packs/` portant un `pack.php`. Ce fichier est
	 * inclus une fois, avant `init`, et retourne ses métadonnées. Ses blocs —
	 * les dossiers de `blocs/` qui ont un `block.json` — sont enregistrés par
	 * le registre, pas par le pack : c'est ce qui garantit que l'écran « Tous
	 * les blocs » montre exactement ce qui tourne.
	 *
	 * Supprimer un dossier de pack suffit à le retirer. Aucune trace en base,
	 * aucun réglage à nettoyer.
	 */
	public function charger_packs() {
		$racine = BLOCS_CREATOR_DIR . 'packs';

		if ( ! is_dir( $racine ) ) {
			return;
		}

		foreach ( (array) glob( $racine . '/*/pack.php' ) as $fichier ) {
			$slug = basename( dirname( $fichier ) );

			/**
			 * Filtre le chargement d'un pack.
			 *
			 * Permet à un site de désactiver un pack sans supprimer son
			 * dossier — le temps d'un diagnostic, par exemple.
			 *
			 * @param bool   $charger Faut-il charger ce pack.
			 * @param string $slug    Identifiant du pack.
			 */
			if ( ! apply_filters( 'blocs_creator_charger_pack', true, $slug ) ) {
				continue;
			}

			$meta = include_once $fichier;

			$this->packs[ $slug ] = wp_parse_args(
				is_array( $meta ) ? $meta : array(),
				array(
					'nom'         => $slug,
					'description' => '',
					'auteur'      => '',
					'version'     => '',
					'dossier'     => dirname( $fichier ),
				)
			);

			$this->packs[ $slug ]['dossier'] = dirname( $fichier );
		}
	}

	/**
	 * Retourne les packs chargés.
	 *
	 * @return array<string, array>
	 */
	public function packs() {
		return $this->packs;
	}

	/**
	 * Retourne un pack par son identifiant.
	 *
	 * @param string $slug Identifiant du pack.
	 * @return array|null
	 */
	public function pack( $slug ) {
		return $this->packs[ $slug ] ?? null;
	}

	/**
	 * Rattrape ce qu'une montée de version demande.
	 *
	 * Une seule chose pour l'instant, et elle ne peut pas se faire à
	 * l'activation : savoir quelles catégories l'inséreur connaît suppose que
	 * tous les plugins et le thème aient parlé, ce qui n'arrive qu'à
	 * `admin_init`.
	 */
	public function mettre_a_jour() {
		$depuis = (string) get_option( 'blocs_creator_version', '0' );

		if ( version_compare( $depuis, BLOCS_CREATOR_VERSION, '>=' ) ) {
			return;
		}

		if ( version_compare( $depuis, '3.0.0', '<' ) ) {
			$this->reglages->fusionner_categorie();
		}

		Blocs_Creator_Usage::vider_cache();

		update_option( 'blocs_creator_version', BLOCS_CREATOR_VERSION );
	}

	/**
	 * À l'activation : déclarer le type de contenu, puis rafraîchir les
	 * permaliens et le cache des blocs.
	 */
	public static function activation() {
		Blocs_Creator_Definition::declarer_type();
		flush_rewrite_rules();

		Blocs_Creator_Usage::vider_cache();

		if ( false === get_option( 'blocs_creator_reglages' ) ) {
			add_option( 'blocs_creator_reglages', Blocs_Creator_Reglages::defauts() );
		}

		update_option( 'blocs_creator_version', BLOCS_CREATOR_VERSION );
	}

	/**
	 * À la désactivation : rien à détruire, juste les caches à vider.
	 *
	 * Les définitions de blocs restent en base et les gabarits sur le disque :
	 * désactiver n'est pas désinstaller.
	 */
	public static function desactivation() {
		Blocs_Creator_Usage::vider_cache();
		flush_rewrite_rules();
	}
}
