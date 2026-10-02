<?php
/**
 * Module « Mode maintenance » : fermer le site aux visiteurs le temps d'un
 * chantier.
 *
 * Quand elle est activée :
 *
 *   - les visiteurs voient une page d'attente, servie avec le code HTTP 503
 *     (« indisponible pour un moment ») et un `Retry-After` : Google repasse
 *     plus tard, sans rien retirer de son index ;
 *   - les personnes connectées qui peuvent modifier le contenu voient le site
 *     normalement, pour travailler ; la barre d'outils leur rappelle que le
 *     site est fermé ;
 *   - la connexion, l'administration, l'API REST et les tâches planifiées ne
 *     passent pas par ici : elles marchent comme d'habitude.
 *
 * Pour l'activer : « Maintenance » dans la barre d'outils (sur le site comme
 * dans l'administration), ou Réglages › Maintenance, où se règlent aussi les
 * textes. `?apercu-maintenance` montre la page aux personnes connectées.
 *
 * La page est autonome : ni en-tête ni menus (ils mèneraient à des pages
 * fermées), rien des extensions — les styles globaux du thème (theme.json) et
 * une feuille à elle. Un thème qui veut la dessiner lui-même pose un fichier
 * `blocs-creator/maintenance.php` (il reçoit $reglages, $retour, $apercu), ou
 * ajoute sa feuille par le filtre `blocs_creator_maintenance_feuilles`.
 * Elle n'a pas de lien de connexion : l'adresse de connexion reste secrète.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Le paramètre d'adresse qui montre la page en aperçu.
 */
const BLOCS_CREATOR_MAINTENANCE_APERCU = 'apercu-maintenance';

/**
 * L'option des réglages.
 */
const BLOCS_CREATOR_MAINTENANCE_OPTION = 'blocs_creator_maintenance';

/**
 * Les réglages par défaut. Titre et message acceptent le balisage léger :
 * **mot** en gras, _mot_ en italique (blocs_creator_balisage_leger()).
 *
 * @return array{actif: bool, titre: string, texte: string, retour: string}
 */
function blocs_creator_maintenance_defauts() {
	return array(
		'actif'  => false,
		'titre'  => __( 'Le site fait **peau neuve**.', 'blocs-creator' ),
		'texte'  => __( 'Nous faisons quelques travaux. Le site revient très vite : merci de votre patience !', 'blocs-creator' ),
		'retour' => '',
	);
}

/**
 * Les réglages, complétés par les valeurs par défaut.
 *
 * @return array
 */
function blocs_creator_maintenance_reglages() {
	return wp_parse_args( (array) get_option( BLOCS_CREATOR_MAINTENANCE_OPTION, array() ), blocs_creator_maintenance_defauts() );
}

/**
 * La maintenance est-elle activée ?
 *
 * @return bool
 */
function blocs_creator_maintenance_active() {
	return (bool) blocs_creator_maintenance_reglages()['actif'];
}

/**
 * Cette personne voit-elle le site malgré la maintenance ?
 *
 * @return bool
 */
function blocs_creator_maintenance_peut_passer() {
	/**
	 * Filtre qui voit le site pendant la maintenance.
	 *
	 * @param bool $passe Par défaut : qui peut modifier des publications.
	 */
	return (bool) apply_filters( 'blocs_creator_maintenance_peut_passer', current_user_can( 'edit_posts' ) );
}

/**
 * Les balises permises dans le titre et le message.
 *
 * @return array
 */
function blocs_creator_maintenance_balises() {
	return array(
		'em'     => array(),
		'strong' => array(),
		'br'     => array(),
		'span'   => array( 'class' => true ),
		'a'      => array(
			'href'   => true,
			'target' => true,
			'rel'    => true,
		),
	);
}

/**
 * Le retour prévu, s'il est encore à venir.
 *
 * @return int|null Horodatage, ou null.
 */
function blocs_creator_maintenance_retour() {
	$retour = (string) blocs_creator_maintenance_reglages()['retour'];
	$date   = '' === $retour ? false : date_create_immutable_from_format( 'Y-m-d\TH:i', $retour, wp_timezone() );

	if ( ! $date || $date->getTimestamp() <= time() ) {
		return null;
	}

	return $date->getTimestamp();
}

