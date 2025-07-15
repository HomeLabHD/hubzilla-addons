<?php

/**
 *   * Name: Wiki list
 *   * Description: Display a menu with links to all existing wikis
 */


namespace Zotlabs\Widget;

require_once('addon/wiki/Lib/NativeWiki.php');
use NativeWiki;

class Wiki_list {

	function widget($arr) {
		$channel = channelx_by_n(\App::$profile_uid);

		$wikis = NativeWiki::listwikis($channel, get_observer_hash());

		if($wikis) {
      $tpl = get_markup_template('wikilist_widget.tpl') ?: get_markup_template('wikilist_widget.tpl', 'addon/wiki');
			return replace_macros($tpl, [
				'$header' => t('Wikis'),
				'$channel' => $channel['channel_address'],
				'$wikis' => $wikis['wikis']
			]);
		}
		return '';
	}

}
