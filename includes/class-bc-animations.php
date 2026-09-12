<?php
/**
 * Les apparitions : faire entrer un bloc quand il arrive à l'écran.
 *
 * Trois décisions tiennent ce fichier, et rien ne doit les entamer.
 *
 * 1. L'APPARITION APPARTIENT AU BLOC, PAS À LA PAGE. Elle se choisit une fois,
 *    là où le bloc se définit — sur son écran pour un bloc que vous avez créé,
 *    dans les réglages pour un bloc de WordPress ou d'une autre extension.
 *    Toutes ses occurrences entrent donc de la même façon, sur toutes les
 *    pages. Un réglage posé page par page finit toujours par diverger : trois
 *    bannières, trois entrées différentes, et un site qui a l'air improvisé.
 *
 * 2. UNE APPARITION EST UNE SCÈNE, PAS UN GESTE. Un bloc n'est pas une boîte :
 *    c'est un titre, un chapô, une image, des cartes. Chaque scénario dit donc
 *    ce que fait le bloc ET ce que font ses parties, avec un décalage entre
 *    elles. Dix scènes travaillées valent mieux que quinze fondus — dont une,
 *    « Composée », qui laisse le fichier de dessin distribuer les rôles partie
 *    par partie, pour les blocs dont les moitiés n'entrent pas de la même façon.
 *
 * 3. LA CLASSE EST POSÉE AU RENDU, jamais dans le balisage enregistré. Un bloc
 *    statique garde exactement le HTML qu'il avait : changer une apparition ne
 *    rend jamais un contenu « inattendu », et retirer le plugin ne laisse
 *    aucune classe orpheline dans les pages.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Réglage et rendu des apparitions.
 */
class BC_Animations {

	/**
	 * Option qui porte les apparitions des blocs qu'on n'a pas créés.
	 *
	 * Celles des blocs créés ici vivent dans leur définition : c'est là qu'on
	 * les règle, et c'est là qu'elles s'exportent avec le reste.
	 */
	const OPTION = 'blocs_creator_animations';

	/**
	 * Blocs auxquels l'apparition n'est pas proposée.
	 *
	 * Ceux qui n'ont pas de balise à eux, ceux qui n'existent que dans
	 * l'éditeur, et ceux dont l'enveloppe appartient à leur parent.
	 *
	 * @var array<int, string>
	 */
	const EXCLUS = array(
		'core/freeform',
		'core/html',
		'core/shortcode',
		'core/missing',
		'core/block',
		'core/pattern',
		'core/template-part',
		'core/post-content',
		'core/list-item',
		'core/navigation-link',
		'core/navigation-submenu',
		'core/page-list-item',
		'core/nextpage',
		'core/more',
		'core/legacy-widget',
		'core/widget-group',
	);

	/**
	 * Les apparitions en vigueur, par nom de bloc.
	 *
	 * @var array<string, array>|null
	 */
	private static $carte = null;

