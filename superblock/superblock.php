<?php
/**
 * Name: Superblock
 * Description: Block and manage a block list of channels you don't want to see again.
 * Version: 3.2.0
 * Author: Mike Macgirvin
 * Author: Harald Eilertsen
 * Maintainer: Mike Macgirvin <mike@macgirvin.com>
 * Maintainer: Harald Eilertsen
 * MinVersion: 11.4
 */

require_once __DIR__ . '/../addon_common/vendor/autoload.php';

use Zotlabs\Addons\Superblock\Superblock;
use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Hook;
use Zotlabs\Extend\Route;

/**
 * Setup the addon when enabled.
 *
 * Called when the addon is enabled by the site administrator.
 *
 * Registers the hooks we want to listen to, and a route for
 * the superblock user facing app page.
 */
function superblock_load(): void
{
	$hooks = [
		'activity_widget' => 'superblock_activity_widget',
		'api_format_items' => 'superblock_api_format_items',
		'conversation_start' => 'superblock_conversation_start',
		'directory_item' => 'superblock_directory_item',
		'enotify_format' => 'superblock_enotify_format',
		'enotify_store' => 'superblock_enotify_store',
		'item_store_before' => 'superblock_item_store_before',
		'item_store' => 'superblock_item_store',
		'messages_widget' => 'superblock_messages_widget',
		'perm_is_allowed' => 'superblock_perm_is_allowed',
		'post_mail' => 'superblock_post_mail',
		'stream_item' => 'superblock_stream_item',
		'thread_author_menu' => 'superblock_item_photo_menu',
	];

	Hook::register_array('addon/superblock/superblock.php', $hooks);
	Route::register('addon/superblock/Mod_Superblock.php','superblock');
	Route::register('addon/superblock/src/Module/Settings/Superblock.php', 'settings/superblock');
}


/**
 * Unregister the addon.
 *
 * Called when the addon is disabled by the site administrator,
 * and cleans up after the addon.
 */
function superblock_unload(): void
{
	Hook::unregister_by_file('addon/superblock/superblock.php');
	Route::unregister('addon/superblock/Mod_Superblock.php','superblock');
	Route::unregister('addon/superblock/src/Module/Settings/Superblock.php', 'settings/superblock');
}

function superblock_stream_item(&$b)
{
	$channelId = local_channel();

	if ($channelId && Apps::addon_app_installed($channelId, 'superblock')) {
		$plugin = Superblock::getInstance($channelId);
		$plugin->filterStreamItem($b['item']);
	}
}

/**
 * Filters incoming activities before storing them.
 */
function superblock_item_store_before(array &$params): void
{
	// Make sure we take a reference to the item, so that any
	// changes we do to it is reflected back to the caller.
	$item = &$params['item'] ?? [];

	if (empty($item)) {
		return;
	}

	$channelId = $item['uid'] ?? 0;
	if ($channelId && Apps::addon_app_installed($channelId, 'superblock')) {
		$plugin = Superblock::getInstance($channelId);
		if ($plugin->settings->blockIncoming() && $plugin->filterItem($item)) {
			$item['cancel'] = true;
		}
	}
}

function superblock_item_store(&$b)
{
	if (!empty($b['item_wall'])
		&& isset($b['uid'])
		&& Apps::addon_app_installed($b['uid'], 'superblock'))
	{
		$plugin = Superblock::getInstance($b['uid']);
		if ($plugin->filterItem($b)) {
			$b['cancel'] = true;
		}
	}
}

function superblock_post_mail(&$b)
{
	if (isset($b['channel_id'])
		&& Apps::addon_app_installed($b['channel_id'], 'superblock'))
	{
		$plugin = Superblock::getInstance($b['channel_id']);
		$plugin->filterMailPost($b);
	}
}

function superblock_enotify_store(&$b)
{
	if (isset($b['uid'])
		&& Apps::addon_app_installed($b['uid'], 'superblock'))
	{
		$plugin = Superblock::getInstance($b['uid']);
		$plugin->filterEnotifyStore($b);
	}
}


function superblock_enotify_format(&$b)
{
	if (isset($b['uid'])
		&& Apps::addon_app_installed($b['uid'], 'superblock'))
	{
		$plugin = Superblock::getInstance($b['uid']);
		$plugin->filterEnotifyFormat($b);
	}
}

