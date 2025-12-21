<?php
/*
 * SPDX-FileCopyrightText: 2025 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Addons\Superblock\Tests\Helpers;

use PHPUnit\Framework\Attributes\After;
use Zotlabs\Lib\Apps;

trait PluginHelperTrait {

	#[After]
	public function cleanup(): void {
		$this->unloadPlugin();
		$this->endSession();
	}

	private function startSession(array $channel): void {
		session_start();

		if (!empty($channel)) {
			$_SESSION['authenticated'] = true;
			$_SESSION['uid'] = $channel['channel_id'];
		}
	}

	private function endSession(): void {
		session_abort();
		$_SESSION = [];
	}

	private function installPluginApp(array $channel): void {
		$app = Apps::parse_app_description(__DIR__ . '/../../superblock.apd', false, false);
		$app['plugin'] = 'superblock';
		$app['guid'] = hash('whirlpool', 'Superblock');
		Apps::app_install(0, $app);

		if (!empty($channel)) {
			Apps::app_install($channel['channel_id'], $app);
		}

		install_plugin('superblock');
		load_hooks();
	}

	private function unloadPlugin(): void {
		unload_plugin('superblock');
	}
}
