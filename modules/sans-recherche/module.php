<?php
/**
 * Module « Pas de recherche » : pour un site qui n'en a pas l'usage.
 *
 *   - une adresse de recherche (`?s=…`, `/search/…`) mène à la page 404,
 *     sans rien chercher en base — les robots qui y glissent du spam tombent
 *     dans le vide ;
 *   - un bloc « Rechercher » qui traînerait dans une page ou un menu ne
 *     s'affiche pas, et il disparaît de l'outil d'ajout de blocs ;
 *   - la loupe de la barre d'outils et la « boîte de recherche » que Yoast
 *     annonce à Google sont retirées.
 *
 * La recherche de l'éditeur (choisir une page pour un lien) passe par l'API
 * REST : elle n'est pas touchée. Une recherche propre au thème (filtrer des
 * articles par `?recherche=`, filtrer une FAQ sur place) non plus.
 *
 * Pensez au modèle 404 du thème : s'il propose un champ de recherche, il
 * mène désormais à une autre 404.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Une recherche devient une page introuvable, avant toute requête.
 *
 * @param array $variables Variables de la requête.
 * @return array
 */
function blocs_creator_sans_recherche( $variables ) {
	if ( isset( $variables['s'] ) && ! is_admin() ) {
		unset( $variables['s'] );
		$variables['error'] = '404';
	}

	return $variables;
}
add_filter( 'request', 'blocs_creator_sans_recherche' );

/**
 * Un bloc « Rechercher » n'affiche plus rien sur le site.
 *
 * @return string
 */
function blocs_creator_sans_bloc_recherche() {
	return '';
}
add_filter( 'render_block_core/search', 'blocs_creator_sans_bloc_recherche' );

/**
 * Le bloc « Rechercher » ne se propose plus dans l'éditeur. Ceux qui existent
 * restent valides (pas de bloc en erreur), ils ne s'affichent simplement pas.
 */
function blocs_creator_sans_bloc_recherche_editeur() {
	wp_register_script( 'blocs-creator-sans-recherche', false, array( 'wp-hooks' ), BLOCS_CREATOR_VERSION, false );
	wp_enqueue_script( 'blocs-creator-sans-recherche' );
	wp_add_inline_script(
		'blocs-creator-sans-recherche',
		'wp.hooks.addFilter("blocks.registerBlockType","blocs-creator/sans-recherche",function(r,n){'
		. 'return "core/search"===n?Object.assign({},r,{supports:Object.assign({},r.supports,{inserter:false})}):r;});'
	);
}
add_action( 'enqueue_block_editor_assets', 'blocs_creator_sans_bloc_recherche_editeur' );

/**
 * La loupe de la barre d'outils, sur le site.
 *
 * @param WP_Admin_Bar $barre La barre d'outils.
 */
function blocs_creator_sans_recherche_barre( $barre ) {
	$barre->remove_node( 'search' );
}
add_action( 'admin_bar_menu', 'blocs_creator_sans_recherche_barre', 999 );

/*
 * Yoast décrit le site à Google avec une action « rechercher sur le site »
 * (la boîte de recherche sous le résultat) : elle mènerait à une 404.
 */
add_filter( 'disable_wpseo_json_ld_search', '__return_true' );
