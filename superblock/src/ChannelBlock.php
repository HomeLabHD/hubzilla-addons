<?php
/*
 * SPDX-FileCopyrightText: 2026 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Addons\Superblock;

/**
 * An entry in the ChannelBlockList.
 */
class ChannelBlock
{
	public readonly string $hash;

	public function __construct(string $hash)
	{
		$this->hash = trim($hash);
	}
}
