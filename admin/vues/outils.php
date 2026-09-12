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

						<?php foreach ( $definitions as $bc_definition ) : ?>
							<label class="bc-case">
								<input type="checkbox" name="blocs[]" value="<?php echo esc_attr( (int) $bc_definition['id'] ); ?>" checked>
								<span>
									<strong><?php echo esc_html( $bc_definition['titre'] ); ?></strong>
									<span class="bc-aide"><?php echo esc_html( BC_Definition::nom( $bc_definition ) ); ?></span>
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

</div>
