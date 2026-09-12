<?php
/**
 * Le registre : ce qui existe, et ce qui tourne.
 *
 * Deux sortes de blocs entrent ici, et en ressortent enregistrées de la même
 * façon auprès de Gutenberg :
 *
 *   - LES BLOCS GÉNÉRÉS, décrits dans le back-office (BC_Definition). Leurs
 *     attributs sont déduits de leurs champs, leur rendu passe par BC_Rendu.
 *   - LES BLOCS CODÉS, découverts sur le disque à leur `block.json` — dans un
 *     pack du plugin, dans le thème, ou dans `wp-content/blocs-creator/`.
 *     Le registre ne fait que les enregistrer : leur rendu et leur éditeur
 *     sont leur affaire.
 *
 * C'est aussi le registre qui fournit à l'écran « Tous les blocs » sa liste.
 * Un bloc y figure parce qu'il est enregistré, pas parce qu'on l'a recopié
 * quelque part : la liste ne peut donc pas mentir.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Découverte et enregistrement des blocs.
 */
class BC_Registre {

	/**
	 * Identifiant du script de l'éditeur.
	 */
	const SCRIPT = 'blocs-creator-editeur';

	/**
	 * Identifiant de la feuille de style de l'éditeur.
	 */
	const STYLE = 'blocs-creator-editeur';

	/**
	 * Identifiant de la feuille de style du site.
	 *
	 * Vingt lignes, et rien de ce que le visiteur est venu lire : l'apparence
	 * d'un bloc appartient à son gabarit. Voir assets/css/blocs.css.
	 */
	const STYLE_SITE = 'blocs-creator';

	/**
	 * Cache de la découverte des blocs codés.
	 *
	 * @var array|null
	 */
	private $codes = null;

	/**
	 * Définitions enregistrées, par nom de bloc.
	 *
	 * @var array<string, array>
	 */
	private $generes = array();

	/**
	 * Blocs codés repris en main, par nom.
	 *
	 * @var array<string, bool>
	 */
	private $reprises = array();

