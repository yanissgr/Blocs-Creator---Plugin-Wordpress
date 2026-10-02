/*
 * Les apparitions : révéler un bloc, et ses parties, quand il entre à l'écran.
 *
 * Le script ne décide de rien visuellement. Il fait cinq choses :
 *
 *   1. il désigne les PARTIES d'un bloc — son titre, son texte, ses cartes —
 *      et leur donne un rang, pour que le CSS les décale ;
 *   2. il découpe en mots les titres qui le demandent (`data-bc-part="mots"`) ;
 *   3. il guette l'entrée du bloc à l'écran, et pose `is-vu` ;
 *   4. il fait attendre leur tour aux parties encore sous l'écran
 *      (`bc-attend`) : sur un téléphone, une section fait deux écrans de haut ;
 *   5. il garde un filet : ce qui est à l'écran finit toujours par entrer.
 *
 * Tout le dessin est dans assets/css/animations.css, et c'est PHP qui a posé
 * `data-bc-anim` d'après le réglage du bloc.
 *
 * Chaque bloc révélé reçoit l'évènement `blocs-creator:vu` (il remonte) : un
 * gabarit qui veut lancer quelque chose à l'entrée de son bloc l'écoute.
 *
 * Écrit en JavaScript natif, sans dépendance : le plugin s'installe partout
 * sans outil de build.
 */

