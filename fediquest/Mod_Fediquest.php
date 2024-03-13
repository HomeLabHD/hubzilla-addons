<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

class Fediquest extends Controller {

	function get() {
		if(!local_channel())
			return;

		if(!Apps::addon_app_installed(local_channel(), 'fediquest')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';
			$papp = Apps::get_papp('Fediquest');
			return Apps::app_render($papp, 'module');
		}

		$o = '<h2>' . t('Fediquest App') . '</h2>';
		$o .= t('A distributed quest for a given word (game).') . '<br><br>';
		$o .= t('To start a game, enter [fediquest]your_word[/fediquest] somewhere in a toplevel post.') . '<br>';
		$o .= t('Your contacts can post their guess in the comments.') . '<br>';
		$o .= t('Your channel will evaluate the guess and automatically post the response.') . '<br><br>';

		$o .= '🟢 ' . t('Correct letters') . '<br>';
		$o .= '🟡 ' . t('Letters contained in the word but at the wrong spot') . '<br>';
		$o .= '🔴 ' . t('Letters not contained in the word') . '<br>';

		return $o;
	}

}
