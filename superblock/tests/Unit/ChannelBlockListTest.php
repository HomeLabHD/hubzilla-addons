<?php
/*
 * Test cases for the ChannelBlockList class.
 *
 * SPDX-FileCopyrightText: 2025 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Addons\Superblock\Tests\Unit;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zotlabs\Addons\Superblock\ChannelBlock;
use Zotlabs\Addons\Superblock\ChannelBlockList;
use Zotlabs\Addons\Superblock\ConfigInterface;

require_once dirname(dirname(dirname(__DIR__))) . '/addon_common/vendor/autoload.php';

class ChannelBlockListTest extends TestCase
{
	#[DataProvider('blockListProvider')]
	public function testChannelBlockList(string $hash, mixed $blocked, bool $result): void {

		//
		// Stub implementation of the ConfigInterface, so we can inject into
		// the class under test to replace the dependency on PConfig.
		//
		$testConfig = new class($blocked) implements ConfigInterface {
			public function __construct(private mixed $blocked) {}
			public function getSettings(int $channelId): array {}
			public function saveSettings(int $channelId, array $settings): void {}
			public function getBlockedChannels(int $channelId): mixed {
				return $channelId ? $this->blocked : '';
			}
			public function saveBlockedChannels(int $channelId, array $blockList): void {
			}
		};

		$blockList = new ChannelBlockList(42, $testConfig);

		$this->assertEquals($result, $blockList->match($hash));
	}

	public function testChannelBlockListWithNoConfiguredList(): void {
		//
		// Stub implementation of the ConfigInterface, so we can inject into
		// the class under test to replace the dependency on PConfig.
		//
		$testConfig = new class() implements ConfigInterface {
			public function getSettings(int $channelId): array {}
			public function saveSettings(int $channelId, array $settings): void {}
			public function getBlockedChannels(int $channelId): mixed {
				$channelId = 42;
				return false;
			}
			public function saveBlockedChannels(int $channelId, array $blockList): void {
			}
		};

		$blockList = new ChannelBlockList(42, $testConfig);

		$entries = $blockList->getEntries();
		$this->assertIsArray($entries);
		$this->assertEmpty($entries);
	}

	public function testAddChannelToBLockListMarksItDirty(): void {
		//
		// Stub implementation of the ConfigInterface, so we can inject into
		// the class under test to replace the dependency on PConfig.
		//
		$dummyConfig = new class() implements ConfigInterface {
			public function __construct() {}

			public function getSettings(int $channelId): array {}
			public function saveSettings(int $channelId, array $settings): void {}
			// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
			public function getBlockedChannels(int $channelId): string {
				return '';
			}
			public function saveBlockedChannels(int $channelId, array $blockList): void {
			}
		};

		$blockList = new ChannelBlockList(43, $dummyConfig);
		$this->assertFalse($blockList->isModified());

		$blockList->add(['hash' => 'gangster@scarface.test']);

		$this->assertTrue($blockList->isModified());
		$this->assertTrue($blockList->match('gangster@scarface.test'));
	}

	public function testRemoveChannelFromBlockListMarksItDirty(): void {
		//
		// Stub implementation of the ConfigInterface, so we can inject into
		// the class under test to replace the dependency on PConfig.
		//
		$dummyConfig = new class() implements ConfigInterface {
			public function __construct() {}
			public function getSettings(int $channelId): array {}
			public function saveSettings(int $channelId, array $settings): void {}
			public function getBlockedChannels(int $channelId): string {
				return 'gangster@scarface.test,lowlife@mob.test';
			}
			public function saveBlockedChannels(int $channelId, array $blockList): void {
			}
		};

		$blockList = new ChannelBlockList(43, $dummyConfig);
		$this->assertFalse($blockList->isModified());

		$blockList->remove('gangster@scarface.test');

		$this->assertTrue($blockList->isModified());
		$this->assertFalse($blockList->match('gangster@scarface.test'));
	}

	public function testBlockingAlreadyBlockedChannelDoesNotAlterList(): void {
		//
		// Stub implementation of the ConfigInterface, so we can inject into
		// the class under test to replace the dependency on PConfig.
		//
		$dummyConfig = new class() implements ConfigInterface {
			public function __construct() {}
			public function getSettings(int $channelId): array {}
			public function saveSettings(int $channelId, array $settings): void {}
			public function getBlockedChannels(int $channelId): string {
				return 'gangster@scarface.test,lowlife@mob.test';
			}
			public function saveBlockedChannels(int $channelId, array $blockList): void {
			}
		};

		$blockList = new ChannelBlockList(43, $dummyConfig);
		$this->assertFalse($blockList->isModified());

		$blockList->add(['hash' => 'gangster@scarface.test']);

		$this->assertFalse($blockList->isModified());
		$this->assertTrue($blockList->match('gangster@scarface.test'));
	}

	public function testUpdatingChannelBlock(): void {
		//
		// Stub implementation of the ConfigInterface, so we can inject into
		// the class under test to replace the dependency on PConfig.
		//
		$dummyConfig = new class() implements ConfigInterface {
			public function __construct() {}
			public function getSettings(int $channelId): array {}
			public function saveSettings(int $channelId, array $settings): void {}
			public function getBlockedChannels(int $channelId): string {
				return 'gangster@scarface.test,lowlife@mob.test';
			}
			public function saveBlockedChannels(int $channelId, array $blockList): void {
			}
		};

		$blockList = new ChannelBlockList(43, $dummyConfig);
		$this->assertFalse($blockList->isModified());

		$date = new DateTimeImmutable("3 days");
		$blockList->add(['hash' => 'gangster@scarface.test', 'until' => $date]);

		$this->assertTrue($blockList->isModified());
		$this->assertTrue($blockList->match('gangster@scarface.test'));
	}

	/*
	 * DataProvider for testChannelBlockList
	 *
	 * Returns an array of [$hash, $blocked, $result] args for the
	 * testChannelBlockList function.
	 */
	public static function blockListProvider(): array {
		$utc = new DateTimeZone('UTC');
		$tomorrow = new DateTimeImmutable('tomorrow', $utc);
		$yesterday = new DateTimeImmutable('yesterday', $utc);

		return [
			'hash should not match when no block list' => [
				'blocked@example.test',
				false,
				false,
			],
			'blocked hash should match' => [
				'blocked@example.test',
				'one@example.test,blocked@example.test,two@example.test,https://example.test/users/bot',
				true,
			],
			'not blocked hash should not match' => [
				'blocked@example.test',
				'one@example.test,two@example.test,https://example.test/users/bot',
				false,
			],
			'empty entries in old-style block list should be ignored' => [
				'blocked@example.test',
				',,,,',
				false,
			],
			'whitespace in old-style block list should be ignored' => [
				'blocked@example.test',
				'one@example.test, blocked@example.test ,two@example.test,https://example.test/users/bot',
				true,
			],
			'whitespace padding hash should be ignored' => [
				' blocked@example.test ',
				'one@example.test,blocked@example.test,two@example.test,https://example.test/users/bot',
				true,
			],
			'empty hash should not match' => [
				'',
				'one@example.test,blocked@example.test,two@example.test,https://example.test/users/bot',
				false,
			],
			'empty hash with whitespace should not match' => [
				' ',
				'one@example.test, ,blocked@example.test,two@example.test,https://example.test/users/bot',
				false,
			],
			'empty hash with whitespave should not match empty block list' => [
				' ',
				'',
				false,
			],
			'blocked hash should match new-style block list' => [
				'blocked@example.test',
				[
					[ 'hash' => 'somechan@example.test' ],
					[ 'hash' => 'blocked@example.test' ],
				],
				true,
			],
			'block not yet expired should match' => [
				'blocked@example.test',
				[
					[ 'hash' => 'somechan@example.test' ],
					[
						'hash' => 'blocked@example.test',
						'until' => $tomorrow,
					],
				],
				true,
			],
			'expired block should not match' => [
				'blocked@example.test',
				[
					[ 'hash' => 'somechan@example.test' ],
					[
						'hash' => 'blocked@example.test',
						'until' => $yesterday,
					],
				],
				false,
			],

		];
	}
}
