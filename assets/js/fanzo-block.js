/**
 * Fanzo Sports Feed — Gutenberg Block (Editor Script)
 *
 * Registers the "fanzo-sports-feed/fixture-feed" block in the block editor.
 * No build step required — uses the globally available wp.* APIs.
 *
 * @package FanzoSportsFeed
 * @since   1.0.0
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	var el          = element.createElement;
	var __          = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var TextControl = components.TextControl;
	var PanelBody   = components.PanelBody;
	var Placeholder = components.Placeholder;
	var Icon        = components.Icon;

	blocks.registerBlockType( 'fanzo-sports-feed/fixture-feed', {
		title       : __( 'Fanzo Sports Feed', 'fanzo-sports-feed' ),
		description : __( 'Display live sports fixtures from the Fanzo feed.', 'fanzo-sports-feed' ),
		category    : 'widgets',
		icon        : 'megaphone',
		supports    : {
			html  : false,
			align : [ 'wide', 'full' ],
		},
		attributes  : {
			venue : {
				type    : 'string',
				default : '',
			},
		},

		/**
		 * Block editor interface.
		 *
		 * @param {Object} props Block properties.
		 * @returns {Element}
		 */
		edit : function ( props ) {
			var venue = props.attributes.venue;

			function onChangeVenue( newVenue ) {
				props.setAttributes( { venue : newVenue } );
			}

			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title        : __( 'Feed Settings', 'fanzo-sports-feed' ),
							initialOpen  : true,
						},
						el( TextControl, {
							label   : __( 'Venue API URL (optional)', 'fanzo-sports-feed' ),
							help    : __( 'Leave blank to use the global URL from plugin settings. Provide a full Fanzo XML URL to override for this block only.', 'fanzo-sports-feed' ),
							value   : venue,
							type    : 'url',
							onChange: onChangeVenue,
						} )
					)
				),
				el(
					Placeholder,
					{
						icon  : 'megaphone',
						label : __( 'Fanzo Sports Feed', 'fanzo-sports-feed' ),
					},
					el(
						'div',
						{ className : 'fanzo-block-preview' },
						el( 'p', null, __( 'The live fixture feed will appear here on the frontend.', 'fanzo-sports-feed' ) ),
						venue
							? el( 'p', null,
								el( 'strong', null, __( 'Venue URL: ', 'fanzo-sports-feed' ) ),
								el( 'span', null, venue )
							)
							: el( 'p', null, __( 'Using global venue URL from plugin settings.', 'fanzo-sports-feed' ) )
					)
				)
			);
		},

		/**
		 * Save function — null because this is server-side rendered.
		 *
		 * @returns {null}
		 */
		save : function () {
			return null;
		},
	} );

} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n
);
