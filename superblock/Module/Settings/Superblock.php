<?php
// SPDX-FileCopyrightText: 2026 The Hubzilla Community
//
// SPDX-License-Identifier: MIT

namespace Zotlabs\Module\Settings;

use Zotlabs\Web\Controller;

class Superblock extends Controller
{
	public function get(): string
	{
		$tpl = get_markup_template('superblock_settings.tpl', 'addon/superblock/');
		return replace_macros($tpl, [
			'title' => 'Superblock settings',
			'securityToken' => get_form_security_token('settings/superblock'),
			'blockResharesField' => [
				'block_reshares',		// name, id
				t('Block reshares from blocked channels'),	// label
				true,					// checked
				t("If enabled, posts containing a reshare of a post by a blocked channel
					will be blocked, even if the channel resharing the post is not blocked."),
				[ t('No'), t('Yes') ],
				null,
				null,
			],
		]);
	}
}
