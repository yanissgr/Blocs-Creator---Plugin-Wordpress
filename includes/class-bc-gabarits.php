<?php
/**
 * Les gabarits : où se trouve le dessin d'un bloc, et comment l'amorcer.
 *
 * Un bloc généré n'embarque aucun balisage. Son apparence est un fichier PHP
 * du thème, que le plugin cherche, appelle, et ne réécrit jamais. Cette
 * frontière est volontaire : les champs se saisissent, le dessin s'écrit.
 *
 * Ordre de recherche, du plus spécifique au plus général :
 *
 *   1. <thème enfant>/blocs/<espace>-<slug>.php
 *   2. <thème enfant>/blocs/<slug>.php
 *   3. <thème parent>/blocs/…            (si le thème a un parent)
 *   4. wp-content/blocs-creator/gabarits/<slug>.php
 *   5. le gabarit de secours du plugin, qui affiche les champs tels quels.
 *
 * Le point 4 existe pour une raison précise : il survit au changement de
 * thème. Le point 5 pour une autre : un bloc tout juste créé doit montrer
 * quelque chose, pas un vide.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Résolution, lecture et création des fichiers de gabarit.
 */
class BC_Gabarits {

	/**
	 * Styles déjà enregistrés, par nom de bloc.
	 *
	 * @var array<string, string>
	 */
	private static $styles = array();

	/**
	 * Retourne les chemins candidats d'un gabarit, dans l'ordre.
	 *
	 * @param array $definition La définition.
	 * @return array<int, string>
	 */
	public static function candidats( $definition ) {
		$dossier = trim( (string) blocs_creator()->reglages->get( 'dossier_gabarits' ), '/' );
		$slug    = $definition['slug'] ?? '';
		$espace  = $definition['espace'] ?? '';

		if ( '' === $slug ) {
			return array();
		}

		$noms = array( $espace . '-' . $slug . '.php', $slug . '.php' );

		$racines = array( get_stylesheet_directory() . '/' . $dossier );

		if ( get_template_directory() !== get_stylesheet_directory() ) {
			$racines[] = get_template_directory() . '/' . $dossier;
		}

		$racines[] = WP_CONTENT_DIR . '/blocs-creator/gabarits';

		$candidats = array();

		foreach ( $racines as $racine ) {
			foreach ( $noms as $nom ) {
				$candidats[] = $racine . '/' . $nom;
			}
		}

		/**
		 * Filtre les chemins candidats d'un gabarit.
		 *
		 * @param array $candidats  Chemins, du plus spécifique au plus général.
		 * @param array $definition La définition.
		 */
		return apply_filters( 'blocs_creator_candidats_gabarit', $candidats, $definition );
	}

	/**
	 * Retourne le gabarit d'un bloc, ou une chaîne vide s'il n'existe pas.
	 *
	 * @param array $definition La définition.
	 * @return string
	 */
	public static function chemin( $definition ) {
		foreach ( self::candidats( $definition ) as $candidat ) {
			if ( file_exists( $candidat ) ) {
				return $candidat;
			}
		}

		return '';
	}

	/**
	 * Retourne le gabarit à utiliser au rendu — celui du thème, ou le secours.
	 *
	 * @param array $definition La définition.
	 * @return string
	 */
	public static function chemin_rendu( $definition ) {
		$chemin = self::chemin( $definition );

		return '' !== $chemin ? $chemin : BLOCS_CREATOR_DIR . 'gabarits/secours.php';
	}

	/**
	 * Retourne le chemin où le plugin écrirait le gabarit.
	 *
	 * @param array $definition La définition.
	 * @param bool  $prefixe    Préférer le nom préfixé de l'espace de noms.
	 * @return string
	 */
	public static function chemin_prefere( $definition, $prefixe = false ) {
		$candidats = self::candidats( $definition );

		if ( $prefixe ) {
			// <thème>/blocs/<espace>-<slug>.php : c'est le nom qu'on donne au
			// gabarit d'un bloc repris au code, dont le nom complet compte.
			return $candidats[0] ?? '';
		}

		// Le second candidat : <thème>/blocs/<slug>.php, sans le préfixe
		// d'espace de noms, qui n'a d'intérêt qu'en cas de collision.
		return $candidats[1] ?? ( $candidats[0] ?? '' );
	}

