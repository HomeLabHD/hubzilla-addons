<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\PConfig;
use Zotlabs\Web\Controller;

class Pubcrawl extends Controller {



	function post() {

		if (!local_channel()) {
			return;
		}

		if (!Apps::addon_app_installed(local_channel(), 'pubcrawl')) {
			return;
		}

		check_form_security_token_redirectOnErr('/pubcrawl', 'pubcrawl');

		PConfig::Set(local_channel(), 'activitypub', 'force_note', $_REQUEST['activitypub_force_note']);

		info( t('ActivityPub Protocol Settings updated.') . EOL);
	}

	function get() {

		if (!local_channel()) {
			return;
		}

		if (!Apps::addon_app_installed(local_channel(), 'pubcrawl')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';
			$papp = Apps::get_papp('Activitypub Protocol');
			return Apps::app_render($papp, 'module');
		}

		$desc = t('The activitypub protocol does not support location independence. Connections you make within that network may be unreachable from alternate channel locations.');
		$yes_no = [t('No'),t('Yes')];

		$sc = '<div class="section-content-info-wrapper">' . $desc . '</div><br>';

		$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), [
			'$field' => [
				'activitypub_force_note',
				t('Prefer compatibility with Mastodon'),
				PConfig::Get(local_channel(), 'activitypub', 'force_note', true),
				t('Mastodon does not always follow the ActivityPub spec. Enabling this option will ensure compatibility with Mastodon over strict adherence to the ActivityPub spec in these cases.'),
				$yes_no
			],
		]);

		$tpl = get_markup_template("settings_addon.tpl");

		$o .= replace_macros($tpl, [
			'$action_url' => 'pubcrawl',
			'$form_security_token' => get_form_security_token("pubcrawl"),
			'$title' => t('Activitypub Protocol'),
			'$content'  => $sc,
			'$baseurl'   => z_root(),
			'$submit'    => t('Submit'),
		]);

		return $o;

	}

}
