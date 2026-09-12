<?php
/**
 * Le catalogue des types de champs.
 *
 * Un type de champ répond à quatre questions, et c'est tout ce que le reste du
 * plugin a besoin de savoir :
 *
 *   1. Comment se stocke-t-il ? → un attribut Gutenberg (`attribut`).
 *   2. Comment se nettoie-t-il ? → assainir_valeur().
 *   3. Comment se présente-t-il au gabarit ? → preparer().
 *   4. Quels réglages propose-t-il au constructeur ? → `reglages`.
 *
 * Ajouter un type, c'est ajouter une entrée ici et un cas dans le contrôle
 * JavaScript correspondant (admin/js/constructeur.js et assets/js/editeur.js).
 * Rien d'autre dans le plugin ne connaît la liste des types.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Catalogue et manipulation des champs.
 */
class BC_Champs {

	/**
	 * Le catalogue, construit une seule fois.
	 *
	 * @var array|null
	 */
	private static $catalogue = null;

	/**
	 * Retourne le catalogue complet des types de champs.
	 *
	 * @return array<string, array>
	 */
	public static function catalogue() {
		if ( null !== self::$catalogue ) {
			return self::$catalogue;
		}

		$catalogue = array(

			/* ---- Texte ---- */

			'texte'           => array(
				'libelle'     => __( 'Texte', 'blocs-creator' ),
				'famille'     => 'texte',
				'description' => __( 'Une ligne de texte simple.', 'blocs-creator' ),
				'attribut'    => 'string',
				'defaut'      => '',
				'reglages'    => array( 'defaut', 'placeholder', 'maxlength' ),
			),

			'texte-long'      => array(
				'libelle'     => __( 'Texte long', 'blocs-creator' ),
				'famille'     => 'texte',
				'description' => __( 'Plusieurs lignes, sans mise en forme.', 'blocs-creator' ),
				'attribut'    => 'string',
				'defaut'      => '',
				'reglages'    => array( 'defaut', 'placeholder', 'lignes' ),
			),

			'texte-riche'     => array(
				'libelle'     => __( 'Texte enrichi', 'blocs-creator' ),
				'famille'     => 'texte',
				'description' => __( 'Du texte avec gras, italique et liens. S\'écrit directement dans le bloc.', 'blocs-creator' ),
				'attribut'    => 'string',
				'defaut'      => '',
				'reglages'    => array( 'defaut', 'placeholder', 'balise' ),
			),

			'nombre'          => array(
				'libelle'     => __( 'Nombre', 'blocs-creator' ),
				'famille'     => 'texte',
				'description' => __( 'Une valeur numérique, avec bornes facultatives.', 'blocs-creator' ),
				'attribut'    => 'number',
				'defaut'      => 0,
				'reglages'    => array( 'defaut', 'min', 'max', 'pas', 'curseur' ),
			),

			/* ---- Choix ---- */

			'bascule'         => array(
				'libelle'     => __( 'Oui / Non', 'blocs-creator' ),
				'famille'     => 'choix',
				'description' => __( 'Un interrupteur. Sert les options d\'affichage.', 'blocs-creator' ),
				'attribut'    => 'boolean',
				'defaut'      => false,
				'reglages'    => array( 'defaut_bascule' ),
			),

			'liste'           => array(
				'libelle'     => __( 'Liste déroulante', 'blocs-creator' ),
				'famille'     => 'choix',
				'description' => __( 'Un choix parmi plusieurs, que vous définissez.', 'blocs-creator' ),
				'attribut'    => 'string',
				'defaut'      => '',
				'reglages'    => array( 'choix', 'defaut' ),
			),

			'boutons'         => array(
				'libelle'     => __( 'Groupe de boutons', 'blocs-creator' ),
				'famille'     => 'choix',
				'description' => __( 'Les mêmes choix qu\'une liste, mais visibles d\'un coup d\'œil. Au-delà de quatre options, préférez la liste.', 'blocs-creator' ),
				'attribut'    => 'string',
				'defaut'      => '',
				'reglages'    => array( 'choix', 'defaut' ),
			),

			'cases'           => array(
				'libelle'     => __( 'Cases à cocher', 'blocs-creator' ),
				'famille'     => 'choix',
				'description' => __( 'Plusieurs choix possibles parmi une liste.', 'blocs-creator' ),
				'attribut'    => 'array',
				'defaut'      => array(),
				'reglages'    => array( 'choix' ),
			),

			'niveau-titre'    => array(
				'libelle'     => __( 'Niveau de titre', 'blocs-creator' ),
				'famille'     => 'choix',
				'description' => __( 'De h1 à h6. Le bloc propose le sélecteur natif de WordPress, dans sa barre d\'outils.', 'blocs-creator' ),
				'attribut'    => 'number',
				'defaut'      => 2,
				'reglages'    => array( 'defaut_niveau', 'niveau_min' ),
			),

			'couleur'         => array(
				'libelle'     => __( 'Couleur', 'blocs-creator' ),
				'famille'     => 'choix',
				'description' => __( 'Une couleur de la palette du thème, ou une couleur libre.', 'blocs-creator' ),
				'attribut'    => 'string',
				'defaut'      => '',
				'reglages'    => array( 'defaut', 'palette_seule' ),
			),

			'point-focal'     => array(
				'libelle'     => __( 'Point de cadrage', 'blocs-creator' ),
				'famille'     => 'choix',
				'description' => __( 'L\'endroit d\'une image qui ne doit jamais être rogné. Le gabarit reçoit un `position` prêt à poser en CSS.', 'blocs-creator' ),
				'attribut'    => 'object',
				'defaut'      => array(
					'x' => 0.5,
					'y' => 0.5,
				),
				'reglages'    => array( 'image' ),
			),

			'icone'           => array(
				'libelle'     => __( 'Icône', 'blocs-creator' ),
				'famille'     => 'choix',
				'description' => __( 'Une icône Dashicons, choisie dans une grille.', 'blocs-creator' ),
				'attribut'    => 'string',
				'defaut'      => '',
				'reglages'    => array( 'defaut' ),
			),

			/* ---- Médias ---- */

			'image'           => array(
				'libelle'     => __( 'Image', 'blocs-creator' ),
				'famille'     => 'media',
				'description' => __( 'Une image de la médiathèque. Le gabarit reçoit son identifiant, son URL et son texte alternatif.', 'blocs-creator' ),
				'attribut'    => 'number',
				'defaut'      => 0,
				'reglages'    => array( 'taille' ),
			),

			'galerie'         => array(
				'libelle'     => __( 'Galerie', 'blocs-creator' ),
				'famille'     => 'media',
				'description' => __( 'Plusieurs images, dans l\'ordre que vous fixez.', 'blocs-creator' ),
				'attribut'    => 'array',
				'defaut'      => array(),
				'reglages'    => array( 'taille', 'max_medias' ),
			),

			'fichier'         => array(
				'libelle'     => __( 'Fichier', 'blocs-creator' ),
				'famille'     => 'media',
				'description' => __( 'Un document de la médiathèque : PDF, audio, vidéo…', 'blocs-creator' ),
				'attribut'    => 'number',
				'defaut'      => 0,
				'reglages'    => array( 'types_fichier' ),
			),

			/* ---- Liens et contenus ---- */

			'lien'            => array(
				'libelle'     => __( 'Lien', 'blocs-creator' ),
				'famille'     => 'lien',
				'description' => __( 'Une destination, un libellé et l\'option « nouvel onglet ». Cherche dans les pages du site comme dans les URL externes.', 'blocs-creator' ),
				'attribut'    => 'object',
				'defaut'      => array(
					'url'          => '',
					'titre'        => '',
					'nouvelOnglet' => false,
				),
				'reglages'    => array( 'defaut_libelle' ),
			),

			'contenu'         => array(
				'libelle'     => __( 'Publication', 'blocs-creator' ),
				'famille'     => 'lien',
				'description' => __( 'Une publication du site, choisie dans une liste. Le gabarit reçoit l\'objet WP_Post.', 'blocs-creator' ),
				'attribut'    => 'number',
				'defaut'      => 0,
				'reglages'    => array( 'types_contenu' ),
			),

			'contenus'        => array(
				'libelle'     => __( 'Publications', 'blocs-creator' ),
				'famille'     => 'lien',
				'description' => __( 'Plusieurs publications, dans l\'ordre que vous fixez.', 'blocs-creator' ),
				'attribut'    => 'array',
				'defaut'      => array(),
				'reglages'    => array( 'types_contenu', 'max_contenus' ),
			),

			'type-publication' => array(
				'libelle'     => __( 'Type de publication', 'blocs-creator' ),
				'famille'     => 'lien',
				'description' => __( 'Un type de contenu du site — Articles, Ateliers… — choisi dans une liste. Ce qu\'il faut pour un bloc qui affiche « les dernières publications de ».', 'blocs-creator' ),
				'attribut'    => 'string',
				'defaut'      => '',
				'reglages'    => array( 'defaut' ),
			),

			'taxonomie'       => array(
				'libelle'     => __( 'Terme', 'blocs-creator' ),
				'famille'     => 'lien',
				'description' => __( 'Une catégorie, une étiquette ou un terme personnalisé.', 'blocs-creator' ),
				'attribut'    => 'array',
				'defaut'      => array(),
				'reglages'    => array( 'taxonomie', 'terme_unique' ),
			),

			/* ---- Structure ---- */

			'groupe'          => array(
				'libelle'      => __( 'Groupe', 'blocs-creator' ),
				'famille'      => 'structure',
				'description'  => __( 'Réunit plusieurs champs sous un même titre. Le gabarit les reçoit dans un tableau.', 'blocs-creator' ),
				'attribut'     => 'object',
				'defaut'       => array(),
				'sous_champs'  => true,
				'reglages'     => array(),
			),

			'repeteur'        => array(
				'libelle'      => __( 'Répéteur', 'blocs-creator' ),
				'famille'      => 'structure',
				'description'  => __( 'Une liste de lignes, chacune portant les mêmes champs. C\'est ce qu\'il faut pour une série de cartes, de témoignages ou de tarifs.', 'blocs-creator' ),
				'attribut'     => 'array',
				'defaut'       => array(),
				'sous_champs'  => true,
				'reglages'     => array( 'libelle_ligne', 'min_lignes', 'max_lignes', 'lignes_depart' ),
			),

			'blocs-imbriques' => array(
				'libelle'      => __( 'Blocs imbriqués', 'blocs-creator' ),
				'famille'      => 'structure',
				'description'  => __( 'Laisse poser d\'autres blocs à l\'intérieur du vôtre. Le gabarit les reçoit dans $content. Un seul par bloc.', 'blocs-creator' ),
				'attribut'     => null,
				'defaut'       => null,
				'porte_valeur' => false,
				'unique'       => true,
				'reglages'     => array( 'blocs_autorises', 'gabarit_interne', 'verrou_gabarit', 'orientation' ),
			),

			'message'         => array(
				'libelle'      => __( 'Note', 'blocs-creator' ),
				'famille'      => 'structure',
				'description'  => __( 'Pas un champ : un mot d\'explication affiché à la rédaction dans le formulaire du bloc.', 'blocs-creator' ),
				'attribut'     => null,
				'defaut'       => null,
				'porte_valeur' => false,
				'reglages'     => array( 'message' ),
			),
		);

		/**
		 * Filtre le catalogue des types de champs.
		 *
		 * Un pack ou un thème peut y ajouter un type. Il devra aussi fournir
		 * son contrôle JavaScript, via le filtre `blocs_creator_donnees_editeur`.
		 *
		 * @param array $catalogue Le catalogue.
		 */
		self::$catalogue = apply_filters( 'blocs_creator_catalogue_champs', $catalogue );

		return self::$catalogue;
	}

