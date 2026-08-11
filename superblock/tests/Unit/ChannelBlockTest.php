<?php
/*
 * SPDX-FileCopyrightText: 2026 The Hubzilla Community
 *
 * SPDX-License-Identifier: MIT
 */

namespace Zotlabs\Addons\Superblock\Tests\Unit;

use DateInterval;
use DateTimeImmutable;
use DomainException;
use Exception;
use PHPUnit\Framework\TestCase;
use Zotlabs\Addons\Superblock\ChannelBlock;

class ChannelBlockTest extends TestCase
{
	public function testCreateChannelBlockFromArray(): void
	{
		$cb1 = new ChannelBlock(['hash' => '1234']);
		$this->assertEquals('1234', $cb1->hash);
		$this->assertNull($cb1->expire);

		$expire = (new DateTimeImmutable())->add(new DateInterval("P10D"));

		$cb2 = new ChannelBlock(['hash' => '5678', 'until' => $expire]);
		$this->assertEquals('5678', $cb2->hash);
		$this->assertEquals($expire, $cb2->expire);
	}

	public function testCreateChannelBlockWithoutHashShouldFail(): void
	{
		$this->expectException(DomainException::class);
		$cb = new ChannelBlock([]);
	}

	public function testCreateChannelBlockWithInvalidExpiration(): void
	{
		$this->expectException(Exception::class);
		$cb = new ChannelBlock(['hash' => '1234', 'until' => 'invalid']);
	}

	public function testChannelBlockValidate(): void
	{
		$cb1 = new ChannelBlock(['hash' => '1234']);

		$inTenDays = (new DateTimeImmutable())->add(new DateInterval("P10D"));
		$cb2 = new ChannelBlock(['hash' => '5678', 'until' => $inTenDays]);

		$yesterday = (new DateTimeImmutable())->sub(new DateInterval("P1D"));
		$cb3 = new ChannelBlock(['hash' => '5678', 'until' => $yesterday]);

		$this->assertTrue($cb1->validate());
		$this->assertTrue($cb2->validate());
		$this->assertFalse($cb3->validate());
	}
}