/**
 * Le retour prévu, en toutes lettres : « aujourd'hui, vers 18 h », « demain,
 * vers 9 h 30 », « le mercredi 1er octobre, vers 14 h ».
 *
 * @param int $horodatage Horodatage du retour.
 * @return string
 */
function blocs_creator_maintenance_retour_texte( $horodatage ) {
	$minutes = wp_date( 'i', $horodatage );
	$heure   = wp_date( 'G', $horodatage ) . ' h' . ( '00' === $minutes ? '' : ' ' . $minutes );
	$jour    = wp_date( 'Y-m-d', $horodatage );

	if ( wp_date( 'Y-m-d' ) === $jour ) {
		$quand = __( 'aujourd\'hui', 'blocs-creator' );
	} elseif ( wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) === $jour ) {
		$quand = __( 'demain', 'blocs-creator' );
	} else {
		$numero = wp_date( 'j', $horodatage );
		$quand  = sprintf(
			/* translators: 1: jour de la semaine, 2: numéro du jour, 3: mois. */
			__( 'le %1$s %2$s %3$s', 'blocs-creator' ),
			wp_date( 'l', $horodatage ),
			'1' === $numero ? '1er' : $numero,
			wp_date( 'F', $horodatage )
		);

		if ( wp_date( 'Y' ) !== wp_date( 'Y', $horodatage ) ) {
			$quand .= ' ' . wp_date( 'Y', $horodatage );
		}
	}

	/* translators: 1: jour (aujourd'hui, demain, le mardi 7 octobre), 2: heure. */
	return sprintf( __( 'Retour prévu %1$s, vers %2$s.', 'blocs-creator' ), $quand, $heure );
}

/* ------------------------------------------------------------------ *
 * 1. Fermer le site aux visiteurs
 * ------------------------------------------------------------------ */

/**
 * Montre la page de maintenance à qui n'a pas à voir le site.
 *
 * Avant tout le reste de `template_redirect`, et notamment avant les
 * formulaires qui s'y traitent : un visiteur n'envoie rien pendant le
 * chantier. robots.txt reste servi, pour que les moteurs sachent quoi faire.
 */
function blocs_creator_maintenance_filtrer() {
	$peut_passer = blocs_creator_maintenance_peut_passer();

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple affichage.
	if ( $peut_passer && isset( $_GET[ BLOCS_CREATOR_MAINTENANCE_APERCU ] ) ) {
		blocs_creator_maintenance_page( true );
	}

	if ( $peut_passer || ! blocs_creator_maintenance_active() || is_robots() || is_favicon() ) {
		return;
	}

	blocs_creator_maintenance_page();
}
add_action( 'template_redirect', 'blocs_creator_maintenance_filtrer', -10 );

/**
 * Écrit la page de maintenance, puis s'arrête.
 *
 * @param bool $apercu Aperçu pour une personne connectée : code 200 et un
 *                     bandeau qui le dit.
 */
function blocs_creator_maintenance_page( $apercu = false ) {
	$reglages = blocs_creator_maintenance_reglages();
	$retour   = blocs_creator_maintenance_retour();

	if ( $apercu ) {
		status_header( 200 );
	} else {
		status_header( 503 );
		// Google repasse à l'heure prévue, sinon dans une heure.
		header( 'Retry-After: ' . ( $retour ? max( MINUTE_IN_SECONDS, $retour - time() ) : HOUR_IN_SECONDS ) );
	}

	nocache_headers();
	header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );

	$gabarit = get_theme_file_path( 'blocs-creator/maintenance.php' );

	if ( is_readable( $gabarit ) ) {
		include $gabarit;
		exit;
	}

	$logo = (int) get_theme_mod( 'custom_logo' );
	$nom  = get_bloginfo( 'name' );

	/**
	 * Filtre les feuilles de style de la page de maintenance.
	 *
	 * Un thème y ajoute la sienne pour lui donner son allure.
	 *
	 * @param string[] $feuilles Adresses des feuilles.
	 */
	$feuilles = (array) apply_filters(
		'blocs_creator_maintenance_feuilles',
		array( add_query_arg( 'ver', Blocs_Creator_Modules::version( 'maintenance', 'maintenance.css' ), Blocs_Creator_Modules::url( 'maintenance', 'maintenance.css' ) ) )
	);
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( sprintf( /* translators: %s: nom du site. */ __( 'Site en maintenance · %s', 'blocs-creator' ), $nom ) ); ?></title>
	<?php
	wp_site_icon();

	if ( function_exists( 'wp_print_font_faces' ) ) {
		wp_print_font_faces();
	}
	?>
	<style id="blocs-creator-maintenance-global"><?php echo wp_get_global_stylesheet(); // phpcs:ignore WordPress.Security.EscapeOutput -- CSS du thème. ?></style>
	<?php foreach ( $feuilles as $feuille ) : ?>
		<link rel="stylesheet" href="<?php echo esc_url( $feuille ); ?>">
	<?php endforeach; ?>