	/**
	 * Retourne la définition d'un type, ou null.
	 *
	 * @param string $type Identifiant du type.
	 * @return array|null
	 */
	public static function type( $type ) {
		$catalogue = self::catalogue();

		return $catalogue[ $type ] ?? null;
	}

	/**
	 * Le type existe-t-il ?
	 *
	 * @param string $type Identifiant du type.
	 * @return bool
	 */
	public static function type_existe( $type ) {
		return null !== self::type( $type );
	}

	/**
	 * Ce type stocke-t-il une valeur ?
	 *
	 * Les notes et les blocs imbriqués n'ont pas d'attribut : la note n'est
	 * qu'un texte affiché, et les blocs imbriqués vivent dans le contenu du
	 * bloc, pas dans ses attributs.
	 *
	 * @param string $type Identifiant du type.
	 * @return bool
	 */
	public static function porte_valeur( $type ) {
		$def = self::type( $type );

		if ( null === $def ) {
			return false;
		}

		return $def['porte_valeur'] ?? true;
	}

	/**
	 * Les familles de types, pour grouper la liste du constructeur.
	 *
	 * @return array<string, string>
	 */
	public static function familles() {
		return array(
			'texte'     => __( 'Texte', 'blocs-creator' ),
			'choix'     => __( 'Choix et options', 'blocs-creator' ),
			'media'     => __( 'Médias', 'blocs-creator' ),
			'lien'      => __( 'Liens et contenus', 'blocs-creator' ),
			'structure' => __( 'Structure', 'blocs-creator' ),
		);
	}

