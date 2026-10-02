<?php
/**
 * Module « Nom des blocs sur une page d'exemple ».
 *
 * Un site livré à une rédaction gagne à avoir une page qui montre tous ses
 * blocs. Pour savoir lequel choisir dans l'éditeur, chacun y porte une
 * étiquette : la famille de l'outil d'insertion (« Mes blocs », « Texte »,
 * « Médias »…), son nom tel qu'on le cherche, et les blocs qu'il contient.
 * Les noms viennent des blocs enregistrés : ils restent justes si un bloc est
 * renommé, et un bloc ajouté à la page reçoit le sien.
 *
 * Seules les pages qui le demandent sont concernées — case « Afficher le nom
 * des blocs » dans la colonne de droite de l'éditeur (méta
 * `_blocs_creator_noms_blocs`, qu'une migration peut aussi poser) — et seuls
 * les blocs posés à même le contenu : ni l'en-tête, ni le pied de page.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * La méta qui marque une page d'exemple.
 */
const BLOCS_CREATOR_NOMS_BLOCS_META = '_blocs_creator_noms_blocs';

/**
 * Déclare la méta, pour que l'éditeur la lise et l'écrive.
 */
function blocs_creator_noms_blocs_meta() {
	register_post_meta(
		'page',
		BLOCS_CREATOR_NOMS_BLOCS_META,
		array(
			'type'          => 'boolean',
			'single'        => true,
			'default'       => false,
			'show_in_rest'  => true,
			'auth_callback' => static function ( $permis, $cle, $post_id ) {
				return current_user_can( 'edit_post', $post_id );
			},
		)
	);
}
add_action( 'init', 'blocs_creator_noms_blocs_meta' );

/**
 * La case de l'éditeur des pages.
 */
function blocs_creator_noms_blocs_editeur() {
	$ecran = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $ecran || 'page' !== $ecran->post_type ) {
		return;
	}

	wp_enqueue_script(
		'blocs-creator-noms-blocs',
		Blocs_Creator_Modules::url( 'noms-blocs', 'noms-blocs-editeur.js' ),
		array( 'wp-components', 'wp-data', 'wp-editor', 'wp-element', 'wp-i18n', 'wp-plugins' ),
		Blocs_Creator_Modules::version( 'noms-blocs', 'noms-blocs-editeur.js' ),
		true
	);
	wp_set_script_translations( 'blocs-creator-noms-blocs', 'blocs-creator', BLOCS_CREATOR_DIR . 'languages' );
}
add_action( 'enqueue_block_editor_assets', 'blocs_creator_noms_blocs_editeur' );

/**
 * La page affichée demande-t-elle le nom des blocs ?
 *
 * @return bool
 */
function blocs_creator_noms_blocs_actifs() {
	if ( is_admin() || wp_is_serving_rest_request() || ! is_singular() ) {
		return false;
	}

	return (bool) get_post_meta( get_queried_object_id(), BLOCS_CREATOR_NOMS_BLOCS_META, true );
}

/**
 * Repère les blocs à étiqueter, au moment où WordPress les prépare.
 *
 * Le contenu de la page passe par le filtre `the_content` : les blocs qu'il
 * rend sans parent sont ceux qu'on a posés à même la page.
 *
 * L'étiquette est posée par le filtre propre au bloc, en tout dernier : les
 * autres filtres (apparitions…) travaillent sur la première balise du bloc,
 * qui doit rester la sienne.
 *
 * @param array         $bloc   Le bloc analysé.
 * @param array         $source Le bloc d'origine.
 * @param WP_Block|null $parent Le bloc parent.
 * @return array
 */
function blocs_creator_noms_blocs_reperer( $bloc, $source, $parent ) {
	$nom = (string) ( $bloc['blockName'] ?? '' );

	if ( '' === $nom || null !== $parent || ! doing_filter( 'the_content' ) || ! blocs_creator_noms_blocs_actifs() ) {
		return $bloc;
	}

	// Le contenu de la page, pas celui d'un article listé ailleurs sur elle.
	if ( (int) get_the_ID() !== (int) get_queried_object_id() ) {
		return $bloc;
	}

	$bloc['bc_nom_bloc'] = true;

	if ( ! has_filter( 'render_block_' . $nom, 'blocs_creator_noms_blocs_etiqueter' ) ) {
		add_filter( 'render_block_' . $nom, 'blocs_creator_noms_blocs_etiqueter', PHP_INT_MAX, 2 );
	}

	return $bloc;
}
add_filter( 'render_block_data', 'blocs_creator_noms_blocs_reperer', 10, 3 );

