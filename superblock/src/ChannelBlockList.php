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
	private $channelId;
	private ConfigInterface $config;
	private bool $dirty;
	private array $list = [];

	/**
	 * Initialize with the blocklist for the given channel.
	 *
	 * The block list is read from the private config of the channel, and is
	 * expected to be a list of blocked channels separated by a comma.
	 *
	 * @param int $channelId	Numeric id of this block lists channel.
	 */
	function __construct(int $channelId, ConfigInterface $config = new PConfigAdapter()) {
		$this->channelId = $channelId;
		$this->config = $config;
		$this->loadBlockList();
		$this->dirty = false;
	}

	/**
	 * Add a channel to the block list.
	 *
	 * @param string $channel   The channel to block, either as a webbie, url
	 *                          or xchan hash.
	 */
	public function add(string $channel): void {
		$this->list[] = new ChannelBlock($channel);
		$this->dirty = true;
	}

	public function remove(string $channel): void {
		$this->list = array_filter($this->list, fn($ch) => $ch->hash !== $channel);
		$this->dirty = true;
	}

	public function getEntries(): array {
		return $this->list;
	}

	public function isModified(): bool {
		return $this->dirty;
	}

	/**
	 * Loads the block list from the configuration of the channel.
	 */
	private function loadBlockList(): void {
		$entries = $this->config->getBlockedChannels($this->channelId);
		$this->list = array_filter($entries, fn ($cb) => !empty($cb->hash));
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

		return !!array_find(
			$this->list,
		   	fn (ChannelBlock $cb) => $trimmed === $cb->hash);
	}

	/**
	 * Save the block list to persistent storage.
	 *
	 * This persists the block list using the `ConfigInterface` that was
	 * passed in the constructor.
	 */
	public function save(): void {
		$this->config->saveBlockedChannels($this->channelId, $this->list);
		$this->loadBlockList();
	}
}
