<?php
/*
 * SPDX-FileCopyrightText: 2026 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Addons\Superblock\Views;

use dba_driver;
use DateTimeImmutable;
use Zotlabs\Addons\Superblock\ChannelBlock;
use Zotlabs\Addons\Superblock\Superblock;

class ChannelBlockEntry
{
	public readonly string $name;
	public readonly string $hash;
	public readonly string $addr;
	public readonly string $url;
	public readonly string $photo_url;
	public readonly ?DateTimeImmutable $expire;

	public readonly string $token;

	public function __construct(string $token, ChannelBlock $cb, array $xchan)
	{
		$this->token = $token;
		$this->name = $xchan['xchan_name'];
		$this->hash = $xchan['xchan_hash'];
		$this->addr = $xchan['xchan_addr'];
		$this->url = $xchan['xchan_url'];
		$this->photo_url = $xchan['xchan_photo_s'];
		$this->expire = $cb->expire;
	}

	public function render(): string
	{
		$tpl = get_markup_template('superblock_list_entry.tpl','addon/superblock');
		return replace_macros($tpl, [
			'entry' => $this,
			'link_alt_text' => sprintf(t('Go to %1$s\'s channel (Opens in new tab)'), $this->name),
			'remove' => t('Remove from blocklist'),
			'until' => t('Until:'),
		]);
	}
}
