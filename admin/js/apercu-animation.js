/*
 * L'aperçu d'une apparition, dans le back-office.
 *
 * Choisir une apparition sans la voir revient à choisir au hasard : la scène
 * rejoue le scénario retenu, à chaque changement, et sur demande.
 *
 * Elle emprunte la feuille du site (assets/css/animations.css) plutôt que
 * d'avoir la sienne — ce qu'on voit en choisissant est littéralement ce que le
 * visiteur verra. Les parties de la scène sont marquées `data-bc-part` dans le
 * balisage, sans rang : quatre parties, quatre rangs, c'est leur ordre.
 */

( function ( window, document ) {
	'use strict';

	/** Le temps qu'on laisse à la scène avant de la rejouer. */
	var REPRISE = 260;

	function cabler( racine ) {
		var choix       = racine.querySelector( '[data-bc-apparition-choix]' );
		var description = racine.querySelector( '[data-bc-apparition-description]' );
		var apercu      = racine.querySelector( '[data-bc-apercu]' );
		var bloc        = racine.querySelector( '[data-bc-apercu-bloc]' );
		var rejouer     = racine.querySelector( '[data-bc-apercu-rejouer]' );

		if ( ! choix || ! apercu || ! bloc ) {
			return;
		}

		/* Sans cette classe sur <html>, le CSS ne cache rien — donc rien à révéler. */
		document.documentElement.classList.add( 'bc-anim-prete' );

		Array.prototype.forEach.call( bloc.querySelectorAll( '[data-bc-part]' ), function ( partie, rang ) {
			partie.style.setProperty( '--bc-anim-rang', String( rang ) );
		} );

		var minuteur = null;

		function jouer() {
			var scenario = choix.value;

			apercu.hidden = ! scenario;

			if ( ! scenario ) {
				return;
			}

			bloc.setAttribute( 'data-bc-anim', scenario );
			bloc.classList.remove( 'is-vu' );

			window.clearTimeout( minuteur );

			/*
			 * Deux images d'écart avant de révéler : le navigateur doit avoir
			 * peint l'état de départ, sinon il passe directement à l'arrivée et
			 * on ne voit rien.
			 */
			minuteur = window.setTimeout( function () {
				window.requestAnimationFrame( function () {
					window.requestAnimationFrame( function () {
						bloc.classList.add( 'is-vu' );
					} );
				} );
			}, REPRISE );
		}

		choix.addEventListener( 'change', function () {
			if ( description ) {
				var option = choix.options[ choix.selectedIndex ];

				description.textContent = option ? option.getAttribute( 'data-bc-description' ) || '' : '';
			}

			jouer();
		} );

		if ( rejouer ) {
			rejouer.addEventListener( 'click', jouer );
		}

		jouer();
	}

	function demarrer() {
		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-bc-apparition]' ),
			cabler
		);
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', demarrer );
	} else {
		demarrer();
	}
}( window, document ) );
