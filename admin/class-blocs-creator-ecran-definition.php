<?php
/**
 * L'écran d'édition d'un bloc.
 *
 * WordPress fournit déjà un écran d'édition solide : un titre, une colonne
 * latérale, un bouton Publier, la corbeille, les révisions. On s'en sert plutôt
 * que d'en réécrire un — le bloc est un type de contenu, ses champs sont des
 * métaboxes. Ce qui est spécifique tient dans une seule : le constructeur.
 *
 * L'enregistrement passe par deux hooks plutôt qu'un :
 *
 *   - `wp_insert_post_data` pose le slug et la description AVANT l'écriture,
 *     pour que WordPress les valide comme les siens ;
 *   - `save_post` écrit la définition en méta.
 *
 * Faire les deux dans `save_post` obligerait à ré-enregistrer le post depuis
 * son propre hook d'enregistrement. On sait comment ça finit.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Métaboxes et enregistrement d'une définition.
 */
class Blocs_Creator_Ecran_Definition {

	/**
	 * Messages à afficher au prochain écran.
	 *
	 * @var array<int, array{type:string, texte:string}>
	 */
	private $messages = array();

	/**
	 * Branche les hooks.
	 */
	public function demarrer() {
		add_action( 'add_meta_boxes_' . Blocs_Creator_Definition::TYPE, array( $this, 'metaboxes' ) );
		add_filter( 'wp_insert_post_data', array( $this, 'avant_ecriture' ), 10, 2 );
		add_action( 'save_post_' . Blocs_Creator_Definition::TYPE, array( $this, 'enregistrer' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'invite_titre' ), 10, 2 );
		add_action( 'edit_form_after_title', array( $this, 'sous_titre' ) );
		add_filter( 'post_updated_messages', array( $this, 'messages_edition' ) );
		add_action( 'admin_notices', array( $this, 'afficher_messages' ) );
		add_filter( 'screen_options_show_screen', array( $this, 'masquer_options_ecran' ), 10, 2 );
	}

	/* ------------------------------------------------------------------ *
	 * Écran
	 * ------------------------------------------------------------------ */

	/**
	 * Remplace l'invite du champ titre.
	 *
	 * @param string  $texte Invite courante.
	 * @param WP_Post $post  Le post.
	 * @return string
	 */
	public function invite_titre( $texte, $post ) {
		if ( $post instanceof WP_Post && Blocs_Creator_Definition::TYPE === $post->post_type ) {
			return __( 'Nom du bloc — « Bannière », « Témoignages »…', 'blocs-creator' );
		}

		return $texte;
	}

	/**
	 * Affiche l'identifiant du bloc juste sous le titre.
	 *
	 * C'est le nom que portera le bloc dans le contenu des pages. Le voir au
	 * moment où l'on nomme le bloc évite de le découvrir trop tard.
	 *
	 * @param WP_Post $post Le post.
	 */
	public function sous_titre( $post ) {
		if ( ! $post instanceof WP_Post || Blocs_Creator_Definition::TYPE !== $post->post_type ) {
			return;
		}

		$definition = Blocs_Creator_Definition::charger( $post );
		$nom        = $definition ? Blocs_Creator_Definition::nom( $definition ) : '';

		printf(
			'<p class="bc-sous-titre">%s <code id="bc-apercu-nom">%s</code></p>',
			esc_html__( 'Identifiant du bloc :', 'blocs-creator' ),
			esc_html( '' !== $nom ? $nom : blocs_creator()->reglages->get( 'espace' ) . '/…' )
		);

		$this->parcours( $post, $definition );
	}

