<?php
/*
 * Test cases for the ChannelBlockList class.
 *
 * SPDX-FileCopyrightText: 2025 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Addons\Superblock\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zotlabs\Addons\Superblock\ChannelBlockList;
use Zotlabs\Addons\Superblock\ConfigInterface;

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

class ChannelBlockListTest extends TestCase
{
	#[DataProvider('blockListProvider')]
	public function testChannelBlockList(string $hash, string|false $blocked, bool $result): void {

		//
		// Stub implementation of the ConfigInterface, so we can inject into
		// the class under test to replace the dependency on PConfig.
		//
		$testConfig = new class($blocked) implements ConfigInterface {
			public function __construct(private string $blocked) {}
			public function getBlockedChannels(int $channelId): array {
				return $channelId
					? array_map(fn (string $s): string => trim($s), explode(',', $this->blocked))
					: [];
			}
			public function saveBlockedChannels(int $channelId, array $blockList): void {
			}
		};

		$blockList = new ChannelBlockList(42, $testConfig);

		$this->assertEquals($result, $blockList->match($hash));
	}

	/*
	 * DataProvider for testChannelBlockList
	 *
	 * Returns an array of [$hash, $blocked, $result] args for the
	 * testChannelBlockList function.
	 */
	public static function blockListProvider(): array {
		return [
			[
				'blocked@example.test',
				false,
				false,
			],
			[
				'blocked@example.test',
				'one@example.test,blocked@example.test,two@example.test,https://example.test/users/bot',
				true,
			],
			[
				'blocked@example.test',
				'one@example.test,two@example.test,https://example.test/users/bot',
				false,
			],
			[
				'blocked@example.test',
				',,,,',
				false,
			],
			[
				'blocked@example.test',
				'one@example.test, blocked@example.test ,two@example.test,https://example.test/users/bot',
				true,
			],
			[
				' blocked@example.test ',
				'one@example.test,blocked@example.test,two@example.test,https://example.test/users/bot',
				true,
			],
			[
				'',
				'one@example.test,blocked@example.test,two@example.test,https://example.test/users/bot',
				false,
			],
			[
				' ',
				'one@example.test, ,blocked@example.test,two@example.test,https://example.test/users/bot',
				false,
			],
			[
				' ',
				'',
				false,
			],
		];
	}
}
