<?php
/**
 * Le diagnostic des réglages.
 *
 * Ce fichier existe pour une raison précise, et il faut la connaître avant d'y
 * toucher : « le bouton Enregistrer ne fait rien » est un symptôme, pas une
 * cause. Il peut venir d'une requête tronquée, d'une extension qui intercepte
 * l'écriture, d'un cache d'objets qui ressert l'ancienne valeur, d'une base en
 * lecture seule — et, vu du navigateur, les quatre se ressemblent : un écran
 * qui revient inchangé.
 *
 * Deux choses sont donc écrites ici.
 *
 *   1. UN JOURNAL. Chaque tentative d'enregistrement laisse une trace : quand,
 *      par qui, combien de champs sont arrivés, ce qui a été écrit, ce qui a
 *      résisté. Si le journal reste vide après un clic, la requête n'est jamais
 *      arrivée — et l'on cherche ailleurs que dans le plugin.
 *   2. UN RELEVÉ. L'état de la machine au moment où l'on regarde : version,
 *      limites de PHP, présence de la ligne en base, cache d'objets, extensions
 *      actives. Copiable d'un bouton, pour être lu par quelqu'un d'autre.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Journal et relevé de l'enregistrement des réglages.
 */
class BC_Diagnostic {

	/**
	 * Option qui porte le journal. Jamais autochargée : on ne la lit qu'ici.
	 */
	const JOURNAL = 'blocs_creator_journal';

	/**
	 * Nombre de tentatives conservées.
	 */
	const GARDE = 8;

	/**
	 * Inscrit une tentative d'enregistrement.
	 *
	 * @param array $entree Ce qu'il y a à retenir.
	 */
	public static function noter( $entree ) {
		$journal = (array) get_option( self::JOURNAL, array() );

		array_unshift(
			$journal,
			wp_parse_args(
				$entree,
				array(
					'date'  => gmdate( 'c' ),
					'qui'   => wp_get_current_user()->user_login,
					'quoi'  => '',
				)
			)
		);

		update_option( self::JOURNAL, array_slice( $journal, 0, self::GARDE ), false );
	}

	/**
	 * Retourne le journal, du plus récent au plus ancien.
	 *
	 * @return array<int, array>
	 */
	public static function journal() {
		return (array) get_option( self::JOURNAL, array() );
	}

	/**
	 * Écrit une option sans passer par l'API des options.
	 *
	 * Dernier recours, appelé seulement quand la voie normale n'a rien écrit.
	 * Ce n'est pas élégant, et c'est délibéré : un filtre `pre_update_option`,
	 * un cache d'objets qui ne se vide pas, une extension de sécurité trop
	 * zélée — autant de choses invisibles depuis ici, et qui font toutes le
	 * même bouton mort. La valeur passée est déjà assainie ; ce qu'on saute,
	 * ce sont les filtres, pas les gardes.
	 *
	 * @param string $option Nom de l'option.
	 * @param mixed  $valeur Valeur déjà assainie.
	 * @return bool Vrai si la ligne a été touchée.
	 */
	public static function forcer( $option, $valeur ) {
		global $wpdb;

		$brut = maybe_serialize( $valeur );

		$touche = $wpdb->update(
			$wpdb->options,
			array( 'option_value' => $brut ),
			array( 'option_name' => $option )
		);

		if ( 0 === $touche || false === $touche ) {
			$touche = $wpdb->insert(
				$wpdb->options,
				array(
					'option_name'  => $option,
					'option_value' => $brut,
					'autoload'     => 'yes',
				)
			);
		}

		wp_cache_delete( $option, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );

		return (bool) $touche;
	}