</head>
<body class="bc-maintenance-page">
	<?php if ( $apercu ) : ?>
		<p class="bc-maintenance-apercu">
			<span>
				<strong><?php esc_html_e( 'Aperçu', 'blocs-creator' ); ?></strong> ·
				<?php
				if ( ! empty( $reglages['actif'] ) ) {
					esc_html_e( 'la maintenance est activée : c\'est la page que voient les visiteurs.', 'blocs-creator' );
				} else {
					esc_html_e( 'la maintenance est désactivée : les visiteurs voient le site normalement.', 'blocs-creator' );
				}
				?>
			</span>
			<span class="bc-maintenance-apercu__liens">
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=blocs-creator-maintenance' ) ); ?>"><?php esc_html_e( 'Réglages', 'blocs-creator' ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Retour au site', 'blocs-creator' ); ?></a>
			</span>
		</p>
	<?php endif; ?>

	<main class="bc-maintenance">
		<p class="bc-maintenance__marque">
			<?php
			if ( $logo ) {
				echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput -- échappé par WordPress.
					$logo,
					'medium',
					false,
					array(
						'class'    => 'bc-maintenance__logo',
						'alt'      => '',
						'loading'  => 'eager',
						'decoding' => 'async',
					)
				);
			}
			?>
			<span><?php echo esc_html( $nom ); ?></span>
		</p>

		<p class="bc-maintenance__surtitre"><?php esc_html_e( 'Maintenance en cours', 'blocs-creator' ); ?></p>

		<h1 class="bc-maintenance__titre"><?php echo wp_kses( blocs_creator_balisage_leger( $reglages['titre'] ), blocs_creator_maintenance_balises() ); ?></h1>

		<?php if ( '' !== trim( $reglages['texte'] ) ) : ?>
			<p class="bc-maintenance__message"><?php echo wp_kses( blocs_creator_balisage_leger( $reglages['texte'] ), blocs_creator_maintenance_balises() ); ?></p>
		<?php endif; ?>

		<?php if ( $retour ) : ?>
			<p class="bc-maintenance__retour">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
				<span><?php echo esc_html( blocs_creator_maintenance_retour_texte( $retour ) ); ?></span>
			</p>
		<?php endif; ?>
	</main>
</body>
</html>
	<?php
	exit;
}

/* ------------------------------------------------------------------ *
 * 2. L'interrupteur, dans la barre d'outils
 * ------------------------------------------------------------------ */

/**
 * Le lien qui active (ou désactive) la maintenance en un clic.
 *
 * @param bool $activer Activer, ou désactiver.
 * @return string
 */
function blocs_creator_maintenance_lien_bascule( $activer ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'blocs_creator_maintenance',
				'etat'   => $activer ? 'on' : 'off',
			),
			admin_url( 'admin-post.php' )
		),
		'blocs_creator_maintenance'
	);
}

/**
 * Ajoute « Maintenance » à la barre d'outils.
 *
 * Les administrateurs l'ont toujours, avec l'interrupteur au survol. Les
 * autres personnes qui voient le site pendant la maintenance ne l'ont que
 * lorsqu'elle est activée, pour savoir que les visiteurs, eux, ne le voient
 * pas.
 *
 * @param WP_Admin_Bar $barre La barre d'outils.
 */
