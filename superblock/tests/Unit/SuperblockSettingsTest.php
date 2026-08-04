<?php
// SPDX-FileCopyrightText: 2026 The Hubzilla Community
//
// SPDX-License-Identifier: MIT

namespace Zotlabs\Addons\Superblock\Tests\Unit;

use Zotlabs\Addons\Superblock\Tests\Helpers\PluginHelperTrait;
use Zotlabs\Tests\Unit\Module\TestCase;

class SuperblockSettingsTest extends TestCase
{
	use PluginHelperTrait;

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

	private function getSettingsPage(): void {
		$this->getFunctionMock('Zotlabs\Module\Settings', 'get_form_security_token')
			->expects($this->once())
			->willReturn('very_security');

		$channel = $this->fixtures['channel'][1];

		// Just to silence a warning...
		// Should really be fixed in Zotlabs\Module\Settings
		$channel['xchan_photo_s'] = z_root() . '/photo/1.jpg';

		$this->startSession($channel);
		$this->installPluginApp($channel);
		$this->get('settings/superblock');
	}
}