	/**
	 * Branche les hooks.
	 */
	public function demarrer() {
		add_action( 'init', array( 'BC_Definition', 'declarer_type' ), 5 );
		add_action( 'init', array( $this, 'enregistrer_assets' ), 15 );
		add_action( 'init', array( $this, 'enregistrer_blocs' ), 20 );
		add_filter( 'block_categories_all', array( $this, 'categorie' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'donnees_editeur' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Assets
	 * ------------------------------------------------------------------ */

	/**
	 * Enregistre le script et la feuille de style de l'éditeur.
	 *
	 * Un seul script pour tous les blocs générés : ils partagent le même
	 * `edit`, seule leur définition change.
	 */
	public function enregistrer_assets() {
		wp_register_script(
			self::SCRIPT,
			BLOCS_CREATOR_URL . 'assets/js/editeur.js',
			array(
				'wp-api-fetch',
				'wp-blocks',
				'wp-block-editor',
				'wp-components',
				'wp-compose',
				'wp-core-data',
				'wp-data',
				'wp-element',
				'wp-i18n',
				'wp-server-side-render',
				'wp-url',
			),
			$this->version( 'assets/js/editeur.js' ),
			true
		);

		wp_set_script_translations( self::SCRIPT, 'blocs-creator', BLOCS_CREATOR_DIR . 'languages' );

		wp_register_style(
			self::STYLE_SITE,
			BLOCS_CREATOR_URL . 'assets/css/blocs.css',
			array(),
			$this->version( 'assets/css/blocs.css' )
		);

		wp_register_style(
			self::STYLE,
			BLOCS_CREATOR_URL . 'assets/css/editeur.css',
			array( self::STYLE_SITE ),
			$this->version( 'assets/css/editeur.css' )
		);
	}

	/**
	 * Retourne une version d'asset basée sur la date du fichier.
	 *
	 * En développement, le cache du navigateur s'invalide à chaque
	 * enregistrement ; en production, la date ne bouge plus.
	 *
	 * @param string $chemin Chemin relatif à la racine du plugin.
	 * @return string
	 */
	private function version( $chemin ) {
		$fichier = BLOCS_CREATOR_DIR . ltrim( $chemin, '/' );

		return file_exists( $fichier ) ? (string) filemtime( $fichier ) : BLOCS_CREATOR_VERSION;
	}

	/**
	 * Déclare la catégorie d'inséreur des blocs générés.
	 *
	 * @param array $categories Catégories existantes.
	 * @return array
	 */
	public function categorie( $categories ) {
		$slug  = (string) blocs_creator()->reglages->get( 'categorie' );
		$titre = (string) blocs_creator()->reglages->get( 'categorie_titre' );

		foreach ( (array) $categories as $existante ) {
			$autre_slug  = (string) ( $existante['slug'] ?? '' );
			$autre_titre = (string) ( $existante['title'] ?? '' );

			/*
			 * Même slug : il n'y a rien à ajouter, la section est là.
			 *
			 * Même titre sous un autre slug : il n'y a rien à ajouter non
			 * plus. Deux sections du même nom dans l'inséreur ne sont pas deux
			 * rangements, c'est le même coupé en deux — et plus personne ne
			 * sait dans laquelle chercher. La section qui existe déjà gagne ;
			 * pour y ranger vos blocs, choisissez-la dans les réglages.
			 */
			if ( $autre_slug === $slug || ( '' !== $titre && 0 === strcasecmp( $autre_titre, $titre ) ) ) {
				return $categories;
			}
		}

		array_unshift(
			$categories,
			array(
				'slug'  => $slug,
				'title' => $titre,
				'icon'  => null,
			)
		);

		return $categories;
	}

	/**
	 * Passe à l'éditeur les définitions dont il a besoin.
	 *
	 * Le script est unique et les définitions sont des données : le même
	 * fichier JavaScript sert un site à deux blocs comme un site à quarante.
	 */
	public function donnees_editeur() {
		$this->garde_reprises();

		if ( empty( $this->generes ) ) {
			return;
		}

		$blocs = array();

		foreach ( $this->generes as $nom => $definition ) {
			$blocs[] = array(
				'nom'     => $nom,
				'titre'   => $definition['titre'],
				'apercu'  => $definition['apercu'],
				'gabarit' => '' !== BC_Gabarits::chemin( $definition ),
				'champs'  => $this->champs_editeur( $definition['champs'] ),
			);
		}

		/**
		 * Filtre les données passées à l'éditeur.
		 *
		 * @param array $donnees Données.
		 */
		$donnees = apply_filters(
			'blocs_creator_donnees_editeur',
			array(
				'blocs'        => $blocs,
				'reprises'     => $this->reprises(),
				'tailles'      => $this->tailles_images(),
				'typesContenu' => $this->types_contenu(),
				'dashicons'    => BC_Reglages::dashicons(),
			)
		);

		// WordPress met déjà ce script en file au titre des blocs qui le
		// déclarent. On le redemande tout de même : un enregistrement en
		// double ne coûte rien, une donnée attachée à un script absent coûte
		// un éditeur muet.
		wp_enqueue_script( self::SCRIPT );
		wp_enqueue_style( self::STYLE );

		wp_add_inline_script(
			self::SCRIPT,
			'window.blocsCreator = ' . wp_json_encode( $donnees ) . ';',
			'before'
		);
	}

	/**
	 * Empêche le script d'un pack de réenregistrer un bloc qu'on a repris.
	 *
	 * Un bloc codé est déclaré deux fois : en PHP par son `block.json`, et en
	 * JavaScript par le script de son pack, qui lui donne son `edit`. Quand on
	 * reprend le bloc en main, le PHP passe à la définition — mais le script
	 * du pack, lui, continue de tourner pour ses autres blocs, et
	 * réenregistrerait celui-là par-dessus le nôtre.
	 *
	 * On ne peut pas compter sur l'ordre de chargement des scripts pour
	 * trancher. On pose donc le garde-fou juste après `wp-blocks`, avant tout
	 * script de bloc : les noms repris n'y répondent plus qu'à nous.
	 */
	private function garde_reprises() {
		$reprises = $this->reprises();

		if ( empty( $reprises ) ) {
			return;
		}

		wp_add_inline_script(
			'wp-blocks',
			'( function ( wp, repris ) {
	if ( ! wp || ! wp.blocks || ! repris.length ) { return; }
	window.blocsCreatorInterne = false;
	var original = wp.blocks.registerBlockType;
	wp.blocks.registerBlockType = function ( nom, reglages ) {
		var cle = "string" === typeof nom ? nom : ( nom && nom.name );
		if ( ! window.blocsCreatorInterne && -1 !== repris.indexOf( cle ) ) { return; }
		return original.apply( this, arguments );
	};
}( window.wp, ' . wp_json_encode( $reprises ) . ' ) );',
			'after'
		);
	}

	/**
	 * Retourne les types de publication du site, pour les menus de l'éditeur.
	 *
	 * La liste vient d'ici et non du JavaScript : un type de contenu déclaré
	 * plus tard, par un thème ou une extension, y apparaît sans qu'on touche à
	 * quoi que ce soit.
	 *
	 * @return array<int, array{value:string, label:string}>
	 */
	private function champs_editeur( $champs ) {
		foreach ( $champs as &$champ ) {
			$champ['defaut_editeur'] = BC_Champs::defaut( $champ );
			if ( in_array( $champ['type'], array( 'liste', 'boutons', 'cases' ), true ) ) {
				$champ['choix_editeur'] = BC_Champs::choix( $champ );
			}
			if ( isset( $champ['sous_champs'] ) ) {
				$champ['sous_champs'] = $this->champs_editeur( $champ['sous_champs'] );
			}
		}
		unset( $champ );
		return $champs;
	}

	private function types_contenu() {
		$types = array();

		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			if ( in_array( $type->name, array( 'attachment', BC_Definition::TYPE ), true ) ) {
				continue;
			}

			$types[] = array(
				'value' => $type->name,
				'label' => $type->labels->name,
			);
		}

		return $types;
	}

