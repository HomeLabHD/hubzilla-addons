<?php
/*
 * Implementation of the Superblock\BlockList class.
 *
 * SPDX-FileCopyrightText: 2025 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Addons\Superblock;

class BlockList {

	private $list = [];

	function __construct($channel_id) {
		$cnf = get_pconfig($channel_id,'system','blocked');
		if(! $cnf)
			return;
		$this->list = explode(',',$cnf);
	}

	function get_list() {
		return $this->list;
	}

	function match($n) {
		if(! $this->list)
			return false;

		//foreach($this->list as $l) {
		//	if(trim($n) === trim($l)) {
		//		return true;
		//	}
		//}

		if (in_array($n, $this->list)) {
			return true;
		}

		return false;
	}

}
