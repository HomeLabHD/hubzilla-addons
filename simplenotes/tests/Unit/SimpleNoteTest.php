<?php
namespace Zotlabs\Addons\SimpleNotes\Tests\Unit;

require_once __DIR__ . '/../../../addon_common/vendor/autoload.php';

use Zotlabs\Tests\Unit\UnitTestCase;
use Zotlabs\Addons\SimpleNotes\SimpleNote;
use Zotlabs\Addons\SimpleNotes\TextNote;
use Zotlabs\Addons\SimpleNotes\ChecklistNote;

final class SimpleNoteTest extends UnitTestCase
{
	public function testTextNoteDefaultCreation()
	{
		$note = SimpleNote::fromArray([
			'title' => 'Test',
			'content' => 'Hello world'
		]);

		$this->assertInstanceOf(TextNote::class, $note);
		$this->assertSame('TEXT', $note->noteType);
		$this->assertSame('Test', $note->title);
		$this->assertSame('Hello world', $note->content);
	}

	public function testChecklistParsingFromContent()
	{
		$content = "[ ] Task 1\n[x] Task 2";

		$note = SimpleNote::fromArray([
			'noteType' => 'CHECKLIST',
			'content' => $content
		]);

		$this->assertInstanceOf(ChecklistNote::class, $note);

		$items = $note->checklistItems;

		$this->assertCount(2, $items);

		$this->assertSame('Task 1', $items[0]->text);
		$this->assertFalse($items[0]->isChecked);

		$this->assertSame('Task 2', $items[1]->text);
		$this->assertTrue($items[1]->isChecked);
	}

	public function testChecklistToContentRoundtrip()
	{
		$data = [
			'noteType' => 'CHECKLIST',
			'checklistItems' => [
				['text' => 'A', 'isChecked' => false, 'order' => 0],
				['text' => 'B', 'isChecked' => true, 'order' => 1],
			]
		];

		$note = SimpleNote::fromArray($data);

		$this->assertSame("[ ] A\n[x] B", $note->content);
	}

	public function testSortingUncheckedFirst()
	{
		$data = [
			'noteType' => 'CHECKLIST',
			'checklistSortOption' => 'UNCHECKED_FIRST',
			'checklistItems' => [
				['text' => 'Checked', 'isChecked' => true, 'order' => 0],
				['text' => 'Unchecked', 'isChecked' => false, 'order' => 1],
			]
		];

		$note = SimpleNote::fromArray($data);
		$items = $note->checklistItems;

		$this->assertFalse($items[0]->isChecked);
		$this->assertTrue($items[1]->isChecked);
	}

	public function testAlphabeticalSorting()
	{
		$data = [
			'noteType' => 'CHECKLIST',
			'checklistSortOption' => 'ALPHABETICAL_ASC',
			'checklistItems' => [
				['text' => 'Banana'],
				['text' => 'apple'],
			]
		];

		$note = SimpleNote::fromArray($data);
		$items = $note->checklistItems;

		$this->assertSame('apple', $items[0]->text);
		$this->assertSame('Banana', $items[1]->text);
	}

	public function testToArrayStructure()
	{
		$note = SimpleNote::fromArray([
			'title' => 'Test',
			'content' => 'Hello'
		]);

		$array = $note->toArray();

		$this->assertArrayHasKey('id', $array);
		$this->assertArrayHasKey('deviceId', $array);
		$this->assertArrayHasKey('title', $array);
		$this->assertArrayHasKey('content', $array);
		$this->assertArrayHasKey('noteType', $array);
		$this->assertArrayHasKey('createdAt', $array);
		$this->assertArrayHasKey('updatedAt', $array);
	}

	public function testChecklistToArrayIncludesItems()
	{
		$note = SimpleNote::fromArray([
			'noteType' => 'CHECKLIST',
			'content' => "[ ] A\n[x] B"
		]);

		$array = $note->toArray();

		$this->assertArrayHasKey('checklistItems', $array);
		$this->assertCount(2, $array['checklistItems']);
	}
}
