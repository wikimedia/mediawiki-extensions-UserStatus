<?php
/**
 * UserStatus extension -- allows users to provide social status updates
 * (à la Twitter or Facebook) that will show up on their social profile pages.
 *
 * @file
 * @ingroup Extensions
 * @date 10 September 2014
 * @author Aaron Wright <aaron.wright@gmail.com>
 * @author David Pean <david.pean@gmail.com>
 * @author Jack Phoenix <jack@countervandalism.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link https://www.mediawiki.org/wiki/Extension:UserStatus Documentation
 */

/**
 * Protect against register_globals vulnerabilities.
 * This line must be present before any global variable is referenced.
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is not a valid entry point to MediaWiki.' );
}

// Extension credits that will show up on Special:Version
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'UserStatus',
	'version' => '3.2',
	'author' => array( 'Aaron Wright', 'David Pean', 'Jack Phoenix' ),
	'description' => 'Social status updates on user profiles and on network pages',
	'url' => 'https://www.mediawiki.org/wiki/Extension:UserStatus'
);

// Set up i18n and the new special pages
$wgMessagesDirs['UserStatus'] = __DIR__ . '/i18n';
$wgAutoloadClasses['UserStatus'] = __DIR__ . '/UserStatusClass.php';
$wgAutoloadClasses['ViewFanUpdates'] = __DIR__ . '/SpecialFanUpdates.php';
$wgAutoloadClasses['ViewUserStatus'] = __DIR__ . '/SpecialUserStatus.php';
$wgAutoloadClasses['ViewThought'] = __DIR__ . '/SpecialViewThought.php';
$wgSpecialPages['FanUpdates'] = 'ViewFanUpdates';
$wgSpecialPages['UserStatus'] = 'ViewUserStatus';
$wgSpecialPages['ViewThought'] = 'ViewThought';

// API module
$wgAutoloadClasses['ApiUserStatus'] = __DIR__ . '/ApiUserStatus.php';
$wgAPIModules['userstatus'] = 'ApiUserStatus';

// New user right, required to delete other people's status messages
$wgAvailableRights[] = 'delete-status-updates';
$wgGroupPermissions['sysop']['delete-status-updates'] = true;
$wgGroupPermissions['staff']['delete-status-updates'] = true;

// ResourceLoader support for MediaWiki 1.17+
$resourceTemplate = array(
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'UserStatus',
	'position' => 'top' // available since r85616
);

$wgResourceModules['ext.userStatus'] = $resourceTemplate + array(
	'styles' => 'UserStatus.css',
	'scripts' => 'UserStatus.js',
	'messages' => array( 'userstatus-confirm-delete' )
);

$wgResourceModules['ext.userStatus.viewThought'] = $resourceTemplate + array(
	'styles' => 'ViewThought.css'
);

// Hooked functions, such as the database updater, etc.
$wgAutoloadClasses['UserStatusHooks'] = __DIR__ . '/UserStatusHooks.php';

$wgHooks['LoadExtensionSchemaUpdates'][] = 'UserStatusHooks::onLoadExtensionSchemaUpdates';
$wgHooks['RenameUserSQL'][] = 'UserStatusHooks::onRenameUserSQL';
