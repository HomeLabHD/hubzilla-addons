<?php
// SPDX-FileCopyrightText: 2026 The Hubzilla Community
//
// SPDX-License-Identifier: MIT

namespace Zotlabs\Addons\Superblock\Tests\Unit;

use PHPUnit\Framework\Attributes\Before;
use Zotlabs\Addons\Superblock\ConfigInterface;
use Zotlabs\Addons\Superblock\PConfigAdapter;
use Zotlabs\Addons\Superblock\Superblock as Plugin;
use Zotlabs\Addons\Superblock\SuperblockSettings;
use Zotlabs\Addons\Superblock\Tests\Helpers\PluginHelperTrait;
use Zotlabs\Tests\Unit\Module\TestCase;

class SuperblockSettingsTest extends TestCase
{
	use PluginHelperTrait;

	private array $channel;

	#[Before]
	public function init(): void
	{
		$this->channel = $this->fixtures['channel'][1];

		// Just to silence a warning...
		// Should really be fixed in Zotlabs\Module\Settings
		$this->channel['xchan_photo_s'] = z_root() . '/photo/1.jpg';

	}

	public function testRenderSettingsForm(): void
	{
		$this->getSettingsPage();
		$this->assertPageContains('<form id="superblock-settings-form');
	}

	public function testSettingsFormHasSecurityToken(): void
	{
		$this->getSettingsPage();
		$this->assertPageContains('<input type="hidden" name="form_security_token" value="very_security"');
	}

	public function testSettingsPageHasBlockResharesCheckbox(): void
	{
		$this->getSettingsPage();
		$this->assertPageContains('<input type="checkbox" name="block_reshares"');
	}

	public function testSettingsPageHasBLockIncomingCheckbox(): void
	{
		$this->getSettingsPage();
		$this->assertPageContains('<input type="checkbox" name="block_incoming"');
	}

	private function getSettingsPage(): void {
		$this->getFunctionMock('Zotlabs\Module\Settings', 'get_form_security_token')
			->expects($this->once())
			->willReturn('very_security');

		$this->startSession($this->channel);
		$this->installPluginApp($this->channel);
		$this->get('settings/superblock');
	}

	public function testSubmitFormChangesConfig(): void
	{
		$plugin = Plugin::getInstance($this->channel['channel_id']);

		$this->getFunctionMock('Zotlabs\Module', 'local_channel')
		    ->expects($this->any())
			->willReturn($this->channel['channel_id']);

		$this->startSession($this->channel);
		$this->installPluginApp($this->channel);

		$this->post('settings/superblock', [], [
			'form_security_token' => get_form_security_token(
				'settings/superblock',
			   	'form_security_token'),
		]);

		$settings = new SuperblockSettings($this->channel['channel_id'], new PConfigAdapter());

		$this->assertFalse($settings->blockReshares());
		$this->assertFalse($settings->blockIncoming());

		$this->post('settings/superblock', [], [
			'form_security_token' => get_form_security_token(
				'settings/superblock',
			   	'form_security_token'),
			'block_reshares' => '1',
			'block_incoming' => '1',
		]);

		$settings = new SuperblockSettings($this->channel['channel_id'], new PConfigAdapter());

		$this->assertTrue($settings->blockReshares());
		$this->assertTrue($settings->blockIncoming());
	}
}
