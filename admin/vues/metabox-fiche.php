<?php
/**
 * Métabox « Fiche du bloc ».
 *
 * Blocs Creator coupe le travail en deux : les champs se déclarent ici, le
 * dessin s'écrit dans un fichier PHP du thème. Quand ces deux moitiés ne sont
 * pas faites par la même personne — ou pas au même moment —, il manque le
 * pont : celui qui dessine doit connaître les clés, les types, et le nom exact
 * du fichier à créer.
 *
 * C'est ce que cette boîte donne, en un bouton. On crée le bloc ici, on colle
 * la fiche à qui écrit le dessin, et c'est tout.
 *
 * @package BlocsCreator
 *
 * @var array   $definition La définition.
 * @var WP_Post $post       Le post.
 */

defined( 'ABSPATH' ) || exit;

$blocs_creator_nouveau = 'auto-draft' === $post->post_status;
?>
<div class="bc-metabox">

	<?php if ( $blocs_creator_nouveau ) : ?>

		<p class="bc-aide">
			<?php esc_html_e( 'La fiche sera prête dès la première publication : elle rassemble l\'identifiant du bloc, le nom du fichier de dessin et la liste des champs avec la ligne qui va chercher chacun.', 'blocs-creator' ); ?>
		</p>

	<?php else : ?>

		<p class="bc-aide">
			<?php esc_html_e( 'Tout ce qu\'il faut pour dessiner ce bloc : son identifiant, le fichier à écrire, et chaque champ avec la ligne qui va le chercher. À copier et à donner à qui écrit le dessin.', 'blocs-creator' ); ?>
		</p>

		<p>
			<button type="button" class="button button-primary" data-bc-copier="#bc-fiche">
				<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
				<?php esc_html_e( 'Copier la fiche', 'blocs-creator' ); ?>
			</button>
			<span class="bc-copie-faite" data-bc-copie-faite hidden><?php esc_html_e( 'Copiée.', 'blocs-creator' ); ?></span>
		</p>

		<details class="bc-details">
			<summary><?php esc_html_e( 'La voir', 'blocs-creator' ); ?></summary>
			<textarea id="bc-fiche" class="bc-code-depart" readonly rows="14" onclick="this.select()"><?php
				echo esc_textarea( Blocs_Creator_Definition::fiche( $definition ) );
			?></textarea>
		</details>

		<p class="bc-aide">
			<a href="<?php
				echo esc_url(
					wp_nonce_url(
						admin_url( 'admin-post.php?action=bc_exporter&blocs=' . (int) $post->ID ),
						'bc_exporter'
					)
				);
			?>"><?php esc_html_e( 'Ou exporter la définition complète (JSON)', 'blocs-creator' ); ?></a>
		</p>

	<?php endif; ?>

</div>
