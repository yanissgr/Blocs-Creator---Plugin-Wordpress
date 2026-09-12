/*
 * Les boutons qui copient : la fiche d'un bloc, le diagnostic des réglages.
 *
 * Un `<textarea>` en lecture seule et « sélectionnez tout, puis copiez » suffit
 * techniquement. Il ne suffit pas dans les faits : ces deux textes n'existent
 * que pour être donnés à quelqu'un, et le geste doit tenir en un clic.
 *
 * Le repli compte autant que le presse-papiers. Hors HTTPS, `navigator.clipboard`
 * n'existe pas : on déplie alors le texte et on le sélectionne, pour que le
 * raccourci du clavier fasse le reste.
 *
 * Écrit en JavaScript natif, sans dépendance : le plugin s'installe partout
 * sans outil de build.
 */

( function ( window, document ) {
	'use strict';

	function replier( cible ) {
		var boite = cible.closest( 'details' );

		if ( boite ) {
			boite.open = true;
		}

		cible.select();
	}

	function annoncer( bouton ) {
		var temoin = bouton.parentNode.querySelector( '[data-bc-copie-faite]' );

		if ( ! temoin ) {
			return;
		}

		temoin.hidden = false;

		window.setTimeout( function () {
			temoin.hidden = true;
		}, 2500 );
	}

	function demarrer() {
		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-bc-copier]' ),
			function ( bouton ) {
				bouton.addEventListener( 'click', function () {
					var cible = document.querySelector( bouton.getAttribute( 'data-bc-copier' ) );

					if ( ! cible ) {
						return;
					}

					if ( window.navigator && window.navigator.clipboard ) {
						window.navigator.clipboard.writeText( cible.value ).then(
							function () {
								annoncer( bouton );
							},
							function () {
								replier( cible );
							}
						);

						return;
					}

					replier( cible );
				} );
			}
		);
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', demarrer );
	} else {
		demarrer();
	}
}( window, document ) );