/**
 * Pose l'étiquette au-dessus d'un bloc marqué.
 *
 * @param string $html Le bloc rendu.
 * @param array  $bloc Le bloc analysé.
 * @return string
 */
function blocs_creator_noms_blocs_etiqueter( $html, $bloc ) {
	if ( empty( $bloc['bc_nom_bloc'] ) || '' === trim( $html ) ) {
		return $html;
	}

	return blocs_creator_noms_blocs_etiquette( $bloc ) . $html;
}

/**
 * Le nom d'un bloc, tel que l'outil d'insertion l'affiche.
 *
 * @param string $nom Nom complet du bloc.
 * @return string
 */
function blocs_creator_noms_blocs_titre( $nom ) {
	$type = WP_Block_Type_Registry::get_instance()->get_registered( $nom );

	return $type && '' !== (string) $type->title ? (string) $type->title : $nom;
}

/**
 * La famille d'un bloc dans l'outil d'insertion.
 *
 * @param string $nom Nom complet du bloc.
 * @return string
 */
function blocs_creator_noms_blocs_famille( $nom ) {
	static $familles = null;

	if ( null === $familles ) {
		$familles = wp_list_pluck( get_block_categories( get_post() ), 'title', 'slug' );
	}

	$type = WP_Block_Type_Registry::get_instance()->get_registered( $nom );

	return $type && isset( $familles[ $type->category ] ) ? (string) $familles[ $type->category ] : '';
}

/**
 * Les noms des blocs contenus, sans doublons, dans l'ordre où ils arrivent.
 *
 * @param array    $blocs Blocs enfants.
 * @param string[] $noms  Noms déjà trouvés.
 * @return string[]
 */
function blocs_creator_noms_blocs_enfants( $blocs, $noms = array() ) {
	foreach ( (array) $blocs as $bloc ) {
		if ( empty( $bloc['blockName'] ) ) {
			continue;
		}

		$titre = blocs_creator_noms_blocs_titre( $bloc['blockName'] );

		if ( ! in_array( $titre, $noms, true ) ) {
			$noms[] = $titre;
		}

		$noms = blocs_creator_noms_blocs_enfants( $bloc['innerBlocks'] ?? array(), $noms );
	}

	return $noms;
}

/**
 * L'étiquette d'un bloc. Elle prend la largeur du bloc : large pour une
 * section, celle du texte pour un paragraphe.
 *
 * @param array $bloc Le bloc analysé.
 * @return string
 */
function blocs_creator_noms_blocs_etiquette( $bloc ) {
	$nom     = $bloc['blockName'];
	$type    = WP_Block_Type_Registry::get_instance()->get_registered( $nom );
	$aligne  = $bloc['attrs']['align'] ?? ( $type->attributes['align']['default'] ?? '' );
	$famille = blocs_creator_noms_blocs_famille( $nom );
	$enfants = blocs_creator_noms_blocs_enfants( $bloc['innerBlocks'] ?? array() );

	$html = sprintf(
		'<div class="bc-nom-bloc%1$s">',
		in_array( $aligne, array( 'wide', 'full' ), true ) ? ' alignwide' : ''
	);

	if ( '' !== $famille ) {
		$html .= sprintf( '<span class="bc-nom-bloc__famille">%s</span>', esc_html( $famille ) );
	}

	$html .= sprintf( '<strong class="bc-nom-bloc__titre">%s</strong>', esc_html( blocs_creator_noms_blocs_titre( $nom ) ) );

	if ( $enfants ) {
		$html .= sprintf(
			'<span class="bc-nom-bloc__enfants">%1$s %2$s</span>',
			esc_html__( 'Contient :', 'blocs-creator' ),
			esc_html( implode( ', ', $enfants ) )
		);
	}

	return $html . '</div>';
}

/**
 * La feuille de style des étiquettes, seulement sur les pages qui en ont.
 */
function blocs_creator_noms_blocs_style() {
	if ( ! blocs_creator_noms_blocs_actifs() ) {
		return;
	}

	wp_enqueue_style(
		'blocs-creator-noms-blocs',
		Blocs_Creator_Modules::url( 'noms-blocs', 'noms-blocs.css' ),
		array(),
		Blocs_Creator_Modules::version( 'noms-blocs', 'noms-blocs.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'blocs_creator_noms_blocs_style' );
