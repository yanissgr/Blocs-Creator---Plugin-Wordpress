<?php
/**
 * L'aide à l'écriture d'un gabarit.
 *
 * La documentation du plugin tient dans cet écran, et il est volontairement
 * court : quinze fonctions, un tableau par type de champ. Tout ce qui aurait
 * demandé un long paragraphe a été corrigé dans le code plutôt qu'expliqué
 * ici.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

$bc_retours = array(
	'texte'           => array( 'string', 'esc_html( bc_champ( \'titre\' ) )' ),
	'texte-long'      => array( 'string', 'nl2br( esc_html( bc_champ( \'chapo\' ) ) )' ),
	'texte-riche'     => array( 'string (HTML)', 'wp_kses_post( bc_champ( \'corps\' ) )' ),
	'nombre'          => array( 'float', 'esc_html( bc_champ( \'duree\' ) )' ),
	'bascule'         => array( 'bool', 'if ( bc_champ( \'afficher_image\' ) ) : …' ),
	'liste'           => array( 'string', 'esc_attr( bc_champ( \'sens\' ) )' ),
	'boutons'         => array( 'string', 'esc_attr( bc_champ( \'alignement\' ) )' ),
	'cases'           => array( 'string[]', 'in_array( \'pdf\', bc_champ( \'formats\' ), true )' ),
	'niveau-titre'    => array( 'int (1-6)', 'printf( \'<h%1$d>%2$s</h%1$d>\', bc_niveau( \'niveau\' ), … )' ),
	'couleur'         => array( 'string', 'style="color: <?php echo esc_attr( bc_couleur( \'teinte\' ) ); ?>"' ),
	'icone'           => array( 'string', 'esc_attr( bc_champ( \'icone\' ) )' ),
	'point-focal'     => array( 'array', 'style="object-position: <?php echo esc_attr( bc_champ( \'cadrage\' )[\'position\'] ); ?>"' ),
	'type-publication' => array( 'string', 'get_posts( array( \'post_type\' => bc_champ( \'type\' ) ) )' ),
	'image'           => array( 'array|null', 'bc_image( \'photo\', array( \'class\' => \'carte__media\' ) )' ),
	'galerie'         => array( 'array[]', 'foreach ( bc_champ( \'images\' ) as $image ) : …' ),
	'fichier'         => array( 'array|null', '$f = bc_champ( \'pdf\' ); echo esc_url( $f[\'url\'] );' ),
	'lien'            => array( 'array', '<a <?php echo bc_lien_attrs( \'cta\' ); ?>>' ),
	'contenu'         => array( 'WP_Post|null', 'get_permalink( bc_champ( \'article\' ) )' ),
	'contenus'        => array( 'WP_Post[]', 'foreach ( bc_champ( \'articles\' ) as $post ) : …' ),
	'taxonomie'       => array( 'WP_Term[]', 'foreach ( bc_champ( \'categories\' ) as $terme ) : …' ),
	'groupe'          => array( 'array', '$bloc = bc_champ( \'entete\' ); echo $bloc[\'titre\'];' ),
	'repeteur'        => array( 'array[]', 'foreach ( bc_boucle( \'lignes\' ) as $ligne ) : …' ),
	'blocs-imbriques' => array( '—', 'echo bc_contenu();' ),
);

$bc_fonctions = array(
	array( 'bc_champ( $cle, $defaut = null )', __( 'La valeur du champ, prête à l\'emploi. La forme dépend du type — voir le tableau ci-dessous.', 'blocs-creator' ) ),
	array( 'bc_brut( $cle, $defaut = null )', __( 'La valeur telle qu\'enregistrée. Pour une image, l\'identifiant de la pièce jointe.', 'blocs-creator' ) ),
	array( 'bc_a_champ( $cle )', __( 'Le champ est-il rempli ? Un zéro compte comme rempli, une chaîne vide non.', 'blocs-creator' ) ),
	array( 'bc_attributs( $classes, $extra )', __( 'Les attributs de la balise racine : classe du bloc, ancre, alignement, couleurs. Indispensable.', 'blocs-creator' ) ),
	array( 'bc_contenu()', __( 'Les blocs imbriqués, déjà rendus.', 'blocs-creator' ) ),
	array( 'bc_image( $cle, $attrs, $taille )', __( 'La balise img, ou une surface d\'attente si le champ est vide.', 'blocs-creator' ) ),
	array( 'bc_url( $cle, $taille )', __( 'L\'URL d\'une image ou d\'un fichier.', 'blocs-creator' ) ),
	array( 'bc_lien_rempli( $cle )', __( 'Le lien a-t-il une destination ? À tester avant d\'écrire la balise a.', 'blocs-creator' ) ),
	array( 'bc_lien_attrs( $cle )', __( 'href, target et rel, déjà échappés.', 'blocs-creator' ) ),
	array( 'bc_lien_titre( $cle, $defaut )', __( 'Le libellé du lien.', 'blocs-creator' ) ),
	array( 'bc_lien_url( $cle )', __( 'L\'URL seule.', 'blocs-creator' ) ),
	array( 'bc_boucle( $cle )', __( 'Les lignes d\'un répéteur. Toujours un tableau, même vide.', 'blocs-creator' ) ),
	array( 'bc_compte( $cle )', __( 'Le nombre de lignes d\'un répéteur.', 'blocs-creator' ) ),
	array( 'bc_couleur( $cle, $defaut )', __( 'Une couleur utilisable en CSS, que la palette du thème ou un code hexadécimal.', 'blocs-creator' ) ),
	array( 'bc_niveau( $cle, $minimum = 2 )', __( 'Un niveau de titre borné. Le plancher est h2 : le h1 appartient à la page.', 'blocs-creator' ) ),
	array( 'bc_rappel( $message )', __( 'Un rappel visible des seuls rédacteurs. Le visiteur ne voit rien.', 'blocs-creator' ) ),
	array( 'bc_bloc()', __( 'La définition du bloc en cours, pour un gabarit partagé par plusieurs blocs.', 'blocs-creator' ) ),
);
?>
<div class="wrap bc-wrap bc-aide-page">

	<h1><?php esc_html_e( 'Écrire un gabarit', 'blocs-creator' ); ?></h1>

	<p class="bc-chapo">
		<?php esc_html_e( 'Un bloc généré n\'embarque aucun balisage : son apparence est un fichier PHP de votre thème. Le plugin l\'appelle avec les valeurs saisies, et se retire.', 'blocs-creator' ); ?>
	</p>

	<div class="bc-carte">
		<h2><?php esc_html_e( 'Où va le fichier', 'blocs-creator' ); ?></h2>

		<p>
			<?php
			printf(
				/* translators: 1: dossier des gabarits, 2: nom de fichier. */
				esc_html__( 'Dans %1$s, sous le nom %2$s. Le plugin le crée pour vous à la publication du bloc, avec un point de départ pour chaque champ déclaré — et ne le réécrit plus jamais ensuite.', 'blocs-creator' ),
				'<code>' . esc_html( BC_Gabarits::chemin_court( get_stylesheet_directory() . '/' . blocs_creator()->reglages->get( 'dossier_gabarits' ) ) ) . '</code>',
				'<code>identifiant-du-bloc.php</code>'
			);
			?>
		</p>

		<p>
			<?php
			printf(
				/* translators: %s: nom de fichier CSS. */
				esc_html__( 'Un %s posé à côté est chargé automatiquement, et seulement sur les pages qui portent le bloc.', 'blocs-creator' ),
				'<code>identifiant-du-bloc.css</code>'
			);
			?>
		</p>
	</div>

	<div class="bc-carte">
		<h2><?php esc_html_e( 'Un exemple complet', 'blocs-creator' ); ?></h2>

		<pre class="bc-exemple"><code><?php
		echo esc_html(
			'<?php' . "\n" .
			'/**' . "\n" .
			' * Gabarit du bloc « Témoignages ».' . "\n" .
			' *' . "\n" .
			' * @var array  $attributes Les valeurs brutes.' . "\n" .
			' * @var string $content    Les blocs imbriqués, rendus.' . "\n" .
			' * @var array  $champs     Les valeurs prêtes à l\'emploi.' . "\n" .
			' */' . "\n\n" .
			'defined( \'ABSPATH\' ) || exit;' . "\n\n" .
			'?>' . "\n" .
			'<section <?php echo bc_attributs( \'temoignages\' ); ?>>' . "\n\n" .
			'	<?php if ( bc_a_champ( \'titre\' ) ) : ?>' . "\n" .
			'		<?php printf( \'<h%1$d class="temoignages__titre">%2$s</h%1$d>\', bc_niveau( \'niveau\' ), esc_html( bc_champ( \'titre\' ) ) ); ?>' . "\n" .
			'	<?php endif; ?>' . "\n\n" .
			'	<ul class="temoignages__liste">' . "\n" .
			'		<?php foreach ( bc_boucle( \'lignes\' ) as $ligne ) : ?>' . "\n" .
			'			<li class="temoignages__item">' . "\n" .
			'				<blockquote><?php echo wp_kses_post( $ligne[\'citation\'] ); ?></blockquote>' . "\n" .
			'				<cite><?php echo esc_html( $ligne[\'auteur\'] ); ?></cite>' . "\n" .
			'			</li>' . "\n" .
			'		<?php endforeach; ?>' . "\n" .
			'	</ul>' . "\n\n" .
			'	<?php if ( bc_lien_rempli( \'cta\' ) ) : ?>' . "\n" .
			'		<a class="temoignages__lien" <?php echo bc_lien_attrs( \'cta\' ); ?>><?php echo esc_html( bc_lien_titre( \'cta\' ) ); ?></a>' . "\n" .
			'	<?php endif; ?>' . "\n\n" .
			'</section>'
		);
		?></code></pre>
	</div>

	<div class="bc-carte">
		<h2><?php esc_html_e( 'Les fonctions', 'blocs-creator' ); ?></h2>

		<table class="widefat striped bc-table-aide">
			<tbody>
				<?php foreach ( $bc_fonctions as $bc_fonction ) : ?>
					<tr>
						<td class="bc-table-aide__code"><code><?php echo esc_html( $bc_fonction[0] ); ?></code></td>
						<td><?php echo esc_html( $bc_fonction[1] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="bc-carte">
		<h2><?php esc_html_e( 'Ce que rend chaque type de champ', 'blocs-creator' ); ?></h2>

		<table class="widefat striped bc-table-aide">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Type', 'blocs-creator' ); ?></th>
					<th><?php esc_html_e( 'bc_champ() rend', 'blocs-creator' ); ?></th>
					<th><?php esc_html_e( 'Exemple', 'blocs-creator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $bc_retours as $bc_type => $bc_ligne ) : ?>
					<?php $bc_def = BC_Champs::type( $bc_type ); ?>
					<tr>
						<td><strong><?php echo esc_html( $bc_def ? $bc_def['libelle'] : $bc_type ); ?></strong></td>
						<td><code><?php echo esc_html( $bc_ligne[0] ); ?></code></td>
						<td class="bc-table-aide__code"><code><?php echo esc_html( $bc_ligne[1] ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="bc-carte">
		<h2><?php esc_html_e( 'Les crochets', 'blocs-creator' ); ?></h2>

		<table class="widefat striped bc-table-aide">
			<tbody>
				<tr>
					<td class="bc-table-aide__code"><code>blocs_creator_champs</code></td>
					<td><?php esc_html_e( 'Les valeurs, juste avant qu\'elles n\'arrivent au gabarit.', 'blocs-creator' ); ?></td>
				</tr>
				<tr>
					<td class="bc-table-aide__code"><code>blocs_creator_args_bloc</code></td>
					<td><?php esc_html_e( 'Les arguments passés à register_block_type().', 'blocs-creator' ); ?></td>
				</tr>
				<tr>
					<td class="bc-table-aide__code"><code>blocs_creator_candidats_gabarit</code></td>
					<td><?php esc_html_e( 'Les chemins où le gabarit est cherché.', 'blocs-creator' ); ?></td>
				</tr>
				<tr>
					<td class="bc-table-aide__code"><code>blocs_creator_catalogue_champs</code></td>
					<td><?php esc_html_e( 'Pour ajouter un type de champ.', 'blocs-creator' ); ?></td>
				</tr>
				<tr>
					<td class="bc-table-aide__code"><code>blocs_creator_emplacements_blocs</code></td>
					<td><?php esc_html_e( 'Les dossiers où les blocs codés sont découverts.', 'blocs-creator' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

</div>
