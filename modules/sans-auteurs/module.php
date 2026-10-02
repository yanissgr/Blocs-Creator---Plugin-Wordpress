<?php
/**
 * Module « Pas de page d'auteur ».
 *
 * WordPress donne à chaque auteur une page qui liste ses articles
 * (/author/identifiant/, ou /?author=1). Sur un site d'association ou de
 * petite entreprise, personne n'en veut : les articles sont ceux du site, et
 * ces pages dévoilent l'identifiant de connexion de ceux qui écrivent. Donc :
 *
 *   - une page d'auteur (et son flux) renvoie, pour de bon, vers la page des
 *     articles — à défaut, vers l'accueil ;
 *   - les liens vers ces pages mènent au même endroit ;
 *   - les auteurs sortent du plan du site de WordPress (celui de Yoast suit
 *     son propre réglage « Archives d'auteur », à couper aussi) ;
 *   - pour les visiteurs, l'API REST ne liste plus les comptes et ne trie
 *     plus les articles par auteur.
 *
 * L'administration n'est pas touchée : y trier par auteur (« Moi ») marche
 * toujours.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * L'adresse où mènent les pages d'auteur : la page des articles, sinon
 * l'accueil.
 *
 * @return string
 */
function blocs_creator_adresse_sans_auteur() {
	$page_articles = (int) get_option( 'page_for_posts' );

	if ( $page_articles && 'publish' === get_post_status( $page_articles ) ) {
		return get_permalink( $page_articles );
	}

	return home_url( '/' );
}

/**
 * Renvoie les pages d'auteur vers la page des articles.
 *
 * À `wp`, avant la redirection de Yoast (priorité 10, vers l'accueil) et
 * avant celle de WordPress qui change /?author=1 en /author/identifiant/.
 *
 * `wp` tourne aussi dans l'administration (listes des articles, des pages,
 * des médias) : là, trier par auteur doit continuer de marcher.
 */
function blocs_creator_sans_page_auteur() {
	if ( is_admin() || ! is_author() ) {
		return;
	}

	wp_safe_redirect( blocs_creator_adresse_sans_auteur(), 301, 'Blocs Creator' );
	exit;
}
add_action( 'wp', 'blocs_creator_sans_page_auteur', 1 );

/**
 * Les liens vers une page d'auteur mènent à la page des articles.
 *
 * @return string
 */
function blocs_creator_lien_auteur() {
	return blocs_creator_adresse_sans_auteur();
}
add_filter( 'author_link', 'blocs_creator_lien_auteur' );

/**
 * Retire les auteurs du plan du site de WordPress.
 *
 * @param WP_Sitemaps_Provider $fournisseur Fournisseur de plan du site.
 * @param string               $nom         Son nom.
 * @return WP_Sitemaps_Provider|false
 */
function blocs_creator_plan_sans_auteurs( $fournisseur, $nom ) {
	return 'users' === $nom ? false : $fournisseur;
}
add_filter( 'wp_sitemaps_add_provider', 'blocs_creator_plan_sans_auteurs', 10, 2 );

/**
 * Pour les visiteurs, l'API REST ne liste plus les comptes du site.
 *
 * L'éditeur, connecté, les garde (choix de l'auteur d'un article).
 *
 * @param array $routes Routes de l'API.
 * @return array
 */
function blocs_creator_rest_sans_comptes( $routes ) {
	if ( ! is_user_logged_in() ) {
		unset( $routes['/wp/v2/users'], $routes['/wp/v2/users/(?P<id>[\d]+)'] );
	}

	return $routes;
}
add_filter( 'rest_endpoints', 'blocs_creator_rest_sans_comptes' );

/**
 * Pour les visiteurs, l'API REST ne trie plus les articles par auteur :
 * /wp-json/wp/v2/posts?author=1 rend tous les articles.
 *
 * @param array $args Arguments de la requête.
 * @return array
 */
function blocs_creator_rest_articles_sans_auteur( $args ) {
	if ( ! is_user_logged_in() ) {
		unset( $args['author'], $args['author__in'], $args['author__not_in'] );
	}

	return $args;
}
add_filter( 'rest_post_query', 'blocs_creator_rest_articles_sans_auteur' );
