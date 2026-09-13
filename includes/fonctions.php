<?php
/**
 * Les fonctions des gabarits.
 *
 * C'est la seule partie du plugin qu'on écrit tous les jours : l'API d'un
 * fichier de rendu. Elle tient en une quinzaine de fonctions, toutes préfixées
 * `bc_`, toutes utilisables sans rien connaître du reste.
 *
 * Deux principes :
 *
 *   - CE QUI SORT EST ÉCHAPPÉ. blocs_creator_image(), blocs_creator_lien_attrs() et blocs_creator_attributs()
 *     rendent du HTML prêt à poser. Les valeurs de texte, elles, sortent
 *     brutes : le gabarit choisit son échappement, parce que lui seul sait
 *     s'il écrit dans un attribut, dans une balise ou dans une URL.
 *   - RIEN NE CASSE SUR UN CHAMP VIDE. Une image absente ne renvoie pas
 *     d'erreur, un lien sans destination ne rend pas de `<a href="">`.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retourne la valeur prête à l'emploi d'un champ.
 *
 * Selon le type du champ :
 *   - texte, nombre, liste, couleur… : la valeur ;
 *   - image, fichier : un tableau (`id`, `url`, `alt`, `largeur`, `hauteur`…)
 *     ou null ;
 *   - galerie : un tableau de ces tableaux ;
 *   - lien : un tableau (`url`, `titre`, `nouvelOnglet`, `attrs`, `rempli`) ;
 *   - publication : un WP_Post ou null ; publications : un tableau de WP_Post ;
 *   - terme : un WP_Term, ou un tableau de WP_Term ;
 *   - répéteur : un tableau de lignes ; groupe : un tableau.
 *
 * @param string $cle    Clé du champ.
 * @param mixed  $defaut Valeur de repli si le champ n'existe pas.
 * @return mixed
 */
function blocs_creator_champ( $cle, $defaut = null ) {
	return Blocs_Creator_Rendu::champ( $cle, $defaut );
}

/**
 * Retourne la valeur brute d'un champ, telle qu'enregistrée dans le bloc.
 *
 * Utile pour les identifiants : `blocs_creator_brut( 'image' )` donne le numéro de la
 * pièce jointe, là où `blocs_creator_champ( 'image' )` donne le tableau complet.
 *
 * @param string $cle    Clé du champ.
 * @param mixed  $defaut Valeur de repli.
 * @return mixed
 */
function blocs_creator_brut( $cle, $defaut = null ) {
	return Blocs_Creator_Rendu::brut( $cle, $defaut );
}

/**
 * Le champ est-il rempli ?
 *
 * Un zéro numérique compte comme rempli — c'est une valeur. Une chaîne vide,
 * un tableau vide, un null ne comptent pas.
 *
 * @param string $cle Clé du champ.
 * @return bool
 */
function blocs_creator_a_champ( $cle ) {
	$valeur = blocs_creator_champ( $cle );

	if ( is_array( $valeur ) ) {
		return ! empty( $valeur );
	}

	if ( is_string( $valeur ) ) {
		return '' !== trim( $valeur );
	}

	return null !== $valeur && false !== $valeur;
}

/**
 * Retourne les attributs du conteneur du bloc.
 *
 * Reprend `get_block_wrapper_attributes()` : la classe du bloc, l'ancre, la
 * classe personnalisée, l'alignement et les styles de couleur, de typographie
 * et d'espacement que la rédaction a posés. À mettre sur la balise racine du
 * gabarit, sans quoi la moitié des réglages de l'éditeur resteront lettre
 * morte.
 *
 * @param string|array $classes Classes supplémentaires.
 * @param array        $extra   Autres attributs (`style`, `data-…`).
 * @return string
 */
