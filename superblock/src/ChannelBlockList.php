<?php
/*
 * Implementation of a channel block list for the superblock addon.
 *
 * SPDX-FileCopyrightText: 2025 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Addons\Superblock;

/**
 * Block list implementation for a channel.
 */
class ChannelBlockList
{
	private $list = [];

	/**
	 * Initialize with the blocklist for the given channel.
	 *
	 * The block list is read from the private config of the channel, and is
	 * expected to be a list of blocked channels separated by a comma.
	 *
	 * @param int $channelId	Numeric id of this block lists channel.
	 */
	function __construct(int $channelId, ConfigInterface $config = new PConfigAdapter()) {
		$blockList = $config->getBlockedChannels($channelId);
		if (!empty($blockList)) {
			$this->list = array_map(
				fn (string $s): string => trim($s),
				explode(',', $blockList)
			);
		}
	}

	/**
	 * Check if a channel name matches the block list.
	 *
	 * @param string $n		The channel name to match against the list.
	 *
	 * @return bool		`true` if the channel matches, `false` otherwise.
	 */
	public function match(string $n): bool {
		$trimmed = trim($n);
		return !empty($trimmed) && in_array($trimmed, $this->list);
	}
}
