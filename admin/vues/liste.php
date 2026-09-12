<?php
/**
 * L'écran « Tous les blocs ».
 *
 * @package BlocsCreator
 *
 * @var BC_Liste_Table $table Le tableau, déjà préparé.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap bc-wrap">

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Blocs Creator', 'blocs-creator' ); ?></h1>

	<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . BC_Definition::TYPE ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Ajouter un bloc', 'blocs-creator' ); ?>
	</a>

	<hr class="wp-header-end">

	<p class="bc-chapo">
		<?php
		esc_html_e(
			'Tous les blocs de ce site, qu\'ils aient été déclarés ici ou écrits à la main dans un fichier. Les blocs générés se modifient d\'un clic ; les blocs codés se modifient dans leur dossier.',
			'blocs-creator'
		);
		?>
	</p>

	<?php $bc_a_reprendre = BC_Adoption::a_reprendre(); ?>

	<?php if ( ! empty( $bc_a_reprendre ) ) : ?>
		<div class="notice notice-info bc-reprendre-tout">
			<p>
				<?php
				printf(
					esc_html(
						/* translators: %d: nombre de blocs codés. */
						_n(
							'%d bloc de ce site est écrit à la main : il se liste ici, mais ne s\'y modifie pas.',
							'%d blocs de ce site sont écrits à la main : ils se listent ici, mais ne s\'y modifient pas.',
							count( $bc_a_reprendre ),
							'blocs-creator'
						)
					),
					count( $bc_a_reprendre )
				);
				?>
				<?php esc_html_e( 'Les reprendre en main en fait des blocs comme les vôtres — mêmes identifiants, même aspect, champs modifiables. L\'opération se défait bloc par bloc.', 'blocs-creator' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bc_reprendre_tout">
				<?php wp_nonce_field( 'bc_reprendre_tout' ); ?>

				<p>
					<button type="submit" class="button button-primary"
						onclick="return confirm('<?php echo esc_js( __( 'Tous les blocs codés vont devenir des blocs modifiables. Leurs identifiants et leur aspect ne changent pas, et chacun peut être rendu à son code ensuite. On y va ?', 'blocs-creator' ) ); ?>');">
						<?php esc_html_e( 'Tout reprendre en main', 'blocs-creator' ); ?>
					</button>
				</p>
			</form>
		</div>
	<?php endif; ?>

	<?php $bc_reprises = BC_Adoption::reprises_en_cours(); ?>

	<?php if ( ! empty( $bc_reprises ) ) : ?>
		<div class="notice notice-info bc-reprendre-tout">
			<p>
				<?php
				printf(
					esc_html(
						/* translators: %d: nombre de blocs repris. */
						_n(
							'%d bloc de cette liste a été repris à son code : sa définition est modifiable ici, et son dossier d\'origine attend, intact.',
							'%d blocs de cette liste ont été repris à leur code : leurs définitions sont modifiables ici, et leurs dossiers d\'origine attendent, intacts.',
							count( $bc_reprises ),
							'blocs-creator'
						)
					),
					count( $bc_reprises )
				);
				?>
				<?php esc_html_e( 'Les rendre au code supprime leurs définitions et remet leurs dossiers aux commandes. Les pages ne changent pas : mêmes identifiants, même aspect.', 'blocs-creator' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bc_rendre_tout_au_code">
				<?php wp_nonce_field( 'bc_rendre_tout_au_code' ); ?>

				<p>
					<button type="submit" class="button"
						onclick="return confirm('<?php echo esc_js( __( 'Toutes les définitions issues d\'une reprise vont être supprimées, et les blocs codés reprendront la main. Les pages ne changent pas. On y va ?', 'blocs-creator' ) ); ?>');">
						<?php esc_html_e( 'Tout rendre au code', 'blocs-creator' ); ?>
					</button>
				</p>
			</form>
		</div>
	<?php endif; ?>

	<?php $table->views(); ?>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( BC_Admin::PAGE ); ?>">
		<?php if ( isset( $_GET['filtre'] ) ) : ?>
			<input type="hidden" name="filtre" value="<?php echo esc_attr( sanitize_key( wp_unslash( $_GET['filtre'] ) ) ); ?>">
		<?php endif; ?>
		<?php $table->search_box( __( 'Rechercher un bloc', 'blocs-creator' ), 'bc-recherche' ); ?>
		<?php $table->display(); ?>
	</form>

</div>