	/**
	 * Retourne le chemin du fichier CSS d'un bloc, s'il existe.
	 *
	 * Un `<slug>.css` posé à côté du gabarit est chargé automatiquement, et
	 * seulement sur les pages qui portent le bloc.
	 *
	 * @param array $definition La définition.
	 * @return string
	 */
	public static function chemin_style( $definition ) {
		foreach ( self::candidats( $definition ) as $candidat ) {
			$css = preg_replace( '/\.php$/', '.css', $candidat );

			if ( $css && file_exists( $css ) ) {
				return $css;
			}
		}

		return '';
	}

	/**
	 * Enregistre le style d'un bloc et retourne son identifiant.
	 *
	 * @param array $definition La définition.
	 * @return string Identifiant du style, ou chaîne vide.
	 */
	public static function handle_style( $definition ) {
		$nom = BC_Definition::nom( $definition );

		if ( isset( self::$styles[ $nom ] ) ) {
			return self::$styles[ $nom ];
		}

		$fichier = self::chemin_style( $definition );

		if ( '' === $fichier ) {
			return '';
		}

		$url = self::url( $fichier );

		if ( '' === $url ) {
			return '';
		}

		$handle = 'bc-' . sanitize_key( str_replace( '/', '-', $nom ) );

		wp_register_style( $handle, $url, array(), (string) filemtime( $fichier ) );

		self::$styles[ $nom ] = $handle;

		return $handle;
	}

	/**
	 * Traduit un chemin disque en URL, pour les fichiers de wp-content.
	 *
	 * @param string $chemin Chemin absolu.
	 * @return string
	 */
	public static function url( $chemin ) {
		$racine = wp_normalize_path( WP_CONTENT_DIR );
		$chemin = wp_normalize_path( $chemin );

		if ( ! str_starts_with( $chemin, $racine ) ) {
			return '';
		}

		return content_url( substr( $chemin, strlen( $racine ) ) );
	}

	/**
	 * Rend un chemin lisible, relatif à wp-content.
	 *
	 * @param string $chemin Chemin absolu.
	 * @return string
	 */
	public static function chemin_court( $chemin ) {
		if ( '' === $chemin ) {
			return '';
		}

		$racine = wp_normalize_path( dirname( WP_CONTENT_DIR ) );
		$chemin = wp_normalize_path( $chemin );

		return str_starts_with( $chemin, $racine ) ? ltrim( substr( $chemin, strlen( $racine ) ), '/' ) : $chemin;
	}

	/* ------------------------------------------------------------------ *
	 * Création
	 * ------------------------------------------------------------------ */

	/**
	 * Crée le fichier de gabarit d'un bloc.
	 *
	 * N'écrase jamais un fichier existant : un gabarit appartient à celui qui
	 * l'a écrit.
	 *
	 * @param array $definition La définition.
	 * @return string|WP_Error Chemin du fichier créé.
	 */
	public static function creer( $definition ) {
		$existant = self::chemin( $definition );

		if ( '' !== $existant ) {
			return new WP_Error(
				'bc_gabarit_existant',
				sprintf(
					/* translators: %s: chemin du fichier. */
					__( 'Le gabarit existe déjà : %s', 'blocs-creator' ),
					self::chemin_court( $existant )
				)
			);
		}

		return self::ecrire( self::chemin_prefere( $definition ), self::code_depart( $definition ) );
	}

