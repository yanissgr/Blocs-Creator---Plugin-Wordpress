<?php
/**
 * Module « Bandeau cookies » : ce qui améliore la visite attend l'accord du
 * visiteur.
 *
 * Deux traceurs sont soumis à consentement, présentés ensemble au visiteur
 * comme « ce qui améliore votre visite » :
 *
 *   - la mesure d'audience de Google Analytics (ajoutée par Site Kit, ou par
 *     toute extension qui écrit gtag.js ou analytics.js) ;
 *   - les brouillons des formulaires, si ce module-là est actif aussi.
 *
 * Le principe :
 *
 *   - tant que le visiteur n'a pas accepté, les balises de Google Analytics
 *     sont écrites en `type="text/plain"` : le navigateur ne les exécute pas,
 *     rien ne part chez Google et aucun cookie `_ga` n'est déposé ;
 *   - un petit bandeau, en bas à gauche, propose « Accepter », mis en avant,
 *     et « Refuser », plus discret mais sur la même ligne et de même taille
 *     (la CNIL le demande). Il ne bloque rien : on peut lire sans répondre ;
 *   - le choix est gardé six mois dans le cookie `bc_cookies`. S'il accepte,
 *     le script réveille les balises sur place.
 *
 * Tout se passe dans le navigateur : la page servie est la même pour tout le
 * monde, et reste compatible avec un cache de pages.
 *
 * Les textes se modifient dans Réglages › Cookies. Un lien vers
 * `#gerer-les-cookies` (dans un menu, un texte, un bouton) rouvre le bandeau ;
 * un bouton `data-cookies-accepter`, où qu'il soit, accepte.
 *
 * Pour les autres scripts : `window.blocsCreatorCookies.choix()` rend le
 * choix ('accepte', 'refuse' ou ''), et l'évènement `blocs-creator:cookies`
 * (sur `document`, `detail.choix`) signale chaque nouveau choix.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * L'ancre qui rouvre le bandeau.
 */
const BLOCS_CREATOR_COOKIES_ANCRE = 'gerer-les-cookies';

/**
 * L'option des réglages.
 */
const BLOCS_CREATOR_COOKIES_OPTION = 'blocs_creator_cookies';

/**
 * Les réglages par défaut du bandeau.
 *
 * @return array{actif: bool, titre: string, texte: string, accepter: string, refuser: string, lien: string, page: int, version: int}
 */
function blocs_creator_cookies_defauts() {
	$texte = Blocs_Creator_Modules::actif( 'brouillons' )
		? __( 'Quelques cookies nous aident à rendre votre visite plus agréable : garder vos réponses aux formulaires si la page se ferme, et voir quelles pages vous sont utiles. Si vous refusez, rien de tout cela.', 'blocs-creator' )
		: __( 'Avec votre accord, quelques cookies nous aident à voir quelles pages vous sont utiles. Rien n’est mesuré si vous refusez.', 'blocs-creator' );

	return array(
		'actif'    => true,
		'titre'    => __( 'Un petit cookie ?', 'blocs-creator' ),
		'texte'    => $texte,
		'accepter' => __( 'Accepter', 'blocs-creator' ),
		'refuser'  => __( 'Refuser', 'blocs-creator' ),
		'lien'     => __( 'En savoir plus', 'blocs-creator' ),
		'page'     => 0,
		'version'  => 1,
	);
}

/**
 * Les réglages du bandeau, complétés par les valeurs par défaut.
 *
 * @return array
 */
function blocs_creator_cookies_reglages() {
	$reglages = wp_parse_args( (array) get_option( BLOCS_CREATOR_COOKIES_OPTION, array() ), blocs_creator_cookies_defauts() );

	// Sans page choisie, la page « Cookies » si elle existe.
	if ( ! $reglages['page'] ) {
		$page             = get_page_by_path( 'cookies' );
		$reglages['page'] = $page ? (int) $page->ID : 0;
	}

	return $reglages;
}

/**
 * Le bandeau est-il en service ? S'il ne l'est pas, Google Analytics se
 * charge comme avant, sans rien demander.
 *
 * @return bool
 */
function blocs_creator_cookies_actif() {
	return (bool) blocs_creator_cookies_reglages()['actif'];
}

/**
 * Les balises permises dans le texte du bandeau.
 *
 * @return array
 */
function blocs_creator_cookies_balises() {
	return array(
		'em'     => array(),
		'strong' => array(),
		'br'     => array(),
		'a'      => array(
			'href'   => true,
			'target' => true,
			'rel'    => true,
		),
	);
}

/* ------------------------------------------------------------------ *
 * 1. Mettre Google Analytics en attente
 * ------------------------------------------------------------------ */

