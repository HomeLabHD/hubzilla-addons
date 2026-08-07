<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Activity;
use Zotlabs\Web\Controller;
use Zotlabs\Web\HTTPSig;

class Followers extends Controller {

	function init() {
		if (!ActivityStreams::is_as_request()) {
			http_status_exit(400, 'Bad request');
		}

		if (observer_prohibited(true)) {
			http_status_exit(403, 'Forbidden');
		}

		if (argc() < 2) {
			http_status_exit(404, 'Not found');
		}

		$channel = channelx_by_nick(argv(1));
		if (!$channel) {
			http_status_exit(404, 'Not found');
		}

		$sigdata = HTTPSig::verify(EMPTY_STR);
		if ($sigdata['portable_id'] && $sigdata['header_valid']) {
			$portable_id = $sigdata['portable_id'];
			if (!check_channelallowed($portable_id)) {
				http_status_exit(403, 'Permission denied');
			}
			if (!check_siteallowed($sigdata['signer'])) {
				http_status_exit(403, 'Permission denied');
			}
			observer_auth($portable_id);
		}

		$observer_hash = get_observer_hash();
		$has_permission = perm_is_allowed($channel['channel_id'], $observer_hash, 'view_contacts');

		$sql_extra = (($observer_hash !== $channel['channel_hash']) ? " and abook_hidden = 0 and xchan_hidden = 0 " : '');

		if (!$has_permission) {
			if ($observer_hash) {
				$sql_extra .= " AND xchan_hash = '" . dbesc($observer_hash) . "' ";
			}
			else {
				http_status_exit(403, 'Permission denied');
			}
		}

		$count = q(
			"select count(xchan_hash) as total from xchan
			left join abconfig on abconfig.xchan = xchan_hash
			left join abook on abook_xchan = xchan_hash
			where abook_channel = %d and abconfig.chan = %d and abconfig.cat = 'their_perms'
			and abconfig.k = 'send_stream' and abconfig.v = '1' and xchan_hash != '%s' and xchan_orphan = 0 and xchan_deleted = 0
			and abook_pending = 0 and abook_self = 0 $sql_extra ",
			intval($channel['channel_id']),
			intval($channel['channel_id']),
			dbesc($channel['channel_hash'])
		);

		$total = intval($count[0]['total']);

		if ($total) {
			App::set_pager_total($total);
			App::set_pager_itemspage(30);
		}

		if (!$total && !$has_permission) {
			http_status_exit(403, 'Permission denied');
		}

		$ret = [];

		if (empty($_GET['page']) && $total > App::$pager['itemspage']) {
			$ret = Activity::paged_collection_init($total, App::$query_string, 'Collection', 'actor');
		} else {
			$pager_sql = sprintf(" LIMIT %d OFFSET %d ", intval(App::$pager['itemspage']), intval(App::$pager['start']));

			$r = q(
				"select * from xchan left join abconfig on abconfig.xchan = xchan_hash
				left join abook on abook_xchan = xchan_hash
				where abook_channel = %d and abconfig.chan = %d and abconfig.cat = 'their_perms'
				and abconfig.k = 'send_stream' and abconfig.v = '1'
				and xchan_orphan = 0 and xchan_deleted = 0 and abook_pending = 0
				and abook_self = 0 $sql_extra $pager_sql",
				intval($channel['channel_id']),
				intval($channel['channel_id']),
				dbesc($channel['channel_hash'])
			);

			if ($r) {
				$ret = Activity::encode_follow_collection($r, App::$query_string, 'Collection', $total);
			}
		}

		as_return_and_die($ret, $channel);
	}

}