	/**
	 * Écrit un fichier de gabarit, en créant son dossier au besoin.
	 *
	 * Sert la création d'un bloc neuf comme la reprise en main d'un bloc codé :
	 * les deux ont les mêmes trois façons d'échouer, et méritent les mêmes
	 * trois messages.
	 *
	 * @param string $cible Chemin absolu du fichier à écrire.
	 * @param string $code  Contenu du fichier.
	 * @return string|WP_Error Chemin du fichier écrit.
	 */
	public static function ecrire( $cible, $code ) {
		$dossier = dirname( $cible );

		if ( ! wp_mkdir_p( $dossier ) ) {
			return new WP_Error(
				'bc_dossier_impossible',
				sprintf(
					/* translators: %s: chemin du dossier. */
					__( 'Impossible de créer le dossier %s. Créez-le à la main, puis réessayez.', 'blocs-creator' ),
					self::chemin_court( $dossier )
				)
			);
		}

		if ( ! is_writable( $dossier ) ) {
			return new WP_Error(
				'bc_dossier_verrouille',
				sprintf(
					/* translators: %s: chemin du dossier. */
					__( 'Le dossier %s n\'est pas accessible en écriture. Copiez le code proposé dans un fichier créé à la main.', 'blocs-creator' ),
					self::chemin_court( $dossier )
				)
			);
		}

		$octets = file_put_contents( $cible, $code ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( false === $octets ) {
			return new WP_Error(
				'bc_ecriture_impossible',
				sprintf(
					/* translators: %s: chemin du fichier. */
					__( 'L\'écriture de %s a échoué.', 'blocs-creator' ),
					self::chemin_court( $cible )
				)
			);
		}

		return $cible;
	}

	/**
	 * Compose le code de départ d'un gabarit.
	 *
	 * Ce n'est pas un fichier vide avec un commentaire : chaque champ déclaré
	 * y apparaît, correctement échappé, avec le nom de la fonction qui va le
	 * chercher. Le premier geste après « Créer le bloc » est de supprimer ce
	 * qu'on ne veut pas, pas d'aller lire une documentation.
	 *
	 * @param array $definition La définition.
	 * @return string
	 */
	public static function code_depart( $definition ) {
		$nom     = BC_Definition::nom( $definition );
		$classe  = sanitize_html_class( str_replace( '/', '-', $nom ) );
		$lignes  = array();
		$lignes[] = '<?php';
		$lignes[] = '/**';
		$lignes[] = sprintf( ' * Gabarit du bloc « %s » (%s).', $definition['titre'], $nom );
		$lignes[] = ' *';
		$lignes[] = ' * Créé par Blocs Creator le ' . wp_date( 'd/m/Y' ) . '. Ce fichier vous appartient :';
		$lignes[] = ' * le plugin ne le réécrira jamais, même si vous ajoutez des champs.';
		$lignes[] = ' *';
		$lignes[] = ' * @var array    $attributes Les valeurs brutes, telles qu\'enregistrées.';
		$lignes[] = ' * @var string   $content    Les blocs imbriqués, déjà rendus.';
		$lignes[] = ' * @var WP_Block $block      L\'instance du bloc.';
		$lignes[] = ' * @var array    $champs     Les valeurs prêtes à l\'emploi, par clé.';
		$lignes[] = ' */';
		$lignes[] = '';
		$lignes[] = 'defined( \'ABSPATH\' ) || exit;';
		$lignes[] = '';
		$lignes[] = '?>';
		$lignes[] = sprintf( '<section <?php echo bc_attributs( \'%s\' ); ?>>', $classe );
		$lignes[] = '';

		/*
		 * Un champ « niveau de titre » ne s'affiche pas seul : il donne la
		 * balise d'un titre. Quand le bloc porte aussi un champ texte, on
		 * apparie les deux — c'est ce qu'on aurait écrit à la main, et ça
		 * évite de sortir deux fois le même texte dans le fichier de départ.
		 */
		$cle_niveau = '';
		$cle_titre  = '';

		foreach ( $definition['champs'] as $champ ) {
			if ( '' === $cle_niveau && 'niveau-titre' === $champ['type'] ) {
				$cle_niveau = $champ['cle'];
			}

			if ( '' === $cle_titre && 'texte' === $champ['type'] ) {
				$cle_titre = $champ['cle'];
			}
		}

		$appariement = ( '' !== $cle_niveau && '' !== $cle_titre )
			? array( 'niveau' => $cle_niveau, 'titre' => $cle_titre )
			: array();

		$corps = array();

		foreach ( $definition['champs'] as $champ ) {
			$extrait = self::extrait( $champ, $classe, "\t", $appariement );

			if ( '' !== $extrait ) {
				$corps[] = $extrait;
			}
		}

		if ( empty( $corps ) ) {
			$corps[] = "\t" . '<?php // Ce bloc n\'a pas encore de champs. Ajoutez-en, puis reprenez ce fichier. ?>';
		}

		$lignes[] = implode( "\n\n", $corps );
		$lignes[] = '';
		$lignes[] = '</section>';
		$lignes[] = '';

		$code = implode( "\n", $lignes );

		/**
		 * Filtre le code de départ d'un gabarit.
		 *
		 * @param string $code       Le code généré.
		 * @param array  $definition La définition.
		 */
		return apply_filters( 'blocs_creator_code_depart', $code, $definition );
	}

	/**
	 * Retourne l'extrait de code d'un champ.
	 *
	 * @param array  $champ       Définition du champ.
	 * @param string $classe      Classe racine du bloc.
	 * @param string $indent      Indentation.
	 * @param array  $appariement Clés du couple niveau de titre / texte, si le
	 *                            bloc porte les deux.
	 * @return string
	 */
	private static function extrait( $champ, $classe, $indent = "\t", $appariement = array() ) {
		$cle     = $champ['cle'];
		$libelle = $champ['libelle'];
		$type    = $champ['type'];
		$bem     = $classe . '__' . str_replace( '_', '-', $cle );
		$titre   = sprintf( '%s<?php // %s — %s. ?>', $indent, $libelle, BC_Champs::type( $type )['libelle'] );

		switch ( $type ) {
			case 'texte':
				// Apparié à un niveau de titre : le texte devient le titre.
				if ( isset( $appariement['titre'] ) && $appariement['titre'] === $cle ) {
					$corps = sprintf(
						"%1\$s<?php printf( '<h%%1\$d class=\"%2\$s\">%%2\$s</h%%1\$d>', bc_niveau( '%3\$s' ), esc_html( bc_champ( '%4\$s' ) ) ); ?>",
						$indent,
						$bem,
						$appariement['niveau'],
						$cle
					);
					break;
				}

				$corps = sprintf(
					'%1$s<p class="%2$s"><?php echo esc_html( bc_champ( \'%3$s\' ) ); ?></p>',
					$indent,
					$bem,
					$cle
				);
				break;

			case 'texte-long':
				$corps = sprintf(
					'%1$s<p class="%2$s"><?php echo nl2br( esc_html( bc_champ( \'%3$s\' ) ) ); ?></p>',
					$indent,
					$bem,
					$cle
				);
				break;

			case 'texte-riche':
				$corps = sprintf(
					'%1$s<div class="%2$s"><?php echo wp_kses_post( bc_champ( \'%3$s\' ) ); ?></div>',
					$indent,
					$bem,
					$cle
				);
				break;

			case 'nombre':
				$corps = sprintf(
					'%1$s<span class="%2$s"><?php echo esc_html( bc_champ( \'%3$s\' ) ); ?></span>',
					$indent,
					$bem,
					$cle
				);
				break;

			case 'niveau-titre':
				// Apparié : le titre est déjà sorti avec sa balise, il n'y a
				// rien à écrire de plus.
				if ( isset( $appariement['niveau'] ) && $appariement['niveau'] === $cle ) {
					return '';
				}

				$corps = sprintf(
					"%1\$s<?php printf( '<h%%1\$d class=\"%2\$s\">%%2\$s</h%%1\$d>', bc_niveau( '%3\$s' ), esc_html( '…' ) ); ?>",
					$indent,
					$classe . '__titre',
					$cle
				);
				break;

			case 'bascule':
				$corps = sprintf(
					"%1\$s<?php if ( bc_champ( '%2\$s' ) ) : ?>\n%1\$s\t<!-- %3\$s -->\n%1\$s<?php endif; ?>",
					$indent,
					$cle,
					$libelle
				);
				break;

			case 'liste':
			case 'boutons':
			case 'icone':
				$corps = sprintf(
					'%1$s<span class="%2$s %2$s--<?php echo esc_attr( bc_champ( \'%3$s\' ) ); ?>"></span>',
					$indent,
					$bem,
					$cle
				);
				break;

			case 'cases':
				$corps = sprintf(
					"%1\$s<?php foreach ( bc_champ( '%2\$s' ) as \$valeur ) : ?>\n%1\$s\t<span class=\"%3\$s\"><?php echo esc_html( \$valeur ); ?></span>\n%1\$s<?php endforeach; ?>",
					$indent,
					$cle,
					$bem
				);
				break;

			case 'couleur':
				$corps = sprintf(
					'%1$s<div class="%2$s" style="--couleur: <?php echo esc_attr( bc_couleur( \'%3$s\' ) ); ?>"></div>',
					$indent,
					$bem,
					$cle
				);
				break;

			case 'image':
				$corps = sprintf(
					'%1$s<?php echo bc_image( \'%2$s\', array( \'class\' => \'%3$s\' ) ); ?>',
					$indent,
					$cle,
					$bem
				);
				break;

			case 'galerie':
				$corps = sprintf(
					"%1\$s<ul class=\"%3\$s\">\n%1\$s\t<?php foreach ( bc_champ( '%2\$s' ) as \$image ) : ?>\n%1\$s\t\t<li><?php echo wp_get_attachment_image( \$image['id'], \$image['taille'] ); ?></li>\n%1\$s\t<?php endforeach; ?>\n%1\$s</ul>",
					$indent,
					$cle,
					$bem
				);
				break;

			case 'fichier':
				$corps = sprintf(
					"%1\$s<?php \$fichier = bc_champ( '%2\$s' ); ?>\n%1\$s<?php if ( \$fichier ) : ?>\n%1\$s\t<a class=\"%3\$s\" href=\"<?php echo esc_url( \$fichier['url'] ); ?>\"><?php echo esc_html( \$fichier['titre'] ); ?></a>\n%1\$s<?php endif; ?>",
					$indent,
					$cle,
					$bem
				);
				break;

			case 'lien':
				$corps = sprintf(
					"%1\$s<?php if ( bc_lien_rempli( '%2\$s' ) ) : ?>\n%1\$s\t<a class=\"%3\$s\" <?php echo bc_lien_attrs( '%2\$s' ); ?>><?php echo esc_html( bc_lien_titre( '%2\$s' ) ); ?></a>\n%1\$s<?php endif; ?>",
					$indent,
					$cle,
					$bem
				);
				break;

			case 'contenu':
				$corps = sprintf(
					"%1\$s<?php \$publication = bc_champ( '%2\$s' ); ?>\n%1\$s<?php if ( \$publication ) : ?>\n%1\$s\t<a class=\"%3\$s\" href=\"<?php echo esc_url( get_permalink( \$publication ) ); ?>\"><?php echo esc_html( get_the_title( \$publication ) ); ?></a>\n%1\$s<?php endif; ?>",
					$indent,
					$cle,
					$bem
				);
				break;

			case 'contenus':
				$corps = sprintf(
					"%1\$s<ul class=\"%3\$s\">\n%1\$s\t<?php foreach ( bc_champ( '%2\$s' ) as \$publication ) : ?>\n%1\$s\t\t<li><a href=\"<?php echo esc_url( get_permalink( \$publication ) ); ?>\"><?php echo esc_html( get_the_title( \$publication ) ); ?></a></li>\n%1\$s\t<?php endforeach; ?>\n%1\$s</ul>",
					$indent,
					$cle,
					$bem
				);
				break;

			case 'taxonomie':
				$corps = sprintf(
					"%1\$s<?php foreach ( (array) bc_champ( '%2\$s' ) as \$terme ) : ?>\n%1\$s\t<a class=\"%3\$s\" href=\"<?php echo esc_url( get_term_link( \$terme ) ); ?>\"><?php echo esc_html( \$terme->name ); ?></a>\n%1\$s<?php endforeach; ?>",
					$indent,
					$cle,
					$bem
				);
				break;

			case 'groupe':
				$sous = array();

				foreach ( (array) ( $champ['sous_champs'] ?? array() ) as $enfant ) {
					$sous[] = sprintf( '%s<?php // $%s[\'%s\'] ?>', $indent . "\t", $cle, $enfant['cle'] );
				}

				$corps = sprintf(
					"%1\$s<?php \$%2\$s = bc_champ( '%2\$s' ); ?>\n%1\$s<div class=\"%3\$s\">\n%4\$s\n%1\$s</div>",
					$indent,
					$cle,
					$bem,
					implode( "\n", $sous )
				);
				break;

			case 'repeteur':
				$sous = array();

				foreach ( (array) ( $champ['sous_champs'] ?? array() ) as $enfant ) {
					$sous[] = sprintf(
						'%s<?php // $ligne[\'%s\'] — %s ?>',
						$indent . "\t\t",
						$enfant['cle'],
						$enfant['libelle']
					);
				}

				$corps = sprintf(
					"%1\$s<ul class=\"%3\$s\">\n%1\$s\t<?php foreach ( bc_boucle( '%2\$s' ) as \$ligne ) : ?>\n%1\$s\t\t<li>\n%4\$s\n%1\$s\t\t</li>\n%1\$s\t<?php endforeach; ?>\n%1\$s</ul>",
					$indent,
					$cle,
					$bem,
					implode( "\n", $sous )
				);
				break;

			case 'blocs-imbriques':
				$corps = sprintf(
					'%1$s<div class="%2$s"><?php echo bc_contenu(); ?></div>',
					$indent,
					$classe . '__contenu'
				);
				break;

			case 'message':
				return '';

			default:
				$corps = sprintf( '%1$s<?php // %2$s ?>', $indent, $cle );
		}

		return $titre . "\n" . $corps;
	}
}
