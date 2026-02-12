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
use Zotlabs\Lib\Config;
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
		$xchans = self::createXChans();
		foreach ($xchans as $xchan) {
			$this->addXChan($xchan, true);
		}

		$this->get('superblock');
		foreach ($xchans as $xchan) {
			$this->assertXChanIsListed($xchan);
		}
	}

	public function testRenderedHTMLContainsCSRFToken(): void {
		$this->channel = $this->fixtures['channel'][1];
		$this->startSession($this->channel);
		$this->installPluginApp($this->channel);
		$this->stubGetSecurityToken();

		// Add some Blocked XChans
		$xchans = self::createXChans();
		foreach ($xchans as $xchan) {
			$this->addXChan($xchan, true);
		}

		$this->get('superblock');

		$this->assertMatchesRegularExpression(
			'/form_security_token: "[0-9a-f.]+"/',
		   	App::$page['htmlhead']);
	}

	public function testRenderAddChannelBLockForm(): void {
		$this->channel = $this->fixtures['channel'][1];
		$this->startSession($this->channel);
		$this->installPluginApp($this->channel);
		$this->stubGetSecurityToken();

		// With no blocked channels
		$this->get('superblock/add');

		// Verify and extract the form element
		$this->assertEquals(1, preg_match(
			'/<form\s+name="superblock-add-channel-block"[^>]*>(.*)<\/form>/s',
			App::$page['content'],
			$form)
		);

		// Check that the form contains the elements we want
		$this->assertMatchesRegularExpression(
			'/<input\s+class="form-control"\s+name="author"/',
		   	$form[1]);
		$this->assertStringContainsString('<input name="action" type="hidden" value="block"', $form[1]);
		$this->AssertStringContainsString(
			'<input name="form_security_token" type="hidden" value="very security"',
		   	$form[1]);
	}

	/**
	 * Test processing actions passed as HTML form data.
	 *
	 * @param array $params      The data to pass to the request.
	 * @param array $expected    The expected result of the request.
	 * @param bool  $is_admin    True if the request should be performed as a site admin.
	 */
	#[DataProvider('actionProvider')]
	public function testHTMLFormAction(array $params, array $expected, bool $is_admin = false): void
	{
		$this->channel = $this->fixtures['channel'][1];
		$this->startSession($this->channel);
		$this->installPluginApp($this->channel);
		$this->stubGetSecurityToken();
		$this->stubCheckFormSecurityToken();
		$this->stubHttpStatusExit();
		$this->stubIsSiteAdmin($is_admin);

		if (!empty($expected['xchan'])) {
			// Add expected xchan, and block if we're testing the unblock action
			$this->addXChan($expected['xchan'], isset($params['action']) && $params['action'] === 'unblock');
		}

		try {
			$this->post('superblock', [], $params);
		} catch (KillmeException $e) {
			$this->assertIsArray($this->returnedJson);
			$this->assertArrayHasKey('status', $this->returnedJson);
			$this->assertEquals($expected['status']['code'], $this->returnedJson['status']);
			$this->assertArrayHasKey('message', $this->returnedJson);
			$this->assertEquals($expected['status']['msg'], $this->returnedJson['message']);

			return;
		}

		if ($params['action'] === 'block') {
			//
			// Verify that the rendered page contains the newly blocked channel
			//
			$this->assertXChanIsListed($expected['xchan']);
		} elseif ($params['action'] === 'unblock') {
			//
			// Verify that the rendered page does _not_ contain the unblockec channel
			//
			$this->assertXChanIsNotListed($expected['xchan']);
		} elseif ($params['action'] === 'siteblock') {
			//
			// Verify that site blocklist contains added xchan
			//
			$siteBlockList = Config::Get('system', 'blacklisted_channels');
			$this->assertIsArray($siteBlockList);
			$this->assertContains($params['author'], $siteBlockList);
		}
	}

	/**
	 * Test processing actions passed as JSON data as an Ajax request.
	 *
	 * @param array $params      The data to pass to the request.
	 * @param array $expected    The expected result of the request.
	 * @param bool  $is_admin    True if the request should be performed as a site admin.
	 */
	#[DataProvider('actionProvider')]
	public function testAjaxRequestAction(array $params, array $expected, bool $is_admin = false): void
	{
		$this->channel = $this->fixtures['channel'][1];
		$this->startSession($this->channel);
		$this->installPluginApp($this->channel);
		$this->stubGetSecurityToken();
		$this->stubCheckFormSecurityToken();
		$this->stubFileGetContents();
		$this->stubJsonReturnAndDie();
		$this->stubIsSiteAdmin($is_admin);

		if (!empty($expected['xchan'])) {
			// Add expected xchan, and block if we're testing the unblock action
			$this->addXChan($expected['xchan'], isset($params['action']) && $params['action'] === 'unblock');
		}

		try {
			$this->ajax_request('POST', 'superblock', $params);
		} catch (KillmeException $e) {
			$this->assertIsArray($this->returnedJson);
			$this->assertArrayHasKey('status', $this->returnedJson);
			$this->assertEquals($expected['status']['text'], $this->returnedJson['status']);
			$this->assertArrayHasKey('message', $this->returnedJson);
			$this->assertEquals($expected['status']['msg'], $this->returnedJson['message']);
		}

		if ($this->returnedJson['status'] === 'success') {

			if ($params['action'] === 'block') {
				//
				// Verify that the rendered page contains the newly blocked channel
				//
				$this->get('superblock');
				$this->assertXChanIsListed($expected['xchan']);
			} elseif ($params['action'] === 'unblock') {
				//
				// Verify that the rendered page does _not_ contain the unblockec channel
				//
				$this->get('superblock');
				$this->assertXChanIsNotListed($expected['xchan']);
			} elseif ($params['action'] === 'siteblock') {
				//
				// Verify that site blocklist contains added xchan
				//
				$siteBlockList = Config::Get('system', 'blacklisted_channels');
				$this->assertIsArray($siteBlockList);
				$this->assertContains($params['author'], $siteBlockList);
			}
		}
	}

	/**
	 * Add an xchan to the db, and optionally block it.
	 *
	 * @param array $xchan	An array containing the xchan to add.
	 * @param bool	$block	True if the xchan should be added to the block
	 *                      list.
	 */
	private function addXChan(array $xchan, bool $block): void {
		xchan_store_lowlevel($xchan);
		if ($block) {
			$plugin = Superblock::getInstance($this->channel['channel_id']);
			$plugin->blockChannel($xchan['xchan_hash']);
			$plugin->save();
		}
	}

	/**
	 * Action provider for the HTMLForm and Ajax tests.
	 *
	 * Generates an array of entries that contain the test vectors for
	 * the tests. The vector is divided into three main parts:
	 *
	 *   - `params`: The request params for the POST request.
	 *   - `expected`: The expected results from the request.
	 *   - `is_admin`: An optional bool telling if the request should be
	 *     performed as an admin.
	 *
	 * The `expected` field is an array consisting of the status and
	 * response message expected from the request, as well as the xchan
	 * that should be added/removed from the block list.
	 *
	 * The `status` field contains both the status code for the HTMLForm
	 * tests, and the result message for the Ajax tests.
	 *
	 * @return The array of the test vectors.
	 */
	public static function actionProvider(): array {
		$xchans = self::createXChans();
		$vectors = [];

		foreach (['snertemoen', 'balder', 'knallert'] as $author) {
			//
			// Vectors to block by xchan_hash
			//
			// This is the normal way a channel will be blocked when
			// selecting "Block permanently" from the post avatar menu.
			//
			$vectors["block {$author} by hash"] = [
				'params' => [
					'action' => 'block',
					'author' => $xchans[$author]['xchan_hash'],
					'item' => 666,
					'form_security_token' => 'very security',
				],
				'expected' => [
					'status' => [
						'text' => 'success',
						'code' => 200,
						'msg' => "blocked {$xchans[$author]['xchan_addr']} permanently",
					],
					'xchan' => $xchans[$author],
				]
			];

			//
			// Vectors to block by xchan_addr (aka webbie)
			//
			// We need to support this to allow users to manually add channels
			// to the block list.
			//
			$vectors["block {$author} by addr"] = [
				'params' => [
					'action' => 'block',
					'author' => $xchans[$author]['xchan_addr'],
					'item' => 666,
					'form_security_token' => 'very security',
				],
				'expected' => [
					'status' => [
						'text' => 'success',
						'code' => 200,
						'msg' => "blocked {$xchans[$author]['xchan_addr']} permanently",
					],
					'xchan' => $xchans[$author],
				]
			];

			//
			// Vectors to block by xchan_url
			// This is not supported by xchan_fetch
			//---------------------------------------------
			// $vectors["block {$author} by url"] = [
			// 	'params' => [
			// 		'action' => 'block',
			// 		'author' => $xchans[$author]['xchan_url'],
			// 		'item' => 666,
			// 		'form_security_token' => 'very security',
			// 	],
			// 	'expected' => [
			// 		'status' => [
			// 			'text' => 'success',
			// 			'code' => 200,
			// 			'msg' => "blocked {$xchans[$author]['xchan_addr']} permanently",
			// 		],
			// 		'xchan' => $xchans[$author],
			// 	]
			// ];

			//
			// Vectors to unblock a channel
			//
			// We only support unblocking by the xchan hash. This is because we
			// don't expect anyone to perfom this by typing the user to
			// unblock. It should allways be done via the UI.
			//
			$vectors["unblock {$author}"] = [
				'params' => [
					'action' => 'unblock',
					'author' => $xchans[$author]['xchan_hash'],
					'item' => 666,
					'form_security_token' => 'very security',
				],
				'expected' => [
					'status' => [
						'text' => 'success',
						'code' => 200,
						'msg' => "removed {$xchans[$author]['xchan_addr']} from block list",
					],
					'xchan' => $xchans[$author],
				]
			];
		}

		$vectors['POST with no action is rejected'] = [
			'params' => [
				'author' => $xchans['snertemoen']['xchan_hash'],
				'item' => 666,
				'form_security_token' => 'very security',
			],
			'expected' => [
				'status' => [
					'text' => 'error',
					'code' => 400,
					'msg' => 'no action specified',
				],
				'xchan' => [],
			]
		];

		$vectors['POST with no author is rejected'] = [
			'params' => [
				'action' => 'block',
				'item' => 666,
				'form_security_token' => 'very security',
			],
			'expected' => [
				'status' => [
					'text' => 'error',
					'code' => 400,
					'msg' => 'no channel specified',
				],
				'xchan' => [],
			]
		];

		$vectors['POST with invalid security token is rejected'] = [
			'params' => [
				'action' => 'block',
				'author' => 'this_is_ignored',
				'item' => 666,
				'form_security_token' => 'wrong token',
			],
			'expected' => [
				'status' => [
					'text' => 'error',
					'code' => 403,
					'msg' => 'Invalid or missing security token',
				],
				'xchan' => [],
			]
		];

		$vectors['POST with unknown author is rejected'] = [
			'params' => [
				'action' => 'block',
				'author' => 'unknown@example.test',
				'item' => 666,
				'form_security_token' => 'very security',
			],
			'expected' => [
				'status' => [
					'text' => 'error',
					'code' => 400,
					'msg' => 'Invalid or unknown channel',
				],
				'xchan' => [],
			]
		];

		$vectors['POST siteblock valid author'] = [
			'params' => [
				'action' => 'siteblock',
				'author' => $xchans['snertemoen']['xchan_hash'],
				'item' => 666,
				'form_security_token' => 'very security'
			],
			'expected' => [
				'status' => [
					'text' => 'success',
					'code' => 200,
					'msg' => 'added snertemoen@valdres.test to site block list',
				],
				'xchan' => $xchans['snertemoen'],
			],
			'is_admin' => true,
		];

		return $vectors;
	}

	private static function createXChans(): array {
		$uid = Libzot::new_uid('knallert');
		$hash = Libzot::make_xchan_hash($uid, 'dummy_public_key_3fa9e');

		return [
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

	private function stubIsSiteAdmin(bool $is_admin): void {
		$this->getFunctionMock('Zotlabs\Module', 'is_site_admin')
			->expects($this->any())
			->willReturn($is_admin);
	}
}