	/**
	 * Retourne la valeur par défaut d'un champ.
	 *
	 * Le réglage `defaut` de la définition l'emporte sur celui du type.
	 *
	 * @param array $champ Définition du champ.
	 * @return mixed
	 */
	public static function defaut( $champ ) {
		$type = self::type( $champ['type'] ?? '' );

		if ( null === $type ) {
			return null;
		}

		if ( ! self::porte_valeur( $champ['type'] ) ) {
			return null;
		}

		$options = $champ['options'] ?? array();

		switch ( $champ['type'] ) {
			case 'bascule':
				return ! empty( $options['defaut'] );

			case 'nombre':
				return isset( $options['defaut'] ) && '' !== $options['defaut'] ? (float) $options['defaut'] : 0;

			case 'niveau-titre':
				return isset( $options['defaut'] ) ? max( 1, min( 6, (int) $options['defaut'] ) ) : 2;

			case 'lien':
				return array(
					'url'          => '',
					'titre'        => (string) ( $options['defaut_libelle'] ?? '' ),
					'nouvelOnglet' => false,
				);

			case 'point-focal':
				return array(
					'x' => 0.5,
					'y' => 0.5,
				);

			case 'repeteur':
				return self::lignes_de_depart( $champ );

			case 'groupe':
				return self::defauts_sous_champs( $champ );

			case 'cases':
			case 'galerie':
			case 'contenus':
			case 'taxonomie':
				return array();

			case 'image':
			case 'fichier':
			case 'contenu':
				return 0;

			default:
				return isset( $options['defaut'] ) ? (string) $options['defaut'] : (string) $type['defaut'];
		}
	}