	/**
	 * Branche les hooks.
	 */
	public static function demarrer() {
		add_action( 'admin_init', array( __CLASS__, 'declarer' ) );
		add_filter( 'render_block', array( __CLASS__, 'rendre' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets_site' ) );
		add_action( 'wp_head', array( __CLASS__, 'amorce' ), 1 );
		add_action( 'blocs_creator_definition_enregistree', array( __CLASS__, 'vider_cache' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Le catalogue
	 * ------------------------------------------------------------------ */

	/**
	 * Retourne les scénarios d'apparition.
	 *
	 * Chacun est décrit dans assets/css/animations.css, et nulle part ailleurs :
	 * ajouter un scénario, c'est ajouter une entrée ici et un bloc de règles
	 * là-bas. `parties` dit si le scénario anime aussi l'intérieur du bloc —
	 * l'aperçu de l'écran d'édition s'en sert pour montrer la bonne scène.
	 *
	 * @return array<string, array{libelle:string, description:string, parties:bool}>
	 */
	public static function scenarios() {
		$scenarios = array(
			'montee'      => array(
				'libelle'     => __( 'Montée', 'blocs-creator' ),
				'description' => __( 'Le bloc entier monte et se fond. Le plus sobre : il va partout.', 'blocs-creator' ),
				'parties'     => false,
			),
			'cascade'     => array(
				'libelle'     => __( 'Cascade', 'blocs-creator' ),
				'description' => __( 'Le bloc reste en place ; son titre, son texte et ses images montent l\'un après l\'autre.', 'blocs-creator' ),
				'parties'     => true,
			),
			'croisement'  => array(
				'libelle'     => __( 'Croisement', 'blocs-creator' ),
				'description' => __( 'Les parties arrivent alternativement de la gauche et de la droite, et se rejoignent. Pour un bloc en deux moitiés.', 'blocs-creator' ),
				'parties'     => true,
			),
			'deploiement' => array(
				'libelle'     => __( 'Déploiement', 'blocs-creator' ),
				'description' => __( 'Le bloc se dévoile du bas, comme un store qu\'on déroule, et son contenu monte derrière.', 'blocs-creator' ),
				'parties'     => true,
			),
			'signature'   => array(
				'libelle'     => __( 'Signature', 'blocs-creator' ),
				'description' => __( 'Un balayage de la gauche vers la droite découvre le bloc, comme un coup de pinceau. Le contenu se fond ensuite.', 'blocs-creator' ),
				'parties'     => true,
			),
			'pastilles'   => array(
				'libelle'     => __( 'Pastilles', 'blocs-creator' ),
				'description' => __( 'Chaque partie apparaît petite et se pose, en cascade rapide. Ce qu\'il faut pour une grille de cartes.', 'blocs-creator' ),
				'parties'     => true,
			),
			'souffle'     => array(
				'libelle'     => __( 'Souffle', 'blocs-creator' ),
				'description' => __( 'Le bloc arrive flou et trop grand, puis se pose net. Le contenu suit en fondu lent.', 'blocs-creator' ),
				'parties'     => true,
			),
			'bascule'     => array(
				'libelle'     => __( 'Bascule', 'blocs-creator' ),
				'description' => __( 'Le bloc bascule vers vous depuis sa base, en perspective, et son contenu monte.', 'blocs-creator' ),
				'parties'     => true,
			),
			'ressort'     => array(
				'libelle'     => __( 'Ressort', 'blocs-creator' ),
				'description' => __( 'Le bloc dépasse sa place et y revient. Le plus appuyé : un par page suffit.', 'blocs-creator' ),
				'parties'     => true,
			),
			'composee'    => array(
				'libelle'     => __( 'Composée — le dessin décide', 'blocs-creator' ),
				'description' => __( 'Chaque partie entre à sa façon, celle que le fichier de dessin lui donne : le texte monte pendant que l\'image s\'installe, deux colonnes se croisent, un trait se peint. C\'est la plus fidèle quand le bloc a été dessiné pour ça.', 'blocs-creator' ),
				'parties'     => true,
			),
		);

		/**
		 * Filtre les scénarios d'apparition.
		 *
		 * Un thème qui en ajoute un doit aussi écrire ses règles CSS, sur le
		 * sélecteur `[data-bc-anim="<identifiant>"]`.
		 *
		 * @param array $scenarios Les scénarios.
		 */
		return apply_filters( 'blocs_creator_scenarios_animation', $scenarios );
	}

	/**
	 * Le scénario existe-t-il ?
	 *
	 * @param string $nom Identifiant du scénario.
	 * @return bool
	 */
	public static function scenario_existe( $nom ) {
		return '' !== (string) $nom && array_key_exists( $nom, self::scenarios() );
	}

	/**
	 * L'apparition est-elle proposée à ce bloc ?
	 *
	 * @param string $nom Nom complet du bloc.
	 * @return bool
	 */
	public static function concerne( $nom ) {
		$nom = (string) $nom;

		if ( '' === $nom || in_array( $nom, self::EXCLUS, true ) ) {
			return false;
		}

		/**
		 * Filtre l'accès d'un bloc au réglage d'apparition.
		 *
		 * @param bool   $concerne Le bloc est-il concerné.
		 * @param string $nom      Nom complet du bloc.
		 */
		return (bool) apply_filters( 'blocs_creator_animation_concerne', true, $nom );
	}

	/* ------------------------------------------------------------------ *
	 * Qui anime quoi
	 * ------------------------------------------------------------------ */

	/**
	 * Retourne les apparitions en vigueur, par nom de bloc.
	 *
	 * Deux sources, et l'ordre compte : la définition d'abord — c'est le bloc
	 * lui-même qui le dit —, l'option ensuite, pour les blocs qu'on n'a pas
	 * créés et qui n'ont donc pas de définition où l'inscrire.
	 *
	 * @return array<string, array{nom:string, duree:int}>
	 */
	public static function carte() {
		if ( null !== self::$carte ) {
			return self::$carte;
		}

		$carte = array();

		foreach ( (array) get_option( self::OPTION, array() ) as $bloc => $reglage ) {
			$propre = self::assainir_reglage( $reglage );

			if ( '' !== $propre['nom'] ) {
				$carte[ (string) $bloc ] = $propre;
			}
		}

		if ( did_action( 'init' ) ) {
			foreach ( blocs_creator()->registre->generes() as $nom => $definition ) {
				$propre = self::assainir_reglage(
					array(
						'nom'   => $definition['animation'] ?? '',
						'duree' => $definition['animation_duree'] ?? 0,
					)
				);

				if ( '' !== $propre['nom'] ) {
					$carte[ $nom ] = $propre;
				} else {
					unset( $carte[ $nom ] );
				}
			}
		}

		self::$carte = $carte;

		return self::$carte;
	}

	/**
	 * Retourne l'apparition d'un bloc, ou null.
	 *
	 * @param string $nom Nom complet du bloc.
	 * @return array{nom:string, duree:int}|null
	 */
	public static function pour( $nom ) {
		$carte = self::carte();

		return $carte[ (string) $nom ] ?? null;
	}

	/**
	 * Nettoie un réglage d'apparition.
	 *
	 * @param mixed $reglage Réglage brut.
	 * @return array{nom:string, duree:int}
	 */
	public static function assainir_reglage( $reglage ) {
		if ( is_string( $reglage ) ) {
			$reglage = array( 'nom' => $reglage );
		}

		$reglage = (array) $reglage;
		$nom     = sanitize_key( (string) ( $reglage['nom'] ?? '' ) );
		$duree   = (int) ( $reglage['duree'] ?? 0 );

		return array(
			'nom'   => self::scenario_existe( $nom ) ? $nom : '',
			'duree' => $duree > 0 ? min( 3000, max( 200, $duree ) ) : 0,
		);
	}

	/**
	 * Déclare l'option auprès de l'API des réglages.
	 *
	 * Elle voyage dans le même groupe que les autres réglages : l'écran n'a
	 * donc qu'un formulaire et qu'un bouton, quel que soit l'onglet ouvert.
	 */
	public static function declarer() {
		register_setting(
			'blocs_creator_reglages',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'assainir_option' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Nettoie les apparitions soumises par l'écran des réglages.
	 *
	 * Les blocs créés ici n'y figurent jamais : leur apparition vit dans leur
	 * définition, et deux endroits pour le même réglage, c'est un endroit de
	 * trop.
	 *
	 * @param mixed $valeurs Valeurs brutes.
	 * @return array<string, array{nom:string, duree:int}>
	 */
	public static function assainir_option( $valeurs ) {
		$valeurs = (array) $valeurs;
		$ajout   = isset( $valeurs['__ajout'] ) ? (array) $valeurs['__ajout'] : array();

		unset( $valeurs['__ajout'] );

		if ( ! empty( $ajout['bloc'] ) ) {
			$valeurs[ (string) $ajout['bloc'] ] = $ajout;
		}

		$generes = did_action( 'init' ) ? blocs_creator()->registre->generes() : array();
		$propres = array();

		foreach ( $valeurs as $bloc => $reglage ) {
			$bloc = (string) $bloc;

			if ( ! preg_match( '#^[a-zA-Z0-9-]+/[a-zA-Z0-9-]+$#', $bloc ) || isset( $generes[ $bloc ] ) ) {
				continue;
			}

			$propre = self::assainir_reglage( $reglage );

			if ( '' !== $propre['nom'] ) {
				$propres[ $bloc ] = $propre;
			}
		}

		ksort( $propres );

		self::$carte = null;

		return $propres;
	}

	/**
	 * Écrit les apparitions envoyées par l'écran des réglages.
	 *
	 * Même raison que pour BC_Reglages : l'écran n'envoie plus son formulaire
	 * à `options.php`, il appelle son propre gestionnaire.
	 *
	 * @param array $valeurs Valeurs brutes du formulaire.
	 */
	public static function enregistrer_depuis_formulaire( $valeurs ) {
		$attendu = self::assainir_option( (array) $valeurs );

		update_option( self::OPTION, $attendu );

		wp_cache_delete( self::OPTION, 'options' );

		self::$carte = null;

		// Même filet que pour les réglages : si la ligne n'a pas bougé, on
		// l'écrit directement. Voir BC_Diagnostic::forcer().
		if ( (array) get_option( self::OPTION, array() ) !== $attendu ) {
			BC_Diagnostic::forcer( self::OPTION, $attendu );

			self::$carte = null;

			BC_Diagnostic::noter(
				array( 'quoi' => 'apparitions : écriture directe (la voie normale n\'a rien écrit)' )
			);
		}
	}

	/**
	 * Retourne les blocs qui peuvent recevoir une apparition ici.
	 *
	 * C'est-à-dire tous ceux qu'on n'a pas créés : les autres se règlent sur
	 * leur propre écran.
	 *
	 * @return array<string, string> Titre, par nom de bloc.
	 */
	public static function blocs_reglables() {
		$generes  = blocs_creator()->registre->generes();
		$reglables = array();

		foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $nom => $type ) {
			$nom = (string) $nom;

			if ( isset( $generes[ $nom ] ) || ! self::concerne( $nom ) ) {
				continue;
			}

			// Un bloc qui n'existe qu'à l'intérieur d'un autre entre avec lui :
			// lui donner sa propre apparition, c'est animer deux fois.
			if ( ! empty( $type->parent ) || ! empty( $type->ancestor ) ) {
				continue;
			}

			$reglables[ $nom ] = (string) ( $type->title ? $type->title : $nom );
		}

		asort( $reglables );

		return $reglables;
	}

	/**
	 * Écrit les apparitions des blocs qu'on n'a pas créés.
	 *
	 * @param array $valeurs Réglage, par nom de bloc.
	 */
	public static function enregistrer( $valeurs ) {
		$propres = array();

		foreach ( (array) $valeurs as $bloc => $reglage ) {
			$bloc = (string) $bloc;

			if ( ! preg_match( '#^[a-zA-Z0-9-]+/[a-zA-Z0-9-]+$#', $bloc ) ) {
				continue;
			}

			$propre = self::assainir_reglage( $reglage );

			if ( '' !== $propre['nom'] ) {
				$propres[ $bloc ] = $propre;
			}
		}

		self::$carte = null;

		update_option( self::OPTION, $propres );
	}

	/**
	 * Oublie la carte des apparitions.
	 */
	public static function vider_cache() {
		self::$carte = null;
	}

	/* ------------------------------------------------------------------ *
	 * Rendu
	 * ------------------------------------------------------------------ */

	/**
	 * Pose la classe et le scénario sur le bloc rendu.
	 *
	 * @param string $html HTML du bloc.
	 * @param array  $bloc Bloc analysé.
	 * @return string
	 */
	public static function rendre( $html, $bloc ) {
		$nom = (string) ( $bloc['blockName'] ?? '' );

		if ( '' === $nom || '' === trim( $html ) ) {
			return $html;
		}

		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $html;
		}

		$apparition = self::pour( $nom );

		if ( null === $apparition ) {
			return $html;
		}

		$processeur = new WP_HTML_Tag_Processor( $html );

		if ( ! $processeur->next_tag() ) {
			return $html;
		}

		$processeur->add_class( 'bc-anim' );
		$processeur->set_attribute( 'data-bc-anim', $apparition['nom'] );

		if ( $apparition['duree'] > 0 ) {
			$style = (string) $processeur->get_attribute( 'style' );
			$style = '' !== trim( $style ) ? rtrim( $style, '; ' ) . ';' : '';

			$processeur->set_attribute( 'style', $style . sprintf( '--bc-anim-duree:%dms;', $apparition['duree'] ) );
		}

		return $processeur->get_updated_html();
	}

	/* ------------------------------------------------------------------ *
	 * Assets
	 * ------------------------------------------------------------------ */

	/**
	 * Le script d'en-tête : il décide si l'on cache quoi que ce soit.
	 *
	 * Il doit s'exécuter avant le premier rendu de la page, sans quoi les blocs
	 * s'afficheraient une fraction de seconde avant de disparaître pour
	 * réapparaître. C'est la seule raison d'un script en ligne ici.
	 *
	 * Le filet de sécurité est aussi important que l'animation : si le script
	 * principal ne se charge pas, la page se démasque toute seule au bout de
	 * quatre secondes. Une page blanche est un prix trop élevé pour une
	 * apparition.
	 */
	public static function amorce() {
		if ( is_admin() || ! self::carte() ) {
			return;
		}

		echo '<script id="bc-anim-amorce">' .
			'(function(d,w){try{if(w.matchMedia&&w.matchMedia("(prefers-reduced-motion: reduce)").matches){return;}}catch(e){return;}' .
			'var r=d.documentElement;r.classList.add("bc-anim-prete");' .
			'w.bcAnimFilet=w.setTimeout(function(){r.classList.remove("bc-anim-prete");},4000);}(document,window));' .
			'</script>' . "\n";
	}

	/**
	 * Met en file la feuille et le script du site.
	 */
	public static function assets_site() {
		if ( is_admin() || ! self::carte() ) {
			return;
		}

		wp_enqueue_style(
			'blocs-creator-animations',
			BLOCS_CREATOR_URL . 'assets/css/animations.css',
			array(),
			self::version( 'assets/css/animations.css' )
		);

		wp_enqueue_script(
			'blocs-creator-animations',
			BLOCS_CREATOR_URL . 'assets/js/animations.js',
			array(),
			self::version( 'assets/js/animations.js' ),
			true
		);
	}

	/**
	 * Met en file l'aperçu d'apparition des écrans d'administration.
	 *
	 * L'aperçu charge la feuille du site : ce qu'on voit en choisissant est
	 * littéralement ce que le visiteur verra.
	 */
	public static function assets_admin() {
		wp_enqueue_style(
			'blocs-creator-animations',
			BLOCS_CREATOR_URL . 'assets/css/animations.css',
			array(),
			self::version( 'assets/css/animations.css' )
		);

		wp_enqueue_script(
			'blocs-creator-apercu-animation',
			BLOCS_CREATOR_URL . 'admin/js/apercu-animation.js',
			array(),
			self::version( 'admin/js/apercu-animation.js' ),
			true
		);
	}

	/**
	 * Retourne une version d'asset basée sur la date du fichier.
	 *
	 * @param string $chemin Chemin relatif à la racine du plugin.
	 * @return string
	 */
	private static function version( $chemin ) {
		$fichier = BLOCS_CREATOR_DIR . ltrim( $chemin, '/' );

		return file_exists( $fichier ) ? (string) filemtime( $fichier ) : BLOCS_CREATOR_VERSION;
	}

	/* ------------------------------------------------------------------ *
	 * L'aperçu
	 * ------------------------------------------------------------------ */

	/**
	 * Affiche le champ de choix d'une apparition, et son aperçu.
	 *
	 * Le même contrôle sert l'écran d'un bloc et l'écran des réglages : une
	 * seule façon de choisir une apparition, donc une seule à apprendre.
	 *
	 * @param string $id      Identifiant HTML du champ.
	 * @param string $nom     Attribut `name` du champ.
	 * @param string $courant Scénario retenu.
	 */
	public static function champ( $id, $nom, $courant ) {
		$scenarios = self::scenarios();
		?>
		<div class="bc-apparition" data-bc-apparition>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $nom ); ?>" class="widefat" data-bc-apparition-choix>
				<option value=""><?php esc_html_e( 'Aucune — le bloc est là, tout simplement', 'blocs-creator' ); ?></option>
				<?php foreach ( $scenarios as $bc_valeur => $bc_scenario ) : ?>
					<option value="<?php echo esc_attr( $bc_valeur ); ?>"
						data-bc-description="<?php echo esc_attr( $bc_scenario['description'] ); ?>"
						<?php selected( $courant, $bc_valeur ); ?>>
						<?php echo esc_html( $bc_scenario['libelle'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<p class="bc-aide" data-bc-apparition-description>
				<?php echo esc_html( (string) ( $scenarios[ $courant ]['description'] ?? '' ) ); ?>
			</p>

			<div class="bc-apercu" data-bc-apercu hidden>
				<div class="bc-apercu__scene">
					<div class="bc-apercu__bloc bc-anim" data-bc-apercu-bloc>
						<span class="bc-apercu__titre" data-bc-part></span>
						<span class="bc-apercu__ligne" data-bc-part></span>
						<span class="bc-apercu__ligne bc-apercu__ligne--courte" data-bc-part></span>
						<span class="bc-apercu__grille" data-bc-part>
							<span></span><span></span><span></span>
						</span>
					</div>
				</div>

				<button type="button" class="button button-small" data-bc-apercu-rejouer>
					<span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span>
					<?php esc_html_e( 'Rejouer', 'blocs-creator' ); ?>
				</button>
			</div>
		</div>
		<?php
	}
}