function blocs_creator_attributs( $classes = '', $extra = array() ) {
	if ( is_array( $classes ) ) {
		$classes = implode( ' ', array_filter( $classes ) );
	}

	$args = (array) $extra;

	if ( '' !== $classes ) {
		$args['class'] = trim( $classes . ' ' . ( $args['class'] ?? '' ) );
	}

	return get_block_wrapper_attributes( $args );
}

/**
 * Retourne le contenu des blocs imbriqués, déjà rendu.
 *
 * N'a de sens que dans un bloc portant un champ « Blocs imbriqués ».
 *
 * @return string
 */
function blocs_creator_contenu() {
	return Blocs_Creator_Rendu::contenu();
}

/* ---------------------------------------------------------------------- *
 * Images et fichiers
 * ---------------------------------------------------------------------- */

/**
 * Retourne la balise `<img>` d'un champ image.
 *
 * Si le champ est vide, retourne une surface d'attente — un `<span>` vide que
 * le thème peut habiller. Une section reste ainsi présentable pendant que la
 * rédaction cherche son visuel.
 *
 * @param string $cle    Clé du champ.
 * @param array  $attrs  Attributs HTML de l'image (`class`, `sizes`…).
 * @param string $taille Taille d'image ; par défaut celle réglée sur le champ.
 * @return string
 */
function blocs_creator_image( $cle, $attrs = array(), $taille = '' ) {
	$image = blocs_creator_champ( $cle );

	if ( ! is_array( $image ) || empty( $image['id'] ) || empty( $image['est_image'] ) ) {
		$classe = isset( $attrs['class'] ) ? (string) $attrs['class'] : '';

		return sprintf(
			'<span class="bc-attente %s" aria-hidden="true"></span>',
			esc_attr( $classe )
		);
	}

	$attrs  = wp_parse_args( (array) $attrs, array( 'loading' => 'lazy' ) );
	$taille = '' !== $taille ? $taille : (string) ( $image['taille'] ?? 'large' );

	return (string) wp_get_attachment_image( (int) $image['id'], $taille, false, $attrs );
}

/**
 * Retourne l'URL d'un champ image ou fichier, ou une chaîne vide.
 *
 * @param string $cle    Clé du champ.
 * @param string $taille Taille d'image ; ignorée pour un fichier.
 * @return string
 */
function blocs_creator_url( $cle, $taille = '' ) {
	$media = blocs_creator_champ( $cle );

	if ( ! is_array( $media ) || empty( $media['id'] ) ) {
		return '';
	}

	if ( '' !== $taille && ! empty( $media['est_image'] ) ) {
		$source = wp_get_attachment_image_src( (int) $media['id'], $taille );

		return $source ? $source[0] : '';
	}

	return (string) ( $media['url'] ?? '' );
}

/* ---------------------------------------------------------------------- *
 * Liens
 * ---------------------------------------------------------------------- */

/**
 * Le champ lien a-t-il une destination ?
 *
 * Un `<a href="">` renvoie le visiteur sur la page courante : mieux vaut ne
 * pas rendre le lien du tout que rendre un lien menteur.
 *
 * @param string $cle Clé du champ.
 * @return bool
 */
function blocs_creator_lien_rempli( $cle ) {
	$lien = blocs_creator_champ( $cle );

	return is_array( $lien ) && ! empty( $lien['rempli'] );
}

/**
 * Retourne les attributs HTML d'un champ lien, déjà échappés.
 *
 * Porte le `href`, et le `target`/`rel` quand le nouvel onglet est demandé.
 * À écrire tel quel : `<a <?php echo blocs_creator_lien_attrs( 'cta' ); ?>>`.
 *
 * @param string $cle Clé du champ.
 * @return string
 */
function blocs_creator_lien_attrs( $cle ) {
	$lien = blocs_creator_champ( $cle );

	return is_array( $lien ) ? (string) ( $lien['attrs'] ?? '' ) : '';
}

/**
 * Retourne le libellé d'un champ lien.
 *
 * @param string $cle    Clé du champ.
 * @param string $defaut Libellé de repli.
 * @return string
 */
