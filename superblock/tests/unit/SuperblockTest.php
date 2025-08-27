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

require_once __DIR__ . '/../../superblock.php';

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
	}

	#[After]
	public function cleanup(): void {
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
				$this->assertTrue($this->checkIfWallPostIsBLocked($author, $owner));
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

		superblock_stream_item($item);
		return isset($item['item']['blocked']) ? $item['item']['blocked'] : false;
	}

	private function checkIfWallPostIsBlocked(string $author, string $owner): bool {
		$item = [
			'uid' => $this->channel['channel_id'],
			'item_wall' => true,
			'author_xchan' => $author,
			'owner_xchan' => $owner,
		];

		superblock_item_store($item);
		return isset($item['cancel']) ? $item['cancel'] : false;
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
