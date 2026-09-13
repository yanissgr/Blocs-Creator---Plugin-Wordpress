<?php
/**
 * Une définition de bloc.
 *
 * C'est l'objet que l'on saisit dans le back-office : un nom, un identifiant,
 * une icône, et surtout une liste de champs. Il est stocké dans un type de
 * contenu privé (`blocs_creator_bloc`) sous forme de JSON, avec le titre, le slug et la
 * description recopiés dans les colonnes natives — pour que la recherche et
 * les listes de WordPress fonctionnent sans rien réapprendre.
 *
 * Une définition ne sait pas s'enregistrer comme bloc Gutenberg : c'est le
 * travail du registre. Elle ne sait pas non plus s'afficher : c'est celui du
 * rendu. Elle se contente de se lire, de se nettoyer et de s'écrire.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lecture, nettoyage et écriture des définitions de blocs.
 */
class Blocs_Creator_Definition {

	/**
	 * Identifiant du type de contenu.
	 */
	const TYPE = 'bc_bloc';

	/**
	 * Clé de la méta qui porte la définition.
	 */
	const META = '_bc_definition';

	/**
	 * Déclare le type de contenu.
	 *
	 * Il n'est ni public, ni dans le menu : l'écran de liste est le nôtre, et
	 * une définition de bloc n'a pas de page sur le site. `show_ui` reste vrai
	 * pour profiter de l'écran d'édition natif, de la corbeille et des
	 * capacités.
	 */
	public static function declarer_type() {
		register_post_type(
			self::TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Blocs', 'blocs-creator' ),
					'singular_name'      => __( 'Bloc', 'blocs-creator' ),
					'add_new'            => __( 'Ajouter un bloc', 'blocs-creator' ),
					'add_new_item'       => __( 'Nouveau bloc', 'blocs-creator' ),
					'edit_item'          => __( 'Modifier le bloc', 'blocs-creator' ),
					'new_item'           => __( 'Nouveau bloc', 'blocs-creator' ),
					'view_item'          => __( 'Voir le bloc', 'blocs-creator' ),
					'search_items'       => __( 'Rechercher un bloc', 'blocs-creator' ),
					'not_found'          => __( 'Aucun bloc pour l\'instant.', 'blocs-creator' ),
					'not_found_in_trash' => __( 'Aucun bloc dans la corbeille.', 'blocs-creator' ),
					'all_items'          => __( 'Tous les blocs', 'blocs-creator' ),
					'menu_name'          => __( 'Blocs Creator', 'blocs-creator' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'hierarchical'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => true,
				'delete_with_user'    => false,
				'menu_icon'           => 'dashicons-layout',
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Retourne la fiche d'un bloc : tout ce qu'il faut pour le dessiner.
	 *
	 * Blocs Creator coupe le travail en deux : les champs se déclarent ici, le
	 * dessin s'écrit dans un fichier du thème. Quand ces deux moitiés sont
	 * faites par deux personnes — ou par une personne et un assistant —, il
	 * manquait le pont : celui qui dessine doit connaître les clés, les types,
	 * les valeurs qu'il recevra, et le nom exact du fichier à créer.
	 *
	 * Cette fiche est ce pont. Elle se copie d'un bouton et se lit telle
	 * quelle : aucune base à interroger, aucun export à ouvrir.
	 *
	 * @param array $definition La définition.
	 * @return string Texte brut, en Markdown.
	 */
	public static function fiche( $definition ) {
		$nom     = self::nom( $definition );
		$gabarit = Blocs_Creator_Gabarits::chemin( $definition );
		$cible   = '' !== $gabarit ? $gabarit : Blocs_Creator_Gabarits::chemin_prefere( $definition );

		$lignes = array();

		$lignes[] = sprintf( '# Bloc « %s »', $definition['titre'] );
		$lignes[] = '';
		$lignes[] = sprintf( '- Identifiant : `%s`', $nom );
		$lignes[] = sprintf(
			'- Fichier de dessin : `%s`%s',
			Blocs_Creator_Gabarits::chemin_court( $cible ),
			'' !== $gabarit ? '' : ' — **à créer**'
		);

		if ( '' !== (string) $definition['description'] ) {
			$lignes[] = sprintf( '- Description : %s', $definition['description'] );
		}

		$lignes[] = sprintf(
			'- Apparition : %s',
			'' !== (string) $definition['animation'] ? $definition['animation'] : 'aucune'
		);

		$lignes[] = '';
		$lignes[] = '## Champs';
		$lignes[] = '';

		if ( empty( $definition['champs'] ) ) {
			$lignes[] = '_Aucun champ pour l\'instant._';
		} else {
			$lignes[] = '| Clé | Libellé | Type | Dans le gabarit |';
			$lignes[] = '| --- | --- | --- | --- |';

			foreach ( $definition['champs'] as $champ ) {
				$lignes[] = sprintf(
					'| `%s` | %s | %s | `%s` |',
					$champ['cle'],
					str_replace( '|', '/', (string) $champ['libelle'] ),
					Blocs_Creator_Champs::type( $champ['type'] )['libelle'] ?? $champ['type'],
					self::appel( $champ )
				);

				foreach ( (array) ( $champ['sous_champs'] ?? array() ) as $enfant ) {
					$lignes[] = sprintf(
						'| ↳ `%s` | %s | %s | `$ligne[\'%s\']` |',
						$enfant['cle'],
						str_replace( '|', '/', (string) $enfant['libelle'] ),
						Blocs_Creator_Champs::type( $enfant['type'] )['libelle'] ?? $enfant['type'],
						$enfant['cle']
					);
				}
			}
		}

		$lignes[] = '';

		/**
		 * Filtre la fiche d'un bloc.
		 *
		 * @param string $fiche      La fiche, en Markdown.
		 * @param array  $definition La définition.
		 */
		return apply_filters( 'blocs_creator_fiche', implode( "\n", $lignes ), $definition );
	}

	/**
	 * Retourne l'appel qui va chercher la valeur d'un champ dans un gabarit.
	 *
	 * @param array $champ Le champ.
	 * @return string
	 */
	private static function appel( $champ ) {
		switch ( $champ['type'] ) {
			case 'repeteur':
				return sprintf( "blocs_creator_boucle( '%s' )", $champ['cle'] );

			case 'image':
				return sprintf( "blocs_creator_image( '%s' )", $champ['cle'] );

			case 'lien':
				return sprintf( "blocs_creator_lien_attrs( '%s' )", $champ['cle'] );

			case 'niveau-titre':
				return sprintf( "blocs_creator_niveau( '%s' )", $champ['cle'] );

			case 'couleur':
				return sprintf( "blocs_creator_couleur( '%s' )", $champ['cle'] );

			case 'blocs-imbriques':
				return 'blocs_creator_contenu()';

			default:
				return sprintf( "blocs_creator_champ( '%s' )", $champ['cle'] );
		}
	}

	/**
	 * Retourne une définition vierge, prête à être remplie.
	 *
	 * @return array
	 */
	public static function vierge() {
		$reglages = blocs_creator()->reglages;

		return array(
			'id'          => 0,
			'source'      => 'genere',
			'statut'      => 'publish',
			'titre'       => '',
			'slug'        => '',
			'espace'      => $reglages->get( 'espace' ),
			'description' => '',
			'icone'       => 'block-default',
			'categorie'   => $reglages->get( 'categorie' ),
			'mots_cles'   => array(),
			'parent'      => array(),
			'supports'    => array(
				'anchor'          => true,
				'align'           => false,
				'customClassName' => true,
				'color'           => false,
				'typography'      => false,
				'spacing'         => false,
				'multiple'        => true,
				'reusable'        => true,
			),
			'apercu'      => 'serveur',
			/*
			 * L'apparition appartient au bloc, pas à la page : elle se choisit
			 * ici une fois, et toutes ses occurrences entrent de la même façon.
			 * Voir Blocs_Creator_Animations.
			 */
			'animation'       => '',
			'animation_duree' => 0,
			'champs'      => array(),
			/*
			 * Les quatre clés qui suivent ne se saisissent pas : elles ne sont
			 * remplies que par la reprise d'un bloc codé (Blocs_Creator_Adoption), et
			 * traversent le formulaire sans être touchées.
			 */
			'attributs'   => array(),
			'assets'      => array(),
			'extras'      => array(),
			'adoption'    => array(),
		);
	}

	/**
	 * Charge une définition depuis un post.
	 *
	 * @param int|WP_Post $post Le post, ou son identifiant.
	 * @return array|null
	 */
	public static function charger( $post ) {
		$post = get_post( $post );

		if ( ! $post instanceof WP_Post || self::TYPE !== $post->post_type ) {
			return null;
		}

		$brut = get_post_meta( $post->ID, self::META, true );

		if ( is_string( $brut ) && '' !== $brut ) {
			$brut = json_decode( $brut, true );
		}

		$definition = wp_parse_args(
			is_array( $brut ) ? $brut : array(),
			self::vierge()
		);

		// Les colonnes natives font foi pour ces trois-là : l'écran d'édition
		// les écrit directement, sans passer par le JSON.
		$definition['id']          = $post->ID;
		$definition['source']      = 'genere';
		$definition['statut']      = $post->post_status;
		$definition['titre']       = $post->post_title;
		$definition['slug']        = $post->post_name ? $post->post_name : sanitize_title( $post->post_title );
		$definition['description'] = $post->post_excerpt;

		return self::normaliser( $definition );
	}

	/**
	 * Retourne toutes les définitions générées.
	 *
	 * @param array $args Arguments de requête supplémentaires.
	 * @return array<int, array>
	 */
	public static function toutes( $args = array() ) {
		$requete = new WP_Query(
			wp_parse_args(
				$args,
				array(
					'post_type'              => self::TYPE,
					'post_status'            => array( 'publish', 'draft' ),
					'posts_per_page'         => 500,
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
				)
			)
		);

		$definitions = array();

		foreach ( $requete->posts as $post ) {
			$definition = self::charger( $post );

			if ( null !== $definition ) {
				$definitions[] = $definition;
			}
		}

		return $definitions;
	}

	/**
	 * Retourne le nom Gutenberg d'une définition : `espace/slug`.
	 *
	 * @param array $definition La définition.
	 * @return string
	 */
	public static function nom( $definition ) {
		$espace = sanitize_key( $definition['espace'] ?? '' );
		$slug   = sanitize_title( $definition['slug'] ?? '' );

		if ( '' === $espace || '' === $slug ) {
			return '';
		}

		return $espace . '/' . $slug;
	}

	/**
	 * Normalise une définition : types corrects, valeurs dans leur plage,
	 * clés de champs uniques.
	 *
	 * Passe obligé de toute définition qui entre dans le plugin, qu'elle
	 * vienne du formulaire, d'un import ou de la base.
	 *
	 * @param array $definition Définition brute.
	 * @return array
	 */
	public static function normaliser( $definition ) {
		$propre = wp_parse_args( (array) $definition, self::vierge() );

		$propre['titre']       = sanitize_text_field( (string) $propre['titre'] );
		$propre['slug']        = sanitize_title( (string) $propre['slug'] );
		$propre['espace']      = sanitize_key( (string) $propre['espace'] );
		$propre['description'] = sanitize_textarea_field( (string) $propre['description'] );
		$propre['icone']       = sanitize_text_field( (string) $propre['icone'] );
		$propre['categorie']   = sanitize_key( (string) $propre['categorie'] );
		$propre['apercu']      = in_array( $propre['apercu'], array( 'serveur', 'formulaire' ), true ) ? $propre['apercu'] : 'serveur';

		$apparition                 = Blocs_Creator_Animations::assainir_reglage(
			array(
				'nom'   => $propre['animation'],
				'duree' => $propre['animation_duree'],
			)
		);
		$propre['animation']        = $apparition['nom'];
		$propre['animation_duree']  = $apparition['duree'];

		if ( '' === $propre['slug'] && '' !== $propre['titre'] ) {
			$propre['slug'] = sanitize_title( $propre['titre'] );
		}

		if ( '' === $propre['espace'] ) {
			$propre['espace'] = blocs_creator()->reglages->get( 'espace' );
		}

		if ( '' === $propre['icone'] ) {
			$propre['icone'] = 'block-default';
		}

		$propre['mots_cles'] = self::normaliser_mots_cles( $propre['mots_cles'] );
		$propre['parent']    = array_values(
			array_filter(
				array_map(
					static function ( $nom ) {
						$nom = trim( (string) $nom );

						return preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $nom ) ? $nom : '';
					},
					(array) $propre['parent']
				)
			)
		);

		$supports = array();

		foreach ( self::vierge()['supports'] as $cle => $defaut ) {
			$supports[ $cle ] = ! empty( $propre['supports'][ $cle ] );
		}

		$propre['supports'] = $supports;
		$propre['champs']   = self::normaliser_champs( $propre['champs'] );

		$propre['attributs'] = self::normaliser_attributs( $propre['attributs'] );
		$propre['assets']    = self::normaliser_assets( $propre['assets'] );
		$propre['extras']    = is_array( $propre['extras'] ) ? $propre['extras'] : array();
		$propre['adoption']  = self::normaliser_adoption( $propre['adoption'] );

		return $propre;
	}

	/**
	 * Nettoie les attributs conservés d'un bloc repris au code.
	 *
	 * Ils sont réenregistrés tels quels auprès de Gutenberg : on vérifie donc
	 * leur forme, pas leur contenu — un `default` peut être n'importe quoi,
	 * c'est le bloc d'origine qui le savait.
	 *
	 * @param mixed $attributs Attributs bruts.
	 * @return array<string, array>
	 */
	private static function normaliser_attributs( $attributs ) {
		$propres = array();

		foreach ( (array) $attributs as $nom => $spec ) {
			$nom = (string) $nom;

			if ( ! preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $nom ) || ! is_array( $spec ) ) {
				continue;
			}

			$type = (string) ( $spec['type'] ?? '' );

			if ( ! in_array( $type, array( 'string', 'number', 'integer', 'boolean', 'array', 'object', 'null' ), true ) ) {
				continue;
			}

			$propre = array( 'type' => $type );

			foreach ( array( 'default', 'source', 'selector', 'attribute', 'query', 'enum', 'items' ) as $cle ) {
				if ( array_key_exists( $cle, $spec ) ) {
					$propre[ $cle ] = $spec[ $cle ];
				}
			}

			$propres[ $nom ] = $propre;
		}

		return $propres;
	}

	/**
	 * Nettoie les identifiants d'assets gardés attachés à un bloc repris.
	 *
	 * @param mixed $assets Assets bruts.
	 * @return array<string, array<int, string>>
	 */
	private static function normaliser_assets( $assets ) {
		$propres = array();

		foreach ( array( 'style', 'editor_style', 'view_script' ) as $sorte ) {
			$liste = array();

			foreach ( (array) ( $assets[ $sorte ] ?? array() ) as $handle ) {
				$handle = sanitize_key( (string) $handle );

				if ( '' !== $handle ) {
					$liste[] = $handle;
				}
			}

			if ( $liste ) {
				$propres[ $sorte ] = array_values( array_unique( $liste ) );
			}
		}

		return $propres;
	}

	/**
	 * Nettoie la trace de reprise d'un bloc codé.
	 *
	 * @param mixed $adoption Données brutes.
	 * @return array
	 */
	private static function normaliser_adoption( $adoption ) {
		$adoption = (array) $adoption;
		$nom      = (string) ( $adoption['nom'] ?? '' );

		if ( ! preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $nom ) ) {
			return array();
		}

		return array(
			'nom'     => $nom,
			'origine' => sanitize_text_field( (string) ( $adoption['origine'] ?? '' ) ),
			'dossier' => (string) ( $adoption['dossier'] ?? '' ),
			'rendu'   => (string) ( $adoption['rendu'] ?? '' ),
			'date'    => sanitize_text_field( (string) ( $adoption['date'] ?? '' ) ),
		);
	}

