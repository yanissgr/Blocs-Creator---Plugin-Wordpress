/*
 * L'éditeur des blocs générés.
 *
 * Un seul `edit` pour tous les blocs : ils ne diffèrent que par leurs champs,
 * et les champs sont des données. Un site à deux blocs et un site à quarante
 * chargent le même fichier.
 *
 * Écrit en JavaScript natif — `wp.element.createElement` plutôt que du JSX —
 * pour que le plugin s'installe partout sans outil de build. Plus verbeux,
 * mais lisible et modifiable tel quel.
 *
 * Deux règles tiennent ce fichier :
 *
 *   1. L'APERÇU NE MENT PAS. Par défaut, le bloc s'affiche par son rendu
 *      serveur : exactement le code qui tournera sur le site, pas une
 *      approximation redessinée en JavaScript. La barre d'outils bascule vers
 *      le formulaire quand il faut saisir.
 *
 *   2. LE RÉGLAGE EST LÀ OÙ SE TROUVE LA CHOSE. Un champ déclaré « dans le
 *      bloc » se saisit dans le canevas ; un champ déclaré « colonne de
 *      droite » va dans l'inspecteur. C'est le réglage `emplacement` de
 *      chaque champ qui tranche, pas ce fichier.
 */

( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! window.blocsCreator ) {
		return;
	}

	var donnees = window.blocsCreator;

	var el        = wp.element.createElement;
	var Fragment  = wp.element.Fragment;
	var useState  = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var __        = wp.i18n.__;
	var sprintf   = wp.i18n.sprintf;

	var be                  = wp.blockEditor;
	var useBlockProps       = be.useBlockProps;
	var useInnerBlocksProps = be.useInnerBlocksProps;
	var RichText            = be.RichText;
	var BlockControls       = be.BlockControls;
	var InspectorControls   = be.InspectorControls;
	var MediaUpload         = be.MediaUpload;
	var MediaUploadCheck    = be.MediaUploadCheck;
	var ColorPalette        = be.ColorPalette;
	var InnerBlocks         = be.InnerBlocks;

	/*
	 * WordPress a stabilisé ce composant au fil des versions : on prend le nom
	 * définitif s'il existe, l'expérimental sinon, et le bloc se replie sur un
	 * simple champ d'URL si aucun des deux n'est là. Un bloc doit rester
	 * réglable même sur une version plus ancienne.
	 */
	var LinkControl = be.LinkControl || be.__experimentalLinkControl || null;

	// Stabilisé dans wp.components, expérimental avant : on prend ce qui est là.
	var FocalPointPicker = ( wp.components && ( wp.components.FocalPointPicker || wp.components.__experimentalFocalPointPicker ) ) || null;

	var c = wp.components;

	var PanelBody        = c.PanelBody;
	var TextControl      = c.TextControl;
	var TextareaControl  = c.TextareaControl;
	var ToggleControl    = c.ToggleControl;
	var SelectControl    = c.SelectControl;
	var RangeControl     = c.RangeControl;
	var CheckboxControl  = c.CheckboxControl;
	var BaseControl      = c.BaseControl;
	var Button           = c.Button;
	var ButtonGroup      = c.ButtonGroup;
	var Notice           = c.Notice;
	var Spinner          = c.Spinner;
	var Placeholder      = c.Placeholder;
	var ToolbarGroup     = c.ToolbarGroup;
	var ToolbarButton    = c.ToolbarButton;
	var Popover          = c.Popover;

	var ServerSideRender = wp.serverSideRender;
	var useSelect        = wp.data.useSelect;
	var apiFetch         = wp.apiFetch;
	var addQueryArgs     = wp.url.addQueryArgs;

	/* ------------------------------------------------------------------ *
	 * Aides
	 * ------------------------------------------------------------------ */

	/**
	 * Découpe les choix d'un champ, saisis une option par ligne.
	 *
	 * @param {Object} champ Le champ.
	 * @return {Array} Les options.
	 */
	function choixDe( champ ) {
		if ( Array.isArray( champ.choix_editeur ) ) {
			return champ.choix_editeur.map( function ( choix ) {
				return { value: choix.valeur, label: choix.libelle };
			} );
		}
		var brut = ( champ.options && champ.options.choix ) || '';

		return String( brut )
			.split( /\r\n|\r|\n/ )
			.map( function ( ligne ) { return ligne.trim(); } )
			.filter( Boolean )
			.map( function ( ligne ) {
				var separateur = ligne.indexOf( ':' );

				if ( -1 === separateur ) {
					return {
						value: ligne.toLowerCase().replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' ),
						label: ligne
					};
				}

				return {
					value: ligne.slice( 0, separateur ).trim(),
					label: ligne.slice( separateur + 1 ).trim()
				};
			} );
	}

	/**
	 * Lit un média de la médiathèque.
	 *
	 * @param {number} id Identifiant.
	 * @return {Object|null} Le média, ou null tant qu'il n'est pas chargé.
	 */
	function useMedia( id ) {
		return useSelect(
			function ( select ) {
				return id ? select( 'core' ).getMedia( id ) : null;
			},
			[ id ]
		);
	}

	/**
	 * Retourne la valeur d'une ligne, ou celle du bloc.
	 *
	 * Un sous-champ de répéteur lit dans sa ligne, un champ de premier niveau
	 * dans les attributs. Le reste du code n'a pas à faire la différence.
	 *
	 * @param {Object} source Attributs ou ligne.
	 * @param {string} cle    Clé du champ.
	 * @return {*} La valeur.
	 */
	function valeurDe( source, cle ) {
		return source ? source[ cle ] : undefined;
	}

	/* ------------------------------------------------------------------ *
	 * Les contrôles, un par type de champ
	 * ------------------------------------------------------------------ */

	/**
	 * Dessine le contrôle d'un champ.
	 *
	 * @param {Object}   champ    Définition du champ.
	 * @param {*}        valeur   Valeur courante.
	 * @param {Function} ecrire   Rappel d'écriture.
	 * @param {Object}   options  Contexte : `dansBloc`, `cle`.
	 * @return {Object} Élément React.
	 */
	function controle( champ, valeur, ecrire, options ) {
		var reglages = champ.options || {};
		var contexte = options || {};
		if ( valeur === undefined || valeur === null ) {
			valeur = defautDe( champ );
		}

		switch ( champ.type ) {

			case 'message':
				return el( 'p', { className: 'bc-editeur__note', key: champ.cle }, reglages.message || '' );

			case 'texte':
				return el( TextControl, {
					key: champ.cle,
					label: champ.libelle,
					help: champ.aide || undefined,
					value: valeur || '',
					placeholder: reglages.placeholder || undefined,
					maxLength: reglages.maxlength > 0 ? reglages.maxlength : undefined,
					onChange: ecrire,
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true
				} );

			case 'texte-long':
				return el( TextareaControl, {
					key: champ.cle,
					label: champ.libelle,
					help: champ.aide || undefined,
					value: valeur || '',
					rows: reglages.lignes || 4,
					placeholder: reglages.placeholder || undefined,
					onChange: ecrire,
					__nextHasNoMarginBottom: true
				} );

			case 'texte-riche':
				// Dans le canevas, le texte enrichi s'écrit là où il
				// s'affichera. Dans la colonne de droite, il n'y a pas de
				// place pour une barre de mise en forme : on retombe sur une
				// zone de texte.
				if ( contexte.dansBloc ) {
					return el(
						'div',
						{ className: 'bc-editeur__riche', key: champ.cle },
						el( 'span', { className: 'bc-editeur__etiquette' }, champ.libelle ),
						el( RichText, {
							tagName: reglages.balise || 'p',
							value: valeur || '',
							placeholder: reglages.placeholder || champ.libelle,
							onChange: ecrire
						} ),
						champ.aide ? el( 'span', { className: 'bc-editeur__aide' }, champ.aide ) : null
					);
				}

				return el( TextareaControl, {
					key: champ.cle,
					label: champ.libelle,
					help: champ.aide || undefined,
					value: valeur || '',
					rows: 4,
					onChange: ecrire,
					__nextHasNoMarginBottom: true
				} );

			case 'nombre':
				if ( reglages.curseur ) {
					return el( RangeControl, {
						key: champ.cle,
						label: champ.libelle,
						help: champ.aide || undefined,
						value: Number( valeur ) || 0,
						min: '' !== reglages.min && undefined !== reglages.min ? Number( reglages.min ) : 0,
						max: '' !== reglages.max && undefined !== reglages.max ? Number( reglages.max ) : 100,
						step: reglages.pas || 1,
						onChange: ecrire,
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true
					} );
				}

				return el( TextControl, {
					key: champ.cle,
					type: 'number',
					label: champ.libelle,
					help: champ.aide || undefined,
					value: undefined === valeur || null === valeur ? '' : valeur,
					min: reglages.min,
					max: reglages.max,
					step: reglages.pas || undefined,
					onChange: function ( brut ) {
						ecrire( '' === brut ? 0 : Number( brut ) );
					},
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true
				} );

			case 'bascule':
				return el( ToggleControl, {
					key: champ.cle,
					label: champ.libelle,
					help: champ.aide || undefined,
					checked: !! valeur,
					onChange: ecrire,
					__nextHasNoMarginBottom: true
				} );

			case 'liste':
				return el( SelectControl, {
					key: champ.cle,
					label: champ.libelle,
					help: champ.aide || undefined,
					value: valeur || '',
					options: [ { value: '', label: __( '— Choisir —', 'blocs-creator' ) } ].concat( choixDe( champ ) ),
					onChange: ecrire,
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true
				} );

			case 'boutons':
				return el(
					BaseControl,
					{ key: champ.cle, label: champ.libelle, help: champ.aide || undefined, __nextHasNoMarginBottom: true },
					el(
						ButtonGroup,
						{ className: 'bc-editeur__boutons' },
						choixDe( champ ).map( function ( choix ) {
							return el( Button, {
								key: choix.value,
								variant: valeur === choix.value ? 'primary' : 'secondary',
								size: 'compact',
								onClick: function () { ecrire( choix.value ); }
							}, choix.label );
						} )
					)
				);

			case 'cases':
				return el(
					BaseControl,
					{ key: champ.cle, label: champ.libelle, help: champ.aide || undefined, __nextHasNoMarginBottom: true },
					choixDe( champ ).map( function ( choix ) {
						var cochees = Array.isArray( valeur ) ? valeur : [];

						return el( CheckboxControl, {
							key: choix.value,
							label: choix.label,
							checked: cochees.indexOf( choix.value ) !== -1,
							onChange: function ( coche ) {
								var suite = cochees.filter( function ( v ) { return v !== choix.value; } );

								if ( coche ) {
									suite.push( choix.value );
								}

								ecrire( suite );
							},
							__nextHasNoMarginBottom: true
						} );
					} )
				);

			case 'niveau-titre':
				return el( SelectControl, {
					key: champ.cle,
					label: champ.libelle,
					help: champ.aide || undefined,
					value: String( valeur || 2 ),
					options: [ 1, 2, 3, 4, 5, 6 ]
						.filter( function ( n ) { return n >= ( reglages.niveau_min || 2 ); } )
						.map( function ( n ) { return { value: String( n ), label: 'h' + n }; } ),
					onChange: function ( brut ) { ecrire( Number( brut ) ); },
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true
				} );

			case 'couleur':
				return el(
					BaseControl,
					{ key: champ.cle, label: champ.libelle, help: champ.aide || undefined, __nextHasNoMarginBottom: true },
					el( ColorPalette, {
						value: valeur || '',
						disableCustomColors: !! reglages.palette_seule,
						clearable: true,
						onChange: function ( couleur ) { ecrire( couleur || '' ); }
					} )
				);

			case 'icone':
				return el( IconeControl, {
					key: champ.cle,
					champ: champ,
					valeur: valeur || '',
					ecrire: ecrire
				} );

			case 'image':
				return el( ImageControl, {
					key: champ.cle,
					champ: champ,
					valeur: valeur || 0,
					ecrire: ecrire,
					dansBloc: !! contexte.dansBloc
				} );

			case 'point-focal':
				return el( PointFocalControl, {
					key: champ.cle,
					champ: champ,
					valeur: valeur || {},
					ecrire: ecrire,
					valeurs: contexte.valeurs || {}
				} );

			case 'type-publication':
				return el( SelectControl, {
					key: champ.cle,
					label: champ.libelle,
					help: champ.aide || undefined,
					value: valeur || '',
					options: ( donnees.typesContenu || [] ).map( function ( type ) {
						return { value: type.value, label: type.label };
					} ),
					onChange: ecrire,
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true
				} );

			case 'galerie':
				return el( GalerieControl, { key: champ.cle, champ: champ, valeur: valeur || [], ecrire: ecrire } );

			case 'fichier':
				return el( FichierControl, { key: champ.cle, champ: champ, valeur: valeur || 0, ecrire: ecrire } );

			case 'lien':
				return el( LienControl, { key: champ.cle, champ: champ, valeur: valeur || {}, ecrire: ecrire } );

			case 'contenu':
			case 'contenus':
				return el( ContenuControl, {
					key: champ.cle,
					champ: champ,
					valeur: valeur,
					ecrire: ecrire,
					multiple: 'contenus' === champ.type
				} );

			case 'taxonomie':
				return el( TermesControl, { key: champ.cle, champ: champ, valeur: valeur || [], ecrire: ecrire } );

			case 'groupe':
				return el(
					'fieldset',
					{ key: champ.cle, className: 'bc-editeur__groupe' },
					el( 'legend', { className: 'bc-editeur__legende' }, champ.libelle ),
					( champ.sous_champs || [] ).map( function ( sous ) {
						return controle(
							sous,
							valeurDe( valeur || {}, sous.cle ),
							function ( neuf ) {
								var suite = Object.assign( {}, valeur || {} );

								suite[ sous.cle ] = neuf;
								ecrire( suite );
							},
							{ dansBloc: !! contexte.dansBloc, valeurs: valeur || {} }
						);
					} )
				);

			case 'repeteur':
				return el( RepeteurControl, { key: champ.cle, champ: champ, valeur: valeur || [], ecrire: ecrire } );

			default:
				return null;
		}
	}

	/* ------------------------------------------------------------------ *
	 * Contrôles composés
	 * ------------------------------------------------------------------ */

	/**
	 * Choix du point de cadrage d'une image.
	 *
	 * Quand le champ désigne une image du même bloc — réglage « image » —, on
	 * sert le vrai sélecteur de WordPress, sur la vraie image : on déplace une
	 * cible sur la photo au lieu de taper deux pourcentages. Sans image
	 * choisie, ou sans le composant, on retombe sur deux curseurs, qui règlent
	 * exactement la même chose.
	 *
	 * @param {Object} props Propriétés.
	 * @return {Object} Élément React.
	 */
	function PointFocalControl( props ) {
		var valeur = props.valeur && 'object' === typeof props.valeur ? props.valeur : {};

		var point = {
			x: undefined !== valeur.x ? Number( valeur.x ) : 0.5,
			y: undefined !== valeur.y ? Number( valeur.y ) : 0.5
		};

		var cle   = ( props.champ.options || {} ).image || '';
		var media = useMedia( cle ? Number( props.valeurs[ cle ] ) || 0 : 0 );

		if ( FocalPointPicker && media && media.source_url ) {
			return el( FocalPointPicker, {
				label: props.champ.libelle,
				help: props.champ.aide || undefined,
				url: media.source_url,
				value: point,
				onChange: props.ecrire,
				__nextHasNoMarginBottom: true
			} );
		}

		return el(
			BaseControl,
			{
				label: props.champ.libelle,
				help: props.champ.aide || __( 'Le point de l\'image qui ne doit jamais être rogné.', 'blocs-creator' ),
				__nextHasNoMarginBottom: true
			},
			el( RangeControl, {
				label: __( 'Horizontal', 'blocs-creator' ),
				value: Math.round( point.x * 100 ),
				min: 0,
				max: 100,
				onChange: function ( v ) {
					props.ecrire( { x: ( Number( v ) || 0 ) / 100, y: point.y } );
				},
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true
			} ),
			el( RangeControl, {
				label: __( 'Vertical', 'blocs-creator' ),
				value: Math.round( point.y * 100 ),
				min: 0,
				max: 100,
				onChange: function ( v ) {
					props.ecrire( { x: point.x, y: ( Number( v ) || 0 ) / 100 } );
				},
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true
			} )
		);
	}

	/**
	 * Choix d'une icône Dashicon.
	 *
	 * @param {Object} props Propriétés.
	 * @return {Object} Élément React.
	 */
	function IconeControl( props ) {
		var ouvert = useState( false );
		var estOuvert = ouvert[ 0 ];
		var setOuvert = ouvert[ 1 ];

		return el(
			BaseControl,
			{ label: props.champ.libelle, help: props.champ.aide || undefined, __nextHasNoMarginBottom: true },
			el(
				'div',
				{ className: 'bc-editeur__icone' },
				el( Button, {
					variant: 'secondary',
					onClick: function () { setOuvert( ! estOuvert ); },
					icon: props.valeur || 'block-default'
				}, props.valeur || __( 'Choisir', 'blocs-creator' ) ),
				props.valeur ? el( Button, {
					variant: 'tertiary',
					isDestructive: true,
					size: 'small',
					onClick: function () { props.ecrire( '' ); }
				}, __( 'Retirer', 'blocs-creator' ) ) : null,
				estOuvert ? el(
					Popover,
					{ onClose: function () { setOuvert( false ); }, placement: 'bottom-start' },
					el(
						'div',
						{ className: 'bc-editeur__icone-grille' },
						( donnees.dashicons || [] ).map( function ( icone ) {
							return el( Button, {
								key: icone,
								icon: icone,
								label: icone,
								isPressed: props.valeur === icone,
								onClick: function () {
									props.ecrire( icone );
									setOuvert( false );
								}
							} );
						} )
					)
				) : null
			)
		);
	}

	/**
	 * Choix d'une image.
	 *
	 * @param {Object} props Propriétés.
	 * @return {Object} Élément React.
	 */
	function ImageControl( props ) {
		var media = useMedia( props.valeur );
		var url   = media && media.source_url ? media.source_url : '';

		var choisir = el( MediaUploadCheck, {}, el( MediaUpload, {
			allowedTypes: [ 'image' ],
			value: props.valeur,
			onSelect: function ( selection ) { props.ecrire( selection.id ); },
			render: function ( rendu ) {
				return el(
					'div',
					{ className: 'bc-editeur__media' },
					url
						? el( 'button', {
							type: 'button',
							className: 'bc-editeur__media-apercu',
							onClick: rendu.open
						}, el( 'img', { src: url, alt: media.alt_text || '' } ) )
						: el( Button, { variant: 'secondary', onClick: rendu.open }, __( 'Choisir une image', 'blocs-creator' ) ),
					url ? el(
						'div',
						{ className: 'bc-editeur__media-outils' },
						el( Button, { variant: 'tertiary', size: 'small', onClick: rendu.open }, __( 'Remplacer', 'blocs-creator' ) ),
						el( Button, {
							variant: 'tertiary',
							size: 'small',
							isDestructive: true,
							onClick: function () { props.ecrire( 0 ); }
						}, __( 'Retirer', 'blocs-creator' ) )
					) : null
				);
			}
		} ) );

		return el(
			BaseControl,
			{ label: props.champ.libelle, help: props.champ.aide || undefined, __nextHasNoMarginBottom: true },
			choisir
		);
	}

	/**
	 * Choix de plusieurs images.
	 *
	 * @param {Object} props Propriétés.
	 * @return {Object} Élément React.
	 */
	function GalerieControl( props ) {
		var ids = Array.isArray( props.valeur ) ? props.valeur : [];
		var max = ( props.champ.options && props.champ.options.max_medias ) || 0;

		return el(
			BaseControl,
			{ label: props.champ.libelle, help: props.champ.aide || undefined, __nextHasNoMarginBottom: true },
			el( MediaUploadCheck, {}, el( MediaUpload, {
				allowedTypes: [ 'image' ],
				multiple: true,
				gallery: true,
				value: ids,
				onSelect: function ( selection ) {
					var suite = selection.map( function ( media ) { return media.id; } );

					props.ecrire( max > 0 ? suite.slice( 0, max ) : suite );
				},
				render: function ( rendu ) {
					return el(
						'div',
						{ className: 'bc-editeur__media' },
						el( Button, { variant: 'secondary', onClick: rendu.open },
							ids.length
								? sprintf(
									/* translators: %d: nombre d'images. */
									__( '%d image(s) — modifier', 'blocs-creator' ),
									ids.length
								)
								: __( 'Choisir des images', 'blocs-creator' )
						),
						ids.length ? el( Button, {
							variant: 'tertiary',
							size: 'small',
							isDestructive: true,
							onClick: function () { props.ecrire( [] ); }
						}, __( 'Vider', 'blocs-creator' ) ) : null
					);
				}
			} ) )
		);
	}

	/**
	 * Choix d'un fichier.
	 *
	 * @param {Object} props Propriétés.
	 * @return {Object} Élément React.
	 */
	function FichierControl( props ) {
		var media = useMedia( props.valeur );
		var types = ( props.champ.options && props.champ.options.types_fichier ) || [];

		return el(
			BaseControl,
			{ label: props.champ.libelle, help: props.champ.aide || undefined, __nextHasNoMarginBottom: true },
			el( MediaUploadCheck, {}, el( MediaUpload, {
				allowedTypes: types.length ? types : undefined,
				value: props.valeur,
				onSelect: function ( selection ) { props.ecrire( selection.id ); },
				render: function ( rendu ) {
					return el(
						'div',
						{ className: 'bc-editeur__media' },
						el( Button, { variant: 'secondary', onClick: rendu.open },
							media && media.title ? media.title.rendered : __( 'Choisir un fichier', 'blocs-creator' )
						),
						props.valeur ? el( Button, {
							variant: 'tertiary',
							size: 'small',
							isDestructive: true,
							onClick: function () { props.ecrire( 0 ); }
						}, __( 'Retirer', 'blocs-creator' ) ) : null
					);
				}
			} ) )
		);
	}

	/**
	 * Saisie d'un lien : destination, libellé, nouvel onglet.
	 *
	 * @param {Object} props Propriétés.
	 * @return {Object} Élément React.
	 */
	function LienControl( props ) {
		var lien   = props.valeur || {};
		var ouvert = useState( false );
		var estOuvert = ouvert[ 0 ];
		var setOuvert = ouvert[ 1 ];

		/**
		 * Écrit une partie du lien.
		 *
		 * @param {Object} morceau Les clés à remplacer.
		 */
		function poser( morceau ) {
			props.ecrire( Object.assign( {}, lien, morceau ) );
		}

		return el(
			BaseControl,
			{ label: props.champ.libelle, help: props.champ.aide || undefined, __nextHasNoMarginBottom: true },
			el(
				'div',
				{ className: 'bc-editeur__lien' },
				el( TextControl, {
					label: __( 'Libellé', 'blocs-creator' ),
					value: lien.titre || '',
					onChange: function ( valeur ) { poser( { titre: valeur } ); },
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true
				} ),
				LinkControl
					? el(
						'div',
						{ className: 'bc-editeur__lien-destination' },
						el( Button, {
							variant: 'secondary',
							icon: 'admin-links',
							onClick: function () { setOuvert( ! estOuvert ); }
						}, lien.url || __( 'Choisir la destination', 'blocs-creator' ) ),
						estOuvert ? el(
							Popover,
							{ onClose: function () { setOuvert( false ); }, placement: 'bottom-start' },
							el( LinkControl, {
								value: {
									url: lien.url || '',
									title: lien.titre || '',
									opensInNewTab: !! lien.nouvelOnglet
								},
								settings: [ { id: 'opensInNewTab', title: __( 'Ouvrir dans un nouvel onglet', 'blocs-creator' ) } ],
								onChange: function ( neuf ) {
									poser( {
										url: neuf.url || '',
										titre: lien.titre || neuf.title || '',
										nouvelOnglet: !! neuf.opensInNewTab
									} );
								},
								onRemove: function () {
									poser( { url: '' } );
									setOuvert( false );
								}
							} )
						) : null
					)
					: el( TextControl, {
						label: __( 'Destination', 'blocs-creator' ),
						value: lien.url || '',
						onChange: function ( valeur ) { poser( { url: valeur } ); },
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true
					} ),
				el( ToggleControl, {
					label: __( 'Ouvrir dans un nouvel onglet', 'blocs-creator' ),
					checked: !! lien.nouvelOnglet,
					onChange: function ( valeur ) { poser( { nouvelOnglet: valeur } ); },
					__nextHasNoMarginBottom: true
				} )
			)
		);
	}

	/**
	 * Choix d'une ou plusieurs publications.
	 *
	 * @param {Object} props Propriétés.
	 * @return {Object} Élément React.
	 */
	function ContenuControl( props ) {
		var etat      = useState( [] );
		var liste     = etat[ 0 ];
		var setListe  = etat[ 1 ];
		var recherche = useState( '' );
		var terme     = recherche[ 0 ];
		var setTerme  = recherche[ 1 ];

		var types = ( ( props.champ.options && props.champ.options.types_contenu ) || [] ).join( ',' );

		useEffect( function () {
			var annule = false;

			apiFetch( {
				path: addQueryArgs( '/blocs-creator/v1/publications', {
					types: types,
					recherche: terme,
					nombre: 40
				} )
			} ).then( function ( resultats ) {
				if ( ! annule ) {
					setListe( resultats || [] );
				}
			} ).catch( function () {
				if ( ! annule ) {
					setListe( [] );
				}
			} );

			return function () { annule = true; };
		}, [ types, terme ] );

		if ( props.multiple ) {
			var choisies = Array.isArray( props.valeur ) ? props.valeur : [];
			var max      = ( props.champ.options && props.champ.options.max_contenus ) || 0;

			return el(
				BaseControl,
				{ label: props.champ.libelle, help: props.champ.aide || undefined, __nextHasNoMarginBottom: true },
				el( TextControl, {
					placeholder: __( 'Rechercher…', 'blocs-creator' ),
					value: terme,
					onChange: setTerme,
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true
				} ),
				el(
					'div',
					{ className: 'bc-editeur__contenus' },
					liste.map( function ( item ) {
						return el( CheckboxControl, {
							key: item.id,
							label: item.titre,
							checked: choisies.indexOf( item.id ) !== -1,
							onChange: function ( coche ) {
								var suite = choisies.filter( function ( id ) { return id !== item.id; } );

								if ( coche ) {
									suite.push( item.id );
								}

								props.ecrire( max > 0 ? suite.slice( 0, max ) : suite );
							},
							__nextHasNoMarginBottom: true
						} );
					} )
				)
			);
		}

		return el( SelectControl, {
			label: props.champ.libelle,
			help: props.champ.aide || undefined,
			value: String( props.valeur || 0 ),
			options: [ { value: '0', label: __( '— Choisir —', 'blocs-creator' ) } ].concat(
				liste.map( function ( item ) {
					return { value: String( item.id ), label: item.titre + ' (' + item.label + ')' };
				} )
			),
			onChange: function ( valeur ) { props.ecrire( Number( valeur ) ); },
			__next40pxDefaultSize: true,
			__nextHasNoMarginBottom: true
		} );
	}

	/**
	 * Choix de termes d'une taxonomie.
	 *
	 * @param {Object} props Propriétés.
	 * @return {Object} Élément React.
	 */
	function TermesControl( props ) {
		var etat     = useState( [] );
		var liste    = etat[ 0 ];
		var setListe = etat[ 1 ];

		var taxonomie = ( props.champ.options && props.champ.options.taxonomie ) || 'category';
		var unique    = !! ( props.champ.options && props.champ.options.terme_unique );
		var choisis   = Array.isArray( props.valeur ) ? props.valeur : [];

		useEffect( function () {
			var annule = false;

			apiFetch( {
				path: addQueryArgs( '/blocs-creator/v1/termes', { taxonomie: taxonomie } )
			} ).then( function ( resultats ) {
				if ( ! annule ) {
					setListe( resultats || [] );
				}
			} ).catch( function () {
				if ( ! annule ) {
					setListe( [] );
				}
			} );

			return function () { annule = true; };
		}, [ taxonomie ] );

		if ( unique ) {
			return el( SelectControl, {
				label: props.champ.libelle,
				help: props.champ.aide || undefined,
				value: String( choisis[ 0 ] || 0 ),
				options: [ { value: '0', label: __( '— Choisir —', 'blocs-creator' ) } ].concat(
					liste.map( function ( terme ) {
						return { value: String( terme.id ), label: terme.titre };
					} )
				),
				onChange: function ( valeur ) {
					props.ecrire( Number( valeur ) > 0 ? [ Number( valeur ) ] : [] );
				},
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true
			} );
		}

		return el(
			BaseControl,
			{ label: props.champ.libelle, help: props.champ.aide || undefined, __nextHasNoMarginBottom: true },
			el(
				'div',
				{ className: 'bc-editeur__contenus' },
				liste.map( function ( terme ) {
					return el( CheckboxControl, {
						key: terme.id,
						label: terme.titre,
						checked: choisis.indexOf( terme.id ) !== -1,
						onChange: function ( coche ) {
							var suite = choisis.filter( function ( id ) { return id !== terme.id; } );

							if ( coche ) {
								suite.push( terme.id );
							}

							props.ecrire( suite );
						},
						__nextHasNoMarginBottom: true
					} );
				} )
			)
		);
	}

	/**
	 * Un répéteur : des lignes portant les mêmes champs.
	 *
	 * @param {Object} props Propriétés.
	 * @return {Object} Élément React.
	 */
	function RepeteurControl( props ) {
		var lignes   = Array.isArray( props.valeur ) ? props.valeur : [];
		var reglages = props.champ.options || {};
		var sous     = props.champ.sous_champs || [];
		var nomLigne = reglages.libelle_ligne || __( 'ligne', 'blocs-creator' );
		var maxi     = reglages.max_lignes || 0;
		var mini     = reglages.min_lignes || 0;

		/**
		 * Remplace les lignes.
		 *
		 * @param {Array} suite Les nouvelles lignes.
		 */
		function poser( suite ) {
			props.ecrire( suite );
		}

		/**
		 * Retourne une ligne vierge.
		 *
		 * @return {Object} La ligne.
		 */
		function vierge() {
			var ligne = {};

			sous.forEach( function ( champ ) {
				if ( 'message' === champ.type ) {
					return;
				}

				ligne[ champ.cle ] = defautDe( champ );
			} );

			return ligne;
		}

		return el(
			BaseControl,
			{ label: props.champ.libelle, help: props.champ.aide || undefined, __nextHasNoMarginBottom: true },
			el(
				'div',
				{ className: 'bc-editeur__repeteur' },
				lignes.map( function ( ligne, index ) {
					return el(
						'div',
						{ key: index, className: 'bc-editeur__ligne' },
						el(
							'div',
							{ className: 'bc-editeur__ligne-entete' },
							el( 'span', { className: 'bc-editeur__ligne-titre' }, nomLigne + ' ' + ( index + 1 ) ),
							el(
								'div',
								{ className: 'bc-editeur__ligne-outils' },
								el( Button, {
									icon: 'arrow-up-alt2',
									size: 'small',
									label: __( 'Monter', 'blocs-creator' ),
									disabled: 0 === index,
									onClick: function () {
										var suite = lignes.slice();

										suite.splice( index - 1, 0, suite.splice( index, 1 )[ 0 ] );
										poser( suite );
									}
								} ),
								el( Button, {
									icon: 'arrow-down-alt2',
									size: 'small',
									label: __( 'Descendre', 'blocs-creator' ),
									disabled: index === lignes.length - 1,
									onClick: function () {
										var suite = lignes.slice();

										suite.splice( index + 1, 0, suite.splice( index, 1 )[ 0 ] );
										poser( suite );
									}
								} ),
								el( Button, {
									icon: 'trash',
									size: 'small',
									isDestructive: true,
									label: __( 'Supprimer', 'blocs-creator' ),
									disabled: lignes.length <= mini,
									onClick: function () {
										poser( lignes.filter( function ( item, i ) { return i !== index; } ) );
									}
								} )
							)
						),
						el(
							'div',
							{ className: 'bc-editeur__ligne-corps' },
							sous.map( function ( champ ) {
								return controle(
									champ,
									valeurDe( ligne, champ.cle ),
									function ( neuf ) {
										var suite = lignes.slice();

										suite[ index ] = Object.assign( {}, ligne );
										suite[ index ][ champ.cle ] = neuf;
										poser( suite );
									},
									{ dansBloc: true, valeurs: ligne }
								);
							} )
						)
					);
				} ),
				el( Button, {
					variant: 'secondary',
					icon: 'plus-alt2',
					disabled: maxi > 0 && lignes.length >= maxi,
					onClick: function () { poser( lignes.concat( [ vierge() ] ) ); }
				}, sprintf(
					/* translators: %s: nom d'une ligne, par exemple « témoignage ». */
					__( 'Ajouter %s', 'blocs-creator' ),
					nomLigne
				) )
			)
		);
	}

	/**
	 * Retourne la valeur par défaut d'un champ, côté éditeur.
	 *
	 * @param {Object} champ Le champ.
	 * @return {*} La valeur.
	 */
	function defautDe( champ ) {
		if ( Object.prototype.hasOwnProperty.call( champ, 'defaut_editeur' ) ) {
			return JSON.parse( JSON.stringify( champ.defaut_editeur ) );
		}
		var reglages = champ.options || {};

		switch ( champ.type ) {
			case 'bascule':
				return !! reglages.defaut_bascule;

			case 'nombre':
				return Number( reglages.defaut || 0 );

			case 'niveau-titre':
				return Number( reglages.defaut_niveau || 2 );

			case 'image':
			case 'fichier':
			case 'contenu':
				return 0;

			case 'galerie':
			case 'contenus':
			case 'taxonomie':
			case 'cases':
			case 'repeteur':
				return [];

			case 'lien':
				return { url: '', titre: reglages.defaut_libelle || '', nouvelOnglet: false };

			case 'groupe':
				return {};

			default:
				return reglages.defaut || '';
		}
	}

	/* ------------------------------------------------------------------ *
	 * Le bloc
	 * ------------------------------------------------------------------ */

	/**
	 * Construit le `edit` d'un bloc à partir de sa définition.
	 *
	 * Deux composants plutôt qu'un, et une condition à la fabrication plutôt
	 * qu'au rendu : `useInnerBlocksProps` est un hook, et un hook ne s'appelle
	 * pas derrière un `if`. La définition du bloc, elle, ne change jamais —
	 * le choix se fait donc ici, une fois pour toutes.
	 *
	 * @param {Object} definition La définition envoyée par le serveur.
	 * @return {Function} Le composant.
	 */
	function fabriquerEdit( definition ) {
		var champs      = definition.champs || [];
		var imbriques   = null;
		var dansCanevas = [];
		var dansPanneau = [];

		champs.forEach( function ( champ ) {
			if ( 'blocs-imbriques' === champ.type ) {
				imbriques = champ;
			} else if ( 'panneau' === champ.emplacement ) {
				dansPanneau.push( champ );
			} else {
				dansCanevas.push( champ );
			}
		} );

		/**
		 * Retourne le rappel d'écriture d'un champ de premier niveau.
		 *
		 * @param {Object} props Propriétés du bloc.
		 * @param {string} cle   Clé du champ.
		 * @return {Function} Le rappel.
		 */
		function ecrivain( props, cle ) {
			return function ( valeur ) {
				var morceau = {};

				morceau[ cle ] = valeur;
				props.setAttributes( morceau );
			};
		}

		/**
		 * La barre d'outils : basculer entre l'aperçu et le formulaire.
		 *
		 * @param {string}   mode    Mode courant.
		 * @param {Function} setMode Changement de mode.
		 * @return {Object} Élément React.
		 */
		function barre( mode, setMode ) {
			return el(
				BlockControls,
				{ group: 'block' },
				el(
					ToolbarGroup,
					{},
					el( ToolbarButton, {
						icon: 'visibility',
						label: __( 'Aperçu du bloc', 'blocs-creator' ),
						isActive: 'apercu' === mode,
						disabled: ! definition.gabarit,
						onClick: function () { setMode( 'apercu' ); }
					} ),
					el( ToolbarButton, {
						icon: 'edit',
						label: __( 'Modifier les champs', 'blocs-creator' ),
						isActive: 'formulaire' === mode,
						onClick: function () { setMode( 'formulaire' ); }
					} )
				)
			);
		}

		/**
		 * La colonne de droite, quand des champs y sont déclarés.
		 *
		 * @param {Object} props Propriétés du bloc.
		 * @return {Object|null} Élément React.
		 */
		function panneau( props ) {
			if ( ! dansPanneau.length ) {
				return null;
			}

			return el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: definition.titre, initialOpen: true },
					dansPanneau.map( function ( champ ) {
						return controle(
							champ,
							props.attributes[ champ.cle ],
							ecrivain( props, champ.cle ),
							{ dansBloc: false, valeurs: props.attributes }
						);
					} )
				)
			);
		}

		/**
		 * Le corps du bloc : son rendu serveur, ou son formulaire.
		 *
		 * @param {Object} props Propriétés du bloc.
		 * @param {string} mode  Mode courant.
		 * @return {Object} Élément React.
		 */
		function corps( props, mode ) {
			if ( 'apercu' === mode ) {
				return el( ServerSideRender, {
					block: props.name,
					attributes: props.attributes,
					httpMethod: 'POST',
					EmptyResponsePlaceholder: function () {
						return el(
							Placeholder,
							{ icon: 'layout', label: definition.titre },
							__( 'Ce bloc ne rend rien pour l\'instant. Remplissez ses champs, ou complétez son gabarit.', 'blocs-creator' )
						);
					},
					LoadingResponsePlaceholder: function () {
						return el( 'div', { className: 'bc-editeur__chargement' }, el( Spinner, {} ) );
					}
				} );
			}

			return el(
				'div',
				{ className: 'bc-editeur__formulaire' },
				el(
					'div',
					{ className: 'bc-editeur__entete' },
					el( 'span', { className: 'bc-editeur__nom' }, definition.titre )
				),
				dansCanevas.length
					? dansCanevas.map( function ( champ ) {
						return controle(
							champ,
							props.attributes[ champ.cle ],
							ecrivain( props, champ.cle ),
							{ dansBloc: true, valeurs: props.attributes }
						);
					} )
					: el(
						'p',
						{ className: 'bc-editeur__vide' },
						dansPanneau.length
							? __( 'Les réglages de ce bloc sont dans la colonne de droite.', 'blocs-creator' )
							: __( 'Ce bloc n\'a aucun champ.', 'blocs-creator' )
					)
			);
		}

		/**
		 * Le mode d'affichage de départ.
		 *
		 * Le rendu serveur ne vaut que s'il y a un gabarit à appeler : sans
		 * lui, l'aperçu montrerait le repli du plugin plutôt que le bloc.
		 *
		 * @return {string} `apercu` ou `formulaire`.
		 */
		function modeDepart() {
			return ( 'serveur' === definition.apercu && definition.gabarit ) ? 'apercu' : 'formulaire';
		}

		/**
		 * Les réglages des blocs imbriqués, tirés du champ qui les déclare.
		 *
		 * @return {Object} Les réglages d'useInnerBlocksProps.
		 */
		function reglagesImbriques() {
			var reglages = ( imbriques && imbriques.options ) || {};
			var modele   = ( reglages.gabarit_interne || '' )
				.split( /\r\n|\r|\n/ )
				.map( function ( ligne ) { return ligne.trim(); } )
				.filter( Boolean )
				.map( function ( nom ) { return [ nom, {} ]; } );

			return {
				allowedBlocks: ( reglages.blocs_autorises || [] ).length ? reglages.blocs_autorises : undefined,
				orientation: reglages.orientation || 'vertical',
				template: modele.length ? modele : undefined,
				templateLock: reglages.verrou_gabarit ? 'all' : false
			};
		}

		/**
		 * Le bloc sans blocs imbriqués.
		 *
		 * @param {Object} props Propriétés du bloc.
		 * @return {Object} Élément React.
		 */
		function EditSimple( props ) {
			var etat    = useState( modeDepart );
			var mode    = etat[ 0 ];
			var setMode = etat[ 1 ];

			var blockProps = useBlockProps( { className: 'bc-editeur bc-editeur--' + mode } );

			return el(
				Fragment,
				{},
				barre( mode, setMode ),
				panneau( props ),
				el( 'div', blockProps, corps( props, mode ) )
			);
		}

		/**
		 * Le bloc qui accueille d'autres blocs.
		 *
		 * @param {Object} props Propriétés du bloc.
		 * @return {Object} Élément React.
		 */
		function EditImbrique( props ) {
			var etat    = useState( modeDepart );
			var mode    = etat[ 0 ];
			var setMode = etat[ 1 ];

			var blockProps = useBlockProps( { className: 'bc-editeur bc-editeur--' + mode } );

			var propsInternes = useInnerBlocksProps(
				{ className: 'bc-editeur__imbriques' },
				reglagesImbriques()
			);

			return el(
				Fragment,
				{},
				barre( mode, setMode ),
				panneau( props ),
				el(
					'div',
					blockProps,
					corps( props, mode ),
					el( 'div', propsInternes )
				)
			);
		}

		return imbriques ? EditImbrique : EditSimple;
	}

	/* ------------------------------------------------------------------ *
	 * Enregistrement
	 * ------------------------------------------------------------------ */

	/*
	 * Les blocs sont déjà enregistrés côté PHP : titre, catégorie, icône,
	 * attributs et supports viennent de là, et WordPress les a transmis à
	 * l'éditeur avant ce fichier. On ne fournit donc que `edit` et `save` —
	 * le reste serait de la redite, donc de la dérive en puissance.
	 */
	/*
	 * Le garde-fou des blocs repris (BC_Registre::garde_reprises) refuse
	 * `registerBlockType` sur les noms qu'un pack ne doit plus enregistrer.
	 * Nous, si : on lève le drapeau le temps de nos propres déclarations.
	 */
	window.blocsCreatorInterne = true;

	( donnees.blocs || [] ).forEach( function ( definition ) {
		var aDesImbriques = ( definition.champs || [] ).some( function ( champ ) {
			return 'blocs-imbriques' === champ.type;
		} );

		wp.blocks.registerBlockType( definition.nom, {
			edit: fabriquerEdit( definition ),
			save: function () {
				// Le rendu est fait par PHP. Un bloc à blocs imbriqués doit
				// tout de même enregistrer ses enfants dans le contenu, sans
				// quoi le gabarit recevrait un $content vide.
				return aDesImbriques ? el( InnerBlocks.Content, {} ) : null;
			}
		} );
	} );

	window.blocsCreatorInterne = false;
}( window.wp ) );
