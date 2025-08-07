<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\PermissionDescription;

require_once('include/channel.php');
require_once('include/conversation.php');
require_once('include/acl_selectors.php');

/**
 * @brief Provides the Cards module.
 *
 */
class Cards extends Controller {

	public function init() {

		if(argc() > 1)
			$which = argv(1);
		else
			return;

		profile_load($which);
	}

	/**
	 * {@inheritDoc}
	 * @see \\Zotlabs\\Web\\Controller::get()
	 *
	 * @return string Parsed HTML from template 'cards.tpl'
	 */
	public function get($update = 0, $load = false) {

		if(observer_prohibited(true)) {
			return login();
		}

		if(! App::$profile) {
			notice( t('Requested profile is not available.') . EOL );
			App::$error = 404;
			return;
		}

		if(! Apps::addon_app_installed(App::$profile_uid, 'cards')) {
			//Do not display any associated widgets at this point
			App::$pdl = EMPTY_STR;

			if (App::$profile_uid !== local_channel()) {
				return EMPTY_STR;
			}

			$papp = Apps::get_papp('Cards');
			return Apps::app_render($papp, 'module');
		}

		nav_set_selected('Cards');

		head_add_link([
			'rel'   => 'alternate',
			'type'  => 'application/json+oembed',
			'href'  => z_root() . '/oep?f=&url=' . urlencode(z_root() . '/' . App::$query_string),
			'title' => 'oembed'
		]);


		$category = (($_REQUEST['cat']) ? escape_tags(trim($_REQUEST['cat'])) : '');

		if($category) {
			$sql_extra2 .= protect_sprintf(term_item_parent_query(App::$profile['profile_uid'], 'item', $category, TERM_CATEGORY));
		}


		$which = argv(1);

		$selected_card = ((argc() > 2) ? argv(2) : '');

		$_SESSION['return_url'] = App::$query_string;

		$uid      = local_channel();
		$owner    = App::$profile_uid;
		$observer = App::get_observer();

		$ob_hash = (($observer) ? $observer['xchan_hash'] : '');

		if(! perm_is_allowed($owner, $ob_hash, 'view_pages')) {
			notice( t('Permission denied.') . EOL);
			return;
		}

		$is_owner = ($uid && $uid == $owner);

		$channel = channelx_by_n($owner);

		if($channel) {
			$channel_acl = [
				'allow_cid' => $channel['channel_allow_cid'],
				'allow_gid' => $channel['channel_allow_gid'],
				'deny_cid'  => $channel['channel_deny_cid'],
				'deny_gid'  => $channel['channel_deny_gid']
			];
		}
		else {
			$channel_acl = [ 'allow_cid' => '', 'allow_gid' => '', 'deny_cid' => '', 'deny_gid' => '' ];
		}


		if(perm_is_allowed($owner, $ob_hash, 'write_pages')) {

			$x = [
				'webpage'           => ITEM_TYPE_CARD,
				'is_owner'          => true,
				'content_label'     => t('Add Card'),
				'button'            => t('Save'),
				'nickname'          => $channel['channel_address'],
				'lockstate'         => (($channel['channel_allow_cid'] || $channel['channel_allow_gid']
					|| $channel['channel_deny_cid'] || $channel['channel_deny_gid']) ? 'lock' : 'unlock'),
				'acl'               => (($is_owner) ? populate_acl($channel_acl, false,
					PermissionDescription::fromGlobalPermission('view_pages')) : ''),
				'permissions'       => $channel_acl,
				'showacl'           => (($is_owner) ? true : false),
				'visitor'           => true,
				'hide_location'     => false,
				'hide_voting'       => false,
				'profile_uid'       => intval($owner),
				'mimetype'          => 'text/bbcode',
				'mimeselect'        => false,
				'layoutselect'      => false,
				'expanded'          => false,
				'novoting'          => false,
				'catsenabled'       => feature_enabled($owner, 'categories'),
				'bbco_autocomplete' => 'bbcode',
				'bbcode'            => true
			];

			if($_REQUEST['title'])
				$x['title'] = $_REQUEST['title'];
			if($_REQUEST['body'])
				$x['body'] = $_REQUEST['body'];

			$editor = status_editor($x, false, 'Cards');
		}
		else {
			$editor = '';
		}


		$itemspage = get_pconfig(local_channel(),'system','itemspage');
		App::set_pager_itemspage(((intval($itemspage)) ? $itemspage : 10));
		$pager_sql = sprintf(" LIMIT %d OFFSET %d ", intval(App::$pager['itemspage']), intval(App::$pager['start']));

		$permission_sql = item_permissions_sql($owner);
		$sql_item = '';

		if($selected_card) {
			$r = q("select * from iconfig where iconfig.cat = 'system' and iconfig.k = 'CARD' and iconfig.v = '%s' limit 1",
				dbesc($selected_card)
			);
			if($r) {
				$sql_item = "and item.id = " . intval($r[0]['iid']) . " ";
			}
		}

		$mode = 'articles';
		$page_mode = 'traditional';

		$blog_mode = get_pconfig(App::$profile['profile_uid'], 'system', 'cards_list_mode') && !$selected_card;
		if ($blog_mode) {
			$page_mode = 'list';
		}

		$item_normal = item_normal(type: ITEM_TYPE_CARD);

		$r = q("select id as item_id from item
			where uid = %d and item_type = %d and item_thread_top = 1 and verb = 'Create'
			$permission_sql $sql_extra2 $sql_item $item_normal order by item.created desc $pager_sql",
			intval($owner),
			intval(ITEM_TYPE_CARD)
		);

		$items = [];

		if($r) {
			$pager_total = count($r);
			$items = items_by_parent_ids($r, permission_sql: $permission_sql, blog_mode: $blog_mode, type: ITEM_TYPE_CARD);

			xchan_query($items);
			$items = fetch_post_tags($items, true);
			$items = conv_sort($items, 'updated');
		}

		$content = conversation($items, $mode, false, $page_mode);

		$o = replace_macros(get_markup_template('cards.tpl', 'addon/cards'), [
			'$title' => t('Cards'),
			'$editor' => $editor,
			'$content' => $content,
			'$pager' => alt_pager($pager_total)
		]);

		return $o;
	}

}
