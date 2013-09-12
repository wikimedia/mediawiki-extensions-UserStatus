<?php
/**
 * UserStatus API module
 *
 * @file
 * @ingroup API
 * @date 10 July 2013
 * @see http://www.mediawiki.org/wiki/API:Extensions#ApiSampleApiExtension.php
 */
class ApiUserStatus extends ApiBase {

	/**
	 * @var UserStatus
	 */
	private $userStatus;

	public function execute() {
		// Set the private variable which is used all over the place in the
		// *Status() functions
		$this->userStatus = new UserStatus();

		$user = $this->getUser();

		// Don't do anything if the user is blocked or the DB is read-only
		if ( $user->isBlocked() || wfReadOnly() ) {
			return '';
		}

		// Get the request parameters
		$params = $this->extractRequestParams();

		// Sanity checks for these variables and their types is performed below,
		// in the switch() loop
		wfSuppressWarnings();
		$sportId = $params['sportId'];
		$teamId = $params['teamId'];
		$text = $params['text'];
		$count = $params['count'];
		$userId = $params['userId'];
		$num = $params['num'];
		$us_id = $params['us_id'];
		$vote = $params['vote'];
		wfRestoreWarnings();

		// Hmm, what do we want to do?
		switch ( $params['what'] ) {
			case 'addstatus': // add a status to user profile
				if (
					$sportId === null || !is_numeric( $sportId ) ||
					$teamId === null || !is_numeric( $teamId ) ||
					!isset( $text ) || count( $params ) < 3
				)
				{
					$this->dieUsage( 'One or more of the required three params is missing', 'zomgamissingparam' );
				}
				$output = $this->addStatus( $sportId, $teamId, $text );
				break;
			case 'addnetworkstatus': // add a status to a network page
				if (
					$sportId === null || !is_numeric( $sportId ) ||
					$teamId === null || !is_numeric( $teamId ) ||
					!isset( $text ) || !is_numeric( $count ) ||
					count( $params ) < 4
				)
				{
					$this->dieUsage( 'One or more of the required four params is missing', 'zomgamissingparam2' );
				}
				$output = $this->addNetworkStatus( $sportId, $teamId, $text, $count );
				break;
			case 'getstatus':
				if (
					$userId === null || !is_numeric( $userId ) ||
					$num === null || !is_numeric( $num ) ||
					count( $params ) < 2
				)
				{
					$this->dieUsage( 'One or more of the required two params is missing', 'zomgamissingparam3' );
				}
				$output = $this->getStatus( $userId, $num );
				break;
			case 'votestatus':
				if (
					$us_id === null || !is_numeric( $us_id ) ||
					$vote === null || count( $params ) < 2
				)
				{
					$this->dieUsage( 'One or more of the required two params is missing', 'zomgamissingparam4' );
				}
				$output = $this->voteStatus( $us_id, $vote );
				break;
			case 'deletestatus':
				if ( $us_id === null || !is_numeric( $us_id ) ) {
					$this->dieUsage( 'The only required parameter is missing', 'zomgamissingparam5' );
				}
				$output = $this->deleteStatus( $us_id );
				break;
			case 'updatestatus':
				if (
					$user_id === null || !is_numeric( $user_id ) ||
					$user_name === null || $text === null || $date === null ||
					$next_row === null || count( $params ) < 5
				)
				{
					$this->dieUsage( 'One or more of the required five params is missing', 'zomgamissingparam6' );
				}
				$output = $this->updateStatus( $user_id, $user_name, $text, $date, $next_row );
				break;
			default:
				// Let's see who gets the reference...
				$this->dieUsage( 'Oh fuck off already, will ya?', 'gordon' );
		}

		// Top level
		$this->getResult()->addValue( null, $this->getModuleName(),
			array( 'result' => $output )
		);

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

	/**
	 * Unused...I think this function's original incarnation was used in
	 * ancient history.
	 */
	function getStatus( $userId, $num ) {
		global $wgExtensionAssetsPath;

		$update = $this->userStatus->getStatusMessages( $userId, 0, 0, 1, $num );
		$update = $update[0];

		return SportsTeams::getLogo( $update['sport_id'], $update['team_id'], 's' ) .
		"<img src=\"{$wgExtensionAssetsPath}/UserStatus/quoteIcon.png\" border=\"0\" style=\"margin-left:5px;\" alt=\"\" />
		{$update['text']}
		<img src=\"{$wgExtensionAssetsPath}/UserStatus/endQuoteIcon.png\" border=\"0\" alt=\"\" />
		<span class=\"user-status-date\">" . 
			wfMessage( 'userstatus-ago', UserStatus::getTimeAgo( $update['timestamp'] ) )->text() .
		'</span>';
	}

	function voteStatus( $us_id, $vote ) {
		$update = $this->userStatus->addStatusVote( $us_id, $vote );
		$votes = $this->userStatus->getStatusVotes( $us_id );

		$output = wfMessage( 'userstatus-num-agree', $votes['plus'] )->parse();
		return $output;
	}

	function deleteStatus( $us_id ) {
		$user = $this->getUser();
		if(
			$this->userStatus->doesUserOwnStatusMessage( $user->getId(), $us_id ) ||
			$user->isAllowed( 'delete-status-updates' )
		)
		{
			$this->userStatus->deleteStatus( $us_id );
		}

		return 'ok';
	}

	function updateStatus( $user_id, $user_name, $text, $date, $next_row ) {
		$user = User::newFromId( $user_id );
		// @todo FIXME: IS USER_ID (+user_name) REALLY NEEDED? CAN WE JUST UTILIZE $this->getUser() HERE?

		// Don't do anything if the user is blocked
		if ( $user->isBlocked() ) {
			return '';
		}

		// Get a database handler
		$dbw = wfGetDB( DB_MASTER );

		// Write new data to user_status
		$dbw->insert(
			'user_status',
			array(
				'us_user_id' => $user_id,
				'us_user_name' => $user_name,
				'us_text' => $text,
				'us_date' => $date,
			),
			__METHOD__
		);

		// Grab all rows from user_status
		$res = $dbw->select(
			'user_status',
			array(
				'us_user_id', 'us_user_name', 'us_text',
				'UNIX_TIMESTAMP(us_date) AS unix_time'
			),
			array( 'us_id' => intval( $next_row ) ),
			__METHOD__
		);

		$x = 1;

		foreach ( $res as $row ) {
			$db_user_id = $row->us_user_id;
			$db_user_name = $row->us_user_name;
			$db_status_text = $row->us_text;
			$user_status_date = $row->unix_time;
			$avatar = new wAvatar( $db_user_id, 'ml' );
			$userTitle = Title::makeTitle( NS_USER, $db_user_name );

			$output .= "<div class=\"user-status-row\">
			{$avatar->getAvatarURL()}
			<a href=\"{$userTitle->escapeFullURL()}\"><b>{$db_user_name}</b></a> {$db_status_text}
			<span class=\"user-status-date\">" .
				wfMessage( 'userstatus-just-added' )->text() .
			'</span>
		</div>';

			$x++;
		}

		return $output;
	}

	/**
	 * @return String: human-readable module description
	 */
	public function getDescription() {
		return 'API for handling social status messages';
	}

	/**
	 * @return Array
	 */
	public function getAllowedParams() {
		return array(
			'what' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'sportId' => array(
				ApiBase::PARAM_TYPE => 'integer'
			),
			'teamId' => array(
				ApiBase::PARAM_TYPE => 'integer'
			),
			'text' => array(
				ApiBase::PARAM_TYPE => 'string'
			),
			'count' => array(
				ApiBase::PARAM_TYPE => 'integer'
			),
			'userId' => array(
				ApiBase::PARAM_TYPE => 'integer'
			),
			'num' => array(
				ApiBase::PARAM_TYPE => 'integer'
			),
			'us_id' => array(
				ApiBase::PARAM_TYPE => 'integer'
			),
			'vote' => array(
				ApiBase::PARAM_TYPE => 'integer'
			),
		);
	}

	// Describe the parameter
	public function getParamDescription() {
		return array_merge( parent::getParamDescription(), array(
			'what' => 'What to do? Verb + subject, i.e. addnetworkstatus to add a status to a network page, deletestatus to delete a status message, etc.',
			'sportId' => 'ID of the sport',
			'teamId' => 'ID of the sport team',
			'text' => 'Status update text',
			'count' => 'Display this many status updates in the returned feed',
			'userId' => 'User ID number',
			'num' => 'No idea, really; DOCUMENT ME!',
			'us_id' => 'Status message ID',
			'vote' => '1 to "upvote" a status message, -1 to "downvote" instead',
		) );
	}

	// Get examples
	public function getExamples() {
		return array(
			'api.php?action=userstatus&what=addstatus&sportId=2&teamId=43&text=My team rocks!' => 'Adds a status message ("My team rocks!") under the team #43, sport #2',
			'api.php?action=userstatus&what=addnetworkstatus&sportId=3&teamId=10&text=My team rocks!&count=20' => 'Adds a status message (with the text "My team rocks!") to the network page of team #10 under sport #3',
			'api.php?action=userstatus&what=deletestatus&us_id=35' => 'Deletes the status message with the ID #35',
			'api.php?action=userstatus&what=votestatus&us_id=47&vote=1' => 'Gives an upvote ("thumbs up") to the status message which has the ID #47',
			'api.php?action=userstatus&what=updatestatus&user_id=367&user_name=Foo bar&text=We are the champions!&date=FIX_ME&next_row=FIX_ME' => 'Updates a status message',
		);
	}
}