	/**
	 * Retourne une ligne vierge de répéteur, remplie des valeurs par défaut.
	 *
	 * @param array $champ Définition du répéteur ou du groupe.
	 * @return array
	 */
	public static function defauts_sous_champs( $champ ) {
		$ligne = array();

		foreach ( (array) ( $champ['sous_champs'] ?? array() ) as $sous ) {
			if ( ! self::porte_valeur( $sous['type'] ?? '' ) ) {
				continue;
			}

			$ligne[ $sous['cle'] ] = self::defaut( $sous );
		}

		return $ligne;
	}

	/**
	 * Retourne les lignes de départ d'un répéteur.
	 *
	 * @param array $champ Définition du répéteur.
	 * @return array
	 */
	private static function lignes_de_depart( $champ ) {
		$nombre = (int) ( $champ['options']['lignes_depart'] ?? 0 );
		$nombre = max( 0, min( 20, $nombre ) );
		$lignes = array();

		for ( $i = 0; $i < $nombre; $i++ ) {
			$lignes[] = self::defauts_sous_champs( $champ );
		}

		return $lignes;
	}

	/**
	 * Traduit un champ en déclaration d'attribut Gutenberg.
	 *
	 * @param array $champ Définition du champ.
	 * @return array|null Le tableau `attributes` de ce champ, ou null.
	 */
	public static function attribut( $champ ) {
		$type = self::type( $champ['type'] ?? '' );

		if ( null === $type || ! self::porte_valeur( $champ['type'] ) ) {
			return null;
		}

		return array(
			'type'    => $type['attribut'],
			'default' => self::defaut( $champ ),
		);
	}

