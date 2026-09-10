<?php
/**
 * Name: Random Planet, Imperial Version
 * Description: Set a random planet from the Star Wars Empire as your location when posting.
 * Version: 1.0
 * Author: Mike Macgirvin <http://macgirvin.com/profile/mike>
 * Author: Tony Baldwin <https://free-haven.org/profile/tony>
 * Maintainer: none
 */

use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Hook;
use Zotlabs\Extend\Route;


function planets_load() {
	register_hook('post_local', 'addon/planets/planets.php', 'planets_post_hook');
	register_hook('render_location', 'addon/planets/planets.php', 'planets_render_location');
	Route::register('addon/planets/Mod_Planets.php', 'planets');
	logger("loaded planets");
}


function planets_unload() {
	unregister_hook('post_local',    'addon/planets/planets.php', 'planets_post_hook');
	unregister_hook('render_location', 'addon/planets/planets.php', 'planets_render_location');
	Route::unregister('addon/planets/Mod_Planets.php', 'planets');
	logger("removed planets");
}

function planets_get_all() {
	return ['Alderaan','Tatooine','Dagoba','Polis Massa','Coruscant','Hoth','Endor','Kamino','Rattatak','Mustafar','Iego','Geonosis','Felucia','Dantooine','Ansion','Artaru','Bespin','Boz Pity','Cato Neimoidia','Christophsis','Kashyyk','Kessel','Malastare','Mygeeto','Nar Shaddaa','Ord Mantell','Saleucami','Subterrel','Death Star','Teth','Tund','Utapau','Yavin'];
}

function planets_post_hook(&$item) {

	/**
	 *
	 * An item was posted on the local system.
	 * We are going to look for specific items:
	 *      - A status post by a profile owner
	 *      - The profile owner must have allowed our plugin
	 *
	 */

	logger('planets invoked');

	if (!local_channel())   /* non-zero if this is a logged in user of this system */
		return;

	if (!Apps::addon_app_installed(local_channel(), 'planets'))
		return;

	if (local_channel() != $item['uid'])    /* Does this person own the post? */
		return;


	/**
	 *
	 * OK, we're allowed to do our stuff.
	 * Here's what we are going to do:
	 * load the list of timezone names, and use that to generate a list of world planets.
	 * Then we'll pick one of those at random and put it in the "location" field for the post.
	 *
	 */

	$planets = planets_get_all();

	$planet = array_rand($planets,1);
	$item['location'] = $planets[$planet];
}

function planets_render_location(&$arr) {
	if (!Apps::addon_app_installed(local_channel(), 'planets')) {
		return;
	}

	// Deal with deprecated format
	if(str_starts_with($arr['location'], '#')) {
		preg_match('/\#[url=(.*?)\](.*?)\[\/url\]/', $arr['location'], $matches);
		$arr['location'] = $matches[2] ?? '';
	}

	$planets = planets_get_all();

	if(!in_array($arr['location'], $planets)) {
		return;
	}

	$arr['html'] = '<a href="https://www.starwars.com/search?q=' . $arr['location'] . '" target="_blank" rel="nofollow noopener" title="' . $arr['location'] . '"><i class="bi bi-geo-alt"></i></a>';
}
