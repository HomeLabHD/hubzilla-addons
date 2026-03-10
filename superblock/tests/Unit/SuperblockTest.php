<?php
/**
 * Unit tests for the Superblock addon.
 *
 * SPDX-FileCopyrightText: 2025 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Addons\Superblock\Tests\Unit;

use App;
use PHPUnit\Framework\Attributes\{Before, After};
use Zotlabs\Addons\Superblock\Superblock;
use Zotlabs\Addons\Superblock\Tests\Helpers;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Config;
use Zotlabs\Lib\PConfig;
use Zotlabs\Tests\Unit\UnitTestCase;

class SuperblockTest extends UnitTestCase {

	use Helpers\PluginHelperTrait;

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
		$this->channel = $this->fixtures['channel'][1];
		$this->startSession($this->channel);
		$this->setup_channel();
	}

	public function testGetListOfBlockedChannels(): void {
		$plugin = Superblock::getInstance($this->channel['channel_id']);
		$list = $plugin->getBlockedChannels();

		$this->assertIsArray($list);
		$this->assertContains('blockeduser@somesite.test', $list);
		$this->assertContains('evil@othersite.test', $list);
		$this->assertContains('upyours@arse.test', $list);
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

	/*
	 * Blocked channels are visible through reshares
	 *
	 * Test to ensure that posts containing a reshare of a bost from a blocked
	 * channel is itself blocked.
	 *
	 * Issue: https://framagit.org/hubzilla/addons/-/issues/121
	 */
	public function testBlockingReshares(): void {
		$this->loadFixture(dirname(__DIR__) . '/fixtures/xchan.yml');

		$author = $this->fixtures['xchan'][1];
		$share_author = $this->fixtures['xchan'][2];

		$args = [
			'item' => [
				'author_xchan' => $author['xchan_hash'],
				'owner_xchan' => $author['xchan_hash'],
				'body' => <<<BODY
					[share author='{$share_author['xchan_name']}'
						profile='{$share_author['xchan_url']}'
						avatar='{$share_author['xchan_photo_s']}'
						link='https://hubzilla.ddev.site/item/685e81c5-c8c3-444a-bbe4-ae6659d0dd41'
						auth='true'
						posted='2026-03-08 21:35:49'
						message_id='https://hubzilla.ddev.site/item/685e81c5-c8c3-444a-bbe4-ae6659d0dd41'
						quote='true'
					]Hei og hå, her skal det reparares![/share]\r

					Pompel er på hugget!
					BODY
			]
		];

		$plugin = Superblock::getInstance($this->channel['channel_id']);
		$plugin->blockChannel($share_author['xchan_hash']);

		call_hooks('stream_item', $args);

		$this->assertTrue($args['item']['blocked']);
	}

	public function testFilterChildItems(): void {
		$children_with_blocked_authors = array_map(
			fn ($author) => [
				'owner_xchan' => self::NONBLOCKED_CHANNELS[1],
				'author_xchan' => $author,
				'blocked' => false,
			],
			self::BLOCKED_CHANNELS
		);

		$children_with_blocked_owners = array_map(
			fn ($owner) => [
				'owner_xchan' => $owner,
				'author_xchan' => self::NONBLOCKED_CHANNELS[1],
				'blocked' => false,
			],
			self::BLOCKED_CHANNELS
		);

		$unblocked_children = array_map(
			fn ($author) => [
				'owner_xchan' => $author,
				'author_xchan' => $author,
				'blocked' => false,
			],
			self::NONBLOCKED_CHANNELS
		);

		$args = [
			'item' => [
				'author_xchan' => self::NONBLOCKED_CHANNELS[0],
				'owner_xchan' => self::NONBLOCKED_CHANNELS[0],
				'children' => array_merge(
					$children_with_blocked_authors,
					$children_with_blocked_owners,
					$unblocked_children
				),
				'blocked' => false,
			],
		];

		call_hooks('stream_item', $args);

		// Verify that the main item was not blocked:
		$this->assertFalse($args['item']['blocked']);

		// Check that children from blocked authors or owners are marked
		// as blocked:
		foreach ($args['item']['children'] as $item) {
			$this->assertEquals($this->shouldBlockedItem($item), $item['blocked']);
		}
	}

	private function shouldBlockedItem(array $item): bool {
		return in_array($item['author_xchan'], self::BLOCKED_CHANNELS) ||
			in_array($item['owner_xchan'], self::BLOCKED_CHANNELS);
	}

	public function testAddingNewBlock(): void {
		$newBlock = 'anotherblockeduser@example.test';

		$this->assertFalse($this->checkIfItemIsBlocked($newBlock, $newBlock));

		$sb = Superblock::getInstance($this->channel['channel_id']);
		$sb->blockChannel($newBlock);
		$sb->save();

		$this->assertTrue($this->checkIfItemIsBlocked($newBlock, $newBlock));
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

	/**
	 * Superblock may be invoked for different channels in the same session.
	 * Make sure we don't use the same blocklist for different channels.
	 */
	public function testDontUseSameBlocklistForDifferentChannels(): void {
		$first = Superblock::getInstance($this->channel['channel_id']);
		$other = Superblock::getInstance($this->channel['channel_id'] + 1);

		$item = [
			'uid' => $this->channel['channel_id'],
			'item_wall' => true,
			'author_xchan' => self::BLOCKED_CHANNELS[0],
			'owner_xchan' => self::BLOCKED_CHANNELS[0],
		];

		$other->cancelItem($item);
		$this->assertArrayNotHasKey('cancel', $item);

		$first->cancelItem($item);

		$this->assertArrayHasKey('cancel', $item);
		$this->assertTrue($item['cancel']);
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

	private function checkIfNotificationIsBlocked(
		string $author,
		string $parent_author = '',
		string $parent_owner = ''
	): bool {
		$item = [
			'uid' => $this->channel['channel_id'],
			'sender_hash' => $author,
		];

		$item['parent_item'] = null;

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

	#[BackupStaticProperties(App::class)]
	public function testAddingPhotoMenuForNonBlockedChannels(): void {
		App::$data['superblock'] = self::BLOCKED_CHANNELS;
		App::$channel['channel_hash'] = 'someone else';
		App::$account = [ 'account_roles' => 0 ];

		foreach (self::NONBLOCKED_CHANNELS as $author) {
			$args = [
				'item' => [ 'id' => 42, 'author_xchan' => $author ],
				'menu' => [],
			];

			call_hooks('thread_author_menu', $args);

			$this->assertArrayHasKey('menu', $args['menu'][0]);
			$this->assertEquals('superblock', $args['menu'][0]['menu']);

			$this->assertArrayHasKey('action', $args['menu'][0]);
			$this->assertStringContainsString(
				"superblockAjax('block', '{$author}', 42);",
				$args['menu'][0]['action']
			);
		}
	}

	#[BackupStaticProperties(App::class)]
	public function testDontAddPhotoMenuForBlockedChannels(): void {
		App::$data['superblock'] = self::BLOCKED_CHANNELS;
		App::$channel['channel_hash'] = 'someone else';

		foreach (self::BLOCKED_CHANNELS as $author) {
			$args = [
				'item' => [ 'id' => 42, 'author_xchan' => $author ],
				'menu' => [],
			];

			call_hooks('thread_author_menu', $args);

			$this->assertEmpty($args['menu']);
		}
	}

	#[BackupStaticProperties(App::class)]
	public function testDontAddPhotoMenuForSelf(): void {
		App::$data['superblock'] = self::BLOCKED_CHANNELS;
		App::$channel['channel_hash'] = 'someone else';

		foreach (self::NONBLOCKED_CHANNELS as $author) {
			$args = [
				'item' => [ 'id' => 42, 'author_xchan' => $author ],
				'menu' => [],
			];

			App::$channel['channel_hash'] = $author;

			call_hooks('thread_author_menu', $args);

			$this->assertEmpty($args['menu']);
		}
	}

	/**
	 * Install the addon and set the blocklist.
	 */
	private function setup_channel(): void {
		$this->installPluginApp($this->channel);

		PConfig::Set(
			$this->channel['channel_id'],
			'system',
			'blocked',
			implode(',', self::BLOCKED_CHANNELS)
		);
	}
}
