<?php
namespace Zotlabs\Addons\SimpleNotes;

final class TextNote extends SimpleNote
{
	public function __construct(array $data = [])
	{
		parent::__construct($data);
		$this->noteType = 'TEXT';
	}

	public function normalize(): static
	{
		return $this; // already canonical
	}
}