	/**
	 * Normalise les mots-clés.
	 *
	 * @param mixed $mots Mots-clés, tableau ou texte séparé par des virgules.
	 * @return array<int, string>
	 */
	private static function normaliser_mots_cles( $mots ) {
		if ( is_string( $mots ) ) {
			$mots = explode( ',', $mots );
		}

		$propres = array();

		foreach ( (array) $mots as $mot ) {
			$mot = sanitize_text_field( trim( (string) $mot ) );

			if ( '' !== $mot && ! in_array( $mot, $propres, true ) ) {
				$propres[] = $mot;
			}
		}

		// Gutenberg n'en retient que trois ; en proposer vingt serait mentir.
		return array_slice( $propres, 0, 12 );
	}

	/**
	 * Normalise une liste de champs.
	 *
	 * Deux règles tiennent cette fonction :
	 *
	 *   - une clé est unique dans sa liste, sinon la seconde écraserait la
	 *     première au rendu ;
	 *   - la structure ne s'imbrique qu'une fois. Un répéteur dans un répéteur
	 *     donne une interface que personne ne sait plus lire, et un tableau que
	 *     personne ne sait plus écrire dans un gabarit.
	 *
	 * @param mixed $champs     Liste de champs.
	 * @param int   $profondeur Profondeur courante (0 = racine).
	 * @return array<int, array>
	 */
	public static function normaliser_champs( $champs, $profondeur = 0 ) {
		$propres = array();
		$cles    = array();
		$uniques = array();

		foreach ( (array) $champs as $champ ) {
			if ( ! is_array( $champ ) ) {
				continue;
			}

			$type = sanitize_key( (string) ( $champ['type'] ?? '' ) );

			if ( ! Blocs_Creator_Champs::type_existe( $type ) ) {
				continue;
			}

			$def = Blocs_Creator_Champs::type( $type );

			/*
			 * La structure ne s'imbrique pas. Dans une ligne de répéteur, un
			 * répéteur, un groupe ou des blocs imbriqués n'ont pas de place :
			 * on les écarte plutôt que d'en garder une coquille vide, qui
			 * ajouterait un attribut que rien ne remplirait jamais.
			 */
			if ( $profondeur >= 1 && ( ! empty( $def['sous_champs'] ) || 'blocs-imbriques' === $type ) ) {
				continue;
			}

			// Un bloc n'a qu'un jeu de blocs imbriqués : le second n'aurait
			// nulle part où aller dans $content.
			if ( ! empty( $def['unique'] ) ) {
				if ( isset( $uniques[ $type ] ) ) {
					continue;
				}

				$uniques[ $type ] = true;
			}

			$libelle = sanitize_text_field( (string) ( $champ['libelle'] ?? '' ) );
			$cle     = self::cle_valide( (string) ( $champ['cle'] ?? '' ), $libelle, $cles );

			if ( '' === $cle ) {
				continue;
			}

			$cles[] = $cle;

			$largeur = (int) ( $champ['largeur'] ?? 100 );

			$propre = array(
				'cle'         => $cle,
				'libelle'     => '' !== $libelle ? $libelle : $cle,
				'type'        => $type,
				'aide'        => sanitize_text_field( (string) ( $champ['aide'] ?? '' ) ),
				'emplacement' => in_array( $champ['emplacement'] ?? '', array( 'bloc', 'panneau' ), true ) ? $champ['emplacement'] : 'bloc',
				'largeur'     => in_array( $largeur, array( 33, 50, 100 ), true ) ? $largeur : 100,
				'options'     => self::normaliser_options( $type, (array) ( $champ['options'] ?? array() ) ),
			);

			if ( ! empty( $def['sous_champs'] ) ) {
				$propre['sous_champs'] = self::normaliser_champs( $champ['sous_champs'] ?? array(), $profondeur + 1 );
			}

			$propres[] = $propre;
		}

		return $propres;
	}

