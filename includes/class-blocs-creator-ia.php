<?php
/**
 * L'IA : rédiger un article, remplir une section, et laisser l'extension
 * « AI » de WordPress lire les blocs.
 *
 * Le plugin ne parle à aucune IA lui-même et ne garde aucune clé : il passe
 * par le client d'IA de WordPress (7.0 et plus), branché dans Réglages ›
 * Connecteurs — Google, Anthropic, OpenAI. Sans connecteur, ou avec un
 * WordPress plus ancien, rien de tout cela n'apparaît.
 *
 * Trois services :
 *
 *   1. RÉDIGER UN ARTICLE. Dans la colonne de droite de l'éditeur d'article,
 *      des notes en vrac et une longueur ; l'IA écrit un brouillon — titre,
 *      chapô, texte en Markdown — que l'éditeur change en blocs et qu'on
 *      relit avant de l'insérer. Rien n'est publié tout seul.
 *   2. REMPLIR UNE SECTION. Dans la colonne de droite d'un bloc créé ici, des
 *      notes ; l'IA propose le contenu de ses champs de texte (titre, texte,
 *      libellé des liens, lignes d'un répéteur) d'après la définition du bloc.
 *      Ni les images, ni les adresses, ni les réglages d'affichage : ce sont
 *      des choix, pas du texte.
 *   3. L'EXTENSION « AI » LIT LES BLOCS. Un bloc créé ici garde son texte dans
 *      ses réglages : dans le contenu enregistré, ce n'est qu'un commentaire.
 *      L'extension, qui lit ce contenu pour proposer une méta description ou
 *      un extrait, trouvait donc vide une page faite de sections. On lui
 *      donne la page affichée.
 *
 * Deux règles tiennent les consignes données à l'IA : elle n'invente rien
 * (ni nom, ni date, ni chiffre) qui ne soit dans les notes, et tout ce
 * qu'elle propose se relit avant d'entrer dans la page.
 *
 * Quand Google est surchargé (503) ou le quota gratuit atteint (429), on
 * réessaie avec le modèle suivant de la liste. Les erreurs reviennent en
 * français, avec quoi faire.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Les services d'IA.
 */
class Blocs_Creator_Ia {

	/**
	 * Option des réglages.
	 */
	const OPTION = 'blocs_creator_ia';

	/**
	 * Les hôtes des IA, pour le délai de réponse.
	 */
	const HOTES = array( 'generativelanguage.googleapis.com', 'api.anthropic.com', 'api.openai.com' );

	/**
	 * Les types de champs que l'IA sait remplir.
	 */
	const REMPLISSABLES = array( 'texte', 'texte-long', 'texte-riche', 'nombre', 'lien', 'groupe', 'repeteur' );

