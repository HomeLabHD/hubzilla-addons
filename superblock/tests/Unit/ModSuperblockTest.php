<?php
/* Tests for the Mod_Superblock module.
 *
 * SPDX-FileCopyrightText: 2025 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Addons\Superblock\Tests\Unit;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\DataProvider;
use Zotlabs\Tests\Unit\Module\TestCase;
use Zotlabs\Tests\Unit\Module\KillmeException;
use Zotlabs\Addons\Superblock\Superblock;
use Zotlabs\Addons\Superblock\Tests\Helpers;

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

class ModSuperblockTest extends TestCase {

	use Helpers\PluginHelperTrait;
	use PHPMock;

	// Holds the channel that we simulate during the test
	private array $channel;

	// Used to store the result of ajax calls
	private array $returnedJson;

	public function testGetModuleWhenAppNotInstalledRendersAppInfo(): void {
		$this->channel = $this->fixtures['channel'][1];
		$this->startSession($this->channel);

		// Make the app available (install as system app), but don't
		// install it for the current channel.
		$this->installPluginApp([]);

		$this->get('superblock');

		// Check that install button is rendered.
		//
		// This could probably be more robust, but it'll do for now.
		$this->assertPageContains('<button type="submit" name="install" value="install"');
	}


	public function testGetModuleWhenAppInstalledListBlockedChannels(): void {
		$this->channel = $this->fixtures['channel'][1];
		$this->startSession($this->channel);
		$this->installPluginApp($this->channel);
		$this->stubGetSecurityToken();

		// With no blocked channels
		$this->get('superblock');
		$this->assertPageContains('No channels currently blocked');

		// Then add some blocks
		$plugin = Superblock::getInstance($this->channel['channel_id']);
		$plugin->blockChannel('snertemoen@valdres.test');
		$plugin->blockChannel('knallert@blowback.test');
		$plugin->save();

		// Only channels matching xchans are listed!
		$this->addXChans();

		$this->get('superblock');
		$this->assertPageContains('href="https://valdres.test/users/snertemoen"');
		$this->assertPageContains('href="superblock?f=&unblock=snertemoen%40valdres.test');
		$this->assertPageContains('href="https://blowback.test/~knallert"');
		$this->assertPageContains('href="superblock?f=&unblock=knallert%40blowback.test');
	}

	public function testAddNewBlockByHTMLForm(): void {
		$this->channel = $this->fixtures['channel'][1];
		$this->startSession($this->channel);
		$this->installPluginApp($this->channel);
		$this->stubGetSecurityToken();
		$this->stubCheckFormSecurityToken();

		// Only channels matching xchans are listed!
		$this->addXChans();

		$this->post('superblock', [], [
			'action' => 'block',
			'author' => 'snertemoen@valdres.test',
			'item' => 666,
			'form_security_token' => 'very security',
		]);

		$this->assertPageContains('href="https://valdres.test/users/snertemoen"');
		$this->assertPageContains('href="superblock?f=&unblock=snertemoen%40valdres.test');
	}

	#[DataProvider('actionProvider')]
	public function testAjaxRequest(array $params, string $status, string $msg): void {
		$this->channel = $this->fixtures['channel'][1];
		$this->startSession($this->channel);
		$this->installPluginApp($this->channel);
		$this->stubGetSecurityToken();
		$this->stubCheckFormSecurityToken();
		$this->stubFileGetContents();
		$this->stubJsonReturnAndDie();

		// Only channels matching xchans are listed!
		$this->addXChans();

		try {
			$this->ajax_request('POST', 'superblock', $params);
		} catch (KillmeException $e) {
			$this->assertIsArray($this->returnedJson);
			$this->assertArrayHasKey('status', $this->returnedJson);
			$this->assertEquals($status, $this->returnedJson['status']);
			$this->assertArrayHasKey('message', $this->returnedJson);
			$this->assertEquals($msg, $this->returnedJson['message']);
		}
	}

	public static function actionProvider(): array {
		return [
			'add new block should succeed' => [
				'params' => [
					'action' => 'block',
					'author' => 'snertemoen@valdres.test',
					'item' => 666,
					'form_security_token' => 'very security',
				],
				'status' => 'success',
				'msg' => 'blocked snertemoen@valdres.test permanently',
			],
			'POST with no action is rejected' => [
				'params' => [
					'author' => 'snertemoen@valdres.test',
					'item' => 666,
					'form_security_token' => 'very security',
				],
				'status' => 'error',
				'msg' => 'No action given',
			],
			'POST with no author is rejected' => [
				'params' => [
					'action' => 'block',
					'item' => 666,
					'form_security_token' => 'very security',
				],
				'status' => 'error',
				'msg' => 'Invalid xchan',
			],
			'POST with invalid security token is rejected' => [
				'params' => [
					'action' => 'block',
					'author' => 'snertemoen@valdres.test',
					'item' => 666,
					'form_security_token' => 'wrong token',
				],
				'status' => 'error',
				'msg' => 'Invalid or missing security token',
			],
			'POST with unknown author should be rejected' => [
				'params' => [
					'action' => 'block',
					'author' => 'unknown@example.test',
					'item' => 666,
					'form_security_token' => 'very security',
				],
				'status' => 'error',
				'msg' => 'Unknown author',
			],
		];
	}

	private function addXChans(): void {
		xchan_store_lowlevel([
			'xchan_addr' => 'snertemoen@valdres.test',
			'xchan_hash' => 'snertemoen@valdres.test',
			'xchan_url' => 'https://valdres.test/users/snertemoen',
		]);
		xchan_store_lowlevel([
			'xchan_addr' => 'knallert@blowback.test',
			'xchan_hash' => 'knallert@blowback.test',
			'xchan_url' => 'https://blowback.test/~knallert'
		]);
	}

	private function stubGetSecurityToken(): void {
		$this->getFunctionMock('Zotlabs\Module', 'get_form_security_token')
			->expects($this->any())
			->willReturn('very security');
	}

	private function stubCheckFormSecurityToken(): void {
		$this->getFunctionMock('Zotlabs\Module', 'check_form_security_token')
			->expects($this->any())
			->willReturnCallback(fn ($type) =>
				$type === 'superblock' &&
				$_REQUEST['form_security_token'] === 'very security');
	}

	private function stubFileGetContents(): void {
		$this->getFunctionMock('Zotlabs\Module', 'file_get_contents')
			->expects($this->any())
			->willReturnCallback(fn() => $_SERVER['HTTP_POST_BODY']);
	}

	private function stubJsonReturnAndDie(): void {
		$this->getFunctionMock('Zotlabs\Module', 'json_return_and_die')
			->expects($this->once())
			->willReturnCallback(function (array $data) {
				$this->returnedJson = $data;

				// Make sure we stop processing
				throw new KillmeException();
			});
	}
}
