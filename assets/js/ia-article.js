/*
 * Le panneau « Rédiger avec l'IA », dans la colonne de droite d'un article.
 *
 * Des notes en vrac et une longueur ; l'IA écrit un brouillon (titre, chapô,
 * texte en Markdown), l'éditeur le change en blocs, et on le relit dans une
 * fenêtre avant de l'insérer — dans un article vide, ou à la suite du texte.
 * Rien n'est publié tout seul. Voir includes/class-blocs-creator-ia.php.
 *
 * JavaScript natif, sans outil de build — comme le reste du plugin.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.editor || ! window.blocsCreatorIaArticle ) {
		return;
	}

	var el                = wp.element.createElement;
	var useState          = wp.element.useState;
	var __                = wp.i18n.__;
	var registerPlugin    = wp.plugins.registerPlugin;
	var c                 = wp.components;
	var select            = wp.data.select;
	var useDispatch       = wp.data.useDispatch;
	var useSelect         = wp.data.useSelect;
	var blocks            = wp.blocks;
	var apiFetch          = wp.apiFetch;
	var PanneauDocument   = wp.editor.PluginDocumentSettingPanel;

	var TYPES = window.blocsCreatorIaArticle.types || [ 'post' ];

	// Assez de notes pour que l'IA n'ait pas à inventer.
	var MINIMUM = 20;

	var LONGUEURS = [
		{ value: 'court', label: __( 'Court (≈ 250 mots)', 'blocs-creator' ) },
		{ value: 'moyen', label: __( 'Moyen (≈ 500 mots)', 'blocs-creator' ) },
		{ value: 'long', label: __( 'Long (≈ 900 mots)', 'blocs-creator' ) },
	];

	/**
	 * La fenêtre de relecture : le brouillon tel qu'il sera inséré.
	 *
	 * @param {Object} props Propriétés.
	 * @return {Element} La fenêtre.
	 */
	function Relecture( props ) {
		var brouillon = props.brouillon;

		return el(
			c.Modal,
			{
				title: __( 'Brouillon proposé', 'blocs-creator' ),
				onRequestClose: props.fermer,
				size: 'large',
				className: 'bc-ia-fenetre',
			},
			el(
				'div',
				{ className: 'bc-ia-apercu' },
				brouillon.titre && el( 'p', { className: 'bc-ia-apercu__titre' }, brouillon.titre ),
				brouillon.chapo && el( 'p', { className: 'bc-ia-apercu__chapo' }, brouillon.chapo ),
				el( 'div', { dangerouslySetInnerHTML: { __html: blocks.serialize( brouillon.blocs ) } } )
			),
			el(
				'div',
				{ className: 'bc-ia-options' },
				brouillon.titre && el( c.CheckboxControl, {
					__nextHasNoMarginBottom: true,
					label: __( 'Utiliser ce titre', 'blocs-creator' ),
					checked: props.garderTitre,
					onChange: props.setGarderTitre,
				} ),
				brouillon.chapo && el( c.CheckboxControl, {
					__nextHasNoMarginBottom: true,
					label: __( 'Utiliser ce chapô comme extrait (le texte sous le titre)', 'blocs-creator' ),
					checked: props.garderChapo,
					onChange: props.setGarderChapo,
				} )
			),
			props.erreur && el( c.Notice, { status: 'error', isDismissible: false }, props.erreur ),
			el(
				'div',
				{ className: 'bc-ia-actions' },
				el( c.Button, { variant: 'tertiary', onClick: props.fermer }, __( 'Annuler', 'blocs-creator' ) ),
				el(
					c.Button,
					{ variant: 'secondary', onClick: props.recommencer, isBusy: props.enCours, disabled: props.enCours, accessibleWhenDisabled: true },
					props.enCours ? __( 'Rédaction en cours…', 'blocs-creator' ) : __( 'Rédiger une autre version', 'blocs-creator' )
				),
				el(
					c.Button,
					{ variant: 'primary', onClick: props.inserer, disabled: props.enCours, accessibleWhenDisabled: true },
					__( 'Insérer', 'blocs-creator' )
				)
			)
		);
	}

	function PanneauRedaction() {
		var infos = useSelect( function ( choisir ) {
			var editeur = choisir( 'core/editor' );

			return {
				type: editeur.getCurrentPostType(),
				postId: editeur.getCurrentPostId(),
				titre: editeur.getEditedPostAttribute( 'title' ) || '',
				extrait: editeur.getEditedPostAttribute( 'excerpt' ) || '',
				categories: editeur.getEditedPostAttribute( 'categories' ) || [],
			};
		}, [] );

		var editeur = useDispatch( 'core/editor' );
		var avis    = useDispatch( 'core/notices' );

		var notesEtat       = useState( '' );
		var longueurEtat    = useState( 'moyen' );
		var enCoursEtat     = useState( false );
		var erreurEtat      = useState( '' );
		var brouillonEtat   = useState( null );
		var garderTitreEtat = useState( true );
		var garderChapoEtat = useState( true );

		var notes = notesEtat[ 0 ];
		var brouillon = brouillonEtat[ 0 ];

		if ( -1 === TYPES.indexOf( infos.type ) ) {
			return null;
		}

		var assezDeNotes = notes.trim().length >= MINIMUM;

		function rediger() {
			enCoursEtat[ 1 ]( true );
			erreurEtat[ 1 ]( '' );

			apiFetch( {
				path: '/blocs-creator/v1/ia/article',
				method: 'POST',
				data: {
					notes: notes,
					longueur: longueurEtat[ 0 ],
					titre: infos.titre,
					categories: infos.categories,
					post_id: infos.postId,
				},
			} )
				.then( function ( reponse ) {
					var nouveaux = blocks.pasteHandler( { plainText: reponse.markdown || '', mode: 'BLOCKS' } );

					if ( ! nouveaux.length ) {
						throw new Error( __( 'L’IA n’a rien renvoyé d’utilisable. Réessayez.', 'blocs-creator' ) );
					}

					brouillonEtat[ 1 ]( { titre: reponse.titre || '', chapo: reponse.chapo || '', blocs: nouveaux } );

					// Le titre et le chapô déjà écrits restent, sauf si on coche.
					garderTitreEtat[ 1 ]( '' === infos.titre.trim() );
					garderChapoEtat[ 1 ]( '' === infos.extrait.trim() );
				} )
				.catch( function ( probleme ) {
					erreurEtat[ 1 ]( ( probleme && probleme.message ) || __( 'Le brouillon n’a pas pu être rédigé. Réessayez.', 'blocs-creator' ) );
				} )
				.finally( function () {
					enCoursEtat[ 1 ]( false );
				} );
		}

		function inserer() {
			var existants = select( 'core/editor' ).getEditorBlocks();
			var vide      = ! existants.length || ( 1 === existants.length && blocks.isUnmodifiedDefaultBlock( existants[ 0 ] ) );
			var modifs    = {};

			editeur.resetEditorBlocks( vide ? brouillon.blocs : existants.concat( brouillon.blocs ) );

			if ( garderTitreEtat[ 0 ] && brouillon.titre ) {
				modifs.title = brouillon.titre;
			}

			if ( garderChapoEtat[ 0 ] && brouillon.chapo ) {
				modifs.excerpt = brouillon.chapo;
			}

			if ( Object.keys( modifs ).length ) {
				editeur.editPost( modifs );
			}

			brouillonEtat[ 1 ]( null );
			avis.createSuccessNotice(
				vide
					? __( 'Brouillon inséré. Relisez-le et corrigez-le avant de publier.', 'blocs-creator' )
					: __( 'Brouillon ajouté à la fin. Relisez-le avant de publier.', 'blocs-creator' ),
				{ type: 'snackbar' }
			);
		}

		return el(
			PanneauDocument,
			{
				name: 'blocs-creator-ia-article',
				title: __( 'Rédiger avec l’IA', 'blocs-creator' ),
				className: 'bc-ia',
			},
			el(
				'p',
				{ className: 'bc-ia__aide' },
				__( 'Donnez vos notes en vrac : l’IA en fait un article, sans rien inventer d’autre. Vous le relisez avant de l’insérer.', 'blocs-creator' )
			),
			el( c.TextareaControl, {
				__nextHasNoMarginBottom: true,
				label: __( 'Vos notes', 'blocs-creator' ),
				value: notes,
				onChange: notesEtat[ 1 ],
				rows: 8,
				help: assezDeNotes ? '' : __( 'Quelques phrases suffisent : qui, quoi, quand, et le message à faire passer.', 'blocs-creator' ),
			} ),
			el( 'div', { className: 'bc-ia__espace' } ),
			el( c.SelectControl, {
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
				label: __( 'Longueur', 'blocs-creator' ),
				value: longueurEtat[ 0 ],
				options: LONGUEURS,
				onChange: longueurEtat[ 1 ],
			} ),
			el( 'div', { className: 'bc-ia__espace' } ),
			el(
				c.Button,
				{
					variant: 'primary',
					className: 'bc-ia__bouton',
					onClick: rediger,
					isBusy: enCoursEtat[ 0 ],
					disabled: enCoursEtat[ 0 ] || ! assezDeNotes,
					accessibleWhenDisabled: true,
					__next40pxDefaultSize: true,
				},
				enCoursEtat[ 0 ] ? __( 'Rédaction en cours…', 'blocs-creator' ) : __( 'Rédiger le brouillon', 'blocs-creator' )
			),
			! brouillon && erreurEtat[ 0 ] && el( c.Notice, { status: 'error', isDismissible: false }, erreurEtat[ 0 ] ),
			brouillon && el( Relecture, {
				brouillon: brouillon,
				enCours: enCoursEtat[ 0 ],
				erreur: erreurEtat[ 0 ],
				garderTitre: garderTitreEtat[ 0 ],
				garderChapo: garderChapoEtat[ 0 ],
				setGarderTitre: garderTitreEtat[ 1 ],
				setGarderChapo: garderChapoEtat[ 1 ],
				recommencer: rediger,
				inserer: inserer,
				fermer: function () {
					brouillonEtat[ 1 ]( null );
					erreurEtat[ 1 ]( '' );
				},
			} )
		);
	}

	registerPlugin( 'blocs-creator-ia-article', { render: PanneauRedaction } );
}( window.wp ) );
