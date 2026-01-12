<?php
/* Tests for the Mod_Superblock module.
 *
 * SPDX-FileCopyrightText: 2025 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Addons\Superblock\Tests\Unit;

use App;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Constraint\LogicalAnd;
use Zotlabs\Lib\Libzot;
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

	// Xchans used by the tests
	private array $xchans;

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

		// Add some Blocked XChans
		$this->addXChans(true);

		$this->get('superblock');
		foreach ($this->xchans as $xchan) {
			$this->assertXChanIsListed($xchan);
		}
	}

	public function testRenderedHTMLContainsCSRFToken(): void {
		$this->channel = $this->fixtures['channel'][1];
		$this->startSession($this->channel);
		$this->installPluginApp($this->channel);
		$this->stubGetSecurityToken();

		// Add some Blocked XChans
		$this->addXChans(true);

		$this->get('superblock');

		$this->assertMatchesRegularExpression(
			'/form_security_token: "[0-9a-f.]+"/',
		   	App::$page['htmlhead']);
	}

	#[DataProvider('actionProvider')]
	public function testHTMLFormAction(array $params, array $status): void {
		$this->channel = $this->fixtures['channel'][1];
		$this->startSession($this->channel);
		$this->installPluginApp($this->channel);
		$this->stubGetSecurityToken();
		$this->stubCheckFormSecurityToken();
		$this->stubHttpStatusExit();

		// Add xchans, and block them if we're testing the unblock action
		$this->addXChans(isset($params['action']) && $params['action'] === 'unblock');

		try {
			if (!empty($params['author']) && !empty($this->xchans[$params['author']])) {
				$xchan = $this->xchans[$params['author']];
				$params['author'] = $this->xchans[$params['author']]['xchan_hash'];
			}
			$this->post('superblock', [], $params);
		} catch (KillmeException $e) {
			$this->assertIsArray($this->returnedJson);
			$this->assertArrayHasKey('status', $this->returnedJson);
			$this->assertEquals($status['code'], $this->returnedJson['status']);
			$this->assertArrayHasKey('message', $this->returnedJson);
			$this->assertEquals($status['msg'], $this->returnedJson['message']);

			return;
		}

		if ($params['action'] === 'block') {
			//
			// Verify that the rendered page contains the newly blocked channel
			//
			$this->assertXChanIsListed($xchan);
		} elseif ($params['action'] === 'unblock') {
			//
			// Verify that the rendered page does _not_ contain the unblockec channel
			//
			$this->assertXChanIsNotListed($xchan);
		}
	}

	#[DataProvider('actionProvider')]
	public function testAjaxRequestAction(array $params, array $status): void {
		$this->channel = $this->fixtures['channel'][1];
		$this->startSession($this->channel);
		$this->installPluginApp($this->channel);
		$this->stubGetSecurityToken();
		$this->stubCheckFormSecurityToken();
		$this->stubFileGetContents();
		$this->stubJsonReturnAndDie();

		// Add xchans, and block them if we're testing the unblock action
		$this->addXChans(isset($params['action']) && $params['action'] === 'unblock');

		try {
			if (!empty($params['author']) && !empty($this->xchans[$params['author']])) {
				$xchan = $this->xchans[$params['author']];
				$params['author'] = $this->xchans[$params['author']]['xchan_hash'];
			}
			$this->ajax_request('POST', 'superblock', $params);
		} catch (KillmeException $e) {
			$this->assertIsArray($this->returnedJson);
			$this->assertArrayHasKey('status', $this->returnedJson);
			$this->assertEquals($status['text'], $this->returnedJson['status']);
			$this->assertArrayHasKey('message', $this->returnedJson);
			$this->assertEquals($status['msg'], $this->returnedJson['message']);
		}

		if ($this->returnedJson['status'] === 'success') {

			// Reload page to check that channel was added or removed from block list
			$this->get('superblock');

			if ($params['action'] === 'block') {
				//
				// Verify that the rendered page contains the newly blocked channel
				//
				$this->assertXChanIsListed($xchan);
			} elseif ($params['action'] === 'unblock') {
				//
				// Verify that the rendered page does _not_ contain the unblockec channel
				//
				$this->assertXChanIsNotListed($xchan);
			}
		}
	}

	public static function actionProvider(): array {
		return [
			'add new block from webbie should succeed' => [
				'params' => [
					'action' => 'block',
					'author' => 'snertemoen',
					'item' => 666,
					'form_security_token' => 'very security',
				],
				'status' => [
					'text' => 'success',
					'code' => 200,
					'msg' => 'blocked snertemoen@valdres.test permanently',
				],
			],
			'add new block from url should succeed' => [
				'params' => [
					'action' => 'block',
					'author' => 'balder',
					'item' => 666,
					'form_security_token' => 'very security',
				],
				'status' => [
					'text' => 'success',
					'code' => 200,
					'msg' => 'blocked balder@contact.test permanently',
				],
			],
			'add new block from nomadic hash should succeed' => [
				'params' => [
					'action' => 'block',
					'author' => 'knallert',
					'item' => 666,
					'form_security_token' => 'very security',
				],
				'status' => [
					'text' => 'success',
					'code' => 200,
					'msg' => 'blocked knallert@blowback.test permanently',
				],
			],
			'unblock blocked user should succeed' => [
				'params' => [
					'action' => 'unblock',
					'author' => 'knallert',
					'item' => null,
					'form_security_token' => 'very security',
				],
				'status' => [
					'text' => 'success',
					'code' => 200,
					'msg' => 'removed knallert@blowback.test from block list',
				],
			],
			'POST with no action is rejected' => [
				'params' => [
					'author' => 'snertemoen',
					'item' => 666,
					'form_security_token' => 'very security',
				],
				'status' => [
				   'text' => 'error',
				   'code' => 400,
				   'msg' => 'No action given',
				],
			],
			'POST with no author is rejected' => [
				'params' => [
					'action' => 'block',
					'item' => 666,
					'form_security_token' => 'very security',
				],
				'status' => [
					'text' => 'error',
					'code' => 400,
					'msg' => 'Invalid or unknown channel',
				],
			],
			'POST with invalid security token is rejected' => [
				'params' => [
					'action' => 'block',
					'author' => 'snertemoen',
					'item' => 666,
					'form_security_token' => 'wrong token',
				],
				'status' => [
					'text' => 'error',
					'code' => 403,
					'msg' => 'Invalid or missing security token',
				],
			],
			'POST with unknown author is rejected' => [
				'params' => [
					'action' => 'block',
					'author' => 'unknown@example.test',
					'item' => 666,
					'form_security_token' => 'very security',
				],
				'status' => [
					'text' => 'error',
					'code' => 400,
					'msg' => 'Invalid or unknown channel',
				],
			],
		];
	}

	private function addXChans(bool $block): void {
		$uid = Libzot::new_uid('knallert');
		$hash = Libzot::make_xchan_hash($uid, 'dummy_public_key_3fa9e');

		$this->xchans = [
			// A typical Diaspora contact
			'snertemoen' => [
				'xchan_addr' => 'snertemoen@valdres.test',
				'xchan_hash' => 'snertemoen@valdres.test',
				'xchan_url' => 'https://valdres.test/users/snertemoen',
			],

			// Typical ActivityPub contact
			'balder' => [
				'xchan_addr' => 'balder@contact.test',
				'xchan_hash' => 'https://contact.test/@balder',
				'xchan_url' =>  'https://contact.test/@balder',
			],

			// Zot/Nomadic contact
			'knallert' => [
				'xchan_addr' => 'knallert@blowback.test',
				'xchan_hash' => $hash,
				'xchan_url' => 'https://blowback.test/~knallert'
			],
		];

		// Then add some blocks
		$plugin = Superblock::getInstance($this->channel['channel_id']);

		foreach ($this->xchans as $xchan) {
			xchan_store_lowlevel($xchan);
			if ($block) {
				$plugin->blockChannel($xchan['xchan_hash']);
			}
		}

		$plugin->save();
	}

	private function xchanIsListed(array $xchan): LogicalAnd {
		return $this->logicalAnd(
			$this->stringContains("href=\"{$xchan['xchan_url']}\""),
			$this->matchesRegularExpression(
				"|<input\s+type=\"hidden\"\s+name=\"author\"\s+value=\"{$xchan['xchan_hash']}\"|"
			)
		);
	}

	private function assertXChanIsListed(array $xchan): void {
		$this->assertThat(
			App::$page['content'],
			$this->xchanIsListed($xchan)
		);
	}

	private function assertXChanIsNotListed(array $xchan): void {
		$this->assertThat(
			App::$page['content'],
			$this->logicalNot(
				$this->xchanIsListed($xchan)
			)
		);
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

	private function stubHttpStatusExit(): void {
		$this->getFunctionMock('Zotlabs\Module', 'http_status_exit')
			->expects($this->atMost(1))
			->willReturnCallback(function (int $status, string $msg = '') {
				$this->returnedJson = ['status' => $status, 'message' => $msg];

				// Make sure we stop processing
				throw new KillmeException();
			});
	}
}
