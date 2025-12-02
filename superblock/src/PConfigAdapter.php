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
	public function getBlockedChannels(int $channelId): string|false {
		return PConfig::Get($channelId, 'system', 'blocked');
	}
}
