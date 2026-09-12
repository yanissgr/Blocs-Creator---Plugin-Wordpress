<?php
/**
 * L'écran « Tous les blocs ».
 *
 * Une seule liste pour deux natures de blocs. C'est le point de l'écran : on
 * ne veut pas savoir si un bloc a été déclaré dans le back-office ou écrit à
 * la main pour répondre à la question « qu'est-ce qui existe sur ce site ? ».
 * La colonne Source le dit, et les actions s'adaptent — un bloc codé ne se
 * modifie pas ici, il se modifie dans son fichier.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Le tableau des blocs.
 */
class BC_Liste_Table extends WP_List_Table {

	/**
	 * Tous les blocs, avant filtrage.
	 *
	 * @var array
	 */
	private $blocs = array();

	/**
	 * Constructeur.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'bloc',
				'plural'   => 'blocs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Déclare les colonnes.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'titre'   => __( 'Bloc', 'blocs-creator' ),
			'nom'     => __( 'Identifiant', 'blocs-creator' ),
			'source'  => __( 'Source', 'blocs-creator' ),
			'champs'  => __( 'Champs', 'blocs-creator' ),
			'gabarit' => __( 'Gabarit', 'blocs-creator' ),
			'usage'   => __( 'Utilisé', 'blocs-creator' ),
		);
	}

	/**
	 * Colonnes triables.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'titre'  => array( 'titre', true ),
			'nom'    => array( 'nom', false ),
			'source' => array( 'source', false ),
			'usage'  => array( 'usage', false ),
		);
	}

	/**
	 * Les vues : tous, générés, codés, brouillons.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$blocs = blocs_creator()->registre->tous();

		$comptes = array(
			'tous'      => count( $blocs ),
			'genere'    => 0,
			'code'      => 0,
			'brouillon' => 0,
		);

		foreach ( $blocs as $bloc ) {
			++$comptes[ $bloc['source'] ];

			if ( 'genere' === $bloc['source'] && 'publish' !== $bloc['statut'] ) {
				++$comptes['brouillon'];
			}
		}

		$courant = $this->filtre();

		$vues    = array();
		$libelles = array(
			'tous'      => __( 'Tous', 'blocs-creator' ),
			'genere'    => __( 'Générés', 'blocs-creator' ),
			'code'      => __( 'Codés', 'blocs-creator' ),
			'brouillon' => __( 'Brouillons', 'blocs-creator' ),
		);

		foreach ( $libelles as $cle => $libelle ) {
			if ( 0 === $comptes[ $cle ] && 'tous' !== $cle ) {
				continue;
			}

			$vues[ $cle ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( add_query_arg( array( 'page' => BC_Admin::PAGE, 'filtre' => $cle ), admin_url( 'admin.php' ) ) ),
				$courant === $cle ? ' class="current" aria-current="page"' : '',
				esc_html( $libelle ),
				(int) $comptes[ $cle ]
			);
		}

		return $vues;
	}

	/**
	 * Retourne le filtre courant.
	 *
	 * @return string
	 */
	private function filtre() {
		$filtre = isset( $_GET['filtre'] ) ? sanitize_key( wp_unslash( $_GET['filtre'] ) ) : 'tous';

		return in_array( $filtre, array( 'tous', 'genere', 'code', 'brouillon' ), true ) ? $filtre : 'tous';
	}

	/**
	 * Le message affiché quand il n'y a rien.
	 */
	public function no_items() {
		esc_html_e( 'Aucun bloc pour l\'instant. Créez-en un — il vous faudra un nom et au moins un champ.', 'blocs-creator' );
	}