	/**
	 * Retourne les tailles d'images disponibles, pour les menus de l'éditeur.
	 *
	 * @return array<int, array{value:string, label:string}>
	 */
	private function tailles_images() {
		$tailles = array();

		foreach ( get_intermediate_image_sizes() as $taille ) {
			$tailles[] = array(
				'value' => $taille,
				'label' => $taille,
			);
		}

		$tailles[] = array(
			'value' => 'full',
			'label' => __( 'Taille d\'origine', 'blocs-creator' ),
		);

		return $tailles;
	}

	/* ------------------------------------------------------------------ *
	 * Enregistrement
	 * ------------------------------------------------------------------ */

	/**
	 * Enregistre tous les blocs, codés puis générés.
	 *
	 * Les codés d'abord : si un bloc généré porte par accident le même nom,
	 * c'est le code qui gagne — il est plus difficile à corriger qu'une
	 * définition, et c'est lui qui a probablement des pages derrière lui.
	 */
	public function enregistrer_blocs() {
		$definitions = BC_Definition::toutes( array( 'post_status' => 'publish' ) );

		foreach ( $definitions as $definition ) {
			if ( ! empty( $definition['adoption']['nom'] ) ) {
				$this->reprises[ (string) $definition['adoption']['nom'] ] = true;
			}
		}

		foreach ( $this->blocs_codes() as $bloc ) {
			// Un bloc repris en main est désormais servi par sa définition :
			// enregistrer aussi son dossier, c'est enregistrer deux fois le
			// même nom, et c'est le premier arrivé qui gagnerait.
			if ( isset( $this->reprises[ $bloc['nom'] ] ) || $this->deja_enregistre( $bloc['nom'] ) ) {
				continue;
			}

			register_block_type( $bloc['dossier'] );
		}

		foreach ( $definitions as $definition ) {
			$this->enregistrer_genere( $definition );
		}
	}

