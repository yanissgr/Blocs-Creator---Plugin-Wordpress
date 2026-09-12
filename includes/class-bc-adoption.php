<?php
/**
 * La reprise en main : faire d'un bloc codé un bloc modifiable.
 *
 * Un bloc écrit à la main est un dossier : un `block.json` qui déclare ses
 * attributs, un `rendu.php` qui le dessine, du JavaScript qui l'édite. On ne
 * peut ni lui ajouter un champ ni lui changer son icône sans ouvrir un
 * éditeur de code.
 *
 * Reprendre la main, c'est traduire ce dossier en définition — la même chose
 * qu'on aurait saisie dans le back-office — et poser son `rendu.php` dans le
 * thème comme gabarit. Après quoi le bloc se modifie comme les autres.
 *
 * Trois promesses tiennent cette classe, et rien ne doit les entamer :
 *
 *   1. LE NOM NE BOUGE PAS. `mon-pack/banniere` reste `mon-pack/banniere` : les
 *      pages qui le portent ne voient pas la différence.
 *   2. LE DESSIN NE BOUGE PAS. Le `rendu.php` est recopié tel quel, et les
 *      feuilles de style que le bloc déclarait restent attachées.
 *   3. RIEN NE SE PERD. Un attribut qu'aucun type de champ ne sait porter —
 *      un point focal, une structure à soi — est conservé tel quel plutôt
 *      qu'abandonné. Les variantes et les styles de bloc aussi.
 *
 * Et l'opération se défait : « Rendre au code » supprime la définition, le
 * dossier reprend la main, le gabarit recopié reste dans le thème sans gêner.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Conversion d'un bloc codé en définition modifiable.
 */
class BC_Adoption {

	/**
	 * Noms d'attributs que WordPress se réserve, et qu'un champ ne doit pas
	 * reprendre : ils viennent des `supports`, pas d'une saisie.
	 *
	 * @var array<int, string>
	 */
	const ATTRIBUTS_NATIFS = array( 'align', 'anchor', 'className', 'style', 'lock', 'metadata', 'content', 'fontSize', 'textColor', 'backgroundColor', 'gradient', 'borderColor', 'layout' );

	/**
	 * Retourne les blocs codés déjà repris, par nom.
	 *
	 * La source de vérité, ce sont les définitions elles-mêmes : une qui porte
	 * une clé `adoption` a repris le bloc codé du même nom. Aucune option en
	 * base à tenir à jour, donc aucune occasion de la voir diverger.
	 *
	 * @return array<string, array> Définition, par nom de bloc.
	 */
	public static function reprises() {
		$reprises = array();

		foreach ( BC_Definition::toutes() as $definition ) {
			if ( empty( $definition['adoption']['nom'] ) ) {
				continue;
			}

			$reprises[ (string) $definition['adoption']['nom'] ] = $definition;
		}

		return $reprises;
	}

	/**
	 * Le bloc codé a-t-il été repris ?
	 *
	 * @param string $nom Nom complet du bloc.
	 * @return bool
	 */
	public static function est_reprise( $nom ) {
		$reprises = self::reprises();

		return isset( $reprises[ $nom ] );
	}

	/* ------------------------------------------------------------------ *
	 * Traduction
	 * ------------------------------------------------------------------ */

