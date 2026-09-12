<?php
/**
 * Métabox « Apparition ».
 *
 * L'apparition se choisit ici, sur le bloc, et pas dans les pages qui le
 * portent : toutes ses occurrences entrent donc de la même façon, partout.
 * Un réglage posé page par page finit toujours par diverger — trois bannières,
 * trois entrées différentes, et un site qui a l'air improvisé.
 *
 * @package BlocsCreator
 *
 * @var array   $definition La définition.
 * @var WP_Post $post       Le post.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="bc-metabox">

	<div class="bc-champ">
		<label for="bc-animation"><?php esc_html_e( 'Quand le bloc arrive à l\'écran', 'blocs-creator' ); ?></label>

		<?php BC_Animations::champ( 'bc-animation', 'bc[animation]', (string) $definition['animation'] ); ?>
	</div>

	<?php if ( '' !== $definition['animation'] ) : ?>
		<p class="bc-champ">
			<label for="bc-animation-duree"><?php esc_html_e( 'Durée', 'blocs-creator' ); ?></label>
			<input type="number" id="bc-animation-duree" name="bc[animation_duree]" class="small-text"
				min="200" max="3000" step="50"
				value="<?php echo esc_attr( (string) ( $definition['animation_duree'] > 0 ? $definition['animation_duree'] : 720 ) ); ?>">
			<span class="bc-aide"><?php esc_html_e( 'En millisecondes. 720 par défaut — la durée commune à tout le site.', 'blocs-creator' ); ?></span>
		</p>
	<?php else : ?>
		<input type="hidden" name="bc[animation_duree]" value="<?php echo esc_attr( (string) $definition['animation_duree'] ); ?>">
	<?php endif; ?>

	<p class="bc-aide">
		<?php esc_html_e( 'L\'apparition ne se joue pas dans l\'éditeur : on la règle ici, on ne la subit pas à chaque frappe. Et rien ne s\'anime pour qui a demandé moins d\'animations dans les réglages de son appareil.', 'blocs-creator' ); ?>
	</p>

</div>
