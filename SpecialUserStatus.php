<?php

class ViewUserStatus extends UnlistedSpecialPage {

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'UserStatus' );
	}

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the special page or null
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$currentUser = $this->getUser();

		$messages_show = 25;
		$output = '';
		$user_name = $request->getVal( 'user', $par );
		$page = $request->getInt( 'page', 1 );

		/**
		 * Redirect Non-logged in users to Login Page
		 * It will automatically return them to their Status page
		 */
		if ( $currentUser->getID() == 0 && $user_name == '' ) {
			$out->setPageTitle( $this->msg( 'userstatus-woops' )->text() );
			$login = SpecialPage::getTitleFor( 'Userlogin' );
			$out->redirect( $login->getFullURL( 'returnto=Special:UserStatus' ) );
			return false;
		}

		/**
		 * If no user is set in the URL, we assume its the current user
		 */
		if ( !$user_name ) {
			$user_name = $currentUser->getName();
		}
		$user_id = User::idFromName( $user_name );
		$user = Title::makeTitle( NS_USER, $user_name );

		/**
		 * Error message for username that does not exist (from URL)
		 */
		if ( $user_id == 0 ) {
			$out->setPageTitle( $this->msg( 'userstatus-woops' )->text() );
			$out->addHTML( $this->msg( 'userstatus-no-user' )->text() );
			return false;
		}

		/**
		 * Config for the page
		 */
		$per_page = $messages_show;

		$stats = new UserStats( $user_id, $user_name );
		$stats_data = $stats->getUserStats();
		$total = $stats_data['user_status_count'];

		$s = new UserStatus();
		$messages = $s->getStatusMessages( $user_id, 0, 0, $messages_show, $page );

		// Set a different page title depending on whose thoughts (yours or
		// someone else's) we're viewing
		if ( !( $currentUser->getName() == $user_name ) ) {
			$out->setPageTitle( $this->msg( 'userstatus-user-thoughts', $user_name )->text() );
		} else {
			$out->setPageTitle( $this->msg( 'userstatus-your-thoughts' )->text() );
		}

		// "Back to (your|$user_name's) profile" link
		$output .= '<div class="gift-links">'; // @todo FIXME: this really should be renamed...
		if ( !( $currentUser->getName() == $user_name ) ) {
			$output .= "<a href=\"{$user->getFullURL()}\">" .
				$this->msg( 'userstatus-back-user-profile', $user_name )->text() . '</a>';
		} else {
			$output .= '<a href="' . $currentUser->getUserPage()->getFullURL() . '">' .
				$this->msg( 'userstatus-back-your-profile' )->text() . '</a>';
		}
		$output .= '</div>';

		if ( $page == 1 ) {
			$start = 1;
		} else {
			$start = ( $page - 1 ) * $per_page + 1;
		}

		$end = $start + ( count( $messages ) ) - 1;
		wfDebug( "total = {$total}" );

		if ( $total ) {
			$output .= '<div class="user-page-message-top">
				<span class="user-page-message-count" style="font-size: 11px; color: #666666;">' .
					$this->msg( 'userstatus-showing-thoughts', $start, $end, $total )->parse() .
				'</span>
			</div>';
		}

		/**
		 * Build next/prev navigation
		 */
		$numofpages = $total / $per_page;
		$thisTitle = $this->getPageTitle();

		if ( $numofpages > 1 ) {
			$output .= '<div class="page-nav">';
			if ( $page > 1 ) {
				$output .= Linker::link(
					$thisTitle,
					$this->msg( 'userstatus-prev' )->plain(),
					array(),
					array(
						'user' => $user_name,
						'page' => ( $page - 1 )
					)
				) . $this->msg( 'word-separator' )->plain();
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
					$output .= ( $i . ' ' );
				} else {
					$output .= Linker::link(
						$thisTitle,
						$i,
						array(),
						array(
							'user' => $user_name,
							'page' => $i
						)
					) . $this->msg( 'word-separator' )->plain();
				}
			}

			if ( ( $total - ( $per_page * $page ) ) > 0 ) {
				$output .= $this->msg( 'word-separator' )->plain() .
					Linker::link(
						$thisTitle,
						$this->msg( 'userstatus-next' )->plain(),
						array(),
						array(
							'user' => $user_name,
							'page' => ( $page + 1 )
						)
					);
			}
			$output .= '</div><p>';
		}

		// Add CSS & JS
		$out->addModules( 'ext.userStatus' );

		$output .= '<div class="user-status-container">';
		$thought_link = SpecialPage::getTitleFor( 'ViewThought' );
		if ( $messages ) {
			foreach ( $messages as $message ) {
				$user = Title::makeTitle( NS_USER, $message['user_name'] );
				$avatar = new wAvatar( $message['user_id'], 'm' );

				$network_link = '<a href="' . SportsTeams::getNetworkURL( $message['sport_id'], $message['team_id'] ) . '">' .
					$this->msg(
						'userstatus-all-team-updates',
						SportsTeams::getNetworkName( $message['sport_id'], $message['team_id'] )
					)->text() .
				'</a>';

				$delete_link = '';
				if (
					$currentUser->getName() == $message['user_name'] ||
					$currentUser->isAllowed( 'delete-status-updates' )
				)
				{
					$delete_link = "<span class=\"user-status-delete-link\">
						<a href=\"javascript:void(0);\" data-message-id=\"{$message['id']}\">" .
						$this->msg( 'userstatus-delete-thought-text' )->text() ."</a>
					</span>";
				}

				// If there are links and their texts are tl;dr, cut 'em a bit
				$message_text = preg_replace_callback(
					'/(<a[^>]*>)(.*?)(<\/a>)/i',
					array( 'UserStatus', 'cutLinkText' ),
					$message['text']
				);

				$vote_count = $this->msg( 'userstatus-num-agree' )->numParams( $message['plus_count'] )->parse();

				$vote_link = '';
				// Only registered users who aren't the author of the particular
				// thought can vote for it
				if ( $currentUser->isLoggedIn() && $currentUser->getName() != $message['user_name'] ) {
					if ( !$message['voted'] ) {
						$vote_link = "<a class=\"vote-status-link\" href=\"javascript:void(0);\" data-message-id=\"{$message['id']}\">[" .
							$this->msg( 'userstatus-agree' )->text() . ']</a>';
					} else {
						$vote_link = $vote_count;
					}
				}

				$view_thought_link = Linker::link(
					$thought_link,
					$this->msg(
						'brackets',
						$this->msg( 'userstatus-see-who-agrees' )->plain()
					)->plain(),
					array(),
					array( 'id' => $message['id'] )
				);

				$output .= '<div class="user-status-row">

					<div class="user-status-logo">

						<a href="' . SportsTeams::getNetworkURL( $message['sport_id'], $message['team_id'] ) . '">' .
							SportsTeams::getLogo( $message['sport_id'], $message['team_id'], 'm' ) .
						"</a>

					</div>

					<div class=\"user-status-message\">

						{$message_text}

						<div class=\"user-status-date\">" .
							$this->msg( 'userstatus-ago', UserStatus::getTimeAgo( $message['timestamp'] ) )->text() .
							"<span class=\"user-status-vote\" id=\"user-status-vote-{$message['id']}\">
								{$vote_link}
							</span>
							{$view_thought_link}
							<span class=\"user-status-links\">
								{$delete_link}
							</span>
						</div>

					</div>

					<div class=\"cleared\"></div>

				</div>";
			}
		} else {
			$output .= '<p>' . $this->msg( 'userstatus-no-updates' )->text() . '</p>';
		}

		$output .= '</div>';

		$out->addHTML( $output );
	}
}