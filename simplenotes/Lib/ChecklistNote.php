<?php

namespace Zotlabs\Addons\SimpleNotes;

final class ChecklistNote extends SimpleNote
{
	public readonly array $checklistItems;
	public readonly string $checklistSortOption;

	public function __construct(array $data = [])
	{
		parent::__construct($data);

		$this->noteType = 'CHECKLIST';

		// ALPHABETICAL_ASC, ALPHABETICAL_DESC, UNCHECKED_FIRST, CHECKED_FIRST are possible options but they are not supported yet in the UI.
		// The original Androis app also provides CREATION_DATE, CREATION_DATE_DESC but we do not preserve those in the editor.
		$this->checklistSortOption = (string)($data['checklistSortOption'] ?? 'MANUAL');

		$this->checklistItems = array_map(
			fn($i) => new ChecklistItem($i),
			(array)($data['checklistItems'] ?? [])
		);
	}

	public function normalize(): static
	{
		$items = $this->checklistItems;

		if (empty($items)) {
			$items = $this->contentToItems($this->content);
		}

		$items = $this->applySorting($items);
		$content = $this->itemsToContent($items);;

		return new self([
			...$this->toArray(),
			'content' => $content,
			'checklistItems' => array_map(fn($i) => $i->toArray(), $items),
			'checklistSortOption' => $this->checklistSortOption,
		]);
	}

	private function contentToItems(string $content): array
	{
		$lines = preg_split('/\r\n|\r|\n/', $content);
		$items = [];

		foreach ($lines as $index => $line) {
			if (preg_match('/^\[( |x)\]\s*(.+)$/', $line, $m)) {
				$items[] = new ChecklistItem([
					'text' => $m[2],
					'isChecked' => $m[1] === 'x',
					'order' => $index,
				]);
			}
		}

		return $items;
	}

	private function itemsToContent(array $items): string
	{
		return implode("\n", array_map(
			fn($i) => ($i->isChecked ? '[x] ' : '[ ] ') . $i->text,
			$items
		));
	}

	private function applySorting(array $items): array
	{
		$option = $this->checklistSortOption;

		usort($items, function (ChecklistItem $a, ChecklistItem $b) use ($option) {
			return match ($option) {

				'UNCHECKED_FIRST' =>
					($a->isChecked <=> $b->isChecked)
					?: ($a->originalOrder <=> $b->originalOrder),

				'CHECKED_FIRST' =>
					($b->isChecked <=> $a->isChecked)
					?: ($a->originalOrder <=> $b->originalOrder),

				'ALPHABETICAL_ASC' =>
					(mb_strtolower($a->text) <=> mb_strtolower($b->text))
					?: ($a->originalOrder <=> $b->originalOrder),

				'ALPHABETICAL_DESC' =>
					(mb_strtolower($b->text) <=> mb_strtolower($a->text))
					?: ($a->originalOrder <=> $b->originalOrder),

				default =>
					$a->originalOrder <=> $b->originalOrder,
			};
		});

		foreach ($items as $index => $item) {
			$item->order = $index;
		}

		return $items;
	}

	public function toArray(): array
	{
		$data = parent::toArray();
		$data['checklistSortOption'] = $this->checklistSortOption;
		$data['checklistItems'] = array_map(fn($i) => $i->toArray(), $this->checklistItems);
		return $data;
	}
}
