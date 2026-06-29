<?php
/*
 * SPDX-FileCopyrightText: 2025 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Module;

use App;
use DateTimeImmutable;
use Zotlabs\Addons\Superblock\Superblock as Plugin;
use Zotlabs\Addons\Superblock\Views\ChannelBlockEntry;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Config;
use Zotlabs\Lib\Libsync;
use Zotlabs\Web\Controller;

require_once __DIR__ . '/../addon_common/vendor/autoload.php';

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
	 * Validated parameters to the request.
	 */
	private ?array $params = null;

	/**
	 * The Superblock Plugin instance for this request
	 */
	private Plugin $plugin;

	/**
	 * Default constructor to initialize the state of the controller.
	 */
	public function __construct() {
		$this->localChannel = local_channel();
		$this->request_method = $_SERVER['REQUEST_METHOD'];
		$this->is_json_request =
			getBestSupportedMimeType('application/json') === 'application/json'
			|| (isset($_SERVER['HTTP_CONTENT_TYPE']) && $_SERVER['HTTP_CONTENT_TYPE'] === 'application/json')
			|| ($this->request_method === 'POST' && empty($_POST));


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

		$this->plugin = Plugin::getInstance($this->localChannel);
	}

	/**
	 * Handle POST requests to the superblock endpoint.
	 *
	 * This will typically be invoked asynchronously through an AJAX call,
	 * where the request parameters are passed in as a json object in the
	 * request body. In this case it will also return a json object with the
	 * result of the action in the response body, and processing terminates.
	 *
	 * It can also handle being invoked via a HTML form, in which
	 * case the request parameters will be found in the PHP `$_POST`
	 * superglobal, as normal. In this case the user is informed
	 * about the result in a notification, and we fall through to the `get`
	 * method for generating the HTML response to the request.
	 *
	 * The request parameters are:
	 *
	 *   - `action`: The action to perform (block, unblock or siteblock).
	 *   - `author`: The author (channel) to block, either as an xchan hash or webbie.
	 *   - `form_security_token`: CSRF token.
	 *   - `item`: The item that the block is initiated from (unused at the moment).
	 *
	 * The `block` and `unblock` actions affect the block list for the channel invoking
	 * the actions. The `siteblock` action affects the site wide block list, and is
	 * only available to site administrators.
	 */
	public function post(): void {
		$this->processPostRequest();

		if (!empty($this->error) && $this->is_json_request) {
			http_status($this->error['status']);
			json_return_and_die($this->error);
		}
	}

	private function processPostRequest(): void {
		if (!$this->validate_params()) {
			return;
		}

		if (!$this->check_security_token($this->params['form_security_token'])) {
			return;
		}

		$xchan = $this->findXChanFromAuthor($this->params['author']);
		if (!$xchan) {
			$this->error(400, t('Invalid or unknown channel'));
			return;
		}

		$plugin = Plugin::getInstance($this->localChannel);

		$msg = '';

		switch ($this->params['action']) {
			case 'block':
				$until = $this->getExpirationDate();
				$plugin->blockChannel($xchan['hash'], $until);

				if ($until) {
					$msg = sprintf(
						t('temporarily blocked %1$s until %2$s'),
						$xchan['address'],
						$until->format(DateTimeImmutable::ISO8601)
					);
				} else {
					$msg = sprintf(
						t('blocked %s permanently'),
						$xchan['address']
					);
				}
				break;

			case 'unblock':
				$plugin->unblockChannel($xchan['hash']);
				$msg = sprintf(t('removed %s from block list'), $xchan['address']);
				break;

			case 'siteblock':
				$blocked = Config::Get('system', 'blacklisted_channels', []);
				if (!in_array($xchan['hash'], $blocked)) {
					$blocked[] = $xchan['hash'];
					sort($blocked);
					Config::Set('system', 'blacklisted_channels', $blocked);
				}
				$msg = sprintf(t('added %s to site block list'), $xchan['address']);
				$this->success($msg);
				break;

			default:
				$this->error(400, t('No action given'));
				return;
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
		if (!$this->app_installed) {
			//Do not display any associated widgets at this point
			App::$pdl = '';
			$papp = Apps::get_papp('Superblock');
			return Apps::app_render($papp, 'module');
		}

		if (argc() == 2) {
		    if (argv(1) === "add") {
				$author_hash = filter_input(INPUT_GET, 'author', FILTER_VALIDATE_REGEXP, [
					'options' => [
						'regexp' => '/[a-zA-Z0-9@:\/_-]+/',
						'default' => null
					]
				]);

				if ($author_hash) {
					$xchan = $this->findXChanFromAuthor($author_hash);
					$author = $xchan['address'] ?? '';
				} else {
					$author = '';
				}

				echo $this->renderAddBlockForm($author);
				killme();
			}
		}

		// Return page not found for all other paths not
		// implemented.
		if (argc() !== 1) {
			http_status_exit(404);
		}

		// Return the block list for the addon base path
		return $this->renderBlockList();
	}

	private function renderBlockList(): string {
		$config_changed = false;

		$this->plugin->loadJavaScript();
		$this->plugin->loadStyleSheet();

		$token = get_form_security_token('superblock');

		$entries = [];
		$list = $this->plugin->getBlockedChannels();
		$query_str = implode(',', array_map(fn ($cb) => "'" . dbesc($cb->hash) . "'", $list));
		if ($query_str) {
			$r = q("select * from xchan where xchan_hash in ( " . $query_str . " ) and xchan_hash != '' ");
			foreach ($list as $cb) {
				$xchan = array_find($r, fn ($xchan) => $xchan['xchan_hash'] === $cb->hash);
				$entries[] = new ChannelBlockEntry($token, $cb, $xchan);
			}
		}
		else {
			$r = [];
		}

		$tpl = get_markup_template('superblock_list.tpl','addon/superblock');

		return replace_macros($tpl, [
			'$addonTitle' => t('Superblock'),
			'$title' => t('Your blocked channels'),
			'$newEntry' => t('Add new block'),
			'$entries' => array_map(fn($e) => $e->render(), $entries),
			'$token' => $token,
			'$nothing' => t('No channels currently blocked'),
		]);
	}

	private function renderAddBlockForm($author = ''): string {
		$tpl = get_markup_template('superblock_add_block_form.tpl','addon/superblock');
		return replace_macros($tpl, [
			'$token' => get_form_security_token('superblock'),
			'$authorInputField' => [
				'author',						// name, id
				t('Channel address (webbie):'),	// label
			   	$author,						// value
				t('The address of the channel to block, typically like \'channel@example.com\'.'), // help text
				'',								// additional label
				'',								// additional attributes
			],
			'$expireInputField' => replace_macros(get_markup_template('field_duration.qmc.tpl'), [
				'label' => t('Block this channel for'),
				'help' => t('How long to block activities from this channel, leave as 0 to block forever.'),
				'wrapper' => 'yes',
				'qmc' => 'superblock_',
				'field' => [
					'name' => 'until',
					'min' => 0,
					'max' => 99,
					'size' => 13,
					'value' => 0,
					'title' => t('Something....'),
					'default' => 'years'
				],
				'rabot' => [
					'mins' => 'Minute(s)',
					'hours' => 'Hour(s)',
					'days' => 'Day(s)',
					'weeks' => 'Week(s)',
					'months' => 'Month(s)',
					'Years' => 'Years',
				],
			]),
			'$blockChannelButtonText' => t('Block channel!'),
			'$title' => t('Superblock: Add new entry'),
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
	 * The validated parameters are saved in the `$this->params` property.
	 * Validates parameters passed in as either form params, or a JSON object.
	 *
	 * @sideeffect Modifies the `$params` property.
	 *
	 * @return bool `true` if the passed in params are valid, `false` otherwise.
	 */
	private function validate_params(): bool {
		if ($this->is_json_request) {
			$data = json_decode(file_get_contents('php://input'), true);
		} else {
			$data = $_POST;
		}

		logger("Superblock POST: " . print_r($data, true), LOGGER_DEBUG);

		$this->params = filter_var_array(
			$data,
			[
				'action' => [
					'filter' => FILTER_VALIDATE_REGEXP,
					'options' => ['regexp' => '/^(block|siteblock|unblock)$/']
				],
				'author' => [
					'filter' => FILTER_DEFAULT,
				],
				'superblock_until' => [
					'filter' => FILTER_VALIDATE_REGEXP,
					'options' => [
						'regexp' => '/^(mins|hours|days|weeks|months|years)$/',
					],
				],
				'superblock_untiln' => [
					'filter' => FILTER_VALIDATE_INT,
					'options' => [
						'default' => 0,
						'max_range' => 99,
						'min_range' => 0,
					],
				],
				'form_security_token' => [
					'filter' => FILTER_DEFAULT,
				],
			],
			true
		);

		if (empty($this->params['action'])) {
			$this->error(400, t('no action specified'));
			return false;
		}

		if (empty($this->params['author'])) {
			$this->error(400, t('no channel specified'));
			return false;
		}

		// Only admins can do a site block
		if ($this->params['action'] === 'siteblock' && !is_site_admin()) {
			$this->error(403, t('You do not have access to perform this operation'));
			return false;
		}

		return true;
	}

	/**
	 * Function to wrap check_form_security_token, so we can verify the token
	 * regardless of where it originates.
	 *
	 * @param string $token		The token to check.
	 *
	 * @return `true` if the token is valid, `false` otherwise.
	 */
	private function check_security_token(string $token): bool {
		//
		// Since `check_form_security_token` is hardcoded to only check the
		// `$_REQUEST` superglobal for the token (a really bad idea!), we have
		// to stuff our token into the superglobal to satisfy the call
		//
		// phpcs:disable Generic.PHP.DisallowRequestSuperglobal
		$_REQUEST['form_security_token'] = $token;

		if (!check_form_security_token('superblock')) {
			$this->error(403, t('Invalid or missing security token'));
			return false;
		}

		return true;
	}

	private function findXChanFromAuthor(string $author): array|false {
		$xchan = xchan_fetch(['hash' => $author]);

		if (!$xchan) {
			$xchan = xchan_fetch(['address' => $author]);
		}

		return $xchan;
	}

	private function getExpirationDate(): ?DateTimeImmutable {
		$amount = $this->params['superblock_untiln'] ?? 0;
		$unit = $this->params['superblock_until'] ?? 0;

		if ($unit && $amount) {
			return new DateTimeImmutable("{$amount} {$unit}");
		}

		return null;
	}

	/**
	 * Convenience method to flag that the request should signal an error.
	 *
	 * @param int $status		The HTTP status code to return.
	 * @param string $message	The error message, only used for ajax requests.
	 */
	private function error(int $status, string $message): void {
		notice($message);
		if ($this->is_json_request) {
			http_status($status);
			json_return_and_die([ 'status' => 'error', 'message' => $message ]);
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
