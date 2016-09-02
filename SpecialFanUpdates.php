<?php
/**
 * A special page to show all status updates from the various users ("fans") of
 * a given network.
 * This special page also allows a user to post an update to a network page
 * without being a member of the network in question.
 *
 * @file
 * @ingroup Extensions
 */
class ViewFanUpdates extends UnlistedSpecialPage {

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'FanUpdates' );
	}

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the special page or null
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		$messages_show = 25;
		$updates_show = 25; // just an arbitrary value to stop PHP from complaining on 12 August 2011 --ashley
		$output = '';
		$sport_id = $request->getInt( 'sport_id' );
		$team_id = $request->getInt( 'team_id' );
		$page = $request->getInt( 'page', 1 );

		if ( $team_id ) {
			$team = SportsTeams::getTeam( $team_id );
			$network_name = $team['name'];
		} elseif ( $sport_id ) {
			$sport = SportsTeams::getSport( $sport_id );
			$network_name = $sport['name'];
		} else {
			// No sports ID nor team ID...bail out or we'll get a database
			// error...
			$out->setPageTitle( $this->msg( 'userstatus-woops' )->plain() );
			$output = '<div class="relationship-request-message">' .
				$this->msg( 'userstatus-invalid-link' )->text() . '</div>';
			$output .= '<div class="relationship-request-buttons">';
			$output .= '<input type="button" class="site-button" value="' .
				$this->msg( 'mainpage' )->text() .
				"\" onclick=\"window.location='" .
				htmlspecialchars( Title::newMainPage()->getFullURL() ) . "'\"/>";
			/* removed because I was too lazy to port the error message over :P
			if ( $user->isLoggedIn() ) {
				$output .= ' <input type="button" class="site-button" value="' .
					$this->msg( 'st_network_your_profile' )->text() .
					"\" onclick=\"window.location='" .
					htmlspecialchars( Title::makeTitle( NS_USER, $wgUser->getName() )->getFullURL() ) . "'\"/>";
			}
			*/
			$output .= '</div>';
			$out->addHTML( $output );
			return true;
		}

		$out->setPageTitle( $this->msg( 'userstatus-network-thoughts', $network_name )->text() );

		/**
		 * Config for the page
		 */
		$per_page = $messages_show;

		$s = new UserStatus();
		$total = $s->getNetworkUpdatesCount( $sport_id, $team_id );
		$messages = $s->getStatusMessages(
			0,
			$sport_id,
			$team_id,
			$messages_show,
			$page
		);

		$output .= '<div class="gift-links">'; // @todo FIXME: rename!
		$output .= '<a href="' .
			SportsTeams::getNetworkURL( $sport_id, $team_id ) . '">' .
				$this->msg( 'userstatus-back-to-network' )->text() . '</a>';
		$output .= '</div>';

		if ( $page == 1 ) {
			$start = 1;
		} else {
			$start = ( $page - 1 ) * $per_page + 1;
		}
		$end = $start + ( count( $messages ) ) - 1;

		if ( $total ) {
			$output .= '<div class="user-page-message-top">
			<span class="user-page-message-count">' .
				$this->msg( 'userstatus-showing-thoughts', $start, $end, $total )->parse() .
			'</span>
		</div>';
		}

		/**
		 * Build next/prev navigation
		 */
		$numofpages = $total / $per_page;

		if ( $numofpages > 1 ) {
			$output .= '<div class="page-nav">';
			if ( $page > 1 ) {
				$output .= '<a href="' .
					SportsTeams::getFanUpdatesURL( $sport_id, $team_id ) .
					'&page=' . ( $page - 1 ) . '">' . $this->msg( 'userstatus-prev' )->text() .
					'</a> ';
			}

			if ( ( $total % $per_page ) != 0 ) {
				$numofpages++;
			}
			if ( $numofpages >= 9 && $page < $total ) {
				$numofpages = 9 + $page;
				if ( $numofpages >= ( $total / $per_page ) ) {
					$numofpages = ( $total / $per_page ) + 1;
				}
			}

			for ( $i = 1; $i <= $numofpages; $i++ ) {
				if ( $i == $page ) {
					$output .= ( $i . ' ');
				} else {
					$output .= '<a href="' .
						SportsTeams::getFanUpdatesURL( $sport_id, $team_id ) .
						"&page=$i\">$i</a> ";
				}
			}

			if( ( $total - ( $per_page * $page ) ) > 0 ) {
				$output .= ' <a href="' .
					SportsTeams::getFanUpdatesURL( $sport_id, $team_id ) .
					'&page=' . ( $page + 1 ) . '">' . $this->msg( 'userstatus-next' )->text() .
					'</a>';
			}
			$output .= '</div><p>';
		}

		// Add CSS & JS
		$out->addModuleStyles( 'ext.userStatus.styles' );
		$out->addModules( 'ext.userStatus.scripts' );

		// Registered users who are not blocked can add status updates when the
		// database is not locked
		if ( $user->isLoggedIn() && !$user->isBlocked() && !wfReadOnly() ) {
			$output .= "<script>
				var __sport_id__ = {$sport_id};
				var __team_id__ = {$team_id};
				var __updates_show__ = \"{$updates_show}\";
				var __redirect_url__ = \"" . str_replace( '&amp;', '&', SportsTeams::getFanUpdatesURL( $sport_id, $team_id ) ) . "\";
			</script>";

			$output .= "<div class=\"user-status-form\">
			<span class=\"user-name-top\">{$user->getName()}</span> <input type=\"text\" name=\"user_status_text\" id=\"user_status_text\" size=\"40\"/>
			<input type=\"button\" value=\"" . $this->msg( 'userstatus-btn-add' )->text() . '" class="site-button" />
			</div>';
		}

		$output .= '<div class="user-status-container">';
		if ( $messages ) {
			foreach ( $messages as $message ) {
				$user = Title::makeTitle( NS_USER, $message['user_name'] );
				$avatar = new wAvatar( $message['user_id'], 'm' );

				$messages_link = '<a href="' .
					UserStatus::getUserUpdatesURL( $message['user_name'] ) . '">' .
					$this->msg( 'userstatus-view-all-updates', $message['user_name'] )->text() .
					'</a>';
				$delete_link = '';
				// Allow the owner of the status update and privileged users to
				// delete it
				if (
					$user->getName() == $message['user_name'] ||
					$user->isAllowed( 'delete-status-updates' )
				)
				{
					$delete_link = "<span class=\"user-status-delete-link\">
							<a href=\"javascript:void(0);\" data-message-id=\"{$message['id']}\">" .
						$this->msg( 'userstatus-delete' )->text() . '</a>
					</span>';
				}

				$message_text = preg_replace_callback(
					'/(<a[^>]*>)(.*?)(<\/a>)/i',
					array( 'UserStatus', 'cutLinkText' ),
					$message['text']
				);

				$output .= "<div class=\"user-status-row\">
					<a href=\"{$user->getFullURL()}\">{$avatar->getAvatarURL()}</a>
					<a href=\"{$user->getFullURL()}\"><b>{$message['user_name']}</b></a> {$message_text}
					<span class=\"user-status-date\">" .
						$this->msg( 'userstatus-ago', UserStatus::getTimeAgo( $message['timestamp'] ) )->text() .
					'</span>
				</div>';
			}
		} else {
			$output .= '<p>' . $this->msg( 'userstatus-no-updates' )->text() . '</p>';
		}

		$output .= '</div>';

		$out->addHTML( $output );
	}

}