	/**
	 * Nettoie les réglages d'un champ, en ne gardant que ceux que son type
	 * propose.
	 *
	 * @param string $type    Type du champ.
	 * @param array  $options Réglages bruts.
	 * @return array
	 */
	private static function normaliser_options( $type, $options ) {
		$def     = Blocs_Creator_Champs::type( $type );
		$permis  = (array) ( $def['reglages'] ?? array() );
		$propres = array();

		foreach ( $permis as $reglage ) {
			if ( ! array_key_exists( $reglage, $options ) ) {
				continue;
			}

			$valeur = $options[ $reglage ];

			switch ( $reglage ) {
				case 'choix':
				case 'message':
				case 'defaut':
					$propres[ $reglage ] = is_array( $valeur )
						? array_map( 'sanitize_text_field', $valeur )
						: sanitize_textarea_field( (string) $valeur );
					break;

				case 'min':
				case 'max':
				case 'pas':
					$propres[ $reglage ] = '' === $valeur ? '' : (float) $valeur;
					break;

				case 'lignes':
				case 'maxlength':
				case 'min_lignes':
				case 'max_lignes':
				case 'lignes_depart':
				case 'max_medias':
				case 'max_contenus':
				case 'defaut_niveau':
				case 'niveau_min':
					$propres[ $reglage ] = max( 0, (int) $valeur );
					break;

				case 'curseur':
				case 'point_focal':
				case 'palette_seule':
				case 'terme_unique':
				case 'verrou_gabarit':
				case 'defaut_bascule':
					$propres[ $reglage ] = ! empty( $valeur );
					break;

				case 'types_contenu':
				case 'types_fichier':
				case 'blocs_autorises':
				case 'formats':
					if ( is_string( $valeur ) ) {
						$valeur = preg_split( '/[\s,]+/', $valeur );
					}

					$propres[ $reglage ] = array_values(
						array_filter(
							array_map(
								static function ( $item ) {
									return sanitize_text_field( trim( (string) $item ) );
								},
								(array) $valeur
							)
						)
					);
					break;

				case 'image':
					$propres[ $reglage ] = self::cle_valide( (string) $valeur );
					break;

				case 'gabarit_interne':
					$propres[ $reglage ] = is_string( $valeur ) ? trim( $valeur ) : '';
					break;

				default:
					$propres[ $reglage ] = sanitize_text_field( (string) $valeur );
			}
		}

		// Un niveau de titre par défaut plus haut que le minimum autorisé
		// serait corrigé en silence au rendu : autant le corriger ici.
		if ( 'niveau-titre' === $type ) {
			$minimum                  = max( 1, min( 6, (int) ( $propres['niveau_min'] ?? 2 ) ) );
			$propres['niveau_min']    = $minimum;
			$propres['defaut_niveau'] = max( $minimum, min( 6, (int) ( $propres['defaut_niveau'] ?? 2 ) ) );
			$propres['defaut']        = $propres['defaut_niveau'];
		}

		if ( 'bascule' === $type ) {
			$propres['defaut'] = ! empty( $propres['defaut_bascule'] );
		}

		return $propres;
	}

