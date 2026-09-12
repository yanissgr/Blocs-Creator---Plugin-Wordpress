<?php
/**
 * Métabox « Gabarit ».
 *
 * Elle répond à une seule question, celle qu'on se pose en sortant de cet
 * écran : « où est-ce que je dessine ce bloc ? ». Soit le fichier existe et
 * son chemin est écrit, soit il n'existe pas et un bouton le crée.
 *
 * @package BlocsCreator
 *
 * @var array   $definition La définition.
 * @var WP_Post $post       Le post.
 */

defined( 'ABSPATH' ) || exit;

$bc_gabarit = BC_Gabarits::chemin( $definition );
$bc_prefere = BC_Gabarits::chemin_prefere( $definition );
$bc_nouveau = 'auto-draft' === $post->post_status;
$bc_dossier = dirname( $bc_prefere );

// Sur bien des hébergements, le thème arrive par FTP et PHP n'y écrit pas.
// Proposer un bouton qui échouera n'aide personne : on montre alors le code.
$bc_ecrivant = is_dir( $bc_dossier ) ? is_writable( $bc_dossier ) : is_writable( dirname( $bc_dossier ) );
?>
<div class="bc-metabox">

	<?php if ( $bc_nouveau ) : ?>

		<p class="bc-aide">
			<?php esc_html_e( 'Le fichier de rendu sera créé à la publication, avec un point de départ pour chacun de vos champs.', 'blocs-creator' ); ?>
		</p>

	<?php elseif ( '' !== $bc_gabarit ) : ?>

		<p class="bc-gabarit-etat bc-gabarit-etat--ok">
			<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
			<?php esc_html_e( 'Le gabarit existe.', 'blocs-creator' ); ?>
		</p>

		<p class="bc-chemin bc-chemin--bloc"><?php echo esc_html( BC_Gabarits::chemin_court( $bc_gabarit ) ); ?></p>

		<p class="bc-aide">
			<?php esc_html_e( 'Modifiez-le dans votre éditeur de code. Le plugin ne le réécrira jamais, même si vous ajoutez des champs.', 'blocs-creator' ); ?>
		</p>

		<?php $bc_style = BC_Gabarits::chemin_style( $definition ); ?>

		<?php if ( '' !== $bc_style ) : ?>
			<p class="bc-aide">
				<?php esc_html_e( 'Feuille de style chargée avec le bloc :', 'blocs-creator' ); ?>
				<code><?php echo esc_html( basename( $bc_style ) ); ?></code>
			</p>
		<?php else : ?>
			<p class="bc-aide">
				<?php
				printf(
					/* translators: %s: nom de fichier CSS. */
					esc_html__( 'Un fichier %s posé à côté serait chargé automatiquement, et seulement sur les pages qui portent ce bloc.', 'blocs-creator' ),
					'<code>' . esc_html( basename( (string) preg_replace( '/\.php$/', '.css', $bc_gabarit ) ) ) . '</code>'
				);
				?>
			</p>
		<?php endif; ?>

	<?php else : ?>

		<p class="bc-gabarit-etat bc-gabarit-etat--manquant">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<?php esc_html_e( 'Aucun gabarit : le bloc s\'affiche sans mise en forme, avec ses champs les uns sous les autres. C\'est ce fichier qui lui donne son allure.', 'blocs-creator' ); ?>
		</p>

		<p class="bc-chemin bc-chemin--bloc"><?php echo esc_html( BC_Gabarits::chemin_court( $bc_prefere ) ); ?></p>

		<?php if ( $bc_ecrivant ) : ?>
			<p>
				<a class="button button-secondary" href="<?php
					echo esc_url(
						wp_nonce_url(
							admin_url( 'admin-post.php?action=bc_creer_gabarit&bloc=' . (int) $post->ID ),
							'bc_creer_gabarit_' . (int) $post->ID
						)
					);
				?>">
					<?php esc_html_e( 'Créer le fichier', 'blocs-creator' ); ?>
				</a>
			</p>
		<?php else : ?>
			<p class="bc-aide">
				<?php
				printf(
					/* translators: %s: chemin du dossier. */
					esc_html__( '%s n\'est pas accessible en écriture : le plugin ne peut pas déposer le fichier. Copiez le code ci-dessous et déposez-le vous-même, par FTP ou depuis votre éditeur.', 'blocs-creator' ),
					'<code>' . esc_html( BC_Gabarits::chemin_court( $bc_dossier ) ) . '</code>'
				);
				?>
			</p>
		<?php endif; ?>

		<details class="bc-details" <?php echo $bc_ecrivant ? '' : 'open'; ?>>
			<summary><?php esc_html_e( 'Le code de départ', 'blocs-creator' ); ?></summary>
			<textarea class="bc-code-depart" readonly rows="12" onclick="this.select()"><?php
				echo esc_textarea( BC_Gabarits::code_depart( $definition ) );
			?></textarea>
		</details>

	<?php endif; ?>

	<p class="bc-aide">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . BC_Admin::PAGE . '-aide' ) ); ?>">
			<?php esc_html_e( 'Les fonctions disponibles dans un gabarit', 'blocs-creator' ); ?>
		</a>
	</p>

</div>
