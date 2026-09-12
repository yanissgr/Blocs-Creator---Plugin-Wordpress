<?php
/**
 * Métabox « Champs du bloc » — le constructeur.
 *
 * Le PHP ne pose ici qu'une coquille : la liste vivante est construite en
 * JavaScript (admin/js/constructeur.js) à partir du JSON déposé dans le champ
 * caché, et la renvoie dans ce même champ à l'enregistrement. Un seul aller et
 * un seul retour, sans requête intermédiaire : on peut ajouter douze champs,
 * les réordonner, changer d'avis, rien n'est écrit avant le bouton Publier.
 *
 * @package BlocsCreator
 *
 * @var array   $definition La définition.
 * @var WP_Post $post       Le post.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="bc-constructeur" id="bc-constructeur">

	<input type="hidden" name="bc_champs_json" id="bc-champs-json"
		value="<?php echo esc_attr( wp_json_encode( $definition['champs'], JSON_UNESCAPED_UNICODE ) ); ?>">

	<noscript>
		<p class="bc-avertissement">
			<?php esc_html_e( 'Le constructeur de champs a besoin de JavaScript. Activez-le pour modifier ce bloc.', 'blocs-creator' ); ?>
		</p>
	</noscript>

	<div class="bc-constructeur__liste" data-role="liste">
		<p class="bc-constructeur__chargement"><?php esc_html_e( 'Chargement…', 'blocs-creator' ); ?></p>
	</div>

	<div class="bc-constructeur__pied">
		<button type="button" class="button button-primary" data-role="ajouter">
			<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
			<?php esc_html_e( 'Ajouter un champ', 'blocs-creator' ); ?>
		</button>

		<p class="bc-aide bc-constructeur__aide">
			<?php
			esc_html_e(
				'Chaque champ devient une case à remplir dans l\'éditeur, et une valeur disponible dans le gabarit sous sa clé.',
				'blocs-creator'
			);
			?>
		</p>
	</div>

</div>