function superblock_messages_widget(&$b)
{
	if (isset($b['uid'])
		&& Apps::addon_app_installed($b['uid'], 'superblock'))
	{
		$plugin = Superblock::getInstance($b['uid']);
		$plugin->cancelItem($b);
	}
}

function superblock_api_format_items(&$b)
{
	if (isset($b['api_user'])
		&& Apps::addon_app_installed($b['api_user'], 'superblock'))
	{
		$plugin = Superblock::getInstance($b['api_user']);

		// array_filter does not reindex the array, so we wrap it in array_values
		// to be sure the resulting array is indexed linearly without gaps.
		$b['items'] = array_values(
			array_filter($b['items'], fn ($item) => $plugin->filterItem($item) === false)
		);
	}
}


function superblock_directory_item(&$b)
{
	$channelId = local_channel();

	if ($channelId && Apps::addon_app_installed($channelId, 'superblock')) {
		$plugin = Superblock::getInstance($channelId);
		$plugin->filterDirectoryItem($b);
	}
}


function superblock_activity_widget(&$b)
{
	$channelId = local_channel();

	if ($channelId && Apps::addon_app_installed($channelId, 'superblock')) {
		$plugin = Superblock::getInstance($channelId);

		// array_filter does not reindex the array, so we wrap it in array_values
		// to be sure the resulting array is indexed linearly without gaps.
		$b['entries'] = array_values(
			array_filter($b['entries'], fn ($item) => $plugin->filterItem($item) === false)
		);
	}
}

/**
 * Filter incoming activities from blocked senders.
 *
 * @param array $params
 *		An associative array of the hook parameters:
 *		- \b channel_id \e (in) - The recipient channel id
 *		- \b observer_hash \e (in) - The xchan hash of the sender
 *		- \b permission \e (in) - The permission to check
 *		- \b result \e (out) - `true` if permission is allowed, `false` if
 *		  denied, `"unset"` by default.
 *
 */
function superblock_perm_is_allowed(array &$params): void
{
	$channelId = $params['channel_id'] ?? 0;
	$sender = $params['observer_hash'] ?? 0;

	if (!$sender) {
		// We can't block activities with no sender
		return;
	}

	if ($channelId && Apps::addon_app_installed($channelId, 'superblock')) {
		$perm = $params['permission'] ?? '';
		if (!in_array($perm, ['send_stream'])) {
			// Not a permission we care about
			return;
		}

		$plugin = Superblock::getInstance($channelId);

		if ($plugin->settings->blockIncoming() && $plugin->filterByProfileUrl($sender)) {
			$params['result'] = false;
		}
	}
}

/**
 * Inject javascript helpers at start of the conversation view.
 *
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
function superblock_conversation_start(&$b)
{
	$channelId = local_channel();

	if ($channelId && Apps::addon_app_installed($channelId, 'superblock')) {
		$plugin = Superblock::getInstance($channelId);
		$plugin->loadJavaScript();
		$plugin->loadStyleSheet();

		App::$page['content'] .= '<dialog id="superblockSubmitDialog" closedby="any"></dialog>';
	}
}

/**
 * Add superblock menu items to the avatar menu of a post.
 *
 * @param array $args
 *    Reference to an array containing the following fields:
 *      - \e item - An array with the current item.
 *      - \e mode - A string containing a mode (unused by superblock).
 *      - \e menu - An array of menu items for this item.
 */
function superblock_item_photo_menu(array &$args): void
{
	$channelId = local_channel();

	if ($channelId && Apps::addon_app_installed(local_channel(), 'superblock')) {
		$author = $args['item']['author_xchan'];
		$item = $args['item']['id'];

		if (App::$channel['channel_hash'] == $author) {
			return;
		}

		$plugin = Superblock::getInstance($channelId);
		$security = $plugin->security_token;

		if (!$plugin->isChannelBlocked($author)) {
		    $args['menu'][] = [
				'menu' => 'superblock_mute',
				'title' => t('Block channel…'),
				'icon' => 'fw',
				'action' => "superblockPopupSubmitForm('${author}', true); return false;",
				'href' => "#"
			];

			if (is_site_admin()) {
				$args['menu'][] = [
					'superblock_admin_block',
					'title' => t('Block from site'),
					'icon' => 'fw',
					'action' => "superblockAjax('siteblock', '{$author}', {$item}, '{$security}'); return false;",
					'href' => '#',
				];
			}
		}
	}
}
