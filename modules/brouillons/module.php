<?php
/**
 * Module « Brouillons des formulaires » : les réponses d'un visiteur gardées
 * sur son appareil, pour qu'il les retrouve si la page se recharge, se ferme
 * ou si le réseau coupe.
 *
 * Tout se passe dans le navigateur (brouillons.js) : les réponses sont
 * rangées dans son stockage local, jamais envoyées au site avant l'envoi du
 * formulaire, et effacées dès que celui-ci est parti — au plus tard au bout
 * de 30 jours. Les fichiers joints et les champs cachés ne sont jamais
 * gardés.
 *
 * C'est un traceur de confort : avec le module « Bandeau cookies », il
 * attend l'accord du visiteur. Sans cet accord, rien n'est gardé, et un petit
 * mot le dit en tête du formulaire, avec un bouton pour accepter sur place.
 * Sans bandeau, les réponses sont gardées sans rien demander.
 *
 * Deux façons de s'en servir :
 *
 *   - DÉCLARATIVE. Un formulaire qui porte `data-bc-brouillon` (la valeur, si
 *     elle est donnée, sert de clé : « contact », « inscription ») est suivi
 *     tout seul, sur toutes les pages. Pour les petits mots, le gabarit écrit
 *     blocs_creator_brouillon_messages() au début du formulaire.
 *   - PAR LE SCRIPT, pour un formulaire qui a plus à garder (l'étape en
 *     cours, un dessin) : blocs_creator_brouillon_assets() charge le script,
 *     et `window.blocsCreatorBrouillon.suivre( form, { cle, extra,
 *     extraRempli, restaurer, vider } )` le branche.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Durée de vie d'un brouillon, en jours.
 */
const BLOCS_CREATOR_BROUILLON_JOURS = 30;

/**
 * Charge le script et la feuille des brouillons.
 *
 * Appelée d'elle-même quand un bloc rendu contient un formulaire
 * `data-bc-brouillon` ; un gabarit qui écrit son formulaire autrement
 * l'appelle lui-même.
 *
 * @return string Le nom du script, à mettre en dépendance du script du formulaire.
 */
function blocs_creator_brouillon_assets() {
	if ( ! wp_script_is( 'blocs-creator-brouillon', 'registered' ) ) {
		wp_register_script(
			'blocs-creator-brouillon',
			Blocs_Creator_Modules::url( 'brouillons', 'brouillons.js' ),
			array(),
			Blocs_Creator_Modules::version( 'brouillons', 'brouillons.js' ),
			true
		);

		wp_add_inline_script(
			'blocs-creator-brouillon',
			'window.blocsCreatorBrouillonJours = ' . (int) BLOCS_CREATOR_BROUILLON_JOURS . ';',
			'before'
		);
	}

	wp_enqueue_style(
		'blocs-creator-brouillon',
		Blocs_Creator_Modules::url( 'brouillons', 'brouillons.css' ),
		array(),
		Blocs_Creator_Modules::version( 'brouillons', 'brouillons.css' )
	);

	wp_enqueue_script( 'blocs-creator-brouillon' );

	return 'blocs-creator-brouillon';
}

/**
 * Charge les brouillons quand un bloc rendu porte un formulaire à suivre.
 *
 * @param string $html HTML du bloc.
 * @return string
 */
function blocs_creator_brouillon_reperer( $html ) {
	if ( ! wp_script_is( 'blocs-creator-brouillon' ) && str_contains( $html, 'data-bc-brouillon' ) && ! is_admin() ) {
		blocs_creator_brouillon_assets();
	}

	return $html;
}
add_filter( 'render_block', 'blocs_creator_brouillon_reperer', 30 );

/**
 * Écrit les petits messages des brouillons, cachés : le script montre celui
 * qui convient.
 *
 *   - sans accord pour les cookies : « vos réponses ne sont pas gardées »,
 *     avec « Accepter les cookies » ;
 *   - juste après cet accord : « c'est noté », quelques secondes ;
 *   - de retour sur un formulaire commencé : « vos réponses sont là », avec
 *     de quoi tout effacer.
 *
 * À écrire à l'intérieur du formulaire, en tête.
 */
function blocs_creator_brouillon_messages() {
	$picto = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
	?>
	<div class="bc-brouillon">
		<div class="bc-brouillon__message is-note" data-brouillon-note hidden>
			<span class="bc-brouillon__picto" aria-hidden="true"><?php echo $picto; // phpcs:ignore WordPress.Security.EscapeOutput -- SVG fixe. ?></span>
			<div class="bc-brouillon__corps">
				<p class="bc-brouillon__texte"><?php esc_html_e( 'Petit mot au passage : comme les cookies ne sont pas acceptés, vos réponses ne sont pas mises de côté. Si la page se recharge ou se ferme, il faudra tout reprendre.', 'blocs-creator' ); ?></p>
				<button type="button" class="bc-brouillon__accepter" data-cookies-accepter><?php esc_html_e( 'Accepter les cookies', 'blocs-creator' ); ?></button>
			</div>
			<button type="button" class="bc-brouillon__fermer" data-brouillon-fermer aria-label="<?php esc_attr_e( 'Masquer ce message', 'blocs-creator' ); ?>"><span aria-hidden="true">×</span></button>
		</div>

		<div class="bc-brouillon__message is-retrouve" data-brouillon-merci role="status" hidden>
			<span class="bc-brouillon__picto" aria-hidden="true"><?php echo $picto; // phpcs:ignore WordPress.Security.EscapeOutput -- SVG fixe. ?></span>
			<p class="bc-brouillon__texte"><?php esc_html_e( 'C\'est noté ! Vos réponses sont désormais mises de côté, au cas où.', 'blocs-creator' ); ?></p>
		</div>

		<div class="bc-brouillon__message is-retrouve" data-brouillon-retrouve hidden>
			<span class="bc-brouillon__picto" aria-hidden="true"><?php echo $picto; // phpcs:ignore WordPress.Security.EscapeOutput -- SVG fixe. ?></span>
			<p class="bc-brouillon__texte"><?php esc_html_e( 'Bon retour ! Vos réponses vous attendaient, là où vous les aviez laissées.', 'blocs-creator' ); ?></p>
			<button type="button" class="bc-brouillon__effacer" data-brouillon-effacer data-confirmation="<?php esc_attr_e( 'Effacer toutes vos réponses et repartir de zéro ?', 'blocs-creator' ); ?>"><?php esc_html_e( 'Tout effacer', 'blocs-creator' ); ?></button>
		</div>
	</div>
	<?php
}
