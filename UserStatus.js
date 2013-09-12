/**
 * JavaScript functions used by UserStatus.
 *
 * When updating this file, please remember to update
 * /extensions/SportsTeams/fanhome.js too, because these functions are
 * duplicated over there.
 *
 * @file
 * @date 11 July 2013 (second rewrite)
 */
var UserStatus = {
	posted: 0,

	/**
	 * Adds a status update to a network page and then reloads the page.
	 */
	addStatus: function() {
		var statusUpdateText = document.getElementById( 'user_status_text' ).value;
		if ( statusUpdateText && !UserStatus.posted ) {
			UserStatus.posted = 1;

			jQuery.post(
				mw.util.wikiScript( 'api' ), {
					action: 'userstatus',
					what: 'addnetworkstatus',
					sportId: __sport_id__,
					teamId: __team_id__,
					text: encodeURIComponent( statusUpdateText ),
					count: __updates_show__,
					format: 'json'
				},
				function() {
					UserStatus.posted = 0;
					window.location = __redirect_url__;
				}
			);
		}
	},

	/**
	 * Votes for a status update with the given ID.
	 * 'Vote' in this context is similar to 'like'.
	 *
	 * @param {id} number ID of the status message to vote for
	 * @param {id} vote
	 */
	voteStatus: function( id, vote ) {
		jQuery.post(
			mw.util.wikiScript( 'api' ), {
				action: 'userstatus',
				what: 'votestatus',
				us_id: id,
				vote: vote,
				format: 'json'
			},
			function( data ) {
				jQuery( '#user-status-vote-' + id ).text( data.userstatus.result );
			}
		);
	},

	/**
	 * Prompts the user for confirmation and upon confirmation, deletes a
	 * status message with the supplied ID and then hides the relevant DOM node
	 * on the page.
	 *
	 * @param {id} number Status message ID
	 */
	deleteMessage: function( id ) {
		if ( confirm( mw.msg( 'userstatus-confirm-delete' ) ) ) {
			jQuery.post(
				mw.util.wikiScript( 'api' ), {
					action: 'userstatus',
					what: 'deletestatus',
					us_id: id,
					format: 'json'
				},
				function() {
					jQuery( 'span#user-status-vote-' + id ).parent().parent()
						.parent().hide( 1000 );
					//window.location = mw.config.get( 'wgArticlePath' ).replace( '$1', 'Special:UserStatus' );
				}
			);
		}
	}
};

jQuery( document ).ready( function() {
	// Both Special:FanUpdates and Special:UserStatus have "delete" links, so...
	// UserStatus::displayStatusMessages() (UserStatusClass.php) also depends
	// on this
	jQuery( 'span.user-status-delete-link a' ).each( function( index ) {
		jQuery( this ).on( 'click', function() {
			UserStatus.deleteMessage( jQuery( this ).data( 'message-id' ) );
		} );
	} );

	// Code specific to Special:FanUpdates
	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) == 'FanUpdates' ) {
		jQuery( 'div.user-status-form input[type="button"]' ).on( 'click', function() {
			UserStatus.addStatus();
		} );
	}

	// Code specific to Special:UserStatus
	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) == 'UserStatus' ) {
		// Voting links
		jQuery( 'a.vote-status-link' ).each( function( index ) {
			jQuery( this ).on( 'click', function() {
				UserStatus.voteStatus( jQuery( this ).data( 'message-id' ), 1 );
			} );
		} );
	}
} );