	/**
	 * Affiche le parcours d'un bloc : les trois étapes, et où l'on en est.
	 *
	 * Un bloc créé ici ne montre rien tant que son dessin n'existe pas. C'est
	 * la règle qui tient tout le plugin — les champs se déclarent, le dessin
	 * s'écrit — mais elle se découvrait jusqu'ici en publiant, puis en allant
	 * voir une page vide. Elle s'annonce maintenant en haut de l'écran, avec
	 * l'état réel du fichier : il existe, il reste à créer, ou le dossier est
	 * verrouillé.
	 *
	 * @param WP_Post    $post       Le post.
	 * @param array|null $definition La définition, si elle est lisible.
	 */
	private function parcours( $post, $definition ) {
		if ( null === $definition ) {
			return;
		}

		$nouveau  = 'auto-draft' === $post->post_status;
		$champs   = count( (array) $definition['champs'] );
		$gabarit  = Blocs_Creator_Gabarits::chemin( $definition );
		$prefere  = Blocs_Creator_Gabarits::chemin_prefere( $definition );
		$dossier  = dirname( $prefere );
		$ecrivant = is_dir( $dossier ) ? wp_is_writable( $dossier ) : wp_is_writable( dirname( $dossier ) );

		$etapes = array(
			array(
				'titre' => __( 'Nommez-le', 'blocs-creator' ),
				'etat'  => '' !== trim( (string) $post->post_title ) ? 'fait' : 'a-faire',
				'quoi'  => '' !== trim( (string) $post->post_title )
					? sprintf(
						/* translators: %s: identifiant complet du bloc. */
						__( 'Il s\'appellera %s dans vos pages.', 'blocs-creator' ),
						'<code>' . esc_html( Blocs_Creator_Definition::nom( $definition ) ) . '</code>'
					)
					: __( 'Le nom donne l\'identifiant du bloc, et l\'identifiant ne se change plus après coup.', 'blocs-creator' ),
			),
			array(
				'titre' => __( 'Déclarez ses champs', 'blocs-creator' ),
				'etat'  => $champs > 0 ? 'fait' : 'a-faire',
				'quoi'  => $champs > 0
					? sprintf(
						/* translators: %d: nombre de champs. */
						esc_html( _n( '%d champ à remplir dans l\'éditeur.', '%d champs à remplir dans l\'éditeur.', $champs, 'blocs-creator' ) ),
						$champs
					)
					: esc_html__( 'Ce qui se saisira dans l\'éditeur : un titre, une image, un tableau de lignes…', 'blocs-creator' ),
			),
		);

		if ( $nouveau ) {
			$etapes[] = array(
				'titre' => __( 'Dessinez-le', 'blocs-creator' ),
				'etat'  => 'a-faire',
				'quoi'  => sprintf(
					/* translators: %s: chemin du fichier de gabarit. */
					__( 'À la publication, un fichier de départ sera créé dans %s. C\'est lui qui donne au bloc son allure : tant qu\'il n\'est pas écrit, le bloc s\'affiche sans mise en forme.', 'blocs-creator' ),
					'<code>' . esc_html( Blocs_Creator_Gabarits::chemin_court( $prefere ) ) . '</code>'
				),
			);
		} elseif ( '' !== $gabarit ) {
			$etapes[] = array(
				'titre' => __( 'Dessinez-le', 'blocs-creator' ),
				'etat'  => 'fait',
				'quoi'  => sprintf(
					/* translators: %s: chemin du fichier de gabarit. */
					__( 'Son dessin est %s. Modifiez-le dans votre éditeur de code : le plugin ne le réécrit jamais.', 'blocs-creator' ),
					'<code>' . esc_html( Blocs_Creator_Gabarits::chemin_court( $gabarit ) ) . '</code>'
				),
			);
		} else {
			$etapes[] = array(
				'titre' => __( 'Dessinez-le', 'blocs-creator' ),
				'etat'  => 'alerte',
				'quoi'  => $ecrivant
					? sprintf(
						/* translators: 1: chemin du fichier, 2: lien vers la création. */
						__( 'Aucun fichier de dessin : le bloc montre ses champs bruts, sans mise en forme. %2$s pour créer %1$s.', 'blocs-creator' ),
						'<code>' . esc_html( Blocs_Creator_Gabarits::chemin_court( $prefere ) ) . '</code>',
						sprintf(
							'<a href="%s">%s</a>',
							esc_url(
								wp_nonce_url(
									admin_url( 'admin-post.php?action=bc_creer_gabarit&bloc=' . (int) $post->ID ),
									'bc_creer_gabarit_' . (int) $post->ID
								)
							),
							esc_html__( 'Cliquez ici', 'blocs-creator' )
						)
					)
					: sprintf(
						/* translators: %s: chemin du dossier. */
						__( 'Aucun fichier de dessin, et %s n\'est pas accessible en écriture : le bloc montre ses champs bruts. Copiez le code proposé dans la colonne de droite et déposez le fichier vous-même.', 'blocs-creator' ),
						'<code>' . esc_html( Blocs_Creator_Gabarits::chemin_court( $dossier ) ) . '</code>'
					),
			);
		}

		echo '<ol class="bc-parcours">';

		foreach ( $etapes as $rang => $etape ) {
			printf(
				'<li class="bc-parcours__etape est-%1$s"><span class="bc-parcours__rang" aria-hidden="true">%2$d</span>' .
				'<span class="bc-parcours__texte"><strong class="bc-parcours__titre">%3$s</strong>' .
				'<span class="bc-parcours__quoi">%4$s</span></span></li>',
				esc_attr( $etape['etat'] ),
				(int) $rang + 1,
				esc_html( $etape['titre'] ),
				wp_kses( $etape['quoi'], array( 'code' => array(), 'a' => array( 'href' => array() ) ) )
			);
		}

		echo '</ol>';
	}

