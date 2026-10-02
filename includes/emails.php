<?php
/**
 * La mise en page des e-mails : une boîte à outils pour les formulaires du
 * thème.
 *
 * Toujours chargée, et sans effet tant qu'on ne l'appelle pas : aucun e-mail
 * de WordPress n'est touché. Un formulaire de contact, d'inscription ou de
 * devis s'en sert pour envoyer, au site comme au visiteur, des e-mails qui
 * se ressemblent tous : une carte blanche sur un fond doux, le nom du site en
 * petit surtitre, un grand titre, puis le contenu.
 *
 *     $html = blocs_creator_email_html( array(
 *         'titre' => 'Nouveau message',
 *         'intro' => 'Reçu le 2 octobre à 14 h',
 *         'corps' => blocs_creator_email_tableau(
 *             blocs_creator_email_ligne( 'Nom', esc_html( $nom ) )
 *             . blocs_creator_email_ligne( 'Message', nl2br( esc_html( $message ) ) )
 *         ),
 *     ) );
 *     blocs_creator_email_envoyer( $destinataire, 'Nouveau message', $html, array( 'Reply-To: ' . $email ) );
 *
 * Les styles sont écrits dans les balises : la plupart des messageries
 * ignorent les feuilles de style. Chaque e-mail part aussi en texte brut, à
 * côté du HTML, pour les messageries qui ne lisent pas le HTML — et pour les
 * filtres anti-spam, qui s'en méfient moins.
 *
 * Les couleurs se règlent par le filtre `blocs_creator_email_couleurs`.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Les couleurs des e-mails.
 *
 * @return array<string, string>
 */
function blocs_creator_email_couleurs() {
	/**
	 * Filtre les couleurs des e-mails : celles du site, de préférence.
	 *
	 * @param array<string, string> $couleurs fond, carte, encre, gris, pale, filet, accent, profond.
	 */
	return (array) apply_filters(
		'blocs_creator_email_couleurs',
		array(
			'fond'    => '#f6f5f2',
			'carte'   => '#ffffff',
			'encre'   => '#1d1b19',
			'gris'    => '#5f5a55',
			'pale'    => '#97908a',
			'filet'   => '#ebe7e2',
			'accent'  => '#c2410c',
			'profond' => '#9a3412',
		)
	);
}

/**
 * L'e-mail complet, dans sa mise en page.
 *
 * @param array $morceaux {
 *     Les morceaux de l'e-mail.
 *
 *     @type string $titre Le grand titre (texte brut).
 *     @type string $intro Sous le titre, en petit (texte brut).
 *     @type string $corps Le contenu (HTML).
 *     @type string $pied  Sous le contenu, en petit (HTML).
 * }
 * @return string HTML.
 */
function blocs_creator_email_html( $morceaux ) {
	$c    = blocs_creator_email_couleurs();
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

	$morceaux = wp_parse_args(
		$morceaux,
		array(
			'titre' => '',
			'intro' => '',
			'corps' => '',
			'pied'  => '',
		)
	);

	return sprintf(
		'<!DOCTYPE html><html lang="%10$s"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>%1$s</title></head>'
		. '<body style="margin:0;padding:24px;background:%6$s;font-family:Arial,Helvetica,sans-serif">'
		. '<div style="max-width:680px;margin:0 auto;padding:32px;border-radius:16px;background:%7$s">'
		. '<p style="margin:0 0 6px;color:%8$s;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase">%2$s</p>'
		. '<h1 style="margin:0 0 10px;color:%9$s;font-size:26px">%1$s</h1>'
		. '%3$s%4$s%5$s'
		. '</div></body></html>',
		esc_html( $morceaux['titre'] ),
		esc_html( $site ),
		'' !== $morceaux['intro'] ? sprintf( '<p style="margin:0 0 8px;color:%1$s;font-size:14px">%2$s</p>', esc_attr( $c['gris'] ), esc_html( $morceaux['intro'] ) ) : '',
		$morceaux['corps'],
		'' !== $morceaux['pied'] ? sprintf( '<p style="margin:28px 0 0;color:%1$s;font-size:12px;line-height:1.5">%2$s</p>', esc_attr( $c['pale'] ), $morceaux['pied'] ) : '',
		esc_attr( $c['fond'] ),
		esc_attr( $c['carte'] ),
		esc_attr( $c['profond'] ),
		esc_attr( $c['encre'] ),
		esc_attr( substr( (string) get_bloginfo( 'language' ), 0, 2 ) )
	);
}

/**
 * Un intertitre de l'e-mail : petites capitales, soulignées.
 *
 * @param string $texte Texte brut.
 * @return string HTML (une rangée de tableau).
 */
