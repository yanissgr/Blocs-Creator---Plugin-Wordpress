<?php
/**
 * L'écran des réglages.
 *
 * Trois onglets, un seul formulaire : tout est envoyé d'un coup, quel que soit
 * l'onglet ouvert. C'est ce qui permet de cocher trois blocs ici, de changer
 * une animation là, et de n'appuyer qu'une fois sur « Enregistrer ».
 *
 * Deux choses en découlent, et elles ont coûté assez cher pour être écrites :
 *
 *   1. L'ÉCRAN DIT CE QU'IL A FAIT. Un formulaire qui se recharge à l'identique
 *      sans un mot ressemble à un bouton cassé. Le formulaire n'est donc plus
 *      envoyé à `options.php` — qui fait dépendre l'enregistrement de quatre
 *      choses qu'on ne voit pas, et se tait quand l'une lâche — mais à notre
 *      propre gestionnaire, qui écrit, relit, compare, et revient sur l'onglet
 *      qu'on avait sous les yeux. Voir BC_Admin::action_reglages().
 *   2. L'ONGLET DES BLOCS N'ENVOIE PAS CENT TRENTE CHAMPS. Il en envoie un seul,
 *      la liste des blocs écartés, composée au moment de l'envoi. Un champ par
 *      bloc dépasserait `max_input_vars` sur un site fourni, et le formulaire
 *      arriverait tronqué — c'est-à-dire avec des blocs qu'on croirait cochés.
 *      Sans JavaScript, les cases repartent comme avant : rien n'est perdu.
 *
 * @package BlocsCreator
 *
 * @var BC_Reglages $reglages Les réglages.
 */

defined( 'ABSPATH' ) || exit;

$bc_valeurs    = $reglages->tout();
$bc_dossier    = get_stylesheet_directory() . '/' . trim( (string) $bc_valeurs['dossier_gabarits'], '/' );
$bc_option     = BC_Reglages::OPTION;
$bc_categories = BC_Reglages::categories_connues();
$bc_inventaire = BC_Disponibilite::inventaire();
$bc_ecartes    = count( BC_Disponibilite::ecartes() );

// L'onglet qu'on avait sous les yeux. Il voyage dans l'adresse : c'est ce qui
// permet à l'enregistrement de revenir là où l'on était, et à un signet de
// pointer sur la bonne section.
$bc_onglets = array( 'general', 'disponibilite', 'animations' );
$bc_onglet  = isset( $_GET['bc_onglet'] ) ? sanitize_key( wp_unslash( $_GET['bc_onglet'] ) ) : 'general';
$bc_onglet  = in_array( $bc_onglet, $bc_onglets, true ) ? $bc_onglet : 'general';

$bc_total = 0;

