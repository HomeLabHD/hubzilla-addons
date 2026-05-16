<?php
/*
 * SPDX-FileCopyrightText: 2026 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Addons\Superblock;

use DateTimeImmutable;
use DateTimeZone;

/**
 * An entry in the ChannelBlockList.
 *
 * A ChannelBlock represents a channel to be blocked, either permanently, or
 * temporarily for a specific time.
 */
class ChannelBlock
{
	public readonly string $hash;
	public readonly ?DateTimeImmutable $expire;

	private static $tz = null;

	/**
	 * Construct a new ChannelBlock object.
	 *
	 * The constructor takes an array of key => value pairs as arguments. The
	 * following keys are recognized:
	 *
	 *   - `hash`  (string, required) - the channel hash of the channel to block.
	 *   - `until` (string, optional) - the optional expiry time of the block.
	 *
	 * The `validate()` method must be called to check that the block has not
	 * been expired.
	 */
	public function __construct(array $data)
	{
		$this->hash = trim($data['hash']);
		if (!empty($data['until'])) {
			$this->expire =
				new DateTimeImmutable($data['until'], self::tz());
		} else {
			$this->expire = null;
		}
	}

	/**
	 * Check that the channel block is still valid.
	 *
	 * A block can be not valid if it has a time limit, and is expired.
	 *
	 * @return bool
	 *		true if block is still valid, false otherwise.
	 */
	public function validate(): bool
	{
		if ($this->expire) {
			//
			// Check the expiry of the block to the minute.
			//
			$now = new DateTimeImmutable('now', self::tz());
			$diff = $this->expire->diff($now);
			return $diff->invert === 1 && ($diff->days > 0 || $diff->h > 0 || $diff->i > 0);
		}

		return true;
	}

	/**
	 * Returns an array representation of this ChannelBlock object.
	 *
	 * @return array
	 *		An array with attributes as keys, and corresponding values.
	 */
	public function toArray(): array
	{
		$data = [ 'hash' => $this->hash ];
		if ($this->expire) {
			$data['until'] = $this->expire->format(DateTimeImmutable::ISO8601);
		}

		return $data;
	}

	/**
	 * Return a initialized timezone object, initializing it if necessary.
	 *
	 * @return DateTimeZone
	 *		A time zone object representing the UTC timezone.
	 */
	private static function tz(): DateTimeZone {
		if (!self::$tz) {
			self::$tz = new DateTimeZone('UTC');
		}

		return self::$tz;
	}
}