function blocs_creator_email_intertitre( $texte ) {
	$c = blocs_creator_email_couleurs();

	return sprintf(
		'<tr><td colspan="2" style="padding:26px 0 8px;border-bottom:2px solid %1$s;color:%2$s;font-size:13px;font-weight:800;letter-spacing:.08em;text-transform:uppercase">%3$s</td></tr>',
		esc_attr( $c['accent'] ),
		esc_attr( $c['profond'] ),
		esc_html( $texte )
	);
}

/**
 * Une ligne de réponses : le libellé à gauche, la réponse à droite.
 *
 * @param string $libelle Libellé (texte brut).
 * @param string $valeur  Réponse (HTML, déjà échappé).
 * @return string HTML (une rangée de tableau).
 */
function blocs_creator_email_ligne( $libelle, $valeur ) {
	$c = blocs_creator_email_couleurs();

	return sprintf(
		'<tr><th scope="row" style="width:42%%;padding:10px 16px 10px 0;border-bottom:1px solid %1$s;color:%2$s;font-size:14px;font-weight:600;text-align:left;vertical-align:top">%3$s</th><td style="padding:10px 0;border-bottom:1px solid %1$s;color:%4$s;font-size:15px;vertical-align:top">%5$s</td></tr>',
		esc_attr( $c['filet'] ),
		esc_attr( $c['gris'] ),
		esc_html( $libelle ),
		esc_attr( $c['encre'] ),
		$valeur
	);
}

/**
 * Le tableau des réponses.
 *
 * @param string $lignes Rangées (HTML).
 * @return string HTML.
 */
function blocs_creator_email_tableau( $lignes ) {
	return '<table role="presentation" style="width:100%;border-collapse:collapse">' . $lignes . '</table>';
}

/**
 * Un lien, aux couleurs de l'e-mail.
 *
 * @param string $url     Adresse (mailto:, tel:, https:).
 * @param string $libelle Texte du lien (texte brut).
 * @return string HTML.
 */
function blocs_creator_email_lien( $url, $libelle ) {
	return sprintf(
		'<a href="%1$s" style="color:%2$s">%3$s</a>',
		esc_url( $url, array( 'http', 'https', 'mailto', 'tel' ) ),
		esc_attr( blocs_creator_email_couleurs()['profond'] ),
		esc_html( $libelle )
	);
}

/**
 * Un texte écrit par la rédaction (un réglage de bloc), en paragraphes : une
 * ligne vide sépare deux paragraphes, un retour à la ligne reste un retour à
 * la ligne, **mot** passe en gras.
 *
 * @param string $texte Texte brut.
 * @return string HTML.
 */
function blocs_creator_email_paragraphes( $texte ) {
	$c     = blocs_creator_email_couleurs();
	$html  = '';
	$blocs = preg_split( '/\R\s*\R/', trim( (string) $texte ) );

	foreach ( (array) $blocs as $bloc ) {
		$bloc = trim( $bloc );

		if ( '' === $bloc ) {
			continue;
		}

		$bloc  = preg_replace( '/\*\*(.+?)\*\*/su', '<strong>$1</strong>', esc_html( $bloc ) );
		$html .= sprintf(
			'<p style="margin:0 0 14px;color:%1$s;font-size:16px;line-height:1.6">%2$s</p>',
			esc_attr( $c['encre'] ),
			nl2br( (string) $bloc, false )
		);
	}

	return $html;
}

/**
 * La version texte brut d'un e-mail HTML.
 *
 * @param string $html HTML de l'e-mail.
 * @return string
 */
function blocs_creator_email_texte( $html ) {
	$html = (string) preg_replace( '#<(head|style|script)\b.*?</\1>#is', '', $html );
	$html = (string) preg_replace( '#<br\s*/?>\s*#i', "\n", $html );
	$html = (string) preg_replace( '#</th>\s*#i', ' : ', $html );
	$html = (string) preg_replace( '#</tr>\s*#i', "\n", $html );
	$html = (string) preg_replace( '#</(p|h[1-6]|div|table)>#i', "\n\n", $html );
	$html = (string) preg_replace_callback(
		'#<a\s[^>]*href="([^"]*)"[^>]*>(.*?)</a>#is',
		static function ( $lien ) {
			$adresse = html_entity_decode( $lien[1], ENT_QUOTES, 'UTF-8' );
			$libelle = wp_strip_all_tags( $lien[2] );

			// Le texte du lien suffit pour un e-mail, un téléphone, ou quand il EST l'adresse ; sinon, l'adresse suit entre parenthèses.
			if ( preg_match( '#^(mailto|tel):#', $adresse ) || rtrim( $adresse, '/' ) === rtrim( html_entity_decode( $libelle, ENT_QUOTES, 'UTF-8' ), '/' ) ) {
				return $libelle;
			}

			return $libelle . ' (' . $adresse . ')';
		},
		$html
	);

	$texte = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
	$texte = (string) preg_replace( "/[ \t]+\n/", "\n", $texte );

	return trim( (string) preg_replace( "/\n{3,}/", "\n\n", $texte ) );
}

