<?php
/*
 * SPDX-FileCopyrightText: 2025 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Addons\Superblock;

class Superblock
{
	private $channelId;
	private BlockList $blockList;

	private static $instance = null;

	public static function getInstance(int $channelId): self {
		if (self::$instance === null) {
			self::$instance = new self($channelId);
		}
		return self::$instance;
	}

	private function __construct(int $channelId) {
		$this->channelId = $channelId;
		$this->blockList = new BlockList($channelId);
	}

	public function filterStreamItem(array &$item): void {
		if ($this->filterItem($item)) {
			$item['blocked'] = true;
		}

		if (!empty($item['children'])) {
			foreach ($item['children'] as &$child) {
				if ($this->filterItem($child)) {
					$child['blocked'] = true;
				}
			}
		}
	}

	public function filterMailPost(array &$item): void {
		if($this->blockList->match($item['from_xchan'])) {
			$item['cancel'] = true;
		}
	}

	public function filterEnotifyStore(array &$item): void {
		if ($this->blockList->match($item['sender_hash'])
			|| (is_array($item['parent_item']) && $this->filterItem($item['parent_item'])))
		{
			$item['abort'] = true;
			return;
		}
	}

	public function filterEnotifyFormat(array &$item): void {
		if ($this->blockList->match($item['hash'])) {
			$item['display'] = false;
		}
	}

	public function cancelItem(array &$item): void {
		if ($this->filterItem($item)) {
			$item['cancel'] = true;
		}
	}

	public function filterDirectoryItem(array &$item): void {
		if($this->blockList->match($item['entry']['hash'])) {
			unset($item['entry']);
		}
	}

	public function filterItem(array &$item): bool {
		return (isset($item['author_xchan']) && $this->blockList->match($item['author_xchan']))
			|| (isset($item['owner_xchan']) && $this->blockList->match($item['owner_xchan']));
	}
}
