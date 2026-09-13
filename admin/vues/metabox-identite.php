<?php
/**
 * Métabox « Identité ».
 *
 * L'espace de noms et l'identifiant composent le nom que le bloc portera dans
 * le contenu des pages. Les changer après coup casse les pages qui s'en
 * servent : l'écran le dit plutôt que de le laisser découvrir.
 *
 * @package BlocsCreator
 *
 * @var array   $definition La définition.
 * @var WP_Post $post       Le post.
 */

defined( 'ABSPATH' ) || exit;

$blocs_creator_usages = Blocs_Creator_Usage::compter( Blocs_Creator_Definition::nom( $definition ) );
?>
<div class="bc-metabox">

	<p class="bc-champ">
		<label for="bc-espace"><?php esc_html_e( 'Espace de noms', 'blocs-creator' ); ?></label>
		<input type="text" id="bc-espace" name="bc[espace]" class="widefat bc-surveille"
			value="<?php echo esc_attr( $definition['espace'] ); ?>"
			pattern="[a-z0-9-]+"
			data-role="espace">
		<span class="bc-aide"><?php esc_html_e( 'Le préfixe commun à vos blocs. En minuscules, sans espace.', 'blocs-creator' ); ?></span>
	</p>

	<p class="bc-champ">
		<label for="bc-slug"><?php esc_html_e( 'Identifiant', 'blocs-creator' ); ?></label>
		<input type="text" id="bc-slug" name="bc[slug]" class="widefat bc-surveille"
			value="<?php echo esc_attr( $definition['slug'] ); ?>"
			pattern="[a-z0-9-]+"
			data-role="slug">
		<span class="bc-aide"><?php esc_html_e( 'Déduit du nom si vous le laissez vide.', 'blocs-creator' ); ?></span>
	</p>

	<?php if ( $blocs_creator_usages > 0 ) : ?>
		<div class="bc-avertissement">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<?php
			printf(
				esc_html(
					/* translators: %d: nombre de publications. */
					_n(
						'Ce bloc est posé dans %d publication. Changer son espace de noms ou son identifiant l\'y rendrait méconnaissable.',
						'Ce bloc est posé dans %d publications. Changer son espace de noms ou son identifiant l\'y rendrait méconnaissable.',
						$blocs_creator_usages,
						'blocs-creator'
					)
				),
				(int) $blocs_creator_usages
			);
			?>
		</div>
	<?php endif; ?>

	<p class="bc-champ">
		<label for="bc-description"><?php esc_html_e( 'Description', 'blocs-creator' ); ?></label>
		<textarea id="bc-description" name="bc[description]" class="widefat" rows="3"><?php echo esc_textarea( $definition['description'] ); ?></textarea>
		<span class="bc-aide"><?php esc_html_e( 'Affichée dans l\'inséreur de blocs, sous le nom.', 'blocs-creator' ); ?></span>
	</p>

	<div class="bc-champ">
		<label for="bc-icone"><?php esc_html_e( 'Icône', 'blocs-creator' ); ?></label>
		<div class="bc-icone-choix">
			<input type="text" id="bc-icone" name="bc[icone]" class="bc-icone-valeur"
				value="<?php echo esc_attr( $definition['icone'] ); ?>">
			<button type="button" class="button bc-icone-ouvrir" aria-expanded="false">
				<span class="dashicons dashicons-<?php echo esc_attr( $definition['icone'] ); ?>" aria-hidden="true"></span>
				<?php esc_html_e( 'Choisir', 'blocs-creator' ); ?>
			</button>
		</div>
		<div class="bc-icone-grille" hidden>
			<?php foreach ( Blocs_Creator_Reglages::dashicons() as $blocs_creator_icone ) : ?>
				<button type="button" class="bc-icone-bouton<?php echo $blocs_creator_icone === $definition['icone'] ? ' est-actif' : ''; ?>"
					data-icone="<?php echo esc_attr( $blocs_creator_icone ); ?>"
					title="<?php echo esc_attr( $blocs_creator_icone ); ?>">
					<span class="dashicons dashicons-<?php echo esc_attr( $blocs_creator_icone ); ?>" aria-hidden="true"></span>
				</button>
			<?php endforeach; ?>
		</div>
	</div>

	<p class="bc-champ">
		<label for="bc-categorie"><?php esc_html_e( 'Catégorie de l\'inséreur', 'blocs-creator' ); ?></label>
		<select id="bc-categorie" name="bc[categorie]" class="widefat">
			<?php
			/*
			 * Les vraies sections de l'inséreur, pas une liste écrite à la
			 * main : celles de WordPress, celles des extensions, celles des
			 * packs. C'est ce qui permet de ranger un bloc dans une section
			 * qui existe déjà plutôt que d'en créer une jumelle.
			 */
			$blocs_creator_categories = Blocs_Creator_Reglages::categories_connues();

			if ( ! isset( $blocs_creator_categories[ blocs_creator()->reglages->get( 'categorie' ) ] ) ) {
				$blocs_creator_categories[ blocs_creator()->reglages->get( 'categorie' ) ] = blocs_creator()->reglages->get( 'categorie_titre' );
			}

			if ( ! isset( $blocs_creator_categories[ $definition['categorie'] ] ) ) {
				$blocs_creator_categories[ $definition['categorie'] ] = $definition['categorie'];
			}

			foreach ( $blocs_creator_categories as $blocs_creator_slug => $blocs_creator_titre ) :
				?>
				<option value="<?php echo esc_attr( $blocs_creator_slug ); ?>" <?php selected( $definition['categorie'], $blocs_creator_slug ); ?>>
					<?php echo esc_html( $blocs_creator_titre ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>

	<?php if ( ! empty( $definition['adoption']['nom'] ) ) : ?>
		<div class="bc-repris">
			<p>
				<span class="bc-etiquette bc-etiquette--repris"><?php esc_html_e( 'Repris du code', 'blocs-creator' ); ?></span>
			</p>
			<p class="bc-aide">
				<?php
				printf(
					/* translators: 1: origine du bloc, 2: chemin du dossier. */
					esc_html__( 'Ce bloc était écrit à la main dans %1$s (%2$s). Son dossier est toujours là : « Rendre au code », dans la liste des blocs, lui redonne la main.', 'blocs-creator' ),
					esc_html( (string) $definition['adoption']['origine'] ),
					'<code>' . esc_html( Blocs_Creator_Gabarits::chemin_court( (string) $definition['adoption']['dossier'] ) ) . '</code>'
				);
				?>
			</p>

			<?php if ( ! empty( $definition['attributs'] ) ) : ?>
				<p class="bc-aide">
					<?php
					printf(
						/* translators: %s: liste de noms d'attributs. */
						esc_html__( 'Réglages conservés tels quels, sans formulaire : %s. Ils gardent leur valeur et le gabarit continue de les lire.', 'blocs-creator' ),
						'<code>' . implode( '</code>, <code>', array_map( 'esc_html', array_keys( $definition['attributs'] ) ) ) . '</code>'
					);
					?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<p class="bc-champ">
		<label for="bc-mots-cles"><?php esc_html_e( 'Mots-clés', 'blocs-creator' ); ?></label>
		<input type="text" id="bc-mots-cles" name="bc[mots_cles]" class="widefat"
			value="<?php echo esc_attr( implode( ', ', $definition['mots_cles'] ) ); ?>">
		<span class="bc-aide"><?php esc_html_e( 'Séparés par des virgules. Ils servent à retrouver le bloc dans la recherche de l\'inséreur.', 'blocs-creator' ); ?></span>
	</p>

</div>