	/**
	 * Retourne une clé de champ valide et unique.
	 *
	 * Les clés deviennent des noms d'attributs Gutenberg et des index de
	 * tableau dans les gabarits : on les veut en minuscules, sans accent, sans
	 * tiret — `mon_champ`, pas `mon-champ`, pour rester écrivable en PHP.
	 *
	 * @param string $cle     Clé proposée.
	 * @param string $libelle Libellé, d'où déduire la clé si besoin.
	 * @param array  $prises  Clés déjà utilisées.
	 * @return string
	 */
	public static function cle_valide( $cle, $libelle = '', $prises = array() ) {
		/*
		 * La casse est conservée. Une clé saisie à la main sera presque
		 * toujours en minuscules, mais un bloc repris au code arrive avec les
		 * noms d'attributs qu'il avait — `imageId`, `nouvelOnglet` — et son
		 * fichier de rendu les lit sous cette orthographe-là. Les abaisser
		 * ici, c'est casser le rendu sans rien dire.
		 */
		$cle = remove_accents( (string) $cle );
		$cle = preg_replace( '/[^A-Za-z0-9_]/', '_', $cle );
		$cle = trim( (string) $cle, '_' );

		if ( '' === $cle && '' !== $libelle ) {
			$cle = self::cle_valide( $libelle );
		}

		if ( '' === $cle ) {
			return '';
		}

		// Un attribut ne peut pas commencer par un chiffre s'il doit aussi
		// servir de nom de variable lisible dans un gabarit.
		if ( preg_match( '/^[0-9]/', $cle ) ) {
			$cle = 'champ_' . $cle;
		}

		/*
		 * Gutenberg pose ses propres attributs sur chaque bloc. Un champ qui
		 * s'appellerait `align` écraserait l'alignement choisi dans la barre
		 * d'outils, et le bloc perdrait la moitié de ses réglages sans qu'on
		 * comprenne pourquoi. On préfixe plutôt que d'interdire.
		 */
		$reserves = array( 'align', 'anchor', 'className', 'classname', 'style', 'lock', 'metadata', 'content', 'fontSize', 'fontsize', 'textColor', 'textcolor', 'backgroundColor', 'backgroundcolor', 'gradient', 'borderColor', 'bordercolor' );

		if ( in_array( strtolower( $cle ), array_map( 'strtolower', $reserves ), true ) ) {
			$cle = 'champ_' . $cle;
		}

		$base     = $cle;
		$compteur = 2;

		$prises = array_map( 'strtolower', (array) $prises );

		while ( in_array( strtolower( $cle ), $prises, true ) ) {
			$cle = $base . '_' . $compteur;
			++$compteur;
		}

		return $cle;
	}