	/**
	 * Retourne les noms des blocs codés repris en main.
	 *
	 * @return array<int, string>
	 */
	public function reprises() {
		return array_keys( $this->reprises );
	}

	/**
	 * Le bloc est-il déjà enregistré ?
	 *
	 * @param string $nom Nom complet du bloc.
	 * @return bool
	 */
	private function deja_enregistre( $nom ) {
		return WP_Block_Type_Registry::get_instance()->is_registered( $nom );
	}

	/**
	 * Enregistre un bloc généré auprès de Gutenberg.
	 *
	 * @param array $definition La définition.
	 * @return WP_Block_Type|false
	 */
	private function enregistrer_genere( $definition ) {
		$nom = BC_Definition::nom( $definition );

		if ( '' === $nom || $this->deja_enregistre( $nom ) ) {
			return false;
		}

		$this->generes[ $nom ] = $definition;

		$args = array(
			'api_version'          => 3,
			'title'                => $definition['titre'],
			'description'          => $definition['description'],
			'category'             => $definition['categorie'],
			'icon'                 => $definition['icone'],
			'keywords'             => $definition['mots_cles'],
			'textdomain'           => 'blocs-creator',
			'attributes'           => $this->attributs( $definition ),
			'supports'             => $this->supports( $definition ),
			'render_callback'      => array( 'BC_Rendu', 'rendre' ),
			'editor_script_handles' => array( self::SCRIPT ),
			'editor_style_handles'  => array( self::STYLE ),
		);

		if ( ! empty( $definition['parent'] ) ) {
			$args['parent'] = $definition['parent'];
		}

		$args['style_handles'] = array( self::STYLE_SITE );

		$style = BC_Gabarits::handle_style( $definition );

		if ( '' !== $style ) {
			$args['style_handles'][] = $style;
		}

		/*
		 * Un bloc repris au code garde les feuilles et les scripts que son
		 * `block.json` déclarait, et tout ce que la définition ne sait pas
		 * dire — ses variantes, ses styles de bloc, son exemple d'inséreur.
		 * Sans quoi la reprise changerait son aspect, ce qu'elle promet de ne
		 * pas faire.
		 */
		if ( ! empty( $definition['assets']['style'] ) ) {
			$args['style_handles'] = array_merge( $args['style_handles'], $definition['assets']['style'] );
		}

		if ( ! empty( $definition['assets']['editor_style'] ) ) {
			$args['editor_style_handles'] = array_merge( $args['editor_style_handles'], $definition['assets']['editor_style'] );
		}

		if ( ! empty( $definition['assets']['view_script'] ) ) {
			$args['view_script_handles'] = $definition['assets']['view_script'];
		}

		foreach ( (array) $definition['extras'] as $cle => $valeur ) {
			$args[ $cle ] = $valeur;
		}

		/**
		 * Filtre les arguments d'enregistrement d'un bloc généré.
		 *
		 * @param array $args       Arguments passés à register_block_type().
		 * @param array $definition La définition.
		 */
		$args = apply_filters( 'blocs_creator_args_bloc', $args, $definition );

		return register_block_type( $nom, $args );
	}

	/**
	 * Traduit les champs d'une définition en attributs Gutenberg.
	 *
	 * @param array $definition La définition.
	 * @return array
	 */
	private function attributs( $definition ) {
		// Les attributs conservés d'un bloc repris passent en premier : un
		// champ qui porterait le même nom a été déclaré après, exprès, et
		// c'est lui qui doit gagner.
		$attributs = (array) ( $definition['attributs'] ?? array() );

		foreach ( $definition['champs'] as $champ ) {
			$attribut = BC_Champs::attribut( $champ );

			if ( null !== $attribut ) {
				$attributs[ $champ['cle'] ] = $attribut;
			}
		}

		return $attributs;
	}