	/**
	 * Rassemble, filtre et trie les lignes.
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->blocs = blocs_creator()->registre->tous();

		foreach ( $this->blocs as &$bloc ) {
			$bloc['usage'] = BC_Usage::compter( $bloc['nom'] );
		}

		unset( $bloc );

		$items  = $this->blocs;
		$filtre = $this->filtre();

		if ( 'brouillon' === $filtre ) {
			$items = array_filter(
				$items,
				static function ( $bloc ) {
					return 'genere' === $bloc['source'] && 'publish' !== $bloc['statut'];
				}
			);
		} elseif ( 'tous' !== $filtre ) {
			$items = array_filter(
				$items,
				static function ( $bloc ) use ( $filtre ) {
					return $bloc['source'] === $filtre;
				}
			);
		}

		$recherche = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		if ( '' !== $recherche ) {
			$items = array_filter(
				$items,
				static function ( $bloc ) use ( $recherche ) {
					return false !== stripos( $bloc['titre'] . ' ' . $bloc['nom'] . ' ' . $bloc['description'], $recherche );
				}
			);
		}

		$tri   = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'titre';
		$sens  = isset( $_GET['order'] ) && 'desc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? -1 : 1;
		$items = array_values( $items );

		usort(
			$items,
			static function ( $a, $b ) use ( $tri, $sens ) {
				if ( 'usage' === $tri ) {
					return ( $a['usage'] <=> $b['usage'] ) * $sens;
				}

				$cle = in_array( $tri, array( 'nom', 'source' ), true ) ? $tri : 'titre';

				return strcasecmp( (string) $a[ $cle ], (string) $b[ $cle ] ) * $sens;
			}
		);

		$par_page = 50;
		$page     = $this->get_pagenum();
		$total    = count( $items );

		$this->items = array_slice( $items, ( $page - 1 ) * $par_page, $par_page );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $par_page,
				'total_pages' => (int) ceil( $total / $par_page ),
			)
		);
	}

	/* ------------------------------------------------------------------ *
	 * Colonnes
	 * ------------------------------------------------------------------ */

	/**
	 * Colonne du nom du bloc, avec son icône et ses actions.
	 *
	 * @param array $bloc La ligne.
	 * @return string
	 */
	public function column_titre( $bloc ) {
		$icone = $this->icone( $bloc['icone'] );

		if ( 'genere' === $bloc['source'] ) {
			$lien  = get_edit_post_link( $bloc['id'] );
			$titre = sprintf(
				'<a class="row-title" href="%s">%s</a>',
				esc_url( (string) $lien ),
				esc_html( $bloc['titre'] )
			);
		} else {
			$titre = sprintf( '<span class="row-title">%s</span>', esc_html( $bloc['titre'] ) );
		}

		if ( 'genere' === $bloc['source'] && 'publish' !== $bloc['statut'] ) {
			$titre .= ' <span class="bc-etiquette bc-etiquette--brouillon">' . esc_html__( 'Brouillon', 'blocs-creator' ) . '</span>';
		}

		$description = '' !== $bloc['description']
			? '<div class="bc-liste__description">' . esc_html( wp_trim_words( $bloc['description'], 14 ) ) . '</div>'
			: '';

		return $icone . '<strong>' . $titre . '</strong>' . $description . $this->row_actions( $this->actions( $bloc ) );
	}

	/**
	 * Retourne la puce d'icône d'un bloc.
	 *
	 * @param string $icone Nom de Dashicon, ou balise.
	 * @return string
	 */
	private function icone( $icone ) {
		$icone = (string) $icone;

		if ( '' === $icone || ! preg_match( '/^[a-z0-9-]+$/', $icone ) ) {
			$icone = 'block-default';
		}

		return sprintf(
			'<span class="bc-liste__icone dashicons dashicons-%s" aria-hidden="true"></span>',
			esc_attr( $icone )
		);
	}

