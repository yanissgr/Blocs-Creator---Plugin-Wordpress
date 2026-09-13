<?php
/**
 * Les réglages du plugin.
 *
 * Ils tiennent en une option, et en six lignes. Un plugin qui se réinstalle
 * sur d'autres sites doit avoir des valeurs par défaut justes, pas un écran de
 * réglages long : tout ce qui est ici a une valeur qui marche sans y toucher.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lecture et écriture des réglages.
 */
class Blocs_Creator_Reglages {

	/**
	 * Nom de l'option.
	 */
	const OPTION = 'blocs_creator_reglages';

	/**
	 * Valeurs en cache.
	 *
	 * @var array|null
	 */
	private $valeurs = null;

	/**
	 * Branche les hooks.
	 */
	public function demarrer() {
		add_action( 'admin_init', array( $this, 'declarer' ) );
	}

	/**
	 * Retourne les valeurs par défaut.
	 *
	 * L'espace de noms se déduit du nom du site : sur « Cabinet Martin », les
	 * blocs s'appelleront `cabinet-martin/…`. C'est ce qu'on aurait écrit à la
	 * main, et ça évite d'avoir deux sites dont les blocs portent le même nom
	 * le jour où l'on copie un contenu de l'un vers l'autre.
	 *
	 * @return array
	 */
	public static function defauts() {
		$espace = sanitize_key( sanitize_title( get_bloginfo( 'name' ) ) );

		if ( '' === $espace || is_numeric( $espace ) ) {
			$espace = 'blocs';
		}

		return array(
			'espace'             => $espace,
			'categorie'          => 'blocs-creator',
			'categorie_titre'    => __( 'Mes blocs', 'blocs-creator' ),
			'dossier_gabarits'   => 'blocs',
			'creer_gabarit'      => true,
			'supprimer_donnees'  => false,
			'blocs_desactives'   => array(),
		);
	}

	/**
	 * Retourne toutes les valeurs.
	 *
	 * @return array
	 */
	public function tout() {
		if ( null === $this->valeurs ) {
			$this->valeurs = wp_parse_args(
				(array) get_option( self::OPTION, array() ),
				self::defauts()
			);
		}

		return $this->valeurs;
	}

	/**
	 * Retourne un réglage.
	 *
	 * @param string $cle Nom du réglage.
	 * @return mixed
	 */
	public function get( $cle ) {
		$tout = $this->tout();

		return $tout[ $cle ] ?? null;
	}

	/**
	 * Écrit les réglages.
	 *
	 * @param array $valeurs Nouvelles valeurs.
	 */
	public function set( $valeurs ) {
		$this->valeurs = null;

		update_option( self::OPTION, $this->assainir( (array) $valeurs ) );
	}