	/**
	 * Enregistre une définition.
	 *
	 * @param array $definition Définition normalisée ou non.
	 * @param int   $post_id    Post à mettre à jour, 0 pour en créer un.
	 * @return int|WP_Error L'identifiant du post.
	 */
	public static function enregistrer( $definition, $post_id = 0 ) {
		$definition = self::normaliser( $definition );

		if ( '' === $definition['titre'] ) {
			return new WP_Error( 'bc_titre_manquant', __( 'Un bloc a besoin d\'un nom.', 'blocs-creator' ) );
		}

		if ( '' === $definition['slug'] ) {
			return new WP_Error( 'bc_slug_manquant', __( 'Un bloc a besoin d\'un identifiant.', 'blocs-creator' ) );
		}

		$conflit = self::conflit( $definition, $post_id );

		if ( is_wp_error( $conflit ) ) {
			return $conflit;
		}

		$donnees = array(
			'post_type'    => self::TYPE,
			'post_title'   => $definition['titre'],
			'post_name'    => $definition['slug'],
			'post_excerpt' => $definition['description'],
			'post_status'  => in_array( $definition['statut'], array( 'publish', 'draft' ), true ) ? $definition['statut'] : 'publish',
		);

		if ( $post_id > 0 ) {
			$donnees['ID'] = $post_id;
			$resultat      = wp_update_post( $donnees, true );
		} else {
			$resultat = wp_insert_post( $donnees, true );
		}

		if ( is_wp_error( $resultat ) ) {
			return $resultat;
		}

		$post_id = (int) $resultat;

		// Le titre, le slug et la description vivent dans les colonnes du
		// post : les répéter dans le JSON, c'est préparer une divergence.
		$a_stocker = $definition;
		unset( $a_stocker['id'], $a_stocker['titre'], $a_stocker['slug'], $a_stocker['description'], $a_stocker['statut'], $a_stocker['source'] );

		update_post_meta(
			$post_id,
			self::META,
			wp_slash( wp_json_encode( $a_stocker, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) )
		);

		Blocs_Creator_Usage::vider_cache();

		/**
		 * Se déclenche après l'enregistrement d'une définition de bloc.
		 *
		 * @param int   $post_id    Identifiant du post.
		 * @param array $definition Définition normalisée.
		 */
		do_action( 'blocs_creator_definition_enregistree', $post_id, self::charger( $post_id ) );

		return $post_id;
	}

