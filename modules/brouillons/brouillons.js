/**
 * Les brouillons des formulaires (modules/brouillons/module.php).
 *
 * Les réponses d'un formulaire sont gardées dans le stockage local du
 * navigateur à chaque frappe (un peu différé), et remises en place quand on
 * revient sur la page : après un rechargement, une fermeture, une coupure de
 * réseau. Elles ne quittent jamais l'appareil, sont effacées à l'envoi du
 * formulaire, et au plus tard au bout de 30 jours.
 *
 * Avec le bandeau cookies, seulement avec l'accord du visiteur — y compris
 * s'il le donne ou le retire alors que le formulaire est ouvert : un refus
 * efface tous les brouillons.
 *
 * Les fichiers joints ne sont jamais gardés (un navigateur ne sait pas les
 * remettre), ni les champs cachés, ni ce qui porte `data-brouillon-ignorer`.
 * Ce que le formulaire veut garder en plus (l'étape en cours, un dessin)
 * passe par `extra` et `restaurer`.
 *
 * Usage : un formulaire `data-bc-brouillon` est suivi tout seul ; sinon,
 * `window.blocsCreatorBrouillon.suivre( form, { cle, extra, extraRempli,
 * restaurer, vider } )`.
 */
( function ( window, document ) {
	'use strict';

	var PREFIXE = 'bc-brouillon:';
	var DUREE   = ( window.blocsCreatorBrouillonJours || 30 ) * 24 * 60 * 60 * 1000;
	var IGNORES = [ 'hidden', 'file', 'password', 'submit', 'button', 'reset', 'image' ];

	/**
	 * Le stockage local, s'il est permis (une navigation privée stricte ou des
	 * données de site bloquées le refusent).
	 *
	 * @return {Storage|null} Le stockage.
	 */
	function stockage() {
		try {
			var essai = PREFIXE + 'essai';

			window.localStorage.setItem( essai, '1' );
			window.localStorage.removeItem( essai );

			return window.localStorage;
		} catch ( e ) {
			return null;
		}
	}

	var magasin = stockage();

	/**
	 * Le choix du visiteur pour les cookies : 'accepte', 'refuse', '' (pas
	 * encore choisi), ou 'sans-bandeau' si le bandeau n'est pas là.
	 *
	 * Le script du bandeau peut se charger après celui-ci : on lit donc le
	 * cookie nous-mêmes au départ, de la même façon que lui.
	 *
	 * @return {string} Le choix.
	 */
	function choixCookies() {
		if ( window.blocsCreatorCookies && 'function' === typeof window.blocsCreatorCookies.choix ) {
			return window.blocsCreatorCookies.choix();
		}

		var bandeau = document.getElementById( 'bc-cookies' );

		if ( ! bandeau ) {
			return 'sans-bandeau';
		}

		var version = bandeau.getAttribute( 'data-version' ) || '1';
		var trouve  = document.cookie.match( /(?:^|;\s*)bc_cookies=([^;]*)/ );
		var valeur  = trouve ? decodeURIComponent( trouve[ 1 ] ).split( '.' ) : [];

		return ( 2 === valeur.length && valeur[ 1 ] === version && /^(accepte|refuse)$/.test( valeur[ 0 ] ) ) ? valeur[ 0 ] : '';
	}

	function autorise( choix ) {
		return 'accepte' === choix || 'sans-bandeau' === choix;
	}

	/**
	 * Parcourt les brouillons rangés.
	 *
	 * @param {Function} rappel Reçoit la clé de chacun.
	 */
	function chaqueBrouillon( rappel ) {
		if ( ! magasin ) {
			return;
		}

		var cles = [];

		for ( var i = 0; i < magasin.length; i++ ) {
			var cle = magasin.key( i );

			if ( cle && 0 === cle.indexOf( PREFIXE ) ) {
				cles.push( cle );
			}
		}

		cles.forEach( rappel );
	}

	function toutEffacer() {
		chaqueBrouillon( function ( cle ) {
			magasin.removeItem( cle );
		} );
	}

	/**
	 * Lit un brouillon ; un brouillon périmé ou illisible est effacé.
	 *
	 * @param {string} cle Clé complète.
	 * @return {Object|null} Le brouillon.
	 */
	function lire( cle ) {
		var brouillon = null;

		try {
			brouillon = JSON.parse( magasin.getItem( cle ) || 'null' );
		} catch ( e ) {
			brouillon = null;
		}

		if ( ! brouillon || 'object' !== typeof brouillon || ! brouillon.t || Date.now() - brouillon.t > DUREE ) {
			magasin.removeItem( cle );
			return null;
		}

		return brouillon;
	}

	/**
	 * Un champ que l'on ne garde pas : caché, fichier, bouton, piège à robots.
	 *
	 * @param {HTMLElement} champ Le contrôle.
	 * @return {boolean} À ignorer.
	 */
	function ignore( champ ) {
		return ! champ.name || -1 !== IGNORES.indexOf( champ.type ) || !! champ.closest( '[aria-hidden="true"], [data-brouillon-ignorer]' );
	}

	/**
	 * Les réponses d'un formulaire : nom du champ → valeur (ou liste de
	 * valeurs, pour des cases à cocher qui partagent un nom).
	 *
	 * @param {HTMLFormElement} form Le formulaire.
	 * @return {Object} Les réponses.
	 */
	function lireReponses( form ) {
		var reponses = {};

		Array.prototype.forEach.call( form.elements, function ( champ ) {
			if ( ignore( champ ) ) {
				return;
			}

			var nom = champ.name;

			if ( 'checkbox' === champ.type && /\[\]$/.test( nom ) ) {
				reponses[ nom ] = reponses[ nom ] || [];

				if ( champ.checked ) {
					reponses[ nom ].push( champ.value );
				}
			} else if ( 'checkbox' === champ.type ) {
				reponses[ nom ] = champ.checked ? champ.value : '';
			} else if ( 'radio' === champ.type ) {
				if ( champ.checked ) {
					reponses[ nom ] = champ.value;
				} else if ( ! ( nom in reponses ) ) {
					reponses[ nom ] = '';
				}
			} else if ( 'select-multiple' === champ.type ) {
				reponses[ nom ] = Array.prototype.filter.call( champ.options, function ( option ) {
					return option.selected;
				} ).map( function ( option ) {
					return option.value;
				} );
			} else {
				reponses[ nom ] = champ.value;
			}
		} );

		return reponses;
	}

	/**
	 * Remet des réponses dans un formulaire.
	 *
	 * @param {HTMLFormElement} form     Le formulaire.
	 * @param {Object}          reponses Les réponses.
	 */
	function poserReponses( form, reponses ) {
		Array.prototype.forEach.call( form.elements, function ( champ ) {
			if ( ignore( champ ) || ! Object.prototype.hasOwnProperty.call( reponses, champ.name ) ) {
				return;
			}

			var valeur = reponses[ champ.name ];

			if ( 'checkbox' === champ.type || 'radio' === champ.type ) {
				champ.checked = Array.isArray( valeur ) ? -1 !== valeur.indexOf( champ.value ) : valeur === champ.value;
			} else if ( 'select-multiple' === champ.type ) {
				Array.prototype.forEach.call( champ.options, function ( option ) {
					option.selected = Array.isArray( valeur ) && -1 !== valeur.indexOf( option.value );
				} );
			} else if ( 'string' === typeof valeur ) {
				champ.value = valeur;
			}
		} );
	}

	/**
	 * Y a-t-il au moins une réponse ?
	 *
	 * @param {Object} reponses Les réponses.
	 * @return {boolean} Rempli.
	 */
	function rempli( reponses ) {
		return Object.keys( reponses || {} ).some( function ( nom ) {
			var valeur = reponses[ nom ];

			return Array.isArray( valeur ) ? valeur.length > 0 : '' !== String( valeur ).trim();
		} );
	}

	/**
	 * Suit un formulaire : garde ses réponses, les remet en place au retour.
	 *
	 * @param {HTMLFormElement} form     Le formulaire.
	 * @param {Object}          reglages `cle` (unique par formulaire), et
	 *                                   facultatifs : `extra()` rend ce qu'il
	 *                                   faut garder en plus, `extraRempli(extra)`
	 *                                   dit s'il compte comme une réponse,
	 *                                   `restaurer(extra)` le remet en place,
	 *                                   `vider()` remet le formulaire à zéro.
	 * @return {Object} `enregistrer()`, `effacer()`.
	 */
	function suivre( form, reglages ) {
		if ( form.bcBrouillon ) {
			return form.bcBrouillon;
		}

		reglages = reglages || {};

		var cle        = PREFIXE + ( reglages.cle || window.location.pathname );
		var note       = form.querySelector( '[data-brouillon-note]' );
		var merci      = form.querySelector( '[data-brouillon-merci]' );
		var retrouve   = form.querySelector( '[data-brouillon-retrouve]' );
		var actif      = !! magasin && autorise( choixCookies() );
		var minuteur   = null;
		var noteFermee = false;
		var envoye     = false;

		function enregistrer() {
			window.clearTimeout( minuteur );
			minuteur = null;

			if ( ! actif || envoye ) {
				return;
			}

			var reponses    = lireReponses( form );
			var extra       = reglages.extra ? reglages.extra() : null;
			var extraRempli = !! extra && ( reglages.extraRempli ? reglages.extraRempli( extra ) : false );

			if ( ! rempli( reponses ) && ! extraRempli ) {
				magasin.removeItem( cle );
				return;
			}

			try {
				magasin.setItem( cle, JSON.stringify( { v: 1, t: Date.now(), reponses: reponses, extra: extra } ) );
			} catch ( e ) {
				// Stockage plein : tant pis pour ce brouillon, le formulaire marche toujours.
			}
		}

		function planifier() {
			if ( ! actif ) {
				return;
			}

			window.clearTimeout( minuteur );
			minuteur = window.setTimeout( enregistrer, 400 );
		}

		function effacer() {
			window.clearTimeout( minuteur );
			minuteur = null;

			if ( magasin ) {
				magasin.removeItem( cle );
			}

			if ( retrouve ) {
				retrouve.hidden = true;
			}
		}

		function montrerNote() {
			if ( note ) {
				note.hidden = actif || noteFermee;
			}
		}

		// Le retour sur un formulaire commencé.
		if ( actif ) {
			var brouillon = lire( cle );

			if ( brouillon ) {
				poserReponses( form, brouillon.reponses || {} );

				if ( reglages.restaurer ) {
					reglages.restaurer( brouillon.extra || null );
				}

				if ( retrouve ) {
					retrouve.hidden = false;
				}
			}
		} else if ( 'refuse' === choixCookies() ) {
			toutEffacer();
		}

		montrerNote();

		form.addEventListener( 'input', planifier );
		form.addEventListener( 'change', planifier );

		/*
		 * Envoyé par le navigateur : le brouillon n'a plus lieu d'être, et la
		 * page qui se ferme ne doit pas le réécrire. On regarde après tous les
		 * autres écouteurs : un formulaire envoyé en arrière-plan (fetch)
		 * annule l'envoi, et appelle lui-même `effacer()` une fois la réponse
		 * reçue — un envoi raté garde ainsi son brouillon.
		 */
		form.addEventListener( 'submit', function ( evenement ) {
			window.setTimeout( function () {
				if ( ! evenement.defaultPrevented ) {
					envoye = true;
					effacer();
				}
			}, 0 );
		} );

		// La page se ferme ou passe en arrière-plan : on garde tout de suite.
		window.addEventListener( 'pagehide', enregistrer );
		document.addEventListener( 'visibilitychange', function () {
			if ( 'hidden' === document.visibilityState ) {
				enregistrer();
			}
		} );

		// Le visiteur répond au bandeau (ou au bouton du petit mot) pendant
		// qu'il remplit le formulaire. Accepté depuis le petit mot : un « c'est
		// noté » le remplace quelques secondes.
		document.addEventListener( 'blocs-creator:cookies', function ( evenement ) {
			var noteVisible = !! note && ! note.hidden;

			actif = !! magasin && autorise( evenement.detail && evenement.detail.choix );
			montrerNote();

			if ( merci ) {
				merci.hidden = ! ( actif && noteVisible );

				if ( ! merci.hidden ) {
					window.setTimeout( function () {
						merci.hidden = true;
					}, 6000 );
				}
			}

			if ( actif ) {
				enregistrer();
			} else {
				effacer();
			}
		} );

		if ( note ) {
			note.addEventListener( 'click', function ( evenement ) {
				if ( evenement.target.closest( '[data-brouillon-fermer]' ) ) {
					noteFermee = true;
					montrerNote();
				}
			} );
		}

		if ( retrouve ) {
			retrouve.addEventListener( 'click', function ( evenement ) {
				var bouton = evenement.target.closest( '[data-brouillon-effacer]' );

				if ( ! bouton || ! window.confirm( bouton.getAttribute( 'data-confirmation' ) || '' ) ) {
					return;
				}

				form.reset();
				effacer();

				if ( reglages.vider ) {
					reglages.vider();
				}
			} );
		}

		form.bcBrouillon = {
			enregistrer: enregistrer,
			effacer: effacer,
		};

		return form.bcBrouillon;
	}

	window.blocsCreatorBrouillon = { suivre: suivre };

	/*
	 * Les formulaires déclarés. Un formulaire qu'un script de thème suit
	 * lui-même, avec ses `extra`, le fait avant `DOMContentLoaded` (script
	 * différé) ou porte `data-bc-brouillon="manuel"` : il n'est pas suivi deux
	 * fois.
	 */
	function demarrer() {
		Array.prototype.forEach.call( document.querySelectorAll( 'form[data-bc-brouillon]' ), function ( form ) {
			var cle = form.getAttribute( 'data-bc-brouillon' );

			if ( 'manuel' !== cle ) {
				suivre( form, { cle: cle || '' } );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', demarrer );
	} else {
		demarrer();
	}
}( window, document ) );
