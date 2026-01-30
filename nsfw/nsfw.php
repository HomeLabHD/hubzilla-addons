<?php

/**
 * Name: NSFW
 * Description: Collapse posts with inappropriate content
 * Version: 1.0
 * Author: Mike Macgirvin <http://macgirvin.com/profile/mike>
 * Maintainer: Mike Macgirvin <mike@macgirvin.com>
 */


use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Hook;
use Zotlabs\Extend\Route;
use Zotlabs\Lib\MessageFilter;

function nsfw_install() {
	Hook::register('prepare_body', 'addon/nsfw/nsfw.php', 'nsfw_prepare_body', 10);
	Route::register('addon/nsfw/Mod_Nsfw.php','nsfw');
}


function nsfw_uninstall() {
	Hook::unregister('prepare_body', 'addon/nsfw/nsfw.php', 'nsfw_prepare_body');
	Route::unregister('addon/nsfw/Mod_Nsfw.php','nsfw');
}


// This function isn't perfect and isn't trying to preserve the html structure - it's just a
// quick and dirty filter to pull out embedded photo blobs because 'nsfw' seems to come up
// inside them quite often. We don't need anything fancy, just pull out the data blob so we can
// check against the rest of the body.

function nsfw_extract_photos($body) {

	$new_body = '';

	$img_start = strpos($body,'src="data:');
	if(! $img_start)
		return $body;

	$img_end = (($img_start !== false) ? strpos(substr($body,$img_start),'>') : false);

	$cnt = 0;

	while($img_end !== false) {
		$img_end += $img_start;
		$new_body = $new_body . substr($body,0,$img_start);

		$cnt ++;
		$body = substr($body,0,$img_end);

		$img_start = strpos($body,'src="data:');
		$img_end = (($img_start !== false) ? strpos(substr($body,$img_start),'>') : false);

	}

	if(! $cnt)
		return $body;

	return $new_body;
}

function nsfw_prepare_body(&$b) {

	if (!Apps::addon_app_installed(local_channel(),'nsfw')) {
		return;
	}

	$words = 'nsfw,contentwarning';

	if(local_channel()) {
		$words = get_pconfig(local_channel(),'nsfw','words',$words);
	}

	if ($words) {
        $words = str_replace(',', "\n", $words);
	}

	if ($words) {
		$messageFilter = new MessageFilter($b['item'], '', html_entity_decode($words));
		if ($messageFilter->evaluate()) {
			return;
		}

		$matchingRule = $messageFilter->getLastMatch() ?? t('Filtered content');
	}

	$ob_hash = get_observer_hash();
	if (!$ob_hash
		&& (intval($b['item']['author']['xchan_censored']) || intval($b['item']['author']['xchan_selfcensored']))
	) {
		$matchingRule = t('Possible adult content');
	}

	$rnd = random_string(8);

	$b['html'] = preg_replace('~<img[^>]*\K(?=src)~i','data-',$b['html']);

	if($b['photo']) {
		$b['photo'] = preg_replace('~<img[^>]*\K(?=src)~i','data-',$b['photo']);
		$onclick = 'onclick="datasrc2src(\'#nsfw-html-' . $rnd . ' img[data-src]\'); datasrc2src(\'#nsfw-photo-' . $rnd . ' img[data-src]\'); openClose(\'nsfw-html-' . $rnd . '\'); openClose(\'nsfw-photo-' . $rnd . '\');"';
	}
	else {
		$onclick = 'onclick="datasrc2src(\'#nsfw-html-' . $rnd . ' img[data-src]\'); openClose(\'nsfw-html-' . $rnd . '\');"';
	}

	$b['html'] = '<div class="text-center"><button id="nsfw-wrap-' . $rnd . '" class="btn btn-warning btn-nsfw-wrap" type="button" ' . $onclick . '>' . sprintf( t('%s - view'), $matchingRule) . '</button></div><div id="nsfw-html-' . $rnd . '" style="display: none; " class="no-collapse">' . $b['html'] . '</div>';
	$b['photo'] = (($b['photo']) ? '<div id="nsfw-photo-' . $rnd . '" style="display: none; " >' . $b['photo'] . '</div>' : '');

}
