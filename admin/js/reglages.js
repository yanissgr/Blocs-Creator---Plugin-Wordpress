/*
 * L'écran des réglages : ses onglets, et la liste des blocs disponibles.
 *
 * Les trois sections sont dans un seul formulaire, et y restent : les onglets
 * ne font que masquer, jamais retirer. C'est ce qui permet de cocher des blocs
 * dans l'un, de changer une apparition dans l'autre, et de n'appuyer qu'une
 * fois sur « Enregistrer ».
 *
 * Deux choses méritent d'être lues avant de toucher à ce fichier.
 *
 * 1. L'ONGLET OUVERT VOYAGE AVEC LE FORMULAIRE. Il est inscrit dans l'adresse
 *    (pour qu'un signet et un rechargement le retrouvent) et dans un champ
 *    caché, que le gestionnaire d'enregistrement relit pour revenir au bon
 *    endroit. Sans lui, on repart toujours sur le premier onglet et l'on croit
 *    que rien n'a été enregistré.
 *
 * 2. LA LISTE DES BLOCS PART EN UN SEUL CHAMP. Au moment de l'envoi, les cases
 *    sont mises hors circuit et remplacées par `blocs_ecartes`, qui porte la
 *    liste complète. Cent trente champs dépasseraient `max_input_vars` sur un
 *    site fourni, et le formulaire arriverait tronqué : des blocs qu'on croit
 *    avoir écartés reviendraient, et l'enregistrement aurait l'air de ne rien
 *    faire. Sans JavaScript, les cases repartent comme avant.
 *
 * Écrit en JavaScript natif, sans dépendance : le plugin s'installe partout
 * sans outil de build.
 */

