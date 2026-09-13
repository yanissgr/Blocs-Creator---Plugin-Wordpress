<?php
/**
 * L'écran de confirmation : reprendre un bloc codé.
 *
 * On ne reprend pas un bloc sans avoir vu ce que ça donne. L'écran montre la
 * traduction exacte que la reprise va écrire — les mêmes champs, les mêmes
 * réglages conservés — puis demande confirmation.
 *
 * @package BlocsCreator
 *
 * @var array $code       Le bloc codé.
 * @var array $definition La définition qui en sera tirée.
 */

defined( 'ABSPATH' ) || exit;

$blocs_creator_gabarit  = Blocs_Creator_Gabarits::chemin_prefere( $definition, true );
$blocs_creator_existant = Blocs_Creator_Gabarits::chemin( $definition );
$blocs_creator_usages   = Blocs_Creator_Usage::compter( $code['nom'] );
$blocs_creator_types    = Blocs_Creator_Champs::catalogue();
?>
<div class="wrap bc-wrap">

	<h1><?php esc_html_e( 'Reprendre la main sur un bloc codé', 'blocs-creator' ); ?></h1>

	<p class="bc-chapo">
		<?php
		printf(
			/* translators: %s: nom complet du bloc. */
			esc_html__( '%s est écrit à la main, dans un dossier. Le reprendre en fait un bloc comme ceux que vous créez ici : ses champs se modifient, son icône se change, son dessin devient un fichier de votre thème.', 'blocs-creator' ),
			'<code>' . esc_html( $code['nom'] ) . '</code>'
		);
		?>
	</p>

	<div class="bc-reprise">

		<h2><?php esc_html_e( 'Ce qui ne bouge pas', 'blocs-creator' ); ?></h2>

		<ul class="bc-liste-puces">
			<li>
				<?php
				printf(
					/* translators: %s: nom complet du bloc. */
					esc_html__( 'L\'identifiant reste %s. Les pages qui portent ce bloc ne verront pas la différence.', 'blocs-creator' ),
					'<code>' . esc_html( $code['nom'] ) . '</code>'
				);
				?>
				<?php if ( $blocs_creator_usages > 0 ) : ?>
					<strong>
						<?php
						printf(
							esc_html(
								/* translators: %d: nombre de publications. */
								_n( '(%d publication concernée)', '(%d publications concernées)', $blocs_creator_usages, 'blocs-creator' )
							),
							(int) $blocs_creator_usages
						);
						?>
					</strong>
				<?php endif; ?>
			</li>
			<li>
				<?php
				if ( '' !== $blocs_creator_existant ) {
					printf(
						/* translators: %s: chemin du fichier. */
						esc_html__( 'Un gabarit existe déjà à %s : il n\'est pas touché, et c\'est lui qui dessinera le bloc.', 'blocs-creator' ),
						'<code>' . esc_html( Blocs_Creator_Gabarits::chemin_court( $blocs_creator_existant ) ) . '</code>'
					);
				} else {
					printf(
						/* translators: 1: fichier d'origine, 2: fichier créé. */
						esc_html__( 'Le dessin du bloc est recopié tel quel de %1$s vers %2$s. Le fichier devient le vôtre : le plugin ne le réécrira jamais.', 'blocs-creator' ),
						'<code>' . esc_html( Blocs_Creator_Gabarits::chemin_court( (string) $code['rendu'] ) ) . '</code>',
						'<code>' . esc_html( Blocs_Creator_Gabarits::chemin_court( $blocs_creator_gabarit ) ) . '</code>'
					);
				}
				?>
			</li>
			<?php if ( ! empty( $definition['assets']['style'] ) || ! empty( $definition['assets']['editor_style'] ) ) : ?>
				<li><?php esc_html_e( 'Les feuilles de style que le bloc déclarait restent attachées : son aspect ne change pas.', 'blocs-creator' ); ?></li>
			<?php endif; ?>
			<?php if ( ! empty( $definition['extras']['variations'] ) ) : ?>
				<li>
					<?php
					printf(
						esc_html(
							/* translators: %d: nombre de variantes. */
							_n( '%d variante conservée.', '%d variantes conservées.', count( (array) $definition['extras']['variations'] ), 'blocs-creator' )
						),
						count( (array) $definition['extras']['variations'] )
					);
					?>
				</li>
			<?php endif; ?>
		</ul>

		<h2><?php esc_html_e( 'Ce qui change', 'blocs-creator' ); ?></h2>

		<ul class="bc-liste-puces">
			<li><?php esc_html_e( 'Le bloc se règle désormais par le formulaire commun — les champs ci-dessous — et non plus par l\'éditeur sur mesure que son pack lui donnait. Un sélecteur maison, une barre d\'outils dessinée pour lui : ces gestes-là sont remplacés par le formulaire.', 'blocs-creator' ); ?></li>
			<li><?php esc_html_e( 'L\'aperçu dans l\'éditeur passe par le rendu du serveur : c\'est exactement le code qui tournera sur le site.', 'blocs-creator' ); ?></li>
			<li><?php esc_html_e( 'L\'opération se défait : « Rendre au code » supprime la définition, et le dossier reprend la main. Le dossier n\'est jamais supprimé.', 'blocs-creator' ); ?></li>
		</ul>

		<h2>
			<?php
			printf(
				esc_html(
					/* translators: %d: nombre de champs. */
					_n( '%d champ que vous pourrez modifier', '%d champs que vous pourrez modifier', count( $definition['champs'] ), 'blocs-creator' )
				),
				count( $definition['champs'] )
			);
			?>
		</h2>

		<?php if ( empty( $definition['champs'] ) ) : ?>
			<p><?php esc_html_e( 'Aucun : ce bloc n\'a pas d\'attribut que le formulaire sache porter.', 'blocs-creator' ); ?></p>
		<?php else : ?>
			<table class="widefat striped bc-table-reprise">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Champ', 'blocs-creator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Clé', 'blocs-creator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type proposé', 'blocs-creator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $definition['champs'] as $blocs_creator_champ ) : ?>
						<tr>
							<td><?php echo esc_html( $blocs_creator_champ['libelle'] ); ?></td>
							<td><code class="bc-code"><?php echo esc_html( $blocs_creator_champ['cle'] ); ?></code></td>
							<td><?php echo esc_html( (string) ( $blocs_creator_types[ $blocs_creator_champ['type'] ]['libelle'] ?? $blocs_creator_champ['type'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="description">
				<?php esc_html_e( 'Le type proposé est une lecture du code, pas une certitude : après la reprise, l\'écran du bloc permet de le changer. Attention tout de même — la clé, elle, ne se change pas sans casser le gabarit qui la lit.', 'blocs-creator' ); ?>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $definition['attributs'] ) ) : ?>
			<h2><?php esc_html_e( 'Les réglages conservés tels quels', 'blocs-creator' ); ?></h2>

			<p>
				<?php esc_html_e( 'Aucun type de champ ne sait porter ceux-là sans en changer la forme — et en changer la forme casserait le fichier de rendu qui les lit. Ils restent donc enregistrés, avec leur valeur actuelle, mais sans formulaire pour les modifier :', 'blocs-creator' ); ?>
			</p>

			<ul class="bc-liste-puces">
				<?php foreach ( $definition['attributs'] as $blocs_creator_nom => $blocs_creator_spec ) : ?>
					<li>
						<code class="bc-code"><?php echo esc_html( $blocs_creator_nom ); ?></code>
						<span class="bc-vide"><?php echo esc_html( (string) ( $blocs_creator_spec['type'] ?? '' ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="bc_reprendre">
			<input type="hidden" name="bloc" value="<?php echo esc_attr( $code['nom'] ); ?>">
			<?php wp_nonce_field( 'bc_reprendre_' . $code['nom'] ); ?>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Reprendre la main sur ce bloc', 'blocs-creator' ); ?>
				</button>

				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Blocs_Creator_Admin::PAGE ) ); ?>">
					<?php esc_html_e( 'Annuler', 'blocs-creator' ); ?>
				</a>
			</p>
		</form>

	</div>
</div>
