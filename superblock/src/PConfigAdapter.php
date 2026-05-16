<?php
/*
 * SPDX-FileCopyrightText: 2025 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Addons\Superblock;

use Zotlabs\Lib\PConfig;

/**
 * Adapter implementing the ConfigInterface for the PConfig class.
 *
 * @see ConfigInterface
 * @see Zotlabs::Lib::PConfig
 */
class PConfigAdapter implements ConfigInterface
{
	private const FAMILY = 'system';
	private const KEY = 'blocked';

	public function getBlockedChannels(int $channelId): mixed {
		return PConfig::Get($channelId, self::FAMILY, self::KEY);
	}

	public function saveBlockedChannels(int $channelId, array $blockList): void {
		PConfig::Set($channelId, self::FAMILY, self::KEY, $blockList);
	}
}