	/**
	 * Retourne les actions d'une ligne.
	 *
	 * @param array $bloc La ligne.
	 * @return array<string, string>
	 */
	private function actions( $bloc ) {
		if ( 'genere' !== $bloc['source'] ) {
			$actions = array(
				'reprendre' => sprintf(
					'<a href="%s">%s</a>',
					esc_url(
						wp_nonce_url(
							add_query_arg(
								array(
									'page'         => BC_Admin::PAGE,
									'bc_reprendre' => rawurlencode( $bloc['nom'] ),
								),
								admin_url( 'admin.php' )
							),
							'bc_reprendre_' . $bloc['nom']
						)
					),
					esc_html__( 'Reprendre la main', 'blocs-creator' )
				),
			);

			if ( '' !== $bloc['gabarit'] ) {
				$actions['fichier'] = sprintf(
					'<span class="bc-chemin">%s</span>',
					esc_html( BC_Gabarits::chemin_court( dirname( $bloc['gabarit'] ) ) )
				);
			}

			return $actions;
		}

		$id = (int) $bloc['id'];

		$actions = array(
			'edit' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( (string) get_edit_post_link( $id ) ),
				esc_html__( 'Modifier', 'blocs-creator' )
			),
			'dupliquer' => sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					wp_nonce_url(
						admin_url( 'admin-post.php?action=bc_dupliquer&bloc=' . $id ),
						'bc_dupliquer_' . $id
					)
				),
				esc_html__( 'Dupliquer', 'blocs-creator' )
			),
			'exporter' => sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					wp_nonce_url(
						admin_url( 'admin-post.php?action=bc_exporter&blocs=' . $id ),
						'bc_exporter'
					)
				),
				esc_html__( 'Exporter', 'blocs-creator' )
			),
		);

		/*
		 * La suppression est la même pour tous les blocs générés, repris ou
		 * non : la définition part, franchement. Le message de confirmation
		 * change, lui, selon ce qui attend derrière — un dossier de code qui
		 * reprend la main, ou rien du tout.
		 */
		$origine = ! empty( $bloc['adoption']['nom'] )
			? BC_Adoption::bloc_code( (string) $bloc['adoption']['nom'] )
			: null;

		if ( null !== $origine ) {
			$actions['rendre'] = sprintf(
				'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
				esc_url(
					wp_nonce_url(
						admin_url( 'admin-post.php?action=bc_rendre_au_code&bloc=' . $id ),
						'bc_rendre_au_code_' . $id
					)
				),
				esc_js( __( 'Cette définition sera supprimée et le bloc codé reprendra la main. Les pages ne changent pas. On continue ?', 'blocs-creator' ) ),
				esc_html__( 'Rendre au code', 'blocs-creator' )
			);
		}

		$avertissement = null !== $origine
			? __( 'Supprimer cette définition ? Le bloc codé d\'origine reprendra la main : les pages ne changent pas.', 'blocs-creator' )
			: sprintf(
				/* translators: %d: nombre de publications. */
				_n(
					'Supprimer « %2$s » ? %1$d publication s\'en sert et affichera un bloc vide.',
					'Supprimer « %2$s » ? %1$d publications s\'en servent et afficheront un bloc vide.',
					max( 1, (int) $bloc['usage'] ),
					'blocs-creator'
				),
				(int) $bloc['usage'],
				$bloc['titre']
			);

		if ( null === $origine && 0 === (int) $bloc['usage'] ) {
			$avertissement = sprintf(
				/* translators: %s: nom du bloc. */
				__( 'Supprimer « %s » ? Aucune publication ne s\'en sert. Son fichier de gabarit, lui, reste dans le thème.', 'blocs-creator' ),
				$bloc['titre']
			);
		}

		$actions['supprimer'] = sprintf(
			'<a class="submitdelete" href="%s" onclick="return confirm(\'%s\');">%s</a>',
			esc_url(
				wp_nonce_url(
					admin_url( 'admin-post.php?action=bc_supprimer&bloc=' . $id ),
					'bc_supprimer_' . $id
				)
			),
			esc_js( $avertissement ),
			esc_html__( 'Supprimer', 'blocs-creator' )
		);

		return $actions;
	}

	/**
	 * Colonne de l'identifiant Gutenberg.
	 *
	 * @param array $bloc La ligne.
	 * @return string
	 */
	public function column_nom( $bloc ) {
		return sprintf( '<code class="bc-code">%s</code>', esc_html( $bloc['nom'] ) );
	}

	/**
	 * Colonne de la source.
	 *
	 * @param array $bloc La ligne.
	 * @return string
	 */
	public function column_source( $bloc ) {
		if ( 'genere' === $bloc['source'] ) {
			if ( ! empty( $bloc['adoption']['nom'] ) ) {
				return '<span class="bc-etiquette bc-etiquette--repris">' . esc_html__( 'Repris du code', 'blocs-creator' ) . '</span>'
					. '<div class="bc-liste__origine">' . esc_html( (string) $bloc['adoption']['origine'] ) . '</div>';
			}

			return '<span class="bc-etiquette bc-etiquette--genere">' . esc_html__( 'Généré', 'blocs-creator' ) . '</span>';
		}

		return '<span class="bc-etiquette bc-etiquette--code">' . esc_html__( 'Codé', 'blocs-creator' ) . '</span>'
			. '<div class="bc-liste__origine">' . esc_html( (string) ( $bloc['origine_nom'] ?? '' ) ) . '</div>';
	}

	/**
	 * Colonne du nombre de champs.
	 *
	 * @param array $bloc La ligne.
	 * @return string
	 */
	public function column_champs( $bloc ) {
		$champs = (array) $bloc['champs'];
		$nombre = count( $champs );

		if ( 0 === $nombre ) {
			return '<span class="bc-vide">—</span>';
		}

		return sprintf(
			'<span title="%1$s">%2$s</span>',
			esc_attr( implode( ', ', $champs ) ),
			esc_html(
				sprintf(
					/* translators: %d: nombre de champs. */
					_n( '%d champ', '%d champs', $nombre, 'blocs-creator' ),
					$nombre
				)
			)
		);
	}

	/**
	 * Colonne du gabarit.
	 *
	 * @param array $bloc La ligne.
	 * @return string
	 */
	public function column_gabarit( $bloc ) {
		if ( '' !== $bloc['gabarit'] ) {
			$complet = BC_Gabarits::chemin_court( $bloc['gabarit'] );

			/*
			 * Le chemin entier tiendrait sur cinq lignes et ferait une ligne
			 * de tableau haute comme une image. Ce qui distingue un gabarit
			 * d'un autre, c'est son dossier et son nom : le reste va dans
			 * l'infobulle, où il ne gêne personne.
			 */
			$morceaux = explode( '/', $complet );
			$court    = implode( '/', array_slice( $morceaux, -2 ) );

			return sprintf(
				'<span class="bc-oui dashicons dashicons-yes-alt" aria-hidden="true"></span> <span class="bc-chemin" title="%s">%s</span>',
				esc_attr( $complet ),
				esc_html( $court )
			);
		}

		if ( 'genere' !== $bloc['source'] ) {
			return '<span class="bc-vide">—</span>';
		}

		return sprintf(
			'<span class="bc-non dashicons dashicons-warning" aria-hidden="true"></span> <a href="%1$s">%2$s</a>',
			esc_url(
				wp_nonce_url(
					admin_url( 'admin-post.php?action=bc_creer_gabarit&bloc=' . (int) $bloc['id'] ),
					'bc_creer_gabarit_' . (int) $bloc['id']
				)
			),
			esc_html__( 'Créer le fichier', 'blocs-creator' )
		);
	}

	/**
	 * Colonne du nombre d'utilisations.
	 *
	 * @param array $bloc La ligne.
	 * @return string
	 */
	public function column_usage( $bloc ) {
		$nombre = (int) $bloc['usage'];

		if ( 0 === $nombre ) {
			return '<span class="bc-vide">' . esc_html__( 'nulle part', 'blocs-creator' ) . '</span>';
		}

		return esc_html(
			sprintf(
				/* translators: %d: nombre de publications. */
				_n( '%d publication', '%d publications', $nombre, 'blocs-creator' ),
				$nombre
			)
		);
	}

	/**
	 * Repli pour les colonnes sans méthode dédiée.
	 *
	 * @param array  $bloc    La ligne.
	 * @param string $colonne Nom de la colonne.
	 * @return string
	 */
	public function column_default( $bloc, $colonne ) {
		return isset( $bloc[ $colonne ] ) && is_scalar( $bloc[ $colonne ] ) ? esc_html( (string) $bloc[ $colonne ] ) : '';
	}
}
