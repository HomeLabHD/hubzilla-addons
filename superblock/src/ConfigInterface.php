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
	 * @param int $channelId    The id of the channel to get the persisted
	 *                          block list for.
	 *
	 * @return string|false		Either a string containing the blocked channel names
	 *                          separated by commas, or false if the configuration
	 *                          don't exist.
	 */
	public function getBlockedChannels(int $channelId): string|false;
}