	/**
	 * Retire le panneau « Options de l'écran », qui n'a rien à proposer ici.
	 *
	 * @param bool      $afficher Faut-il l'afficher.
	 * @param WP_Screen $ecran    L'écran.
	 * @return bool
	 */
	public function masquer_options_ecran( $afficher, $ecran ) {
		if ( $ecran instanceof WP_Screen && Blocs_Creator_Definition::TYPE === $ecran->post_type ) {
			return false;
		}

		return $afficher;
	}

	/**
	 * Déclare les métaboxes.
	 *
	 * @param WP_Post $post Le post.
	 */
	public function metaboxes( $post ) {
		remove_meta_box( 'slugdiv', Blocs_Creator_Definition::TYPE, 'normal' );

		add_meta_box(
			'bc-champs',
			__( 'Champs du bloc', 'blocs-creator' ),
			array( $this, 'metabox_champs' ),
			Blocs_Creator_Definition::TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'bc-identite',
			__( 'Identité', 'blocs-creator' ),
			array( $this, 'metabox_identite' ),
			Blocs_Creator_Definition::TYPE,
			'side',
			'high'
		);

		add_meta_box(
			'bc-gabarit',
			__( 'Gabarit', 'blocs-creator' ),
			array( $this, 'metabox_gabarit' ),
			Blocs_Creator_Definition::TYPE,
			'side',
			'default'
		);

		add_meta_box(
			'bc-fiche',
			__( 'Fiche du bloc', 'blocs-creator' ),
			array( $this, 'metabox_fiche' ),
			Blocs_Creator_Definition::TYPE,
			'side',
			'default'
		);

		add_meta_box(
			'bc-apparition',
			__( 'Apparition', 'blocs-creator' ),
			array( $this, 'metabox_apparition' ),
			Blocs_Creator_Definition::TYPE,
			'side',
			'default'
		);

		add_meta_box(
			'bc-comportement',
			__( 'Comportement', 'blocs-creator' ),
			array( $this, 'metabox_comportement' ),
			Blocs_Creator_Definition::TYPE,
			'side',
			'default'
		);

		$definition = Blocs_Creator_Definition::charger( $post );

		if ( $definition && Blocs_Creator_Usage::compter( Blocs_Creator_Definition::nom( $definition ) ) > 0 ) {
			add_meta_box(
				'bc-usage',
				__( 'Où ce bloc est-il utilisé ?', 'blocs-creator' ),
				array( $this, 'metabox_usage' ),
				Blocs_Creator_Definition::TYPE,
				'side',
				'low'
			);
		}
	}

	/**
	 * Affiche une métabox.
	 *
	 * @param string  $vue  Nom du fichier de vue, sans extension.
	 * @param WP_Post $post Le post.
	 */
	private function vue( $vue, $post ) {
		$definition = Blocs_Creator_Definition::charger( $post );

		if ( null === $definition ) {
			$definition = Blocs_Creator_Definition::vierge();
		}

		include BLOCS_CREATOR_DIR . 'admin/vues/' . $vue . '.php';
	}

	/**
	 * Métabox « Champs du bloc ».
	 *
	 * @param WP_Post $post Le post.
	 */
	public function metabox_champs( $post ) {
		wp_nonce_field( 'bc_enregistrer_' . $post->ID, 'bc_nonce' );

		$this->vue( 'metabox-champs', $post );
	}

