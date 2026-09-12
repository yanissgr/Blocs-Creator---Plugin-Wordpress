<?php
/**
 * Le rendu de secours.
 *
 * Appelé quand un bloc généré n'a pas encore de gabarit. Il n'essaie pas de
 * deviner une mise en page : il montre les valeurs saisies, pour que la
 * rédaction voie que le bloc fonctionne, et rappelle à qui peut y remédier où
 * écrire le vrai rendu.
 *
 * Ce fichier ne sert jamais une fois le gabarit écrit.
 *
 * @package BlocsCreator
 *
 * @var array    $attributes Les valeurs brutes.
 * @var string   $content    Les blocs imbriqués, déjà rendus.
 * @var WP_Block $block      L'instance du bloc.
 * @var array    $champs     Les valeurs prêtes à l'emploi.
 * @var array    $bloc       La définition du bloc.
 */

defined( 'ABSPATH' ) || exit;

$bc_chemin = BC_Gabarits::chemin_court( BC_Gabarits::chemin_prefere( $bloc ) );
?>
<div <?php echo bc_attributs( 'bc-secours' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>

	<?php
	echo bc_rappel( // phpcs:ignore WordPress.Security.EscapeOutput
		sprintf(
			/* translators: 1: nom du bloc, 2: chemin du fichier à créer. */
			__( '« %1$s » n\'a pas encore de gabarit : voici ses champs bruts. Le dessin du bloc s\'écrit dans %2$s.', 'blocs-creator' ),
			$bloc['titre'],
			$bc_chemin
		)
	);
	?>

	<?php foreach ( $bloc['champs'] as $bc_champ ) : ?>
		<?php
		if ( ! BC_Champs::porte_valeur( $bc_champ['type'] ) ) {
			continue;
		}

		$bc_valeur = $champs[ $bc_champ['cle'] ] ?? null;
		?>

		<div class="bc-secours__champ">
			<span class="bc-secours__libelle"><?php echo esc_html( $bc_champ['libelle'] ); ?></span>

			<?php if ( 'image' === $bc_champ['type'] ) : ?>

				<?php echo bc_image( $bc_champ['cle'], array( 'class' => 'bc-secours__image' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

			<?php elseif ( 'texte-riche' === $bc_champ['type'] ) : ?>

				<div class="bc-secours__valeur"><?php echo wp_kses_post( (string) $bc_valeur ); ?></div>

			<?php elseif ( 'lien' === $bc_champ['type'] ) : ?>

				<div class="bc-secours__valeur">
					<?php
					echo bc_lien_rempli( $bc_champ['cle'] )
						? esc_html( bc_lien_titre( $bc_champ['cle'], bc_lien_url( $bc_champ['cle'] ) ) . ' → ' . bc_lien_url( $bc_champ['cle'] ) )
						: esc_html__( 'aucune destination', 'blocs-creator' );
					?>
				</div>

			<?php elseif ( 'bascule' === $bc_champ['type'] ) : ?>

				<div class="bc-secours__valeur">
					<?php echo $bc_valeur ? esc_html__( 'oui', 'blocs-creator' ) : esc_html__( 'non', 'blocs-creator' ); ?>
				</div>

			<?php elseif ( is_scalar( $bc_valeur ) ) : ?>

				<div class="bc-secours__valeur"><?php echo esc_html( (string) $bc_valeur ); ?></div>

			<?php elseif ( $bc_valeur instanceof WP_Post ) : ?>

				<div class="bc-secours__valeur"><?php echo esc_html( get_the_title( $bc_valeur ) ); ?></div>

			<?php elseif ( is_array( $bc_valeur ) ) : ?>

				<div class="bc-secours__valeur">
					<?php
					printf(
						esc_html(
							/* translators: %d: nombre d'éléments. */
							_n( '%d élément, à parcourir dans le gabarit', '%d éléments, à parcourir dans le gabarit', count( $bc_valeur ), 'blocs-creator' )
						),
						count( $bc_valeur )
					);
					?>
				</div>

			<?php endif; ?>
		</div>

	<?php endforeach; ?>

	<?php if ( '' !== trim( $content ) ) : ?>
		<div class="bc-secours__interieur"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
	<?php endif; ?>

</div>
