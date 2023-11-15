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
		$db = $updater->getDB();
		$dbType = $db->getType();

		// Core SocialProfile had a very similar feature; if its tables are
		// present, get rid of them first before trying to add our tables,
		// because both the SP core one and this extension use a table called
		// "user_status" and trying to create a table that already exists won't
		// work, obviously
		if ( $db->tableExists( 'user_status_history' ) ) {
			$tablesToDrop = [
				'user_status',
				'user_status_history',
				'user_status_likes'
			];
			foreach ( $tablesToDrop as $table ) {
				$db->query( "DROP TABLE {$table}", __METHOD__ );
			}
		}

		$dir = __DIR__ . '/../sql';
		if ( !in_array( $dbType, [ 'mysql', 'sqlite' ] ) ) {
			$dir .= '/' . $dbType;
		}

		$updater->addExtensionTable( 'user_status', $dir . '/user_status.sql' );
		$updater->addExtensionTable( 'user_status_vote', $dir . '/user_status_vote.sql' );
	}
}
