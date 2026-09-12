<?php
/**
 * L'administration : menu, écrans, actions.
 *
 * Un seul menu, quatre entrées, et la règle qui les ordonne : on regarde
 * d'abord ce qui existe (Tous les blocs), on en ajoute (Ajouter), on règle ce
 * qui vaut pour tous (Réglages), on déplace d'un site à l'autre (Outils).
 * L'aide vient en dernier parce qu'elle ne se lit qu'une fois.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Écrans d'administration.
 */
class BC_Admin {

	/**
	 * Identifiant de la page principale.
	 */
	const PAGE = 'blocs-creator';

	/**
	 * Capacité requise.
	 */
	const CAP = 'manage_options';

	/**
	 * L'écran d'édition d'une définition.
	 *
	 * @var BC_Ecran_Definition
	 */
	private $ecran;

	/**
	 * Branche les hooks.
	 */
	public function demarrer() {
		$this->ecran = new BC_Ecran_Definition();
		$this->ecran->demarrer();

		( new BC_Outils() )->demarrer();

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'parent_file', array( $this, 'menu_ouvert' ) );
		add_filter( 'submenu_file', array( $this, 'sous_menu_ouvert' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BLOCS_CREATOR_FICHIER ), array( $this, 'liens_plugin' ) );
		add_action( 'admin_post_bc_creer_gabarit', array( $this, 'action_creer_gabarit' ) );
		add_action( 'admin_post_bc_dupliquer', array( $this, 'action_dupliquer' ) );
		add_action( 'admin_post_bc_reprendre', array( $this, 'action_reprendre' ) );
		add_action( 'admin_post_bc_reprendre_tout', array( $this, 'action_reprendre_tout' ) );
		add_action( 'admin_post_bc_rendre_au_code', array( $this, 'action_rendre_au_code' ) );
		add_action( 'admin_post_bc_rendre_tout_au_code', array( $this, 'action_rendre_tout_au_code' ) );
		add_action( 'admin_post_bc_reglages', array( $this, 'action_reglages' ) );
		add_action( 'admin_post_bc_supprimer', array( $this, 'action_supprimer' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Menu
	 * ------------------------------------------------------------------ */

	/**
	 * Déclare le menu et ses pages.
	 */
	public function menu() {
		add_menu_page(
			__( 'Blocs Creator', 'blocs-creator' ),
			__( 'Blocs Creator', 'blocs-creator' ),
			self::CAP,
			self::PAGE,
			array( $this, 'page_liste' ),
			'dashicons-layout',
			58
		);

		add_submenu_page(
			self::PAGE,
			__( 'Tous les blocs', 'blocs-creator' ),
			__( 'Tous les blocs', 'blocs-creator' ),
			self::CAP,
			self::PAGE,
			array( $this, 'page_liste' )
		);

		add_submenu_page(
			self::PAGE,
			__( 'Ajouter un bloc', 'blocs-creator' ),
			__( 'Ajouter un bloc', 'blocs-creator' ),
			self::CAP,
			'post-new.php?post_type=' . BC_Definition::TYPE
		);

		add_submenu_page(
			self::PAGE,
			__( 'Réglages', 'blocs-creator' ),
			__( 'Réglages', 'blocs-creator' ),
			self::CAP,
			self::PAGE . '-reglages',
			array( $this, 'page_reglages' )
		);

		add_submenu_page(
			self::PAGE,
			__( 'Outils', 'blocs-creator' ),
			__( 'Outils', 'blocs-creator' ),
			self::CAP,
			self::PAGE . '-outils',
			array( 'BC_Outils', 'page' )
		);

		add_submenu_page(
			self::PAGE,
			__( 'Écrire un gabarit', 'blocs-creator' ),
			__( 'Écrire un gabarit', 'blocs-creator' ),
			self::CAP,
			self::PAGE . '-aide',
			array( $this, 'page_aide' )
		);
	}

	/**
	 * Garde le menu ouvert sur les écrans du type de contenu.
	 *
	 * @param string $parent Fichier parent courant.
	 * @return string
	 */
	public function menu_ouvert( $parent ) {
		$ecran = get_current_screen();

		if ( $ecran && BC_Definition::TYPE === $ecran->post_type ) {
			return self::PAGE;
		}

		return $parent;
	}

	/**
	 * Souligne la bonne entrée du sous-menu.
	 *
	 * @param string $sous_menu Entrée courante.
	 * @return string
	 */
	public function sous_menu_ouvert( $sous_menu ) {
		$ecran = get_current_screen();

		if ( $ecran && BC_Definition::TYPE === $ecran->post_type ) {
			return 'post-new' === $ecran->base
				? 'post-new.php?post_type=' . BC_Definition::TYPE
				: self::PAGE;
		}

		return $sous_menu;
	}

	/**
	 * Ajoute les liens utiles sous le plugin, dans la liste des extensions.
	 *
	 * @param array $liens Liens existants.
	 * @return array
	 */
	public function liens_plugin( $liens ) {
		array_unshift(
			$liens,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ),
				esc_html__( 'Mes blocs', 'blocs-creator' )
			),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=' . self::PAGE . '-reglages' ) ),
				esc_html__( 'Réglages', 'blocs-creator' )
			)
		);

