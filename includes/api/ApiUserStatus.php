<?php
/**
 * UserStatus API module
 *
 * @file
 * @ingroup API
 * @see https://www.mediawiki.org/wiki/API:Extensions#ApiSampleApiExtension.php
 */
class ApiUserStatus extends ApiBase {

	/** @var UserStatus */
	private $userStatus;

	public function execute() {
		$user = $this->getUser();

		// Set the private variable which is used all over the place in the
		// *Status() functions
		$this->userStatus = new UserStatus( $user );

		// Don't do anything if the user is blocked
		if ( $user->getBlock() ) {
			return '';
		}

		// Get the request parameters
		$params = $this->extractRequestParams();

		// Sanity checks for these variables and their types is performed below,
		// in the switch() loop
		$sportId = $params['sportId'] ?? null;
		$teamId = $params['teamId'] ?? null;
		$text = $params['text'] ?? null;
		$count = $params['count'] ?? null;
		$us_id = $params['us_id'] ?? null;
		$vote = $params['vote'] ?? null;
		$date = $params['date'] ?? null;

		// Hmm, what do we want to do?
		switch ( $params['what'] ) {
			// add a status to user profile
			case 'addstatus':
				if (
					$sportId === null || !is_numeric( $sportId ) ||
					$teamId === null || !is_numeric( $teamId ) ||
					!isset( $text ) || count( $params ) < 3
				) {
					$this->dieWithError(
						new RawMessage( 'One or more of the required three params is missing' ),
						'zomgamissingparam'
					);
				}
				$output = $this->addStatus( $sportId, $teamId, $text );
				break;
			// add a status to a network page
			case 'addnetworkstatus':
				if (
					$sportId === null || !is_numeric( $sportId ) ||
					$teamId === null || !is_numeric( $teamId ) ||
					!isset( $text ) || !is_numeric( $count ) ||
					count( $params ) < 4
				) {
					$this->dieWithError(
						new RawMessage( 'One or more of the required four params is missing' ),
						'zomgamissingparam2'
					);
				}
				$output = $this->addNetworkStatus( $sportId, $teamId, $text, $count );
				break;
			case 'votestatus':
				if (
					$us_id === null || !is_numeric( $us_id ) ||
					$vote === null || count( $params ) < 2
				) {
					$this->dieWithError(
						new RawMessage( 'One or more of the required two params is missing' ),
						'zomgamissingparam4'
					);
				}
				$output = $this->voteStatus( $us_id, $vote );
				break;
			case 'deletestatus':
				if ( $us_id === null || !is_numeric( $us_id ) ) {
					$this->dieWithError(
						new RawMessage( 'The only required parameter is missing' ),
						'zomgamissingparam5'
					);
				}
				$output = $this->deleteStatus( $us_id );
				break;
			case 'updatestatus':
				if ( $text === null || $date === null || $us_id === null || count( $params ) < 3 ) {
					$this->dieWithError(
						new RawMessage( 'One or more of the required three params is missing' ),
						'zomgamissingparam6'
					);
				}
				$output = $this->updateStatus( $text, $date, $us_id );
				break;
			default:
				// Let's see who gets the reference...
				$this->dieWithError(
					new RawMessage( 'Oh fuck off already, will ya?' ),
					'gordon'
				);
		}

		// Top level
		$this->getResult()->addValue( null, $this->getModuleName(), [ 'result' => $output ] );

		return true;
	}

	function addStatus( $sportId, $teamId, $text ) {
		$text = urldecode( $text );
		$m = $this->userStatus->addStatus( $sportId, $teamId, $text );

		$output = '<div class="status-message">' .
			SportsTeams::getLogo( $sportId, $teamId, 's' ) .
			$this->userStatus->formatMessage( $text ) .
	'</div>
	<div class="user-status-profile-vote">
		<div class="user-status-date">' .
			wfMessage( 'userstatus-just-added' )->text() .
		'</div>
	</div>';

		return $output;
	}

