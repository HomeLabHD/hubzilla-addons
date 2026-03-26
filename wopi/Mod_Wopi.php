<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Cache;
use Zotlabs\Lib\Config;
use Zotlabs\Lib\Keyutils;
use Zotlabs\Lib\Crypto;
use Zotlabs\Web\Controller;

class Wopi extends Controller {

	function init() {

	}

	function post() {
		if (argc() !== 4 && argv(3) !== 'contents') {
			logger("Error: could not handle request.");
			http_status_exit(400, 'Bad Request');
		}

		$token = self::get_bearer_token();
		if (!$token) {
			logger("Error: Bearer token not found.");
			http_status_exit(401, 'Unauthorized');
		}

		$force_signed_request = Config::Get('system', 'wopi_force_signed_requests');
		if ($force_signed_request && !self::verifyProof($token)) {
			logger("Error: could not verify proof.");
			http_status_exit(403, 'Forbidden');
		}

		$meta = Cache::get($token, '30 MINUTE');
		if (!$meta) {
			logger("Error: Data not found in cache for token: $token");
			http_status_exit(401, 'Unauthorized');
		}

		$meta = json_unserialize($meta);
		if (!$meta) {
			logger("Error: Failed to unserialize data for token: $token");
			http_status_exit(500, 'Internal Server Error');
		}

		if ($meta['write_perms'] !== true) {
			logger("Error: Insufficient write permissions for user: " . $meta['observer']['xchan_hash']);
			http_status_exit(403, 'Forbidden');
		}

		$channel = channelx_by_n($meta['file']['uid']);
		if (!$channel) {
			logger("Error: Channel not found for uid: " . $meta['file']['uid']);
			http_status_exit(410, 'Gone');
		}

		$observer_hash = $meta['observer']['xchan_hash'];

		$fileContent = file_get_contents('php://input');
		if ($fileContent === false) {
			logger("Error: Failed to read input stream.");
			http_status_exit(500, 'Internal Server Error');
		}

		$filePath = $meta['file']['content'];
		if (!str_starts_with($filePath, 'store/' . $channel['channel_address'] . '/')) {
			logger("Error: Filepath not allowed: $filePath");
			http_status_exit(500, 'Internal Server Error');
		}

		$meta['file']['filesize'] = file_put_contents($filePath, $fileContent);
		if ($meta['file']['filesize'] === false) {
			logger("Error: Failed to save file content to: $filePath");
			http_status_exit(500, 'Internal Server Error');
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
		http_status_exit(500, 'Internal Server Error');
	}

	function get() {
		$wopi_client_url = Config::Get('system', 'wopi_client_url', '');

		if (!$wopi_client_url) {
			http_status_exit(501, 'Not Implemented');
		}

		// Handle WOPI client requests
		if (argv(1) === 'files') {
			$token = self::get_bearer_token();

			if (!$token) {
				logger("Error: Bearer token not found.");
				http_status_exit(401, 'Unauthorized');
			}

			$force_signed_request = Config::Get('system', 'wopi_force_signed_requests');
			if ($force_signed_request && !self::verifyProof($token)) {
				logger("Error: could not verify proof.");
				http_status_exit(403, 'Forbidden');
			}

			$meta = $token ? Cache::get($token, '30 MINUTE') : null;

			if ($meta) {
				$meta = json_unserialize($meta);
			}

			$file_id = argv(2);
			$file = attach_by_id($file_id, $meta['observer']['xchan_hash'] ?? '');

			if (!$file['success']) {
				http_status_exit(404, 'Not Found');
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
					'UserCanNotWriteRelative' => true,
					'IsAnonymousUser' => $meta['observer'] === null,
					'LastModifiedTime' => $meta['file']['edited'],
					'IsAdminUser' => false // TODO: check if admin and set real value
				];

				json_return_and_die($arr);
			}

			if (argc() === 4 && argv(3) === 'contents') {
				http_response_code(200);
				echo file_get_contents($file['data']['content']);
				killme();
			}

			http_status_exit(400, 'Bad Request');
		}

		// Init redirect to WOPI client if applicable

		$file_id = argv(1);

		if (!$file_id) {
			http_status_exit(400, 'Bad Request');
		}

		$observer = App::get_observer();
		$file = attach_by_id($file_id, $observer['xchan_hash'] ?? '');

		if (!$file['success']) {
			http_status_exit(403, 'Forbidden');
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
			http_status_exit(503, 'Service Unavailable');
		}

		$discovery_parsed = simplexml_load_string($discovery);

		self::cache_supported_types($discovery_parsed);
		self::cache_wopiproof_pubkey($discovery_parsed);

		$result = $discovery_parsed->xpath(sprintf('/wopi-discovery/net-zone/app[@name=\'%s\']/action', $file['filetype']));

		if ($result) {
			goaway($result[0]['urlsrc'] . "WOPISrc=$encoded_url&access_token=$token&closebutton=true");
		}

		http_status_exit(415, 'Unsupported Media Type');
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

	static function cache_wopiproof_pubkey($xml_parsed) {
		// TODO: implement key rotation handling (oldmodulus, oldexponent) -> wopi_proof_oldpubkey, etc.

		if (Config::Get('system', 'wopi_proof_pubkey')) {
			return;
		}

		$result = $xml_parsed->xpath('/wopi-discovery/proof-key');

		if (!$result) {
			return;
		}

		$m = base64_decode($result[0]['modulus']);
		$e = base64_decode($result[0]['exponent']);

		$key = Keyutils::meToPem($m, $e);

		if ($key) {
			Config::Set('system', 'wopi_proof_pubkey', $key);
		}

	}

	static function verifyProof($token) {
		// TODO: implement key rotation handling with wopi_proof_oldpubkey

		$url = z_root() . $_SERVER['REQUEST_URI'];
		$timestamp = $_SERVER['HTTP_X_WOPI_TIMESTAMP'] ?? null;

		if (!$timestamp) {
			return false;
		}

		$expected_proof = sprintf(
			'%s%s%s%s%s%s',
			pack('N', strlen($token)),
			$token,
			pack('N', strlen($url)),
			strtoupper($url),
			pack('N', PHP_INT_SIZE),
			pack('J', $timestamp)
		);

		$proof = $_SERVER['HTTP_X_WOPI_PROOF'] ?? null;

		if (!$proof) {
			return false;
		}

		$signature = base64_decode($proof, true);

		if (!$signature) {
			return false;
		}

		$key = Config::Get('system', 'wopi_proof_pubkey');

		if (!$key) {
			return false;
		}

		return Crypto::verify($expected_proof, $signature, $key);
	}

}
