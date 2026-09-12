<?php
/**
 * Métabox « Comportement ».
 *
 * Ce que Gutenberg ajoutera par-dessus le bloc : l'ancre, l'alignement, les
 * couleurs. Chaque case cochée ajoute une section dans la colonne de droite de
 * l'éditeur — d'où la retenue par défaut : on coche ce qu'on va utiliser.
 *
 * @package BlocsCreator
 *
 * @var array   $definition La définition.
 * @var WP_Post $post       Le post.
 */

defined( 'ABSPATH' ) || exit;

$bc_supports = array(
	'anchor'          => array(
		__( 'Ancre HTML', 'blocs-creator' ),
		__( 'Pour pointer un lien vers ce bloc.', 'blocs-creator' ),
	),
	'align'           => array(
		__( 'Largeur', 'blocs-creator' ),
		__( 'Large et pleine largeur.', 'blocs-creator' ),
	),
	'customClassName' => array(
		__( 'Classe CSS', 'blocs-creator' ),
		__( 'Une classe supplémentaire, saisie à la main.', 'blocs-creator' ),
	),
	'color'           => array(
		__( 'Couleurs', 'blocs-creator' ),
		__( 'Fond, texte et dégradés.', 'blocs-creator' ),
	),
	'typography'      => array(
		__( 'Typographie', 'blocs-creator' ),
		__( 'Corps et interligne.', 'blocs-creator' ),
	),
	'spacing'         => array(
		__( 'Espacements', 'blocs-creator' ),
		__( 'Marges et remplissage.', 'blocs-creator' ),
	),
	'multiple'        => array(
		__( 'Plusieurs fois par page', 'blocs-creator' ),
		__( 'Décochez pour un bloc unique, comme une bannière.', 'blocs-creator' ),
	),
	'reusable'        => array(
		__( 'Bloc réutilisable', 'blocs-creator' ),
		__( 'Autorise la mise en bibliothèque.', 'blocs-creator' ),
	),
);
?>
<div class="bc-metabox">

	<fieldset class="bc-cases">
		<legend class="screen-reader-text"><?php esc_html_e( 'Réglages fournis par Gutenberg', 'blocs-creator' ); ?></legend>

		<?php foreach ( $bc_supports as $bc_cle => $bc_libelle ) : ?>
			<label class="bc-case">
				<input type="checkbox" name="bc[supports][<?php echo esc_attr( $bc_cle ); ?>]" value="1"
					<?php checked( ! empty( $definition['supports'][ $bc_cle ] ) ); ?>>
				<span>
					<strong><?php echo esc_html( $bc_libelle[0] ); ?></strong>
					<span class="bc-aide"><?php echo esc_html( $bc_libelle[1] ); ?></span>
				</span>
			</label>
		<?php endforeach; ?>
	</fieldset>

	<hr>

	<p class="bc-champ">
		<label for="bc-apercu"><?php esc_html_e( 'Aperçu dans l\'éditeur', 'blocs-creator' ); ?></label>
		<select id="bc-apercu" name="bc[apercu]" class="widefat">
			<option value="serveur" <?php selected( $definition['apercu'], 'serveur' ); ?>>
				<?php esc_html_e( 'Le bloc tel qu\'il sera sur le site', 'blocs-creator' ); ?>
			</option>
			<option value="formulaire" <?php selected( $definition['apercu'], 'formulaire' ); ?>>
				<?php esc_html_e( 'Le formulaire des champs', 'blocs-creator' ); ?>
			</option>
		</select>
		<span class="bc-aide">
			<?php esc_html_e( 'Dans les deux cas, la barre d\'outils du bloc permet de basculer de l\'un à l\'autre.', 'blocs-creator' ); ?>
		</span>
	</p>

	<p class="bc-champ">
		<label for="bc-parent"><?php esc_html_e( 'Bloc parent', 'blocs-creator' ); ?></label>
		<input type="text" id="bc-parent" name="bc[parent]" class="widefat"
			value="<?php echo esc_attr( implode( ', ', $definition['parent'] ) ); ?>"
			placeholder="<?php echo esc_attr( 'core/group' ); ?>">
		<span class="bc-aide">
			<?php esc_html_e( 'Laissez vide dans presque tous les cas. Rempli, le bloc ne s\'insère plus qu\'à l\'intérieur de celui-ci.', 'blocs-creator' ); ?>
		</span>
	</p>

</div>