/**
 * Cette balise appartient-elle à Google Analytics ?
 *
 * Site Kit enregistre gtag.js sous `google_gtagjs` (et `google_gtagjs-<ID>`
 * avec la passerelle Google) : ses balises portent des identifiants qui en
 * découlent. Les réglages par défaut du « mode consentement » de Site Kit
 * (`…-consent-mode-data-layer`) ne mesurent rien : ils restent actifs, pour
 * que Google connaisse le refus dès le départ. Par précaution, toute balise
 * gtag.js ou analytics.js ajoutée par une autre extension est aussi retenue.
 *
 * @param array $attributs Attributs de la balise.
 * @return bool
 */
function blocs_creator_cookies_est_statistique( $attributs ) {
	$id  = (string) ( $attributs['id'] ?? '' );
	$src = (string) ( $attributs['src'] ?? '' );

	if ( str_starts_with( $id, 'google_gtagjs' ) && ! str_contains( $id, 'consent-mode' ) ) {
		return true;
	}

	$est = (bool) preg_match( '#//(www\.)?(googletagmanager\.com/gtag/js|google-analytics\.com/(analytics|ga)\.js)#', $src );

	/**
	 * Filtre les balises retenues jusqu'à l'accord du visiteur.
	 *
	 * @param bool  $est       La balise est-elle un traceur soumis à accord.
	 * @param array $attributs Ses attributs.
	 */
	return (bool) apply_filters( 'blocs_creator_cookies_balise_retenue', $est, $attributs );
}

/**
 * Écrit une balise de statistiques en attente : `type="text/plain"`, que le
 * navigateur n'exécute pas et dont il ne télécharge pas la source.
 *
 * @param array $attributs Attributs de la balise.
 * @return array
 */
function blocs_creator_cookies_retenir( $attributs ) {
	if ( is_admin() || ! blocs_creator_cookies_actif() || ! blocs_creator_cookies_est_statistique( $attributs ) ) {
		return $attributs;
	}

	$attributs['type']            = 'text/plain';
	$attributs['data-bc-cookies'] = 'statistiques';

	return $attributs;
}
add_filter( 'wp_script_attributes', 'blocs_creator_cookies_retenir', 99 );
add_filter( 'wp_inline_script_attributes', 'blocs_creator_cookies_retenir', 99 );

/**
 * Les cookies de Google Analytics durent 13 mois au plus, comme le demande la
 * CNIL (deux ans par défaut chez Google).
 *
 * @param array $options Options de la commande `gtag('config')`.
 * @return array
 */
function blocs_creator_cookies_duree_ga( $options ) {
	$options['cookie_expires'] = 13 * 30 * DAY_IN_SECONDS;

	return $options;
}
add_filter( 'googlesitekit_gtag_opt', 'blocs_creator_cookies_duree_ga' );

/* ------------------------------------------------------------------ *
 * 2. Le bandeau
 * ------------------------------------------------------------------ */

/**
 * Feuille et script du bandeau.
 */
