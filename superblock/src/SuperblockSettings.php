<?php
// SPDX-FileCopyrightText: 2026 The Hubzilla Community
//
// SPDX-License-Identifier: MIT

namespace Zotlabs\Addons\Superblock;

class SuperblockSettings
{
	private array $settings;

	public function __construct(
		private int $channelId,
		private ConfigInterface $config
	)
	{
		$this->settings = $this->config->getSettings($channelId);
	}

	public function blockIncoming(): bool
	{
		return $this->settings['block_incoming'] ?? true;
	}

	public function blockReshares(): bool
	{
		return $this->settings['block_reshares'] ?? true;
	}

	public function setBlockIncoming(bool $value): void
	{
		$this->settings['block_incoming'] = $value;
	}

	public function setBlockReshares(bool $value): void
	{
		$this->settings['block_reshares'] = $value;
	}

	public function save(): void
	{
		$this->config->saveSettings($this->channelId, $this->settings);
	}
}
