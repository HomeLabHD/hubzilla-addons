<?php

namespace Zotlabs\Addons\SimpleNotes;

final class ChecklistItem
{
	public readonly string $id;
	public readonly int $createdAt;
	public readonly bool $isChecked;
	public int $order;
	public readonly int $originalOrder;
	public readonly string $text;

	public function __construct(array $data = [])
	{
		$now = (int)(microtime(true) * 1000);

		$this->id = (string)($data['id'] ?? new_uuid());
		$this->createdAt = (int)($data['createdAt'] ?? $now);
		$this->isChecked = (bool)($data['isChecked'] ?? false);
		$this->order = (int)($data['order'] ?? 0);
		$this->originalOrder = (int)($data['originalOrder'] ?? $this->order);
		$this->text = trim((string)($data['text'] ?? ''));
	}

	public function toArray(): array
	{
		return [
			'id' => $this->id,
			'createdAt' => $this->createdAt,
			'isChecked' => $this->isChecked,
			'order' => $this->order,
			'originalOrder' => $this->originalOrder,
			'text' => $this->text,
		];
	}
}