	/**
	 * Écrit les réglages envoyés par l'écran, et dit ce qui s'est passé.
	 *
	 * L'écran n'envoie plus son formulaire à `options.php`. C'est un choix, et
	 * il a une raison : l'API des réglages de WordPress fait dépendre un
	 * enregistrement de quatre choses qu'on ne voit pas — le groupe autorisé,
	 * la capacité filtrée, le jeton, un transitoire de trente secondes pour le
	 * message. Quand l'une lâche, `options.php` renvoie l'écran à l'identique,
	 * sans un mot, et le bouton passe pour mort. C'est exactement ce qui se
	 * produisait ici.
	 *
	 * Ce chemin-ci ne fait que quatre choses, et rend compte de chacune :
	 * il vérifie le droit, écrit, RELIT, et compare. Un réglage qui n'a pas
	 * pris le dit.
	 *
	 * @param array $valeurs Valeurs brutes du formulaire.
	 * @return array{ecrit:bool, refuses:array<int, string>} Ce qui a été fait.
	 */
	public function enregistrer_depuis_formulaire( $valeurs ) {
		$attendu = $this->assainir( (array) $valeurs );

		$this->valeurs = null;

		update_option( self::OPTION, $attendu );

		$refuses = $this->ecart( $attendu );
		$force   = false;

		/*
		 * L'écriture n'a pas pris. Il reste une porte : écrire la ligne
		 * directement. Ce n'est pas élégant, et c'est délibéré — un filtre
		 * `pre_update_option`, un cache d'objets qui ne se vide pas, une
		 * extension de sécurité trop zélée : autant de choses qu'on ne voit pas
		 * depuis ici, et qui font toutes le même bouton mort. La valeur est
		 * déjà assainie ; ce qu'on saute, ce sont les filtres, pas les gardes.
		 */
		if ( ! empty( $refuses ) ) {
			$force = Blocs_Creator_Diagnostic::forcer( self::OPTION, $attendu );

			$this->valeurs = null;
			$refuses       = $this->ecart( $attendu );
		}

		Blocs_Creator_Diagnostic::noter(
			array(
				'quoi' => sprintf(
					'réglages : %d champs reçus, %s%s',
					count( (array) $valeurs ),
					empty( $refuses ) ? 'écrits' : 'REFUSÉS (' . implode( ', ', $refuses ) . ')',
					$force ? ', après écriture directe' : ''
				),
			)
		);

		return array(
			'ecrit'   => empty( $refuses ),
			'refuses' => $refuses,
			'force'   => $force,
		);
	}

	/**
	 * Retourne les réglages qui ne sont pas en base ce qu'on vient d'y mettre.
	 *
	 * La relecture saute le cache d'objets : c'est la seule façon de savoir si
	 * la LIGNE a changé, et non si l'on se relit soi-même.
	 *
	 * @param array $attendu Ce qu'on a voulu écrire.
	 * @return array<int, string> Les clés qui n'ont pas pris.
	 */
	private function ecart( $attendu ) {
		wp_cache_delete( self::OPTION, 'options' );

		$this->valeurs = null;

		$relu    = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defauts() );
		$refuses = array();

		foreach ( $attendu as $cle => $valeur ) {
			if ( ( $relu[ $cle ] ?? null ) !== $valeur ) {
				$refuses[] = (string) $cle;
			}
		}

