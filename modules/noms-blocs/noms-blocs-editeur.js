/*
 * La case « Afficher le nom des blocs », dans le panneau « Résumé » d'une
 * page (modules/noms-blocs/module.php).
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.editor || ! wp.editor.PluginPostStatusInfo ) {
		return;
	}

	var el  = wp.element.createElement;
	var __  = wp.i18n.__;
	var CLE = '_blocs_creator_noms_blocs';

	function Case() {
		var meta = wp.data.useSelect( function ( choisir ) {
			return choisir( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );

		var editeur = wp.data.useDispatch( 'core/editor' );

		if ( ! Object.prototype.hasOwnProperty.call( meta, CLE ) ) {
			return null;
		}

		return el(
			wp.editor.PluginPostStatusInfo,
			{ className: 'bc-noms-blocs-case' },
			el( wp.components.CheckboxControl, {
				__nextHasNoMarginBottom: true,
				label: __( 'Afficher le nom des blocs', 'blocs-creator' ),
				help: __( 'Sur le site, chaque bloc de la page porte son nom : pour une page d’exemple destinée à la rédaction.', 'blocs-creator' ),
				checked: !! meta[ CLE ],
				onChange: function ( coche ) {
					var suite = {};

					suite[ CLE ] = coche;
					editeur.editPost( { meta: suite } );
				},
			} )
		);
	}

	wp.plugins.registerPlugin( 'blocs-creator-noms-blocs', { render: Case } );
}( window.wp ) );
