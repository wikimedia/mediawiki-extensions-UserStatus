<?php
/**
 * @file
 */
class UserStatusHooks {
	/**
	 * Creates UserStatus' new database tables when the user runs
	 * /maintenance/update.php, the MediaWiki core updater script.
	 *
	 * @param $updater DatabaseUpdater
	 * @return Boolean
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = dirname( __FILE__ );
		$dbExt = '';

		/*
		if ( !in_array( $updater->getDB()->getType(), array( 'mysql', 'sqlite' ) ) ) {
			$dbExt = ".{$updater->getDB()->getType()}";
		}
		*/

		// Core SocialProfile had a very similar feature; if its tables are
		// present, get rid of them first before trying to add our tables,
		// because both the SP core one and this extension use a table called
		// "user_status" and trying to create a table that already exists won't
		// work, obviously
		if ( $updater->getDB()->tableExists( 'user_status_history' ) ) {
			$tablesToDrop = array(
				'user_status',
				'user_status_history',
				'user_status_likes'
			);
			foreach ( $tablesToDrop as $table ) {
				$updater->getDB()->query( "DROP TABLE {$table}", __METHOD__ );
			}
		}

		$updater->addExtensionUpdate( array( 'addTable', 'user_status', "$dir/user_status$dbExt.sql", true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'user_status_vote', "$dir/user_status$dbExt.sql", true ) );

		return true;
	}

	/**
	 * For integration with the Renameuser extension.
	 *
	 * @param $renameUserSQL RenameuserSQL
	 * @return bool
	 */
	public static function onRenameUserSQL( $renameUserSQL ) {
		$renameUserSQL->tables['user_status'] = array( 'us_user_name', 'us_user_id' );
		$renameUserSQL->tables['user_status_vote'] = array( 'sv_user_name', 'sv_user_id' );
		return true;
	}
}