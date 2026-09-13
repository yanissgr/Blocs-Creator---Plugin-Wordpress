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

$blocs_creator_chemin = Blocs_Creator_Gabarits::chemin_court( Blocs_Creator_Gabarits::chemin_prefere( $bloc ) );
?>
<div <?php echo blocs_creator_attributs( 'bc-secours' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>

	<?php
	echo blocs_creator_rappel( // phpcs:ignore WordPress.Security.EscapeOutput
		sprintf(
			/* translators: 1: nom du bloc, 2: chemin du fichier à créer. */
			__( '« %1$s » n\'a pas encore de gabarit : voici ses champs bruts. Le dessin du bloc s\'écrit dans %2$s.', 'blocs-creator' ),
			$bloc['titre'],
			$blocs_creator_chemin
		)
	);
	?>

	<?php foreach ( $bloc['champs'] as $blocs_creator_champ ) : ?>
		<?php
		if ( ! Blocs_Creator_Champs::porte_valeur( $blocs_creator_champ['type'] ) ) {
			continue;
		}

		$blocs_creator_valeur = $champs[ $blocs_creator_champ['cle'] ] ?? null;
		?>

		<div class="bc-secours__champ">
			<span class="bc-secours__libelle"><?php echo esc_html( $blocs_creator_champ['libelle'] ); ?></span>

			<?php if ( 'image' === $blocs_creator_champ['type'] ) : ?>

				<?php echo blocs_creator_image( $blocs_creator_champ['cle'], array( 'class' => 'bc-secours__image' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

			<?php elseif ( 'texte-riche' === $blocs_creator_champ['type'] ) : ?>

				<div class="bc-secours__valeur"><?php echo wp_kses_post( (string) $blocs_creator_valeur ); ?></div>

			<?php elseif ( 'lien' === $blocs_creator_champ['type'] ) : ?>

				<div class="bc-secours__valeur">
					<?php
					echo blocs_creator_lien_rempli( $blocs_creator_champ['cle'] )
						? esc_html( blocs_creator_lien_titre( $blocs_creator_champ['cle'], blocs_creator_lien_url( $blocs_creator_champ['cle'] ) ) . ' → ' . blocs_creator_lien_url( $blocs_creator_champ['cle'] ) )
						: esc_html__( 'aucune destination', 'blocs-creator' );
					?>
				</div>

			<?php elseif ( 'bascule' === $blocs_creator_champ['type'] ) : ?>

				<div class="bc-secours__valeur">
					<?php echo $blocs_creator_valeur ? esc_html__( 'oui', 'blocs-creator' ) : esc_html__( 'non', 'blocs-creator' ); ?>
				</div>

			<?php elseif ( is_scalar( $blocs_creator_valeur ) ) : ?>

				<div class="bc-secours__valeur"><?php echo esc_html( (string) $blocs_creator_valeur ); ?></div>

			<?php elseif ( $blocs_creator_valeur instanceof WP_Post ) : ?>

				<div class="bc-secours__valeur"><?php echo esc_html( get_the_title( $blocs_creator_valeur ) ); ?></div>

			<?php elseif ( is_array( $blocs_creator_valeur ) ) : ?>

				<div class="bc-secours__valeur">
					<?php
					printf(
						esc_html(
							/* translators: %d: nombre d'éléments. */
							_n( '%d élément, à parcourir dans le gabarit', '%d éléments, à parcourir dans le gabarit', count( $blocs_creator_valeur ), 'blocs-creator' )
						),
						count( $blocs_creator_valeur )
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
