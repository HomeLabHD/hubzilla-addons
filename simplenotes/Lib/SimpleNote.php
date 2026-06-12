<?php

namespace Zotlabs\Addons\SimpleNotes;

abstract class SimpleNote
{
	public readonly string $id;
	public readonly string $deviceId;
	public readonly string $title;
	public readonly string $content;
	public string $noteType;
	public readonly int $createdAt;
	public readonly int $updatedAt;
	public readonly bool $isPinned;

	public function __construct(array $data = [])
	{
		$now = (int)(microtime(true) * 1000);

		$this->id = (string)($data['id'] ?? new_uuid());
		$this->deviceId = (string)($data['deviceId'] ?? 'hubzilla-' . bin2hex(z_root()));
		$this->title = (string)($data['title'] ?? '');
		$this->content = (string)($data['content'] ?? '');
		$this->noteType = (string)($data['noteType'] ?? 'TEXT');
		$this->createdAt = (int)($data['createdAt'] ?? $now);
		$this->updatedAt = (int)($data['updatedAt'] ?? $now);
		$this->isPinned = (bool)($data['isPinned'] ?? false);
		$this->color = (string)($data['color'] ?? '');
	}

	abstract function normalize(): static;

	public static function fromArray(array $data): self
	{
		$type = strtoupper((string)($data['noteType'] ?? 'TEXT'));

		$note = match ($type) {
			'CHECKLIST' => new ChecklistNote($data),
			default => new TextNote($data),
		};

		return $note->normalize();
	}

	public function toArray(): array
	{
		return [
			'id' => $this->id,
			'deviceId' => $this->deviceId,
			'title' => $this->title,
			'content' => $this->content,
			'noteType' => $this->noteType,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
			'isPinned' => $this->isPinned,
			'color' => $this->color,
		];
	}
}