	/**
	 * Branche les hooks.
	 */
	public static function demarrer() {
		add_filter( 'wpai_pre_normalize_content', array( __CLASS__, 'contenu_affiche' ) );
		add_filter( 'wpai_normalize_content', array( __CLASS__, 'espaces' ) );
		add_filter( 'wpai_min_content_length', array( __CLASS__, 'minimum' ), 10, 2 );
		add_filter( 'http_request_args', array( __CLASS__, 'delai_http' ), 10, 2 );
		add_filter( 'wp_ai_client_default_request_timeout', array( __CLASS__, 'delai_client' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'assets_editeur' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Réglages
	 * ------------------------------------------------------------------ */

	/**
	 * Les réglages par défaut.
	 *
	 * Les modèles sont des Gemini gratuits : les plus récents sont souvent
	 * les plus demandés, d'où plusieurs essais. Un site branché sur une autre
	 * IA garde la liste : un modèle qui n'existe pas chez elle est simplement
	 * ignoré, et WordPress prend le premier modèle qu'elle propose.
	 *
	 * @return array
	 */
	public static function defauts() {
		return array(
			'consignes' => '',
			'modeles'   => "gemini-3.7-flash\ngemini-3.5-flash\ngemini-2.5-flash\ngemini-3.5-flash-lite",
			'articles'  => true,
			'types'     => array( 'post' ),
			'sections'  => true,
			'extension' => true,
			'delai'     => 90,
		);
	}

	/**
	 * Les réglages d'un site qui existait avant la 5.0 : tout éteint.
	 *
	 * Un site en place ne doit rien voir changer tant qu'on ne coche rien —
	 * et il a peut-être déjà son propre panneau d'IA dans son thème (c'est le
	 * cas du site d'où ces services viennent) : deux « Rédiger avec l'IA »
	 * côte à côte, et un minimum de texte compté deux fois.
	 *
	 * @return array
	 */
	public static function eteints() {
		return array(
			'articles'  => false,
			'sections'  => false,
			'extension' => false,
		);
	}

	/**
	 * Le site existait-il avant la 5.0 ?
	 *
	 * @return bool
	 */
	public static function site_anterieur() {
		$version = (string) get_option( 'blocs_creator_version', '' );

		return '' !== $version && '0' !== $version && version_compare( $version, '5.0.0', '<' );
	}

	/**
	 * Les réglages, complétés par les valeurs par défaut.
	 *
	 * Tant que rien n'est enregistré, un site d'avant la 5.0 a l'IA éteinte
	 * (Blocs_Creator_Plugin::mettre_a_jour() l'écrit ensuite pour de bon) ; un
	 * site neuf l'a allumée, dès qu'une IA est branchée.
	 *
	 * @return array
	 */
	public static function reglages() {
		$enregistres = get_option( self::OPTION, null );

		if ( null === $enregistres ) {
			$enregistres = self::site_anterieur() ? self::eteints() : array();
		}

		return wp_parse_args( (array) $enregistres, self::defauts() );
	}

	/**
	 * Nettoie les réglages envoyés par l'écran.
	 *
	 * @param array $valeurs Valeurs brutes.
	 * @return array
	 */
	public static function assainir( $valeurs ) {
		$valeurs = (array) $valeurs;
		$defauts = self::defauts();

		$modeles = preg_split( '/[\r\n,]+/', (string) ( $valeurs['modeles'] ?? '' ) );
		$modeles = array_filter( array_map( static fn( $m ) => preg_replace( '/[^a-zA-Z0-9._:\/-]/', '', trim( (string) $m ) ), (array) $modeles ) );

		$types = array_values(
			array_filter(
				array_map( 'sanitize_key', (array) ( $valeurs['types'] ?? array() ) ),
				'post_type_exists'
			)
		);

		return array(
			'consignes' => sanitize_textarea_field( (string) ( $valeurs['consignes'] ?? '' ) ),
			'modeles'   => $modeles ? implode( "\n", $modeles ) : $defauts['modeles'],
			'articles'  => ! empty( $valeurs['articles'] ),
			'types'     => $types ? $types : $defauts['types'],
			'sections'  => ! empty( $valeurs['sections'] ),
			'extension' => ! empty( $valeurs['extension'] ),
			'delai'     => max( 30, min( 300, (int) ( $valeurs['delai'] ?? $defauts['delai'] ) ) ),
		);
	}

	/**
	 * Écrit les réglages envoyés par l'écran des réglages.
	 *
	 * @param array $valeurs Valeurs brutes du formulaire.
	 */
	public static function enregistrer_depuis_formulaire( $valeurs ) {
		update_option( self::OPTION, self::assainir( $valeurs ), false );
	}

	/**
	 * Le client d'IA de WordPress est-il là, et permis ?
	 *
	 * @return bool
	 */
	public static function disponible() {
		return function_exists( 'wp_ai_client_prompt' ) && function_exists( 'wp_supports_ai' ) && wp_supports_ai();
	}

	/**
	 * Les modèles à essayer, dans l'ordre.
	 *
	 * @return string[]
	 */
	public static function modeles() {
		$modeles = array_filter( array_map( 'trim', explode( "\n", (string) self::reglages()['modeles'] ) ) );

		/**
		 * Filtre les modèles essayés, dans l'ordre.
		 *
		 * @param string[] $modeles Identifiants de modèles.
		 */
		return array_values( array_filter( (array) apply_filters( 'blocs_creator_ia_modeles', $modeles ), 'is_string' ) );
	}

	/**
	 * Les consignes communes à toutes les demandes : qui écrit, pour qui, sur
	 * quel ton.
	 *
	 * @return string
	 */
	public static function consignes_communes() {
		$consignes = trim( (string) self::reglages()['consignes'] );

		if ( '' === $consignes ) {
			// get_bloginfo() rend du HTML : « l&#039;atelier » doit redevenir « l'atelier ».
			$site   = wp_strip_all_tags( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
			$slogan = wp_strip_all_tags( wp_specialchars_decode( get_bloginfo( 'description' ), ENT_QUOTES ) );

			$consignes = sprintf(
				'Tu écris pour le site « %1$s »%2$s. Écris en français, dans un ton chaleureux, simple et clair. Si tu t\'adresses au lecteur, vouvoie-le.',
				$site,
				'' !== $slogan ? ' (' . $slogan . ')' : ''
			);
		}

		/**
		 * Filtre les consignes communes données à l'IA.
		 *
		 * @param string $consignes Consignes.
		 */
		return (string) apply_filters( 'blocs_creator_ia_consignes', $consignes );
	}

	/* ------------------------------------------------------------------ *
	 * L'extension « AI » lit les blocs
	 * ------------------------------------------------------------------ */

	/**
	 * Le nombre de caractères de texte d'un contenu, espaces non compris — la
	 * mesure de l'extension.
	 *
	 * @param string $html Contenu (HTML ou blocs).
	 * @return int
	 */
	private static function longueur( $html ) {
		$texte = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return mb_strlen( (string) preg_replace( '/\s+/u', '', $texte ) );
	}

	/**
	 * Avant que l'extension ne réduise un contenu à son texte : s'il est encore
	 * fait de blocs, on l'affiche d'abord, sections comprises.
	 *
	 * @param string $contenu Contenu envoyé par l'extension.
	 * @return string
	 */
	public static function contenu_affiche( $contenu ) {
		if ( ! self::reglages()['extension'] || ! is_string( $contenu ) || ! has_blocks( $contenu ) ) {
			return $contenu;
		}

		return do_blocks( $contenu );
	}

	/**
	 * Le texte des sections garde l'indentation de leurs gabarits : une seule
	 * espace entre les mots suffit à l'IA (et use moins le quota).
	 *
	 * @param string $texte Contenu réduit à son texte par l'extension.
	 * @return string
	 */
	public static function espaces( $texte ) {
		if ( ! self::reglages()['extension'] || ! is_string( $texte ) ) {
			return $texte;
		}

		return trim( (string) preg_replace( '/\s+/u', ' ', $texte ) );
	}

	/**
	 * Le minimum de texte des boutons de l'extension, dans l'éditeur, diminué
	 * du texte qu'apportent les sections de la page ouverte (mesuré à
	 * l'ouverture : une section ajoutée ensuite compte après enregistrement).
	 *
	 * @param int    $minimum  Minimum de l'extension, en caractères.
	 * @param string $fonction Fonction concernée (meta-description…).
	 * @return int
	 */
	public static function minimum( $minimum, $fonction = '' ) {
		static $apport = null;

		if ( ! is_admin() || ! self::reglages()['extension'] ) {
			return $minimum;
		}

		if ( null === $apport ) {
			$apport  = 0;
			$contenu = get_post() instanceof WP_Post ? (string) get_post()->post_content : '';

			if ( has_blocks( $contenu ) ) {
				$apport = max( 0, self::longueur( do_blocks( $contenu ) ) - self::longueur( $contenu ) );
			}
		}

		return max( 0, (int) $minimum - $apport );
	}

	/**
	 * Le délai des demandes aux IA. Gemini réfléchit avant d'écrire et ne
	 * renvoie rien tant qu'il n'a pas fini : sur une page longue ou un jour
	 * chargé, les 30 secondes par défaut ne suffisaient pas (« cURL error 28:
	 * Operation timed out after 30000 milliseconds with 0 bytes received »).
	 *
	 * @param array  $arguments Arguments de la requête HTTP.
	 * @param string $adresse   Adresse appelée.
	 * @return array
	 */
	public static function delai_http( $arguments, $adresse ) {
		$hote = (string) wp_parse_url( (string) $adresse, PHP_URL_HOST );

		if ( in_array( $hote, self::HOTES, true ) ) {
			$arguments['timeout'] = max( (float) ( $arguments['timeout'] ?? 5 ), (float) self::reglages()['delai'] );
		}

		return $arguments;
	}

	/**
	 * Le même délai, pour le client d'IA de WordPress.
	 *
	 * @param float $delai Délai par défaut, en secondes.
	 * @return float
	 */
	public static function delai_client( $delai ) {
		return max( (float) $delai, (float) self::reglages()['delai'] );
	}

	/* ------------------------------------------------------------------ *
	 * La demande
	 * ------------------------------------------------------------------ */

	/**
	 * Une demande à l'IA, avec repli sur le modèle suivant quand l'erreur est
	 * passagère.
	 *
	 * @param string $consignes Consignes (instruction système).
	 * @param string $demande   Demande.
	 * @param array  $options   `temperature` (0.7), `schema` (réponse JSON).
	 * @return string|WP_Error Le texte rendu, ou l'erreur dite en français.
	 */
	public static function generer( $consignes, $demande, $options = array() ) {
		if ( ! self::disponible() ) {
			return new WP_Error( 'bc_ia', __( 'L’IA n’est pas disponible sur ce site.', 'blocs-creator' ), array( 'status' => 501 ) );
		}

		$modeles = self::modeles();

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 240 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- certains hébergeurs l'interdisent.
		}

		// Trois essais au plus. Un délai dépassé ne se retente pas : trois
		// attentes de 90 secondes dépasseraient ce que l'hébergeur accorde.
		$essais  = max( 1, min( 3, count( $modeles ) ) );
		$reponse = null;

		for ( $essai = 0; $essai < $essais; $essai++ ) {
			$constructeur = wp_ai_client_prompt( $demande )
				->using_system_instruction( $consignes )
				->using_temperature( (float) ( $options['temperature'] ?? 0.7 ) );

			$suite = array_slice( $modeles, $essai );

			if ( $suite ) {
				$constructeur = $constructeur->using_model_preference( ...$suite );
			}

			if ( ! empty( $options['schema'] ) ) {
				$constructeur = $constructeur->as_json_response( $options['schema'] );
			}

			$reponse = $constructeur->generate_text();

			if ( ! is_wp_error( $reponse ) || ! self::passagere( $reponse ) ) {
				break;
			}
		}

		return is_wp_error( $reponse ) ? self::erreur( $reponse ) : (string) $reponse;
	}

	/**
	 * Une erreur passagère, qui vaut d'essayer un autre modèle : surcharge ou
	 * quota (des réponses rapides).
	 *
	 * @param WP_Error $erreur Erreur.
	 * @return bool
	 */
	private static function passagere( $erreur ) {
		$donnees = $erreur->get_error_data();
		$statut  = is_array( $donnees ) ? (int) ( $donnees['status'] ?? 0 ) : 0;

		return in_array( $statut, array( 429, 500, 502, 503 ), true )
			|| (bool) preg_match( '/\b(429|500|502|503)\b|high demand|overloaded|unavailable|resource.?exhausted|quota|rate.?limit/i', $erreur->get_error_message() );
	}

	/**
	 * L'erreur, dite en français, avec quoi faire.
	 *
	 * @param WP_Error $erreur Erreur de l'IA.
	 * @return WP_Error
	 */
	private static function erreur( $erreur ) {
		$message = $erreur->get_error_message();

		if ( preg_match( '/\b429\b|quota|resource.?exhausted|rate.?limit/i', $message ) ) {
			$texte  = __( 'Le quota gratuit de l’IA est atteint pour le moment. Réessayez dans une minute (ou demain, si c’est le quota de la journée).', 'blocs-creator' );
			$statut = 429;
		} elseif ( preg_match( '/\b503\b|high demand|overloaded|unavailable/i', $message ) ) {
			$texte  = __( 'Les serveurs de l’IA sont surchargés en ce moment (erreur 503). Réessayez dans quelques minutes.', 'blocs-creator' );
			$statut = 503;
		} elseif ( preg_match( '/timed? ?out|cURL error 28/i', $message ) ) {
			$texte  = __( 'L’IA a mis trop de temps à répondre. Réessayez, ou demandez un texte plus court.', 'blocs-creator' );
			$statut = 504;
		} elseif ( preg_match( '/no models found|no provider|not configured|api key/i', $message ) ) {
			$texte  = __( 'Aucune IA n’est branchée : ajoutez-en une dans Réglages › Connecteurs, avec sa clé, puis réessayez.', 'blocs-creator' );
			$statut = 501;
		} else {
			/* translators: %s : message d'erreur de l'IA (souvent en anglais). */
			$texte  = sprintf( __( 'L’IA n’a pas pu répondre : %s', 'blocs-creator' ), $message );
			$statut = 502;
		}

		return new WP_Error( 'bc_ia', $texte, array( 'status' => $statut ) );
	}

	/* ------------------------------------------------------------------ *
	 * Les routes
	 * ------------------------------------------------------------------ */

	/**
	 * Déclare les routes de l'éditeur.
	 */
	public static function routes() {
		register_rest_route(
			Blocs_Creator_Rest::NAMESPACE,
			'/ia/article',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rediger_article' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
				'args'                => array(
					'notes'      => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'longueur'   => array(
						'type'    => 'string',
						'enum'    => array_keys( self::longueurs() ),
						'default' => 'moyen',
					),
					'titre'      => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'categories' => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'integer' ),
						'default' => array(),
					),
					'post_id'    => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);

		register_rest_route(
			Blocs_Creator_Rest::NAMESPACE,
			'/ia/section',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'remplir_section' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
				'args'                => array(
					'bloc'    => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[a-z0-9-]+/[a-z0-9-]+$',
					),
					'notes'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'valeurs' => array(
						'type'    => 'object',
						'default' => array(),
					),
					'post_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);
	}

	/**
	 * Qui peut demander : qui peut écrire, et modifier la publication ouverte.
	 *
	 * @param WP_REST_Request $requete Requête.
	 * @return bool
	 */
	public static function permission( $requete ) {
		$post_id = (int) $requete['post_id'];

		return current_user_can( 'edit_posts' ) && ( ! $post_id || current_user_can( 'edit_post', $post_id ) );
	}

	/* ------------------------------------------------------------------ *
	 * 1. Rédiger un article
	 * ------------------------------------------------------------------ */

	/**
	 * Les longueurs proposées, en mots.
	 *
	 * @return array<string, int>
	 */
	public static function longueurs() {
		return array(
			'court' => 250,
			'moyen' => 500,
			'long'  => 900,
		);
	}

	/**
	 * Les consignes d'un article.
	 *
	 * @return string
	 */
	private static function consignes_article() {
		$consignes = self::consignes_communes() . "\n\n" . <<<'CONSIGNES'
Tu écris un article (une actualité) pour ce site, à partir des notes qu'on te donne.

Règles :
- N'utilise QUE les informations données dans les notes. N'invente aucun nom, aucune date, aucun horaire, aucun lieu, aucun prix, aucun chiffre, aucun résultat, aucune citation. S'il manque une information, écris sans elle : pas de crochets ni de texte à compléter.
- Structure : une introduction de deux ou trois phrases, puis des parties avec des intertitres courts (## et, si besoin, ###), des paragraphes courts, une liste à puces quand il y a une énumération (horaires, étapes, choses à prévoir…), et une fin qui invite à passer à l'action (venir, s'inscrire, prendre contact) quand c'est naturel.
- Pas de titre de niveau 1 (#) dans le texte, pas d'emoji, pas de hashtag, pas de lien, pas de tableau.
- Le gras (**…**) avec parcimonie, pour une information clé.

Réponds exactement dans ce format, sans rien avant ni après :
TITRE : un titre accrocheur de 40 à 70 caractères, sans point final
CHAPO : une ou deux phrases qui donnent envie de lire (moins de 200 caractères)
=====
le texte de l'article, en Markdown
CONSIGNES;

		/**
		 * Filtre les consignes données à l'IA pour rédiger un article.
		 *
		 * @param string $consignes Consignes.
		 */
		return (string) apply_filters( 'blocs_creator_ia_consignes_article', $consignes );
	}

	/**
	 * Rédige le brouillon d'un article.
	 *
	 * @param WP_REST_Request $requete Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rediger_article( $requete ) {
		if ( ! self::reglages()['articles'] ) {
			return new WP_Error( 'bc_ia', __( 'La rédaction d’articles par l’IA est désactivée.', 'blocs-creator' ), array( 'status' => 403 ) );
		}

		$notes = trim( (string) $requete['notes'] );

		if ( mb_strlen( $notes ) < 20 ) {
			return new WP_Error( 'bc_ia', __( 'Donnez un peu plus de notes à l’IA : quelques phrases sur ce qui s’est passé.', 'blocs-creator' ), array( 'status' => 400 ) );
		}

		$longueurs = self::longueurs();
		$lignes    = array(
			sprintf( 'Longueur visée : environ %d mots.', $longueurs[ (string) $requete['longueur'] ] ?? $longueurs['moyen'] ),
		);

		$defaut = (int) get_option( 'default_category' );
		$noms   = array();

		foreach ( array_map( 'intval', (array) $requete['categories'] ) as $categorie ) {
			$terme = $categorie && $categorie !== $defaut ? get_term( $categorie, 'category' ) : null;

			if ( $terme instanceof WP_Term ) {
				$noms[] = $terme->name;
			}
		}

		if ( $noms ) {
			$lignes[] = 'Catégorie : ' . implode( ', ', $noms ) . '.';
		}

		$titre = trim( (string) $requete['titre'] );

		if ( '' !== $titre ) {
			$lignes[] = 'Titre déjà choisi : « ' . $titre . ' ». Garde ce sujet ; tu peux proposer un meilleur titre.';
		}

		$lignes[] = '';
		$lignes[] = 'Notes :';
		$lignes[] = mb_substr( $notes, 0, 6000 );

		$reponse = self::generer( self::consignes_article(), implode( "\n", $lignes ) );

		if ( is_wp_error( $reponse ) ) {
			return $reponse;
		}

		$brouillon = self::lire_article( $reponse );

		if ( '' === $brouillon['markdown'] ) {
			return new WP_Error( 'bc_ia', __( 'L’IA n’a rien renvoyé d’utilisable. Réessayez.', 'blocs-creator' ), array( 'status' => 502 ) );
		}

		return rest_ensure_response( $brouillon );
	}

	/**
	 * La réponse de l'IA, rangée : titre, chapô, texte en Markdown.
	 *
	 * @param string $texte Réponse brute.
	 * @return array{titre: string, chapo: string, markdown: string}
	 */
	public static function lire_article( $texte ) {
		$texte = trim( str_replace( array( "\r\n", "\r" ), "\n", (string) $texte ) );

		// Toute la réponse dans un bloc de code : on l'en sort.
		$texte = (string) preg_replace( '/^```[a-z]*\n(.*)\n```$/is', '$1', $texte );

		$titre = '';
		$chapo = '';

		if ( preg_match( '/^[ \t*_]*TITRE[ \t*_]*:[ \t]*(.+)$/mi', $texte, $trouve ) ) {
			$titre = $trouve[1];
			$texte = str_replace( $trouve[0], '', $texte );
		}

		if ( preg_match( '/^[ \t*_]*CHAP[OÔ][ \t*_]*:[ \t]*(.+)$/miu', $texte, $trouve ) ) {
			$chapo = $trouve[1];
			$texte = str_replace( $trouve[0], '', $texte );
		}

		$parties = preg_split( '/^[ \t]*={3,}[ \t]*$/m', $texte, 2 );
		$corps   = trim( 2 === count( $parties ) ? $parties[1] : $parties[0] );

		// Pas de H1 : un titre en tête s'en va (le titre est à part), les autres deviennent des intertitres.
		$corps = (string) preg_replace( '/\A#[ \t]+[^\n]*\n*/', '', $corps );
		$corps = (string) preg_replace( '/^#[ \t]+/m', '## ', $corps );

		// Pas de HTML : l'éditeur ne reçoit que du Markdown.
		$corps = trim( (string) preg_replace( '/<\/?[a-z][^>]*>/i', '', $corps ) );

		$nettoyer = static function ( $ligne ) {
			return rtrim( trim( sanitize_text_field( $ligne ), " \t*_\"«»" ) );
		};

		return array(
			'titre'    => rtrim( $nettoyer( $titre ), '.' ),
			'chapo'    => $nettoyer( $chapo ),
			'markdown' => $corps,
		);
	}

	/* ------------------------------------------------------------------ *
	 * 2. Remplir une section
	 * ------------------------------------------------------------------ */

	/**
	 * L'IA peut-elle remplir ce champ ?
	 *
	 * Seulement ce qui est du texte : ni image, ni adresse, ni réglage
	 * d'affichage. Un champ de premier niveau rangé dans la colonne de
	 * droite est un réglage, même s'il est en texte (le message d'un e-mail
	 * de confirmation, une étiquette technique) : il n'est pas proposé.
	 *
	 * @param array $champ      Champ.
	 * @param array $definition Définition du bloc.
	 * @param bool  $sous       Champ d'un répéteur ou d'un groupe.
	 * @return bool
	 */
	private static function remplissable( $champ, $definition, $sous = false ) {
		$oui = in_array( $champ['type'] ?? '', self::REMPLISSABLES, true )
			&& ( $sous || 'panneau' !== ( $champ['emplacement'] ?? 'bloc' ) );

		/**
		 * Filtre ce que l'IA peut remplir dans une section.
		 *
		 * @param bool  $oui        Le champ est-il proposé à l'IA.
		 * @param array $champ      Le champ.
		 * @param array $definition La définition du bloc.
		 */
		return (bool) apply_filters( 'blocs_creator_ia_champ_remplissable', $oui, $champ, $definition );
	}

	/**
	 * Le schéma JSON d'un champ, ou null s'il n'est pas proposé.
	 *
	 * @param array $champ      Champ.
	 * @param array $definition Définition du bloc.
	 * @param bool  $sous       Champ d'un répéteur ou d'un groupe.
	 * @return array|null
	 */
	private static function schema_champ( $champ, $definition, $sous = false ) {
		if ( ! self::remplissable( $champ, $definition, $sous ) ) {
			return null;
		}

		$options     = (array) ( $champ['options'] ?? array() );
		$description = $champ['libelle'] . ( '' !== (string) ( $champ['aide'] ?? '' ) ? ' — ' . $champ['aide'] : '' );

		switch ( $champ['type'] ) {
			case 'texte':
				if ( ! empty( $options['maxlength'] ) ) {
					$description .= sprintf( ' (au plus %d caractères)', (int) $options['maxlength'] );
				}

				return array(
					'type'        => 'string',
					'description' => $description . '. Une ligne, sans mise en forme.',
				);

			case 'texte-long':
				return array(
					'type'        => 'string',
					'description' => $description . '. Texte brut, retours à la ligne permis.',
				);

			case 'texte-riche':
				return array(
					'type'        => 'string',
					'description' => $description . '. HTML léger permis : <strong>, <em>, <br>. Rien d\'autre.',
				);

			case 'nombre':
				return array(
					'type'        => 'number',
					'description' => $description . '. Garde la valeur actuelle si les notes n\'en donnent pas.',
				);

			case 'lien':
				return array(
					'type'        => 'object',
					'description' => $description . ' (le libellé seulement ; l\'adresse ne change pas).',
					'properties'  => array(
						'titre' => array(
							'type'        => 'string',
							'description' => 'Le libellé du lien ou du bouton, court.',
						),
					),
					'required'    => array( 'titre' ),
				);

			case 'groupe':
			case 'repeteur':
				$proprietes = array();

				foreach ( (array) ( $champ['sous_champs'] ?? array() ) as $sous_champ ) {
					$schema = self::schema_champ( $sous_champ, $definition, true );

					if ( null !== $schema ) {
						$proprietes[ $sous_champ['cle'] ] = $schema;
					}
				}

				if ( ! $proprietes ) {
					return null;
				}

				$objet = array(
					'type'       => 'object',
					'properties' => $proprietes,
					'required'   => array_keys( $proprietes ),
				);

				if ( 'groupe' === $champ['type'] ) {
					return array_merge( $objet, array( 'description' => $description ) );
				}

				$tableau = array(
					'type'        => 'array',
					'description' => $description . ( ! empty( $options['libelle_ligne'] ) ? ' — une ligne par « ' . $options['libelle_ligne'] . ' »' : '' ),
					'items'       => $objet,
				);

				if ( ! empty( $options['min_lignes'] ) ) {
					$tableau['minItems'] = (int) $options['min_lignes'];
				}

				if ( ! empty( $options['max_lignes'] ) ) {
					$tableau['maxItems'] = (int) $options['max_lignes'];
				}

				return $tableau;
		}

		return null;
	}

	/**
	 * Le schéma de la réponse attendue pour une section, et les champs qu'il
	 * couvre.
	 *
	 * @param array $definition Définition du bloc.
	 * @return array{schema: array, champs: array<string, array>}
	 */
	public static function schema_section( $definition ) {
		$proprietes = array();
		$champs     = array();

		foreach ( (array) $definition['champs'] as $champ ) {
			$schema = self::schema_champ( $champ, $definition );

			if ( null === $schema ) {
				continue;
			}

			$proprietes[ $champ['cle'] ] = $schema;
			$champs[ $champ['cle'] ]     = $champ;
		}

		return array(
			'schema' => array(
				'type'       => 'object',
				'properties' => $proprietes,
				'required'   => array_keys( $proprietes ),
			),
			'champs' => $champs,
		);
	}

	/**
	 * Les valeurs actuelles des champs qu'on propose à l'IA, sous la forme du
	 * schéma : elle repart de là.
	 *
	 * @param array $champ  Champ.
	 * @param mixed $valeur Valeur actuelle.
	 * @param array $schema Schéma du champ.
	 * @return mixed
	 */
	private static function valeur_pour_ia( $champ, $valeur, $schema ) {
		switch ( $champ['type'] ) {
			case 'lien':
				return array( 'titre' => (string) ( ( (array) $valeur )['titre'] ?? '' ) );

			case 'groupe':
				$objet = array();

				foreach ( (array) ( $champ['sous_champs'] ?? array() ) as $sous ) {
					if ( isset( $schema['properties'][ $sous['cle'] ] ) ) {
						$objet[ $sous['cle'] ] = self::valeur_pour_ia( $sous, ( (array) $valeur )[ $sous['cle'] ] ?? null, $schema['properties'][ $sous['cle'] ] );
					}
				}

				return $objet;

			case 'repeteur':
				$lignes = array();

				foreach ( (array) $valeur as $ligne ) {
					$lignes[] = self::valeur_pour_ia( array_merge( $champ, array( 'type' => 'groupe' ) ), $ligne, $schema['items'] );
				}

				return $lignes;

			case 'nombre':
				return is_numeric( $valeur ) ? (float) $valeur : 0;

			default:
				return is_scalar( $valeur ) ? (string) $valeur : '';
		}
	}

	/**
	 * Fond la proposition de l'IA dans la valeur actuelle d'un champ.
	 *
	 * L'IA ne voit que le texte : une image d'une ligne de répéteur, l'adresse
	 * d'un lien, un réglage d'une carte restent ce qu'ils étaient.
	 *
	 * @param array $champ      Champ.
	 * @param mixed $actuelle   Valeur actuelle.
	 * @param mixed $proposee   Proposition de l'IA.
	 * @param array $schema     Schéma du champ.
	 * @return mixed
	 */
	private static function fondre( $champ, $actuelle, $proposee, $schema ) {
		switch ( $champ['type'] ) {
			case 'lien':
				$lien          = is_array( $actuelle ) ? $actuelle : (array) Blocs_Creator_Champs::defaut( $champ );
				$lien['titre'] = (string) ( ( (array) $proposee )['titre'] ?? ( $lien['titre'] ?? '' ) );

				return $lien;

			case 'groupe':
				$objet = is_array( $actuelle ) ? $actuelle : Blocs_Creator_Champs::defauts_sous_champs( $champ );

				foreach ( (array) ( $champ['sous_champs'] ?? array() ) as $sous ) {
					$cle = $sous['cle'];

					if ( isset( $schema['properties'][ $cle ] ) && array_key_exists( $cle, (array) $proposee ) ) {
						$objet[ $cle ] = self::fondre( $sous, $objet[ $cle ] ?? null, $proposee[ $cle ], $schema['properties'][ $cle ] );
					}
				}

				return $objet;

			case 'repeteur':
				$actuelles = array_values( is_array( $actuelle ) ? $actuelle : array() );
				$lignes    = array();
				$groupe    = array_merge( $champ, array( 'type' => 'groupe' ) );

				foreach ( array_values( (array) $proposee ) as $rang => $ligne ) {
					$lignes[] = self::fondre( $groupe, $actuelles[ $rang ] ?? null, (array) $ligne, $schema['items'] );
				}

				return $lignes;

			case 'nombre':
				return is_numeric( $proposee ) ? (float) $proposee : $actuelle;

			default:
				return is_scalar( $proposee ) ? (string) $proposee : $actuelle;
		}
	}

	/**
	 * Une valeur, en une ligne lisible : pour la fenêtre de relecture.
	 *
	 * @param array $champ  Champ.
	 * @param mixed $valeur Valeur.
	 * @return string
	 */
	private static function lisible( $champ, $valeur ) {
		switch ( $champ['type'] ) {
			case 'lien':
				return (string) ( ( (array) $valeur )['titre'] ?? '' );

			case 'groupe':
				$morceaux = array();

				foreach ( (array) ( $champ['sous_champs'] ?? array() ) as $sous ) {
					if ( in_array( $sous['type'], self::REMPLISSABLES, true ) && isset( ( (array) $valeur )[ $sous['cle'] ] ) ) {
						$texte = self::lisible( $sous, $valeur[ $sous['cle'] ] );

						if ( '' !== $texte ) {
							$morceaux[] = $texte;
						}
					}
				}

				return implode( ' · ', $morceaux );

			case 'nombre':
				return is_numeric( $valeur ) ? (string) ( 0.0 === fmod( (float) $valeur, 1.0 ) ? (int) $valeur : (float) $valeur ) : '';

			default:
				return trim( wp_strip_all_tags( str_replace( array( '<br>', '<br/>', '<br />' ), ' ', (string) $valeur ) ) );
		}
	}

	/**
	 * Les consignes d'une section.
	 *
	 * @param array $definition Définition du bloc.
	 * @return string
	 */
	private static function consignes_section( $definition ) {
		$consignes = self::consignes_communes() . "\n\n" . sprintf(
			<<<'CONSIGNES'
Tu remplis une section d'une page web : le bloc « %1$s »%2$s. On te donne les notes de la rédaction et les valeurs actuelles de ses champs ; tu proposes le texte de chaque champ, en JSON, exactement dans la forme demandée.

Règles :
- Pars des notes. Ce qu'elles ne disent pas, reprends-le des valeurs actuelles si elles conviennent, sinon écris un texte simple et général.
- N'invente aucun nom, aucune date, aucun horaire, aucun lieu, aucun prix, aucun chiffre, aucune citation qui ne soit ni dans les notes ni dans les valeurs actuelles.
- Respecte la longueur et le rôle de chaque champ : un titre est court, un surtitre tient en quelques mots, un libellé de bouton en deux à quatre mots.
- Pour une liste de lignes (cartes, questions, étapes…), propose le nombre de lignes que les notes demandent ; sans indication, garde le nombre actuel.
- Pas d'emoji, pas de hashtag, pas de lien.
CONSIGNES,
			$definition['titre'],
			'' !== (string) $definition['description'] ? ' (' . $definition['description'] . ')' : ''
		);

		/**
		 * Filtre les consignes données à l'IA pour remplir une section.
		 *
		 * @param string $consignes  Consignes.
		 * @param array  $definition Définition du bloc.
		 */
		return (string) apply_filters( 'blocs_creator_ia_consignes_section', $consignes, $definition );
	}

	/**
	 * Lit une réponse JSON, même enveloppée dans un bloc de code.
	 *
	 * @param string $texte Réponse brute.
	 * @return array|null
	 */
	private static function lire_json( $texte ) {
		$texte = trim( (string) $texte );
		$texte = (string) preg_replace( '/^```[a-z]*\s*(.*?)\s*```$/is', '$1', $texte );
		$json  = json_decode( $texte, true );

		if ( ! is_array( $json ) && preg_match( '/\{.*\}/s', $texte, $trouve ) ) {
			$json = json_decode( $trouve[0], true );
		}

		return is_array( $json ) ? $json : null;
	}

	/**
	 * Propose le contenu d'une section.
	 *
	 * @param WP_REST_Request $requete Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function remplir_section( $requete ) {
		if ( ! self::reglages()['sections'] ) {
			return new WP_Error( 'bc_ia', __( 'Le remplissage des sections par l’IA est désactivé.', 'blocs-creator' ), array( 'status' => 403 ) );
		}

		$definition = blocs_creator()->registre->definition( (string) $requete['bloc'] );

		if ( null === $definition ) {
			return new WP_Error( 'bc_ia', __( 'Ce bloc n’a pas été créé avec Blocs Creator : l’IA ne connaît pas ses champs.', 'blocs-creator' ), array( 'status' => 404 ) );
		}

		$plan = self::schema_section( $definition );

		if ( ! $plan['champs'] ) {
			return new WP_Error( 'bc_ia', __( 'Ce bloc n’a aucun champ de texte que l’IA pourrait remplir.', 'blocs-creator' ), array( 'status' => 400 ) );
		}

		$notes   = trim( mb_substr( (string) $requete['notes'], 0, 6000 ) );
		$valeurs = (array) $requete['valeurs'];
		$depart  = array();

		foreach ( $plan['champs'] as $cle => $champ ) {
			$actuelle       = array_key_exists( $cle, $valeurs ) ? $valeurs[ $cle ] : Blocs_Creator_Champs::defaut( $champ );
			$depart[ $cle ] = self::valeur_pour_ia( $champ, $actuelle, $plan['schema']['properties'][ $cle ] );
		}

		$demande = implode(
			"\n",
			array(
				'Notes de la rédaction :',
				'' !== $notes ? $notes : '(aucune : améliore les valeurs actuelles, sans rien inventer)',
				'',
				'Valeurs actuelles :',
				(string) wp_json_encode( $depart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ),
				'',
				'Schéma de la réponse :',
				(string) wp_json_encode( $plan['schema'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			)
		);

		$reponse = self::generer(
			self::consignes_section( $definition ),
			$demande,
			array(
				'temperature' => 0.6,
				'schema'      => $plan['schema'],
			)
		);

		if ( is_wp_error( $reponse ) ) {
			return $reponse;
		}

		$proposees = self::lire_json( $reponse );

		if ( null === $proposees ) {
			return new WP_Error( 'bc_ia', __( 'L’IA n’a rien renvoyé d’utilisable. Réessayez.', 'blocs-creator' ), array( 'status' => 502 ) );
		}

		$nouvelles = array();
		$apercu    = array();

		foreach ( $plan['champs'] as $cle => $champ ) {
			if ( ! array_key_exists( $cle, $proposees ) ) {
				continue;
			}

			$actuelle = array_key_exists( $cle, $valeurs ) ? $valeurs[ $cle ] : Blocs_Creator_Champs::defaut( $champ );
			$fondue   = self::fondre( $champ, $actuelle, $proposees[ $cle ], $plan['schema']['properties'][ $cle ] );
			$propre   = Blocs_Creator_Champs::assainir_valeur( $champ, $fondue );

			$nouvelles[ $cle ] = $propre;

			if ( 'repeteur' === $champ['type'] ) {
				$groupe = array_merge( $champ, array( 'type' => 'groupe' ) );
				$lignes = array();

				foreach ( (array) $propre as $ligne ) {
					$lignes[] = self::lisible( $groupe, $ligne );
				}

				$apercu[] = array(
					'libelle' => $champ['libelle'],
					'lignes'  => $lignes,
				);
			} else {
				$apercu[] = array(
					'libelle' => $champ['libelle'],
					'texte'   => self::lisible( $champ, $propre ),
				);
			}
		}

		if ( ! $nouvelles ) {
			return new WP_Error( 'bc_ia', __( 'L’IA n’a rien renvoyé d’utilisable. Réessayez.', 'blocs-creator' ), array( 'status' => 502 ) );
		}

		return rest_ensure_response(
			array(
				'valeurs' => $nouvelles,
				'apercu'  => $apercu,
			)
		);
	}

	/* ------------------------------------------------------------------ *
	 * L'éditeur
	 * ------------------------------------------------------------------ */

	/**
	 * Les panneaux de l'éditeur : « Rédiger avec l'IA » sur les articles,
	 * « Remplir avec l'IA » sur les blocs créés ici.
	 */
	public static function assets_editeur() {
		if ( ! self::disponible() ) {
			return;
		}

		$reglages = self::reglages();
		$ecran    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$type     = $ecran ? (string) $ecran->post_type : '';
		$chargee  = false;

		if ( $reglages['articles'] && in_array( $type, (array) $reglages['types'], true ) ) {
			wp_enqueue_script(
				'blocs-creator-ia-article',
				BLOCS_CREATOR_URL . 'assets/js/ia-article.js',
				array( 'wp-api-fetch', 'wp-blocks', 'wp-components', 'wp-data', 'wp-editor', 'wp-element', 'wp-i18n', 'wp-notices', 'wp-plugins' ),
				Blocs_Creator_Plugin::version_fichier( 'assets/js/ia-article.js' ),
				true
			);
			wp_set_script_translations( 'blocs-creator-ia-article', 'blocs-creator', BLOCS_CREATOR_DIR . 'languages' );
			wp_add_inline_script(
				'blocs-creator-ia-article',
				'window.blocsCreatorIaArticle = ' . wp_json_encode( array( 'types' => array_values( (array) $reglages['types'] ) ) ) . ';',
				'before'
			);
			$chargee = true;
		}

		if ( $reglages['sections'] ) {
			$sections = array();

			foreach ( blocs_creator()->registre->generes() as $nom => $definition ) {
				if ( self::schema_section( $definition )['champs'] ) {
					$sections[] = $nom;
				}
			}

			if ( $sections ) {
				wp_enqueue_script(
					'blocs-creator-ia-section',
					BLOCS_CREATOR_URL . 'assets/js/ia-section.js',
					array( 'wp-api-fetch', 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-data', 'wp-element', 'wp-hooks', 'wp-i18n' ),
					Blocs_Creator_Plugin::version_fichier( 'assets/js/ia-section.js' ),
					true
				);
				wp_set_script_translations( 'blocs-creator-ia-section', 'blocs-creator', BLOCS_CREATOR_DIR . 'languages' );
				wp_add_inline_script(
					'blocs-creator-ia-section',
					'window.blocsCreatorIaSection = ' . wp_json_encode( array( 'blocs' => $sections ) ) . ';',
					'before'
				);
				$chargee = true;
			}
		}

		if ( $chargee ) {
			wp_enqueue_style(
				'blocs-creator-ia',
				BLOCS_CREATOR_URL . 'assets/css/ia.css',
				array(),
				Blocs_Creator_Plugin::version_fichier( 'assets/css/ia.css' )
			);
		}
	}
}
