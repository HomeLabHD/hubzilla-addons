<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Cache;
use Zotlabs\Lib\Config;
use Zotlabs\Web\Controller;

class Wopi extends Controller {

	function init() {

	}

	function post() {
		$token = self::get_bearer_token();
		if (!$token) {
			logger("Error: Bearer token not found.");
			killme();
		}

		$meta = Cache::get($token, '30 MINUTE');
		if (!$meta) {
			logger("Error: Data not found in cache for token: $token");
			killme();
		}

		$meta = json_unserialize($meta);
		if (!$meta) {
			logger("Error: Failed to unserialize data for token: $token");
			killme();
		}

		if (empty($meta['write_perms']) || !$meta['write_perms']) {
			logger("Error: Insufficient write permissions for user: " . $meta['observer']['xchan_hash']);
			killme();
		}

		$channel = channelx_by_n($meta['file']['uid']);
		if (!$channel) {
			logger("Error: Channel not found for uid: " . $meta['file']['uid']);
			killme();
		}

		$observer_hash = $meta['observer']['xchan_hash'];

		$fileContent = file_get_contents('php://input');
		if ($fileContent === false) {
			logger("Error: Failed to read input stream.");
			killme();
		}

		$filePath = $meta['file']['content'];
		$meta['file']['filesize'] = file_put_contents($filePath, $fileContent);
		if ($meta['file']['filesize'] === false) {
			logger("Error: Failed to save file content to: $filePath");
			killme();
		}

		$meta['file']['edited'] = datetime_convert();

		$x = attach_store($channel, $observer_hash, 'update', $meta['file']);

		if ($x['success']) {
			json_return_and_die([
				'Status' => 'OK',
				'FileId' => $x['data']['id'],
				'FileSize' => $meta['file']['filesize'],
				'UserId' => $meta['observer']['xchan_hash'] ?? hash('whirlpool', session_id()),
				'LastModifiedTime' => $x['data']['edited']
			]);
		}

		logger("Error: Failed to store attachment.");
		killme();
	}

	function get() {
		$wopi_client_url = Config::Get('system', 'wopi_client_url', '');

		if (!$wopi_client_url) {
			return;
		}

		// Handle WOPI client requests
		if (argv(1) === 'files') {
			$token = self::get_bearer_token();
			$meta = $token ? Cache::get($token, '30 MINUTE') : null;

			if ($meta) {
				$meta = json_unserialize($meta);
			}

			$file_id = argv(2);
			$file = attach_by_id($file_id, $meta['observer']['xchan_hash'] ?? '');

			if (!$file['success']) {
				return $file['message'];
			}

			if (argc() === 3) {
				$arr = [
					'BaseFileName' => $meta['file']['filename'],
					'Size' => $meta['file']['filesize'],
					'OwnerId' => $meta['file']['creator'],
					'UserId' => $meta['observer']['xchan_hash'] ?? hash('whirlpool', session_id()),
					'UserFriendlyName' => $meta['observer']['xchan_name'] ?? 'anonymous_' . substr(hash('sha256', session_id()), 0, 8),
					'UserExtraInfo' => [
						'avatar' => $meta['observer']['xchan_photo_s'] ?? null
					],
					'UserCanWrite' => $meta['write_perms'] ?? null,
					'UserCanRename' => false,
					'SupportsRename' => false,
					'IsAnonymousUser' => $meta['observer'] === null,
					'LastModifiedTime' => $meta['file']['edited']
				];

				json_return_and_die($arr);
			}

			if (argc() === 4 && argv(3) === 'contents') {
				echo file_get_contents($file['data']['content']);
			}

			killme();
		}

		// Init redirect to WOPI client if applicable

		$file_id = argv(1);

		if (!$file_id) {
			return;
		}

		$observer = App::get_observer();
		$file = attach_by_id($file_id, $observer['xchan_hash'] ?? '');

		if (!$file['success']) {
			return $file['message'];
		}

		$file = $file['data'];

		$meta = [
			'file' => $file,
			'observer' => $observer,
			'write_perms' => perm_is_allowed($file['uid'], $observer['xchan_hash'], 'write_storage')
		];

		$token = random_string(32);
		Cache::set($token, json_serialize($meta));

		$encoded_url = urlencode(z_root() . "/wopi/files/$file_id");

		$discovery = file_get_contents($wopi_client_url . '/hosting/discovery');
		if (!$discovery) {
			http_status_exit(500, 'Service not available');
		}

		$discovery_parsed = simplexml_load_string($discovery);

		self::cache_supported_types($discovery_parsed);

		$result = $discovery_parsed->xpath(sprintf('/wopi-discovery/net-zone/app[@name=\'%s\']/action', $file['filetype']));

		if ($result) {
			goaway($result[0]['urlsrc'] . "WOPISrc=$encoded_url&access_token=$token&closebutton=true");
		}
	}

	static function get_bearer_token() {
		foreach (['REDIRECT_REMOTE_USER', 'HTTP_AUTHORIZATION'] as $header) {
			$auth = $_SERVER[$header] ?? '';
			if (str_starts_with($auth, 'Bearer ')) {
				return substr($auth, 7);
			}
		}

		return '';
	}

	static function cache_supported_types($xml_parsed) {
		$supported_types = Cache::get('wopi_supported_mime_types', '1 Day');

		if ($supported_types) {
			return;
		}

		foreach ($xml_parsed->xpath('//app') as $app) {
			$type = (string) $app['name'];
			if (str_contains($type, '/')) {
				$supported_types[] = $type;
			}
		}

		Cache::set('wopi_supported_mime_types', json_encode($supported_types));
	}
}
