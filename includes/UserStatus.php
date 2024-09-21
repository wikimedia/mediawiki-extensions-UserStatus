<?php

use MediaWiki\MediaWikiServices;

/**
 * Class for managing status updates and status update votes ("X people agree")
 *
 * @file
 * @ingroup Extensions
 */
class UserStatus {
	/**
	 * @var User Current user (i.e. the user who is viewing the page)
	 */
	public $user;

	/**
	 * Constructor
	 *
	 * @param User $user Current user, i.e. the user who is viewing the page (for
	 * social profile pages, obviously "current user who is viewing the page" and
	 * "the user whose page is being viewed" can be and likely _will_ be different!)
	 */
	public function __construct( $user ) {
		$this->user = $user;
	}

	/**
	 * Add a status update (provided the user isn't blocked) and update the
	 * relevant social statistics.
	 *
	 * @param int $sport_id Sport ID with which the status update is associated
	 * @param int $team_id Team ID with which the status update is associated
	 * @param string $text User-supplied status update text
	 * @return string|int Empty string if the user is blocked, otherwise ID
	 * of the newly inserted status update
	 */
	public function addStatus( $sport_id, $team_id, $text ) {
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		if ( $this->user->getBlock() ) {
			return '';
		}

		$dbw->insert(
			'user_status',
			[
				'us_actor' => $this->user->getActorId(),
				'us_sport_id' => $sport_id,
				'us_team_id' => $team_id,
				'us_text' => $text,
				'us_date' => $dbw->timestamp( date( 'Y-m-d H:i:s' ) ),
			],
			__METHOD__
		);
		$us_id = $dbw->insertId();

		$stats = new UserStatsTrack( $this->user->getId(), $this->user->getName() );
		$stats->incStatField( 'user_status_count' );

		return $us_id;
	}

	/**
	 * Add a vote for the given status update.
	 * Only registered users who haven't voted before can vote.
	 *
	 * @param int $us_id Status update ID number
	 * @param int $vote -1 or 1
	 * @return void|int Vote ID on success, void if they already voted
	 */
	public function addStatusVote( $us_id, $vote ) {
		// Only registered users may vote...
		if ( $this->user->isRegistered() ) {
			// ...and only if they haven't already voted
			if ( $this->alreadyVotedStatusMessage( $this->user->getActorId(), $us_id ) ) {
				return;
			}

			$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

			$dbw->insert(
				'user_status_vote',
				[
					'sv_actor' => $this->user->getActorId(),
					'sv_us_id' => $us_id,
					'sv_vote_score' => $vote,
					'sv_date' => $dbw->timestamp( date( 'Y-m-d H:i:s' ) ),
				],
				__METHOD__
			);
			$sv_id = $dbw->insertId();

			$this->incStatusVoteCount( $us_id, $vote );

			return $sv_id;
		}
	}

	/**
	 * Increase the vote count for a given status message.
	 *
	 * @param int $us_id Status message ID
	 * @param int $vote 1 to update positive (plus) votes, -1 for negative
	 */
	public function incStatusVoteCount( $us_id, $vote ) {
		if ( $vote == 1 ) {
			$field = 'us_vote_plus';
		} else {
			$field = 'us_vote_minus';
		}
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->update(
			'user_status',
			[ "{$field}={$field}+1" ],
			[ 'us_id' => $us_id ],
			__METHOD__
		);
	}

