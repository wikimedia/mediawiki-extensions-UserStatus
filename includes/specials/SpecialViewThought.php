<?php
/**
 * A special page for viewing an individual status update.
 *
 * @file
 * @ingroup Extensions
 */
class SpecialViewThought extends UnlistedSpecialPage {

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'ViewThought' );
	}

	/**
	 * Show the special page
	 *
	 * @param string|int|null $par Parameter passed to the special page, if any
	 * @return bool|void
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();

		$this->setHeaders();

		$messages_show = 25;
		$output = '';
		$us_id = $request->getInt( 'id', $par );
		$page = $request->getInt( 'page', 1 );

		// No ID? Show an error message then.
		// @phan-suppress-next-line PhanRedundantCondition
		if ( !$us_id || !is_numeric( $us_id ) ) {
			$out->setPageTitle( $this->msg( 'userstatus-woops' )->escaped() );
			$out->addHTML( $this->msg( 'userstatus-invalid-link' )->escaped() );
			return false;
		}

		/**
		 * Config for the page
		 */
		$per_page = $messages_show;

		$s = new UserStatus( $this->getUser() );
		$message = $s->getStatusMessage( $us_id );

		// Before doing any further processing, check if we got an empty array.
		// It occurs when someone is trying to access this special page with
		// the ID of a deleted status update.
		if ( empty( $message ) ) {
			$out->setPageTitle( $this->msg( 'userstatus-woops' )->escaped() );

			$output = '<div class="relationship-request-message">' .
				$this->msg( 'userstatus-invalid-link' )->escaped() . '</div>';
			$output .= '<div class="relationship-request-buttons">';
			$output .= '<input type="button" class="site-button" value="' .
				$this->msg( 'mainpage' )->escaped() .
				"\" onclick=\"window.location='" .
				htmlspecialchars( Title::newMainPage()->getFullURL() ) . "'\"/>";
			$output .= '</div>';

			$out->addHTML( $output );
			return;
		}

		$user = User::newFromActorId( $message['actor'] );
		$user_name = $user->getName();

		// Different page title, depending on whose status updates we're
		// viewing
		if ( !( $this->getUser()->getName() == $user_name ) ) {
			$out->setPageTitle( $this->msg( 'userstatus-user-thoughts', $user_name )->text() );
		} else {
			$out->setPageTitle( $this->msg( 'userstatus-your-thoughts' )->text() );
		}

		// Add CSS
		$out->addModules( 'ext.userStatus.viewThought' );

		$templateParser = new TemplateParser( __DIR__ . '/../templates' );

		// Start building the HTML
		$output .= "<div class=\"view-thought-links\">
			<a href=\"{$user->getUserPage()->getFullURL()}\">" .
				$this->msg( 'userstatus-user-profile', $user_name )->escaped() .
			'</a>
		</div>';

		$output .= '<div class="user-status-container">';
		$output .= $templateParser->processTemplate(
			'status-update',
			[
				'containerClass' => 'user-status-row',
				'showUserAvatar' => false,
				'networkURL' => SpecialPage::getTitleFor( 'FanHome' )->getFullURL( [
					'sport_id' => $message['sport_id'],
					'team_id' => $message['team_id']
				] ),
				'networkLogo' => SportsTeams::getLogo( $message['sport_id'], $message['team_id'], 'm' ),
				'messageText' => $message['text'],
				'messageId' => $message['id'],
				'postedAgo' => $this->msg( 'userstatus-ago', UserStatus::getTimeAgo( $message['timestamp'] ) )->text(),
				'showActionLinks' => false
			]
		);
		$output .= '</div>';

		$output .= '<div class="who-agrees">';
		$output .= '<h1>' . $this->msg( 'userstatus-who-agrees' )->escaped() . '</h1>';
		$voters = $s->getStatusVoters( $us_id );
		// Get the people who agree with this status update, if any
		if ( $voters ) {
			foreach ( $voters as $voter ) {
				$votingUser = User::newFromActorId( $voter['actor'] );
				$avatar = new wAvatar( $votingUser->getId(), 'm' );
				$safeVotingUserName = htmlspecialchars( $votingUser->getName() );

				$output .= "<div class=\"who-agrees-row\">
					<a href=\"{$votingUser->getUserPage()->getFullURL()}\">{$avatar->getAvatarURL()}</a>
					<a href=\"{$votingUser->getUserPage()->getFullURL()}\">{$safeVotingUserName}</a>
				</div>";
			}
		} else {
			$output .= '<p>' . $this->msg( 'userstatus-nobody-agrees' )->escaped() . '</p>';
		}

		$output .= '</div>';

		$out->addHTML( $output );
	}

}