( function ( window, document ) {
	'use strict';

	/* Un bloc est considéré entré quand il a franchi 10 % du bas de l'écran. */
	var MARGE = '0px 0px -10% 0px';

	/* La même ligne, en part de la hauteur de l'écran, pour les parties. */
	var LIGNE_ENTREE = 0.9;

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
	 * Le second filet : toutes les deux secondes de page réellement regardée,
	 * ce qui est à l'écran et attend encore entre. C'est lui qui sert le bas
	 * d'une page : sur un grand écran, le dernier bloc n'atteint jamais la
	 * ligne d'entrée, 10 % au-dessus du bas de l'écran.
	 */
	var RONDE = 2000;

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
	 * Tous les éléments d'un sélecteur, en tableau.
	 *
	 * @param {string}  selecteur Sélecteur CSS.
	 * @param {Element} contexte  Racine de la recherche.
	 * @return {Element[]} Les éléments.
	 */
	function tous( selecteur, contexte ) {
		return Array.prototype.slice.call( ( contexte || document ).querySelectorAll( selecteur ) );
	}

	/**
	 * L'élément est-il, au moins en partie, à l'écran ?
	 *
	 * @param {Element} element L'élément.
	 * @return {boolean} Vrai s'il se voit.
	 */
	function aLEcran( element ) {
		var boite = element.getBoundingClientRect();

		return !! ( boite.width || boite.height ) && boite.top < window.innerHeight && boite.bottom > 0;
	}

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
		return tous( '[data-bc-part]', bloc ).filter( function ( partie ) {
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

	/* ------------------------------------------------------------------ *
	 * Les titres, mot à mot
	 * ------------------------------------------------------------------ */

	/**
	 * Enveloppe chaque mot d'un élément dans deux <span> : le premier cache,
	 * le second monte. Les balises intérieures (em, br, a…) sont conservées :
	 * on ne découpe que les nœuds de texte.
	 *
	 * Un titre déjà découpé — par ce script, ou par un thème qui le faisait
	 * avant que le plugin ne sache le faire — n'est pas redécoupé.
	 *
	 * @param {Element} element Le titre.
	 * @return {number} Le nombre de mots.
	 */
	function decouper( element ) {
		if ( element.hasAttribute( 'data-bc-mots' ) || element.hasAttribute( 'data-kahel-mots' ) || element.querySelector( '.bc-mot, .kahel-mot' ) ) {
			return 0;
		}

		element.setAttribute( 'data-bc-mots', '' );

		var marcheur = document.createTreeWalker( element, window.NodeFilter.SHOW_TEXT );
		var textes   = [];
		var rang     = 0;

		while ( marcheur.nextNode() ) {
			textes.push( marcheur.currentNode );
		}

		textes.forEach( function ( noeud ) {
			var morceaux = noeud.nodeValue.split( /(\s+)/ );
			var fragment = document.createDocumentFragment();

			morceaux.forEach( function ( morceau ) {
				if ( '' === morceau ) {
					return;
				}

				if ( /^\s+$/.test( morceau ) ) {
					fragment.appendChild( document.createTextNode( morceau ) );
					return;
				}

				var mot    = document.createElement( 'span' );
				var dedans = document.createElement( 'span' );

				mot.className    = 'bc-mot';
				dedans.className = 'bc-mot__in';
				dedans.style.setProperty( '--bc-i', String( rang ) );
				dedans.textContent = morceau;

				mot.appendChild( dedans );
				fragment.appendChild( mot );
				rang++;
			} );

			noeud.parentNode.replaceChild( fragment, noeud );
		} );

		return rang;
	}

	/**
	 * Appelle `rappel` dès que `test` rend vrai — tout de suite si c'est déjà
	 * le cas, sinon au premier changement de classe de l'élément qui le rend
	 * vrai.
	 *
	 * @param {Element}  element L'élément surveillé.
	 * @param {Function} test    Rend vrai quand c'est le moment.
	 * @param {Function} rappel  Appelé une seule fois.
	 */
	function quand( element, test, rappel ) {
		if ( test() ) {
			rappel();
			return;
		}

		var guetteur = new window.MutationObserver( function () {
			if ( test() ) {
				guetteur.disconnect();
				rappel();
			}
		} );

		guetteur.observe( element, { attributes: true, attributeFilter: [ 'class' ] } );
	}

	/**
	 * Découpe les titres qui le demandent, et leur rend leurs débords une fois
	 * les mots posés.
	 */
	function titres() {
		tous( '.bc-anim[data-bc-anim] [data-bc-part="mots"]' ).forEach( function ( titre ) {
			var nombre = decouper( titre );
			var bloc   = titre.closest( '.bc-anim[data-bc-anim]' );

			if ( ! nombre || ! bloc ) {
				return;
			}

			quand( bloc, function () {
				return bloc.classList.contains( 'is-vu' );
			}, function () {
				quand( titre, function () {
					return ! titre.classList.contains( 'bc-attend' );
				}, function () {
					window.setTimeout( function () {
						titre.classList.add( 'bc-mots-poses' );
					}, 1800 + nombre * 55 );
				} );
			} );
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Révéler
	 * ------------------------------------------------------------------ */

	/**
	 * Révèle un bloc, et le dit.
	 *
	 * @param {Element} bloc Le bloc.
	 */
	function reveler( bloc ) {
		if ( bloc.classList.contains( 'is-vu' ) ) {
			return;
		}

		bloc.classList.add( 'is-vu' );

		if ( 'function' === typeof window.CustomEvent ) {
			bloc.dispatchEvent( new window.CustomEvent( 'blocs-creator:vu', { bubbles: true } ) );
		}
	}

	/**
	 * Fait attendre leur tour aux parties encore sous l'écran.
	 *
	 * Un bloc est révélé dès que son haut entre à l'écran : toutes ses parties
	 * joueraient alors leur entrée, y compris celles qui sont deux écrans plus
	 * bas. Chacune d'elles garde donc son état de départ (`bc-attend`) jusqu'à
	 * ce qu'elle arrive elle-même à la ligne d'entrée. Les parties qui arrivent
	 * ensemble gardent leur décalage.
	 *
	 * @param {Element[]} blocs Les blocs animés de la page.
	 * @return {Element[]} Les parties mises en attente.
	 */
	function mettreEnAttente( blocs ) {
		var ligne   = window.innerHeight * LIGNE_ENTREE;
		var attente = [];

		/*
		 * Pas d'écran mesurable (page pré-rendue, onglet ouvert sans fenêtre) :
		 * on ne saurait pas ce qui est « sous l'écran ». Personne n'attend.
		 */
		if ( window.innerHeight < 100 ) {
			return attente;
		}

		blocs.forEach( function ( bloc ) {
			if ( bloc.classList.contains( 'is-vu' ) ) {
				return;
			}

			declarees( bloc ).forEach( function ( partie ) {
				var boite = partie.getBoundingClientRect();

				// Ni ce qui ne s'affiche pas (une étape de formulaire masquée), ni ce qui est déjà à l'écran.
				if ( ( ! boite.width && ! boite.height ) || boite.top < ligne ) {
					return;
				}

				partie.classList.add( 'bc-attend' );
				attente.push( partie );
			} );
		} );

		return attente;
	}

	function demarrer() {
		var racine = document.documentElement;

		if ( ! racine.classList.contains( 'bc-anim-prete' ) ) {
			return;
		}

		var blocs = tous( '.bc-anim[data-bc-anim]' );

		if ( ! blocs.length ) {
			return;
		}

		blocs.forEach( preparer );
		titres();

		/*
		 * Le filet posé par le script d'en-tête a couvert le temps de
		 * chargement de ce fichier. À partir d'ici, c'est le nôtre qui prend la
		 * suite — et son décompte ne part que quand la page est réellement
		 * regardée. Un onglet ouvert en arrière-plan ne reçoit pas d'entrées
		 * non plus, mais lui les recevra dès qu'on le regardera.
		 */
		window.clearTimeout( window.bcAnimFilet );

		function tout_montrer() {
			blocs.forEach( reveler );

			tous( '.bc-attend' ).forEach( function ( partie ) {
				partie.classList.remove( 'bc-attend' );
			} );
		}

		if ( ! ( 'IntersectionObserver' in window ) ) {
			tout_montrer();
			return;
		}

		var revele  = false;
		var attente = mettreEnAttente( blocs );

		var observateur = new window.IntersectionObserver(
			function ( entrees ) {
				entrees.forEach( function ( entree ) {
					if ( ! entree.isIntersecting ) {
						return;
					}

					observateur.unobserve( entree.target );

					if ( entree.target.classList.contains( 'bc-attend' ) ) {
						entree.target.classList.remove( 'bc-attend' );
						attente.splice( attente.indexOf( entree.target ), 1 );
					} else {
						reveler( entree.target );
						revele = true;
					}
				} );
			},
			{ rootMargin: MARGE }
		);

		blocs.forEach( function ( bloc ) {
			observateur.observe( bloc );
		} );

		attente.forEach( function ( partie ) {
			observateur.observe( partie );
		} );

		var armer = function () {
			window.setTimeout( function () {
				if ( ! revele ) {
					tout_montrer();
				}
			}, FILET );

			var ronde = window.setInterval( function () {
				if ( 'hidden' === document.visibilityState ) {
					return;
				}

				var restants = blocs.filter( function ( bloc ) {
					if ( ! bloc.classList.contains( 'is-vu' ) && aLEcran( bloc ) ) {
						reveler( bloc );
						revele = true;
					}

					return ! bloc.classList.contains( 'is-vu' );
				} );

				attente.slice().forEach( function ( partie ) {
					if ( aLEcran( partie ) ) {
						partie.classList.remove( 'bc-attend' );
						observateur.unobserve( partie );
						attente.splice( attente.indexOf( partie ), 1 );
					}
				} );

				if ( ! restants.length && ! attente.length ) {
					window.clearInterval( ronde );
				}
			}, RONDE );
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
