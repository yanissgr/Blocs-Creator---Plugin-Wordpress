/*
 * Le bandeau cookies (modules/cookies/module.php).
 *
 * Le choix du visiteur vit dans le cookie `bc_cookies` : `accepte.<version>`
 * ou `refuse.<version>`, six mois. Quand la version des réglages change
 * (« Reposer la question »), l'ancien choix ne compte plus.
 *
 * Accepter réveille les balises de Google Analytics écrites en
 * `type="text/plain"`, et permet aux formulaires de garder les réponses en
 * cours (module « Brouillons »). Refuser (même après coup) laisse dormir les
 * balises, efface les cookies `_ga` déjà posés et les brouillons.
 *
 * Pour les autres scripts : `window.blocsCreatorCookies.choix()` rend le choix
 * ('accepte', 'refuse' ou ''), et l'évènement `blocs-creator:cookies` (sur
 * `document`, `detail.choix`) signale chaque nouveau choix. Un bouton
 * `[data-cookies-accepter]`, où qu'il soit dans la page, accepte comme le
 * bouton du bandeau.
 */

( function ( window, document ) {
	'use strict';

	var NOM       = 'bc_cookies';
	var DUREE     = 182 * 24 * 60 * 60;
	var ANCRE     = '#gerer-les-cookies';
	var BROUILLON = 'bc-brouillon:';
	var racine    = document.documentElement;

	var bandeau = document.getElementById( 'bc-cookies' );

	if ( ! bandeau ) {
		return;
	}

	var MOUVEMENT = ! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );

	var version     = bandeau.getAttribute( 'data-version' ) || '1';
	var etat        = bandeau.querySelector( '[data-cookies-etat]' );
	var declencheur = null;
	var actives     = false;
	var minuteur    = null;

	/**
	 * Le choix enregistré pour la version courante : 'accepte', 'refuse' ou ''.
	 *
	 * @return {string} Le choix.
	 */
	function choixEnregistre() {
		var trouve = document.cookie.match( new RegExp( '(?:^|;\\s*)' + NOM + '=([^;]*)' ) );
		var valeur = trouve ? decodeURIComponent( trouve[ 1 ] ).split( '.' ) : [];

		return ( 2 === valeur.length && valeur[ 1 ] === version && /^(accepte|refuse)$/.test( valeur[ 0 ] ) ) ? valeur[ 0 ] : '';
	}

	function enregistrer( choix ) {
		document.cookie = NOM + '=' + choix + '.' + version + ';max-age=' + DUREE + ';path=/;SameSite=Lax' + ( 'https:' === window.location.protocol ? ';Secure' : '' );

		document.dispatchEvent( new window.CustomEvent( 'blocs-creator:cookies', { detail: { choix: choix } } ) );
	}

	/**
	 * Efface les brouillons des formulaires (même préfixe que le module
	 * « Brouillons »).
	 */
	function effacerBrouillons() {
		try {
			var stockage = window.localStorage;

			for ( var i = stockage.length - 1; i >= 0; i-- ) {
				var cle = stockage.key( i );

				if ( cle && 0 === cle.indexOf( BROUILLON ) ) {
					stockage.removeItem( cle );
				}
			}
		} catch ( e ) {
			// Stockage indisponible : il n'y a rien à effacer.
		}
	}

	/**
	 * Les balises de statistiques en attente.
	 *
	 * @return {Element[]} Les balises.
	 */
	function enAttente() {
		return Array.prototype.slice.call( document.querySelectorAll( 'script[type="text/plain"][data-bc-cookies]' ) );
	}

	/**
	 * Transmet le choix à Google (mode consentement) et à l'API de
	 * consentement de WordPress, si elle est installée.
	 *
	 * @param {boolean} accord Accepté ?
	 */
	function signaler( accord ) {
		window.dataLayer = window.dataLayer || [];

		if ( 'function' !== typeof window.gtag ) {
			window.gtag = function () {
				window.dataLayer.push( arguments );
			};
		}

		window.gtag( 'consent', 'update', { analytics_storage: accord ? 'granted' : 'denied' } );

		if ( 'function' === typeof window.wp_set_consent ) {
			window.wp_set_consent( 'statistics', accord ? 'allow' : 'deny' );
		}
	}

	/**
	 * Réveille les balises : chacune est remplacée par une vraie balise
	 * <script>, dans l'ordre de la page.
	 */
	function activer() {
		if ( actives ) {
			return;
		}

		actives = true;
		signaler( true );

		enAttente().forEach( function ( ancienne ) {
			var nouvelle = document.createElement( 'script' );

			Array.prototype.forEach.call( ancienne.attributes, function ( attribut ) {
				if ( 'type' !== attribut.name && 'data-bc-cookies' !== attribut.name ) {
					nouvelle.setAttribute( attribut.name, attribut.value );
				}
			} );

			if ( ! ancienne.hasAttribute( 'src' ) ) {
				nouvelle.text = ancienne.text;
			}

			ancienne.parentNode.replaceChild( nouvelle, ancienne );
		} );
	}

	/**
	 * Retire l'accord : Google Analytics se tait sur cette page, et ses
	 * cookies sont effacés, sur le domaine et ses parents.
	 */
	function desactiver() {
		signaler( false );

		Array.prototype.forEach.call( document.querySelectorAll( 'script:not([src])' ), function ( balise ) {
			var appels = balise.text.match( /gtag\(\s*["']config["']\s*,\s*["'][^"']+/g ) || [];

			appels.forEach( function ( appel ) {
				window[ 'ga-disable-' + appel.replace( /^.*["']/, '' ) ] = true;
			} );
		} );

		var morceaux = window.location.hostname.split( '.' );
		var domaines = [ '' ];

		for ( var i = 0; i < morceaux.length - 1; i++ ) {
			domaines.push( ';domain=.' + morceaux.slice( i ).join( '.' ) );
		}

		document.cookie.split( ';' ).forEach( function ( cookie ) {
			var nom = cookie.split( '=' )[ 0 ].trim();

			if ( /^_(ga|gid|gat)/.test( nom ) ) {
				domaines.forEach( function ( domaine ) {
					document.cookie = nom + '=;max-age=0;path=/' + domaine;
				} );
			}
		} );

		actives = false;
	}

	/* ------------------------------------------------------------------ *
	 * Montrer, cacher
	 * ------------------------------------------------------------------ */

	/**
	 * La hauteur du bandeau, pour que le bouton « Modifier la page » passe
	 * au-dessus quand ils se partagent le bas de l'écran (mobile).
	 */
	function signalerHauteur() {
		if ( bandeau.hidden ) {
			racine.style.removeProperty( '--bc-cookies-hauteur' );
			return;
		}

		racine.style.setProperty( '--bc-cookies-hauteur', bandeau.offsetHeight + 'px' );
	}

	/**
	 * @param {boolean} rouvert Rouvert par le visiteur (et non à l'arrivée).
	 */
	function montrer( rouvert ) {
		window.clearTimeout( minuteur );

		var choix = choixEnregistre();

		if ( etat ) {
			etat.hidden      = ! ( rouvert && choix );
			etat.textContent = choix ? etat.getAttribute( 'data-' + choix ) : '';
		}

		bandeau.hidden = false;
		racine.classList.add( 'bc-cookies-ouvert' );
		signalerHauteur();

		// Laisser le navigateur poser l'état de départ avant d'animer.
		void bandeau.offsetWidth;
		bandeau.classList.add( 'is-visible' );

		if ( rouvert ) {
			bandeau.focus( { preventScroll: true } );
		}
	}

	function cacher() {
		var avaitLeFocus = bandeau.contains( document.activeElement );

		bandeau.classList.remove( 'is-visible' );
		racine.classList.remove( 'bc-cookies-ouvert' );

		minuteur = window.setTimeout( function () {
			bandeau.hidden = true;
			signalerHauteur();
		}, MOUVEMENT ? 420 : 0 );

		if ( avaitLeFocus && declencheur ) {
			declencheur.focus( { preventScroll: true } );
		}

		declencheur = null;
	}

	/* ------------------------------------------------------------------ *
	 * Départ
	 * ------------------------------------------------------------------ */

	/**
	 * Enregistre un choix et l'applique sur place.
	 *
	 * @param {string} choix 'accepte' ou 'refuse'.
	 */
	function choisir( choix ) {
		enregistrer( choix );

		if ( 'accepte' === choix ) {
			activer();
		} else {
			effacerBrouillons();

			if ( actives ) {
				desactiver();
			}
		}

		if ( ! bandeau.hidden ) {
			cacher();
		}
	}

	bandeau.addEventListener( 'click', function ( evenement ) {
		var bouton = evenement.target.closest( '[data-cookies-choix]' );

		if ( bouton ) {
			choisir( bouton.getAttribute( 'data-cookies-choix' ) );
		}
	} );

	bandeau.addEventListener( 'keydown', function ( evenement ) {
		// Échap referme un bandeau rouvert ; à l'arrivée, il faut choisir.
		if ( 'Escape' === evenement.key && choixEnregistre() ) {
			cacher();
		}
	} );

	document.addEventListener( 'click', function ( evenement ) {
		// Un « Accepter les cookies » posé ailleurs que dans le bandeau.
		if ( evenement.target.closest( '[data-cookies-accepter]' ) && ! bandeau.contains( evenement.target ) ) {
			evenement.preventDefault();
			choisir( 'accepte' );
			return;
		}

		var lien = evenement.target.closest( 'a[href$="' + ANCRE + '"]' );

		if ( ! lien ) {
			return;
		}

		evenement.preventDefault();
		declencheur = lien;
		montrer( true );
	} );

	window.addEventListener( 'resize', signalerHauteur, { passive: true } );

	window.blocsCreatorCookies = { choix: choixEnregistre };

	var choix = choixEnregistre();

	if ( 'accepte' === choix ) {
		activer();
	} else if ( ! choix ) {
		// Un temps de lecture avant d'arriver : le bandeau ne surgit pas.
		minuteur = window.setTimeout( function () {
			montrer( false );
		}, MOUVEMENT ? 1200 : 0 );
	}

	if ( ANCRE === window.location.hash ) {
		montrer( true );
	}
}( window, document ) );
