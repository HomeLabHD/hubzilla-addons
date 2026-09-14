<?php
// SPDX-FileCopyrightText: 2026 The Hubzilla Community
//
// SPDX-License-Identifier: MIT

namespace Zotlabs\Module\Settings;

use Zotlabs\Addons\Superblock\PConfigAdapter;
use Zotlabs\Addons\Superblock\Superblock as Plugin;
use Zotlabs\Addons\Superblock\SuperblockSettings;
use Zotlabs\Web\Controller;

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/addon_common/vendor/autoload.php';

class Superblock extends Controller
{
	public function get(): string
	{
		$plugin = Plugin::getInstance(local_channel());
		$plugin->loadStyleSheet();

		$tpl = get_markup_template('superblock_settings.tpl', 'addon/superblock/');
		return replace_macros($tpl, [
			'title' => 'Superblock settings',
			'securityToken' => get_form_security_token('settings/superblock'),
			'blockResharesField' => [
				'block_reshares',		// name, id
				t('Block reshares from blocked channels'),	// label
				intval($plugin->settings->blockReshares()),	// checked
				t("If enabled, posts containing a reshare of a post by a blocked channel will be blocked, even if the channel resharing the post is not blocked."),
				[ t('No'), t('Yes') ],
				null,
				null,
			],
			'blockIncomingField' => [
				'block_incoming',		// name, id
				t('Block incoming posts and activities'),	// label
				intval($plugin->settings->blockIncoming()),	// checked
				t("If enabled, posts and activities that are blocked will be dropped on arrival. They will not be stored in the system, and will not reappear when the block expires or is removed."),
				[ t('No'), t('Yes') ],
				null,
				null,
			],
			'submitLabel' => t('Save settings'),
		]);
	}

	public function post(): void
	{
		check_form_security_token_redirectOnErr(
			'settings/superblock',
			'settings/superblock',
		   	'form_security_token');

		$settings = new SuperblockSettings(local_channel(), new PConfigAdapter());
		$settings->setBlockReshares(boolval($_POST['block_reshares'] ?? false));
		$settings->setBlockIncoming(boolval($_POST['block_incoming'] ?? false));
		$settings->save();
	}
}
