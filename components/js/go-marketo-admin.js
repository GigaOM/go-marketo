// this extra closure is just so we can the code sniffer to accept the
// "use strict" keyword here.
( function( $ ) {
	'use strict';

	$( function() {

		// function to run when the user clicks on the "Sync to Marketo"
		// button on a user's admin dashboard page
		$( '.go-marketo-sync' ).click(function( e ) {
			e.preventDefault();

			var $el       = $(this);
			var $parent   = $el.closest('.go-marketo');
			var $feedback = $parent.find('.feedback');
			var $results  = $parent.find('.results');

			$feedback.html( 'Synchronizing...' );

			var data = {
				action: 'go_marketo_user_sync',
				go_marketo_user_sync_user: $( '.go-marketo .user' ).val()
			};

			$.post(ajaxurl, data, function(response) {
				$results.html( response );
				$feedback.html( 'Synchronization complete.' );
			});
		});//END click callback
	});
})( jQuery );
