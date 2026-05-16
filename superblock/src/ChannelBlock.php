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

	/**
	 * Returns an array representation of this ChannelBlock object.
	 *
	 * @return array
	 *		An array with attributes as keys, and corresponding values.
	 */
	public function toArray(): array
	{
		return [
			'hash' => $this->hash,
		];
	}
}