	/**
	 * La ligne de l'option existe-t-elle vraiment en base ?
	 *
	 * `get_option()` ne répond pas à cette question : quand la ligne manque, il
	 * rend la valeur par défaut déclarée par `register_setting()`, et l'écran
	 * affiche donc des réglages qui n'ont jamais été enregistrés.
	 *
	 * @param string $option Nom de l'option.
	 * @return array{existe:bool, autocharge:string, octets:int}
	 */
	public static function ligne( $option ) {
		global $wpdb;

		$ligne = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				$option
			)
		);

		return array(
			'existe'     => null !== $ligne,
			'autocharge' => null !== $ligne ? (string) $ligne->autoload : '—',
			'octets'     => null !== $ligne ? strlen( (string) $ligne->option_value ) : 0,
		);
	}

	/**
	 * Écrit puis relit une option témoin, pour savoir si la base répond.
	 *
	 * @return string Ce qui s'est passé, en une ligne.
	 */
	public static function essai_ecriture() {
		$temoin = 'blocs_creator_essai';
		$valeur = 'essai-' . time();

		update_option( $temoin, $valeur, false );
		wp_cache_delete( $temoin, 'options' );

		$relu = get_option( $temoin );

		delete_option( $temoin );

		return $relu === $valeur
			? __( 'la base accepte les écritures', 'blocs-creator' )
			: sprintf(
				/* translators: 1: valeur écrite, 2: valeur relue. */
				__( 'ÉCHEC — écrit « %1$s », relu « %2$s »', 'blocs-creator' ),
				$valeur,
				is_scalar( $relu ) ? (string) $relu : gettype( $relu )
			);
	}

	/**
	 * Compose le relevé, en texte brut.
	 *
	 * @return string
	 */
	public static function releve() {
		global $wp_version;

		$reglages   = self::ligne( BC_Reglages::OPTION );
		$animations = self::ligne( BC_Animations::OPTION );
		$declarees  = get_registered_settings();

		$lignes = array();

		$lignes[] = '# Diagnostic Blocs Creator';
		$lignes[] = '';
		$lignes[] = sprintf( '- Plugin : %s', BLOCS_CREATOR_VERSION );
		$lignes[] = sprintf( '- WordPress : %s — PHP %s', $wp_version, PHP_VERSION );
		$lignes[] = sprintf(
			'- PHP : max_input_vars = %s, post_max_size = %s, memory_limit = %s',
			ini_get( 'max_input_vars' ),
			ini_get( 'post_max_size' ),
			ini_get( 'memory_limit' )
		);
		$lignes[] = sprintf(
			'- Cache d\'objets externe : %s',
			wp_using_ext_object_cache() ? 'OUI' : 'non'
		);
		$lignes[] = sprintf( '- Écriture en base : %s', self::essai_ecriture() );
		$lignes[] = '';
		$lignes[] = '## Les deux options';
		$lignes[] = '';
		$lignes[] = sprintf(
			'- `%s` : %s, autoload=%s, %d octets, déclarée=%s',
			BC_Reglages::OPTION,
			$reglages['existe'] ? 'la ligne existe' : 'LIGNE ABSENTE',
			$reglages['autocharge'],
			$reglages['octets'],
			isset( $declarees[ BC_Reglages::OPTION ] ) ? 'oui' : 'non'
		);
		$lignes[] = sprintf(
			'- `%s` : %s, autoload=%s, %d octets',
			BC_Animations::OPTION,
			$animations['existe'] ? 'la ligne existe' : 'LIGNE ABSENTE',
			$animations['autocharge'],
			$animations['octets']
		);
		$lignes[] = '';
		$lignes[] = '## Ce que l\'écran enverra';
		$lignes[] = '';

		$inventaire = 0;

		foreach ( BC_Disponibilite::inventaire() as $groupe ) {
			$inventaire += count( $groupe['blocs'] );
		}

		$lignes[] = sprintf( '- %d blocs enregistrés sur ce site', $inventaire );
		$lignes[] = sprintf(
			'- environ %d champs dans le formulaire (la limite de PHP est %s)',
			$inventaire + 20,
			ini_get( 'max_input_vars' )
		);
		$lignes[] = '';
		$lignes[] = '## Les dernières tentatives d\'enregistrement';
		$lignes[] = '';

		$journal = self::journal();

		if ( empty( $journal ) ) {
			$lignes[] = '_Aucune._ Si vous venez d\'appuyer sur « Enregistrer », c\'est que';
			$lignes[] = 'la requête n\'est jamais arrivée jusqu\'au plugin : pare-feu de';
			$lignes[] = 'l\'hébergement, extension de sécurité, ou requête tronquée.';
		} else {
			foreach ( $journal as $entree ) {
				$lignes[] = sprintf(
					'- %s — %s — %s',
					$entree['date'] ?? '?',
					$entree['qui'] ?? '?',
					$entree['quoi'] ?? '?'
				);
			}
		}

		$lignes[] = '';
		$lignes[] = '## Extensions actives';
		$lignes[] = '';

		foreach ( (array) get_option( 'active_plugins', array() ) as $extension ) {
			$lignes[] = sprintf( '- %s', $extension );
		}

		$lignes[] = '';

		return implode( "\n", $lignes );
	}
}