function blocs_creator_maintenance_barre( $barre ) {
	$active = blocs_creator_maintenance_active();
	$admin  = current_user_can( 'manage_options' );

	if ( ! blocs_creator_maintenance_peut_passer() || ( ! $active && ! $admin ) ) {
		return;
	}

	$reglages = admin_url( 'options-general.php?page=blocs-creator-maintenance' );
	$apercu   = add_query_arg( BLOCS_CREATOR_MAINTENANCE_APERCU, '1', home_url( '/' ) );

	$barre->add_node(
		array(
			'id'    => 'blocs-creator-maintenance',
			'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">' . esc_html( $active ? __( 'Maintenance activée', 'blocs-creator' ) : __( 'Maintenance', 'blocs-creator' ) ) . '</span>',
			'href'  => $admin ? $reglages : $apercu,
			'meta'  => array(
				'class' => $active ? 'is-active' : '',
				'title' => $active
					? __( 'Le site est fermé : les visiteurs voient la page de maintenance.', 'blocs-creator' )
					: __( 'Le site est ouvert à tous.', 'blocs-creator' ),
			),
		)
	);

	if ( $admin ) {
		$barre->add_node(
			array(
				'parent' => 'blocs-creator-maintenance',
				'id'     => 'blocs-creator-maintenance-bascule',
				'title'  => esc_html( $active ? __( 'Désactiver : rouvrir le site', 'blocs-creator' ) : __( 'Activer : fermer le site aux visiteurs', 'blocs-creator' ) ),
				'href'   => blocs_creator_maintenance_lien_bascule( ! $active ),
			)
		);
	}

	$barre->add_node(
		array(
			'parent' => 'blocs-creator-maintenance',
			'id'     => 'blocs-creator-maintenance-apercu',
			'title'  => esc_html__( 'Voir la page de maintenance', 'blocs-creator' ),
			'href'   => $apercu,
		)
	);

	if ( $admin ) {
		$barre->add_node(
			array(
				'parent' => 'blocs-creator-maintenance',
				'id'     => 'blocs-creator-maintenance-reglages',
				'title'  => esc_html__( 'Modifier les textes de la page', 'blocs-creator' ),
				'href'   => $reglages,
			)
		);
	}
}
add_action( 'admin_bar_menu', 'blocs_creator_maintenance_barre', 100 );

/**
 * L'allure de « Maintenance » dans la barre d'outils : un marteau, et un fond
 * orange quand le site est fermé, pour ne pas l'oublier.
 */
function blocs_creator_maintenance_style_barre() {
	if ( ! is_admin_bar_showing() || ! blocs_creator_maintenance_peut_passer() ) {
		return;
	}

	wp_add_inline_style(
		'admin-bar',
		'#wpadminbar #wp-admin-bar-blocs-creator-maintenance > .ab-item .ab-icon:before{content:"\f308";top:2px}'
		. '#wpadminbar #wp-admin-bar-blocs-creator-maintenance.is-active > .ab-item{background:#c2410c;color:#fff}'
		. '#wpadminbar #wp-admin-bar-blocs-creator-maintenance.is-active:hover > .ab-item,'
		. '#wpadminbar #wp-admin-bar-blocs-creator-maintenance.is-active.hover > .ab-item,'
		. '#wpadminbar #wp-admin-bar-blocs-creator-maintenance.is-active > .ab-item:focus{background:#9a3412;color:#fff}'
		. '#wpadminbar #wp-admin-bar-blocs-creator-maintenance.is-active > .ab-item .ab-icon:before,'
		. '#wpadminbar #wp-admin-bar-blocs-creator-maintenance.is-active:hover > .ab-item .ab-icon:before,'
		. '#wpadminbar #wp-admin-bar-blocs-creator-maintenance.is-active.hover > .ab-item .ab-icon:before{color:#fff}'
		. '@media screen and (max-width:782px){#wpadminbar li#wp-admin-bar-blocs-creator-maintenance{display:block}}'
	);
}
add_action( 'wp_enqueue_scripts', 'blocs_creator_maintenance_style_barre', 20 );
add_action( 'admin_enqueue_scripts', 'blocs_creator_maintenance_style_barre', 20 );

/**
 * Change l'état, puis revient là où l'on était.
 *
 * Par admin-post.php : réservé aux personnes connectées, ce qui tombe bien
 * (une extension qui cache l'administration n'en bloque que les visiteurs).
 */
