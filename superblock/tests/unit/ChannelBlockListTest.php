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
	public function testChannelBlockList(int $channelId, bool $result): void {

		//
		// Stub implementation of the ConfigInterface, so we can inject into
		// the class under test to replace the dependency on PConfig.
		//
		$testConfig = new class implements ConfigInterface {
			public function getBlockedChannels(int $channelId): string|false {
				return match ($channelId) {
					13 => false,
					14 => 'someone@example.test,blocked@example.test,other@example.test',
					15 => 'someone@example.test',
					16 => 'blocked@example.test,other@example.test',
					17 => 'someone@example.test,blocked@example.test',
					18 => 'blocked@example.test',
					19 => ',,,,',
					default => '',
				};
			}
		};

		$blockList = new ChannelBlockList($channelId, $testConfig);

		$this->assertEquals($result, $blockList->match('blocked@example.test'));
	}

	public static function blockListProvider(): array {
		return [
			[13, false],
			[14, true],
			[15, false],
			[16, true],
			[17, true],
			[18, true],
			[66, false],
		];
	}
}
