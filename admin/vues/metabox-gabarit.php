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

$blocs_creator_gabarit = Blocs_Creator_Gabarits::chemin( $definition );
$blocs_creator_prefere = Blocs_Creator_Gabarits::chemin_prefere( $definition );
$blocs_creator_nouveau = 'auto-draft' === $post->post_status;
$blocs_creator_dossier = dirname( $blocs_creator_prefere );

// Sur bien des hébergements, le thème arrive par FTP et PHP n'y écrit pas.
// Proposer un bouton qui échouera n'aide personne : on montre alors le code.
$blocs_creator_ecrivant = is_dir( $blocs_creator_dossier ) ? wp_is_writable( $blocs_creator_dossier ) : wp_is_writable( dirname( $blocs_creator_dossier ) );
?>
<div class="bc-metabox">

	<?php if ( $blocs_creator_nouveau ) : ?>

		<p class="bc-aide">
			<?php esc_html_e( 'Le fichier de rendu sera créé à la publication, avec un point de départ pour chacun de vos champs.', 'blocs-creator' ); ?>
		</p>

	<?php elseif ( '' !== $blocs_creator_gabarit ) : ?>

		<p class="bc-gabarit-etat bc-gabarit-etat--ok">
			<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
			<?php esc_html_e( 'Le gabarit existe.', 'blocs-creator' ); ?>
		</p>

		<p class="bc-chemin bc-chemin--bloc"><?php echo esc_html( Blocs_Creator_Gabarits::chemin_court( $blocs_creator_gabarit ) ); ?></p>

		<p class="bc-aide">
			<?php esc_html_e( 'Modifiez-le dans votre éditeur de code. Le plugin ne le réécrira jamais, même si vous ajoutez des champs.', 'blocs-creator' ); ?>
		</p>

		<?php $blocs_creator_style = Blocs_Creator_Gabarits::chemin_style( $definition ); ?>

		<?php if ( '' !== $blocs_creator_style ) : ?>
			<p class="bc-aide">
				<?php esc_html_e( 'Feuille de style chargée avec le bloc :', 'blocs-creator' ); ?>
				<code><?php echo esc_html( basename( $blocs_creator_style ) ); ?></code>
			</p>
		<?php else : ?>
			<p class="bc-aide">
				<?php
				printf(
					/* translators: %s: nom de fichier CSS. */
					esc_html__( 'Un fichier %s posé à côté serait chargé automatiquement, et seulement sur les pages qui portent ce bloc.', 'blocs-creator' ),
					'<code>' . esc_html( basename( (string) preg_replace( '/\.php$/', '.css', $blocs_creator_gabarit ) ) ) . '</code>'
				);
				?>
			</p>
		<?php endif; ?>

	<?php else : ?>

		<p class="bc-gabarit-etat bc-gabarit-etat--manquant">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<?php esc_html_e( 'Aucun gabarit : le bloc s\'affiche sans mise en forme, avec ses champs les uns sous les autres. C\'est ce fichier qui lui donne son allure.', 'blocs-creator' ); ?>
		</p>

		<p class="bc-chemin bc-chemin--bloc"><?php echo esc_html( Blocs_Creator_Gabarits::chemin_court( $blocs_creator_prefere ) ); ?></p>

		<?php if ( $blocs_creator_ecrivant ) : ?>
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
					'<code>' . esc_html( Blocs_Creator_Gabarits::chemin_court( $blocs_creator_dossier ) ) . '</code>'
				);
				?>
			</p>
		<?php endif; ?>

		<details class="bc-details" <?php echo $blocs_creator_ecrivant ? '' : 'open'; ?>>
			<summary><?php esc_html_e( 'Le code de départ', 'blocs-creator' ); ?></summary>
			<textarea class="bc-code-depart" readonly rows="12" onclick="this.select()"><?php
				echo esc_textarea( Blocs_Creator_Gabarits::code_depart( $definition ) );
			?></textarea>
		</details>

	<?php endif; ?>

	<p class="bc-aide">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Blocs_Creator_Admin::PAGE . '-aide' ) ); ?>">
			<?php esc_html_e( 'Les fonctions disponibles dans un gabarit', 'blocs-creator' ); ?>
		</a>
	</p>

</div>