	function addNetworkStatus( $sportId, $teamId, $text, $count ) {
		$m = $this->userStatus->addStatus( $sportId, $teamId, urldecode( $text ) );

		return $this->userStatus->displayStatusMessages( 0, $sportId, $teamId, $count, 1 );
	}

	function voteStatus( $us_id, $vote ) {
		$update = $this->userStatus->addStatusVote( $us_id, $vote );
		$votes = $this->userStatus->getStatusVotes( $us_id );

		$output = wfMessage( 'userstatus-num-agree', $votes['plus'] )->parse();
		return $output;
	}

	function deleteStatus( $us_id ) {
		$user = $this->getUser();
		if (
			$this->userStatus->doesUserOwnStatusMessage( $user->getActorId(), $us_id ) ||
			$user->isAllowed( 'delete-status-updates' )
		) {
			$this->userStatus->deleteStatus( $us_id );
		}

		return 'ok';
	}

	function updateStatus( $text, $date, $next_row ) {
		$user = $this->getUser();

		// Get a database handler
		$dbw = wfGetDB( DB_MASTER );

		// Write new data to user_status
		$dbw->insert(
			'user_status',
			[
				'us_actor' => $user->getActorId(),
				'us_text' => $text,
				'us_date' => $date,
			],
			__METHOD__
		);

		// Grab all rows from user_status
		$res = $dbw->select(
			'user_status',
			[ 'us_actor', 'us_text', 'us_date' ],
			[ 'us_id' => intval( $next_row ) ],
			__METHOD__
		);

		$x = 1;

		$output = '';
		foreach ( $res as $row ) {
			$userObj = User::newFromActorId( $row->us_actor );
			$db_user_name = $userObj->getName();
			$db_status_text = $row->us_text;
			$user_status_date = wfTimestamp( TS_UNIX, $row->us_date );
			$avatar = new wAvatar( $userObj->getId(), 'ml' );
			$userTitle = Title::makeTitle( NS_USER, $db_user_name );

			$url = htmlspecialchars( $userTitle->getFullURL() );
			$output .= "<div class=\"user-status-row\">
			{$avatar->getAvatarURL()}
			<a href=\"{$url}\"><b>{$db_user_name}</b></a> {$db_status_text}
			<span class=\"user-status-date\">" .
				wfMessage( 'userstatus-just-added' )->text() .
			'</span>
		</div>';

			$x++;
		}

		return $output;
	}

	/**
	 * @return array
	 */
	public function getAllowedParams() {
		return [
			'what' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'sportId' => [
				ApiBase::PARAM_TYPE => 'integer'
			],
			'teamId' => [
				ApiBase::PARAM_TYPE => 'integer'
			],
			'text' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'count' => [
				ApiBase::PARAM_TYPE => 'integer'
			],
			'us_id' => [
				ApiBase::PARAM_TYPE => 'integer'
			],
			'vote' => [
				ApiBase::PARAM_TYPE => 'integer'
			],
		];
	}

	public function needsToken() {
		return 'csrf';
	}

	public function isWriteMode() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getExamples() {
		return [
			'api.php?action=userstatus&what=addstatus&sportId=2&teamId=43&text=My team rocks!' => 'Adds a status message ("My team rocks!") under the team #43, sport #2',
			'api.php?action=userstatus&what=addnetworkstatus&sportId=3&teamId=10&text=My team rocks!&count=20' => 'Adds a status message (with the text "My team rocks!") to the network page of team #10 under sport #3',
			'api.php?action=userstatus&what=deletestatus&us_id=35' => 'Deletes the status message with the ID #35',
			'api.php?action=userstatus&what=votestatus&us_id=47&vote=1' => 'Gives an upvote ("thumbs up") to the status message which has the ID #47',
			'api.php?action=userstatus&what=updatestatus&user_name=Foo bar&text=We are the champions!&date=FIX_ME&next_row=FIX_ME' => 'Updates a status message',
		];
	}
}