function blocs_creator_maintenance_basculer() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Vous n\'avez pas le droit de changer la maintenance du site.', 'blocs-creator' ), 403 );
	}

	check_admin_referer( 'blocs_creator_maintenance' );

	$activer = isset( $_GET['etat'] ) && 'on' === $_GET['etat'];
	update_option( BLOCS_CREATOR_MAINTENANCE_OPTION, array_merge( blocs_creator_maintenance_reglages(), array( 'actif' => $activer ) ) );

	$retour = wp_get_referer();

	if ( ! $retour ) {
		$retour = admin_url( 'options-general.php?page=blocs-creator-maintenance' );
	}

	// Dans l'administration, un message confirme ; sur le site, la barre d'outils suffit.
	if ( str_starts_with( $retour, admin_url() ) ) {
		$retour = add_query_arg( 'bc-maintenance', $activer ? 'activee' : 'desactivee', $retour );
	}

	wp_safe_redirect( $retour );
	exit;
}
add_action( 'admin_post_blocs_creator_maintenance', 'blocs_creator_maintenance_basculer' );

/**
 * Le paramètre du message de confirmation disparaît de l'adresse.
 *
 * @param string[] $parametres Paramètres retirés de l'adresse.
 * @return string[]
 */
function blocs_creator_maintenance_parametres_retires( $parametres ) {
	$parametres[] = 'bc-maintenance';

	return $parametres;
}
add_filter( 'removable_query_args', 'blocs_creator_maintenance_parametres_retires' );

/**
 * Les messages de l'administration : la confirmation après un changement, et
 * un rappel sur le tableau de bord tant que le site est fermé.
 */
function blocs_creator_maintenance_avis() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple affichage.
	$etat = isset( $_GET['bc-maintenance'] ) ? sanitize_key( $_GET['bc-maintenance'] ) : '';

	if ( 'activee' === $etat ) {
		printf(
			'<div class="notice notice-warning is-dismissible"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'Maintenance activée.', 'blocs-creator' ),
			esc_html__( 'Les visiteurs voient la page de maintenance ; vous, vous voyez le site normalement.', 'blocs-creator' )
		);

		return;
	}

	if ( 'desactivee' === $etat ) {
		printf(
			'<div class="notice notice-success is-dismissible"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'Maintenance désactivée.', 'blocs-creator' ),
			esc_html__( 'Le site est de nouveau ouvert à tous.', 'blocs-creator' )
		);

		return;
	}

	$ecran = get_current_screen();

	if ( $ecran && 'dashboard' === $ecran->id && blocs_creator_maintenance_active() ) {
		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <a class="button button-small" href="%3$s">%4$s</a></p></div>',
			esc_html__( 'Le site est en maintenance.', 'blocs-creator' ),
			esc_html__( 'Les visiteurs voient la page de maintenance.', 'blocs-creator' ),
			esc_url( blocs_creator_maintenance_lien_bascule( false ) ),
			esc_html__( 'Rouvrir le site', 'blocs-creator' )
		);
	}
}
add_action( 'admin_notices', 'blocs_creator_maintenance_avis' );

/* ------------------------------------------------------------------ *
 * 3. Réglages › Maintenance
 * ------------------------------------------------------------------ */

/**
 * Enregistre l'écran.
 */
function blocs_creator_maintenance_menu() {
	add_options_page(
		__( 'Maintenance du site', 'blocs-creator' ),
		__( 'Maintenance', 'blocs-creator' ),
		'manage_options',
		'blocs-creator-maintenance',
		'blocs_creator_maintenance_ecran'
	);
}
add_action( 'admin_menu', 'blocs_creator_maintenance_menu' );

/**
 * Déclare l'option. Elle est lue à chaque visite : chargée d'office.
 */
