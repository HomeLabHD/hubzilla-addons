<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Config;
use Zotlabs\Lib\Libsync;
use Zotlabs\Web\Controller;

class Superblock extends Controller {

	/**
	 * The local channel id, or false.
	 */
	private $localChannel = false;

	/**
	 * True if it's a json request.
	 */
	private $is_json_request = false;

	/**
	 * Initialize the state needed for further request handling.
	 */
	public function init(): void {
		$this->localChannel = local_channel();
		$this->is_json_request =
			$_SERVER['HTTP_CONTENT_TYPE'] === 'application/json';
	}

	public function post(): void {
		$this->validate_access();
		$params = $this->validate_params();

		switch ($params['action']) {
			case 'siteblock':
				$author = $params['author'];
				if (!$author) {
					$this->error(400, 'Invalid xchan');
				}

				$author_xchan = xchan_fetch(['hash' => $author]);
				if (!$author_xchan) {
					$this->error(400, 'Unknown author');
				}

				$blocked = Config::Get('system', 'blacklisted_channels', '');
				if (!in_array($author_xchan['hash'], $blocked)) {
					$blocked[] = $author_xchan['hash'];
					sort($blocked);
					Config::Set('system', 'blacklisted_channels', $blocked);
				}
				$this->success($author_xchan['hash']);
				break;

			default:
				$this->error(400, 'No action given');
		}
	}

	function get() {

		if(! local_channel())
			return;

		if(! Apps::addon_app_installed(local_channel(), 'superblock')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';
			$papp = Apps::get_papp('Superblock');
			return Apps::app_render($papp, 'module');
		}

		$words = get_pconfig(local_channel(),'system','blocked');

		//TODO: move this (config changes) to post()

		if(array_key_exists('block',$_GET) && $_GET['block']) {
			$r = q("select id from item where id = %d and author_xchan = '%s' limit 1",
				intval($_GET['item']),
				dbesc($_GET['block'])
			);
			if($r) {
				if(strlen($words))
					$words .= ',';
				$words .= trim($_GET['block']);
			}
			$config_changed = true;
		}

		if(array_key_exists('unblock',$_GET) && $_GET['unblock']) {
			if(check_form_security_token('superblock','sectok')) {
				$newlist = [];
				$list = explode(',',$words);
				if($list) {
					foreach($list as $li) {
						if($li !== $_GET['unblock']) {
							$newlist[] = $li;
						}
					}
				}

				$words = implode(',',$newlist);
			}
			$config_changed = true;
		}

		if($config_changed) {
			set_pconfig(local_channel(),'system','blocked',$words);
			Libsync::build_sync_packet(local_channel(), [ 'config' ]);

			info( t('superblock settings updated') . EOL );
		}

		if(! $words)
			$words = '';

		$list = explode(',',$words);
		stringify_array_elms($list,true);
		$query_str = implode(',',$list);
		if($query_str) {
			$r = q("select * from xchan where xchan_hash in ( " . $query_str . " ) and xchan_hash != '' ");
		}
		else
			$r = [];

		if($r) {
			for($x = 0; $x < count($r); $x ++) {
				$r[$x]['encoded_hash'] = urlencode($r[$x]['xchan_hash']);
			}
		}

		$tpl = get_markup_template('superblock_list.tpl','addon/superblock');

		$o = replace_macros($tpl, [
			'$blocked' => t('Currently blocked'),
			'$entries' => $r,
			'$nothing' => (($r) ? '' : t('No channels currently blocked')),
			'$token' => get_form_security_token('superblock'),
			'$remove' => t('Remove')
		]);

		return $o;

	}

	/**
	 * Validates access to the module.
	 *
	 * If this function returns, access is granted. Otherwise it will send an
	 * appropriate error status, or redirect if the addon is not installed for
	 * the requesting channel.
	 *
	 * Requires that the $localChannel private attribute is set before this
	 * function is called.
	 *
	 * **Note:** This function will not return if access is not granted.
	 */
	private function validate_access(): void {
		if (!$this->localChannel) {
			$this->error(403, 'Forbidden');
		}

		if (!Apps::addon_app_installed($this->localChannel, 'superblock')) {
			goaway('/superblock');
		}
	}

	private function validate_params(): array {
		if ($this->is_json_request) {
			$data = json_decode(file_get_contents('php://input'), true);
		} else {
			$data = $_POST;
		}

		error_log("[*] Superblock POST: " . print_r($data, true));

		return filter_var_array(
			$data,
			[
				'action' => [
					'filter' => FILTER_VALIDATE_REGEXP,
					'options' => ['regexp' => '/^siteblock$/']
				],
				'author' => [
					'filter' => FILTER_DEFAULT,
				],
			],
			true
		);
	}

	/**
	 * Return an error status for the request.
	 *
	 * If the request is an ajax request, a json object with `$message` is
	 * returned. In any case the HTTP status code is set to `$status`.
	 *
	 * **Note:** This function will not return.
	 *
	 * @param int $status		The HTTP status code to return.
	 * @param string $message	The error message, only used for ajax requests.
	 */
	private function error(int $status, string $message): void {
		if ($this->is_json_request) {
			http_status($status);
			json_return_and_die([ 'status' => 'error', 'message' => $message ]);
		} else {
			http_status_exit($status);
		}
	}

	/**
	 * Signal that the request was successful.
	 *
	 * If it's an ajax request, a json object is returned to the client,
	 * otherwise we just prima a notice and let the request fall through
	 * to the normal processing.
	 *
	 * **Note:** This function will not return if the request was a json
	 * request.
	 *
	 * @param string $channel		The channel address that was blocked.
	 */
	private function success(string $channel): void {
		$msg = t("{$channel} was added to the sitewide block list.");
		if ($this->is_json_request) {
			json_return_and_die([ 'status' => 'success', 'message' => $msg ]);
		} else {
			info($msg);
		}
	}

}
