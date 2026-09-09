<?php

use Zotlabs\Web\HTTPSig;
use Zotlabs\Lib\Config;
use GuzzleHttp\Psr7\Request;
use HttpSignature\HttpMessageSigner;

require_once('include/cli_startup.php');
require_once('include/attach.php');
require_once('include/import.php');

	cli_startup();

	$attach_id = $argv[1];
	$channel_address = $argv[2];
	$hz_server = urldecode($argv[3]);

	$m = parse_url($hz_server);

	$channel = channelx_by_nick($channel_address);
	if(! $channel) {
		logger('filehelper: channel not found');
		killme();
	}

	if (Config::Get('system', 'send_rfc9421')) {
		$signer = new HttpMessageSigner();
		$request = new Request(
			'GET',
			$hz_server . '/api/z/1.0/file/export?file_id=' . $attach_id,
			[
				'X-API-Token' => random_string(),
				'X-API-Request' => $hz_server . '/api/z/1.0/file/export?file_id=' . $attach_id,
				'Host' => $m['host'],
				'Date' => gmdate('D, d M Y H:i:s T'),
			]
		);

		$signer->setPrivateKey($channel['channel_prvkey'])
			->setAlgorithm('rsa-v1_5-sha256')
			->setKeyId(channel_url($channel))
			->setCreated(time())
			->setExpires(time() + 3600);

		$coveredFields = '("@method" "@target-uri" "host" "date" "x-api-token" "x-api-request")';
		$request = $signer->signRequest($coveredFields, $request);
		$signedHeaders = $signer->getHeaders($request);
		$curlHeaders = [];
		foreach ($signedHeaders as $key => $value) {
			$curlHeaders[] = $key . ': ' . $value;
		}
	}
	else {
		$headers = [
			'X-API-Token'      => random_string(),
			'X-API-Request'    => $hz_server . '/api/z/1.0/file/export?file_id=' . $attach_id,
			'Host'             => $m['host'],
			'(request-target)' => 'get /api/z/1.0/file/export?file_id=' . $attach_id,
		];

		$curlHeaders = HTTPSig::create_sig($headers, $channel['channel_prvkey'], channel_url($channel), true, 'sha512');
	}

	$x = z_fetch_url($hz_server . '/api/z/1.0/file/export?file_id=' . $attach_id, false, 0, ['headers' => $curlHeaders]);

	if(! $x['success']) {
		logger('no API response',LOGGER_DEBUG);
		return;
	}

	$j = json_decode($x['body'],true);

	$r = sync_files($channel,[$j]);

	killme();