	/**
	 * Nettoie la valeur d'un champ avant rendu.
	 *
	 * Les attributs de bloc passent déjà par la validation de Gutenberg ; ce
	 * second passage vise le rendu serveur, qui peut être appelé avec des
	 * attributs venus d'ailleurs — l'aperçu REST, par exemple.
	 *
	 * @param array $champ  Définition du champ.
	 * @param mixed $valeur Valeur brute.
	 * @return mixed
	 */
	public static function assainir_valeur( $champ, $valeur ) {
		$tipe    = $champ['type'] ?? '';
		$options = $champ['options'] ?? array();

		if ( ! self::porte_valeur( $tipe ) ) {
			return null;
		}

		if ( null === $valeur ) {
			return self::defaut( $champ );
		}

		switch ( $tipe ) {
			case 'texte':
			case 'icone':
				return sanitize_text_field( (string) $valeur );

			case 'texte-long':
				return sanitize_textarea_field( (string) $valeur );

			case 'texte-riche':
				return wp_kses_post( (string) $valeur );

			case 'nombre':
				$nombre = (float) $valeur;

				if ( isset( $options['min'] ) && '' !== $options['min'] ) {
					$nombre = max( (float) $options['min'], $nombre );
				}

				if ( isset( $options['max'] ) && '' !== $options['max'] ) {
					$nombre = min( (float) $options['max'], $nombre );
				}

				return $nombre;

			case 'bascule':
				return (bool) $valeur;

			case 'niveau-titre':
				$minimum = max( 1, min( 6, (int) ( $options['niveau_min'] ?? 2 ) ) );

				return min( 6, max( $minimum, (int) $valeur ) );

			case 'couleur':
				$couleur = trim( (string) $valeur );

				if ( '' === $couleur ) {
					return '';
				}

				// Un code hexadécimal, ou un jeton de palette (var:preset|color|nom).
				$hexa = sanitize_hex_color( $couleur );

				return $hexa ? $hexa : sanitize_text_field( $couleur );

			case 'liste':
			case 'boutons':
				$permis = wp_list_pluck( self::choix( $champ ), 'valeur' );
				$valeur = sanitize_text_field( (string) $valeur );

				return in_array( $valeur, $permis, true ) ? $valeur : self::defaut( $champ );

			case 'cases':
				$permis = wp_list_pluck( self::choix( $champ ), 'valeur' );

				return array_values(
					array_intersect(
						array_map( 'sanitize_text_field', (array) $valeur ),
						$permis
					)
				);

			case 'image':
			case 'fichier':
			case 'contenu':
				return max( 0, (int) $valeur );

			case 'galerie':
			case 'contenus':
			case 'taxonomie':
				return array_values(
					array_filter(
						array_map( 'absint', (array) $valeur )
					)
				);

			case 'point-focal':
				$valeur = (array) $valeur;
				$borner = static function ( $coordonnee ) {
					return is_numeric( $coordonnee ) ? max( 0, min( 1, (float) $coordonnee ) ) : 0.5;
				};

				return array(
					'x' => $borner( $valeur['x'] ?? null ),
					'y' => $borner( $valeur['y'] ?? null ),
				);

			case 'type-publication':
				$type = sanitize_key( (string) $valeur );

				return post_type_exists( $type ) ? $type : (string) self::defaut( $champ );

			case 'lien':
				$valeur = (array) $valeur;

				return array(
					'url'          => esc_url_raw( (string) ( $valeur['url'] ?? '' ) ),
					'titre'        => sanitize_text_field( (string) ( $valeur['titre'] ?? '' ) ),
					'nouvelOnglet' => ! empty( $valeur['nouvelOnglet'] ),
				);

			case 'groupe':
				return self::assainir_ligne( $champ, (array) $valeur );

			case 'repeteur':
				$lignes = array();

				foreach ( (array) $valeur as $ligne ) {
					$lignes[] = self::assainir_ligne( $champ, (array) $ligne );
				}

				$maximum = (int) ( $options['max_lignes'] ?? 0 );

				if ( $maximum > 0 ) {
					$lignes = array_slice( $lignes, 0, $maximum );
				}

				return $lignes;

			default:
				return is_scalar( $valeur ) ? sanitize_text_field( (string) $valeur ) : null;
		}
	}

