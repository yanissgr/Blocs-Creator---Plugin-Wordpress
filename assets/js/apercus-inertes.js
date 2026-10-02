/*
 * Dans le canevas de l'éditeur, l'aperçu d'un bloc est une image.
 *
 * Chaque aperçu reçoit l'attribut `inert` dès qu'il apparaît : on ne peut ni
 * cliquer ses liens et ses boutons, ni y entrer au clavier ; un clic dessus
 * sélectionne simplement le bloc. Un filet empêche aussi tout lien ou envoi
 * parti d'un aperçu, pour les navigateurs qui ignoreraient `inert`. Voir
 * assets/css/editeur.css.
 *
 * Chargé dans le canevas (crochet `enqueue_block_assets`), qui est une
 * iframe : WordPress y retire le <body> au chargement et en pose un nouveau
 * ensuite. Le script ne compte donc jamais sur `document.body`.
 */
( function ( window, document ) {
	'use strict';

	var APERCUS = '.bc-editeur--apercu > :not(.bc-editeur__imbriques), [data-bc-apercu]';
	var prevu   = false;

	function figer() {
		prevu = false;

		Array.prototype.forEach.call( document.querySelectorAll( APERCUS ), function ( apercu ) {
			if ( ! apercu.hasAttribute( 'inert' ) ) {
				apercu.setAttribute( 'inert', '' );
			}
		} );
	}

	function planifier() {
		if ( ! prevu ) {
			prevu = true;
			window.requestAnimationFrame( figer );
		}
	}

	function dansUnApercu( evenement ) {
		var cible = evenement.target;

		return !! ( cible && cible.closest && cible.closest( APERCUS ) );
	}

	document.addEventListener( 'click', function ( evenement ) {
		if ( dansUnApercu( evenement ) && evenement.target.closest( 'a[href], button, input, select, textarea, label, summary, [role="button"]' ) ) {
			evenement.preventDefault();
		}
	}, true );

	document.addEventListener( 'submit', function ( evenement ) {
		if ( dansUnApercu( evenement ) ) {
			evenement.preventDefault();
		}
	}, true );

	figer();

	// On surveille <html>, pas <body> : au démarrage du canevas, il n'y en a pas encore.
	if ( window.MutationObserver ) {
		new window.MutationObserver( planifier ).observe( document.documentElement, { childList: true, subtree: true } );
	}
}( window, document ) );
