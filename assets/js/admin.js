/**
 * SwiNOG Events – admin Media Library picker.
 *
 * Wires up the “Choose file” / “Remove file” buttons on the
 * Presentation edit screen to the WordPress Media Library.
 */
(function ( $ ) {
	'use strict';

	$( function () {
		var $hidden     = $( '#stgl_presentation_attachment_id' );
		var $removeFlag = $( '#stgl_presentation_attachment_remove' );
		var $current    = $( '.stgl-attachment-current' );
		var $link       = $( '#stgl-attachment-link' );
		var $pick       = $( '#stgl-pick-attachment' );
		var $remove     = $( '#stgl-attachment-remove' );

		if ( ! $hidden.length || ! $pick.length ) {
			return;
		}

		var frame;

		$pick.on( 'click', function ( e ) {
			e.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title:    ( window.stglSwinog && stglSwinog.pickTitle )  || 'Select presentation file',
				button:   { text: ( window.stglSwinog && stglSwinog.pickButton ) || 'Use this file' },
				multiple: false,
				library:  {
					type: [
						'application/pdf',
						'application/vnd.ms-powerpoint',
						'application/vnd.openxmlformats-officedocument.presentationml.presentation',
						'application/msword',
						'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
						'application/zip',
						'video/mp4',
						'text/plain'
					]
				}
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();

				$hidden.val( attachment.id );
				$removeFlag.val( '' );

				var label = attachment.filename || attachment.title || attachment.url;
				$link.attr( 'href', attachment.url ).text( label );
				$current.show();
			} );

			frame.open();
		} );

		$remove.on( 'click', function ( e ) {
			e.preventDefault();
			$hidden.val( '' );
			$removeFlag.val( '1' );
			$link.attr( 'href', '#' ).text( '' );
			$current.hide();
		} );
	} );
}( jQuery ) );