	/**
	 * Nettoie une ligne de répéteur ou le contenu d'un groupe.
	 *
	 * @param array $champ  Définition du répéteur ou du groupe.
	 * @param array $valeur Valeurs de la ligne.
	 * @return array
	 */
	private static function assainir_ligne( $champ, $valeur ) {
		$propre = array();

		foreach ( (array) ( $champ['sous_champs'] ?? array() ) as $sous ) {
			if ( ! self::porte_valeur( $sous['type'] ?? '' ) ) {
				continue;
			}

			$cle            = $sous['cle'];
			$propre[ $cle ] = self::assainir_valeur( $sous, $valeur[ $cle ] ?? null );
		}

		return $propre;
	}

	/**
	 * Retourne les choix d'un champ, normalisés.
	 *
	 * Les choix se saisissent une ligne par option, sous la forme
	 * `valeur : Libellé` — ou juste `Libellé`, la valeur étant alors son slug.
	 *
	 * @param array $champ Définition du champ.
	 * @return array<int, array{valeur:string, libelle:string}>
	 */
	public static function choix( $champ ) {
		$brut = $champ['options']['choix'] ?? '';

		if ( is_array( $brut ) ) {
			$lignes = $brut;
		} else {
			$lignes = preg_split( '/\r\n|\r|\n/', (string) $brut );
		}

		$choix = array();

		foreach ( (array) $lignes as $ligne ) {
			if ( is_array( $ligne ) ) {
				$valeur  = (string) ( $ligne['valeur'] ?? '' );
				$libelle = (string) ( $ligne['libelle'] ?? $valeur );
			} else {
				$ligne = trim( (string) $ligne );

				if ( '' === $ligne ) {
					continue;
				}

				if ( str_contains( $ligne, ':' ) ) {
					list( $valeur, $libelle ) = array_map( 'trim', explode( ':', $ligne, 2 ) );
				} else {
					$libelle = $ligne;
					$valeur  = sanitize_title( $ligne );
				}
			}

			if ( '' === $valeur ) {
				continue;
			}

			$choix[] = array(
				'valeur'  => $valeur,
				'libelle' => '' !== $libelle ? $libelle : $valeur,
			);
		}

		return $choix;
	}

	/**
	 * Prépare la valeur d'un champ pour le gabarit.
	 *
	 * C'est ici que le gabarit gagne sa simplicité : une image devient un
	 * tableau qui porte déjà son URL et son alt, un lien porte ses attributs
	 * HTML tout faits, une publication est un WP_Post. Écrire un gabarit ne
	 * doit pas demander de connaître l'API des médias.
	 *
	 * La valeur brute reste accessible : elle est dans $attributes.
	 *
	 * @param array $champ  Définition du champ.
	 * @param mixed $valeur Valeur assainie.
	 * @return mixed
	 */
	public static function preparer( $champ, $valeur ) {
		$tipe    = $champ['type'] ?? '';
		$options = $champ['options'] ?? array();

		switch ( $tipe ) {
			case 'image':
				return self::preparer_media( (int) $valeur, (string) ( $options['taille'] ?? 'large' ) );

			case 'fichier':
				return self::preparer_media( (int) $valeur, 'full' );

			case 'galerie':
				$images = array();

				foreach ( (array) $valeur as $id ) {
					$media = self::preparer_media( (int) $id, (string) ( $options['taille'] ?? 'large' ) );

					if ( null !== $media ) {
						$images[] = $media;
					}
				}

				return $images;

			case 'point-focal':
				$valeur = (array) $valeur;
				$x      = isset( $valeur['x'] ) ? (float) $valeur['x'] : 0.5;
				$y      = isset( $valeur['y'] ) ? (float) $valeur['y'] : 0.5;

				// `position` est prêt à poser : `object-position`,
				// `background-position`, ou une variable CSS du gabarit.
				return array(
					'x'        => $x,
					'y'        => $y,
					'position' => sprintf( '%s%% %s%%', round( $x * 100, 2 ), round( $y * 100, 2 ) ),
				);

			case 'lien':
				return self::preparer_lien( (array) $valeur );

			case 'contenu':
				$publication = (int) $valeur > 0 ? get_post( (int) $valeur ) : null;

				return $publication instanceof WP_Post ? $publication : null;

			case 'contenus':
				$publications = array();

				foreach ( (array) $valeur as $id ) {
					$publication = get_post( (int) $id );

					if ( $publication instanceof WP_Post ) {
						$publications[] = $publication;
					}
				}

				return $publications;

			case 'taxonomie':
				$termes = array();

				foreach ( (array) $valeur as $id ) {
					$terme = get_term( (int) $id );

					if ( $terme instanceof WP_Term ) {
						$termes[] = $terme;
					}
				}

				if ( ! empty( $options['terme_unique'] ) ) {
					return $termes ? $termes[0] : null;
				}

				return $termes;

			case 'groupe':
				return self::preparer_ligne( $champ, (array) $valeur );

			case 'repeteur':
				$lignes = array();

				foreach ( (array) $valeur as $ligne ) {
					$lignes[] = self::preparer_ligne( $champ, (array) $ligne );
				}

				return $lignes;

			default:
				return $valeur;
		}
	}