	/**
	 * Traduit un bloc codé en définition, sans rien écrire.
	 *
	 * Sert deux fois : à montrer ce que la reprise va donner, avant de la
	 * confirmer, et à la faire. Les deux voient donc exactement la même chose.
	 *
	 * @param array $code Un bloc codé, tel que le registre le décrit.
	 * @return array La définition, normalisée.
	 */
	public static function traduire( $code ) {
		$meta = self::manifeste( $code );

		/*
		 * Un bloc codé peut dire lui-même ce que ses attributs doivent devenir,
		 * dans une clé `blocsCreator` de son `block.json`. C'est la seule façon
		 * d'obtenir une reprise EXACTE : un `pointFocal` redevient un point de
		 * cadrage, un `sens` redevient deux boutons, et rien ne finit en champ
		 * de texte faute de mieux. Sans cette clé, on lit le manifeste au
		 * mieux — voir champ() plus bas.
		 */
		$declare = isset( $meta['blocsCreator'] ) && is_array( $meta['blocsCreator'] ) ? $meta['blocsCreator'] : array();

		$definition = BC_Definition::vierge();

		$definition['titre']       = (string) ( $meta['title'] ?? $code['titre'] );
		$definition['slug']        = (string) $code['slug'];
		$definition['espace']      = (string) $code['espace'];
		$definition['description'] = (string) ( $meta['description'] ?? '' );
		$definition['icone']       = self::icone( $meta );
		$definition['categorie']   = (string) ( $meta['category'] ?? $definition['categorie'] );
		$definition['mots_cles']   = (array) ( $meta['keywords'] ?? array() );
		$definition['parent']      = (array) ( $meta['parent'] ?? array() );
		$definition['supports']    = self::supports( (array) ( $meta['supports'] ?? array() ) );
		$definition['apercu']      = 'serveur';

		if ( ! empty( $declare['champs'] ) && is_array( $declare['champs'] ) ) {
			$champs    = $declare['champs'];
			$conserves = array();
		} else {
			list( $champs, $conserves ) = self::champs( (array) ( $meta['attributes'] ?? array() ) );
		}

		$definition['animation']       = (string) ( $declare['animation'] ?? '' );
		$definition['animation_duree'] = (int) ( $declare['animation_duree'] ?? 0 );

		$imbrique = false;

		foreach ( $champs as $champ ) {
			if ( 'blocs-imbriques' === ( $champ['type'] ?? '' ) ) {
				$imbrique = true;
			}
		}

		$enfants = $imbrique ? array() : self::enfants( $code['nom'] );

		if ( $enfants ) {
			$champs[] = array(
				'cle'         => 'interieur',
				'libelle'     => __( 'Contenu du bloc', 'blocs-creator' ),
				'type'        => 'blocs-imbriques',
				'aide'        => '',
				'emplacement' => 'bloc',
				'largeur'     => 100,
				'options'     => array(
					'blocs_autorises' => $enfants,
					'orientation'     => 'vertical',
				),
			);
		}

		$definition['champs']    = $champs;
		$definition['attributs'] = $conserves;
		$definition['assets']    = self::assets( $meta );
		$definition['extras']    = self::extras( $meta );

		$definition['adoption'] = array(
			'nom'     => (string) $code['nom'],
			'origine' => (string) ( $code['origine_nom'] ?? $code['origine'] ?? '' ),
			'dossier' => (string) $code['dossier'],
			'rendu'   => (string) ( $code['rendu'] ?? '' ),
			'date'    => current_time( 'mysql' ),
		);

		return BC_Definition::normaliser( $definition );
	}

