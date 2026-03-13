<?php
/*
 * SPDX-FileCopyrightText: 2025 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Addons\Superblock;

use App;

// array_find is defined in PHP 8.4 or higher, so for earlier PHP versions we
// define it here.
if (!function_exists('array_find')) {

	function array_find(array $array, callable $callback): mixed {
		foreach ($array as $key => $entry) {
			if ($callback($entry, $key) === true) {
				return $entry;
			}
		}

		return null;
	}
}

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
	}

	public function loadJavaScript(): void {
		$security_token = get_form_security_token('superblock');

		if (empty(App::$page['htmlhead'])) {
			App::$page['htmlhead'] = '';
		}

		App::$page['htmlhead'] .= <<<JS
			<script>
			async function superblockAjax(action, author, item) {
				let response = await fetch("superblock", {
					method: "POST",
					headers: {
						"Content-Type": "application/json",
					},
					body: JSON.stringify({
						action: action,
						author: author,
						item: item,
						form_security_token: "{$security_token}",
					}),
				});
				body = await response.text();
			}
			</script>
			JS;
	}

	public function save(): void {
		$this->blockList->save();
	}

	public function blockChannel(string $channel): void {
		$this->blockList->add($channel);
	}

	public function unblockChannel(string $channel): void {
		$this->blockList->remove($channel);
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

	private function filterByProfileUrl(string $profile_url): bool {
		//
		// We should ideally not have to query the db directly here, but core
		// does not (yet) provide an API we can use to get the xchan entry from
		// the URL.
		//
		$result = q('select xchan_hash from xchan where xchan_url=\'%s\'', dbesc($profile_url));
		if ($result) {
			$xchan = array_find(
				$result[0],
				fn ($hash) => $this->blockList->match($hash));

			return $xchan !== null;
		}

		return false;
	}
}