	/**
	 * Check if the given user has already voted on the given status message.
	 *
	 * @param int $actor_id User actor ID number
	 * @param int $us_id Status message ID number
	 * @return bool True if the user has already voted, otherwise false
	 */
	public function alreadyVotedStatusMessage( $actor_id, $us_id ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$s = $dbr->selectRow(
			'user_status_vote',
			[ 'sv_actor' ],
			[ 'sv_us_id' => $us_id, 'sv_actor' => $actor_id ],
			__METHOD__
		);

		if ( $s !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the given user is the author of the given status message.
	 *
	 * @param int $actor_id User actor ID number
	 * @param int $us_id Status message ID number
	 * @return bool True if the user owns the status message, else false
	 */
	public function doesUserOwnStatusMessage( $actor_id, $us_id ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$s = $dbr->selectRow(
			'user_status',
			[ 'us_actor' ],
			[ 'us_id' => $us_id ],
			__METHOD__
		);

		if ( $s !== false ) {
			if ( $actor_id == $s->us_actor ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Delete a status message via its ID.
	 *
	 * @param int $us_id ID number of the status message to delete
	 */
	public function deleteStatus( $us_id ) {
		if ( $us_id ) {
			$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
			$s = $dbw->selectRow(
				'user_status',
				[ 'us_actor', 'us_sport_id', 'us_team_id' ],
				[ 'us_id' => $us_id ],
				__METHOD__
			);
			if ( $s !== false ) {
				$dbw->delete(
					'user_status',
					[ 'us_id' => $us_id ],
					__METHOD__
				);

				// Why not $this->user? Because it is possible for an admin or other privileged
				// user to be deleting someone else's thoughts than only their own, and in
				// that case, we do *not* want to be decrementing the _admin's_ thought count,
				// but rather the target user's.
				$user = User::newFromActorId( $s->us_actor );
				$stats = new UserStatsTrack( $user->getId(), $user->getName() );
				$stats->decStatField( 'user_status_count' );
			}
		}
	}

	/**
	 * Format a message by passing it to the Parser.
	 *
	 * @param string $message Message (wikitext) to parse
	 * @return string Parsed status message
	 */
	static function formatMessage( $message ) {
		global $wgOut;
		$messageText = $wgOut->parseAsContent( trim( $message ), false );
		$messageText = str_replace( [ '<p>', '</p>' ], '', $messageText );
		return $messageText;
	}

	/**
	 * Get information about an individual status message via its ID number.
	 *
	 * @param int $us_id Status update ID number
	 * @return array Array containing info, such as the text and ID, about the
	 *                status message
	 */
	public function getStatusMessage( $us_id ) {
		// Paranoia, because nobody likes an SQL injection point.
		$us_id = (int)$us_id;

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		$sql = "SELECT us_id, us_actor, us_text,
			us_sport_id, us_team_id, us_vote_plus, us_vote_minus,
			us_date,
			(SELECT COUNT(*) FROM {$dbr->tableName( 'user_status_vote' )}
				WHERE sv_us_id = us_id
				AND sv_actor =" . $this->user->getActorId() . ") AS alreadyvoted
			FROM {$dbr->tableName( 'user_status' )}
			WHERE us_id={$us_id} LIMIT 1";

		$res = $dbr->query( $sql, __METHOD__ );

		$messages = [];

		foreach ( $res as $row ) {
			$messages[] = [
				'id' => $row->us_id,
				'timestamp' => wfTimestamp( TS_UNIX, $row->us_date ),
				'actor' => $row->us_actor,
				'sport_id' => $row->us_sport_id,
				'team_id' => $row->us_team_id,
				'plus_count' => $row->us_vote_plus,
				'minus_count' => $row->us_vote_minus,
				'text' => $this->formatMessage( $row->us_text ),
				'voted' => $row->alreadyvoted
			];
		}

		return ( isset( $messages[0] ) ? $messages[0] : [] );
	}

	/**
	 * Get the given amount of the given user's status messages; used by
	 * displayStatusMessages().
	 *
	 * @param int $actor_id User actor ID whose status updates we want to display
	 * @param int $sport_id Sport ID for which we want to display updates
	 * @param int $team_id Sports team ID
	 * @param int $limit Display this many messages
	 * @param int $page Page we're on; used for pagination
	 * @return array Array containing information such as the timestamp,
	 *                status update ID number and more about each update
	 */
	public function getStatusMessages( $actor_id = 0, $sport_id = 0, $team_id = 0, $limit = 10, $page = 0 ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$user_sql = $sport_sql = $where = '';

		$offset = 0;
		if ( $limit > 0 && $page ) {
			$offset = $page * $limit - ( $limit );
		}

		if ( $actor_id > 0 ) {
			$user_sql .= " us_actor = {$actor_id} ";
		}

		if ( $sport_id > 0 && $team_id == 0 ) {
			$sport_sql .= " ( ( us_sport_id = {$sport_id} AND us_team_id = 0 ) OR us_team_id IN
				(SELECT team_id FROM {$dbr->tableName( 'sport_team' )} WHERE team_sport_id = {$sport_id} ) ) ";
		}

		if ( $team_id > 0 ) {
			$sport_sql .= " us_team_id = {$team_id} ";
		}

		if ( $user_sql && $sport_sql ) {
			$user_sql .= ' AND ';
		}
		if ( $user_sql || $sport_sql ) {
			$where = "WHERE {$user_sql} {$sport_sql}";
		}

		$sql = "SELECT us_id, us_actor, us_text,
			us_sport_id, us_team_id, us_vote_plus, us_vote_minus,
			us_date,
			(SELECT COUNT(*) FROM {$dbr->tableName( 'user_status_vote' )}
				WHERE sv_us_id = us_id
				AND sv_actor = " . $this->user->getActorId() . ") AS alreadyvoted
			FROM {$dbr->tableName( 'user_status' )}
			{$where}
			ORDER BY us_id DESC";

		$res = $dbr->query( $dbr->limitResult( $sql, $limit, $offset ), __METHOD__ );

		$messages = [];

		foreach ( $res as $row ) {
			$messages[] = [
				'id' => $row->us_id,
				'timestamp' => wfTimestamp( TS_UNIX, $row->us_date ),
				'actor' => $row->us_actor,
				'sport_id' => $row->us_sport_id,
				'team_id' => $row->us_team_id,
				'plus_count' => $row->us_vote_plus,
				'minus_count' => $row->us_vote_minus,
				'text' => $this->formatMessage( $row->us_text ),
				'voted' => $row->alreadyvoted
			];
		}

		return $messages;
	}

	/**
	 * Display the given amount of the given user's status messages.
	 *
	 * @param int $actor_id User actor ID whose status updates we want to display
	 * @param int $sport_id Sport ID for which we want to display updates
	 * @param int $team_id Sports team ID
	 * @param int $count Display this many messages
	 * @param int $page Page we're on; used for pagination
	 * @return string HTML
	 */
	public function displayStatusMessages( $actor_id, $sport_id = 0, $team_id = 0, $count = 10, $page = 0 ) {
		$output = '';

		$messages = $this->getStatusMessages(
			$actor_id,
			$sport_id,
			$team_id,
			$count,
			$page
		);
		$messages_count = count( $messages );
		$x = 1;

		$thought_link = SpecialPage::getTitleFor( 'ViewThought' );

		if ( $messages ) {
			$statusPage = SpecialPage::getTitleFor( 'UserStatus' );
			$templateParser = new TemplateParser( __DIR__ . '/templates' );

			foreach ( $messages as $message ) {
				$user = User::newFromActorId( $message['actor'] );
				$userName = $user->getName();
				$avatar = new wAvatar( $user->getId(), 'm' );

				$messages_link = '<a href="' .
					self::getUserUpdatesURL( $userName ) . '">' .
					wfMessage( 'userstatus-view-all-updates', $userName )->escaped() .
				'</a>';
				$delete_link = '';

				$vote_count = wfMessage( 'userstatus-num-agree', $message['plus_count'] )->parse();

				if (
					$this->user->getActorId() == $message['actor'] ||
					$this->user->isAllowed( 'delete-status-updates' )
				) {
					$deleteURL = htmlspecialchars(
						$statusPage->getFullURL( [
							'action' => 'delete',
							'us_id' => $message['id']
						] ),
						ENT_QUOTES
					);
					$delete_link = "<span class=\"user-status-delete-link\">
						<a href=\"{$deleteURL}\" data-message-id=\"{$message['id']}\">" .
						wfMessage( 'userstatus-delete-thought-text' )->escaped() . '</a>
					</span>';
				}

				$vote_link = '';
				if ( $this->user->isRegistered() && $this->user->getActorId() != $message['actor'] ) {
					if ( !$message['voted'] ) {
						$voteURL = htmlspecialchars(
							$statusPage->getFullURL( [
								'action' => 'vote',
								'us_id' => $message['id']
							] ),
							ENT_QUOTES
						);
						$vote_link = "<a class=\"vote-status-link\" href=\"{$voteURL}\" data-message-id=\"{$message['id']}\">[" .
							wfMessage( 'userstatus-agree' )->escaped() . ']</a>';
					} else {
						$vote_link = $vote_count;
					}
				}

				$view_thought_link = '<a href="' . $thought_link->getFullURL( 'id=' . $message['id'] ) .
					'">[' . wfMessage( 'userstatus-see-who-agrees' )->escaped() . ']</a>';

				$message_text = preg_replace_callback(
					'/(<a[^>]*>)(.*?)(<\/a>)/i',
					[ 'UserStatus', 'cutLinkText' ],
					$message['text']
				);

				$containerClass = '';
				if ( $x == 1 ) {
					$containerClass = 'user-status-row-top';
				} elseif ( $x < $messages_count ) {
					$containerClass = 'user-status-row';
				} else {
					$containerClass = 'user-status-row-bottom';
				}

				$output .= $templateParser->processTemplate(
					'status-update',
					[
						'containerClass' => $containerClass,
						'userPageURL' => $user->getUserPage()->getFullURL(),
						'showUserAvatar' => true,
						'avatarElement' => $avatar->getAvatarURL(),
						'userName' => $userName,
						'messageId' => $message['id'],
						'messageText' => $message_text,
						'postedAgo' => wfMessage( 'userstatus-ago', self::getTimeAgo( $message['timestamp'] ) )->text(),
						'showActionLinks' => true,
						'voteLink' => $vote_link,
						'viewThoughtLink' => $view_thought_link,
						'deleteLink' => $delete_link
					]
				);

				$x++;
			}
		} else {
			$output .= '<p>' . wfMessage( 'userstatus-no-new-thoughts' )->escaped() . '</p>';
		}

		return $output;
	}

	/**
	 * Get the amount of plus and minus votes a status update has, if any.
	 *
	 * @param int $us_id Status update ID number
	 * @return bool|array False if it doesn't have any votes yet, otherwise
	 *                an array containing the plus and minus votes
	 */
	public function getStatusVotes( $us_id ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$s = $dbr->selectRow(
			'user_status',
			[ 'us_vote_plus', 'us_vote_minus' ],
			[ 'us_id' => $us_id ],
			__METHOD__
		);

		if ( $s !== false ) {
			$votes = [];
			$votes['plus'] = $s->us_vote_plus;
			$votes['minus'] = $s->us_vote_minus;
			return $votes;
		}

		return false;
	}

	/**
	 * Get some information about the users who voted for a given status update.
	 * This information includes vote timestamp, the user's name and ID number
	 * as well as the vote itself.
	 *
	 * @param int $us_id Status update ID number
	 * @return array
	 */
	public function getStatusVoters( $us_id ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		$res = $dbr->select(
			'user_status_vote',
			[ 'sv_actor', 'sv_date', 'sv_vote_score' ],
			[ 'sv_us_id' => intval( $us_id ) ],
			__METHOD__,
			[ 'ORDER BY' => 'sv_id DESC' ]
		);

		$voters = [];

		foreach ( $res as $row ) {
			$voters[] = [
				'timestamp' => wfTimestamp( TS_UNIX, $row->sv_date ),
				'actor' => $row->sv_actor,
				'score' => $row->sv_vote_score
			];
		}

		return $voters;
	}

	static function getNetworkUpdatesCount( $sport_id, $team_id ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		if ( !$team_id ) {
			$teamIds = [];
			$res = $dbr->select(
				'sport_team',
				'team_id',
				[ 'team_sport_id' => $sport_id ],
				__METHOD__
			);
			foreach ( $res as $row ) {
				$teamIds[] = $row->team_id;
			}

			$row = $dbr->selectRow(
				'user_status',
				'COUNT(*) AS the_count',
				[
					// @todo This should be using Database#makeList somehow, but I just couldn't
					// figure out how
					"( us_sport_id = {$sport_id} AND us_team_id = 0 ) " .
						'OR us_team_id IN (' . $dbr->makeList( $teamIds ) . ')'
				],
				__METHOD__
			);
		} else {
			$row = $dbr->selectRow(
				'user_status',
				'COUNT(*) AS the_count',
				[ 'us_team_id' => $team_id ],
				__METHOD__
			);
		}
		return $row->the_count;
	}

	static function getUserUpdatesURL( $user_name ) {
		$title = SpecialPage::getTitleFor( 'UserStatus' );
		return htmlspecialchars( $title->getFullURL( "user=$user_name" ) );
	}

	public static function dateDifference( $date1, $date2 ) {
		$dtDiff = $date1 - $date2;

		$totalDays = intval( $dtDiff / ( 24 * 60 * 60 ) );
		$totalSecs = $dtDiff - ( $totalDays * 24 * 60 * 60 );
		$dif = [];
		$dif['w'] = intval( $totalDays / 7 );
		$dif['d'] = $totalDays;
		$dif['h'] = $h = intval( $totalSecs / ( 60 * 60 ) );
		$dif['m'] = $m = intval( ( $totalSecs - ( $h * 60 * 60 ) ) / 60 );
		$dif['s'] = $totalSecs - ( $h * 60 * 60 ) - ( $m * 60 );

		return $dif;
	}

	public static function getTimeOffset( $time, $timeabrv, $timename ) {
		$timeStr = '';
		if ( $time[$timeabrv] > 0 ) {
			$timeStr = wfMessage( "userstatus-time-{$timename}", $time[$timeabrv] )->parse();
		}
		if ( $timeStr ) {
			$timeStr .= ' ';
		}
		return $timeStr;
	}

	public static function getTimeAgo( $time ) {
		$timeArray = self::dateDifference( time(), $time );
		$timeStr = '';
		$timeStrD = self::getTimeOffset( $timeArray, 'd', 'days' );
		$timeStrH = self::getTimeOffset( $timeArray, 'h', 'hours' );
		$timeStrM = self::getTimeOffset( $timeArray, 'm', 'minutes' );
		$timeStrS = self::getTimeOffset( $timeArray, 's', 'seconds' );
		$timeStr = $timeStrD;
		if ( $timeStr < 2 ) {
			$timeStr .= $timeStrH;
			$timeStr .= $timeStrM;
			if ( !$timeStr ) {
				$timeStr .= $timeStrS;
			}
		}
		if ( !$timeStr ) {
			$timeStr = wfMessage( 'userstatus-time-seconds', 1 )->parse();
		}
		return $timeStr;
	}

	/**
	 * Cuts link text if it's too long.
	 * For example, http://www.google.com/some_stuff_here could be changed into
	 * http://goo...stuff_here or so.
	 *
	 * @param string[] $matches
	 * @return string
	 */
	public static function cutLinkText( $matches ) {
		$tagOpen = $matches[1];
		$linkText = $matches[2];
		$tagClose = $matches[3];

		$image = preg_match( '/<img src=/i', $linkText );
		$isURL = (bool)preg_match( '%^(?:http|https|ftp)://(?:www\.)?.*$%i', $linkText );

		if ( $isURL && !$image && strlen( $linkText ) > 50 ) {
			$start = substr( $linkText, 0, ( 50 / 2 ) - 3 );
			$end = substr( $linkText, strlen( $linkText ) - ( 50 / 2 ) + 3, ( 50 / 2 ) - 3 );
			$linkText = trim( $start ) . wfMessage( 'ellipsis' )->plain() . trim( $end );
		}

		return $tagOpen . $linkText . $tagClose;
	}
}