	/**
	 * Vérifie qu'aucun autre bloc ne porte déjà ce nom.
	 *
	 * Deux blocs de même nom, c'est le second qui gagne au hasard de l'ordre
	 * d'enregistrement, et des pages qui changent d'aspect sans prévenir.
	 *
	 * @param array $definition Définition candidate.
	 * @param int   $post_id    Post en cours d'édition.
	 * @return true|WP_Error
	 */
	private static function conflit( $definition, $post_id = 0 ) {
		$nom = self::nom( $definition );

		foreach ( self::toutes() as $autre ) {
			if ( (int) $autre['id'] === (int) $post_id ) {
				continue;
			}

			if ( self::nom( $autre ) === $nom ) {
				return new WP_Error(
					'bc_nom_pris',
					sprintf(
						/* translators: %s: nom complet du bloc, par exemple monsite/banniere. */
						__( 'Le bloc « %s » existe déjà. Changez son identifiant ou son espace de noms.', 'blocs-creator' ),
						$nom
					)
				);
			}
		}

		$reprend = (string) ( $definition['adoption']['nom'] ?? '' );

		foreach ( blocs_creator()->registre->blocs_codes() as $code ) {
			if ( $code['nom'] === $nom ) {
				// Sauf si cette définition EST la reprise de ce bloc-là :
				// porter son nom est tout l'objet de l'opération.
				if ( $code['nom'] === $reprend ) {
					continue;
				}

				return new WP_Error(
					'bc_nom_pris_code',
					sprintf(
						/* translators: %s: nom complet du bloc. */
						__( '« %s » est déjà le nom d\'un bloc codé. Choisissez-en un autre.', 'blocs-creator' ),
						$nom
					)
				);
			}
		}

		return true;
	}

