<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\LDSignatures;
use Zotlabs\Web\Controller;
use Zotlabs\Web\HTTPSig;

class Following extends Controller {

	function init() {

		if(observer_prohibited(true)) {
			http_status_exit(403, 'Forbidden');
		}

		if(argc() < 2) {
			http_status_exit(404, 'Not found');
		}

		$channel = channelx_by_nick(argv(1));
		if(! $channel) {
			http_status_exit(404, 'Not found');
		}

		$observer_hash = get_observer_hash();

		if(! perm_is_allowed($channel['channel_id'],$observer_hash,'view_contacts')) {
			http_status_exit(403, 'Forbidden');
		}

		$r = q("select * from abconfig left join xchan on abconfig.xchan = xchan_hash where abconfig.chan = %d and abconfig.cat = 'my_perms' and abconfig.k = 'send_stream' and abconfig.v = '1'",
			intval($channel['channel_id'])
		);

		if(ActivityStreams::is_as_request()) {
			as_return_and_die(Activity::encode_follow_collection($r, App::$query_string, 'OrderedCollection'), $channel);
		}

	}

}
