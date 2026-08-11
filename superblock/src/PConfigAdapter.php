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

	private const DEFAULT_SETTINGS = [
		'block_reshares' => true,
	];

	public function getSettings(int $channelId): array {
		return PConfig::Get($channelId, 'superblock', 'settings', self::DEFAULT_SETTINGS);
	}

	public function saveSettings(int $channelId, array $settings): void {
		$fullSettings = array_merge(self::DEFAULT_SETTINGS, $settings);
		PConfig::Set($channelId, 'superblock', 'settings', $fullSettings);
	}

	public function getBlockedChannels(int $channelId): mixed {
		return PConfig::Get($channelId, self::FAMILY, self::KEY);
	}

	public function saveBlockedChannels(int $channelId, array $blockList): void {
		PConfig::Set($channelId, self::FAMILY, self::KEY, $blockList);
	}
}
