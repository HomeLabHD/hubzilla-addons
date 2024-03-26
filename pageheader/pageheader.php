<?php


/**
 * Name: Page Header
 * Description: Inserts a page header
 * Version: 2
 * Author: Keith Fernie <http://friendika.me4.it/profile/keith>
 *         Hauke Altmann <https://snarl.de/profile/tugelblend>
 *
 */

use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Hook;
use Zotlabs\Extend\Route;

function pageheader_load() {
	Hook::register('construct_page', 'addon/pageheader/pageheader.php', array('\\Pageheader','construct_page'));
	Route::register('addon/pageheader/Mod_Pageheader.php', 'pageheader');
}


function pageheader_unload() {
	Hook::unregister('construct_page', 'addon/pageheader/pageheader.php', array('\\Pageheader','construct_page'));
	Route::unregister('addon/pageheader/Mod_Pageheader.php', 'pageheader');
}

class Pageheader {

	static public function construct_page(&$b) {

		if(file_exists('pageheader.html')){
			$s = file_get_contents('pageheader.html');
		} else {
			$s = get_config('pageheader', 'text');
			App::$page['htmlhead'] .= '<link rel="stylesheet" type="text/css" href="' . z_root() . '/addon/pageheader/pageheader.css' . '" media="all" />' . "\r\n";
		}

		if($s) {
			$b['layout']['region_content'] = '<div class="pageheader">' . $s . '</div>' . "\r\n" . $b['layout']['region_content'];
		}
	}

}
