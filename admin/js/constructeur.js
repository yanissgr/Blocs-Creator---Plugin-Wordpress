/*
 * Le constructeur de champs.
 *
 * Écrit en JavaScript natif, sans dépendance ni outil de build : un plugin
 * qu'on installe sur d'autres sites ne doit pas y traîner un `npm install`.
 * Plus verbeux que du JSX, mais lisible et modifiable tel quel.
 *
 * L'état tient dans un tableau, `champs`. Tout passe par lui : l'affichage le
 * lit, les contrôles l'écrivent, et le champ caché du formulaire le reçoit en
 * JSON à l'enregistrement. Aucune requête, aucun aller-retour : on peut
 * ajouter douze champs et changer d'avis, rien n'est écrit avant Publier.
 *
 * Une seule règle de structure : la profondeur s'arrête à un cran. Un répéteur
 * contient des champs simples, jamais un autre répéteur. C'est arbitraire, et
 * c'est ce qui garde l'interface lisible et les gabarits écrivables.
 */

( function () {
	'use strict';

	var donnees = window.blocsCreatorAdmin || {};
	var __      = ( window.wp && window.wp.i18n ) ? window.wp.i18n.__ : function ( t ) { return t; };
	var sprintf = ( window.wp && window.wp.i18n ) ? window.wp.i18n.sprintf : function ( t ) { return t; };

	var racine;
	var sortie;
	var champs   = [];
	var ouverts  = {};
	var compteur = 0;

	/* ------------------------------------------------------------------ *
	 * Aides
	 * ------------------------------------------------------------------ */

	/**
	 * Crée un élément.
	 *
	 * @param {string} balise   Nom de la balise.
	 * @param {Object} attributs Attributs et propriétés.
	 * @param {Array|string|Node} enfants Contenu.
	 * @return {HTMLElement} L'élément.
	 */
	function el( balise, attributs, enfants ) {
		var noeud = document.createElement( balise );

		Object.keys( attributs || {} ).forEach( function ( cle ) {
			var valeur = attributs[ cle ];

			if ( null === valeur || false === valeur || undefined === valeur ) {
				return;
			}

			if ( 'class' === cle ) {
				noeud.className = valeur;
			} else if ( 'texte' === cle ) {
				noeud.textContent = valeur;
			} else if ( 0 === cle.indexOf( 'on' ) && 'function' === typeof valeur ) {
				noeud.addEventListener( cle.slice( 2 ).toLowerCase(), valeur );
			} else if ( true === valeur ) {
				noeud.setAttribute( cle, '' );
			} else {
				noeud.setAttribute( cle, valeur );
			}
		} );

		[].concat( enfants || [] ).forEach( function ( enfant ) {
			if ( ! enfant && 0 !== enfant ) {
				return;
			}

			noeud.appendChild( 'string' === typeof enfant ? document.createTextNode( enfant ) : enfant );
		} );

		return noeud;
	}

	/**
	 * Transforme un libellé en clé de champ.
	 *
	 * Les clés deviennent des noms d'attributs et des index de tableau dans
	 * les gabarits : minuscules, sans accent, soulignés plutôt que tirets,
	 * pour rester écrivables en PHP.
	 *
	 * @param {string} texte Le libellé.
	 * @return {string} La clé.
	 */
	function enCle( texte ) {
		var cle = ( texte || '' )
			.toLowerCase()
			.normalize( 'NFD' )
			.replace( /[\u0300-\u036f]/g, '' )
			.replace( /[^a-z0-9]+/g, '_' )
			.replace( /^_+|_+$/g, '' );

		if ( /^[0-9]/.test( cle ) ) {
			cle = 'champ_' + cle;
		}

		return cle;
	}

	/**
	 * Retourne une clé encore libre dans une liste.
	 *
	 * @param {string} base  Clé souhaitée.
	 * @param {Array}  liste Champs voisins.
	 * @param {Object} sauf  Champ à ignorer dans la comparaison.
	 * @return {string} La clé retenue.
	 */
	function cleLibre( base, liste, sauf ) {
		var cle    = base || 'champ';
		var prises = liste.filter( function ( c ) { return c !== sauf; } ).map( function ( c ) { return c.cle; } );
		var index  = 2;
		var essai  = cle;

		while ( prises.indexOf( essai ) !== -1 ) {
			essai = cle + '_' + index;
			index++;
		}

		return essai;
	}

	/**
	 * Retourne la description d'un type du catalogue.
	 *
	 * @param {string} type Identifiant du type.
	 * @return {Object|null} Le type.
	 */
	function leType( type ) {
		var trouve = null;

		( donnees.types || [] ).forEach( function ( candidat ) {
			if ( candidat.type === type ) {
				trouve = candidat;
			}
		} );

		return trouve;
	}

	/**
	 * Ce type propose-t-il ce réglage ?
	 *
	 * @param {string} type    Identifiant du type.
	 * @param {string} reglage Nom du réglage.
	 * @return {boolean} Vrai s'il le propose.
	 */
	function aReglage( type, reglage ) {
		var def = leType( type );

		return !! def && def.reglages.indexOf( reglage ) !== -1;
	}

	/* ------------------------------------------------------------------ *
	 * État
	 * ------------------------------------------------------------------ */

	/**
	 * Normalise un champ venu du JSON ou tout juste créé.
	 *
	 * @param {Object} brut Le champ.
	 * @return {Object} Le champ complet.
	 */
	function normaliser( brut ) {
		var champ = brut || {};

		compteur++;

		return {
			_id:          champ._id || 'c' + compteur,
			cle:          champ.cle || '',
			libelle:      champ.libelle || '',
			type:         champ.type || 'texte',
			aide:         champ.aide || '',
			emplacement:  'panneau' === champ.emplacement ? 'panneau' : 'bloc',
			largeur:      [ 33, 50, 100 ].indexOf( parseInt( champ.largeur, 10 ) ) !== -1 ? parseInt( champ.largeur, 10 ) : 100,
			options:      champ.options || {},
			sous_champs:  ( champ.sous_champs || [] ).map( normaliser )
		};
	}

	/**
	 * Écrit l'état dans le champ caché du formulaire.
	 */
	function synchroniser() {
		sortie.value = JSON.stringify( champs.map( nettoyer ) );
	}

	/**
	 * Retire du champ ce qui ne regarde que l'interface.
	 *
	 * @param {Object} champ Le champ.
	 * @return {Object} Le champ à enregistrer.
	 */
	function nettoyer( champ ) {
		var propre = {
			cle:         champ.cle,
			libelle:     champ.libelle,
			type:        champ.type,
			aide:        champ.aide,
			emplacement: champ.emplacement,
			largeur:     champ.largeur,
			options:     champ.options
		};

		if ( champ.sous_champs && champ.sous_champs.length ) {
			propre.sous_champs = champ.sous_champs.map( nettoyer );
		}

		return propre;
	}

	/**
	 * Redessine la liste et met le formulaire à jour.
	 */
	function rafraichir() {
		synchroniser();
		dessiner();
	}

	/* ------------------------------------------------------------------ *
	 * Dessin
	 * ------------------------------------------------------------------ */

	/**
	 * Redessine toute la liste.
	 */
	function dessiner() {
		var liste = racine.querySelector( '[data-role="liste"]' );

		liste.innerHTML = '';

		if ( ! champs.length ) {
			liste.appendChild(
				el( 'div', { class: 'bc-constructeur__vide' }, [
					el( 'p', { texte: __( 'Ce bloc n\'a encore aucun champ.', 'blocs-creator' ) } ),
					el( 'p', {
						class: 'bc-aide',
						texte: __( 'Un champ, c\'est une case à remplir dans l\'éditeur et une valeur disponible dans le gabarit.', 'blocs-creator' )
					} )
				] )
			);

			return;
		}

		champs.forEach( function ( champ, index ) {
			liste.appendChild( ligne( champ, index, champs, null ) );
		} );
	}

	/**
	 * Dessine la ligne d'un champ.
	 *
	 * @param {Object}      champ  Le champ.
	 * @param {number}      index  Sa position.
	 * @param {Array}       liste  La liste qui le contient.
	 * @param {Object|null} parent Le champ parent, pour un sous-champ.
	 * @return {HTMLElement} La ligne.
	 */
	function ligne( champ, index, liste, parent ) {
		var def     = leType( champ.type );
		var ouvert  = !! ouverts[ champ._id ];
		var enfants = [];

		enfants.push( entete( champ, index, liste, parent, def, ouvert ) );

		if ( ouvert ) {
			enfants.push( corps( champ, liste, parent, def ) );
		}

		return el(
			'div',
			{
				class: 'bc-champ-ligne' + ( ouvert ? ' est-ouvert' : '' ) + ( parent ? ' est-enfant' : '' ),
				'data-type': champ.type,
				'data-champ': champ._id
			},
			enfants
		);
	}

	/**
	 * Dessine l'en-tête repliable d'un champ.
	 *
	 * @param {Object}      champ  Le champ.
	 * @param {number}      index  Sa position.
	 * @param {Array}       liste  La liste qui le contient.
	 * @param {Object|null} parent Le champ parent.
	 * @param {Object|null} def    Le type du catalogue.
	 * @param {boolean}     ouvert Est-il déplié.
	 * @return {HTMLElement} L'en-tête.
	 */
	function entete( champ, index, liste, parent, def, ouvert ) {
		var titre = champ.libelle || __( 'Champ sans nom', 'blocs-creator' );

		var bascule = el( 'button', {
			type: 'button',
			class: 'bc-champ-ligne__bascule',
			'aria-expanded': ouvert ? 'true' : 'false',
			onClick: function () {
				ouverts[ champ._id ] = ! ouverts[ champ._id ];
				dessiner();
			}
		}, [
			el( 'span', { class: 'bc-champ-ligne__fleche dashicons dashicons-arrow-' + ( ouvert ? 'down' : 'right' ) + '-alt2', 'aria-hidden': 'true' } ),
			el( 'span', { class: 'bc-champ-ligne__titre', texte: titre } ),
			el( 'code', { class: 'bc-champ-ligne__cle', texte: champ.cle || '—' } ),
			el( 'span', { class: 'bc-champ-ligne__type', texte: def ? def.libelle : champ.type } )
		] );

		var outils = el( 'div', { class: 'bc-champ-ligne__outils' }, [
			bouton( 'arrow-up-alt2', __( 'Monter', 'blocs-creator' ), 0 === index, function () {
				deplacer( liste, index, -1 );
			} ),
			bouton( 'arrow-down-alt2', __( 'Descendre', 'blocs-creator' ), index === liste.length - 1, function () {
				deplacer( liste, index, 1 );
			} ),
			bouton( 'admin-page', __( 'Dupliquer', 'blocs-creator' ), false, function () {
				var copie = normaliser( JSON.parse( JSON.stringify( nettoyer( champ ) ) ) );

				copie.cle = cleLibre( champ.cle + '_copie', liste, null );
				liste.splice( index + 1, 0, copie );
				rafraichir();
			} ),
			bouton( 'trash', __( 'Supprimer', 'blocs-creator' ), false, function () {
				/* eslint-disable-next-line no-alert */
				if ( window.confirm( sprintf( __( 'Supprimer le champ « %s » ? Les valeurs déjà saisies dans les pages seront perdues.', 'blocs-creator' ), titre ) ) ) {
					liste.splice( index, 1 );
					rafraichir();
				}
			}, 'est-destructif' )
		] );

		return el( 'div', { class: 'bc-champ-ligne__entete' }, [ bascule, outils ] );
	}

	/**
	 * Dessine un bouton d'outil.
	 *
	 * @param {string}   icone   Nom du Dashicon.
	 * @param {string}   libelle Intitulé accessible.
	 * @param {boolean}  inerte  Bouton désactivé.
	 * @param {Function} action  Au clic.
	 * @param {string}   classe  Classe supplémentaire.
	 * @return {HTMLElement} Le bouton.
	 */
	function bouton( icone, libelle, inerte, action, classe ) {
		return el( 'button', {
			type: 'button',
			class: 'bc-outil ' + ( classe || '' ),
			title: libelle,
			'aria-label': libelle,
			disabled: inerte,
			onClick: action
		}, [
			el( 'span', { class: 'dashicons dashicons-' + icone, 'aria-hidden': 'true' } )
		] );
	}

	/**
	 * Déplace un champ dans sa liste.
	 *
	 * @param {Array}  liste Liste concernée.
	 * @param {number} index Position actuelle.
	 * @param {number} pas   -1 pour monter, 1 pour descendre.
	 */
	function deplacer( liste, index, pas ) {
		var cible = index + pas;

		if ( cible < 0 || cible >= liste.length ) {
			return;
		}

		var deplace = liste.splice( index, 1 )[ 0 ];

		liste.splice( cible, 0, deplace );
		rafraichir();
	}

	/**
	 * Dessine le corps déplié d'un champ.
	 *
	 * @param {Object}      champ  Le champ.
	 * @param {Array}       liste  La liste qui le contient.
	 * @param {Object|null} parent Le champ parent.
	 * @param {Object|null} def    Le type du catalogue.
	 * @return {HTMLElement} Le corps.
	 */
	function corps( champ, liste, parent, def ) {
		var blocs = [];

		blocs.push(
			el( 'div', { class: 'bc-grille' }, [
				texte( __( 'Libellé', 'blocs-creator' ), champ.libelle, function ( valeur ) {
					var cleAuto = enCle( champ.libelle );

					champ.libelle = valeur;

					// Tant que la clé n'a pas été touchée à la main, elle suit
					// le libellé. Dès qu'elle diverge, on n'y touche plus.
					if ( '' === champ.cle || champ.cle === cleAuto ) {
						champ.cle = cleLibre( enCle( valeur ), liste, champ );
					}

					synchroniser();
					majEntete( champ );
				}, __( 'Ce que la rédaction lira au-dessus du champ.', 'blocs-creator' ) ),

				texte( __( 'Clé', 'blocs-creator' ), champ.cle, function ( valeur, entree ) {
					champ.cle = cleLibre( enCle( valeur ), liste, champ );

					if ( entree.value !== champ.cle ) {
						entree.value = champ.cle;
					}

					synchroniser();
					majEntete( champ );
				}, __( 'Le nom sous lequel le gabarit lira la valeur.', 'blocs-creator' ) )
			] )
		);

		blocs.push(
			el( 'div', { class: 'bc-grille' }, [
				selection( __( 'Type', 'blocs-creator' ), champ.type, typesGroupes( parent ), function ( valeur ) {
					champ.type    = valeur;
					champ.options = {};

					if ( ! leType( valeur ) || ! leType( valeur ).sousChamps ) {
						champ.sous_champs = [];
					}

					rafraichir();
				}, def ? def.description : '' ),

				selection( __( 'Où le régler', 'blocs-creator' ), champ.emplacement, [
					{ value: 'bloc', label: __( 'Dans le bloc', 'blocs-creator' ) },
					{ value: 'panneau', label: __( 'Dans la colonne de droite', 'blocs-creator' ) }
				], function ( valeur ) {
					champ.emplacement = valeur;
					synchroniser();
				}, __( 'Ce qui s\'écrit se met dans le bloc ; ce qui ne se montre pas va dans la colonne.', 'blocs-creator' ) )
			] )
		);

		var reglages = reglagesDuType( champ );

		if ( reglages.length ) {
			blocs.push( el( 'div', { class: 'bc-grille' }, reglages ) );
		}

		blocs.push(
			texte( __( 'Texte d\'aide', 'blocs-creator' ), champ.aide, function ( valeur ) {
				champ.aide = valeur;
				synchroniser();
			}, __( 'Affiché sous le champ, dans l\'éditeur. Facultatif.', 'blocs-creator' ) )
		);

		if ( def && def.sousChamps ) {
			blocs.push( sousChamps( champ ) );
		}

		if ( ! parent && def && false !== def.porteValeur ) {
			blocs.push( appel( champ, def ) );
		}

		return el( 'div', { class: 'bc-champ-ligne__corps' }, blocs );
	}

	/**
	 * Rappelle comment le gabarit lira ce champ.
	 *
	 * C'est le pont entre les deux moitiés du plugin : ici on déclare, là-bas
	 * on dessine. Sans cette ligne, il faut ouvrir l'aide pour savoir sous quel
	 * nom la valeur arrive — et c'est le moment précis où l'on décroche.
	 *
	 * @param {Object} champ Le champ.
	 * @param {Object} def   Son type dans le catalogue.
	 * @return {HTMLElement} La ligne de rappel.
	 */
	function appel( champ, def ) {
		var cle = champ.cle || 'cle';
		var code;

		if ( def && def.sousChamps ) {
			code = "foreach ( blocs_creator_boucle( '" + cle + "' ) as $ligne ) { … }";
		} else if ( 'image' === champ.type ) {
			code = "echo blocs_creator_image( '" + cle + "' );";
		} else if ( 'lien' === champ.type ) {
			code = "<a <?php echo blocs_creator_lien_attrs( '" + cle + "' ); ?>>";
		} else {
			code = "blocs_creator_champ( '" + cle + "' )";
		}

		var bouton_copie = el( 'button', {
			type: 'button',
			class: 'button-link bc-champ-ligne__copier',
			texte: __( 'Copier', 'blocs-creator' ),
			onClick: function () {
				if ( window.navigator && window.navigator.clipboard ) {
					window.navigator.clipboard.writeText( code );
				}
			}
		} );

		return el( 'p', { class: 'bc-champ-ligne__appel' }, [
			el( 'span', { class: 'bc-aide', texte: __( 'Dans le gabarit :', 'blocs-creator' ) } ),
			el( 'code', { texte: code } ),
			bouton_copie
		] );
	}

	/**
	 * Met à jour l'en-tête d'un champ sans redessiner la liste.
	 *
	 * Redessiner à chaque frappe ferait perdre le curseur du champ en cours
	 * de saisie.
	 *
	 * @param {Object} champ Le champ.
	 */
	function majEntete( champ ) {
		var ligneDom = racine.querySelector( '[data-champ="' + champ._id + '"]' );

		if ( ! ligneDom ) {
			return;
		}

		ligneDom.querySelector( '.bc-champ-ligne__titre' ).textContent = champ.libelle || __( 'Champ sans nom', 'blocs-creator' );
		ligneDom.querySelector( '.bc-champ-ligne__cle' ).textContent   = champ.cle || '—';
	}

	/**
	 * Retourne les types proposés, groupés par famille.
	 *
	 * Dans un répéteur ou un groupe, les types de structure sont retirés : la
	 * profondeur s'arrête à un cran.
	 *
	 * @param {Object|null} parent Le champ parent, s'il y en a un.
	 * @return {Array} Les groupes d'options.
	 */
	function typesGroupes( parent ) {
		var groupes = [];

		Object.keys( donnees.familles || {} ).forEach( function ( famille ) {
			var options = ( donnees.types || [] )
				.filter( function ( type ) {
					if ( type.famille !== famille ) {
						return false;
					}

					// Dans une ligne de répéteur, pas de structure : ni
					// répéteur, ni groupe, ni blocs imbriqués.
					if ( parent ) {
						return ! type.sousChamps && 'blocs-imbriques' !== type.type;
					}

					return true;
				} )
				.map( function ( type ) {
					return { value: type.type, label: type.libelle };
				} );

			if ( options.length ) {
				groupes.push( { label: donnees.familles[ famille ], options: options } );
			}
		} );

		return groupes;
	}

	/* ------------------------------------------------------------------ *
	 * Réglages propres à chaque type
	 * ------------------------------------------------------------------ */

	/**
	 * Dessine les réglages du type d'un champ.
	 *
	 * @param {Object} champ Le champ.
	 * @return {Array} Les contrôles.
	 */
	function reglagesDuType( champ ) {
		var type     = champ.type;
		var options  = champ.options;
		var controles = [];

		/**
		 * Écrit un réglage et resynchronise.
		 *
		 * @param {string} nom    Nom du réglage.
		 * @param {*}      valeur Valeur.
		 */
		function poser( nom, valeur ) {
			options[ nom ] = valeur;
			synchroniser();
		}

		if ( aReglage( type, 'choix' ) ) {
			controles.push(
				zoneTexte(
					__( 'Choix', 'blocs-creator' ),
					options.choix || '',
					function ( valeur ) { poser( 'choix', valeur ); },
					__( 'Une option par ligne. « valeur : Libellé » pour distinguer les deux, sinon le libellé suffit.', 'blocs-creator' ),
					5
				)
			);
		}

		if ( aReglage( type, 'defaut' ) && 'liste' !== type && 'boutons' !== type ) {
			controles.push(
				texte( __( 'Valeur par défaut', 'blocs-creator' ), options.defaut || '', function ( valeur ) {
					poser( 'defaut', valeur );
				} )
			);
		}

		if ( aReglage( type, 'defaut' ) && ( 'liste' === type || 'boutons' === type ) ) {
			controles.push(
				texte( __( 'Choix par défaut', 'blocs-creator' ), options.defaut || '', function ( valeur ) {
					poser( 'defaut', valeur );
				}, __( 'La valeur, pas le libellé.', 'blocs-creator' ) )
			);
		}

		if ( aReglage( type, 'defaut_bascule' ) ) {
			controles.push(
				case_( __( 'Activé par défaut', 'blocs-creator' ), !! options.defaut_bascule, function ( valeur ) {
					poser( 'defaut_bascule', valeur );
				} )
			);
		}

		if ( aReglage( type, 'image' ) ) {
			controles.push(
				texte(
					__( 'Champ image à cadrer', 'blocs-creator' ),
					options.image || '',
					function ( valeur ) { poser( 'image', valeur ); },
					__( 'La clé du champ image de ce bloc. Renseignée, l\'éditeur montre la cible sur la vraie photo au lieu de deux curseurs.', 'blocs-creator' )
				)
			);
		}

		if ( aReglage( type, 'placeholder' ) ) {
			controles.push(
				texte( __( 'Texte d\'invite', 'blocs-creator' ), options.placeholder || '', function ( valeur ) {
					poser( 'placeholder', valeur );
				}, __( 'Affiché en gris tant que le champ est vide.', 'blocs-creator' ) )
			);
		}

		if ( aReglage( type, 'balise' ) ) {
			controles.push(
				selection( __( 'Balise', 'blocs-creator' ), options.balise || 'p', [
					{ value: 'p', label: 'p' },
					{ value: 'div', label: 'div' },
					{ value: 'span', label: 'span' }
				], function ( valeur ) { poser( 'balise', valeur ); } )
			);
		}

		if ( aReglage( type, 'lignes' ) ) {
			controles.push(
				nombre( __( 'Hauteur (lignes)', 'blocs-creator' ), options.lignes || 4, function ( valeur ) {
					poser( 'lignes', valeur );
				}, 1, 20 )
			);
		}

		if ( aReglage( type, 'maxlength' ) ) {
			controles.push(
				nombre( __( 'Longueur maximale', 'blocs-creator' ), options.maxlength || 0, function ( valeur ) {
					poser( 'maxlength', valeur );
				}, 0, 500, __( '0 pour ne pas limiter.', 'blocs-creator' ) )
			);
		}

		if ( aReglage( type, 'min' ) ) {
			controles.push( nombre( __( 'Minimum', 'blocs-creator' ), options.min, function ( valeur ) { poser( 'min', valeur ); } ) );
			controles.push( nombre( __( 'Maximum', 'blocs-creator' ), options.max, function ( valeur ) { poser( 'max', valeur ); } ) );
			controles.push( nombre( __( 'Pas', 'blocs-creator' ), options.pas, function ( valeur ) { poser( 'pas', valeur ); } ) );
		}

		if ( aReglage( type, 'curseur' ) ) {
			controles.push(
				case_( __( 'Présenter en curseur', 'blocs-creator' ), !! options.curseur, function ( valeur ) {
					poser( 'curseur', valeur );
				}, __( 'Demande un minimum et un maximum.', 'blocs-creator' ) )
			);
		}

		if ( aReglage( type, 'defaut_niveau' ) ) {
			controles.push(
				selection( __( 'Niveau par défaut', 'blocs-creator' ), String( options.defaut_niveau || 2 ), niveaux(), function ( valeur ) {
					poser( 'defaut_niveau', parseInt( valeur, 10 ) );
				} )
			);
			controles.push(
				selection( __( 'Niveau le plus haut autorisé', 'blocs-creator' ), String( options.niveau_min || 2 ), niveaux(), function ( valeur ) {
					poser( 'niveau_min', parseInt( valeur, 10 ) );
				}, __( 'h2 par défaut : le h1 appartient au titre de la page.', 'blocs-creator' ) )
			);
		}

		if ( aReglage( type, 'palette_seule' ) ) {
			controles.push(
				case_( __( 'Limiter à la palette du thème', 'blocs-creator' ), !! options.palette_seule, function ( valeur ) {
					poser( 'palette_seule', valeur );
				} )
			);
		}

		if ( aReglage( type, 'taille' ) ) {
			controles.push(
				selection( __( 'Taille d\'image', 'blocs-creator' ), options.taille || 'large', ( donnees.tailles || [] ), function ( valeur ) {
					poser( 'taille', valeur );
				}, __( 'La taille servie au gabarit. Il peut toujours en demander une autre.', 'blocs-creator' ) )
			);
		}

		if ( aReglage( type, 'point_focal' ) ) {
			controles.push(
				case_( __( 'Choisir un point focal', 'blocs-creator' ), !! options.point_focal, function ( valeur ) {
					poser( 'point_focal', valeur );
				}, __( 'Pour les images recadrées par le CSS.', 'blocs-creator' ) )
			);
		}

		if ( aReglage( type, 'max_medias' ) ) {
			controles.push(
				nombre( __( 'Nombre maximum d\'images', 'blocs-creator' ), options.max_medias || 0, function ( valeur ) {
					poser( 'max_medias', valeur );
				}, 0, 100, __( '0 pour ne pas limiter.', 'blocs-creator' ) )
			);
		}

		if ( aReglage( type, 'types_fichier' ) ) {
			controles.push(
				texte( __( 'Types acceptés', 'blocs-creator' ), ( options.types_fichier || [] ).join( ', ' ), function ( valeur ) {
					poser( 'types_fichier', valeur.split( ',' ).map( function ( t ) { return t.trim(); } ).filter( Boolean ) );
				}, __( 'Par exemple : application/pdf, audio. Vide pour tout accepter.', 'blocs-creator' ) )
			);
		}

		if ( aReglage( type, 'defaut_libelle' ) ) {
			controles.push(
				texte( __( 'Libellé par défaut', 'blocs-creator' ), options.defaut_libelle || '', function ( valeur ) {
					poser( 'defaut_libelle', valeur );
				}, __( 'Par exemple : « En savoir plus ».', 'blocs-creator' ) )
			);
		}

		if ( aReglage( type, 'types_contenu' ) ) {
			controles.push(
				cases( __( 'Types de contenu', 'blocs-creator' ), options.types_contenu || [], ( donnees.typesContenu || [] ), function ( valeur ) {
					poser( 'types_contenu', valeur );
				}, __( 'Aucun coché : tous les types publics.', 'blocs-creator' ) )
			);
		}

		if ( aReglage( type, 'max_contenus' ) ) {
			controles.push(
				nombre( __( 'Nombre maximum', 'blocs-creator' ), options.max_contenus || 0, function ( valeur ) {
					poser( 'max_contenus', valeur );
				}, 0, 100, __( '0 pour ne pas limiter.', 'blocs-creator' ) )
			);
		}

		if ( aReglage( type, 'taxonomie' ) ) {
			controles.push(
				selection( __( 'Taxonomie', 'blocs-creator' ), options.taxonomie || 'category', ( donnees.taxonomies || [] ), function ( valeur ) {
					poser( 'taxonomie', valeur );
				} )
			);
			controles.push(
				case_( __( 'Un seul terme', 'blocs-creator' ), !! options.terme_unique, function ( valeur ) {
					poser( 'terme_unique', valeur );
				} )
			);
		}

		if ( aReglage( type, 'libelle_ligne' ) ) {
			controles.push(
				texte( __( 'Nom d\'une ligne', 'blocs-creator' ), options.libelle_ligne || '', function ( valeur ) {
					poser( 'libelle_ligne', valeur );
				}, __( 'Par exemple : « Témoignage ». Sert le bouton « Ajouter ».', 'blocs-creator' ) )
			);
			controles.push( nombre( __( 'Lignes au départ', 'blocs-creator' ), options.lignes_depart || 0, function ( valeur ) { poser( 'lignes_depart', valeur ); }, 0, 20 ) );
			controles.push( nombre( __( 'Minimum de lignes', 'blocs-creator' ), options.min_lignes || 0, function ( valeur ) { poser( 'min_lignes', valeur ); }, 0, 50 ) );
			controles.push( nombre( __( 'Maximum de lignes', 'blocs-creator' ), options.max_lignes || 0, function ( valeur ) { poser( 'max_lignes', valeur ); }, 0, 50, __( '0 pour ne pas limiter.', 'blocs-creator' ) ) );
		}

		if ( aReglage( type, 'blocs_autorises' ) ) {
			controles.push(
				texte( __( 'Blocs autorisés', 'blocs-creator' ), ( options.blocs_autorises || [] ).join( ', ' ), function ( valeur ) {
					poser( 'blocs_autorises', valeur.split( ',' ).map( function ( t ) { return t.trim(); } ).filter( Boolean ) );
				}, __( 'Noms complets, séparés par des virgules : core/paragraph, core/image. Vide pour tout autoriser.', 'blocs-creator' ) )
			);
			controles.push(
				selection( __( 'Disposition', 'blocs-creator' ), options.orientation || 'vertical', [
					{ value: 'vertical', label: __( 'En colonne', 'blocs-creator' ) },
					{ value: 'horizontal', label: __( 'En ligne', 'blocs-creator' ) }
				], function ( valeur ) { poser( 'orientation', valeur ); } )
			);
			controles.push(
				zoneTexte( __( 'Contenu de départ', 'blocs-creator' ), options.gabarit_interne || '', function ( valeur ) {
					poser( 'gabarit_interne', valeur );
				}, __( 'Un bloc par ligne, par son nom : core/heading, core/paragraph.', 'blocs-creator' ), 3 )
			);
			controles.push(
				case_( __( 'Verrouiller ce contenu', 'blocs-creator' ), !! options.verrou_gabarit, function ( valeur ) {
					poser( 'verrou_gabarit', valeur );
				}, __( 'Les blocs de départ ne peuvent plus être ni ajoutés ni supprimés.', 'blocs-creator' ) )
			);
		}

		if ( aReglage( type, 'message' ) ) {
			controles.push(
				zoneTexte( __( 'Le message', 'blocs-creator' ), options.message || '', function ( valeur ) {
					poser( 'message', valeur );
				}, __( 'Affiché tel quel dans le formulaire du bloc.', 'blocs-creator' ), 3 )
			);
		}

		return controles;
	}

	/**
	 * Retourne les options de niveau de titre.
	 *
	 * @return {Array} Les options.
	 */
	function niveaux() {
		return [ 1, 2, 3, 4, 5, 6 ].map( function ( n ) {
			return { value: String( n ), label: 'h' + n };
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Sous-champs
	 * ------------------------------------------------------------------ */

	/**
	 * Dessine la liste des sous-champs d'un répéteur ou d'un groupe.
	 *
	 * @param {Object} parent Le champ parent.
	 * @return {HTMLElement} Le bloc des sous-champs.
	 */
	function sousChamps( parent ) {
		var lignes = parent.sous_champs.map( function ( enfant, index ) {
			return ligne( enfant, index, parent.sous_champs, parent );
		} );

		if ( ! lignes.length ) {
			lignes.push(
				el( 'p', {
					class: 'bc-aide bc-sous-champs__vide',
					texte: __( 'Aucun sous-champ. Chaque ligne portera ceux que vous ajoutez ici.', 'blocs-creator' )
				} )
			);
		}

		return el( 'div', { class: 'bc-sous-champs' }, [
			el( 'h4', { class: 'bc-sous-champs__titre', texte: __( 'Champs de chaque ligne', 'blocs-creator' ) } ),
			el( 'div', { class: 'bc-sous-champs__liste' }, lignes ),
			el( 'button', {
				type: 'button',
				class: 'button button-small',
				texte: __( 'Ajouter un sous-champ', 'blocs-creator' ),
				onClick: function () {
					var neuf = nouveauChamp( parent.sous_champs );

					parent.sous_champs.push( neuf );
					ouverts[ neuf._id ] = true;
					rafraichir();
				}
			} )
		] );
	}

	/**
	 * Crée un champ vierge.
	 *
	 * @param {Array} liste La liste qui l'accueille.
	 * @return {Object} Le champ.
	 */
	function nouveauChamp( liste ) {
		var champ = normaliser( {
			libelle: '',
			type: 'texte',
			options: {}
		} );

		champ.cle = cleLibre( 'champ', liste, null );

		return champ;
	}

	/* ------------------------------------------------------------------ *
	 * Contrôles
	 * ------------------------------------------------------------------ */

	/**
	 * Enveloppe un contrôle dans son libellé et son aide.
	 *
	 * @param {string}      libelle Intitulé.
	 * @param {HTMLElement} controle Le contrôle.
	 * @param {string}      aide    Texte d'aide.
	 * @return {HTMLElement} Le champ complet.
	 */
	function enveloppe( libelle, controle, aide ) {
		var id = 'bc-' + Math.random().toString( 36 ).slice( 2, 9 );

		controle.id = id;

		return el( 'div', { class: 'bc-controle' }, [
			el( 'label', { class: 'bc-controle__libelle', for: id, texte: libelle } ),
			controle,
			aide ? el( 'span', { class: 'bc-aide', texte: aide } ) : null
		] );
	}

	/**
	 * Un champ texte.
	 *
	 * @param {string}   libelle Intitulé.
	 * @param {string}   valeur  Valeur.
	 * @param {Function} auChangement Rappel.
	 * @param {string}   aide    Texte d'aide.
	 * @return {HTMLElement} Le champ.
	 */
	function texte( libelle, valeur, auChangement, aide ) {
		var entree = el( 'input', { type: 'text', class: 'widefat', value: valeur || '' } );

		entree.addEventListener( 'input', function () {
			auChangement( entree.value, entree );
		} );

		return enveloppe( libelle, entree, aide );
	}

	/**
	 * Une zone de texte.
	 *
	 * @param {string}   libelle Intitulé.
	 * @param {string}   valeur  Valeur.
	 * @param {Function} auChangement Rappel.
	 * @param {string}   aide    Texte d'aide.
	 * @param {number}   lignes  Hauteur.
	 * @return {HTMLElement} Le champ.
	 */
	function zoneTexte( libelle, valeur, auChangement, aide, lignes ) {
		var entree = el( 'textarea', { class: 'widefat', rows: lignes || 4 } );

		entree.value = valeur || '';

		entree.addEventListener( 'input', function () {
			auChangement( entree.value, entree );
		} );

		return enveloppe( libelle, entree, aide );
	}

	/**
	 * Un champ numérique.
	 *
	 * @param {string}   libelle Intitulé.
	 * @param {*}        valeur  Valeur.
	 * @param {Function} auChangement Rappel.
	 * @param {number}   min     Minimum.
	 * @param {number}   max     Maximum.
	 * @param {string}   aide    Texte d'aide.
	 * @return {HTMLElement} Le champ.
	 */
	function nombre( libelle, valeur, auChangement, min, max, aide ) {
		var entree = el( 'input', {
			type: 'number',
			class: 'small-text',
			value: ( undefined === valeur || null === valeur ) ? '' : valeur,
			min: undefined === min ? null : min,
			max: undefined === max ? null : max
		} );

		entree.addEventListener( 'input', function () {
			auChangement( '' === entree.value ? '' : Number( entree.value ), entree );
		} );

		return enveloppe( libelle, entree, aide );
	}

	/**
	 * Une liste déroulante.
	 *
	 * @param {string}   libelle Intitulé.
	 * @param {string}   valeur  Valeur.
	 * @param {Array}    options Options, éventuellement groupées.
	 * @param {Function} auChangement Rappel.
	 * @param {string}   aide    Texte d'aide.
	 * @return {HTMLElement} Le champ.
	 */
	function selection( libelle, valeur, options, auChangement, aide ) {
		var entree = el( 'select', { class: 'widefat' } );

		options.forEach( function ( option ) {
			if ( option.options ) {
				var groupe = el( 'optgroup', { label: option.label } );

				option.options.forEach( function ( sous ) {
					groupe.appendChild( el( 'option', { value: sous.value, texte: sous.label } ) );
				} );

				entree.appendChild( groupe );

				return;
			}

			entree.appendChild( el( 'option', { value: option.value, texte: option.label } ) );
		} );

		entree.value = valeur;

		entree.addEventListener( 'change', function () {
			auChangement( entree.value, entree );
		} );

		return enveloppe( libelle, entree, aide );
	}

	/**
	 * Une case à cocher.
	 *
	 * @param {string}   libelle Intitulé.
	 * @param {boolean}  valeur  État.
	 * @param {Function} auChangement Rappel.
	 * @param {string}   aide    Texte d'aide.
	 * @return {HTMLElement} Le champ.
	 */
	function case_( libelle, valeur, auChangement, aide ) {
		var entree = el( 'input', { type: 'checkbox' } );

		entree.checked = !! valeur;

		entree.addEventListener( 'change', function () {
			auChangement( entree.checked, entree );
		} );

		return el( 'div', { class: 'bc-controle bc-controle--case' }, [
			el( 'label', {}, [ entree, el( 'span', { texte: ' ' + libelle } ) ] ),
			aide ? el( 'span', { class: 'bc-aide', texte: aide } ) : null
		] );
	}

	/**
	 * Un groupe de cases à cocher.
	 *
	 * @param {string}   libelle Intitulé.
	 * @param {Array}    valeurs Valeurs cochées.
	 * @param {Array}    options Options.
	 * @param {Function} auChangement Rappel.
	 * @param {string}   aide    Texte d'aide.
	 * @return {HTMLElement} Le champ.
	 */
	function cases( libelle, valeurs, options, auChangement, aide ) {
		var choisies = ( valeurs || [] ).slice();

		var boites = options.map( function ( option ) {
			var entree = el( 'input', { type: 'checkbox', value: option.value } );

			entree.checked = choisies.indexOf( option.value ) !== -1;

			entree.addEventListener( 'change', function () {
				var index = choisies.indexOf( option.value );

				if ( entree.checked && -1 === index ) {
					choisies.push( option.value );
				} else if ( ! entree.checked && index !== -1 ) {
					choisies.splice( index, 1 );
				}

				auChangement( choisies.slice() );
			} );

			return el( 'label', { class: 'bc-controle__case' }, [ entree, el( 'span', { texte: ' ' + option.label } ) ] );
		} );

		return el( 'div', { class: 'bc-controle' }, [
			el( 'span', { class: 'bc-controle__libelle', texte: libelle } ),
			el( 'div', { class: 'bc-controle__cases' }, boites ),
			aide ? el( 'span', { class: 'bc-aide', texte: aide } ) : null
		] );
	}

	/* ------------------------------------------------------------------ *
	 * Colonne latérale : identité et icône
	 * ------------------------------------------------------------------ */

	/**
	 * Tient à jour l'aperçu de l'identifiant sous le titre, et le sélecteur
	 * d'icône.
	 */
	function colonne() {
		var titre  = document.getElementById( 'title' );
		var espace = document.getElementById( 'bc-espace' );
		var slug   = document.getElementById( 'bc-slug' );
		var apercu = document.getElementById( 'bc-apercu-nom' );

		/**
		 * Réécrit l'aperçu du nom complet du bloc.
		 */
		function majNom() {
			if ( ! apercu || ! espace ) {
				return;
			}

			var fin = ( slug && slug.value ) ? slug.value : enSlug( titre ? titre.value : '' );

			apercu.textContent = ( espace.value || 'blocs' ) + '/' + ( fin || '…' );
		}

		/**
		 * Transforme un texte en slug de bloc.
		 *
		 * @param {string} texte Le texte.
		 * @return {string} Le slug.
		 */
		function enSlug( texte ) {
			return ( texte || '' )
				.toLowerCase()
				.normalize( 'NFD' )
				.replace( /[\u0300-\u036f]/g, '' )
				.replace( /[^a-z0-9]+/g, '-' )
				.replace( /^-+|-+$/g, '' );
		}

		[ titre, espace, slug ].forEach( function ( entree ) {
			if ( entree ) {
				entree.addEventListener( 'input', majNom );
			}
		} );

		if ( slug ) {
			slug.addEventListener( 'blur', function () {
				slug.value = enSlug( slug.value );
				majNom();
			} );
		}

		if ( espace ) {
			espace.addEventListener( 'blur', function () {
				espace.value = enSlug( espace.value );
				majNom();
			} );
		}

		majNom();

		var ouvrir = document.querySelector( '.bc-icone-ouvrir' );
		var grille = document.querySelector( '.bc-icone-grille' );
		var valeur = document.querySelector( '.bc-icone-valeur' );

		if ( ! ouvrir || ! grille || ! valeur ) {
			return;
		}

		ouvrir.addEventListener( 'click', function () {
			var ouverte = ! grille.hidden;

			grille.hidden = ouverte;
			ouvrir.setAttribute( 'aria-expanded', ouverte ? 'false' : 'true' );
		} );

		grille.addEventListener( 'click', function ( evenement ) {
			var cible = evenement.target.closest( '.bc-icone-bouton' );

			if ( ! cible ) {
				return;
			}

			var icone = cible.getAttribute( 'data-icone' );

			valeur.value  = icone;
			grille.hidden = true;
			ouvrir.setAttribute( 'aria-expanded', 'false' );
			ouvrir.querySelector( '.dashicons' ).className = 'dashicons dashicons-' + icone;

			grille.querySelectorAll( '.bc-icone-bouton' ).forEach( function ( bouton ) {
				bouton.classList.toggle( 'est-actif', bouton === cible );
			} );
		} );

		valeur.addEventListener( 'input', function () {
			ouvrir.querySelector( '.dashicons' ).className = 'dashicons dashicons-' + ( valeur.value || 'block-default' );
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Démarrage
	 * ------------------------------------------------------------------ */

	/**
	 * Branche le constructeur.
	 */
	function demarrer() {
		racine = document.getElementById( 'bc-constructeur' );
		sortie = document.getElementById( 'bc-champs-json' );

		colonne();

		if ( ! racine || ! sortie ) {
			return;
		}

		try {
			champs = JSON.parse( sortie.value || '[]' ).map( normaliser );
		} catch ( erreur ) {
			champs = [];
		}

		racine.querySelector( '[data-role="ajouter"]' ).addEventListener( 'click', function () {
			var neuf = nouveauChamp( champs );

			champs.push( neuf );
			ouverts[ neuf._id ] = true;
			rafraichir();
		} );

		rafraichir();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', demarrer );
	} else {
		demarrer();
	}
}() );
