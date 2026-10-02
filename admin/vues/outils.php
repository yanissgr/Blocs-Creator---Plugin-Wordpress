<?php
/**
 * L'écran des outils : import et export.
 *
 * @package BlocsCreator
 *
 * @var array $definitions Les définitions du site.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap bc-wrap">

	<h1><?php esc_html_e( 'Outils', 'blocs-creator' ); ?></h1>

	<p class="bc-chapo">
		<?php esc_html_e( 'Emportez vos blocs d\'un site à l\'autre. L\'export ne contient que les définitions — les gabarits sont des fichiers de votre thème, copiez-les comme le reste du thème.', 'blocs-creator' ); ?>
	</p>

	<div class="bc-colonnes">

		<div class="bc-carte">
			<h2><?php esc_html_e( 'Exporter', 'blocs-creator' ); ?></h2>

			<?php if ( empty( $definitions ) ) : ?>

				<p class="bc-aide"><?php esc_html_e( 'Aucun bloc généré à exporter pour l\'instant.', 'blocs-creator' ); ?></p>

			<?php else : ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="bc_exporter">
					<?php wp_nonce_field( 'bc_exporter' ); ?>

					<fieldset class="bc-cases">
						<legend class="screen-reader-text"><?php esc_html_e( 'Blocs à exporter', 'blocs-creator' ); ?></legend>

						<?php foreach ( $definitions as $blocs_creator_definition ) : ?>
							<label class="bc-case">
								<input type="checkbox" name="blocs[]" value="<?php echo esc_attr( (int) $blocs_creator_definition['id'] ); ?>" checked>
								<span>
									<strong><?php echo esc_html( $blocs_creator_definition['titre'] ); ?></strong>
									<span class="bc-aide"><?php echo esc_html( Blocs_Creator_Definition::nom( $blocs_creator_definition ) ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</fieldset>

					<?php submit_button( __( 'Télécharger le fichier JSON', 'blocs-creator' ), 'secondary' ); ?>
				</form>

			<?php endif; ?>
		</div>

		<div class="bc-carte">
			<h2><?php esc_html_e( 'Importer', 'blocs-creator' ); ?></h2>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bc_importer">
				<?php wp_nonce_field( 'bc_importer' ); ?>

				<p class="bc-champ">
					<label for="bc-fichier"><?php esc_html_e( 'Fichier JSON', 'blocs-creator' ); ?></label>
					<input type="file" id="bc-fichier" name="fichier" accept="application/json,.json">
				</p>

				<p class="bc-champ">
					<label for="bc-json"><?php esc_html_e( 'Ou collez le JSON', 'blocs-creator' ); ?></label>
					<textarea id="bc-json" name="json" rows="6" class="widefat" placeholder="{ &quot;blocs&quot;: [ … ] }"></textarea>
				</p>

				<p>
					<label>
						<input type="checkbox" name="ecraser" value="1">
						<?php esc_html_e( 'Remplacer les blocs de même identifiant', 'blocs-creator' ); ?>
					</label>
				</p>

				<p class="bc-aide">
					<?php esc_html_e( 'Sans cette case, un bloc déjà présent est ignoré plutôt qu\'écrasé.', 'blocs-creator' ); ?>
				</p>

				<?php submit_button( __( 'Importer', 'blocs-creator' ), 'primary' ); ?>
			</form>
		</div>

	</div>

	<?php
	/*
	 * Le fichier du thème : blocs-creator/definitions.json. C'est par lui que
	 * les définitions voyagent avec le thème — Git, FTP, une IA qui écrit le
	 * site. Voir Blocs_Creator_Theme.
	 */
	$blocs_creator_etat    = Blocs_Creator_Theme::etat();
	$blocs_creator_libelle = array(
		'absent'    => __( 'dans le thème seulement', 'blocs-creator' ),
		'different' => __( 'différent', 'blocs-creator' ),
		'identique' => __( 'identique', 'blocs-creator' ),
		'base'      => __( 'en base seulement', 'blocs-creator' ),
	);
	?>
	<div class="bc-carte">
		<h2><?php esc_html_e( 'Le fichier du thème', 'blocs-creator' ); ?></h2>

		<p class="bc-aide">
			<?php
			printf(
				/* translators: %s: chemin du fichier. */
				esc_html__( 'Les définitions peuvent vivre aussi dans le thème, dans %s : elles voyagent alors avec lui. À l\'activation du thème, les blocs qui manquent sont installés ; ici, on compare et on recopie dans un sens ou dans l\'autre. La même chose existe en ligne de commande : wp blocs-creator definitions.', 'blocs-creator' ),
				'<code>' . esc_html( Blocs_Creator_Gabarits::chemin_court( Blocs_Creator_Theme::fichier() ) ) . '</code>'
			);
			?>
		</p>

		<?php if ( is_wp_error( $blocs_creator_etat ) ) : ?>

			<p><?php echo esc_html( $blocs_creator_etat->get_error_message() ); ?></p>

		<?php else : ?>

			<table class="widefat striped bc-theme-etat">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Bloc', 'blocs-creator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'État', 'blocs-creator' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Action', 'blocs-creator' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $blocs_creator_etat['blocs'] as $blocs_creator_nom => $blocs_creator_bloc ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $blocs_creator_bloc['titre'] ); ?></strong>
								<div><code class="bc-code"><?php echo esc_html( $blocs_creator_nom ); ?></code></div>
							</td>
							<td>
								<span class="bc-etat bc-etat--<?php echo esc_attr( $blocs_creator_bloc['etat'] ); ?>"><?php echo esc_html( $blocs_creator_libelle[ $blocs_creator_bloc['etat'] ] ?? $blocs_creator_bloc['etat'] ); ?></span>
							</td>
							<td>
								<?php if ( in_array( $blocs_creator_bloc['etat'], array( 'absent', 'different' ), true ) ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="bc_theme_importer">
										<input type="hidden" name="mode" value="<?php echo esc_attr( $blocs_creator_nom ); ?>">
										<?php wp_nonce_field( 'bc_theme_importer' ); ?>
										<button type="submit" class="button button-small"><?php esc_html_e( 'Prendre la version du thème', 'blocs-creator' ); ?></button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

		<?php endif; ?>

		<div class="bc-theme-actions">
			<?php if ( ! is_wp_error( $blocs_creator_etat ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="bc_theme_importer">
					<input type="hidden" name="mode" value="manquants">
					<?php wp_nonce_field( 'bc_theme_importer' ); ?>
					<button type="submit" class="button"><?php esc_html_e( 'Installer les blocs qui manquent', 'blocs-creator' ); ?></button>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					onsubmit="return window.confirm( <?php echo esc_attr( wp_json_encode( __( 'Remplacer toutes les définitions par celles du thème ? Ce qui a été modifié ici depuis sera perdu.', 'blocs-creator' ) ) ); ?> );">
					<input type="hidden" name="action" value="bc_theme_importer">
					<input type="hidden" name="mode" value="tout">
					<?php wp_nonce_field( 'bc_theme_importer' ); ?>
					<button type="submit" class="button"><?php esc_html_e( 'Tout prendre du thème', 'blocs-creator' ); ?></button>
				</form>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				onsubmit="return window.confirm( <?php echo esc_attr( wp_json_encode( __( 'Écrire les définitions de ce site dans le fichier du thème ? Le fichier actuel sera remplacé.', 'blocs-creator' ) ) ); ?> );">
				<input type="hidden" name="action" value="bc_theme_exporter">
				<?php wp_nonce_field( 'bc_theme_exporter' ); ?>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Écrire ces définitions dans le thème', 'blocs-creator' ); ?></button>
			</form>
		</div>
	</div>

</div>
