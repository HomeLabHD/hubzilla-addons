<?php
/**
 * Unit tests for the Superblock addon.
 *
 * SPDX-FileCopyrightText: 2025 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Addons\Superblock\Tests;

use PHPUnit\Framework\Attributes\{Before, After};
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Config;
use Zotlabs\Lib\PConfig;
use Zotlabs\Tests\Unit\UnitTestCase;

class SuperblockTest extends UnitTestCase {
	private array $channel = [];

	private const BLOCKED_CHANNELS = [
		'blockeduser@somesite.test',
		'evil@othersite.test',
		'upyours@arse.test',
	];

	private const NONBLOCKED_CHANNELS = [
		'grandma@somewhere.test',
		'brandon@bruffalo.test',
	];

	#[Before]
	public function prepare_test(): void {
		create_sys_channel();
		$this->create_channel();
		$this->start_session();
		$this->setup_channel();

		install_plugin('superblock');
		load_hooks();
	}

	#[After]
	public function cleanup(): void {
		unload_plugin('superblock');

		session_abort();
		$_SESSION = [];
	}

	public function testItemFromBlockedUserShouldBeBlocked(): void {
		//
		// We could technically use a dataprovider to iterate through the tests vectors,
		// but since there's a bit of setup before we can run the test itself, it's
		// faster to just iterate through them in one test function.
		//
		// This works since each result don't depend on anything else than the initial
		// setup which is the same regardless of vector.
		//

		foreach (self::BLOCKED_CHANNELS as $author) {
			$this->assertTrue($this->checkIfItemIsBlocked($author, $author));

			// Should block even if the owner is not blocked
			foreach (self::NONBLOCKED_CHANNELS as $owner) {
				$this->assertTrue($this->checkIfItemIsBLocked($author, $owner));
			}
		}

		foreach (self::NONBLOCKED_CHANNELS as $author) {
			$this->assertFalse($this->checkIfItemIsBlocked($author, $author));

			// Should block even if the author is not blocked
			foreach (self::BLOCKED_CHANNELS as $owner) {
				$this->assertTrue($this->checkIfItemIsBlocked($author, $owner));
			}
		}
	}

	/**
	 * Helper function to make the check whether items will be blocked or not
	 * given `$author` and `$owner`.
	 *
	 * @param string $author	The author xchan of the item.
	 * @param string $owner		The owner xchan of the item.
	 *
	 * @return bool `true` if item is blocked, `false` otherwise.
	 */
	private function checkIfItemIsBlocked(string $author, string $owner): bool {
		$item = [
			'item' => [
				'author_xchan' => $author,
				'owner_xchan' => $owner,
			],
		];

		//superblock_stream_item($item);
		call_hooks('stream_item', $item);
		return isset($item['item']['blocked']) ? $item['item']['blocked'] : false;
	}


	public function testWallPostsFromBlockedUsersShouldBeBlocked(): void {
		//
		// We could technically use a dataprovider to iterate through the tests vectors,
		// but since there's a bit of setup before we can run the test itself, it's
		// faster to just iterate through them in one test function.
		//
		// This works since each result don't depend on anything else than the initial
		// setup which is the same regardless of vector.
		//

		foreach (self::BLOCKED_CHANNELS as $author) {
			$this->assertTrue($this->checkIfWallPostIsBlocked($author, $author));

			// Should block even if the owner is not blocked
			foreach (self::NONBLOCKED_CHANNELS as $owner) {
				$this->assertTrue($this->checkIfWallPostIsBlocked($author, $owner));
			}
		}

		foreach (self::NONBLOCKED_CHANNELS as $author) {
			$this->assertFalse($this->checkIfWallPostIsBlocked($author, $author));

			// Should block even if the author is not blocked
			foreach (self::BLOCKED_CHANNELS as $owner) {
				$this->assertTrue($this->checkIfWallPostIsBlocked($author, $owner));
			}
		}
	}

	private function checkIfWallPostIsBlocked(string $author, string $owner): bool {
		$item = [
			'uid' => $this->channel['channel_id'],
			'item_wall' => true,
			'author_xchan' => $author,
			'owner_xchan' => $owner,
		];

		call_hooks('item_store', $item);
		return isset($item['cancel']) ? $item['cancel'] : false;
	}

	public function testPMFromBlockedUsersShouldBeBlocked(): void {
		foreach (self::BLOCKED_CHANNELS as $sender) {
			$this->assertTrue($this->checkIfPMIsBlocked($sender));
		}

		foreach (self::NONBLOCKED_CHANNELS as $sender) {
			$this->assertFalse($this->checkIfPMIsBlocked($sender));
		}
	}

	private function checkIfPMIsBlocked(string $sender): bool {
		$item = [
			'channel_id' => $this->channel['channel_id'],
			'from_xchan' => $sender,
		];

		call_hooks('post_mail', $item);
		return isset($item['cancel']) ? $item['cancel'] : false;
	}


	public function testNotificationsFromBlockedUsersShouldBeBlocked(): void {
		foreach (self::BLOCKED_CHANNELS as $sender) {
			$this->assertTrue($this->checkIfNotificationIsBlocked($sender));
		}

		foreach (self::NONBLOCKED_CHANNELS as $sender) {
			$this->assertFalse($this->checkIfNotificationIsBlocked($sender));

			foreach (self::BLOCKED_CHANNELS as $author) {
				// Should block if parent author is blocked
				$this->assertTrue($this->checkIfNotificationIsBlocked($sender, $author));
				$this->assertTrue($this->checkIfNotificationIsBlocked($sender, $author, $sender));

				// Should block is parent owner is blocked
				$this->assertTrue($this->checkIfNotificationIsBlocked($sender, '', $author));
				$this->assertTrue($this->checkIfNotificationIsBlocked($sender, $sender, $author));
			}
		}
	}

	private function checkIfNotificationIsBlocked(string $author, string $parent_author = '', string $parent_owner = ''): bool {
		$item = [
			'uid' => $this->channel['channel_id'],
			'sender_hash' => $author,
		];

		if (!empty($parent_author) || !empty($parent_owner)) {
			$item['parent_item'] = [
				'author_xchan' => $parent_author,
				'owner_xchan' => $parent_owner,
			];
		}

		call_hooks('enotify_store', $item);
		return isset($item['abort']) ? $item['abort'] : false;
	}

	public function testEnotifyFormatDontDisplayBlockedItems(): void {
		foreach (self::BLOCKED_CHANNELS as $author) {
			$this->assertTrue($this->checkIfEnotifyIsBlocked($author));
		}

		foreach (self::NONBLOCKED_CHANNELS as $author) {
			$this->assertFalse($this->checkIfEnotifyIsBlocked($author));
		}
	}

	private function checkIfEnotifyIsBlocked(string $author): bool {
		$item = [
			'uid' => $this->channel['channel_id'],
			'hash' => $author,
			'display' => true,
		];

		call_hooks('enotify_format', $item);

		// While the other hooks set a value to `true` to signal that the item
		// should be blocked, the enotify_format hook sets the value to false.
		//
		// We invert the result here to keep the tests more consistent.
		return !$item['display'];
	}

	public function testMessagesWidgetIsBlocked(): void {
		foreach (self::BLOCKED_CHANNELS as $author) {
			$this->assertTrue($this->checkIfMessagesWidgetIsBlocked($author, $author));

			// Should block even if the owner is not blocked
			foreach (self::NONBLOCKED_CHANNELS as $owner) {
				$this->assertTrue($this->checkIfMessagesWidgetIsBlocked($author, $owner));
			}
		}

		foreach (self::NONBLOCKED_CHANNELS as $author) {
			$this->assertFalse($this->checkIfMessagesWidgetIsBlocked($author, $author));

			// Should block even if the author is not blocked
			foreach (self::BLOCKED_CHANNELS as $owner) {
				$this->assertTrue($this->checkIfMessagesWidgetIsBlocked($author, $owner));
			}
		}
	}

	private function checkIfMessagesWidgetIsBlocked(string $author, string $owner): bool {
		$item = [
			'uid' => $this->channel['channel_id'],
			'owner_xchan' => $owner,
			'author_xchan' => $author,
			'cancel' => false,
		];

		call_hooks('messages_widget', $item);
		return $item['cancel'];
	}

	public function testApiFormatItemsDiscardsItemsFromBlockedChannels(): void {
		$args = [
			'api_user' => $this->channel['channel_id'],
			'items' => [
				[
					'owner_xchan' => self::BLOCKED_CHANNELS[0],
					'author_xchan' => self::NONBLOCKED_CHANNELS[0],
					'content' => 'Should not be seen',
				],
				[
					'owner_xchan' => self::NONBLOCKED_CHANNELS[1],
					'author_xchan' => self::NONBLOCKED_CHANNELS[0],
					'content' => 'This is fine',
				],
				[
					'owner_xchan' => self::NONBLOCKED_CHANNELS[1],
					'author_xchan' => self::BLOCKED_CHANNELS[1],
					'content' => 'Should not be seen',
				],
			],
		];

		call_hooks('api_format_items', $args);
		$this->assertEquals(1, count($args['items']));
		$this->assertEquals('This is fine', $args['items'][0]['content']);
	}

	public function testBlockedChannelsShouldNotShowInDirectory(): void {
		foreach (self::BLOCKED_CHANNELS as $channel) {
			$data['entry'] = [ 'hash' => $channel ];
			call_hooks('directory_item', $data);
			$this->assertFalse(isset($data['entry']));
		}
	}

	public function testNonBlockedChannelsShouldShowInDirectory(): void {
		foreach (self::NONBLOCKED_CHANNELS as $channel) {
			$data['entry'] = [ 'hash' => $channel ];
			call_hooks('directory_item', $data);
			$this->assertTrue(isset($data['entry']));
		}
	}

	public function testFilteringActivityWidget(): void {
		$args = [
			'entries' => array_map(
				fn ($ch) => ['author_xchan' => $ch],
				array_merge(self::BLOCKED_CHANNELS, self::NONBLOCKED_CHANNELS)
			)
		];

		call_hooks('activity_widget', $args);

		$this->assertCount(count(self::NONBLOCKED_CHANNELS), $args['entries']);

		foreach (self::NONBLOCKED_CHANNELS as $ch) {
			$this->assertContains(['author_xchan' => $ch], $args['entries']);
		}

		foreach (self::BLOCKED_CHANNELS as $ch) {
			$this->assertNotContains(
				['author_xchan' => $ch],
				$args['entries'],
				"expected {$ch} to be blocked"
			);
		}
	}

	/**
	 * Create the channel that will run the tests.
	 */
	private function create_channel(): void {
		if (!empty($this->channel)) {
			return;
		}

		$result = create_identity([
			'account_id' => $this->fixtures['account'][0]['account_id'],
			'nickname' => 'sbtest',
			'name' => 'Superblock Test Channel',
		]);

		$this->assertTrue($result['success']);
		$this->channel = $result['channel'];
	}

	private function start_session(): void {
		session_start();

		$_SESSION['authenticated'] = true;
		$_SESSION['uid'] = $this->channel['channel_id'];
	}

	/**
	 * Install the addon and set the blocklist.
	 */
	private function setup_channel(): void {
		$app = Apps::parse_app_description(__DIR__ . '/../../superblock.apd', false, false);
		$app['plugin'] = 'superblock';
		Apps::app_install(0, $app);
		Apps::app_install($this->channel['channel_id'], $app);

		PConfig::Set(
			$this->channel['channel_id'],
			'system',
			'blocked',
			implode(',', self::BLOCKED_CHANNELS)
		);
	}
}
