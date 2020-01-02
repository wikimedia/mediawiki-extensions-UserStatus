<?php
/**
 * @file
 */
class UserStatusHooks {
	/**
	 * Creates UserStatus' new database tables when the user runs
	 * /maintenance/update.php, the MediaWiki core updater script.
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dbExt = '';

		/*
		if ( !in_array( $updater->getDB()->getType(), [ 'mysql', 'sqlite' ] ) ) {
			$dbExt = ".{$updater->getDB()->getType()}";
		}
		*/

		// Core SocialProfile had a very similar feature; if its tables are
		// present, get rid of them first before trying to add our tables,
		// because both the SP core one and this extension use a table called
		// "user_status" and trying to create a table that already exists won't
		// work, obviously
		if ( $updater->getDB()->tableExists( 'user_status_history' ) ) {
			$tablesToDrop = [
				'user_status',
				'user_status_history',
				'user_status_likes'
			];
			foreach ( $tablesToDrop as $table ) {
				$updater->getDB()->query( "DROP TABLE {$table}", __METHOD__ );
			}
		}

		$updater->addExtensionTable( 'user_status', __DIR__ . "/../sql/user_status$dbExt.sql" );
		$updater->addExtensionTable( 'user_status_vote', __DIR__ . "/../sql/user_status$dbExt.sql" );
	}
}