		return $refuses;
	}

	/**
	 * Déclare l'option auprès de l'API des réglages.
	 */
	public function declarer() {
		register_setting(
			'blocs_creator_reglages',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'assainir' ),
				'default'           => self::defauts(),
			)
		);
	}

	/**
	 * Nettoie les réglages soumis.
	 *
	 * @param array $valeurs Valeurs brutes.
	 * @return array
	 */
	public function assainir( $valeurs ) {
		$defauts = self::defauts();
		$propres = array();

		$espace = sanitize_key( (string) ( $valeurs['espace'] ?? '' ) );
		$espace = preg_replace( '/[^a-z0-9-]/', '', $espace );

		$propres['espace']    = ( '' !== $espace && ! is_numeric( $espace ) ) ? $espace : $defauts['espace'];
		$propres['categorie'] = sanitize_key( (string) ( $valeurs['categorie'] ?? '' ) );

		if ( '' === $propres['categorie'] ) {
			$propres['categorie'] = $defauts['categorie'];
		}

		$propres['categorie_titre'] = sanitize_text_field( (string) ( $valeurs['categorie_titre'] ?? '' ) );

		if ( '' === $propres['categorie_titre'] ) {
			$propres['categorie_titre'] = $defauts['categorie_titre'];
		}

		// Un chemin de gabarits reste relatif au thème : ni racine absolue,
		// ni remontée de dossier.
		$dossier = trim( (string) ( $valeurs['dossier_gabarits'] ?? '' ), "/ \t\n\r\0\x0B" );
		$dossier = str_replace( '..', '', $dossier );
		$dossier = preg_replace( '#[^a-zA-Z0-9/_-]#', '', $dossier );

		$propres['dossier_gabarits']  = '' !== $dossier ? $dossier : $defauts['dossier_gabarits'];
		$propres['creer_gabarit']     = ! empty( $valeurs['creer_gabarit'] );
		$propres['supprimer_donnees'] = ! empty( $valeurs['supprimer_donnees'] );

		/*
		 * La liste des blocs mis de côté se stocke en négatif — ce qu'on
		 * retire, pas ce qu'on garde. Un bloc qui arrive avec une nouvelle
		 * extension est donc disponible d'emblée : le contraire obligerait à
		 * revenir cocher ici à chaque installation, et donnerait un éditeur
		 * qui perd des blocs sans qu'on ait rien demandé.
		 */
		$noms = static function ( $liste ) {
			// L'écran envoie la liste des blocs qu'il montrait en un seul
			// champ, séparée par des virgules ; un appel programmatique passe
			// plutôt un tableau. Les deux se lisent ici.
			if ( is_string( $liste ) ) {
				$liste = explode( ',', $liste );
			}

			return array_values(
				array_unique(
					array_filter(
						array_map(
							static function ( $nom ) {
								$nom = trim( (string) $nom );

								return preg_match( '#^[a-zA-Z0-9-]+/[a-zA-Z0-9-]+$#', $nom ) ? $nom : '';
							},
							(array) $liste
						)
					)
				)
			);
		};

		$connus = isset( $valeurs['blocs_connus'] ) ? $noms( $valeurs['blocs_connus'] ) : array();

		/*
		 * Un envoi qui annonce la liste des blocs montrés mais l'annonce vide
		 * est un envoi tronqué — `max_input_vars` dépassé, requête coupée. On
		 * ne touche alors à rien : perdre la liste des blocs écartés en
		 * silence serait le pire des deux maux.
		 */
		if ( isset( $valeurs['blocs_connus'] ) && empty( $connus ) ) {
			$propres['blocs_desactives'] = $noms( $this->get( 'blocs_desactives' ) );
		} elseif ( isset( $valeurs['blocs_ecartes'] ) && ! empty( $connus ) ) {
			/*
			 * L'écran compose lui-même la liste des blocs écartés et l'envoie
			 * en un seul champ. Ceux qu'il ne montrait pas sont conservés : un
			 * bloc mis à l'écart, puis dont l'extension est désactivée, doit
			 * rester à l'écart si on la réactive — sans quoi il reviendrait
			 * sans prévenir.
			 */
			$ecartes  = array_intersect( $noms( $valeurs['blocs_ecartes'] ), $connus );
			$ailleurs = array_diff( $noms( $this->get( 'blocs_desactives' ) ), $connus );

			$propres['blocs_desactives'] = array_values( array_unique( array_merge( $ecartes, $ailleurs ) ) );
		} elseif ( ! empty( $connus ) ) {
			/*
			 * Le repli sans JavaScript : l'écran coche ce qui reste
			 * disponible, et une case décochée n'envoie rien. On soustrait
			 * donc les cochées de la liste des blocs affichés.
			 */
			$actifs   = $noms( $valeurs['blocs_actifs'] ?? array() );
			$ailleurs = array_diff( $noms( $this->get( 'blocs_desactives' ) ), $connus );

			$propres['blocs_desactives'] = array_values( array_unique( array_merge( array_diff( $connus, $actifs ), $ailleurs ) ) );
		} else {
			$propres['blocs_desactives'] = $noms( $valeurs['blocs_desactives'] ?? $this->get( 'blocs_desactives' ) );
		}

		$this->valeurs = null;

		return $propres;
	}

	/**
	 * Retourne les catégories de l'inséreur, telles que l'éditeur les verra.
	 *
	 * Sert les deux écrans qui proposent de ranger un bloc : les réglages et
	 * la métabox d'identité. Ils montrent donc les vraies sections — celles du
	 * cœur, celles des extensions, celles des packs — plutôt qu'une liste
	 * écrite à la main qui finit toujours par dater.
	 *
	 * @return array<string, string> Titre, par slug.
	 */
	public static function categories_connues() {
		$connues = array();

		if ( function_exists( 'get_block_categories' ) && class_exists( 'WP_Block_Editor_Context' ) ) {
			foreach ( (array) get_block_categories( new WP_Block_Editor_Context() ) as $categorie ) {
				if ( empty( $categorie['slug'] ) ) {
					continue;
				}

				$connues[ (string) $categorie['slug'] ] = (string) ( $categorie['title'] ?? $categorie['slug'] );
			}
		}

		return $connues;
	}

	/**
	 * Range les blocs sous la catégorie homonyme si elle existe déjà.
	 *
	 * Le cas est celui d'un site qui a donné à la catégorie de ses blocs créés
	 * le titre d'une catégorie qu'un pack déclarait déjà :
	 * l'inséreur affichait deux sections du même nom. Depuis, le registre
	 * n'ajoute plus la nôtre quand le titre est pris — mais il reste à faire
	 * déménager les blocs qui pointaient sur l'ancien slug, sans quoi ils
	 * seraient rangés dans une section qui n'existe plus.
	 *
	 * Exécuté une fois, à la montée de version.
	 *
	 * @return string Le slug retenu.
	 */
	public function fusionner_categorie() {
		$slug       = (string) $this->get( 'categorie' );
		$titre      = (string) $this->get( 'categorie_titre' );
		$categories = self::categories_connues();

		if ( '' === $titre || isset( $categories[ $slug ] ) ) {
			return $slug;
		}

		$cible = '';

		foreach ( $categories as $autre_slug => $autre_titre ) {
			if ( 0 === strcasecmp( (string) $autre_titre, $titre ) ) {
				$cible = (string) $autre_slug;
				break;
			}
		}

		if ( '' === $cible || $cible === $slug ) {
			return $slug;
		}

		$valeurs              = $this->tout();
		$valeurs['categorie'] = $cible;

		update_option( self::OPTION, $this->assainir( $valeurs ) );

		foreach ( Blocs_Creator_Definition::toutes() as $definition ) {
			if ( $definition['categorie'] !== $slug ) {
				continue;
			}

			$definition['categorie'] = $cible;

			$a_stocker = $definition;
			unset( $a_stocker['id'], $a_stocker['titre'], $a_stocker['slug'], $a_stocker['description'], $a_stocker['statut'], $a_stocker['source'] );

			update_post_meta(
				$definition['id'],
				Blocs_Creator_Definition::META,
				wp_slash( wp_json_encode( $a_stocker, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) )
			);
		}

		return $cible;
	}

	/**
	 * Retourne une sélection d'icônes Dashicons pour le sélecteur.
	 *
	 * Pas les trois cents : celles qui servent vraiment à nommer un bloc.
	 * Le champ reste libre, on peut y taper n'importe quel nom de Dashicon.
	 *
	 * @return array<int, string>
	 */
	public static function dashicons() {
		return array(
			'block-default', 'layout', 'align-wide', 'align-full-width', 'align-pull-left', 'align-pull-right',
			'columns', 'grid-view', 'list-view', 'screenoptions', 'menu-alt3', 'editor-table',
			'editor-alignleft', 'editor-aligncenter', 'editor-quote', 'editor-ul', 'editor-ol', 'editor-code',
			'format-image', 'format-gallery', 'format-video', 'format-audio', 'format-quote', 'format-aside',
			'images-alt', 'images-alt2', 'camera', 'video-alt3', 'media-document', 'media-spreadsheet',
			'admin-links', 'admin-page', 'admin-post', 'admin-users', 'admin-comments', 'admin-appearance',
			'star-filled', 'heart', 'awards', 'megaphone', 'lightbulb', 'flag',
			'calendar-alt', 'clock', 'location', 'email-alt', 'phone', 'businessperson',
			'cart', 'tag', 'tickets-alt', 'money-alt', 'chart-bar', 'analytics',
			'yes-alt', 'info-outline', 'warning', 'sos', 'shield', 'lock',
			'smiley', 'palmtree', 'universal-access', 'groups', 'testimonial', 'welcome-learn-more',
		);
	}
}
