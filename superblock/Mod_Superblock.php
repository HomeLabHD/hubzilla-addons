<?php
/*
 * SPDX-FileCopyrightText: 2025 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Module;

use App;
use Zotlabs\Addons\Superblock\Superblock as Plugin;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Config;
use Zotlabs\Lib\Libsync;
use Zotlabs\Web\Controller;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Superblock module controller.
 *
 * This module implements the request handler (Controller) for the main
 * Superblock view.
 */
class Superblock extends Controller {

	/**
	 * The local channel id, or false.
	 */
	private int $localChannel;

	/**
	 * True if it's a json request.
	 */
	private bool $is_json_request;

	/**
	 * The request method used for this request.
	 */
	private string $request_method;

	/**
	 * True if the Superblock app is installed for the channel making the
	 * request.
	 */
	private bool $app_installed;

	/**
	 * Default constructor to initialize the state of the controller.
	 */
	public function __construct() {
		$this->localChannel = local_channel();
		$this->is_json_request =
			$_SERVER['HTTP_CONTENT_TYPE'] === 'application/json';

		$this->request_method = $_SERVER['REQUEST_METHOD'];

		$this->app_installed = $this->localChannel
			? Apps::addon_app_installed($this->localChannel, 'superblock')
			: false;
	}

	/**
	 * The init function is called before the actual request processing begins.
	 */
	public function init(): void {
		//
		// Validate access to the module here.
		//
		// Since the init function will be invoked before any of the other
		// functions, we can validate the acces once and for all here.
		//
		$this->validate_access();
	}

	/**
	 * Handle POST requests to the superblock endpoint.
	 *
	 * This will typically be invoked asynchronously through an AJAX call,
	 * where the request parameters are passed in as a json object in the
	 * request body. In this case it will also return a json object with the
	 * result of the action in the response body, and processing terminates.
	 *
	 * It can also handle being invoked as HTML form as it's payload, in which
	 * case the request parameters will be found in the PHP `$_POST`
	 * superglobal, as normally for PHP. In this case the user is informed
	 * about the result in a notification, and we fall through to the `get`
	 * method for generating the HTML response to the request.
	 */
	public function post(): void {
		$params = $this->validate_params();
		$this->check_security_token($params['form_security_token']);

		$plugin = Plugin::getInstance($this->localChannel);

		$msg = '';

		switch ($params['action']) {
			case 'block':
				$xchan = xchan_fetch(['hash' => $params['author']]);
				if (!$xchan) {
					$this->error(400, 'Invalid or unknown channel');
				}

				$plugin->blockChannel($xchan['hash']);
				$msg = "blocked {$xchan['address']} permanently";
				break;

			case 'unblock':
				$xchan = xchan_fetch(['hash' => $params['author']]);
				if (!$xchan) {
					$this->error(400, 'Invalid or unknown channel');
				}

				$plugin->unblockChannel($params['author']);
				$msg = "removed {$xchan['address']} from block list";
				break;

			case 'siteblock':
				if (!is_site_admin()) {
					$this->error(403, 'You do not have access to perform this operation');
				}

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
				$msg = "Added {$author_xchan['address']} to site block list";
				break;

			default:
				$this->error(400, 'No action given');
		}

		if ($plugin->configChanged()) {
			$plugin->save();
			Libsync::build_sync_packet(local_channel(), [ 'config' ]);

			$this->success($msg);
		}
	}

	/**
	 * Handle GET requests to the superblock endpoint.
	 *
	 * This also renders the result of a POST request with a HTML form payload.
	 *
	 * Renders a list of blocked channels, as well as actions for manipulating the
	 * list.
	 *
	 * @return string	The rendered HTML of the request.
	 */
	function get(): string {

		$config_changed = false;

		if (!$this->app_installed) {
			//Do not display any associated widgets at this point
			App::$pdl = '';
			$papp = Apps::get_papp('Superblock');
			return Apps::app_render($papp, 'module');
		}

		$plugin = Plugin::getInstance($this->localChannel);
		$plugin->loadJavaScript();

		$list = $plugin->getBlockedChannels();
		stringify_array_elms($list,true);
		$query_str = implode(',',$list);
		if($query_str) {
			$r = q("select * from xchan where xchan_hash in ( " . $query_str . " ) and xchan_hash != '' ");
		}
		else {
			$r = [];
		}

		$tpl = get_markup_template('superblock_list.tpl','addon/superblock');

		return replace_macros($tpl, [
			'$title' => t('Currently blocked channels'),
			'$entries' => $r,
			'$nothing' => (($r) ? '' : t('No channels currently blocked')),
			'$token' => get_form_security_token('superblock'),
			'$remove' => t('Remove from blocklist')
		]);
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
			$this->error(401, 'Unauthorized');
		}

		// Redirect POST requests to the APP description page if the APP is
		// not installed
		if ($this->request_method === 'POST' && !$this->app_installed) {
			goaway('/superblock');
		}
	}

	/**
	 * Validate and extract parameters for POST requests.
	 *
	 * Returns an array of parameters passed in after validating and sanitizing
	 * them. The parameters can be passed as either a JSON object if this is an
	 * AJAX request, or as a HTML form payload.
	 */
	private function validate_params(): array {
		if ($this->is_json_request) {
			$data = json_decode(file_get_contents('php://input'), true);
		} else {
			$data = $_POST;
		}

		logger("Superblock POST: " . print_r($data, true), LOGGER_DEBUG);

		return filter_var_array(
			$data,
			[
				'action' => [
					'filter' => FILTER_VALIDATE_REGEXP,
					'options' => ['regexp' => '/^(block|siteblock|unblock)$/']
				],
				'author' => [
					'filter' => FILTER_DEFAULT,
				],
				'form_security_token' => [
					'filter' => FILTER_DEFAULT,
				],
			],
			true
		);
	}

	/**
	 * Function to wrap check_form_security_token, so we can verify the token
	 * regardless of where it originates.
	 *
	 * **Note:** This function will only return if the token is valid.
	 *
	 * @param string $token		The token to check.
	 */
	private function check_security_token(string $token): void {
		//
		// Since `check_form_security_token` is hardcoded to only check the
		// `$_REQUEST` superglobal for the token (a really bad idea!), we have
		// to stuff our token into the superglobal to satisfy the call
		//
		$_REQUEST['form_security_token'] = $token;

		if (!check_form_security_token('superblock')) {
			$this->error(403, "Invalid or missing security token");
		}
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
			http_status_exit($status, $message);
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
	 * @param string $message		Success message for the calling user.
	 */
	private function success(string $message): void {
		info($message);
		if ($this->is_json_request) {
			json_return_and_die([ 'status' => 'success', 'message' => $message ]);
		}
	}

}
