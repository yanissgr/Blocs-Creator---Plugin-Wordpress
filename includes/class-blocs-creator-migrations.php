<?php
/**
 * Les migrations : ce qui doit changer dans la BASE d'un site quand son thème
 * évolue.
 *
 * Un site en ligne reçoit ses fichiers par FTP, ou par Git : ni ligne de
 * commande, ni accès direct à la base. Or certaines évolutions touchent à ce
 * qui vit en base : la définition d'un bloc, le contenu d'une page, un
 * réglage. Chacune s'écrit donc comme une migration numérotée, qui s'exécute
 * une fois, toute seule, à la première visite qui suit la mise en ligne :
 *
 *     add_filter( 'blocs_creator_migrations', function ( $migrations ) {
 *         $migrations['mon-theme'] = array(
 *             1 => 'mon_theme_migration_faq',
 *             2 => 'mon_theme_migration_page_contact',
 *         );
 *         return $migrations;
 *     } );
 *
 * Une migration est une fonction sans argument qui rend la liste de ce
 * qu'elle a fait (des phrases, montrées une fois aux administrateurs), ou un
 * WP_Error : elle est alors retentée un quart d'heure plus tard : le temps,
 * par exemple, que le reste des fichiers finisse d'arriver.
 *
 * Trois règles, apprises en production :
 *
 *   1. VÉRIFIER QUE LES FICHIERS SONT LÀ. Un envoi FTP n'est pas atomique : la
 *      migration peut tourner alors que la moitié des fichiers est encore en
 *      route. blocs_creator_migration_fichiers() le vérifie, témoin à l'appui.
 *   2. NE JAMAIS PERDRE UN CONTENU. Une page modifiée garde son état d'avant
 *      dans ses révisions (blocs_creator_migration_page()).
 *   3. UNE À LA FOIS. Deux visites simultanées ne migrent pas deux fois.
 *
 * Le dernier numéro fait est gardé par source, dans l'option
 * `blocs_creator_migrations`.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * L'exécution des migrations.
 */
class Blocs_Creator_Migrations {

	/**
	 * Option : le dernier numéro fait, par source.
	 */
	const OPTION = 'blocs_creator_migrations';

	/**
	 * Option : ce qu'il reste à dire aux administrateurs.
	 */
	const JOURNAL = 'blocs_creator_migrations_journal';

	/**
	 * Branche les hooks.
	 *
	 * À `init`, juste après la déclaration des définitions (priorité 5) et
	 * avant l'enregistrement des blocs (priorité 20) : un bloc qu'une
	 * migration ajoute est donc déjà là pour cette visite-ci.
	 */
	public static function demarrer() {
		add_action( 'init', array( __CLASS__, 'migrer' ), 6 );
		add_action( 'admin_notices', array( __CLASS__, 'avis' ) );
	}

	/**
	 * Les migrations déclarées, par source puis par numéro.
	 *
	 * @return array<string, array<int, callable>>
	 */
	public static function declarees() {
		/**
		 * Filtre les migrations, par source puis par numéro.
		 *
		 * @param array<string, array<int, callable>> $migrations Les migrations.
		 */
		$brutes = (array) apply_filters( 'blocs_creator_migrations', array() );
		$propres = array();

		foreach ( $brutes as $source => $liste ) {
			$source = sanitize_key( (string) $source );
			$liste  = array_filter( (array) $liste, 'is_callable' );

			if ( '' === $source || empty( $liste ) ) {
				continue;
			}

			ksort( $liste, SORT_NUMERIC );
			$propres[ $source ] = $liste;
		}

		return $propres;
	}

	/**
	 * Le dernier numéro fait, par source.
	 *
	 * @return array<string, int>
	 */
	public static function faites() {
		return array_map( 'intval', (array) get_option( self::OPTION, array() ) );
	}

