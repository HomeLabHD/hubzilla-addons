<?php
/**
 * Name: Superblock
 * Description: Block and manage a block list of channels you don't want to see again.
 * Version: 3.0
 * Author: Mike Macgirvin
 * Author: Harald Eilertsen
 * Maintainer: Mike Macgirvin <mike@macgirvin.com>
 * Maintainer: Harald Eilertsen
 * MinVErsion: 10.0
 */

/**
 * This function uses some helper code in include/conversation; which handles filtering item authors.
 * Those function should ultimately be moved to this plugin.
 *
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Zotlabs\Addons\Superblock\Superblock;
use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Route;

function superblock_load() {

	register_hook('conversation_start', 'addon/superblock/superblock.php', 'superblock_conversation_start');
	register_hook('thread_author_menu', 'addon/superblock/superblock.php', 'superblock_item_photo_menu');
	register_hook('enotify_store', 'addon/superblock/superblock.php', 'superblock_enotify_store');
	register_hook('enotify_format', 'addon/superblock/superblock.php', 'superblock_enotify_format');
	register_hook('messages_widget', 'addon/superblock/superblock.php', 'superblock_messages_widget');
	register_hook('item_store', 'addon/superblock/superblock.php', 'superblock_item_store');
	register_hook('directory_item', 'addon/superblock/superblock.php', 'superblock_directory_item');
	register_hook('api_format_items', 'addon/superblock/superblock.php', 'superblock_api_format_items');
	register_hook('stream_item', 'addon/superblock/superblock.php', 'superblock_stream_item');
	register_hook('post_mail', 'addon/superblock/superblock.php', 'superblock_post_mail');
	register_hook('activity_widget', 'addon/superblock/superblock.php', 'superblock_activity_widget');
	Route::register('addon/superblock/Mod_Superblock.php','superblock');

}


function superblock_unload() {

	unregister_hook('conversation_start', 'addon/superblock/superblock.php', 'superblock_conversation_start');
	unregister_hook('thread_author_menu', 'addon/superblock/superblock.php', 'superblock_item_photo_menu');
	unregister_hook('enotify_store', 'addon/superblock/superblock.php', 'superblock_enotify_store');
	unregister_hook('enotify_format', 'addon/superblock/superblock.php', 'superblock_enotify_format');
	unregister_hook('messages_widget', 'addon/superblock/superblock.php', 'superblock_messages_widget');
	unregister_hook('item_store', 'addon/superblock/superblock.php', 'superblock_item_store');
	unregister_hook('directory_item', 'addon/superblock/superblock.php', 'superblock_directory_item');
	unregister_hook('api_format_items', 'addon/superblock/superblock.php', 'superblock_api_format_items');
	unregister_hook('stream_item', 'addon/superblock/superblock.php', 'superblock_stream_item');
	unregister_hook('post_mail', 'addon/superblock/superblock.php', 'superblock_post_mail');
	unregister_hook('activity_widget', 'addon/superblock/superblock.php', 'superblock_activity_widget');
	Route::unregister('addon/superblock/Mod_Superblock.php','superblock');

}

function superblock_stream_item(&$b)
{
	$channelId = local_channel();

	if ($channelId && Apps::addon_app_installed($channelId, 'superblock')) {
		$plugin = Superblock::getInstance($channelId);
		$plugin->filterStreamItem($b['item']);
	}
}


function superblock_item_store(&$b)
{
	if (!empty($b['item_wall'])
		&& isset($b['uid'])
		&& Apps::addon_app_installed($b['uid'], 'superblock'))
	{
		$plugin = Superblock::getInstance($b['uid']);
		$plugin->cancelItem($b);
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
 * Inject javascript helpers at start of the conversation view.
 *
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
function superblock_conversation_start(&$b)
{
	$channelId = local_channel();

	if ($channelId && Apps::addon_app_installed($channelId, 'superblock')) {
		$plugin = Superblock::getInstance($channelId);

		$words = get_pconfig(local_channel(),'system','blocked');
		if ($words) {
			App::$data['superblock'] = explode(',',$words);
		}

		$plugin->loadJavaScript();
	}
}

function superblock_item_photo_menu(&$b)
{
	$channelId = local_channel();

	if ($channelId && Apps::addon_app_installed(local_channel(), 'superblock')) {
		$blocked = false;
		$author = $b['item']['author_xchan'];
		$item = $b['item']['id'];

		if (App::$channel['channel_hash'] == $author)
			return;

		if(!empty(App::$data['superblock'])) {
			foreach(App::$data['superblock'] as $bloke) {
				if(link_compare($bloke,$author)) {
					$blocked = true;
					break;
				}
			}
		}

		if($blocked)
			return;

		$b['menu'][] = [
			'menu' => 'superblock',
			'title' => t('Block Completely'),
			'icon' => 'fw',
			'action' => "superblockAjax('block', '{$author}', {$item}); return false;",
			'href' => '#'
		];

		if (is_site_admin()) {
			$b['menu'][] = [
				'superblock_admin_block',
				'title' => t('Block from site'),
				'icon' => 'fw',
				'action' => "superblockAjax('siteblock', '{$author}', {$item}); return false;",
				'href' => '#',
			];
		}
	}
}
