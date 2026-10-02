<?php
/**
 * Désinstallation.
 *
 * Par défaut, on ne supprime rien : désinstaller un plugin n'est pas toujours
 * un adieu, et retrouver quarante définitions de blocs après une
 * réinstallation vaut mieux que de les avoir effacées proprement. La
 * suppression est un choix, à faire dans les réglages avant de désinstaller.
 *
 * Les fichiers de gabarit ne sont jamais touchés, dans un cas comme dans
 * l'autre : ils sont dans le thème, ils appartiennent au thème.
 *
 * @package BlocsCreator
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$blocs_creator_reglages = get_option( 'blocs_creator_reglages', array() );

if ( empty( $blocs_creator_reglages['supprimer_donnees'] ) ) {
	return;
}

$blocs_creator_blocs = get_posts(
	array(
		'post_type'      => 'bc_bloc',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $blocs_creator_blocs as $blocs_creator_id ) {
	wp_delete_post( (int) $blocs_creator_id, true );
}

$blocs_creator_options = array(
	'blocs_creator_reglages',
	'blocs_creator_version',
	'blocs_creator_journal',
	'blocs_creator_animations',
	'blocs_creator_animations_contenu',
	'blocs_creator_ia',
	'blocs_creator_modules',
	'blocs_creator_maintenance',
	'blocs_creator_cookies',
	'blocs_creator_migrations',
	'blocs_creator_migrations_journal',
);

foreach ( $blocs_creator_options as $blocs_creator_option ) {
	delete_option( $blocs_creator_option );
}

delete_post_meta_by_key( '_blocs_creator_noms_blocs' );

global $wpdb;

$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE %s OR option_name LIKE %s
		    OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_bc_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_bc_' ) . '%',
		$wpdb->esc_like( '_transient_blocs_creator_migration_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_blocs_creator_migration_' ) . '%',
		$wpdb->esc_like( 'blocs_creator_migration_verrou_' ) . '%'
	)
);