	/**
	 * Relit le `block.json` d'un bloc codé.
	 *
	 * @param array $code Le bloc codé.
	 * @return array
	 */
	private static function manifeste( $code ) {
		$fichier = (string) ( $code['manifeste'] ?? '' );

		if ( '' === $fichier || ! file_exists( $fichier ) ) {
			return array();
		}

		$meta = json_decode( (string) file_get_contents( $fichier ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Retourne l'icône du manifeste, si c'est un nom de Dashicon.
	 *
	 * Un bloc peut déclarer un SVG entier comme icône. Il ne rentrerait pas
	 * dans le sélecteur d'icônes, qui ne connaît que des noms : on se replie
	 * alors sur l'icône par défaut, que l'écran permettra de changer.
	 *
	 * @param array $meta Le manifeste.
	 * @return string
	 */
	private static function icone( $meta ) {
		$icone = (string) ( $meta['icon'] ?? '' );

		return preg_match( '/^[a-z0-9-]+$/', $icone ) ? $icone : 'block-default';
	}

	/**
	 * Traduit les `supports` du manifeste en cases de la définition.
	 *
	 * @param array $supports Les supports déclarés.
	 * @return array
	 */
	private static function supports( $supports ) {
		return array(
			'anchor'          => ! empty( $supports['anchor'] ),
			'align'           => ! empty( $supports['align'] ),
			'customClassName' => ! array_key_exists( 'customClassName', $supports ) || ! empty( $supports['customClassName'] ),
			'color'           => ! empty( $supports['color'] ),
			'typography'      => ! empty( $supports['typography'] ),
			'spacing'         => ! empty( $supports['spacing'] ),
			'multiple'        => ! array_key_exists( 'multiple', $supports ) || ! empty( $supports['multiple'] ),
			'reusable'        => ! array_key_exists( 'reusable', $supports ) || ! empty( $supports['reusable'] ),
		);
	}

	/**
	 * Retourne les identifiants d'assets à garder attachés au bloc.
	 *
	 * Le script d'éditeur, lui, n'est pas repris : c'est justement celui que
	 * la reprise remplace. Les feuilles de style le sont — sans elles, le bloc
	 * changerait d'aspect, ce qui est exactement ce qu'on a promis d'éviter.
	 *
	 * @param array $meta Le manifeste.
	 * @return array<string, array<int, string>>
	 */
	private static function assets( $meta ) {
		$prendre = static function ( $valeur ) {
			$liste = array();

			foreach ( (array) $valeur as $handle ) {
				$handle = (string) $handle;

				// Un « file:./… » désigne un fichier du dossier du bloc, que
				// WordPress compilait lui-même : il n'a plus de sens une fois
				// le bloc sorti de son dossier.
				if ( '' !== $handle && ! str_starts_with( $handle, 'file:' ) ) {
					$liste[] = $handle;
				}
			}

			return array_values( array_unique( $liste ) );
		};

		return array(
			'style'        => $prendre( $meta['style'] ?? array() ),
			'editor_style' => $prendre( $meta['editorStyle'] ?? array() ),
			'view_script'  => $prendre( $meta['viewScript'] ?? array() ),
		);
	}

	/**
	 * Retourne ce que le manifeste déclare et que la définition ne sait pas
	 * dire : variantes, styles de bloc, exemple d'inséreur.
	 *
	 * On le transporte tel quel jusqu'à `register_block_type()`. Le
	 * constructeur ne le montre pas — il n'y a rien à y régler — mais rien ne
	 * se perd.
	 *
	 * @param array $meta Le manifeste.
	 * @return array
	 */
	private static function extras( $meta ) {
		$extras = array();

		foreach ( array( 'variations', 'styles', 'example', 'providesContext', 'usesContext', 'ancestor', 'allowedBlocks' ) as $cle ) {
			if ( ! empty( $meta[ $cle ] ) ) {
				$extras[ $cle ] = $meta[ $cle ];
			}
		}

		return $extras;
	}

	/**
	 * Retourne les blocs codés qui se déclarent enfants de celui-ci.
	 *
	 * C'est ainsi qu'on devine qu'un bloc en accueille d'autres : son
	 * `block.json` ne le dit pas — les blocs imbriqués se déclarent en
	 * JavaScript — mais ses enfants, eux, le disent par leur `parent`.
	 *
	 * @param string $nom Nom du bloc.
	 * @return array<int, string>
	 */
	private static function enfants( $nom ) {
		$enfants = array();

		foreach ( blocs_creator()->registre->blocs_codes() as $autre ) {
			$parents = array();

			$manifeste = self::manifeste( $autre );

			if ( ! empty( $manifeste['parent'] ) ) {
				$parents = (array) $manifeste['parent'];
			}

			if ( in_array( $nom, $parents, true ) ) {
				$enfants[] = (string) $autre['nom'];
			}
		}

		return $enfants;
	}

	/* ------------------------------------------------------------------ *
	 * Attributs
	 * ------------------------------------------------------------------ */

	/**
	 * Traduit les attributs d'un manifeste en champs.
	 *
	 * Retourne deux listes : les champs, et les attributs conservés tels
	 * quels. Un attribut passe dans la seconde quand aucun type de champ ne
	 * sait le porter sans en changer la forme — car en changer la forme, c'est
	 * casser le `rendu.php` qui le lit.
	 *
	 * @param array $attributs Les attributs déclarés.
	 * @return array{0: array<int, array>, 1: array<string, array>}
	 */
	private static function champs( $attributs ) {
		$champs    = array();
		$conserves = array();

		foreach ( $attributs as $nom => $spec ) {
			$nom  = (string) $nom;
			$spec = is_array( $spec ) ? $spec : array();

			if ( in_array( $nom, self::ATTRIBUTS_NATIFS, true ) ) {
				continue;
			}

			// Un attribut qui se lit dans le balisage enregistré appartient au
			// `save` du bloc, pas à un champ : le reprendre le viderait.
			if ( isset( $spec['source'] ) ) {
				$conserves[ $nom ] = self::conserver( $spec );
				continue;
			}

			$champ = self::champ( $nom, $spec );

			if ( null === $champ ) {
				$conserves[ $nom ] = self::conserver( $spec );
				continue;
			}

			$champs[] = $champ;
		}

		return array( $champs, $conserves );
	}

	/**
	 * Réduit une déclaration d'attribut à ce qu'on en réenregistrera.
	 *
	 * @param array $spec Déclaration du manifeste.
	 * @return array
	 */
	private static function conserver( $spec ) {
		$garde = array();

		foreach ( array( 'type', 'default', 'source', 'selector', 'attribute', 'query', 'enum', 'items' ) as $cle ) {
			if ( array_key_exists( $cle, $spec ) ) {
				$garde[ $cle ] = $spec[ $cle ];
			}
		}

		return $garde;
	}

	/**
	 * Traduit un attribut en champ, ou retourne null s'il faut le conserver.
	 *
	 * Les règles sont lisibles dans l'ordre où elles sont écrites, de la plus
	 * précise à la plus générale. Aucune n'est devinée au petit bonheur : un
	 * attribut qu'on ne sait pas nommer devient du texte, jamais autre chose.
	 *
	 * @param string $nom  Nom de l'attribut.
	 * @param array  $spec Déclaration.
	 * @return array|null
	 */
	private static function champ( $nom, $spec ) {
		$type   = (string) ( $spec['type'] ?? 'string' );
		$defaut = $spec['default'] ?? null;
		$enum   = (array) ( $spec['enum'] ?? array() );

		$options = array();

		if ( 'boolean' === $type ) {
			$sorte              = 'bascule';
			$options['defaut_bascule'] = ! empty( $defaut );
		} elseif ( 'number' === $type || 'integer' === $type ) {
			if ( preg_match( '/^(niveau|level)/i', $nom ) ) {
				$sorte                    = 'niveau-titre';
				$options['defaut_niveau'] = max( 1, min( 6, (int) $defaut ) );
				$options['niveau_min']    = max( 1, min( 6, (int) $defaut ) );
			} elseif ( preg_match( '/(image|media|visuel|photo|illustration|logo|vignette|fichier)/i', $nom ) ) {
				$sorte = 'image';
			} else {
				$sorte             = 'nombre';
				$options['defaut'] = is_numeric( $defaut ) ? (float) $defaut : 0;
			}
		} elseif ( 'string' === $type ) {
			if ( $enum ) {
				$sorte             = count( $enum ) <= 4 ? 'boutons' : 'liste';
				$options['choix']  = implode( "\n", array_map( 'strval', $enum ) );
				$options['defaut'] = (string) $defaut;
			} elseif ( preg_match( '/(description|chapo|intro|resume|extrait|message|contenu)/i', $nom ) ) {
				$sorte             = 'texte-long';
				$options['defaut'] = (string) $defaut;
				$options['lignes'] = 3;
			} elseif ( preg_match( '/(titre|title|texte|libelle|label|legende|bouton|accroche)/i', $nom ) ) {
				$sorte             = 'texte-riche';
				$options['defaut'] = (string) $defaut;
			} else {
				$sorte             = 'texte';
				$options['defaut'] = (string) $defaut;
			}
		} else {
			// Tableaux et objets : une galerie, un point focal, une structure
			// à soi. Rien ici ne permet de trancher — on conserve.
			return null;
		}

		return array(
			'cle'         => $nom,
			'libelle'     => self::libelle( $nom ),
			'type'        => $sorte,
			'aide'        => '',
			'emplacement' => in_array( $sorte, array( 'texte-riche', 'texte-long' ), true ) ? 'bloc' : 'panneau',
			'largeur'     => 100,
			'options'     => $options,
		);
	}

	/**
	 * Rend lisible un nom d'attribut : `nouvelOnglet` devient « Nouvel onglet ».
	 *
	 * @param string $nom Nom de l'attribut.
	 * @return string
	 */
	private static function libelle( $nom ) {
		$mots = preg_replace( '/([a-z0-9])([A-Z])/', '$1 $2', $nom );
		$mots = str_replace( array( '_', '-' ), ' ', (string) $mots );
		$mots = trim( preg_replace( '/\s+/', ' ', $mots ) );

		return ucfirst( strtolower( $mots ) );
	}

	/* ------------------------------------------------------------------ *
	 * Reprendre, rendre
	 * ------------------------------------------------------------------ */

	/**
	 * Reprend un bloc codé : écrit sa définition et recopie son gabarit.
	 *
	 * @param string $nom Nom complet du bloc codé.
	 * @return array{id:int, gabarit:string, avertissements:array<int, string>}|WP_Error
	 */
	public static function reprendre( $nom ) {
		$code = self::bloc_code( $nom );

		if ( null === $code ) {
			return new WP_Error( 'bc_code_introuvable', __( 'Ce bloc codé est introuvable.', 'blocs-creator' ) );
		}

		if ( self::est_reprise( $nom ) ) {
			return new WP_Error( 'bc_deja_reprise', __( 'Ce bloc a déjà été repris.', 'blocs-creator' ) );
		}

		$definition = self::traduire( $code );
		$post_id    = BC_Definition::enregistrer( $definition );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$avertissements = array();
		$gabarit        = '';

		$existant = BC_Gabarits::chemin( $definition );

		if ( '' !== $existant ) {
			$gabarit = $existant;

			$avertissements[] = sprintf(
				/* translators: %s: chemin du fichier. */
				__( 'Un gabarit existait déjà à %s : il n\'a pas été touché, et c\'est lui qui dessine le bloc.', 'blocs-creator' ),
				BC_Gabarits::chemin_court( $existant )
			);
		} else {
			$resultat = BC_Gabarits::ecrire(
				BC_Gabarits::chemin_prefere( $definition, true ),
				self::code_gabarit( $code, $definition )
			);

			if ( is_wp_error( $resultat ) ) {
				$avertissements[] = $resultat->get_error_message();
			} else {
				$gabarit = $resultat;
			}
		}

		if ( ! empty( $definition['attributs'] ) ) {
			$avertissements[] = sprintf(
				/* translators: %s: liste de noms d'attributs. */
				__( 'Réglages conservés tels quels, sans formulaire pour les modifier : %s.', 'blocs-creator' ),
				implode( ', ', array_keys( $definition['attributs'] ) )
			);
		}

		BC_Usage::vider_cache();

		return array(
			'id'             => (int) $post_id,
			'gabarit'        => (string) $gabarit,
			'avertissements' => $avertissements,
		);
	}

	/**
	 * Retourne les blocs codés qui n'ont pas encore été repris.
	 *
	 * @return array<int, array>
	 */
	public static function a_reprendre() {
		$reprises = self::reprises();
		$restants = array();

		foreach ( blocs_creator()->registre->blocs_codes() as $code ) {
			if ( ! isset( $reprises[ $code['nom'] ] ) ) {
				$restants[] = $code;
			}
		}

		return $restants;
	}

	/**
	 * Reprend d'un coup tous les blocs codés qui restent.
	 *
	 * C'est le geste qu'on fait une fois, au début : après lui, il n'y a plus
	 * de blocs codés sur le site, seulement des blocs qui se modifient. Chaque
	 * bloc est repris pour son compte — l'échec de l'un n'empêche pas les
	 * autres, et le compte rendu dit lesquels.
	 *
	 * @return array{repris:array<int, string>, echecs:array<int, string>, avertissements:array<int, string>}
	 */
	public static function reprendre_tout() {
		$repris  = array();
		$echecs  = array();
		$notes   = array();

		foreach ( self::a_reprendre() as $code ) {
			$resultat = self::reprendre( $code['nom'] );

			if ( is_wp_error( $resultat ) ) {
				$echecs[] = sprintf( '%s (%s)', $code['titre'], $resultat->get_error_message() );
				continue;
			}

			$repris[] = $code['nom'];

			foreach ( $resultat['avertissements'] as $note ) {
				$notes[] = sprintf( '%s : %s', $code['titre'], $note );
			}
		}

		return array(
			'repris'         => $repris,
			'echecs'         => $echecs,
			'avertissements' => $notes,
		);
	}

	/**
	 * Rend un bloc au code : supprime la définition, le dossier reprend.
	 *
	 * Le gabarit recopié dans le thème n'est pas supprimé — il n'appartient
	 * plus au plugin dès qu'il est écrit. Il ne sert simplement plus à rien,
	 * et ne gêne pas.
	 *
	 * @param int $post_id Identifiant de la définition.
	 * @return true|WP_Error
	 */
	public static function rendre_au_code( $post_id ) {
		$definition = BC_Definition::charger( $post_id );

		if ( null === $definition || empty( $definition['adoption']['nom'] ) ) {
			return new WP_Error( 'bc_pas_une_reprise', __( 'Ce bloc n\'a pas été repris à un bloc codé.', 'blocs-creator' ) );
		}

		if ( null === self::bloc_code( (string) $definition['adoption']['nom'] ) ) {
			return new WP_Error(
				'bc_code_disparu',
				__( 'Le bloc codé d\'origine n\'est plus sur le disque : lui rendre la main laisserait les pages sans bloc. Supprimez plutôt cette définition si vous n\'en voulez plus.', 'blocs-creator' )
			);
		}

		wp_delete_post( (int) $post_id, true );

		BC_Usage::vider_cache();

		return true;
	}

	/**
	 * Retourne les définitions nées d'une reprise.
	 *
	 * C'est la liste qu'on rend au code d'un coup : celles qui ont un dossier
	 * derrière elles, et qui peuvent donc disparaître sans rien casser.
	 *
	 * @return array<int, array>
	 */
	public static function reprises_en_cours() {
		$reprises = array();

		foreach ( BC_Definition::toutes() as $definition ) {
			if ( empty( $definition['adoption']['nom'] ) ) {
				continue;
			}

			if ( null === self::bloc_code( (string) $definition['adoption']['nom'] ) ) {
				continue;
			}

			$reprises[] = $definition;
		}

		return $reprises;
	}

	/**
	 * Rend au code toutes les définitions qui en viennent.
	 *
	 * L'inverse exact de reprendre_tout(). Une reprise dont le dossier a
	 * disparu n'est pas touchée : la supprimer laisserait les pages sans bloc.
	 *
	 * @return array{rendus:array<int, string>, echecs:array<int, string>}
	 */
	public static function rendre_tout_au_code() {
		$rendus = array();
		$echecs = array();

		foreach ( self::reprises_en_cours() as $definition ) {
			$resultat = self::rendre_au_code( (int) $definition['id'] );

			if ( is_wp_error( $resultat ) ) {
				$echecs[] = sprintf( '%s (%s)', $definition['titre'], $resultat->get_error_message() );
				continue;
			}

			$rendus[] = BC_Definition::nom( $definition );
		}

		return array(
			'rendus' => $rendus,
			'echecs' => $echecs,
		);
	}

	/**
	 * Retourne un bloc codé par son nom.
	 *
	 * @param string $nom Nom complet du bloc.
	 * @return array|null
	 */
	public static function bloc_code( $nom ) {
		foreach ( blocs_creator()->registre->blocs_codes() as $code ) {
			if ( $code['nom'] === $nom ) {
				return $code;
			}
		}

		return null;
	}

	/**
	 * Compose le gabarit d'un bloc repris.
	 *
	 * Le `rendu.php` d'origine est recopié tel quel, précédé d'un en-tête qui
	 * dit d'où il vient. Tel quel : il lit `$attributes`, que le plugin lui
	 * passe sous le même nom, et il continue donc de dessiner exactement ce
	 * qu'il dessinait.
	 *
	 * @param array $code       Le bloc codé.
	 * @param array $definition La définition qui en est tirée.
	 * @return string
	 */
	public static function code_gabarit( $code, $definition ) {
		$rendu = (string) ( $code['rendu'] ?? '' );

		$entete = sprintf(
			"<?php\n/**\n * Gabarit du bloc « %s » (%s).\n *\n * Repris du bloc codé %s le %s par Blocs Creator.\n * Ce fichier vous appartient : le plugin ne le réécrira jamais.\n *\n * @var array    \$attributes Les valeurs brutes, telles qu'enregistrées.\n * @var string   \$content    Les blocs imbriqués, déjà rendus.\n * @var WP_Block \$block      L'instance du bloc.\n * @var array    \$champs     Les valeurs prêtes à l'emploi, par clé.\n */\n\ndefined( 'ABSPATH' ) || exit;\n\n",
			$definition['titre'],
			BC_Definition::nom( $definition ),
			BC_Gabarits::chemin_court( (string) $code['dossier'] ),
			date_i18n( 'd/m/Y' )
		);

		if ( '' === $rendu || ! file_exists( $rendu ) ) {
			return $entete . "?>\n<p><?php esc_html_e( 'Ce bloc n\\'avait pas de fichier de rendu : écrivez son dessin ici.', 'blocs-creator' ); ?></p>\n";
		}

		$source = (string) file_get_contents( $rendu ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		// On retire l'ouverture PHP, l'en-tête de fichier et le garde-fou
		// d'accès direct du fichier d'origine : le nôtre les remplace.
		$source = preg_replace( '/^<\?php\s*(\/\*\*.*?\*\/\s*)?/s', '', $source, 1 );
		$source = preg_replace( "/^\s*defined\(\s*'ABSPATH'\s*\)\s*\|\|\s*exit;\s*/", '', (string) $source, 1 );

		return $entete . ltrim( (string) $source, "\n" );
	}
}
