<?php
/**
 * Module « Bouton Modifier la page » : un bouton flottant, en bas à droite du
 * site.
 *
 * Il n'apparaît qu'aux personnes connectées qui ont le droit de modifier ce
 * qu'elles regardent : une page, un article, une catégorie ou une étiquette.
 * Les visiteurs ne le voient jamais (rien n'est écrit dans leur page). Son
 * libellé est celui de WordPress : « Modifier la page », « Modifier
 * l'article »…
 *
 * Avec le bandeau cookies sur un téléphone, il passe au-dessus du bandeau.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Le lien de modification de ce qui est affiché, et son libellé.
 *
 * @return array{url: string, libelle: string}|null
 */
function blocs_creator_bouton_modifier_cible() {
	static $cible = false;

	if ( false !== $cible ) {
		return $cible;
	}

	$cible = null;

	if ( ! is_user_logged_in() || is_admin() || is_embed() || is_customize_preview() ) {
		return $cible;
	}

	$objet = get_queried_object();

	// Une page, un article, la page d'accueil ou celle des articles.
	if ( $objet instanceof WP_Post ) {
		$url  = get_edit_post_link( $objet->ID, 'raw' );
		$type = get_post_type_object( $objet->post_type );

		if ( $url && $type ) {
			$cible = array(
				'url'     => $url,
				'libelle' => $type->labels->edit_item,
			);
		}
	} elseif ( $objet instanceof WP_Term ) {
		$url      = get_edit_term_link( $objet, $objet->taxonomy );
		$taxonomy = get_taxonomy( $objet->taxonomy );

		if ( $url && $taxonomy ) {
			$cible = array(
				'url'     => $url,
				'libelle' => $taxonomy->labels->edit_item,
			);
		}
	}

	return $cible;
}

/**
 * Sa feuille de style, seulement quand il s'affiche.
 */
function blocs_creator_bouton_modifier_style() {
	if ( ! blocs_creator_bouton_modifier_cible() ) {
		return;
	}

	wp_enqueue_style(
		'blocs-creator-bouton-modifier',
		Blocs_Creator_Modules::url( 'bouton-modifier', 'bouton-modifier.css' ),
		array(),
		Blocs_Creator_Modules::version( 'bouton-modifier', 'bouton-modifier.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'blocs_creator_bouton_modifier_style' );

/**
 * Écrit le bouton.
 */
function blocs_creator_bouton_modifier() {
	$cible = blocs_creator_bouton_modifier_cible();

	if ( ! $cible ) {
		return;
	}

	printf(
		'<a class="bc-modifier" href="%1$s"><svg class="bc-modifier__picto" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/></svg><span>%2$s</span></a>',
		esc_url( $cible['url'] ),
		esc_html( $cible['libelle'] )
	);
}
add_action( 'wp_footer', 'blocs_creator_bouton_modifier', 6 );