	/**
	 * Traduit les cases à cocher de la définition en `supports` Gutenberg.
	 *
	 * `html` est toujours faux : le balisage d'un bloc dynamique vient du
	 * gabarit, l'éditer à la main dans la page n'aurait aucun effet.
	 *
	 * @param array $definition La définition.
	 * @return array
	 */
	private function supports( $definition ) {
		$choix = $definition['supports'];

		$supports = array(
			'html'            => false,
			'anchor'          => ! empty( $choix['anchor'] ),
			'customClassName' => ! empty( $choix['customClassName'] ),
			'multiple'        => ! empty( $choix['multiple'] ),
			'reusable'        => ! empty( $choix['reusable'] ),
		);

		if ( ! empty( $choix['align'] ) ) {
			$supports['align'] = array( 'wide', 'full' );
		}

		if ( ! empty( $choix['color'] ) ) {
			$supports['color'] = array(
				'background' => true,
				'text'       => true,
				'gradients'  => true,
				'link'       => true,
			);
		}

		if ( ! empty( $choix['typography'] ) ) {
			$supports['typography'] = array(
				'fontSize'   => true,
				'lineHeight' => true,
			);
		}

		if ( ! empty( $choix['spacing'] ) ) {
			$supports['spacing'] = array(
				'margin'   => true,
				'padding'  => true,
				'blockGap' => true,
			);
		}

		return $supports;
	}

	/**
	 * Retourne la définition d'un bloc généré, par son nom.
	 *
	 * @param string $nom Nom complet du bloc.
	 * @return array|null
	 */
	public function definition( $nom ) {
		return $this->generes[ $nom ] ?? null;
	}

	/**
	 * Retourne toutes les définitions enregistrées.
	 *
	 * @return array<string, array>
	 */
	public function generes() {
		return $this->generes;
	}

	/* ------------------------------------------------------------------ *
	 * Découverte des blocs codés
	 * ------------------------------------------------------------------ */

	/**
	 * Retourne les emplacements où chercher des blocs codés.
	 *
	 * @return array<int, array{chemin:string, source:string, libelle:string}>
	 */
	public function emplacements() {
		$emplacements = array();

		foreach ( blocs_creator()->packs() as $slug => $pack ) {
			$emplacements[] = array(
				'chemin'  => $pack['dossier'] . '/blocs',
				'source'  => 'pack:' . $slug,
				'libelle' => sprintf(
					/* translators: %s: nom du pack. */
					__( 'Pack %s', 'blocs-creator' ),
					$pack['nom']
				),
			);
		}

		$emplacements[] = array(
			'chemin'  => get_stylesheet_directory() . '/blocs',
			'source'  => 'theme',
			'libelle' => __( 'Thème', 'blocs-creator' ),
		);

		if ( get_template_directory() !== get_stylesheet_directory() ) {
			$emplacements[] = array(
				'chemin'  => get_template_directory() . '/blocs',
				'source'  => 'theme-parent',
				'libelle' => __( 'Thème parent', 'blocs-creator' ),
			);
		}

		$emplacements[] = array(
			'chemin'  => WP_CONTENT_DIR . '/blocs-creator/blocs',
			'source'  => 'site',
			'libelle' => __( 'wp-content', 'blocs-creator' ),
		);

		/**
		 * Filtre les emplacements où chercher des blocs codés.
		 *
		 * @param array $emplacements Emplacements.
		 */
		return apply_filters( 'blocs_creator_emplacements_blocs', $emplacements );
	}