	/**
	 * Où en est chaque source : dernier numéro fait, dernier numéro déclaré.
	 *
	 * @return array<string, array{faite: int, derniere: int, en_pause: bool}>
	 */
	public static function etat() {
		$faites = self::faites();
		$etat   = array();

		foreach ( self::declarees() as $source => $liste ) {
			$etat[ $source ] = array(
				'faite'    => $faites[ $source ] ?? 0,
				'derniere' => (int) max( array_keys( $liste ) ),
				'en_pause' => (bool) get_transient( 'blocs_creator_migration_pause_' . $source ),
			);
		}

		return $etat;
	}

	/**
	 * Passe les migrations qui restent à faire.
	 *
	 * @param bool $forcer Ignorer la pause d'un quart d'heure (WP-CLI).
	 * @return string[] Ce qui a été fait, ou reporté.
	 */
	public static function migrer( $forcer = false ) {
		$forcer  = true === $forcer;
		$faites  = self::faites();
		$journal = array();

		foreach ( self::declarees() as $source => $liste ) {
			$faite    = $faites[ $source ] ?? 0;
			$derniere = (int) max( array_keys( $liste ) );
			$pause    = 'blocs_creator_migration_pause_' . $source;
			$verrou   = 'blocs_creator_migration_verrou_' . $source;

			if ( $faite >= $derniere || ( ! $forcer && get_transient( $pause ) ) ) {
				continue;
			}

			// Une seule à la fois : deux visites simultanées ne migrent pas deux fois.
			if ( ! add_option( $verrou, time(), '', false ) ) {
				if ( time() - (int) get_option( $verrou ) < 5 * MINUTE_IN_SECONDS ) {
					continue;
				}

				update_option( $verrou, time(), false );
			}

			foreach ( $liste as $numero => $fonction ) {
				if ( $numero <= $faite ) {
					continue;
				}

				$resultat = call_user_func( $fonction );

				if ( is_wp_error( $resultat ) ) {
					$journal[] = sprintf(
						/* translators: 1: source, 2: numéro de migration, 3: raison. */
						__( '%1$s, migration %2$d reportée d\'un quart d\'heure : %3$s', 'blocs-creator' ),
						$source,
						$numero,
						$resultat->get_error_message()
					);
					set_transient( $pause, 1, 15 * MINUTE_IN_SECONDS );
					break;
				}

				$faite             = (int) $numero;
				$faites[ $source ] = $faite;
				update_option( self::OPTION, $faites, true );

				$journal = array_merge( $journal, array_map( 'strval', (array) $resultat ) );
			}

			delete_option( $verrou );
		}

		if ( $journal ) {
			$ancien = (array) get_option( self::JOURNAL, array() );
			update_option( self::JOURNAL, array_slice( array_merge( $ancien, $journal ), -30 ), false );
		}

		return $journal;
	}

	/**
	 * Montre aux administrateurs ce que les migrations ont fait, une fois.
	 */
	public static function avis() {
		$ecran = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		// L'éditeur de blocs ne montre pas ces avis : on attend un autre écran.
		if ( ! current_user_can( 'manage_options' ) || ( $ecran && $ecran->is_block_editor() ) ) {
			return;
		}

		$journal = (array) get_option( self::JOURNAL, array() );

		if ( ! $journal ) {
			return;
		}

		echo '<div class="notice notice-info is-dismissible"><p><strong>' . esc_html__( 'Mise à jour du site', 'blocs-creator' ) . '</strong></p><ul style="list-style:disc;margin-left:1.5em">';

		foreach ( $journal as $ligne ) {
			echo '<li>' . esc_html( (string) $ligne ) . '</li>';
		}

		echo '</ul></div>';

		delete_option( self::JOURNAL );
	}
}

