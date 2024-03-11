<?php

use Zotlabs\Lib\Cache;
use Zotlabs\Lib\Config;

/**
 * Name: Emojione
 * Description: Emoji emoticons
 * Version: 1.0
 *
 */

function emojione_load() {
	\Zotlabs\Extend\Hook::register('get_emojis','addon/emojione/emojione.php', [ '\\Emojione' , 'get_emojis' ]);
}

function emojione_unload() {
	\Zotlabs\Extend\Hook::unregister('get_emojis','addon/emojione/emojione.php', [ '\\Emojione' , 'get_emojis' ]);
}

class Emojione {
	static public function get_emojis(&$arr) {

		// JSON Source: https://raw.githubusercontent.com/muan/unicode-emoji-json/main/data-by-emoji.json

		$set = Config::Get('system', 'emoji_set', 'emojitwo');

		$emojis = Cache::get('emoji_set_' . $set, '1 DAY');

		if ($emojis) {
			$arr = array_merge(json_decode($emojis, true), $arr);
			return;
		}

		$emojis = json_decode(@file_get_contents('addon/emojione/emoji.json'), true);

		foreach($emojis as $emoji => $info) {
			$code = self::convert_emoji($emoji);
			$name = $info['slug'];
			$shortname = ':' . $name . ':';

			// We can add various quirks here
			switch ($set) {
				case 'openmoji':
					$pathcode = strtoupper($code);
					break;
				default:
					$pathcode = $code;
			}

			$filepath = 'addon/emojione/' . $set . '/' . $pathcode . '.png';

			if (!file_exists($filepath)) {
				continue;
			}

			$e['shortname'] = $shortname;
			$e['filepath'] = $filepath;
			$e['code'] = $code;

			$arr[$name] = $e;
		}

		Cache::set('emoji_set_' . $set, json_encode($arr));

	}

	static function convert_emoji($emoji) {
		$emoji = mb_convert_encoding($emoji, 'UTF-32', 'UTF-8');
		$hex = bin2hex($emoji);

		$hex_len = strlen($hex) / 8;
		$chunks = [];

		for ($i = 0; $i < $hex_len; ++$i) {
			$tmp = substr($hex, $i * 8, 8);
			$chunks[$i] = self::format($tmp);
		}
		return implode('-', $chunks);
	}

	static function format($str) {
		$copy = false;
		$len = strlen($str);
		$res = '';

		for ($i = 0; $i < $len; ++$i) {
			$ch = $str[$i];

			if (!$copy) {
				if ($ch != '0') {
					$copy = true;
				}
				else if (($i + 1) == $len) {
					$res = '0';
				}
			}

			if ($copy) {
				$res .= $ch;
			}
		}

		return $res;
	}

}
