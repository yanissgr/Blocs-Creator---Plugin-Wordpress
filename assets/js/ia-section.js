/*
 * Le panneau « Remplir avec l'IA », dans la colonne de droite d'un bloc créé
 * avec Blocs Creator.
 *
 * Des notes en vrac ; l'IA propose le texte des champs du bloc — titre,
 * texte, libellés des boutons, lignes d'un répéteur — d'après sa définition.
 * La proposition se relit dans une fenêtre, champ par champ, avant de
 * remplacer quoi que ce soit. Les images, les adresses des liens et les
 * réglages d'affichage ne sont jamais touchés.
 *
 * Le panneau se greffe sur l'éditeur commun des blocs (assets/js/editeur.js)
 * par le filtre `editor.BlockEdit` : il ne concerne que les blocs listés par
 * PHP, ceux qui ont au moins un champ de texte. Voir
 * includes/class-blocs-creator-ia.php.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.blockEditor || ! window.blocsCreatorIaSection ) {
		return;
	}

	var el                = wp.element.createElement;
	var Fragment          = wp.element.Fragment;
	var useState          = wp.element.useState;
	var __                = wp.i18n.__;
	var c                 = wp.components;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var apiFetch          = wp.apiFetch;
	var useSelect         = wp.data.useSelect;

	var BLOCS = window.blocsCreatorIaSection.blocs || [];

	/**
	 * La fenêtre de relecture : chaque champ, tel qu'il sera rempli.
	 *
	 * @param {Object} props Propriétés.
	 * @return {Element} La fenêtre.
	 */
	function Relecture( props ) {
		return el(
			c.Modal,
			{
				title: __( 'Contenu proposé', 'blocs-creator' ),
				onRequestClose: props.fermer,
				size: 'medium',
				className: 'bc-ia-fenetre',
			},
			el(
				'dl',
				{ className: 'bc-ia-champs' },
				props.proposition.apercu.map( function ( champ, rang ) {
					return el(
						Fragment,
						{ key: rang },
						el( 'dt', {}, champ.libelle ),
						champ.lignes
							? el(
								'dd',
								{},
								el(
									'ol',
									{},
									champ.lignes.map( function ( ligne, numero ) {
										return el( 'li', { key: numero }, ligne || '—' );
									} )
								)
							)
							: el( 'dd', {}, champ.texte || '—' )
					);
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
					props.enCours ? __( 'Rédaction en cours…', 'blocs-creator' ) : __( 'Une autre proposition', 'blocs-creator' )
				),
				el(
					c.Button,
					{ variant: 'primary', onClick: props.appliquer, disabled: props.enCours, accessibleWhenDisabled: true },
					__( 'Remplir le bloc', 'blocs-creator' )
				)
			)
		);
	}

	/**
	 * Le panneau d'un bloc.
	 *
	 * @param {Object} props Propriétés du bloc.
	 * @return {Element} Le panneau.
	 */
	function Panneau( props ) {
		var postId = useSelect( function ( choisir ) {
			var editeur = choisir( 'core/editor' );

			return editeur && editeur.getCurrentPostId ? editeur.getCurrentPostId() : 0;
		}, [] );

		var notesEtat       = useState( '' );
		var enCoursEtat     = useState( false );
		var erreurEtat      = useState( '' );
		var propositionEtat = useState( null );

		function proposer() {
			enCoursEtat[ 1 ]( true );
			erreurEtat[ 1 ]( '' );

			apiFetch( {
				path: '/blocs-creator/v1/ia/section',
				method: 'POST',
				data: {
					bloc: props.name,
					notes: notesEtat[ 0 ],
					valeurs: props.attributes,
					post_id: 'number' === typeof postId ? postId : 0,
				},
			} )
				.then( function ( reponse ) {
					propositionEtat[ 1 ]( reponse );
				} )
				.catch( function ( probleme ) {
					erreurEtat[ 1 ]( ( probleme && probleme.message ) || __( 'L’IA n’a pas pu proposer de contenu. Réessayez.', 'blocs-creator' ) );
				} )
				.finally( function () {
					enCoursEtat[ 1 ]( false );
				} );
		}

		function appliquer() {
			props.setAttributes( propositionEtat[ 0 ].valeurs );
			propositionEtat[ 1 ]( null );

			wp.data.dispatch( 'core/notices' ).createSuccessNotice(
				__( 'Bloc rempli. Relisez-le, et annulez (Ctrl/Cmd + Z) s’il ne convient pas.', 'blocs-creator' ),
				{ type: 'snackbar' }
			);
		}

		return el(
			c.PanelBody,
			{ title: __( 'Remplir avec l’IA', 'blocs-creator' ), initialOpen: false, className: 'bc-ia' },
			el(
				'p',
				{ className: 'bc-ia__aide' },
				__( 'Donnez vos notes : l’IA propose le texte des champs du bloc, sans toucher aux images ni aux liens. Vous relisez avant de remplir.', 'blocs-creator' )
			),
			el( c.TextareaControl, {
				__nextHasNoMarginBottom: true,
				label: __( 'Vos notes', 'blocs-creator' ),
				value: notesEtat[ 0 ],
				onChange: notesEtat[ 1 ],
				rows: 6,
				help: __( 'Vide : l’IA retravaille le texte actuel.', 'blocs-creator' ),
			} ),
			el( 'div', { className: 'bc-ia__espace' } ),
			el(
				c.Button,
				{
					variant: 'secondary',
					className: 'bc-ia__bouton',
					onClick: proposer,
					isBusy: enCoursEtat[ 0 ],
					disabled: enCoursEtat[ 0 ],
					accessibleWhenDisabled: true,
					__next40pxDefaultSize: true,
				},
				enCoursEtat[ 0 ] ? __( 'Rédaction en cours…', 'blocs-creator' ) : __( 'Proposer un contenu', 'blocs-creator' )
			),
			! propositionEtat[ 0 ] && erreurEtat[ 0 ] && el( c.Notice, { status: 'error', isDismissible: false }, erreurEtat[ 0 ] ),
			propositionEtat[ 0 ] && el( Relecture, {
				proposition: propositionEtat[ 0 ],
				enCours: enCoursEtat[ 0 ],
				erreur: erreurEtat[ 0 ],
				recommencer: proposer,
				appliquer: appliquer,
				fermer: function () {
					propositionEtat[ 1 ]( null );
					erreurEtat[ 1 ]( '' );
				},
			} )
		);
	}

	var avecPanneau = wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			if ( -1 === BLOCS.indexOf( props.name ) ) {
				return el( BlockEdit, props );
			}

			return el(
				Fragment,
				{},
				el( BlockEdit, props ),
				props.isSelected && el( InspectorControls, {}, el( Panneau, props ) )
			);
		};
	}, 'avecRemplirIa' );

	wp.hooks.addFilter( 'editor.BlockEdit', 'blocs-creator/ia-section', avecPanneau );
}( window.wp ) );