	/**
	 * Duplique une définition.
	 *
	 * @param int $post_id Post à dupliquer.
	 * @return int|WP_Error
	 */
	public static function dupliquer( $post_id ) {
		$definition = self::charger( $post_id );

		if ( null === $definition ) {
			return new WP_Error( 'bc_introuvable', __( 'Ce bloc est introuvable.', 'blocs-creator' ) );
		}

		$definition['titre']  = sprintf(
			/* translators: %s: nom du bloc d'origine. */
			__( '%s (copie)', 'blocs-creator' ),
			$definition['titre']
		);
		$definition['slug']   = self::slug_libre( $definition['slug'] . '-copie', $definition['espace'] );
		$definition['statut'] = 'draft';

		/*
		 * Une copie n'est la reprise de rien. Lui laisser l'acte de naissance
		 * de l'original ferait croire qu'un dossier de code l'attend : « Rendre
		 * au code » la renverrait vers un bloc qui n'est pas le sien, et le
		 * jour où l'on supprime ce dossier, elle devient insupprimable.
		 */
		$definition['adoption'] = array();

		return self::enregistrer( $definition, 0 );
	}

	/**
	 * Retourne un slug encore libre dans cet espace de noms.
	 *
	 * @param string $slug   Slug souhaité.
	 * @param string $espace Espace de noms.
	 * @return string
	 */
	public static function slug_libre( $slug, $espace ) {
		$slug  = sanitize_title( $slug );
		$pris  = array();
		$base  = $slug;
		$index = 2;

		foreach ( self::toutes() as $autre ) {
			if ( $autre['espace'] === $espace ) {
				$pris[] = $autre['slug'];
			}
		}

		foreach ( blocs_creator()->registre->blocs_codes() as $code ) {
			$pris[] = $code['slug'];
		}

		while ( in_array( $slug, $pris, true ) ) {
			$slug = $base . '-' . $index;
			++$index;
		}

		return $slug;
	}

	/**
	 * Prépare une définition pour l'export JSON.
	 *
	 * @param array $definition La définition.
	 * @return array
	 */
	public static function vers_tableau( $definition ) {
		$export = self::normaliser( $definition );

		unset( $export['id'], $export['source'], $export['statut'] );

		return $export;
	}
}
