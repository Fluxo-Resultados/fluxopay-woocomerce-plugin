(function ( $ ) {
	'use strict';

	$( function () {

		/**
		 * Switch transparent checkout options display basead in payment type.
		 *
		 * @param {String} method
		 */
		function fluxoPaySwitchTCOptions( method ) {
			var fields  = $( '#woocommerce_fluxoPay_tc_card' ).closest( '.form-table' ),
				heading = fields.prev( 'h3' );

			if ( 'transparent' === method ) {
				fields.show();
				heading.show();
			} else {
				fields.hide();
				heading.hide();
			}
		}

		/**
		 * Switch banking ticket message display.
		 *
		 * @param {String} checked
		 */
		function fluxoPaySwitchOptions( checked ) {
			var fields = $( '#woocommerce_fluxoPay_tc_ticket_message' ).closest( 'tr' );

			if ( checked ) {
				fields.show();
			} else {
				fields.hide();
			}
		}

		/**
		 * Awitch user data for sandbox and production.
		 *
		 * @param {String} checked
		 */
		function fluxoPaySwitchUserData( checked ) {
			var token = $( '#woocommerce_fluxoPay_token' ).closest( 'tr' );
			token.show();
		}

		fluxoPaySwitchTCOptions( $( '#woocommerce_fluxoPay_method' ).val() );

		$( 'body' ).on( 'change', '#woocommerce_fluxoPay_method', function () {
			fluxoPaySwitchTCOptions( $( this ).val() );
		}).change();

		fluxoPaySwitchUserData( $( '#woocommerce_fluxoPay_sandbox' ).is( ':checked' ) );
		$( 'body' ).on( 'change', '#woocommerce_fluxoPay_sandbox', function () {
			fluxoPaySwitchUserData( $( this ).is( ':checked' ) );
		});
	});

}( jQuery ));