		return $liens;
	}

	/* ------------------------------------------------------------------ *
	 * Assets
	 * ------------------------------------------------------------------ */

	/**
	 * Charge le style et le script de l'administration.
	 *
	 * @param string $hook Écran courant.
	 */
	public function assets( $hook ) {
		$ecran     = get_current_screen();
		$est_nous  = str_contains( (string) $hook, self::PAGE );
		$est_bloc  = $ecran && BC_Definition::TYPE === $ecran->post_type;

		if ( ! $est_nous && ! $est_bloc ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'blocs-creator-admin',
			BLOCS_CREATOR_URL . 'admin/css/admin.css',
			array(),
			$this->version( 'admin/css/admin.css' )
		);

		// La fiche d'un bloc et le diagnostic des réglages se copient du même
		// bouton : un seul script pour les deux écrans.
		wp_enqueue_script(
			'blocs-creator-copier',
			BLOCS_CREATOR_URL . 'admin/js/copier.js',
			array(),
			$this->version( 'admin/js/copier.js' ),
			true
		);

		if ( str_contains( (string) $hook, self::PAGE . '-reglages' ) ) {
			wp_enqueue_script(
				'blocs-creator-reglages',
				BLOCS_CREATOR_URL . 'admin/js/reglages.js',
				array(),
				$this->version( 'admin/js/reglages.js' ),
				true
			);

			BC_Animations::assets_admin();
		}

		if ( ! $est_bloc ) {
			return;
		}

		// L'aperçu d'apparition emprunte la feuille du site : ce qu'on voit en
		// choisissant est ce que le visiteur verra.
		BC_Animations::assets_admin();

		wp_enqueue_script(
			'blocs-creator-constructeur',
			BLOCS_CREATOR_URL . 'admin/js/constructeur.js',
			array( 'wp-i18n', 'wp-dom-ready' ),
			$this->version( 'admin/js/constructeur.js' ),
			true
		);

		wp_set_script_translations( 'blocs-creator-constructeur', 'blocs-creator', BLOCS_CREATOR_DIR . 'languages' );

		wp_add_inline_script(
			'blocs-creator-constructeur',
			'window.blocsCreatorAdmin = ' . wp_json_encode( $this->donnees_constructeur() ) . ';',
			'before'
		);
	}

	/**
	 * Retourne une version d'asset basée sur la date du fichier.
	 *
	 * @param string $chemin Chemin relatif à la racine du plugin.
	 * @return string
	 */
	private function version( $chemin ) {
		$fichier = BLOCS_CREATOR_DIR . ltrim( $chemin, '/' );

		return file_exists( $fichier ) ? (string) filemtime( $fichier ) : BLOCS_CREATOR_VERSION;
	}

	/**
	 * Rassemble ce dont le constructeur de champs a besoin.
	 *
	 * @return array
	 */
	private function donnees_constructeur() {
		$types = array();

		foreach ( BC_Champs::catalogue() as $slug => $def ) {
			$types[] = array(
				'type'        => $slug,
				'libelle'     => $def['libelle'],
				'famille'     => $def['famille'],
				'description' => $def['description'],
				'reglages'    => (array) ( $def['reglages'] ?? array() ),
				'sousChamps'  => ! empty( $def['sous_champs'] ),
				'porteValeur' => $def['porte_valeur'] ?? true,
			);
		}

		$types_contenu = array();

		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			if ( in_array( $type->name, array( 'attachment', BC_Definition::TYPE ), true ) ) {
				continue;
			}

			$types_contenu[] = array(
				'value' => $type->name,
				'label' => $type->labels->name,
			);
		}

		$taxonomies = array();

		foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $taxonomie ) {
			$taxonomies[] = array(
				'value' => $taxonomie->name,
				'label' => $taxonomie->labels->name,
			);
		}

		$tailles = array();

		foreach ( array_merge( get_intermediate_image_sizes(), array( 'full' ) ) as $taille ) {
			$tailles[] = array(
				'value' => $taille,
				'label' => $taille,
			);
		}

		return array(
			'types'        => $types,
			'familles'     => BC_Champs::familles(),
			'typesContenu' => $types_contenu,
			'taxonomies'   => $taxonomies,
			'tailles'      => $tailles,
			'dashicons'    => BC_Reglages::dashicons(),
		);
	}

	/* ------------------------------------------------------------------ *
	 * Pages
	 * ------------------------------------------------------------------ */

	/**
	 * Affiche l'écran « Tous les blocs ».
	 */
	public function page_liste() {
		if ( $this->page_reprise() ) {
			return;
		}

		// WP_List_Table n'est déclarée qu'une fois wp-admin chargé : notre
		// tableau, qui en hérite, ne peut donc pas l'être plus tôt.
		require_once BLOCS_CREATOR_DIR . 'admin/class-bc-liste-table.php';

		$table = new BC_Liste_Table();
		$table->prepare_items();

		include BLOCS_CREATOR_DIR . 'admin/vues/liste.php';
	}

	/**
	 * Affiche l'écran des réglages.
	 */
	public function page_reglages() {
		$reglages = blocs_creator()->reglages;

		include BLOCS_CREATOR_DIR . 'admin/vues/reglages.php';
	}

	/**
	 * Affiche l'aide à l'écriture d'un gabarit.
	 */
	public function page_aide() {
		include BLOCS_CREATOR_DIR . 'admin/vues/aide.php';
	}

	/**
	 * Affiche l'écran de confirmation d'une reprise, s'il est demandé.
	 *
	 * Il vit sous l'entrée « Tous les blocs » plutôt que dans une page de menu
	 * à lui : c'est de là qu'on y arrive, et c'est là qu'on revient.
	 *
	 * @return bool Vrai si l'écran a été affiché.
	 */
	private function page_reprise() {
		if ( empty( $_GET['bc_reprendre'] ) ) {
			return false;
		}

		$nom = sanitize_text_field( rawurldecode( wp_unslash( $_GET['bc_reprendre'] ) ) );

		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'bc_reprendre_' . $nom ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'blocs-creator' ), 403 );
		}

		$code = BC_Adoption::bloc_code( $nom );

		if ( null === $code ) {
			wp_die( esc_html__( 'Ce bloc codé est introuvable.', 'blocs-creator' ), 404 );
		}

		$definition = BC_Adoption::traduire( $code );

		include BLOCS_CREATOR_DIR . 'admin/vues/reprendre.php';

		return true;
	}

	/* ------------------------------------------------------------------ *
	 * Actions
	 * ------------------------------------------------------------------ */

	/**
	 * Crée le fichier de gabarit d'un bloc.
	 */
	public function action_creer_gabarit() {
		$post_id = isset( $_GET['bloc'] ) ? absint( $_GET['bloc'] ) : 0;

		if ( ! current_user_can( self::CAP ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'bc_creer_gabarit_' . $post_id ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'blocs-creator' ), 403 );
		}

		$definition = BC_Definition::charger( $post_id );

		if ( null === $definition ) {
			$this->rediriger( $post_id, 'erreur', __( 'Ce bloc est introuvable.', 'blocs-creator' ) );
		}

		$resultat = BC_Gabarits::creer( $definition );

		if ( is_wp_error( $resultat ) ) {
			$this->rediriger( $post_id, 'erreur', $resultat->get_error_message() );
		}

		$this->rediriger(
			$post_id,
			'succes',
			sprintf(
				/* translators: %s: chemin du fichier créé. */
				__( 'Gabarit créé : %s', 'blocs-creator' ),
				BC_Gabarits::chemin_court( $resultat )
			)
		);
	}

	/**
	 * Duplique un bloc.
	 */
	public function action_dupliquer() {
		$post_id = isset( $_GET['bloc'] ) ? absint( $_GET['bloc'] ) : 0;

		if ( ! current_user_can( self::CAP ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'bc_dupliquer_' . $post_id ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'blocs-creator' ), 403 );
		}

		$copie = BC_Definition::dupliquer( $post_id );

		if ( is_wp_error( $copie ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'       => self::PAGE,
						'bc_message' => 'erreur',
						'bc_texte'   => rawurlencode( $copie->get_error_message() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		wp_safe_redirect( admin_url( 'post.php?post=' . (int) $copie . '&action=edit' ) );
		exit;
	}

	/**
	 * Reprend la main sur un bloc codé.
	 */
	public function action_reprendre() {
		$nom = isset( $_POST['bloc'] ) ? sanitize_text_field( wp_unslash( $_POST['bloc'] ) ) : '';

		if ( ! current_user_can( self::CAP ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'bc_reprendre_' . $nom ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'blocs-creator' ), 403 );
		}

		$resultat = BC_Adoption::reprendre( $nom );

		if ( is_wp_error( $resultat ) ) {
			$this->retour_liste( 'erreur', $resultat->get_error_message() );
		}

		$texte = sprintf(
			/* translators: %s: nom complet du bloc. */
			__( '« %s » est repris : ses champs se modifient maintenant ici.', 'blocs-creator' ),
			$nom
		);

		if ( ! empty( $resultat['avertissements'] ) ) {
			$texte .= ' ' . implode( ' ', $resultat['avertissements'] );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'post'       => (int) $resultat['id'],
					'action'     => 'edit',
					'bc_message' => 'succes',
					'bc_texte'   => rawurlencode( $texte ),
				),
				admin_url( 'post.php' )
			)
		);
		exit;
	}

	/**
	 * Reprend d'un coup tous les blocs codés qui restent.
	 */
	public function action_reprendre_tout() {
		if ( ! current_user_can( self::CAP ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'bc_reprendre_tout' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'blocs-creator' ), 403 );
		}

		$resultat = BC_Adoption::reprendre_tout();

		if ( empty( $resultat['repris'] ) && empty( $resultat['echecs'] ) ) {
			$this->retour_liste( 'erreur', __( 'Il n\'y avait aucun bloc codé à reprendre.', 'blocs-creator' ) );
		}

		$texte = sprintf(
			/* translators: %d: nombre de blocs. */
			_n( '%d bloc codé repris : il se modifie maintenant ici.', '%d blocs codés repris : ils se modifient maintenant ici.', count( $resultat['repris'] ), 'blocs-creator' ),
			count( $resultat['repris'] )
		);

		if ( ! empty( $resultat['echecs'] ) ) {
			$texte .= ' ' . sprintf(
				/* translators: %s: liste de blocs. */
				__( 'Laissés de côté : %s.', 'blocs-creator' ),
				implode( ' ; ', $resultat['echecs'] )
			);
		}

		if ( ! empty( $resultat['avertissements'] ) ) {
			$texte .= ' ' . implode( ' ', $resultat['avertissements'] );
		}

		$this->retour_liste( empty( $resultat['echecs'] ) ? 'succes' : 'erreur', $texte );
	}

	/**
	 * Rend un bloc repris à son code.
	 */
	public function action_rendre_au_code() {
		$post_id = isset( $_GET['bloc'] ) ? absint( $_GET['bloc'] ) : 0;

		if ( ! current_user_can( self::CAP ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'bc_rendre_au_code_' . $post_id ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'blocs-creator' ), 403 );
		}

		$resultat = BC_Adoption::rendre_au_code( $post_id );

		if ( is_wp_error( $resultat ) ) {
			$this->retour_liste( 'erreur', $resultat->get_error_message() );
		}

		$this->retour_liste(
			'succes',
			__( 'Le bloc est rendu à son code : c\'est de nouveau son dossier qui le sert.', 'blocs-creator' )
		);
	}

	/**
	 * Rend d'un coup au code toutes les définitions qui en viennent.
	 *
	 * Le pendant de « Tout reprendre en main ». Il fallait le pendant : une
	 * reprise qu'on ne sait défaire qu'un bloc à la fois est une reprise qu'on
	 * hésite à faire.
	 */
	public function action_rendre_tout_au_code() {
		if ( ! current_user_can( self::CAP ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'bc_rendre_tout_au_code' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'blocs-creator' ), 403 );
		}

		$resultat = BC_Adoption::rendre_tout_au_code();

		if ( empty( $resultat['rendus'] ) && empty( $resultat['echecs'] ) ) {
			$this->retour_liste( 'erreur', __( 'Aucun bloc repris à rendre au code.', 'blocs-creator' ) );
		}

		$texte = sprintf(
			/* translators: %d: nombre de blocs. */
			_n(
				'%d bloc rendu à son code : il ne se liste plus ici, et c\'est de nouveau son dossier qui le sert.',
				'%d blocs rendus à leur code : ils ne se listent plus ici, et ce sont de nouveau leurs dossiers qui les servent.',
				count( $resultat['rendus'] ),
				'blocs-creator'
			),
			count( $resultat['rendus'] )
		);

		if ( ! empty( $resultat['echecs'] ) ) {
			$texte .= ' ' . sprintf(
				/* translators: %s: liste de blocs. */
				__( 'Laissés de côté : %s.', 'blocs-creator' ),
				implode( ' ; ', $resultat['echecs'] )
			);
		}

		$this->retour_liste( empty( $resultat['echecs'] ) ? 'succes' : 'erreur', $texte );
	}

	/**
	 * Supprime une définition, pour de bon.
	 *
	 * La corbeille de WordPress ne sert à rien ici : l'écran « Tous les blocs »
	 * ne la montre pas, et une définition qui y tombe n'est plus joignable. On
	 * supprime donc franchement, après une confirmation qui dit ce que ça
	 * coûte.
	 *
	 * C'est aussi la sortie de secours d'une reprise dont le dossier de code a
	 * disparu : « Rendre au code » refuse, à juste titre, de rendre la main à
	 * un bloc qui n'existe plus — mais il n'y avait alors plus aucun moyen de
	 * retirer la définition de la liste.
	 */
	public function action_supprimer() {
		$post_id = isset( $_GET['bloc'] ) ? absint( $_GET['bloc'] ) : 0;

		if ( ! current_user_can( self::CAP ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'bc_supprimer_' . $post_id ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'blocs-creator' ), 403 );
		}

		$definition = BC_Definition::charger( $post_id );

		if ( null === $definition ) {
			$this->retour_liste( 'erreur', __( 'Ce bloc est introuvable.', 'blocs-creator' ) );
		}

		$nom   = BC_Definition::nom( $definition );
		$usage = BC_Usage::compter( $nom );

		wp_delete_post( $post_id, true );

		BC_Usage::vider_cache();

		$texte = sprintf(
			/* translators: %s: nom complet du bloc. */
			__( '« %s » est supprimé.', 'blocs-creator' ),
			$nom
		);

		if ( $usage > 0 ) {
			$texte .= ' ' . sprintf(
				/* translators: %d: nombre de publications. */
				_n(
					'%d publication s\'en servait : elle affichera un bloc vide tant que vous ne l\'aurez pas remplacé.',
					'%d publications s\'en servaient : elles afficheront un bloc vide tant que vous ne l\'aurez pas remplacé.',
					$usage,
					'blocs-creator'
				),
				$usage
			);
		}

		$this->retour_liste( $usage > 0 ? 'erreur' : 'succes', $texte );
	}

	/**
	 * Enregistre les réglages.
	 *
	 * Voir BC_Reglages::enregistrer_depuis_formulaire() pour la raison d'être
	 * de ce chemin : on ne passe plus par `options.php`, qui pouvait renvoyer
	 * l'écran inchangé et sans un mot.
	 */
	public function action_reglages() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas le droit de modifier ces réglages.', 'blocs-creator' ), 403 );
		}

		check_admin_referer( 'bc_reglages' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- tout est assaini par les deux assainisseurs appelés ci-dessous.
		$brut = wp_unslash( $_POST );

		$resultat = blocs_creator()->reglages->enregistrer_depuis_formulaire(
			(array) ( $brut[ BC_Reglages::OPTION ] ?? array() )
		);

		BC_Animations::enregistrer_depuis_formulaire(
			(array) ( $brut[ BC_Animations::OPTION ] ?? array() )
		);

		$onglet = isset( $_POST['bc_onglet'] ) ? sanitize_key( wp_unslash( $_POST['bc_onglet'] ) ) : 'general';

		if ( $resultat['ecrit'] ) {
			$type  = 'succes';
			$texte = __( 'Réglages enregistrés.', 'blocs-creator' );
		} else {
			$type  = 'erreur';
			$texte = sprintf(
				/* translators: %s: liste de noms de réglages. */
				__( 'Ces réglages n\'ont pas pu être écrits en base : %s. La base est peut-être en lecture seule, ou une extension intercepte l\'écriture.', 'blocs-creator' ),
				implode( ', ', $resultat['refuses'] )
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE . '-reglages',
					'bc_onglet'  => $onglet,
					'bc_message' => $type,
					'bc_texte'   => rawurlencode( $texte ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Renvoie vers l'écran de liste avec un message.
	 *
	 * @param string $type  `succes` ou `erreur`.
	 * @param string $texte Message.
	 */
	private function retour_liste( $type, $texte ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE,
					'bc_message' => $type,
					'bc_texte'   => rawurlencode( $texte ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Renvoie vers l'écran d'édition d'un bloc avec un message.
	 *
	 * @param int    $post_id Bloc concerné.
	 * @param string $type    `succes` ou `erreur`.
	 * @param string $texte   Message.
	 */
	private function rediriger( $post_id, $type, $texte ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'post'       => $post_id,
					'action'     => 'edit',
					'bc_message' => $type,
					'bc_texte'   => rawurlencode( $texte ),
				),
				admin_url( 'post.php' )
			)
		);
		exit;
	}

	/**
	 * Affiche les messages posés par les actions.
	 */
	public function notices() {
		if ( empty( $_GET['bc_message'] ) ) {
			return;
		}

		$type  = 'succes' === $_GET['bc_message'] ? 'success' : 'error';
		$texte = isset( $_GET['bc_texte'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['bc_texte'] ) ) ) : '';

		if ( '' === $texte ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $texte )
		);
	}
}