	/**
	 * Métabox « Identité ».
	 *
	 * @param WP_Post $post Le post.
	 */
	public function metabox_identite( $post ) {
		$this->vue( 'metabox-identite', $post );
	}

	/**
	 * Métabox « Gabarit ».
	 *
	 * @param WP_Post $post Le post.
	 */
	public function metabox_gabarit( $post ) {
		$this->vue( 'metabox-gabarit', $post );
	}

	/**
	 * Affiche la métabox « Fiche du bloc ».
	 *
	 * @param WP_Post $post Le post.
	 */
	public function metabox_fiche( $post ) {
		$definition = Blocs_Creator_Definition::charger( $post ) ?? Blocs_Creator_Definition::vierge();

		include BLOCS_CREATOR_DIR . 'admin/vues/metabox-fiche.php';
	}

	/**
	 * Métabox « Apparition ».
	 *
	 * @param WP_Post $post Le post.
	 */
	public function metabox_apparition( $post ) {
		$this->vue( 'metabox-apparition', $post );
	}

	/**
	 * Métabox « Comportement ».
	 *
	 * @param WP_Post $post Le post.
	 */
	public function metabox_comportement( $post ) {
		$this->vue( 'metabox-comportement', $post );
	}

	/**
	 * Métabox « Où ce bloc est-il utilisé ? ».
	 *
	 * @param WP_Post $post Le post.
	 */
	public function metabox_usage( $post ) {
		$this->vue( 'metabox-usage', $post );
	}

	/* ------------------------------------------------------------------ *
	 * Enregistrement
	 * ------------------------------------------------------------------ */

