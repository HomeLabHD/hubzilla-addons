<?php
/*
 * SPDX-FileCopyrightText: 2025 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Addons\Superblock;

/**
 * Interface defining how to get config for a given channel id.
 *
 * Using an interface to define how to get the relevant configuration
 * allows us to have different implementations for different needs, and
 * to dynamically replace the implementation during execution.
 */
interface ConfigInterface
{
	/**
	 * Get the persisted block list from the configuration for a given channel.
	 *
	 * @param int $channelId
	 *		The id of the channel whose block to load.
	 *
	 * @return array<ChannelBlock>
	 *		An arry of ChannelBlock objects.
	 */
	public function getBlockedChannels(int $channelId): array;

	/**
	 * Save the block list in the configuration for a given channel.
	 *
	 * @param int $channelId
	 *		The id of the channel whose blocklist should be saved to the
	 *		configuration.
	 * @param array<ChannelBlock> $blockList
	 *		An array of channels to be blocked.
	 */
	public function saveBlockedChannels(int $channelId, array $blockList): void;
}