function blocs_creator_cookies_assets() {
	if ( ! blocs_creator_cookies_actif() ) {
		return;
	}

	wp_enqueue_style(
		'blocs-creator-cookies',
		Blocs_Creator_Modules::url( 'cookies', 'cookies.css' ),
		array(),
		Blocs_Creator_Modules::version( 'cookies', 'cookies.css' )
	);

	wp_enqueue_script(
		'blocs-creator-cookies',
		Blocs_Creator_Modules::url( 'cookies', 'cookies.js' ),
		array(),
		Blocs_Creator_Modules::version( 'cookies', 'cookies.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'blocs_creator_cookies_assets' );

/**
 * Écrit le bandeau, caché : le script le montre si le visiteur n'a pas
 * encore choisi. Sans JavaScript, il reste caché — et Google Analytics, qui
 * en a besoin, ne se charge pas non plus.
 */
function blocs_creator_cookies_bandeau() {
	if ( ! blocs_creator_cookies_actif() || is_embed() ) {
		return;
	}

	$reglages = blocs_creator_cookies_reglages();
	$page     = $reglages['page'] ? get_permalink( $reglages['page'] ) : '';
	?>
	<section id="bc-cookies" class="bc-cookies" aria-labelledby="bc-cookies-titre" data-version="<?php echo (int) $reglages['version']; ?>" tabindex="-1" hidden>
		<div class="bc-cookies__tete">
			<span class="bc-cookies__picto" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M21 12.5A9 9 0 1 1 11.5 3a3 3 0 0 0 3.5 3.5 3 3 0 0 0 3.5 3.5 3 3 0 0 0 2.5 2.5Z"/><path d="M8.5 9.5h.01M15.5 15h.01M10 15.5h.01M12 12h.01"/></svg></span>
			<p class="bc-cookies__titre" id="bc-cookies-titre"><?php echo esc_html( $reglages['titre'] ); ?></p>
		</div>

		<p class="bc-cookies__texte"><?php echo wp_kses( $reglages['texte'], blocs_creator_cookies_balises() ); ?></p>

		<p class="bc-cookies__etat" data-cookies-etat hidden
			data-accepte="<?php esc_attr_e( 'Votre choix actuel : cookies acceptés.', 'blocs-creator' ); ?>"
			data-refuse="<?php esc_attr_e( 'Votre choix actuel : cookies refusés.', 'blocs-creator' ); ?>"></p>

		<div class="bc-cookies__pied">
			<?php if ( $page && '' !== $reglages['lien'] ) : ?>
				<a class="bc-cookies__lien" href="<?php echo esc_url( $page ); ?>"><?php echo esc_html( $reglages['lien'] ); ?></a>
			<?php endif; ?>

			<div class="bc-cookies__actions">
				<button type="button" class="bc-cookies__bouton is-discret" data-cookies-choix="refuse"><?php echo esc_html( $reglages['refuser'] ); ?></button>
				<button type="button" class="bc-cookies__bouton is-principal" data-cookies-choix="accepte"><?php echo esc_html( $reglages['accepter'] ); ?></button>
			</div>
		</div>
	</section>
	<?php
}
add_action( 'wp_footer', 'blocs_creator_cookies_bandeau', 5 );

/* ------------------------------------------------------------------ *
 * 3. Réglages › Cookies
 * ------------------------------------------------------------------ */

/**
 * Enregistre l'écran.
 */
function blocs_creator_cookies_menu() {
	add_options_page(
		__( 'Bandeau cookies', 'blocs-creator' ),
		__( 'Cookies', 'blocs-creator' ),
		'manage_options',
		'blocs-creator-cookies',
		'blocs_creator_cookies_ecran'
	);
}
add_action( 'admin_menu', 'blocs_creator_cookies_menu' );

/**
 * Déclare l'option.
 */
function blocs_creator_cookies_declarer() {
	register_setting(
		'blocs_creator_cookies',
		BLOCS_CREATOR_COOKIES_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'blocs_creator_cookies_nettoyer',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'blocs_creator_cookies_declarer' );

/**
 * Nettoie les réglages envoyés par l'écran.
 *
 * @param mixed $valeurs Valeurs du formulaire.
 * @return array
 */
function blocs_creator_cookies_nettoyer( $valeurs ) {
	$valeurs  = is_array( $valeurs ) ? $valeurs : array();
	$defauts  = blocs_creator_cookies_defauts();
	$actuels  = wp_parse_args( (array) get_option( BLOCS_CREATOR_COOKIES_OPTION, array() ), $defauts );
	$nettoyes = array(
		'actif'   => ! empty( $valeurs['actif'] ),
		'texte'   => trim( wp_kses( (string) ( $valeurs['texte'] ?? '' ), blocs_creator_cookies_balises() ) ),
		'page'    => absint( $valeurs['page'] ?? 0 ),
		'version' => (int) $actuels['version'] + ( empty( $valeurs['redemander'] ) ? 0 : 1 ),
	);

	foreach ( array( 'titre', 'accepter', 'refuser', 'lien' ) as $cle ) {
		$nettoyes[ $cle ] = sanitize_text_field( (string) ( $valeurs[ $cle ] ?? '' ) );
	}

	// Un bouton sans libellé serait invisible : on reprend celui d'origine.
	foreach ( array( 'accepter', 'refuser', 'texte' ) as $cle ) {
		if ( '' === $nettoyes[ $cle ] ) {
			$nettoyes[ $cle ] = $defauts[ $cle ];
		}
	}

	return $nettoyes;
}

/**
 * L'écran Réglages › Cookies.
 */
function blocs_creator_cookies_ecran() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$reglages = blocs_creator_cookies_reglages();
	$nom      = static fn( $cle ) => BLOCS_CREATOR_COOKIES_OPTION . '[' . $cle . ']';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Bandeau cookies', 'blocs-creator' ); ?></h1>
		<p style="max-width:62em">
			<?php esc_html_e( 'Le bandeau s\'affiche en bas à gauche du site tant que le visiteur n\'a pas choisi. Ce qui améliore la visite n\'est utilisé qu\'après « Accepter » : les statistiques de Google Analytics et, s\'ils sont actifs, les brouillons des formulaires. Le choix est gardé 6 mois.', 'blocs-creator' ); ?>
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'blocs_creator_cookies' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Bandeau', 'blocs-creator' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $nom( 'actif' ) ); ?>" value="1" <?php checked( $reglages['actif'] ); ?>>
							<?php esc_html_e( 'Afficher le bandeau et attendre l\'accord du visiteur', 'blocs-creator' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Décoché : plus de bandeau ; Google Analytics se charge pour tout le monde et les formulaires gardent les réponses, sans rien demander (non conforme au RGPD).', 'blocs-creator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bc-cookies-titre-reglage"><?php esc_html_e( 'Titre', 'blocs-creator' ); ?></label></th>
					<td><input type="text" class="regular-text" id="bc-cookies-titre-reglage" name="<?php echo esc_attr( $nom( 'titre' ) ); ?>" value="<?php echo esc_attr( $reglages['titre'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="bc-cookies-texte"><?php esc_html_e( 'Texte', 'blocs-creator' ); ?></label></th>
					<td>
						<textarea class="large-text" rows="3" id="bc-cookies-texte" name="<?php echo esc_attr( $nom( 'texte' ) ); ?>"><?php echo esc_textarea( $reglages['texte'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Une ou deux phrases, qui disent à quoi servent les cookies (la CNIL demande que le visiteur sache ce qu\'il accepte). Balises permises : <strong>, <em>, <a href="…">.', 'blocs-creator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bc-cookies-accepter"><?php esc_html_e( 'Bouton « Accepter »', 'blocs-creator' ); ?></label></th>
					<td><input type="text" class="regular-text" id="bc-cookies-accepter" name="<?php echo esc_attr( $nom( 'accepter' ) ); ?>" value="<?php echo esc_attr( $reglages['accepter'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="bc-cookies-refuser"><?php esc_html_e( 'Bouton « Refuser »', 'blocs-creator' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="bc-cookies-refuser" name="<?php echo esc_attr( $nom( 'refuser' ) ); ?>" value="<?php echo esc_attr( $reglages['refuser'] ); ?>">
						<p class="description"><?php esc_html_e( '« Accepter » est mis en avant, « Refuser » est plus discret. Refuser doit rester visible juste à côté et aussi simple qu\'accepter (exigence de la CNIL) : ne le cachez pas derrière un autre écran.', 'blocs-creator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bc-cookies-page"><?php esc_html_e( 'Page « En savoir plus »', 'blocs-creator' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => $nom( 'page' ), // phpcs:ignore WordPress.Security.EscapeOutput -- échappé par wp_dropdown_pages().
								'id'                => 'bc-cookies-page',
								'selected'          => (int) $reglages['page'],
								'show_option_none'  => esc_html__( '— Aucune —', 'blocs-creator' ),
								'option_none_value' => '0',
							)
						);
						?>
						<input type="text" class="regular-text" aria-label="<?php esc_attr_e( 'Libellé du lien', 'blocs-creator' ); ?>" name="<?php echo esc_attr( $nom( 'lien' ) ); ?>" value="<?php echo esc_attr( $reglages['lien'] ); ?>" style="margin-top:6px">
						<p class="description">
							<?php esc_html_e( 'La page qui détaille les cookies, et le libellé du lien qui y mène. Libellé vide : lien masqué.', 'blocs-creator' ); ?>
							<?php if ( $reglages['page'] && get_edit_post_link( $reglages['page'] ) ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $reglages['page'] ) ); ?>"><?php esc_html_e( 'Modifier cette page', 'blocs-creator' ); ?></a>
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Rouvrir le bandeau', 'blocs-creator' ); ?></th>
					<td>
						<code>#<?php echo esc_html( BLOCS_CREATOR_COOKIES_ANCRE ); ?></code>
						<p class="description"><?php esc_html_e( 'Un lien vers cette adresse — dans un menu, un texte ou un bouton — rouvre le bandeau pour changer d\'avis. Mettez-en un sur la page des cookies.', 'blocs-creator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Redemander', 'blocs-creator' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $nom( 'redemander' ) ); ?>" value="1">
							<?php esc_html_e( 'Reposer la question à tous les visiteurs, même à ceux qui ont déjà choisi', 'blocs-creator' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'À cocher une fois, si le site se met à utiliser d\'autres cookies soumis à accord.', 'blocs-creator' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Signale l'écran depuis Réglages › Confidentialité, là où l'on cherche
 * naturellement.
 */
function blocs_creator_cookies_avis_confidentialite() {
	$ecran = get_current_screen();

	if ( ! $ecran || 'options-privacy' !== $ecran->id || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-info"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
		esc_html__( 'Le bandeau cookies se règle à part :', 'blocs-creator' ),
		esc_url( admin_url( 'options-general.php?page=blocs-creator-cookies' ) ),
		esc_html__( 'Réglages › Cookies', 'blocs-creator' )
	);
}
add_action( 'admin_notices', 'blocs_creator_cookies_avis_confidentialite' );
