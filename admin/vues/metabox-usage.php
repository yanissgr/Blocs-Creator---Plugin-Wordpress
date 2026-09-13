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

$blocs_creator_publications = Blocs_Creator_Usage::publications( Blocs_Creator_Definition::nom( $definition ), 20 );
?>
<div class="bc-metabox">

	<?php if ( empty( $blocs_creator_publications ) ) : ?>

		<p class="bc-aide"><?php esc_html_e( 'Nulle part pour l\'instant.', 'blocs-creator' ); ?></p>

	<?php else : ?>

		<ul class="bc-usage-liste">
			<?php foreach ( $blocs_creator_publications as $blocs_creator_publication ) : ?>
				<li>
					<a href="<?php echo esc_url( (string) get_edit_post_link( $blocs_creator_publication ) ); ?>">
						<?php echo esc_html( get_the_title( $blocs_creator_publication ) ); ?>
					</a>
					<span class="bc-aide"><?php echo esc_html( get_post_type( $blocs_creator_publication ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>

	<?php endif; ?>

</div>
