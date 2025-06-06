<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

class Pubcrawl extends Controller {

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

		$sc = '<div class="section-content-info-wrapper">' . $desc . '</div>';

		$tpl = get_markup_template("settings_addon.tpl");

		return replace_macros($tpl, [
			'$title' => t('Activitypub Protocol'),
			'$content'  => $sc,
		]);
	}
}
