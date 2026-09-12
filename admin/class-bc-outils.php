<?php
/**
 * Import et export des définitions.
 *
 * C'est ce qui rend le plugin réutilisable pour de vrai : un bloc mis au point
 * sur un site se rapporte en deux gestes sur le suivant. L'export ne contient
 * que les définitions — le gabarit, lui, est un fichier du thème, et on le
 * copie comme n'importe quel fichier de thème.
 *
 * Le JSON importé est traité comme une saisie d'utilisateur : il repasse
 * entièrement par BC_Definition::normaliser(), qui jette ce qu'il ne connaît
 * pas. Un fichier trafiqué ne peut donc pas déclarer autre chose que des
 * champs du catalogue.
 *
 * @package BlocsCreator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Les outils d'import et d'export.
 */
class BC_Outils {

	/**
	 * Branche les hooks.
	 */
	public function demarrer() {
		add_action( 'admin_post_bc_exporter', array( $this, 'exporter' ) );
		add_action( 'admin_post_bc_importer', array( $this, 'importer' ) );
	}

	/**
	 * Affiche l'écran des outils.
	 */
	public static function page() {
		$definitions = BC_Definition::toutes();

		include BLOCS_CREATOR_DIR . 'admin/vues/outils.php';
	}

	/**
	 * Envoie un fichier JSON au navigateur.
	 */
	public function exporter() {
		if ( ! current_user_can( BC_Admin::CAP ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ?? '' ), 'bc_exporter' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'blocs-creator' ), 403 );
		}

		$demandes = isset( $_REQUEST['blocs'] ) ? wp_unslash( $_REQUEST['blocs'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$demandes = is_array( $demandes ) ? $demandes : explode( ',', (string) $demandes );
		$demandes = array_filter( array_map( 'absint', $demandes ) );

		$blocs = array();

		foreach ( BC_Definition::toutes() as $definition ) {
			if ( ! empty( $demandes ) && ! in_array( (int) $definition['id'], $demandes, true ) ) {
				continue;
			}

			$blocs[] = BC_Definition::vers_tableau( $definition );
		}

		if ( empty( $blocs ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'       => BC_Admin::PAGE . '-outils',
						'bc_message' => 'erreur',
						'bc_texte'   => rawurlencode( __( 'Aucun bloc à exporter.', 'blocs-creator' ) ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$paquet = array(
			'plugin'     => 'blocs-creator',
			'version'    => BLOCS_CREATOR_VERSION,
			'exporte_le' => gmdate( 'c' ),
			'site'       => home_url(),
			'blocs'      => $blocs,
		);

		$nom = sprintf(
			'blocs-creator-%s-%s.json',
			sanitize_title( get_bloginfo( 'name' ) ),
			gmdate( 'Y-m-d' )
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $nom . '"' );

		echo wp_json_encode( $paquet, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Lit un fichier ou un collage JSON, et crée les blocs.
	 */
	public function importer() {
		if ( ! current_user_can( BC_Admin::CAP ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'bc_importer' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'blocs-creator' ), 403 );
		}

		$json = '';

		if ( ! empty( $_FILES['fichier']['tmp_name'] ) && UPLOAD_ERR_OK === (int) $_FILES['fichier']['error'] ) {
			$chemin = sanitize_text_field( $_FILES['fichier']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

			if ( is_uploaded_file( $chemin ) ) {
				$json = (string) file_get_contents( $chemin ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}

		if ( '' === $json && ! empty( $_POST['json'] ) ) {
			$json = (string) wp_unslash( $_POST['json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}

		$paquet = json_decode( $json, true );

		if ( ! is_array( $paquet ) ) {
			$this->retour( 'erreur', __( 'Ce fichier n\'est pas du JSON lisible.', 'blocs-creator' ) );
		}

		// On accepte aussi bien un paquet complet qu'une définition seule ou
		// une liste de définitions : c'est ce qu'on a sous la main quand on
		// recopie un bloc à la volée.
		if ( isset( $paquet['blocs'] ) && is_array( $paquet['blocs'] ) ) {
			$blocs = $paquet['blocs'];
		} elseif ( isset( $paquet['champs'] ) ) {
			$blocs = array( $paquet );
		} else {
			$blocs = $paquet;
		}

		$ecrase = ! empty( $_POST['ecraser'] );
		$faits  = 0;
		$sautes = 0;

		foreach ( (array) $blocs as $brut ) {
			if ( ! is_array( $brut ) || empty( $brut['titre'] ) ) {
				++$sautes;
				continue;
			}

			$definition = BC_Definition::normaliser( $brut );
			$existant   = $this->existant( $definition );

			if ( $existant > 0 && ! $ecrase ) {
				++$sautes;
				continue;
			}

			$resultat = BC_Definition::enregistrer( $definition, $existant );

			if ( is_wp_error( $resultat ) ) {
				++$sautes;
				continue;
			}

			++$faits;
		}

		if ( 0 === $faits ) {
			$this->retour(
				'erreur',
				__( 'Aucun bloc importé. Les blocs du fichier existent peut-être déjà — cochez « Remplacer » pour les écraser.', 'blocs-creator' )
			);
		}

		$this->retour(
			'succes',
			sprintf(
				/* translators: 1: nombre de blocs importés, 2: nombre de blocs ignorés. */
				_n( '%1$d bloc importé, %2$d ignoré.', '%1$d blocs importés, %2$d ignorés.', $faits, 'blocs-creator' ),
				$faits,
				$sautes
			)
		);
	}

	/**
	 * Retourne l'identifiant d'un bloc de même nom, ou 0.
	 *
	 * @param array $definition La définition importée.
	 * @return int
	 */
	private function existant( $definition ) {
		$nom = BC_Definition::nom( $definition );

		foreach ( BC_Definition::toutes() as $autre ) {
			if ( BC_Definition::nom( $autre ) === $nom ) {
				return (int) $autre['id'];
			}
		}

		return 0;
	}

	/**
	 * Renvoie vers l'écran des outils avec un message.
	 *
	 * @param string $type  `succes` ou `erreur`.
	 * @param string $texte Le message.
	 */
	private function retour( $type, $texte ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => BC_Admin::PAGE . '-outils',
					'bc_message' => $type,
					'bc_texte'   => rawurlencode( $texte ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
