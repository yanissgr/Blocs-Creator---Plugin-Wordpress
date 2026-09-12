<?php
/**
 * Métabox « Où ce bloc est-il utilisé ? ».
 *
 * @package BlocsCreator
 *
 * @var array   $definition La définition.
 * @var WP_Post $post       Le post.
 */

defined( 'ABSPATH' ) || exit;

$bc_publications = BC_Usage::publications( BC_Definition::nom( $definition ), 20 );
?>
<div class="bc-metabox">

	<?php if ( empty( $bc_publications ) ) : ?>

		<p class="bc-aide"><?php esc_html_e( 'Nulle part pour l\'instant.', 'blocs-creator' ); ?></p>

	<?php else : ?>

		<ul class="bc-usage-liste">
			<?php foreach ( $bc_publications as $bc_publication ) : ?>
				<li>
					<a href="<?php echo esc_url( (string) get_edit_post_link( $bc_publication ) ); ?>">
						<?php echo esc_html( get_the_title( $bc_publication ) ); ?>
					</a>
					<span class="bc-aide"><?php echo esc_html( get_post_type( $bc_publication ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>

	<?php endif; ?>

</div>