	/**
	 * Le formulaire a-t-il bien été soumis par nous ?
	 *
	 * @param int $post_id Identifiant du post.
	 * @return bool
	 */
	private function soumission_valide( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( empty( $_POST['bc_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['bc_nonce'] ) ), 'bc_enregistrer_' . $post_id ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Reconstruit la définition depuis le formulaire.
	 *
	 * @param WP_Post|array $post    Le post, pour son titre et son statut.
	 * @param int           $post_id Identifiant du post, pour ce qui ne se saisit pas.
	 * @return array
	 */
	private function depuis_formulaire( $post, $post_id = 0 ) {
		/*
		 * Le jeton et la capacité ont été vérifiés par soumission_valide(),
		 * que les deux seuls appelants passent avant d'arriver ici. PHPCS ne
		 * suit pas d'une méthode à l'autre : d'où les exemptions ci-dessous.
		 * Les valeurs, elles, sont typées une par une plus bas.
		 */
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		$brut = isset( $_POST['bc'] ) ? wp_unslash( $_POST['bc'] ) : array();
		$brut = is_array( $brut ) ? $brut : array();

		$stockee = $post_id > 0 ? Blocs_Creator_Definition::charger( $post_id ) : null;
		$champs = $stockee['champs'] ?? array();

		if ( isset( $_POST['bc_champs_json'] ) && is_string( $_POST['bc_champs_json'] ) ) {
			$json   = json_decode( wp_unslash( $_POST['bc_champs_json'] ), true );
			// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
			if ( is_array( $json ) && array_is_list( $json ) ) {
				$champs = $json;
			} else {
				$this->message( 'error', __( 'Les champs reçus sont incomplets ou invalides. Les champs précédents ont été conservés.', 'blocs-creator' ) );
			}
		} else {
			$this->message( 'error', __( 'Les champs n’ont pas été transmis. Les champs précédents ont été conservés.', 'blocs-creator' ) );
		}

		$definition = wp_parse_args(
			array(
				'titre'       => is_array( $post ) ? (string) ( $post['post_title'] ?? '' ) : $post->post_title,
				'slug'        => (string) ( $brut['slug'] ?? '' ),
				'espace'      => (string) ( $brut['espace'] ?? '' ),
				'description' => (string) ( $brut['description'] ?? '' ),
				'icone'       => (string) ( $brut['icone'] ?? '' ),
				'categorie'   => (string) ( $brut['categorie'] ?? '' ),
				'mots_cles'   => (string) ( $brut['mots_cles'] ?? '' ),
				'apercu'      => (string) ( $brut['apercu'] ?? 'serveur' ),
				'animation'       => (string) ( $brut['animation'] ?? '' ),
				'animation_duree' => (int) ( $brut['animation_duree'] ?? 0 ),
				'parent'      => (string) ( $brut['parent'] ?? '' ) !== '' ? explode( ',', (string) $brut['parent'] ) : array(),
				'supports'    => (array) ( $brut['supports'] ?? array() ),
				'champs'      => $champs,
			),
			Blocs_Creator_Definition::vierge()
		);

		if ( '' === trim( (string) $definition['slug'] ) ) {
			$definition['slug'] = sanitize_title( $definition['titre'] );
		}

		/*
		 * Ce que le formulaire ne montre pas, il ne doit pas l'effacer. Les
		 * attributs conservés, les feuilles de style et la trace de reprise
		 * d'un bloc codé n'ont aucun champ dans l'écran : on les relit tels
		 * qu'ils sont en base et on les repose.
		 */
		$stockee = $post_id > 0 ? Blocs_Creator_Definition::charger( $post_id ) : null;

		if ( null !== $stockee ) {
			foreach ( array( 'attributs', 'assets', 'extras', 'adoption' ) as $cle ) {
				$definition[ $cle ] = $stockee[ $cle ];
			}
		}

		return Blocs_Creator_Definition::normaliser( $definition );
	}

	/**
	 * Pose le slug et la description avant que WordPress n'écrive le post.
	 *
	 * @param array $donnees Données prêtes à l'écriture.
	 * @param array $brut    Données soumises.
	 * @return array
	 */
	public function avant_ecriture( $donnees, $brut ) {
		if ( Blocs_Creator_Definition::TYPE !== ( $donnees['post_type'] ?? '' ) ) {
			return $donnees;
		}

		$post_id = (int) ( $brut['ID'] ?? 0 );

		if ( ! $this->soumission_valide( $post_id ) ) {
			return $donnees;
		}

		$definition = $this->depuis_formulaire( $donnees, $post_id );
		$slug       = $definition['slug'];

		// Deux blocs de même nom, c'est le second qui gagne au hasard de
		// l'ordre d'enregistrement. On corrige et on le dit.
		$libre = $this->slug_libre( $slug, $definition['espace'], $post_id, $definition );

		if ( $libre !== $slug ) {
			$this->message(
				'warning',
				sprintf(
					/* translators: 1: identifiant demandé, 2: identifiant retenu. */
					__( 'L\'identifiant « %1$s » était déjà pris : le bloc a été enregistré sous « %2$s ».', 'blocs-creator' ),
					$slug,
					$libre
				)
			);
		}

		$donnees['post_name']    = $libre;
		$donnees['post_excerpt'] = $definition['description'];

		if ( '' === trim( (string) $donnees['post_title'] ) ) {
			$donnees['post_title'] = __( 'Bloc sans nom', 'blocs-creator' );
		}

		return $donnees;
	}

	/**
	 * Retourne un slug libre dans cet espace de noms, hors du bloc courant.
	 *
	 * @param string $slug       Slug demandé.
	 * @param string $espace     Espace de noms.
	 * @param int    $post_id    Bloc en cours d'édition.
	 * @param array  $definition La définition soumise.
	 * @return string
	 */
	private function slug_libre( $slug, $espace, $post_id, $definition = array() ) {
		$pris    = array();
		$reprend = (string) ( $definition['adoption']['nom'] ?? '' );

		foreach ( Blocs_Creator_Definition::toutes() as $autre ) {
			if ( (int) $autre['id'] === (int) $post_id || $autre['espace'] !== $espace ) {
				continue;
			}

			$pris[] = $autre['slug'];
		}

		foreach ( blocs_creator()->registre->blocs_codes() as $code ) {
			// Le bloc codé qu'on a repris porte le même nom que sa reprise :
			// c'est voulu, il ne prend donc pas la place.
			if ( $code['espace'] === $espace && $code['nom'] !== $reprend ) {
				$pris[] = $code['slug'];
			}
		}

		$base  = $slug;
		$index = 2;

		while ( in_array( $slug, $pris, true ) ) {
			$slug = $base . '-' . $index;
			++$index;
		}

		return $slug;
	}

	/**
	 * Écrit la définition en méta, puis crée le gabarit s'il le faut.
	 *
	 * @param int     $post_id Identifiant du post.
	 * @param WP_Post $post    Le post enregistré.
	 */
	public function enregistrer( $post_id, $post ) {
		if ( ! $this->soumission_valide( $post_id ) ) {
			return;
		}

		$definition = $this->depuis_formulaire( $post, $post_id );

		// Le titre, le slug et la description sont déjà dans les colonnes du
		// post : les répéter en méta, c'est préparer une divergence.
		$a_stocker = $definition;
		unset( $a_stocker['id'], $a_stocker['titre'], $a_stocker['slug'], $a_stocker['description'], $a_stocker['statut'], $a_stocker['source'] );

		update_post_meta(
			$post_id,
			Blocs_Creator_Definition::META,
			wp_slash( wp_json_encode( $a_stocker, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) )
		);

		Blocs_Creator_Usage::vider_cache();

		$fraiche = Blocs_Creator_Definition::charger( $post_id );

		if ( null === $fraiche ) {
			return;
		}

		if ( empty( $fraiche['champs'] ) ) {
			$this->message(
				'warning',
				__( 'Ce bloc n\'a aucun champ : il s\'insérera, mais il n\'y aura rien à y saisir.', 'blocs-creator' )
			);
		}

		if (
			'publish' === $post->post_status
			&& blocs_creator()->reglages->get( 'creer_gabarit' )
			&& empty( $fraiche['adoption'] )
			&& '' === Blocs_Creator_Gabarits::chemin( $fraiche )
		) {
			$resultat = Blocs_Creator_Gabarits::creer( $fraiche );

			if ( is_wp_error( $resultat ) ) {
				$this->message( 'warning', $resultat->get_error_message() );
			} else {
				$this->message(
					'success',
					sprintf(
						/* translators: %s: chemin du fichier créé. */
						__( 'Gabarit créé : %s — c\'est là que se dessine le bloc.', 'blocs-creator' ),
						Blocs_Creator_Gabarits::chemin_court( $resultat )
					)
				);
			}
		}

		do_action( 'blocs_creator_definition_enregistree', $post_id, $fraiche );
	}

	/* ------------------------------------------------------------------ *
	 * Messages
	 * ------------------------------------------------------------------ */

	/**
	 * Met un message de côté pour le prochain écran.
	 *
	 * @param string $type  `success`, `warning` ou `error`.
	 * @param string $texte Le message.
	 */
	private function message( $type, $texte ) {
		$this->messages[] = array(
			'type'  => $type,
			'texte' => $texte,
		);

		set_transient( 'bc_messages_' . get_current_user_id(), $this->messages, 60 );
	}

	/**
	 * Affiche les messages mis de côté.
	 */
	public function afficher_messages() {
		$cle      = 'bc_messages_' . get_current_user_id();
		$messages = get_transient( $cle );

		if ( empty( $messages ) || ! is_array( $messages ) ) {
			return;
		}

		delete_transient( $cle );

		foreach ( $messages as $message ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $message['type'] ),
				esc_html( $message['texte'] )
			);
		}
	}

	/**
	 * Remplace les messages d'enregistrement natifs.
	 *
	 * « Article publié. Voir l'article » n'a aucun sens pour un bloc : il n'a
	 * pas de page à voir.
	 *
	 * @param array $messages Messages existants.
	 * @return array
	 */
	public function messages_edition( $messages ) {
		$messages[ Blocs_Creator_Definition::TYPE ] = array(
			0  => '',
			1  => __( 'Bloc mis à jour. Il est disponible dans l\'éditeur.', 'blocs-creator' ),
			2  => __( 'Champ mis à jour.', 'blocs-creator' ),
			3  => __( 'Champ supprimé.', 'blocs-creator' ),
			4  => __( 'Bloc mis à jour.', 'blocs-creator' ),
			5  => __( 'Bloc restauré à sa version précédente.', 'blocs-creator' ),
			6  => __( 'Bloc créé. Il est disponible dans l\'éditeur.', 'blocs-creator' ),
			7  => __( 'Bloc enregistré.', 'blocs-creator' ),
			8  => __( 'Bloc soumis.', 'blocs-creator' ),
			9  => __( 'Bloc programmé.', 'blocs-creator' ),
			10 => __( 'Brouillon du bloc mis à jour.', 'blocs-creator' ),
		);

		return $messages;
	}
}