	/**
	 * Prépare une ligne de répéteur ou le contenu d'un groupe.
	 *
	 * @param array $champ  Définition du répéteur ou du groupe.
	 * @param array $valeur Valeurs de la ligne.
	 * @return array
	 */
	private static function preparer_ligne( $champ, $valeur ) {
		$prete = array();

		foreach ( (array) ( $champ['sous_champs'] ?? array() ) as $sous ) {
			if ( ! self::porte_valeur( $sous['type'] ?? '' ) ) {
				continue;
			}

			$cle           = $sous['cle'];
			$prete[ $cle ] = self::preparer( $sous, $valeur[ $cle ] ?? self::defaut( $sous ) );
		}

		return $prete;
	}

	/**
	 * Prépare un média pour le gabarit.
	 *
	 * @param int    $id     Identifiant de la pièce jointe.
	 * @param string $taille Taille d'image demandée.
	 * @return array|null
	 */
	private static function preparer_media( $id, $taille = 'large' ) {
		if ( $id <= 0 || ! get_post( $id ) ) {
			return null;
		}

		$est_image = wp_attachment_is_image( $id );
		$source    = $est_image ? wp_get_attachment_image_src( $id, $taille ) : false;

		return array(
			'id'        => $id,
			'est_image' => $est_image,
			'url'       => $source ? $source[0] : (string) wp_get_attachment_url( $id ),
			'largeur'   => $source ? (int) $source[1] : 0,
			'hauteur'   => $source ? (int) $source[2] : 0,
			'alt'       => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'titre'     => get_the_title( $id ),
			'legende'   => (string) wp_get_attachment_caption( $id ),
			'mime'      => (string) get_post_mime_type( $id ),
			'taille'    => $taille,
		);
	}

	/**
	 * Prépare un lien pour le gabarit.
	 *
	 * `attrs` porte les attributs HTML déjà échappés : un gabarit peut les
	 * poser tels quels sur sa balise `<a>` sans rien oublier — ni le
	 * `rel="noreferrer noopener"` du nouvel onglet, ni l'échappement de l'URL.
	 *
	 * @param array $lien Valeur du champ.
	 * @return array
	 */
	private static function preparer_lien( $lien ) {
		$url    = (string) ( $lien['url'] ?? '' );
		$titre  = (string) ( $lien['titre'] ?? '' );
		$onglet = ! empty( $lien['nouvelOnglet'] );

		$attrs = '';

		if ( '' !== $url ) {
			$attrs = 'href="' . esc_url( $url ) . '"';

			if ( $onglet ) {
				$attrs .= ' target="_blank" rel="noreferrer noopener"';
			}
		}

		return array(
			'url'          => $url,
			'titre'        => $titre,
			'nouvelOnglet' => $onglet,
			'attrs'        => $attrs,
			'rempli'       => '' !== $url,
		);
	}
}
