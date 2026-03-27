<?php

/**
 * Name: Simple Notes
 * Description: The Hubzilla counterpart to the [url=https://github.com/inventory69/simple-notes-sync]Simple Notes Sync[/url] app
 * Version: 0.1
 * Author: Mario Vavti
 * Maintainer: Mario Vavti
 */


use Zotlabs\Extend\Route;
use Zotlabs\Extend\Widget;

function simplenotes_install() {
	Route::register('addon/simplenotes/Mod_Simplenotes.php', 'simplenotes');
	Route::register('addon/simplenotes/Settings/Simplenotes.php', 'settings/simplenotes');
	Widget::register('addon/simplenotes/Widget/Simplenotes_control.php', 'simplenotes_control');
}


function simplenotes_uninstall() {
	Route::unregister('addon/simplenotes/Mod_Simplenotes.php', 'simplenotes');
	Route::unregister('addon/simplenotes/Settings/Simplenotes.php', 'settings/simplenotes');
	Widget::unregister('addon/simplenotes/Widget/Simplenotes_control.php', 'simplenotes_control');
}
