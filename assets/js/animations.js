/*
 * Les apparitions : révéler un bloc, et ses parties, quand il entre à l'écran.
 *
 * Le script ne décide de rien visuellement. Il fait trois choses :
 *
 *   1. il désigne les PARTIES d'un bloc — son titre, son texte, ses cartes —
 *      et leur donne un rang, pour que le CSS les décale ;
 *   2. il guette l'entrée du bloc à l'écran ;
 *   3. il pose `is-vu`.
 *
 * Tout le dessin est dans assets/css/animations.css, et c'est PHP qui a posé
 * `data-bc-anim` d'après le réglage du bloc.
 *
 * Écrit en JavaScript natif, sans dépendance : le plugin s'installe partout
 * sans outil de build.
 */

( function ( window, document ) {
	'use strict';

	/* Un bloc est considéré entré quand il a franchi 10 % du bas de l'écran. */
	var MARGE = '0px 0px -10% 0px';

	/*
	 * Le filet de sécurité, en millisecondes.
	 *
	 * Passé ce temps, si rien n'a jamais été révélé, c'est que l'observateur ne
	 * rendra pas la main : page pré-rendue, moteur qui ne livre pas ses
	 * entrées. On montre tout. Une page blanche est un prix trop élevé pour une
	 * apparition.
	 */
	var FILET = 3500;

	/*
	 * Au-delà, le décalage se met à traîner plus qu'il n'accompagne : les
	 * parties suivantes partagent le rang de la dixième.
	 */
	var RANG_MAX = 10;

	/*
	 * Balises qui ne sont que de la mise en page : quand l'une d'elles porte
	 * plusieurs éléments, ce sont EUX qu'on anime, pas elle. C'est ce qui fait
	 * la différence entre une bannière qui entre d'un bloc et une bannière
	 * dont le titre, l'accroche et le bouton se posent l'un après l'autre.
	 *
	 * La liste est délibérément courte. Un <p> qui contient trois <span> n'est
	 * pas une mise en page, c'est une phrase : la découper en morceaux ferait
	 * clignoter le texte.
	 */
	var CONTENEURS = [ 'DIV', 'SECTION', 'ARTICLE', 'HEADER', 'FOOTER', 'ASIDE', 'NAV', 'FORM', 'UL', 'OL', 'DL' ];

	/** Au-delà, on n'anime plus une scène, on fait défiler un générique. */
	var PARTS_MAX = 24;

	/**
	 * L'élément peut-il porter un geste sans que son dessin en souffre ?
	 *
	 * @param {Element} element L'élément.
	 * @return {boolean} Vrai s'il est animable.
	 */
	function animable( element ) {
		/* Un bloc imbriqué a sa propre apparition : elle prime. */
		if ( element.hasAttribute( 'data-bc-anim' ) || element.querySelector( '[data-bc-anim]' ) ) {
			return false;
		}

		/*
		 * Un élément qui porte déjà une transformation ou un filtre les tient
		 * de son dessin — un cercle décalé, un aplat peint. Les lui reprendre
		 * le ferait sauter de sa place : on le laisse tranquille, le bloc
		 * l'emmène avec lui.
		 */
		var style = window.getComputedStyle( element );

		if ( 'none' !== style.transform || 'none' !== style.filter ) {
			return false;
		}

		/* Ni les éléments retirés du flux, ni ceux qui ne se voient pas. */
		return 'none' !== style.display && 'absolute' !== style.position && 'fixed' !== style.position;
	}

	/**
	 * Les parties animables d'un bloc.
	 *
	 * On prend ses enfants directs. Si le bloc n'a qu'un enfant, c'est une
	 * enveloppe — une `<section>` dans un `<div>` — et on descend d'un cran :
	 * animer une enveloppe unique reviendrait à animer le bloc deux fois.
	 *
	 * Puis chaque enfant qui n'est qu'un conteneur cède la place à SES enfants.
	 * Une grille de cartes donne donc ses cartes, et une colonne de texte donne
	 * son titre, son paragraphe et son bouton.
	 *
	 * @param {Element} bloc Le bloc.
	 * @return {Element[]} Ses parties, dans l'ordre.
	 */
	function parties( bloc ) {
		var enfants = Array.prototype.slice.call( bloc.children );

		while ( 1 === enfants.length && enfants[ 0 ].children.length > 1 ) {
			enfants = Array.prototype.slice.call( enfants[ 0 ].children );
		}

		var aplaties = [];

		enfants.forEach( function ( element ) {
			var petits = -1 !== CONTENEURS.indexOf( element.tagName )
				? Array.prototype.slice.call( element.children ).filter( animable )
				: [];

			if ( petits.length > 1 ) {
				aplaties = aplaties.concat( petits );
				return;
			}

			aplaties.push( element );
		} );

		return aplaties.filter( animable ).slice( 0, PARTS_MAX );
	}

	/**
	 * Le nombre de colonnes d'une grille, lu dans les pistes calculées.
	 *
	 * @param {Element} parent Le conteneur.
	 * @return {number} Le nombre de colonnes, ou zéro si ce n'est pas une grille.
	 */
	function colonnes( parent ) {
		var pistes = window.getComputedStyle( parent ).gridTemplateColumns;

		if ( ! pistes || 'none' === pistes ) {
			return 0;
		}

		return Math.max( 1, pistes.trim().split( /\s+/ ).length );
	}

	/**
	 * Donne son rang à chaque partie.
	 *
	 * Le rang repart à zéro À CHAQUE CONTENEUR, et non d'un bout à l'autre du
	 * bloc. Sur une bannière, le titre, l'accroche et le bouton se suivent dans
	 * leur colonne pendant que l'image entre en même temps que le titre — ce
	 * qui est le geste voulu, et non une file d'attente de quatre éléments.
	 *
	 * Dans une grille, il repart aussi à chaque RANGÉE : sans cela, la
	 * quatrième carte d'une grille à trois colonnes entrerait avec le retard
	 * d'une quatrième, alors qu'elle est la première de sa rangée.
	 *
	 * @param {Element[]} parties Les parties, dans l'ordre du balisage.
	 */
	function ranger( parties ) {
		var paquets = [];

		parties.forEach( function ( partie ) {
			var parent = partie.parentElement;
			var paquet = null;

			paquets.forEach( function ( candidat ) {
				if ( candidat.parent === parent ) {
					paquet = candidat;
				}
			} );

			if ( ! paquet ) {
				paquet = { parent: parent, membres: [] };
				paquets.push( paquet );
			}

			paquet.membres.push( partie );
		} );

		paquets.forEach( function ( paquet ) {
			var largeur = paquet.parent ? colonnes( paquet.parent ) : 0;

			paquet.membres.forEach( function ( partie, rang ) {
				/*
				 * Un rang déjà posé est un rang voulu : le gabarit — ou le
				 * thème, qui sait ce que chaque partie doit faire — l'a écrit.
				 * On ne le recalcule pas, sans quoi deux moitiés qui doivent se
				 * croiser partiraient l'une après l'autre.
				 */
				if ( '' !== partie.style.getPropertyValue( '--bc-anim-rang' ) ) {
					return;
				}

				var pose = largeur > 0 ? rang % largeur : rang;

				partie.style.setProperty( '--bc-anim-rang', String( Math.min( pose, RANG_MAX ) ) );
			} );
		} );
	}

	/**
	 * Les parties qu'un gabarit a désignées lui-même.
	 *
	 * Un gabarit qui sait de quoi son bloc est fait n'a pas à laisser deviner :
	 * il pose `data-bc-part` sur ce qui doit entrer, et le script s'en tient à
	 * sa liste. C'est la seule façon d'animer une bannière comme son auteur
	 * l'entendait plutôt que comme un tas de `div`.
	 *
	 * On ne prend que ce qui appartient à CE bloc : un bloc imbriqué garde ses
	 * propres parties pour sa propre apparition.
	 *
	 * @param {Element} bloc Le bloc.
	 * @return {Element[]} Les parties déclarées, dans l'ordre du balisage.
	 */
	function declarees( bloc ) {
		return Array.prototype.slice
			.call( bloc.querySelectorAll( '[data-bc-part]' ) )
			.filter( function ( partie ) {
				return partie.closest( '.bc-anim[data-bc-anim]' ) === bloc;
			} );
	}

	/**
	 * Désigne les parties d'un bloc et leur donne leur rang.
	 *
	 * Une scène « au repos » — cascade, pastilles… — ne déplace pas le bloc :
	 * elle ne vit que par ses parties. Quand le balisage n'en offre aucune que
	 * l'on puisse animer sans l'abîmer, la scène ne jouerait rien du tout. Le
	 * bloc entre alors d'un seul tenant, ce qui vaut toujours mieux qu'un bloc
	 * qui n'entre pas. C'est le CSS qui dit quelles scènes sont au repos
	 * (`--bc-a-repose`) : il est déjà la source de vérité du reste.
	 *
	 * @param {Element} bloc Le bloc.
	 */
	function preparer( bloc ) {
		var choisies = declarees( bloc );

		if ( ! choisies.length ) {
			choisies = parties( bloc );

			choisies.forEach( function ( partie ) {
				partie.setAttribute( 'data-bc-part', '' );
			} );
		}

		ranger( choisies );

		if ( choisies.length ) {
			return;
		}

		var repose = window.getComputedStyle( bloc ).getPropertyValue( '--bc-a-repose' ).trim();

		if ( '' !== repose && '0' !== repose ) {
			bloc.setAttribute( 'data-bc-anim', 'montee' );
		}
	}

	function demarrer() {
		var racine = document.documentElement;

		if ( ! racine.classList.contains( 'bc-anim-prete' ) ) {
			return;
		}

		var blocs = Array.prototype.slice.call( document.querySelectorAll( '.bc-anim[data-bc-anim]' ) );

		if ( ! blocs.length ) {
			return;
		}

		blocs.forEach( preparer );

		/*
		 * Le filet posé par le script d'en-tête a couvert le temps de
		 * chargement de ce fichier. À partir d'ici, c'est le nôtre qui prend la
		 * suite — et son décompte ne part que quand la page est réellement
		 * regardée. Un onglet ouvert en arrière-plan ne reçoit pas d'entrées
		 * non plus, mais lui les recevra dès qu'on le regardera.
		 */
		window.clearTimeout( window.bcAnimFilet );

		function tout_montrer() {
			blocs.forEach( function ( bloc ) {
				bloc.classList.add( 'is-vu' );
			} );
		}

		if ( ! ( 'IntersectionObserver' in window ) ) {
			tout_montrer();
			return;
		}

		var revele = false;

		var observateur = new window.IntersectionObserver(
			function ( entrees ) {
				entrees.forEach( function ( entree ) {
					if ( ! entree.isIntersecting ) {
						return;
					}

					entree.target.classList.add( 'is-vu' );
					observateur.unobserve( entree.target );
					revele = true;
				} );
			},
			{ rootMargin: MARGE }
		);

		blocs.forEach( function ( bloc ) {
			observateur.observe( bloc );
		} );

		var armer = function () {
			window.setTimeout( function () {
				if ( ! revele ) {
					tout_montrer();
				}
			}, FILET );
		};

		if ( 'hidden' === document.visibilityState ) {
			document.addEventListener( 'visibilitychange', function une_fois() {
				document.removeEventListener( 'visibilitychange', une_fois );
				armer();
			} );
		} else {
			armer();
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', demarrer );
	} else {
		demarrer();
	}
}( window, document ) );