foreach ( $bc_inventaire as $bc_groupe_total ) {
	$bc_total += count( $bc_groupe_total['blocs'] );
}
?>
<div class="wrap bc-wrap bc-reglages">

	<h1><?php esc_html_e( 'Réglages', 'blocs-creator' ); ?></h1>

	<nav class="nav-tab-wrapper bc-onglets" aria-label="<?php esc_attr_e( 'Sections des réglages', 'blocs-creator' ); ?>">
		<button type="button" class="nav-tab<?php echo 'general' === $bc_onglet ? ' nav-tab-active' : ''; ?>" data-bc-onglet="general">
			<?php esc_html_e( 'Général', 'blocs-creator' ); ?>
		</button>
		<button type="button" class="nav-tab<?php echo 'disponibilite' === $bc_onglet ? ' nav-tab-active' : ''; ?>" data-bc-onglet="disponibilite">
			<?php esc_html_e( 'Blocs disponibles', 'blocs-creator' ); ?>
			<?php if ( $bc_ecartes > 0 ) : ?>
				<span class="bc-pastille"><?php echo esc_html( (string) $bc_ecartes ); ?></span>
			<?php endif; ?>
		</button>
		<button type="button" class="nav-tab<?php echo 'animations' === $bc_onglet ? ' nav-tab-active' : ''; ?>" data-bc-onglet="animations">
			<?php esc_html_e( 'Apparitions', 'blocs-creator' ); ?>
		</button>
	</nav>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bc-formulaire" data-bc-formulaire>
		<input type="hidden" name="action" value="bc_reglages">
		<input type="hidden" name="bc_onglet" value="<?php echo esc_attr( $bc_onglet ); ?>" data-bc-onglet-champ>
		<?php wp_nonce_field( 'bc_reglages' ); ?>

		<!-- ---------------------------------------------------------- -->
		<section class="bc-onglet" data-bc-panneau="general" <?php echo 'general' === $bc_onglet ? '' : 'hidden'; ?>>

			<p class="bc-chapo">
				<?php esc_html_e( 'Ces réglages valent pour les blocs à venir. Les blocs déjà créés gardent ce qu\'ils ont : un identifiant de bloc ne change pas sans casser les pages qui s\'en servent.', 'blocs-creator' ); ?>
			</p>

			<table class="form-table" role="presentation">

				<tr>
					<th scope="row">
						<label for="bc-r-espace"><?php esc_html_e( 'Espace de noms', 'blocs-creator' ); ?></label>
					</th>
					<td>
						<input type="text" id="bc-r-espace" class="regular-text"
							name="<?php echo esc_attr( $bc_option ); ?>[espace]"
							value="<?php echo esc_attr( $bc_valeurs['espace'] ); ?>"
							pattern="[a-z0-9-]+">
						<p class="description">
							<?php
							printf(
								/* translators: %s: exemple de nom de bloc. */
								esc_html__( 'Le préfixe des blocs que vous créerez : %s. En minuscules, sans espace.', 'blocs-creator' ),
								'<code>' . esc_html( $bc_valeurs['espace'] ) . '/temoignages</code>'
							);
							?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="bc-r-categorie"><?php esc_html_e( 'Catégorie dans l\'inséreur', 'blocs-creator' ); ?></label>
					</th>
					<td>
						<select id="bc-r-categorie" name="<?php echo esc_attr( $bc_option ); ?>[categorie]" class="regular-text">
							<?php
							$bc_liste = $bc_categories;

							if ( ! isset( $bc_liste[ $bc_valeurs['categorie'] ] ) ) {
								$bc_liste[ $bc_valeurs['categorie'] ] = $bc_valeurs['categorie_titre'];
							}

							foreach ( $bc_liste as $bc_slug => $bc_titre ) :
								?>
								<option value="<?php echo esc_attr( $bc_slug ); ?>"
									data-bc-titre="<?php echo esc_attr( $bc_titre ); ?>"
									<?php selected( $bc_valeurs['categorie'], $bc_slug ); ?>>
									<?php
									printf(
										/* translators: 1: titre de la catégorie, 2: identifiant. */
										esc_html__( '%1$s (%2$s)', 'blocs-creator' ),
										esc_html( $bc_titre ),
										esc_html( $bc_slug )
									);
									?>
								</option>
							<?php endforeach; ?>
						</select>

						<p class="description">
							<?php esc_html_e( 'La section où vos blocs apparaissent quand on clique sur le « + » de l\'éditeur. Choisissez-y une section qui existe déjà — celle d\'un pack, par exemple — plutôt que d\'en créer une du même nom : deux sections identiques dans l\'inséreur, c\'est un rangement coupé en deux.', 'blocs-creator' ); ?>
						</p>

						<p>
							<label for="bc-r-categorie-titre" class="bc-etiquette-champ">
								<?php esc_html_e( 'Ou créez la vôtre :', 'blocs-creator' ); ?>
							</label>
							<input type="text" id="bc-r-categorie-titre" class="regular-text"
								name="<?php echo esc_attr( $bc_option ); ?>[categorie_titre]"
								value="<?php echo esc_attr( $bc_valeurs['categorie_titre'] ); ?>">
						</p>
						<p class="description">
							<?php esc_html_e( 'Ce titre n\'est utilisé que si l\'identifiant choisi au-dessus n\'existe encore nulle part.', 'blocs-creator' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="bc-r-dossier"><?php esc_html_e( 'Dossier des gabarits', 'blocs-creator' ); ?></label>
					</th>
					<td>
						<input type="text" id="bc-r-dossier" class="regular-text"
							name="<?php echo esc_attr( $bc_option ); ?>[dossier_gabarits]"
							value="<?php echo esc_attr( $bc_valeurs['dossier_gabarits'] ); ?>">
						<p class="description">
							<?php esc_html_e( 'Relatif au thème actif. Les fichiers de rendu y sont cherchés, et créés.', 'blocs-creator' ); ?>
						</p>
						<p class="description">
							<code><?php echo esc_html( BC_Gabarits::chemin_court( $bc_dossier ) ); ?></code>
							<?php if ( is_dir( $bc_dossier ) ) : ?>
								<span class="bc-oui dashicons dashicons-yes-alt" aria-hidden="true"></span>
								<?php
								echo is_writable( $bc_dossier )
									? esc_html__( 'existe, accessible en écriture', 'blocs-creator' )
									: esc_html__( 'existe, mais verrouillé en écriture', 'blocs-creator' );
								?>
							<?php else : ?>
								<span class="bc-non dashicons dashicons-marker" aria-hidden="true"></span>
								<?php esc_html_e( 'sera créé au premier gabarit', 'blocs-creator' ); ?>
							<?php endif; ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'À la création d\'un bloc', 'blocs-creator' ); ?></th>
					<td>
						<label>
							<input type="checkbox" value="1"
								name="<?php echo esc_attr( $bc_option ); ?>[creer_gabarit]"
								<?php checked( ! empty( $bc_valeurs['creer_gabarit'] ) ); ?>>
							<?php esc_html_e( 'Créer le fichier de gabarit automatiquement', 'blocs-creator' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Un fichier de départ, avec un exemple de code pour chaque champ déclaré. Il n\'est jamais réécrit ensuite.', 'blocs-creator' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'À la désinstallation', 'blocs-creator' ); ?></th>
					<td>
						<label>
							<input type="checkbox" value="1"
								name="<?php echo esc_attr( $bc_option ); ?>[supprimer_donnees]"
								<?php checked( ! empty( $bc_valeurs['supprimer_donnees'] ) ); ?>>
							<?php esc_html_e( 'Supprimer les définitions de blocs et les réglages', 'blocs-creator' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Décoché, tout reste en base : désinstaller puis réinstaller le plugin retrouve vos blocs. Les fichiers de gabarit du thème ne sont jamais supprimés, dans un cas comme dans l\'autre.', 'blocs-creator' ); ?>
						</p>
					</td>
				</tr>

			</table>
		</section>

		<!-- ---------------------------------------------------------- -->
		<section class="bc-onglet" data-bc-panneau="disponibilite" <?php echo 'disponibilite' === $bc_onglet ? '' : 'hidden'; ?>>

			<p class="bc-chapo">
				<?php esc_html_e( 'Ce que le « + » de l\'éditeur a le droit de proposer. Décocher un bloc ne touche à aucune page : les blocs déjà posés continuent de s\'afficher et de se modifier — ils ne s\'insèrent simplement plus.', 'blocs-creator' ); ?>
			</p>

			<?php
			$bc_connus = array();

			foreach ( $bc_inventaire as $bc_groupe_connu ) {
				foreach ( $bc_groupe_connu['blocs'] as $bc_bloc_connu ) {
					$bc_connus[] = $bc_bloc_connu['nom'];
				}
			}
			?>

			<div class="bc-dispo-barre">

				<p class="bc-dispo-bilan" data-bc-bilan
					data-bc-modele-un="<?php esc_attr_e( '%1$s blocs en tout, %2$s écarté.', 'blocs-creator' ); ?>"
					data-bc-modele-plusieurs="<?php esc_attr_e( '%1$s blocs en tout, %2$s écartés.', 'blocs-creator' ); ?>">
					<?php
					printf(
						esc_html(
							/* translators: 1: nombre total de blocs, 2: nombre de blocs écartés. */
							_n( '%1$s blocs en tout, %2$s écarté.', '%1$s blocs en tout, %2$s écartés.', $bc_ecartes, 'blocs-creator' )
						),
						'<strong>' . esc_html( number_format_i18n( $bc_total ) ) . '</strong>',
						'<strong>' . esc_html( number_format_i18n( $bc_ecartes ) ) . '</strong>'
					);
					?>
				</p>

				<div class="bc-dispo-outils">
					<label for="bc-filtre-blocs" class="screen-reader-text"><?php esc_html_e( 'Filtrer les blocs', 'blocs-creator' ); ?></label>
					<input type="search" id="bc-filtre-blocs" class="regular-text"
						placeholder="<?php esc_attr_e( 'Filtrer par nom…', 'blocs-creator' ); ?>">

					<div class="bc-segments" role="group" aria-label="<?php esc_attr_e( 'Ce que la liste montre', 'blocs-creator' ); ?>">
						<button type="button" class="bc-segment est-actif" data-bc-vue="tous"><?php esc_html_e( 'Tous', 'blocs-creator' ); ?></button>
						<button type="button" class="bc-segment" data-bc-vue="disponible"><?php esc_html_e( 'Disponibles', 'blocs-creator' ); ?></button>
						<button type="button" class="bc-segment" data-bc-vue="ecarte"><?php esc_html_e( 'Écartés', 'blocs-creator' ); ?></button>
					</div>
				</div>
			</div>

			<?php
			/*
			 * La liste de ce que cet écran montrait part en UN champ. Le second,
			 * `blocs_ecartes`, est composé au moment de l'envoi par le script :
			 * les cases, elles, sont alors mises hors circuit. Voir l'en-tête.
			 */
			?>
			<input type="hidden" name="<?php echo esc_attr( $bc_option ); ?>[blocs_connus]"
				value="<?php echo esc_attr( implode( ',', $bc_connus ) ); ?>">

			<?php foreach ( $bc_inventaire as $bc_espace => $bc_groupe ) : ?>
				<?php
				$bc_ecartes_groupe = 0;

				foreach ( $bc_groupe['blocs'] as $bc_bloc_compte ) {
					if ( $bc_bloc_compte['ecarte'] && ! $bc_bloc_compte['protege'] ) {
						++$bc_ecartes_groupe;
					}
				}

				$bc_id_groupe = 'bc-groupe-' . sanitize_key( $bc_espace );
				?>
				<div class="bc-groupe-blocs" data-bc-groupe="<?php echo esc_attr( $bc_espace ); ?>">

					<h2 class="bc-groupe-blocs__titre">
						<button type="button" class="bc-groupe-blocs__plier" data-bc-plier
							aria-expanded="true" aria-controls="<?php echo esc_attr( $bc_id_groupe ); ?>">
							<span class="bc-groupe-blocs__chevron dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
							<span class="bc-groupe-blocs__nom"><?php echo esc_html( $bc_groupe['titre'] ); ?></span>
						</button>

						<span class="bc-groupe-blocs__compte" data-bc-compte>
							<?php
							printf(
								/* translators: 1: nombre de blocs disponibles, 2: nombre total. */
								esc_html__( '%1$s sur %2$s', 'blocs-creator' ),
								esc_html( number_format_i18n( count( $bc_groupe['blocs'] ) - $bc_ecartes_groupe ) ),
								esc_html( number_format_i18n( count( $bc_groupe['blocs'] ) ) )
							);
							?>
						</span>

						<span class="bc-groupe-blocs__actions">
							<button type="button" class="button-link" data-bc-tout="1"><?php esc_html_e( 'Tout cocher', 'blocs-creator' ); ?></button>
							<button type="button" class="button-link" data-bc-tout="0"><?php esc_html_e( 'Tout décocher', 'blocs-creator' ); ?></button>
						</span>
					</h2>

					<ul class="bc-blocs" id="<?php echo esc_attr( $bc_id_groupe ); ?>">
						<?php foreach ( $bc_groupe['blocs'] as $bc_bloc ) : ?>
							<li class="bc-blocs__item<?php echo $bc_bloc['protege'] ? ' est-protege' : ''; ?>"
								data-bc-cherche="<?php echo esc_attr( strtolower( $bc_bloc['titre'] . ' ' . $bc_bloc['nom'] ) ); ?>"
								data-bc-etat="<?php echo esc_attr( $bc_bloc['ecarte'] && ! $bc_bloc['protege'] ? 'ecarte' : 'disponible' ); ?>">

								<label>
									<input type="checkbox"
										name="<?php echo esc_attr( $bc_option ); ?>[blocs_actifs][]"
										value="<?php echo esc_attr( $bc_bloc['nom'] ); ?>"
										<?php checked( ! $bc_bloc['ecarte'] || $bc_bloc['protege'] ); ?>
										<?php disabled( $bc_bloc['protege'] ); ?>>

									<?php if ( $bc_bloc['protege'] ) : ?>
										<input type="hidden" name="<?php echo esc_attr( $bc_option ); ?>[blocs_actifs][]"
											value="<?php echo esc_attr( $bc_bloc['nom'] ); ?>">
									<?php endif; ?>

									<span class="bc-blocs__nom">
										<span class="bc-blocs__titre"><?php echo esc_html( $bc_bloc['titre'] ); ?></span>
										<code class="bc-code"><?php echo esc_html( $bc_bloc['nom'] ); ?></code>
									</span>

									<span class="bc-blocs__meta">
										<?php if ( $bc_bloc['protege'] ) : ?>
											<span class="bc-blocs__protege"><?php esc_html_e( 'ne se retire pas', 'blocs-creator' ); ?></span>
										<?php elseif ( $bc_bloc['usage'] > 0 ) : ?>
											<span class="bc-blocs__usage">
												<?php
												printf(
													esc_html(
														/* translators: %d: nombre de publications. */
														_n( 'posé dans %d publication', 'posé dans %d publications', $bc_bloc['usage'], 'blocs-creator' )
													),
													(int) $bc_bloc['usage']
												);
												?>
											</span>
										<?php endif; ?>
									</span>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>

					<p class="bc-groupe-blocs__vide" hidden>
						<?php esc_html_e( 'Aucun bloc ne correspond ici.', 'blocs-creator' ); ?>
					</p>
				</div>
			<?php endforeach; ?>

			<p class="description">
				<?php esc_html_e( 'Deux familles ne se décochent pas : vos propres blocs, et les blocs qui n\'existent qu\'à l\'intérieur d\'un autre — une carte dans une grille de cartes. Les retirer laisserait un conteneur qu\'on ne pourrait plus remplir.', 'blocs-creator' ); ?>
			</p>
		</section>

		<!-- ---------------------------------------------------------- -->
		<section class="bc-onglet" data-bc-panneau="animations" <?php echo 'animations' === $bc_onglet ? '' : 'hidden'; ?>>

			<p class="bc-chapo">
				<?php esc_html_e( 'Une apparition appartient au bloc, pas à la page : elle se choisit une fois, et toutes ses occurrences entrent de la même façon partout sur le site. Pour les blocs que vous avez créés, elle se règle sur leur propre écran ; pour ceux de WordPress et des autres extensions, c\'est ici.', 'blocs-creator' ); ?>
			</p>

			<?php
			$bc_reglables  = BC_Animations::blocs_reglables();
			$bc_apparitions = array();

			foreach ( BC_Animations::carte() as $bc_bloc_nom => $bc_reglage ) {
				if ( isset( $bc_reglables[ $bc_bloc_nom ] ) ) {
					$bc_apparitions[ $bc_bloc_nom ] = $bc_reglage;
				}
			}

			$bc_scenarios = BC_Animations::scenarios();
			$bc_opt_anim  = BC_Animations::OPTION;
			?>

			<h2 class="bc-groupe-blocs__titre"><?php esc_html_e( 'Les blocs qui entrent en scène', 'blocs-creator' ); ?></h2>

			<?php if ( empty( $bc_apparitions ) ) : ?>
				<p class="description">
					<?php esc_html_e( 'Aucun pour l\'instant. Choisissez un bloc ci-dessous pour lui en donner une.', 'blocs-creator' ); ?>
				</p>
			<?php else : ?>
				<table class="widefat striped bc-table-apparitions">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Bloc', 'blocs-creator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Apparition', 'blocs-creator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Durée', 'blocs-creator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $bc_apparitions as $bc_bloc_nom => $bc_reglage ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $bc_reglables[ $bc_bloc_nom ] ); ?></strong>
									<div><code class="bc-code"><?php echo esc_html( $bc_bloc_nom ); ?></code></div>
								</td>
								<td>
									<label class="screen-reader-text" for="bc-anim-<?php echo esc_attr( sanitize_key( str_replace( '/', '-', $bc_bloc_nom ) ) ); ?>">
										<?php esc_html_e( 'Apparition', 'blocs-creator' ); ?>
									</label>
									<select id="bc-anim-<?php echo esc_attr( sanitize_key( str_replace( '/', '-', $bc_bloc_nom ) ) ); ?>"
										name="<?php echo esc_attr( $bc_opt_anim ); ?>[<?php echo esc_attr( $bc_bloc_nom ); ?>][nom]">
										<option value=""><?php esc_html_e( '— la retirer —', 'blocs-creator' ); ?></option>
										<?php foreach ( $bc_scenarios as $bc_valeur => $bc_scenario ) : ?>
											<option value="<?php echo esc_attr( $bc_valeur ); ?>" <?php selected( $bc_reglage['nom'], $bc_valeur ); ?>>
												<?php echo esc_html( $bc_scenario['libelle'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<input type="number" class="small-text" min="200" max="3000" step="50"
										name="<?php echo esc_attr( $bc_opt_anim ); ?>[<?php echo esc_attr( $bc_bloc_nom ); ?>][duree]"
										value="<?php echo esc_attr( (string) ( $bc_reglage['duree'] > 0 ? $bc_reglage['duree'] : 720 ) ); ?>">
									<span class="bc-vide">ms</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2 class="bc-groupe-blocs__titre"><?php esc_html_e( 'En animer un de plus', 'blocs-creator' ); ?></h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="bc-anim-ajout-bloc"><?php esc_html_e( 'Le bloc', 'blocs-creator' ); ?></label>
					</th>
					<td>
						<select id="bc-anim-ajout-bloc" class="regular-text"
							name="<?php echo esc_attr( $bc_opt_anim ); ?>[__ajout][bloc]">
							<option value=""><?php esc_html_e( '— choisir un bloc —', 'blocs-creator' ); ?></option>
							<?php foreach ( $bc_reglables as $bc_bloc_nom => $bc_bloc_titre ) : ?>
								<?php if ( isset( $bc_apparitions[ $bc_bloc_nom ] ) ) : ?>
									<?php continue; ?>
								<?php endif; ?>
								<option value="<?php echo esc_attr( $bc_bloc_nom ); ?>">
									<?php echo esc_html( $bc_bloc_titre . ' — ' . $bc_bloc_nom ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Les blocs que vous avez créés ne sont pas dans cette liste : leur apparition se règle sur leur propre écran, avec le même aperçu.', 'blocs-creator' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="bc-anim-ajout-nom"><?php esc_html_e( 'Son apparition', 'blocs-creator' ); ?></label>
					</th>
					<td>
						<?php BC_Animations::champ( 'bc-anim-ajout-nom', $bc_opt_anim . '[__ajout][nom]', '' ); ?>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="bc-anim-ajout-duree"><?php esc_html_e( 'Durée', 'blocs-creator' ); ?></label>
					</th>
					<td>
						<input type="number" id="bc-anim-ajout-duree" class="small-text" min="200" max="3000" step="50"
							name="<?php echo esc_attr( $bc_opt_anim ); ?>[__ajout][duree]" value="720">
						<span class="bc-vide">ms</span>
						<p class="description">
							<?php esc_html_e( '720 ms est la durée commune à tout le site : la garder, c\'est faire entrer les blocs d\'un même mouvement.', 'blocs-creator' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h2 class="bc-groupe-blocs__titre"><?php esc_html_e( 'Les scènes', 'blocs-creator' ); ?></h2>

			<ul class="bc-variantes">
				<?php foreach ( $bc_scenarios as $bc_valeur => $bc_scenario ) : ?>
					<li>
						<span class="bc-variantes__nom"><?php echo esc_html( $bc_scenario['libelle'] ); ?></span>
						<span class="bc-variantes__quoi"><?php echo esc_html( $bc_scenario['description'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>

			<p class="description">
				<?php
				printf(
					/* translators: 1: nom du filtre, 2: sélecteur CSS. */
					esc_html__( 'Un thème peut en ajouter une : le filtre %1$s la déclare, et des règles CSS sur %2$s la dessinent. Un gabarit qui pose lui-même %3$s sur ses éléments décide de ce qui entre, et dans quel ordre. Rien ne s\'anime pour qui a demandé moins d\'animations dans les réglages de son appareil, et rien n\'est jamais caché si le JavaScript ne se charge pas.', 'blocs-creator' ),
					'<code>blocs_creator_scenarios_animation</code>',
					'<code>[data-bc-anim="…"]</code>',
					'<code>data-bc-part</code>'
				);
				?>
			</p>
		</section>

		<div class="bc-barre-envoi">
			<?php submit_button( __( 'Enregistrer les réglages', 'blocs-creator' ), 'primary', 'submit', false ); ?>
			<span class="bc-barre-envoi__note"><?php esc_html_e( 'Un seul bouton pour les trois onglets.', 'blocs-creator' ); ?></span>

			<?php
			/*
			 * Le diagnostic vit au bas d'une page très longue. Ce lien-ci est
			 * dans la barre collante, donc toujours sous les yeux — et c'est
			 * précisément quand le bouton d'à côté a l'air de ne rien faire
			 * qu'on le cherche.
			 */
			?>
			<button type="button" class="button-link bc-barre-envoi__aide" data-bc-ouvrir="#bc-diagnostic">
				<?php esc_html_e( 'L\'enregistrement ne passe pas ?', 'blocs-creator' ); ?>
			</button>
		</div>
		<div class="bc-diagnostic" id="bc-diagnostic">
			<?php
			/*
			 * Ouvert d'office quand l'enregistrement vient d'échouer : c'est le
			 * seul moment où l'on a vraiment besoin de le lire, et aller le
			 * chercher au bas d'une page de cent trente blocs n'arrive jamais.
			 */
			$bc_echec = isset( $_GET['bc_message'] ) && 'erreur' === $_GET['bc_message'];
			?>
			<details class="bc-details" <?php echo $bc_echec ? 'open' : ''; ?>>
				<summary><?php esc_html_e( 'L\'enregistrement ne passe pas ? Ouvrez ceci.', 'blocs-creator' ); ?></summary>

				<p class="bc-aide">
					<?php esc_html_e( 'Un écran qui revient inchangé peut avoir quatre causes très différentes — une requête tronquée, une extension qui intercepte l\'écriture, un cache qui ressert l\'ancienne valeur, une base en lecture seule — et elles se ressemblent toutes vues d\'ici. Ce relevé les distingue. Copiez-le et donnez-le à qui vous aide.', 'blocs-creator' ); ?>
				</p>

				<p>
					<button type="button" class="button" data-bc-copier="#bc-releve">
						<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
						<?php esc_html_e( 'Copier le diagnostic', 'blocs-creator' ); ?>
					</button>
					<span class="bc-copie-faite" data-bc-copie-faite hidden><?php esc_html_e( 'Copié.', 'blocs-creator' ); ?></span>
				</p>

				<textarea id="bc-releve" class="bc-code-depart" readonly rows="18" onclick="this.select()"><?php
					echo esc_textarea( BC_Diagnostic::releve() );
				?></textarea>

				<p class="bc-aide">
					<?php esc_html_e( 'Le point à regarder en premier : « Les dernières tentatives d\'enregistrement ». Si elle reste vide alors que vous venez d\'appuyer sur le bouton, la requête n\'arrive pas jusqu\'au plugin — et c\'est du côté de l\'hébergement qu\'il faut chercher, pas ici.', 'blocs-creator' ); ?>
				</p>
			</details>
		</div>

	</form>

</div>