	/**
	 * Découvre les blocs codés.
	 *
	 * Un bloc codé est un dossier portant un `block.json`. On lit ce fichier
	 * pour l'afficher dans l'écran de liste — nom, titre, description, icône —
	 * et on retient son dossier pour l'enregistrement.
	 *
	 * @return array<int, array>
	 */
	public function blocs_codes() {
		if ( null !== $this->codes ) {
			return $this->codes;
		}

		$blocs = array();

		foreach ( $this->emplacements() as $emplacement ) {
			if ( ! is_dir( $emplacement['chemin'] ) ) {
				continue;
			}

			foreach ( (array) glob( $emplacement['chemin'] . '/*/block.json' ) as $manifeste ) {
				$meta = json_decode( (string) file_get_contents( $manifeste ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions

				if ( ! is_array( $meta ) || empty( $meta['name'] ) ) {
					continue;
				}

				$nom   = (string) $meta['name'];
				$parts = explode( '/', $nom, 2 );

				$blocs[] = array(
					'source'      => 'code',
					'origine'     => $emplacement['source'],
					'origine_nom' => $emplacement['libelle'],
					'nom'         => $nom,
					'espace'      => $parts[0],
					'slug'        => $parts[1] ?? $parts[0],
					'titre'       => (string) ( $meta['title'] ?? $nom ),
					'description' => (string) ( $meta['description'] ?? '' ),
					'icone'       => (string) ( $meta['icon'] ?? 'block-default' ),
					'categorie'   => (string) ( $meta['category'] ?? '' ),
					'mots_cles'   => (array) ( $meta['keywords'] ?? array() ),
					'champs'      => array_keys( (array) ( $meta['attributes'] ?? array() ) ),
					'dossier'     => dirname( $manifeste ),
					'manifeste'   => $manifeste,
					'rendu'       => $this->fichier_rendu( dirname( $manifeste ), $meta ),
				);
			}
		}

		usort(
			$blocs,
			static function ( $a, $b ) {
				return strcasecmp( $a['titre'], $b['titre'] );
			}
		);

		$this->codes = $blocs;

		return $this->codes;
	}

	/**
	 * Retourne le fichier de rendu d'un bloc codé, s'il en a un.
	 *
	 * @param string $dossier Dossier du bloc.
	 * @param array  $meta    Contenu du block.json.
	 * @return string
	 */
	private function fichier_rendu( $dossier, $meta ) {
		$render = (string) ( $meta['render'] ?? '' );

		if ( str_starts_with( $render, 'file:' ) ) {
			$chemin = $dossier . '/' . ltrim( substr( $render, 5 ), './' );

			return file_exists( $chemin ) ? $chemin : '';
		}

		return '';
	}

	/**
	 * Retourne la liste complète des blocs, générés et codés, pour l'admin.
	 *
	 * @return array<int, array>
	 */
	public function tous() {
		$tous    = array();
		$reprises = array();

		foreach ( BC_Definition::toutes() as $definition ) {
			$repris = (string) ( $definition['adoption']['nom'] ?? '' );

			// Seule une reprise publiée remplace son bloc codé. Repassée en
			// brouillon, c'est le dossier qui sert de nouveau : il doit donc
			// réapparaître dans la liste, sans quoi on ne verrait plus ce qui
			// tourne réellement.
			if ( '' !== $repris && 'publish' === $definition['statut'] ) {
				$reprises[ $repris ] = true;
			}

			$tous[] = array(
				'source'      => 'genere',
				'adoption'    => $definition['adoption'],
				'id'          => $definition['id'],
				'nom'         => BC_Definition::nom( $definition ),
				'espace'      => $definition['espace'],
				'slug'        => $definition['slug'],
				'titre'       => $definition['titre'],
				'description' => $definition['description'],
				'icone'       => $definition['icone'],
				'categorie'   => $definition['categorie'],
				'statut'      => $definition['statut'],
				'champs'      => wp_list_pluck( $definition['champs'], 'cle' ),
				'gabarit'     => BC_Gabarits::chemin( $definition ),
				'definition'  => $definition,
			);
		}

		foreach ( $this->blocs_codes() as $code ) {
			// Un bloc codé repris en main ne figure plus comme bloc codé : sa
			// définition le remplace dans la liste, avec l'étiquette qui dit
			// d'où il vient. Le montrer deux fois ferait croire à deux blocs.
			if ( isset( $reprises[ $code['nom'] ] ) ) {
				continue;
			}

			$tous[] = array_merge(
				$code,
				array(
					'id'       => 0,
					'statut'   => 'publish',
					'gabarit'  => $code['rendu'],
					'adoption' => array(),
				)
			);
		}

		usort(
			$tous,
			static function ( $a, $b ) {
				return strcasecmp( $a['titre'], $b['titre'] );
			}
		);

		return $tous;
	}
}