/**
 * Les fichiers d'une version sont-ils tous en ligne ?
 *
 * Chaque fichier est donné avec un TÉMOIN : un bout de texte que seule la
 * nouvelle version contient. Un fichier présent mais encore ancien (ou à
 * moitié écrit par un envoi FTP en cours) ne passe pas.
 *
 * @param array<string, string> $temoins Témoin, par chemin relatif au thème.
 * @return true|WP_Error
 */
function blocs_creator_migration_fichiers( $temoins ) {
	foreach ( (array) $temoins as $fichier => $temoin ) {
		$chemin = get_theme_file_path( $fichier );

		if ( ! is_readable( $chemin ) || ( '' !== (string) $temoin && ! str_contains( (string) file_get_contents( $chemin ), (string) $temoin ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new WP_Error(
				'bc_fichier_manquant',
				/* translators: %s: chemin du fichier. */
				sprintf( __( 'le fichier %s n\'est pas encore à jour en ligne.', 'blocs-creator' ), $fichier )
			);
		}
	}

	return true;
}

/**
 * Importe des définitions du fichier du thème (blocs-creator/definitions.json).
 *
 * @param bool     $ecraser   Remplacer celles qui existent déjà.
 * @param string[] $seulement Identifiants des seuls blocs à importer ; vide pour tous.
 * @return array{importes: int, ignores: int, erreurs: string[]}
 */
function blocs_creator_importer_definitions( $ecraser = false, $seulement = array() ) {
	return Blocs_Creator_Theme::importer( $ecraser, $seulement );
}

/**
 * Enregistre un nouveau contenu pour une page. Son état d'avant reste dans
 * ses révisions.
 *
 * Les filtres de sécurité (kses) sont levés le temps de l'écriture : la
 * migration peut tourner pendant la visite d'un anonyme, et ils abîmeraient
 * le balisage des blocs.
 *
 * @param WP_Post|int $post    La page.
 * @param string      $contenu Le nouveau contenu.
 * @return int|WP_Error
 */
function blocs_creator_migration_page( $post, $contenu ) {
	$post = get_post( $post );

	if ( ! $post instanceof WP_Post ) {
		return new WP_Error( 'bc_page_introuvable', __( 'la page à modifier est introuvable.', 'blocs-creator' ) );
	}

	if ( wp_revisions_enabled( $post ) ) {
		wp_save_post_revision( $post->ID );
	}

	kses_remove_filters();

	$resultat = wp_update_post(
		array(
			'ID'           => $post->ID,
			'post_content' => wp_slash( $contenu ),
		),
		true
	);

	kses_init();

	return $resultat;
}

/**
 * Une valeur écrite en zone de texte (ou en texte simple), prête pour un
 * champ « Texte enrichi » : le texte est échappé, `**gras**` et `_italique_`
 * deviennent des balises, les retours à la ligne des `<br>` (l'éditeur les
 * montre comme tels). Une valeur qui a déjà des balises garde les siennes.
 *
 * @param mixed $valeur Valeur enregistrée.
 * @return mixed La valeur convertie ; telle quelle si ce n'est pas un texte.
 */
function blocs_creator_vers_texte_riche( $valeur ) {
	if ( ! is_string( $valeur ) || '' === $valeur ) {
		return $valeur;
	}

	$valeur = str_replace( "\r\n", "\n", $valeur );

	if ( str_contains( $valeur, '<' ) ) {
		return str_replace( "\n", '<br>', $valeur );
	}

	return blocs_creator_balisage_leger( esc_html( $valeur ) );
}

/**
 * Passe au texte enrichi des champs déjà écrits, dans tous les contenus du
 * site : pages, articles, modèles, compositions.
 *
 * Changer le type d'un champ dans sa définition ne touche pas aux pages : le
 * texte brut d'une ancienne zone de texte (retours à la ligne, `**gras**`)
 * s'afficherait tel quel dans l'éditeur. Une migration du thème appelle donc
 * cette fonction après avoir importé les définitions :
 *
 *     $bilan = blocs_creator_migration_texte_riche( array(
 *         'mon-theme/hero'   => array( 'chapo' ),
 *         'mon-theme/cartes' => array( 'intro', 'cartes.titre', 'cartes.texte' ),
 *     ) );
 *
 * « repeteur.champ » désigne le champ d'un répéteur (chaque ligne) ou d'un
 * groupe. Un contenu modifié garde son état d'avant dans ses révisions ; un
 * contenu qui n'a rien à convertir n'est pas réécrit.
 *
 * @param array<string, string[]> $champs Chemins des champs, par nom complet de bloc.
 * @return int|WP_Error Le nombre de contenus modifiés.
 */
function blocs_creator_migration_texte_riche( $champs ) {
	global $wpdb;

	$propres = array();

	foreach ( (array) $champs as $bloc => $chemins ) {
		$bloc = (string) $bloc;

		if ( preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $bloc ) ) {
			$propres[ $bloc ] = array_values( array_filter( array_map( 'strval', (array) $chemins ) ) );
		}
	}

	if ( ! $propres ) {
		return 0;
	}

	$conditions = array();
	$valeurs    = array();

	foreach ( array_keys( $propres ) as $bloc ) {
		$conditions[] = 'post_content LIKE %s';
		$valeurs[]    = '%' . $wpdb->esc_like( '<!-- wp:' . $bloc . ' ' ) . '%';
	}

	$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type <> 'revision' AND ( " . implode( ' OR ', $conditions ) . ' )', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$valeurs
		)
	);

	$convertir = static function ( $blocs ) use ( &$convertir, $propres ) {
		foreach ( $blocs as $i => $bloc ) {
			if ( ! empty( $bloc['innerBlocks'] ) ) {
				$blocs[ $i ]['innerBlocks'] = $convertir( $bloc['innerBlocks'] );
			}

			$nom = (string) $bloc['blockName'];

			if ( ! isset( $propres[ $nom ] ) ) {
				continue;
			}

			$attrs = (array) $bloc['attrs'];

			foreach ( $propres[ $nom ] as $chemin ) {
				$parties = explode( '.', $chemin, 2 );

				if ( 1 === count( $parties ) ) {
					if ( isset( $attrs[ $chemin ] ) ) {
						$attrs[ $chemin ] = blocs_creator_vers_texte_riche( $attrs[ $chemin ] );
					}
					continue;
				}

				list( $parent, $cle ) = $parties;

				if ( ! isset( $attrs[ $parent ] ) || ! is_array( $attrs[ $parent ] ) ) {
					continue;
				}

				// Un groupe est une seule ligne ; un répéteur, une liste de lignes.
				if ( ! wp_is_numeric_array( $attrs[ $parent ] ) ) {
					if ( isset( $attrs[ $parent ][ $cle ] ) ) {
						$attrs[ $parent ][ $cle ] = blocs_creator_vers_texte_riche( $attrs[ $parent ][ $cle ] );
					}
					continue;
				}

				foreach ( $attrs[ $parent ] as $rang => $ligne ) {
					if ( is_array( $ligne ) && isset( $ligne[ $cle ] ) ) {
						$attrs[ $parent ][ $rang ][ $cle ] = blocs_creator_vers_texte_riche( $ligne[ $cle ] );
					}
				}
			}

			$blocs[ $i ]['attrs'] = $attrs;
		}

		return $blocs;
	};

	$modifies = 0;

	foreach ( $ids as $id ) {
		$avant = (string) get_post_field( 'post_content', (int) $id, 'raw' );
		$apres = serialize_blocks( $convertir( parse_blocks( $avant ) ) );

		if ( $apres === $avant || $apres === serialize_blocks( parse_blocks( $avant ) ) ) {
			continue;
		}

		$resultat = blocs_creator_migration_page( (int) $id, $apres );

		if ( is_wp_error( $resultat ) ) {
			return $resultat;
		}

		++$modifies;
	}

	return $modifies;
}
