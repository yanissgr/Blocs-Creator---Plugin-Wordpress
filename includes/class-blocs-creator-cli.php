<?php
/**
 * Les commandes WP-CLI : `wp blocs-creator …`.
 *
 * Elles font en ligne de commande ce que les écrans font à la souris — et
 * c'est souvent par là que passe une IA qui monte un site. Chaque commande
 * dit ce qu'elle a fait, et sort en erreur si elle n'a pas pu.
 *
 *     wp blocs-creator definitions etat
 *     wp blocs-creator definitions importer [--ecraser] [--seulement=faq,hero]
 *     wp blocs-creator definitions exporter [--seulement=faq,hero]
 *     wp blocs-creator migrations etat
 *     wp blocs-creator migrations lancer
 *     wp blocs-creator modules liste
 *     wp blocs-creator modules activer <module>…
 *     wp blocs-creator modules desactiver <module>…
 *     wp blocs-creator ia etat
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Les commandes.
 */
class Blocs_Creator_Cli {

	/**
	 * Déclare les commandes.
	 */
	public static function declarer() {
		WP_CLI::add_command( 'blocs-creator definitions', array( __CLASS__, 'definitions' ) );
		WP_CLI::add_command( 'blocs-creator migrations', array( __CLASS__, 'migrations' ) );
		WP_CLI::add_command( 'blocs-creator modules', array( __CLASS__, 'modules' ) );
		WP_CLI::add_command( 'blocs-creator ia', array( __CLASS__, 'ia' ) );
	}

	/**
	 * Le fichier de définitions du thème (blocs-creator/definitions.json).
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : etat, importer ou exporter.
	 *
	 * [--ecraser]
	 * : À l'import, remplacer les définitions qui existent déjà.
	 *
	 * [--seulement=<blocs>]
	 * : Identifiants ou noms complets, séparés par des virgules.
	 *
	 * @param array $args  Arguments.
	 * @param array $assoc Options.
	 */
	public static function definitions( $args, $assoc ) {
		$action    = $args[0] ?? 'etat';
		$seulement = isset( $assoc['seulement'] ) ? explode( ',', (string) $assoc['seulement'] ) : array();

		if ( 'importer' === $action ) {
			$bilan = Blocs_Creator_Theme::importer( ! empty( $assoc['ecraser'] ), $seulement );

			foreach ( $bilan['erreurs'] as $erreur ) {
				WP_CLI::warning( $erreur );
			}

			$message = sprintf( '%d bloc(s) importé(s), %d ignoré(s) (déjà en base : --ecraser pour les remplacer).', $bilan['importes'], $bilan['ignores'] );

			if ( $bilan['erreurs'] ) {
				WP_CLI::error( $message );
			}

			WP_CLI::success( $message );
			return;
		}

		if ( 'exporter' === $action ) {
			$resultat = Blocs_Creator_Theme::exporter( $seulement );

			if ( is_wp_error( $resultat ) ) {
				WP_CLI::error( $resultat->get_error_message() );
			}

			WP_CLI::success( 'Définitions écrites dans ' . $resultat );
			return;
		}

		$etat = Blocs_Creator_Theme::etat();

		if ( is_wp_error( $etat ) ) {
			WP_CLI::error( $etat->get_error_message() );
		}

		$lignes = array();

		foreach ( $etat['blocs'] as $nom => $bloc ) {
			$lignes[] = array(
				'bloc'  => $nom,
				'titre' => $bloc['titre'],
				'etat'  => $bloc['etat'],
			);
		}

		WP_CLI::log( 'Fichier : ' . Blocs_Creator_Theme::fichier() );
		WP_CLI::log( 'absent = dans le fichier seulement, base = en base seulement.' );
		WP_CLI\Utils\format_items( 'table', $lignes, array( 'bloc', 'titre', 'etat' ) );
	}

	/**
	 * Les migrations déclarées par le thème.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : etat ou lancer.
	 *
	 * @param array $args Arguments.
	 */
	public static function migrations( $args ) {
		if ( 'lancer' === ( $args[0] ?? 'etat' ) ) {
			$journal = Blocs_Creator_Migrations::migrer( true );

			foreach ( $journal as $ligne ) {
				WP_CLI::log( '- ' . $ligne );
			}

			WP_CLI::success( $journal ? 'Migrations passées.' : 'Rien à faire.' );
			return;
		}

		$lignes = array();

		foreach ( Blocs_Creator_Migrations::etat() as $source => $etat ) {
			$lignes[] = array(
				'source'   => $source,
				'faite'    => $etat['faite'],
				'derniere' => $etat['derniere'],
				'pause'    => $etat['en_pause'] ? 'oui' : 'non',
			);
		}

		if ( ! $lignes ) {
			WP_CLI::log( 'Aucune migration déclarée (filtre blocs_creator_migrations).' );
			return;
		}

		WP_CLI\Utils\format_items( 'table', $lignes, array( 'source', 'faite', 'derniere', 'pause' ) );
	}

	/**
	 * Les modules optionnels.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : liste, activer ou desactiver.
	 *
	 * [<module>...]
	 * : Identifiants des modules.
	 *
	 * @param array $args Arguments.
	 */
	public static function modules( $args ) {
		$action  = array_shift( $args ) ?? 'liste';
		$modules = Blocs_Creator_Modules::catalogue();

		if ( in_array( $action, array( 'activer', 'desactiver' ), true ) ) {
			$actifs = Blocs_Creator_Modules::coches();

			foreach ( $args as $slug ) {
				if ( ! isset( $modules[ $slug ] ) ) {
					WP_CLI::error( sprintf( 'Module inconnu : %s. Voir « wp blocs-creator modules liste ».', $slug ) );
				}

				$actifs = 'activer' === $action ? array_merge( $actifs, array( $slug ) ) : array_diff( $actifs, array( $slug ) );
			}

			Blocs_Creator_Modules::enregistrer( $actifs );
			WP_CLI::success( 'Modules actifs : ' . ( implode( ', ', Blocs_Creator_Modules::coches() ) ?: 'aucun' ) );
			return;
		}

		$lignes = array();

		foreach ( $modules as $slug => $module ) {
			$lignes[] = array(
				'module' => $slug,
				'nom'    => $module['nom'],
				'actif'  => Blocs_Creator_Modules::actif( $slug ) ? 'oui' : 'non',
				'force'  => Blocs_Creator_Modules::impose( $slug ) ? 'par le thème' : '',
			);
		}

		WP_CLI\Utils\format_items( 'table', $lignes, array( 'module', 'nom', 'actif', 'force' ) );
	}

	/**
	 * L'IA : est-elle disponible, avec quels réglages.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : etat.
	 */
	public static function ia() {
		$reglages = Blocs_Creator_Ia::reglages();

		WP_CLI::log( 'Client d\'IA de WordPress : ' . ( Blocs_Creator_Ia::disponible() ? 'disponible' : 'absent ou désactivé' ) );
		WP_CLI::log( 'Rédiger un article : ' . ( $reglages['articles'] ? 'oui (' . implode( ', ', $reglages['types'] ) . ')' : 'non' ) );
		WP_CLI::log( 'Remplir une section : ' . ( $reglages['sections'] ? 'oui' : 'non' ) );
		WP_CLI::log( 'Modèles préférés : ' . implode( ', ', Blocs_Creator_Ia::modeles() ) );
		WP_CLI::log( 'Consignes :' );
		WP_CLI::log( Blocs_Creator_Ia::consignes_communes() );
	}
}
