/* global kursflowSettings, jQuery */
( function ( $ ) {
	'use strict';

	$( function () {
		var $btn    = $( '#kursflow-test-connection' );
		var $result = $( '#kursflow-test-result' );
		var $slug   = $( '#kursflow_tenant_slug' );

		$btn.on( 'click', function () {
			var slug = $slug.val().trim();
			if ( ! slug ) {
				$result
					.removeClass( 'kf-ok' )
					.addClass( 'kf-err' )
					.text( 'Bitte zuerst den Tenant-Slug eingeben.' )
					.show();
				return;
			}

			$btn.addClass( 'updating-message' ).prop( 'disabled', true );
			$result.hide().removeClass( 'kf-ok kf-err' );

			$.post(
				ajaxurl,
				{
					action:    kursflowSettings.action,
					_ajax_nonce: kursflowSettings.nonce,
					slug:      slug,
				},
				function ( response ) {
					if ( response.success ) {
						$result
							.addClass( 'kf-ok' )
							.text( '✓ ' + ( response.data.message || 'Verbindung erfolgreich.' ) )
							.show();
					} else {
						var msg = response.data && response.data.message
							? response.data.message
							: 'Verbindung fehlgeschlagen.';
						$result
							.addClass( 'kf-err' )
							.text( '✗ ' + msg )
							.show();
					}
				}
			).fail( function () {
				$result
					.addClass( 'kf-err' )
					.text( '✗ Netzwerkfehler — bitte Seite neu laden.' )
					.show();
			} ).always( function () {
				$btn.removeClass( 'updating-message' ).prop( 'disabled', false );
			} );
		} );
	} );
} )( jQuery );