function blocs_creator_maintenance_declarer() {
	register_setting(
		'blocs_creator_maintenance',
		BLOCS_CREATOR_MAINTENANCE_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'blocs_creator_maintenance_nettoyer',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'blocs_creator_maintenance_declarer' );

/**
 * Nettoie les réglages envoyés par l'écran.
 *
 * @param mixed $valeurs Valeurs du formulaire.
 * @return array
 */
function blocs_creator_maintenance_nettoyer( $valeurs ) {
	$valeurs = is_array( $valeurs ) ? $valeurs : array();
	$defauts = blocs_creator_maintenance_defauts();
	$retour  = sanitize_text_field( (string) ( $valeurs['retour'] ?? '' ) );

	$nettoyes = array(
		'actif'  => ! empty( $valeurs['actif'] ),
		'titre'  => trim( wp_kses( (string) ( $valeurs['titre'] ?? '' ), blocs_creator_maintenance_balises() ) ),
		'texte'  => trim( wp_kses( (string) ( $valeurs['texte'] ?? '' ), blocs_creator_maintenance_balises() ) ),
		'retour' => date_create_immutable_from_format( 'Y-m-d\TH:i', $retour, wp_timezone() ) ? $retour : '',
	);

	// Une page sans titre n'aurait pas de H1 : on reprend celui d'origine.
	if ( '' === $nettoyes['titre'] ) {
		$nettoyes['titre'] = $defauts['titre'];
	}

	return $nettoyes;
}

/**
 * L'écran Réglages › Maintenance.
 */
function blocs_creator_maintenance_ecran() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$reglages = blocs_creator_maintenance_reglages();
	$nom      = static fn( $cle ) => BLOCS_CREATOR_MAINTENANCE_OPTION . '[' . $cle . ']';
	$apercu   = add_query_arg( BLOCS_CREATOR_MAINTENANCE_APERCU, '1', home_url( '/' ) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Maintenance du site', 'blocs-creator' ); ?></h1>
		<p style="max-width:62em">
			<?php esc_html_e( 'Pendant la maintenance, les visiteurs voient une page d\'attente à la place du site. Vous, et toutes les personnes connectées qui peuvent modifier le site, continuez à le voir normalement pour travailler.', 'blocs-creator' ); ?>
		</p>

		<?php if ( ! empty( $reglages['actif'] ) ) : ?>
			<div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'La maintenance est activée : le site est fermé aux visiteurs.', 'blocs-creator' ); ?></strong></p></div>
		<?php else : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'La maintenance est désactivée : le site est ouvert à tous.', 'blocs-creator' ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'blocs_creator_maintenance' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Maintenance', 'blocs-creator' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $nom( 'actif' ) ); ?>" value="1" <?php checked( ! empty( $reglages['actif'] ) ); ?>>
							<?php esc_html_e( 'Fermer le site aux visiteurs et afficher la page de maintenance', 'blocs-creator' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Plus rapide : « Maintenance », dans la barre noire en haut de l\'écran, l\'active ou la désactive en un clic, depuis n\'importe quelle page du site ou de l\'administration.', 'blocs-creator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bc-maintenance-titre"><?php esc_html_e( 'Titre', 'blocs-creator' ); ?></label></th>
					<td>
						<input type="text" class="large-text" id="bc-maintenance-titre" name="<?php echo esc_attr( $nom( 'titre' ) ); ?>" value="<?php echo esc_attr( $reglages['titre'] ); ?>">
						<p class="description"><?php esc_html_e( '**mot** s\'affiche en gras (la couleur d\'accent du site), _mot_ en italique.', 'blocs-creator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bc-maintenance-texte"><?php esc_html_e( 'Message', 'blocs-creator' ); ?></label></th>
					<td>
						<textarea class="large-text" rows="3" id="bc-maintenance-texte" name="<?php echo esc_attr( $nom( 'texte' ) ); ?>"><?php echo esc_textarea( $reglages['texte'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Une ou deux phrases. Vous pouvez y mettre un lien pour vous joindre, par exemple <a href="mailto:adresse@exemple.fr">écrivez-nous</a>.', 'blocs-creator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bc-maintenance-retour"><?php esc_html_e( 'Retour prévu', 'blocs-creator' ); ?></label></th>
					<td>
						<input type="datetime-local" id="bc-maintenance-retour" name="<?php echo esc_attr( $nom( 'retour' ) ); ?>" value="<?php echo esc_attr( $reglages['retour'] ); ?>">
						<p class="description"><?php esc_html_e( 'Facultatif. Affiché sur la page (« Retour prévu demain, vers 14 h ») et donné à Google pour qu\'il repasse à ce moment-là. L\'heure passée, la mention disparaît d\'elle-même ; la maintenance, elle, reste activée jusqu\'à ce que vous la désactiviez.', 'blocs-creator' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<?php submit_button( null, 'primary', 'submit', false ); ?>
				<a class="button" href="<?php echo esc_url( $apercu ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Voir la page de maintenance', 'blocs-creator' ); ?></a>
			</p>
		</form>
	</div>
	<?php
}