( function ( window, document ) {
	'use strict';

	/** Le champ qui portera la liste des blocs écartés, à l'envoi. */
	var CHAMP_ECARTES = 'blocs_ecartes';

	/**
	 * Retourne le préfixe des champs du formulaire — le nom de l'option.
	 *
	 * On le lit sur un champ existant plutôt que de l'écrire ici : c'est le PHP
	 * qui décide comment l'option s'appelle.
	 *
	 * @return {string} Par exemple « blocs_creator_reglages ».
	 */
	function prefixe() {
		var connus = document.querySelector( '[name$="[blocs_connus]"]' );

		if ( ! connus ) {
			return '';
		}

		return connus.getAttribute( 'name' ).replace( '[blocs_connus]', '' );
	}

	/* ------------------------------------------------------------------ *
	 * Les onglets
	 * ------------------------------------------------------------------ */

	function onglets() {
		var boutons = document.querySelectorAll( '[data-bc-onglet]' );

		if ( ! boutons.length ) {
			return;
		}

		function ouvrir( cible ) {
			Array.prototype.forEach.call( boutons, function ( autre ) {
				autre.classList.toggle(
					'nav-tab-active',
					autre.getAttribute( 'data-bc-onglet' ) === cible
				);
			} );

			Array.prototype.forEach.call(
				document.querySelectorAll( '[data-bc-panneau]' ),
				function ( panneau ) {
					panneau.hidden = panneau.getAttribute( 'data-bc-panneau' ) !== cible;
				}
			);

			retenir( cible );
		}

		/**
		 * Inscrit l'onglet ouvert dans l'adresse, et dans le retour du
		 * formulaire.
		 *
		 * @param {string} cible Identifiant de l'onglet.
		 */
		function retenir( cible ) {
			var adresse;

			try {
				adresse = new window.URL( window.location.href );
			} catch ( e ) {
				return;
			}

			adresse.searchParams.set( 'bc_onglet', cible );

			window.history.replaceState( null, '', adresse.toString() );

			var champ = document.querySelector( '[data-bc-onglet-champ]' );

			if ( champ ) {
				champ.value = cible;
			}
		}

		Array.prototype.forEach.call( boutons, function ( bouton ) {
			bouton.addEventListener( 'click', function () {
				ouvrir( bouton.getAttribute( 'data-bc-onglet' ) );
			} );
		} );

		var actif = document.querySelector( '[data-bc-onglet].nav-tab-active' );

		if ( actif ) {
			retenir( actif.getAttribute( 'data-bc-onglet' ) );
		}
	}

	/* ------------------------------------------------------------------ *
	 * La liste des blocs
	 * ------------------------------------------------------------------ */

	/**
	 * Les cases d'un groupe, sans celles qu'on ne peut pas décocher.
	 *
	 * @param {Element} groupe Le groupe.
	 * @param {boolean} visibles N'en prendre que les lignes affichées.
	 * @return {Element[]} Les cases.
	 */
	function cases( groupe, visibles ) {
		var selecteur = visibles
			? '.bc-blocs__item:not([hidden]) input[type="checkbox"]:not(:disabled)'
			: '.bc-blocs__item input[type="checkbox"]:not(:disabled)';

		return Array.prototype.slice.call( groupe.querySelectorAll( selecteur ) );
	}

	/**
	 * Met à jour les compteurs : celui de chaque groupe, celui du bilan, et la
	 * pastille de l'onglet. Un chiffre faux est pire que pas de chiffre.
	 */
	function compter() {
		var total = 0;
		var ecartes = 0;

		Array.prototype.forEach.call(
			document.querySelectorAll( '.bc-groupe-blocs' ),
			function ( groupe ) {
				var toutes = cases( groupe, false );
				var cochees = toutes.filter( function ( c ) {
					return c.checked;
				} );

				var proteges = groupe.querySelectorAll( '.bc-blocs__item.est-protege' ).length;
				var compte = groupe.querySelector( '[data-bc-compte]' );

				total += toutes.length + proteges;
				ecartes += toutes.length - cochees.length;

				if ( compte ) {
					compte.textContent = compte.textContent.replace(
						/^\s*\S+/,
						String( cochees.length + proteges )
					);
				}
			}
		);

		var bilan = document.querySelector( '[data-bc-bilan]' );

		if ( bilan ) {
			var modele = 1 === ecartes
				? bilan.getAttribute( 'data-bc-modele-un' )
				: bilan.getAttribute( 'data-bc-modele-plusieurs' );

			bilan.innerHTML = ( modele || '' )
				.replace( '%1$s', '<strong>' + total + '</strong>' )
				.replace( '%2$s', '<strong>' + ecartes + '</strong>' );
		}
	}

	/**
	 * Plie et déplie un groupe.
	 */
	function plier() {
		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-bc-plier]' ),
			function ( bouton ) {
				bouton.addEventListener( 'click', function () {
					var groupe = bouton.closest( '.bc-groupe-blocs' );
					var ouvert = 'true' === bouton.getAttribute( 'aria-expanded' );

					if ( ! groupe ) {
						return;
					}

					bouton.setAttribute( 'aria-expanded', ouvert ? 'false' : 'true' );
					groupe.classList.toggle( 'est-plie', ouvert );

					var chevron = bouton.querySelector( '.bc-groupe-blocs__chevron' );

					if ( chevron ) {
						chevron.classList.toggle( 'dashicons-arrow-up-alt2', ! ouvert );
						chevron.classList.toggle( 'dashicons-arrow-down-alt2', ouvert );
					}
				} );
			}
		);
	}

	function groupes() {
		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-bc-tout]' ),
			function ( bouton ) {
				bouton.addEventListener( 'click', function () {
					var groupe = bouton.closest( '.bc-groupe-blocs' );
					var coche = '1' === bouton.getAttribute( 'data-bc-tout' );

					if ( ! groupe ) {
						return;
					}

					cases( groupe, true ).forEach( function ( case_a_cocher ) {
						case_a_cocher.checked = coche;
						marquer( case_a_cocher );
					} );

					compter();
				} );
			}
		);
	}

	/**
	 * Inscrit sur la ligne l'état de sa case, pour que le tri le voie.
	 *
	 * @param {Element} case_a_cocher La case.
	 */
	function marquer( case_a_cocher ) {
		var ligne = case_a_cocher.closest( '.bc-blocs__item' );

		if ( ! ligne || ligne.classList.contains( 'est-protege' ) ) {
			return;
		}

		ligne.setAttribute( 'data-bc-etat', case_a_cocher.checked ? 'disponible' : 'ecarte' );
	}

	/**
	 * Filtre la liste sur la saisie ET sur la vue choisie, groupe par groupe.
	 *
	 * Un groupe dont plus rien ne correspond se masque entièrement : laisser un
	 * titre de section seul au-dessus du vide ne dit rien à personne.
	 */
	function filtre() {
		var champ = document.getElementById( 'bc-filtre-blocs' );
		var segments = document.querySelectorAll( '[data-bc-vue]' );
		var vue = 'tous';

		function appliquer() {
			var terme = champ ? champ.value.trim().toLowerCase() : '';

			Array.prototype.forEach.call(
				document.querySelectorAll( '.bc-groupe-blocs' ),
				function ( groupe ) {
					var visibles = 0;

					Array.prototype.forEach.call(
						groupe.querySelectorAll( '.bc-blocs__item' ),
						function ( item ) {
							var cherche = item.getAttribute( 'data-bc-cherche' ) || '';
							var etat = item.getAttribute( 'data-bc-etat' ) || 'disponible';
							var trouve = ( '' === terme || -1 !== cherche.indexOf( terme ) ) &&
								( 'tous' === vue || etat === vue );

							item.hidden = ! trouve;

							if ( trouve ) {
								visibles += 1;
							}
						}
					);

					groupe.hidden = 0 === visibles;

					var vide = groupe.querySelector( '.bc-groupe-blocs__vide' );

					if ( vide ) {
						vide.hidden = visibles > 0;
					}
				}
			);
		}

		if ( champ ) {
			champ.addEventListener( 'input', appliquer );
		}

		Array.prototype.forEach.call( segments, function ( segment ) {
			segment.addEventListener( 'click', function () {
				vue = segment.getAttribute( 'data-bc-vue' );

				Array.prototype.forEach.call( segments, function ( autre ) {
					autre.classList.toggle( 'est-actif', autre === segment );
				} );

				appliquer();
			} );
		} );

		Array.prototype.forEach.call(
			document.querySelectorAll( '.bc-blocs input[type="checkbox"]' ),
			function ( case_a_cocher ) {
				case_a_cocher.addEventListener( 'change', function () {
					marquer( case_a_cocher );
					compter();
				} );
			}
		);
	}

	/* ------------------------------------------------------------------ *
	 * L'envoi
	 * ------------------------------------------------------------------ */

	/**
	 * Remplace les cases par un seul champ au moment de l'envoi.
	 *
	 * C'est fait ici, et pas plus tôt, pour que la page reste utilisable telle
	 * quelle : on coche, on décoche, on cherche — et ce n'est qu'en appuyant
	 * sur « Enregistrer » que la liste se compose.
	 */
	function envoi() {
		var formulaire = document.querySelector( '[data-bc-formulaire]' );
		var racine = prefixe();

		if ( ! formulaire || '' === racine ) {
			return;
		}

		formulaire.addEventListener( 'submit', function () {
			var ecartes = [];

			Array.prototype.forEach.call(
				document.querySelectorAll( '.bc-blocs input[type="checkbox"]' ),
				function ( case_a_cocher ) {
					if ( ! case_a_cocher.checked && ! case_a_cocher.disabled ) {
						ecartes.push( case_a_cocher.value );
					}

					// Hors circuit : la case n'est plus envoyée, le champ
					// unique ci-dessous fait foi.
					case_a_cocher.disabled = true;
				}
			);

			Array.prototype.forEach.call(
				document.querySelectorAll( '.bc-blocs input[type="hidden"]' ),
				function ( cache ) {
					cache.disabled = true;
				}
			);

			var champ = document.createElement( 'input' );

			champ.type = 'hidden';
			champ.name = racine + '[' + CHAMP_ECARTES + ']';
			champ.value = ecartes.join( ',' );

			formulaire.appendChild( champ );
		} );
	}

	/**
	 * Déplie et amène à l'écran un panneau qu'un lien désigne.
	 *
	 * Sert le diagnostic : il vit au bas d'une page très longue, et le lien qui
	 * y mène est dans la barre collante. Sans le déplier ni y descendre, le
	 * lien ne ferait rien de visible — exactement le défaut qu'il est censé
	 * aider à comprendre.
	 */
	function ouvreurs() {
		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-bc-ouvrir]' ),
			function ( bouton ) {
				bouton.addEventListener( 'click', function () {
					var cible = document.querySelector( bouton.getAttribute( 'data-bc-ouvrir' ) );

					if ( ! cible ) {
						return;
					}

					var boite = cible.querySelector( 'details' );

					if ( boite ) {
						boite.open = true;
					}

					cible.scrollIntoView( { block: 'start', behavior: 'smooth' } );
				} );
			}
		);
	}

	function demarrer() {
		onglets();
		ouvreurs();
		plier();
		groupes();
		filtre();
		compter();
		envoi();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', demarrer );
	} else {
		demarrer();
	}
}( window, document ) );
