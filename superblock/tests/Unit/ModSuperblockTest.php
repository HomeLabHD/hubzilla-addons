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
use Zotlabs\Tests\Unit\Module\TestCase;
use Zotlabs\Addons\Superblock\Superblock;
use Zotlabs\Addons\Superblock\Tests\Helpers;

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

class ModSuperblockTest extends TestCase {

	use Helpers\PluginHelperTrait;
	use PHPMock;

	private array $channel;

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

		$this->get('superblock');
		$this->assertPageContains('href="https://valdres.test/users/snertemoen"');
		$this->assertPageContains('href="superblock?f=&unblock=snertemoen%40valdres.test');
		$this->assertPageContains('href="https://blowback.test/~knallert"');
		$this->assertPageContains('href="superblock?f=&unblock=knallert%40blowback.test');
	}

	private function stubGetSecurityToken(): void {
		$this->getFunctionMock('Zotlabs\Module', 'get_form_security_token')
			->expects($this->any())
			->willReturn('very security');
	}
}
