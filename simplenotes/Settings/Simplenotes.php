<?php

namespace Zotlabs\Module\Settings;

use Zotlabs\Lib\Apps;
use Zotlabs\Lib\PConfig;

class Simplenotes {

	public function post()
	{

		if (!local_channel()) {
			return;
		}

		if (!Apps::addon_app_installed(local_channel(), 'simplenotes')) {
			return;
		}

		check_form_security_token_redirectOnErr('/settings/simplenotes', 'simplenotes');

		$path = trim($_POST['simplenotes-path']);
		$path = trim($path, '/');

		PConfig::Set(local_channel(), 'simplenotes', 'path', $path);
		info(t('Simple notes path saved') . EOL);

		goaway('/simplenotes');

	}

	public function get()
	{

		if (!local_channel()) {
			return;
		}

		if (!Apps::addon_app_installed(local_channel(), 'simplenotes')) {
			return;
		}

		$path = PConfig::Get(local_channel(), 'simplenotes', 'path');

		$content = replace_macros(get_markup_template('field_input.tpl'),
			[
				'$field' => ['simplenotes-path', t('Path to notes directory'), $path, t('Existing path as configured in the app server settings. E.g. notes')]
			]
		);

		$tpl = get_markup_template('settings_addon.tpl');
		$formsecurity = get_form_security_token('simplenotes');

		$o = replace_macros($tpl, [
			'$action_url' => '/settings/simplenotes',
			'$form_security_token' => $formsecurity,
			'$title' => t('Simple Notes Settings'),
			'$content'  => $content,
			'$baseurl'   => z_root(),
			'$submit'    => t('Submit'),
		]);

		return $o;

	}

}