/**
 * Envoie un e-mail HTML, avec sa version texte brut.
 *
 * @param string|string[] $a          Destinataire(s).
 * @param string          $sujet      Objet.
 * @param string          $html       Corps HTML (blocs_creator_email_html()).
 * @param string[]        $entetes    En-têtes en plus (Reply-To…).
 * @param array           $jointes    Pièces jointes.
 * @param array           $incrustees Images incrustées (`cid` => chemin), WordPress 6.9+.
 * @return bool
 */
function blocs_creator_email_envoyer( $a, $sujet, $html, $entetes = array(), $jointes = array(), $incrustees = array() ) {
	$texte = blocs_creator_email_texte( $html );
	$alt   = static function ( $phpmailer ) use ( $texte ) {
		$phpmailer->AltBody = $texte; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- propriété de PHPMailer.
	};

	$entetes   = (array) $entetes;
	$entetes[] = 'Content-Type: text/html; charset=UTF-8';

	add_action( 'phpmailer_init', $alt );
	$envoye = $incrustees ? wp_mail( $a, $sujet, $html, $entetes, $jointes, $incrustees ) : wp_mail( $a, $sujet, $html, $entetes, $jointes );
	remove_action( 'phpmailer_init', $alt );

	return $envoye;
}

/**
 * L'e-mail de confirmation envoyé au visiteur, après un formulaire.
 *
 * Il répète le message choisi par la rédaction, sous un « Bonjour » à son
 * nom. Il ne reprend JAMAIS ce que le visiteur a écrit : un robot qui
 * glisserait l'adresse d'un tiers dans le formulaire ne pourrait pas s'en
 * servir pour lui faire porter son propre texte.
 *
 * @param array $args {
 *     @type string $email   Adresse du visiteur.
 *     @type string $nom     Son nom, pour le « Bonjour ».
 *     @type string $sujet   Objet de l'e-mail (sans le nom du site).
 *     @type string $titre   Grand titre.
 *     @type string $intro   Sous le titre (date, objet…).
 *     @type string $message Message de la rédaction (texte brut, **gras**).
 *     @type string $reponse Adresse du site, pour répondre.
 * }
 * @return bool
 */
function blocs_creator_email_confirmation( $args ) {
	if ( ! is_email( $args['email'] ?? '' ) || '' === trim( (string) ( $args['message'] ?? '' ) ) ) {
		return false;
	}

	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$nom  = trim( (string) ( $args['nom'] ?? '' ) );

	$corps = blocs_creator_email_paragraphes(
		/* translators: %s : nom du visiteur. */
		( '' !== $nom ? sprintf( __( 'Bonjour %s,', 'blocs-creator' ), $nom ) : __( 'Bonjour,', 'blocs-creator' ) )
	);

	$html = blocs_creator_email_html(
		array(
			'titre' => (string) ( $args['titre'] ?? '' ),
			'intro' => (string) ( $args['intro'] ?? '' ),
			'corps' => '<div style="margin-top:26px;padding-top:24px;border-top:2px solid ' . esc_attr( blocs_creator_email_couleurs()['accent'] ) . '">' . $corps . blocs_creator_email_paragraphes( $args['message'] ) . '</div>',
			'pied'  => sprintf(
				/* translators: %s : lien vers le site. */
				esc_html__( 'Cet e-mail confirme un envoi depuis le site %s. Pour nous écrire, répondez simplement à ce message.', 'blocs-creator' ),
				blocs_creator_email_lien( home_url( '/' ), $site )
			),
		)
	);

	$entetes = array();
	$reponse = sanitize_email( (string) ( $args['reponse'] ?? '' ) );

	if ( is_email( $reponse ) ) {
		$entetes[] = sprintf( 'Reply-To: %1$s <%2$s>', str_replace( array( '<', '>', '"', ',' ), '', $site ), $reponse );
	}

	return blocs_creator_email_envoyer( $args['email'], sprintf( '[%1$s] %2$s', $site, (string) ( $args['sujet'] ?? '' ) ), $html, $entetes );
}
