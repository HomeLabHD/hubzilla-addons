<?php
/*
 * SPDX-FileCopyrightText: 2025 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Addons\Superblock;

use App;
use DateTimeImmutable;

/**
 * Superblock addon class.
 *
 * This class implements most of the logic used in the Superblock addon.
 *
 * It is implemented somewhat like a singleton, except it will instantiate a
 * new instance of itself for each channel it's going to serve. This is needed
 * because each channel needs to have their own block list.
 *
 * To get the instance for a given channel, use the `getInstance()` static
 * function:
 *
 * ```php
 *    $channelId = local_channel();
 *
 *    if ($channelId && Apps::addon_app_installed($channelId, 'superblock')) {
 *        $plugin = Superblock::getInstance($channelId);
 *        $plugin->filterStreamItem($b['item']);
 *    }
 * ```
 */
class Superblock
{
	/**
	 * A form security token for this session.
	 *
	 * Generated at the start of the session, and passed to relevant
	 * javascript functions as needed on rendering.
	 */
	public readonly string $security_token;

	/**
	 * Static property to hold instances of the Superblock class.
	 */
	private static array $instance = [];

	/**
	 * The actual block list for this instance.
	 */
	private ChannelBlockList $blockList;

	/**
	 * Return the Superblock instance for the given channelId.
	 *
	 * @param int $channelId    The id of the channel for this Superblock instance.
	 *
	 * @return A Superblock instance to manage blocks for the given channel.
	 */
	public static function getInstance(int $channelId): self {
		if (empty(self::$instance[$channelId])) {
			self::$instance[$channelId] = new self($channelId);
		}
		return self::$instance[$channelId];
	}

	/*
	 * Private constructor to prevent instantiation by other classes.
	 *
	 * @param int $channelId    The is of the channel for this Superblock instance.
	 */
	private function __construct(int $channelId) {
		$this->blockList = new ChannelBlockList($channelId);
		$this->security_token = get_form_security_token('superblock');
	}

	public function loadJavaScript(): void {
		head_add_js('/addon/superblock/view/js/superblock.js');
	}

	public function loadStyleSheet(): void {
		head_add_css('/addon/superblock/view/css/superblock.css');
	}

	public function save(): void {
		$this->blockList->save();
	}

	public function blockChannel(string $channel, ?DateTimeImmutable $until = null): void {
		$this->blockList->add([
			'hash' => $channel,
			'until' => $until,
		]);
	}

	public function unblockChannel(string $channel): void {
		$this->blockList->remove($channel);
	}

	public function isChannelBlocked(string $channel): bool {
		return $this->blockList->match($channel);
	}

	public function configChanged(): bool {
		return $this->blockList->isModified();
	}

	public function getBlockedChannels(): array {
		return $this->blockList->getEntries();
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
		if (!empty($item['hash']) && $this->blockList->match($item['hash'])) {
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

	public function filterItem(array $item): bool {
		// Block item if author, or owner is blocked
		if ((isset($item['author_xchan']) && $this->blockList->match($item['author_xchan']))
			|| (isset($item['owner_xchan']) && $this->blockList->match($item['owner_xchan'])))
		{
			return true;
		}

		if (!empty($item['body'])) {
			//
			// If the post contains a reshare of a post by a channel we have blocked,
			// we also want to block this post.
			//
			$num_shares = preg_match_all('/\[share\s+([^]]*)\]/s', $item['body'], $matches);
			if ($num_shares > 0) {
				//
				// The first entry in the array is an array of the full matches.
				// We're not interested in them, so we only check the second entry
				// which contains an array of the captures from the regexp above.
				//
				return array_find($matches[1], fn ($m) => $this->filterShare($m)) !== null;
			}
		}

		return false;
	}

	private function filterShare(string $attrs): bool {
		if (preg_match("/profile='([^']*)'/s", $attrs, $match) > 0) {
			$profile_url = $match[1];

			if (!empty($profile_url)) {
				return $this->filterByProfileUrl($profile_url);
			}
		}

		return false;
	}

	/**
	 * Check whether we should filter this profile URL.
	 *
	 * This function will return true if the given profile URL matches
	 * a blocked profile either by xchan hash or xchan URL.
	 *
	 * @param string $profile_url
	 *		The URL to check.
	 *
	 * @return bool
	 *		True if the profile URL should be filtered, false otherwise.
	 */
	public function filterByProfileUrl(string $profile_url): bool {
		//
		// First check if the profile URL matches the xchan hash
		//
		if ($this->blockList->match($profile_url)) {
			return true;
		}

		//
		// First check if the profile URL matches the xchan hash
		//
		if ($this->blockList->match($profile_url)) {
			return true;
		}

		//
		// We should ideally not have to query the db directly here, but core
		// does not (yet) provide an API we can use to get the xchan entry from
		// the URL.
		//
		$result = q('select xchan_hash from xchan where xchan_url=\'%s\'', dbesc($profile_url));
		if (!empty($result)) {
			$xchan = array_find(
				$result[0],
				fn ($hash) => $this->blockList->match($hash));

			return $xchan !== null;
		}

		return false;
	}
}