function blocs_creator_lien_titre( $cle, $defaut = '' ) {
	$lien  = blocs_creator_champ( $cle );
	$titre = is_array( $lien ) ? trim( (string) ( $lien['titre'] ?? '' ) ) : '';

	return '' !== $titre ? $titre : $defaut;
}

/**
 * Retourne l'URL d'un champ lien.
 *
 * @param string $cle Clé du champ.
 * @return string
 */
function blocs_creator_lien_url( $cle ) {
	$lien = blocs_creator_champ( $cle );

	return is_array( $lien ) ? (string) ( $lien['url'] ?? '' ) : '';
}

/* ---------------------------------------------------------------------- *
 * Répéteurs
 * ---------------------------------------------------------------------- */

/**
 * Retourne les lignes d'un répéteur, prêtes pour un `foreach`.
 *
 * Retourne toujours un tableau, même vide : le gabarit n'a pas à tester avant
 * de boucler.
 *
 * @param string $cle Clé du répéteur.
 * @return array<int, array>
 */
function blocs_creator_boucle( $cle ) {
	$lignes = blocs_creator_champ( $cle );

	return is_array( $lignes ) ? $lignes : array();
}

/**
 * Retourne le nombre de lignes d'un répéteur.
 *
 * @param string $cle Clé du répéteur.
 * @return int
 */
function blocs_creator_compte( $cle ) {
	return count( blocs_creator_boucle( $cle ) );
}

/* ---------------------------------------------------------------------- *
 * Divers
 * ---------------------------------------------------------------------- */

/**
 * Retourne une couleur exploitable en CSS.
 *
 * L'éditeur enregistre soit un code hexadécimal, soit un jeton de palette de
 * la forme `var:preset|color|accent`. Cette fonction rend l'un ou l'autre
 * utilisable dans un attribut `style`.
 *
 * @param string $cle    Clé du champ.
 * @param string $defaut Couleur de repli.
 * @return string
 */
function blocs_creator_couleur( $cle, $defaut = '' ) {
	$valeur = (string) blocs_creator_champ( $cle, '' );

	if ( '' === $valeur ) {
		return $defaut;
	}

	if ( str_starts_with( $valeur, 'var:preset|' ) ) {
		$morceaux = explode( '|', $valeur );
		$nom      = array_pop( $morceaux );
		$famille  = $morceaux[1] ?? 'color';

		return sprintf( 'var(--wp--preset--%s--%s)', $famille, $nom );
	}

	return $valeur;
}

/**
 * Ramène un niveau de titre dans une plage valide.
 *
 * Le plancher est h2 par défaut : le h1 appartient au titre de la page, et
 * deux h1 sur une même page cassent le plan du document.
 *
 * @param string|int $cle     Clé d'un champ « niveau de titre », ou un entier.
 * @param int        $minimum Niveau le plus haut autorisé.
 * @return int
 */
function blocs_creator_niveau( $cle, $minimum = 2 ) {
	$niveau  = is_numeric( $cle ) ? (int) $cle : (int) blocs_creator_champ( $cle, 2 );
	$minimum = min( 6, max( 1, (int) $minimum ) );

	return min( 6, max( $minimum, $niveau ) );
}

/**
 * Retourne un rappel visible des seuls utilisateurs qui peuvent corriger.
 *
 * Le visiteur ne voit rien, la rédaction voit ce qu'il reste à remplir.
 *
 * @param string $message Ce qui manque.
 * @return string
 */
function blocs_creator_rappel( $message ) {
	return Blocs_Creator_Rendu::rappel( $message );
}

/**
 * Retourne la définition du bloc en cours de rendu.
 *
 * Pour les gabarits partagés par plusieurs blocs, qui ont besoin de savoir
 * lequel ils dessinent.
 *
 * @return array|null
 */
function blocs_creator_bloc() {
	return Blocs_Creator_Rendu::definition();
}
