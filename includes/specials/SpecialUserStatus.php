<?php

use MediaWiki\MediaWikiServices;

class ViewUserStatus extends UnlistedSpecialPage {

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'UserStatus' );
	}

	/**
	 * Indicate that we do perform write operations, or at least we have the technical
	 * capability to do that (though this page is primarily used for write actions
	 * only by users who have JavaScript disabled).
	 *
	 * @return bool
	 */
	public function doesWrites() {
		return true;
	}

	/**
	 * Show the special page
	 *
	 * @param string|int|null $par Parameter passed to the special page, if any
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$currentUser = $this->getUser();
		$linkRenderer = $this->getLinkRenderer();

		$messages_show = 25;
		$output = '';
		$user_name = $request->getVal( 'user', $par );
		$page = $request->getInt( 'page', 1 );

		/**
		 * Redirect Non-logged in users to Login Page
		 * It will automatically return them to their Status page
		 */
		if ( $currentUser->getId() == 0 && $user_name == '' ) {
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
		$user = User::newFromName( $user_name );
		$user_id = $user->getId();
		$actor_id = $user->getActorId();

		/**
		 * Error message for username that does not exist (from URL)
		 */
		if ( $user_id == 0 ) {
			$out->setPageTitle( $this->msg( 'userstatus-woops' )->text() );
			$out->addHTML( $this->msg( 'userstatus-no-user' )->escaped() );
			return false;
		}

		$s = new UserStatus( $currentUser );

		// Add CSS (needed by the no-JS render*Form() stuff below)
		$out->addModuleStyles( 'ext.userStatus.styles' );

		/**
		 * Handle support for no-JS users
		 */
		$wasPosted = $request->wasPosted();
		$action = $request->getVal( 'action' );
		$isDelete = ( $action === 'delete' );
		$us_id = $request->getInt( 'us_id' );

		$isVote = ( $action === 'vote' );
		$vote = $request->getInt( 'vote' );

		$isAdd = ( $action === 'add' );
		// @todo FIXME: add action=add to simulate "add thought" from user profile,
		// then change the profile hooks to point to this page w/ ?action=add
		/*
		if ( $isAdd && !$wasPosted ) {
			$out->setPageTitle( $this->msg( 'userstatus-add-page-title' ) );
			$output .= $this->renderAddForm();
			$out->addHTML( $output );
			return;
		}
		*/

		if ( $isDelete && !$wasPosted && $us_id ) {
			$out->setPageTitle( $this->msg( 'userstatus-delete-page-title' ) );
			$output .= $this->renderConfirmDeleteForm( $us_id );
			$out->addHTML( $output );
			return;
		}

		if ( $isVote && !$wasPosted && $us_id && $vote ) {
			$out->setPageTitle( $this->msg( 'userstatus-vote-page-title' ) );
			$output .= $this->renderConfirmVoteForm( $us_id, $vote );
			$out->addHTML( $output );
			return;
		}

		if ( $wasPosted && !MediaWikiServices::getInstance()->getReadOnlyMode()->isReadOnly() ) {
			// @todo FIXME: add handling for action=add here
			/*
			if ( $isAdd ) {
				( new UserStatus() )->addStatus(
					$request->getInt( 'sport_id' ),
					$request->getInt( 'team_id' ),
					urldecode( $request->getText( 'user_status_text' ) )
				);
			} else
			*/
			// Deletions
			if ( $isDelete ) {
				if (
					(
						$s->doesUserOwnStatusMessage( $currentUser->getActorId(), $us_id ) ||
						$currentUser->isAllowed( 'delete-status-updates' )
					) &&
					$currentUser->matchEditToken( $request->getVal( 'wpDeleteToken' ) )
				) {
					$s->deleteStatus( $us_id );
					$output .= Html::successBox( $this->msg( 'userstatus-delete-success' )->escaped() );
				} else {
					// CSRF attempt or something...display an informational message in that case
					$output .= Html::errorBox( $this->msg( 'sessionfailure' )->escaped() );
				}
			} elseif ( $isVote ) {
				// Voting for a thought (=agreeing with it)
				if ( $currentUser->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
					$s->addStatusVote( $us_id, $vote );
					$output .= Html::successBox( $this->msg( 'userstatus-vote-success' )->escaped() );
				} else {
					// CSRF attempt or something...display an informational message in that case
					$output .= Html::errorBox( $this->msg( 'sessionfailure' )->escaped() );
				}
			}
		}

		/**
		 * Config for the page
		 */
		$per_page = $messages_show;

		$stats = new UserStats( $user_id, $user_name );
		$stats_data = $stats->getUserStats();
		$total = $stats_data['user_status_count'];

		$messages = $s->getStatusMessages( $actor_id, 0, 0, $messages_show, $page );

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
			$output .= $linkRenderer->makeLink(
				$user->getUserPage(),
				$this->msg( 'userstatus-back-user-profile', $user_name )->text()
			);
		} else {
			$output .= $linkRenderer->makeLink(
				$currentUser->getUserPage(),
				$this->msg( 'userstatus-back-your-profile' )->text()
			);
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
				<span class="user-page-message-count">' .
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
				$output .= $linkRenderer->makeLink(
					$thisTitle,
					$this->msg( 'userstatus-prev' )->text(),
					[],
					[
						'user' => $user_name,
						'page' => ( $page - 1 )
					]
				) . $this->msg( 'word-separator' )->escaped();
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
					$output .= $linkRenderer->makeLink(
						$thisTitle,
						(string)$i,
						[],
						[
							'user' => $user_name,
							'page' => $i
						]
					) . $this->msg( 'word-separator' )->escaped();
				}
			}

			if ( ( $total - ( $per_page * $page ) ) > 0 ) {
				$output .= $this->msg( 'word-separator' )->escaped() .
					$linkRenderer->makeLink(
						$thisTitle,
						$this->msg( 'userstatus-next' )->text(),
						[],
						[
							'user' => $user_name,
							'page' => ( $page + 1 )
						]
					);
			}
			$output .= '</div><p>';
		}

		// Add JS
		$out->addModules( 'ext.userStatus.scripts' );

		$output .= '<div class="user-status-container">';
		$thought_link = SpecialPage::getTitleFor( 'ViewThought' );
		if ( $messages ) {
			$fanHome = SpecialPage::getTitleFor( 'FanHome' );
			$templateParser = new TemplateParser( __DIR__ . '/../templates' );

			// @todo I get the feeling that this massively duplicates UserStatus#displayStatusMessages
			foreach ( $messages as $message ) {
				$user = User::newFromActorId( $message['actor'] );
				$avatar = new wAvatar( $user->getId(), 'm' );

				$networkURL = htmlspecialchars(
					$fanHome->getFullURL( [
						'sport_id' => $message['sport_id'],
						'team_id' => $message['team_id']
					] ),
					ENT_QUOTES
				);

				$network_link = $linkRenderer->makeLink(
					$fanHome,
					$this->msg(
						'userstatus-all-team-updates',
						SportsTeams::getNetworkName( $message['sport_id'], $message['team_id'] )
					)->text(),
					[],
					[
						'sport_id' => $message['sport_id'],
						'team_id' => $message['team_id']
					]
				);

				$delete_link = '';
				if (
					$currentUser->getActorId() == $message['actor'] ||
					$currentUser->isAllowed( 'delete-status-updates' )
				)
				{
					$deleteURL = htmlspecialchars(
						$thisTitle->getFullURL( [
							'action' => 'delete',
							'us_id' => $message['id']
						] ),
						ENT_QUOTES
					);
					$delete_link = "<span class=\"user-status-delete-link\">
						<a href=\"{$deleteURL}\" data-message-id=\"{$message['id']}\">" .
						$this->msg( 'userstatus-delete-thought-text' )->escaped() ."</a>
					</span>";
				}

				// If there are links and their texts are tl;dr, cut 'em a bit
				$message_text = preg_replace_callback(
					'/(<a[^>]*>)(.*?)(<\/a>)/i',
					[ 'UserStatus', 'cutLinkText' ],
					$message['text']
				);

				$vote_count = $this->msg( 'userstatus-num-agree' )->numParams( $message['plus_count'] )->parse();

				$vote_link = '';
				// Only registered users who aren't the author of the particular
				// thought can vote for it
				if ( $currentUser->isRegistered() && $currentUser->getActorId() != $message['actor'] ) {
					if ( !$message['voted'] ) {
						$voteURL = htmlspecialchars(
							$thisTitle->getFullURL( [
								'action' => 'vote',
								'us_id' => $message['id']
							] ),
							ENT_QUOTES
						);
						$vote_link = "<a class=\"vote-status-link\" href=\"{$voteURL}\" data-message-id=\"{$message['id']}\">[" .
							$this->msg( 'userstatus-agree' )->escaped() . ']</a>';
					} else {
						$vote_link = $vote_count;
					}
				}

				$view_thought_link = $linkRenderer->makeLink(
					$thought_link,
					$this->msg(
						'brackets',
						$this->msg( 'userstatus-see-who-agrees' )->plain()
					)->plain(),
					[],
					[ 'id' => $message['id'] ]
				);

				$output .= $templateParser->processTemplate(
					'status-update',
					[
						'containerClass' => 'user-status-row',
						'showUserAvatar' => false,
						'networkURL' => $networkURL,
						'networkLogo' => SportsTeams::getLogo( $message['sport_id'], $message['team_id'], 'm' ),
						'messageText' => $message_text,
						'messageId' => $message['id'],
						'postedAgo' => $this->msg( 'userstatus-ago', UserStatus::getTimeAgo( $message['timestamp'] ) )->text(),
						'showActionLinks' => true,
						'voteLink' => $vote_link,
						'viewThoughtLink' => $view_thought_link,
						'deleteLink' => $delete_link
					]
				);
			}
		} else {
			$output .= '<p>' . $this->msg( 'userstatus-no-updates' )->escaped() . '</p>';
		}

		$output .= '</div>';

		$out->addHTML( $output );
	}

	/**
	 * Render the form for adding a new thought.
	 * Primarily used by no-JS users.
	 *
	 * @return string HTML
	 */
	private function renderAddForm() {
		$form = '';
		/*
		$form .= '<form method="post" name="add-thought" action="">';
		$form .= $this->msg( 'userstatus-add-thought' )->parseAsBlock();
		$form .= '<br />';
		// @todo FIXME: Team & sport selector here somehow...
		$form .= Html::input( 'text',
		$form .= Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );
		$form .= '</form>';
		*/
		return $form;
	}

	/**
	 * Render the "are you sure you REALLY want to delete this thought?" <form>.
	 * Primarily used by no-JS users.
	 *
	 * @param int $us_id ID of the thought to be deleted
	 * @return string HTML
	 */
	private function renderConfirmDeleteForm( $us_id ) {
		$form = '';
		$user = $this->getUser();
		$s = new UserStatus( $user );

		if (
			$s->doesUserOwnStatusMessage( $user->getActorId(), $us_id ) ||
			$user->isAllowed( 'delete-status-updates' )

		) {
			if ( !$user->isAllowed( 'delete-status-updates' ) ) {
				throw new PermissionsError( 'delete-status-updates' );
			}
		}

		$form .= '<form method="post" name="delete-thought" action="">';
		// parseAsBlock() to force the <p> tags to give it some nice spacing and whatnot
		$form .= $this->msg( 'userstatus-confirm-delete' )->parseAsBlock();
		$form .= '<br />';

		// @todo FIXME: This should ideally be UserStatus#displayMessage
		// (to complement the existing displayMessage*s* method)
		// Then again doing it like this allows us slightly more fine-grained control
		// over the variables passed to the template
		$message = $s->getStatusMessage( $us_id );
		$statusUpdateAuthor = User::newFromActorId( $message['actor'] );
		$form .= ( new TemplateParser( __DIR__ . '/../templates' ) )->processTemplate(
			'status-update',
			[
				'containerClass' => 'user-status-row',
				'userPageURL' => $statusUpdateAuthor->getUserPage()->getFullURL(),
				'showUserAvatar' => true,
				'avatarElement' => ( new wAvatar( $statusUpdateAuthor->getId(), 'm' ) )->getAvatarURL(),
				'userName' => $statusUpdateAuthor->getName(),
				'messageId' => $message['id'],
				'messageText' => preg_replace_callback(
					'/(<a[^>]*>)(.*?)(<\/a>)/i',
					[ 'UserStatus', 'cutLinkText' ],
					$message['text']
				),
				'postedAgo' => $this->msg( 'userstatus-ago', UserStatus::getTimeAgo( $message['timestamp'] ) )->text(),
				'showActionLinks' => false,
			]
		);

		$form .= Html::hidden( 'wpDeleteToken', $user->getEditToken() );
		$form .= Html::hidden( 'us_id', $us_id );
		$form .= Html::hidden( 'action', 'delete' );
		$form .= Html::submitButton( $this->msg( 'delete' )->text(), [ 'name' => 'wpSubmit', 'class' => 'site-button' ] );
		$form .= '</form>';

		return $form;
	}

	/**
	 * Render the "are you sure you REALLY want to agree with this thought?" <form>.
	 * Primarily used by no-JS users.
	 *
	 * @param int $us_id ID of the thought to be agreed with
	 * @param int $vote Vote, either 1 (upvote) or -1 (downvote, i.e. revocation of an existing upvote)
	 * @return string HTML
	 */
	private function renderConfirmVoteForm( $us_id, $vote ) {
		$form = '';
		$user = $this->getUser();
		$s = new UserStatus( $user );

		$form .= '<form method="post" name="vote-thought" action="">';
		$form .= $this->msg( 'userstatus-confirm-vote' )->parseAsBlock();
		$form .= '<br />';

		// @todo FIXME: This should ideally be UserStatus#displayMessage
		// (to complement the existing displayMessage*s* method)
		// Then again doing it like this allows us slightly more fine-grained control
		// over the variables passed to the template
		$message = $s->getStatusMessage( $us_id );
		$statusUpdateAuthor = User::newFromActorId( $message['actor'] );
		$form .= ( new TemplateParser( __DIR__ . '/../templates' ) )->processTemplate(
			'status-update',
			[
				'containerClass' => 'user-status-row',
				'userPageURL' => $statusUpdateAuthor->getUserPage()->getFullURL(),
				'showUserAvatar' => true,
				'avatarElement' => ( new wAvatar( $statusUpdateAuthor->getId(), 'm' ) )->getAvatarURL(),
				'userName' => $statusUpdateAuthor->getName(),
				'messageId' => $message['id'],
				'messageText' => preg_replace_callback(
					'/(<a[^>]*>)(.*?)(<\/a>)/i',
					[ 'UserStatus', 'cutLinkText' ],
					$message['text']
				),
				'postedAgo' => $this->msg( 'userstatus-ago', UserStatus::getTimeAgo( $message['timestamp'] ) )->text(),
				'showActionLinks' => false,
			]
		);

		$form .= Html::hidden( 'wpEditToken', $user->getEditToken() );
		$form .= Html::hidden( 'us_id', $us_id );
		$form .= Html::hidden( 'vote', $vote );
		$form .= Html::hidden( 'user', $statusUpdateAuthor->getName() );
		$form .= Html::hidden( 'action', 'vote' );
		$form .= Html::submitButton( $this->msg( 'userstatus-vote' )->text(), [ 'name' => 'wpSubmit', 'class' => 'site-button' ] );
		$form .= '</form>';

		return $form;
	}

}
