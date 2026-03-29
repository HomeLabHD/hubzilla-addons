<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\PConfig;
use Zotlabs\Web\Controller;
use Zotlabs\Storage\Directory;
use Zotlabs\Storage\File;
use Zotlabs\Storage\BasicAuth;

require_once __DIR__ . '/../addon_common/vendor/autoload.php';

use Zotlabs\Addons\SimpleNotes\SimpleNote;
use Zotlabs\Addons\SimpleNotes\TextNote;
use Zotlabs\Addons\SimpleNotes\ChecklistNote;

class Simplenotes extends Controller {

	private $auth;
	private $dir;

	public function init(): void {
		if (!local_channel()) {
			return;
		}

		if (!Apps::addon_app_installed(local_channel(), 'simplenotes')) {
			return;
		}

		$channel = App::get_channel();

		if (!$channel) {
			return;
		}

		$this->auth = new BasicAuth();

		$this->auth->setCurrentUser($channel['channel_address']);
		$this->auth->channel_account_id = $channel['channel_account_id'];
		$this->auth->channel_id = $channel['channel_id'];
		$this->auth->channel_hash = $channel['channel_hash'];
		$this->auth->observer = get_observer_hash();

		if ($channel['channel_timezone']) {
			$this->auth->setTimezone($channel['channel_timezone']);
		}

		$simplenotes_path = PConfig::Get($this->auth->channel_id, 'simplenotes', 'path');

		if (!$simplenotes_path) {
			notice('Please configure a path to the notes directory');
			goaway('settings/simplenotes');
		}

		try {
			$this->dir = new Directory($channel['channel_address'] . '/' . $simplenotes_path, [], $this->auth);
		} catch (\Exception $e) {
			notice('Exception: ' . $e->getMessage());
			goaway('settings/simplenotes');
		}
	}

	public function get(): string
	{

		if (!local_channel()) {
			return '';
		}

		if (!Apps::addon_app_installed(local_channel(), 'simplenotes')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';
			$papp = Apps::get_papp('Simple Notes');
			return Apps::app_render($papp, 'module');
		}

		nav_set_selected('Simple Notes', 'settings/simplenotes');

		try {
			$files = $this->dir->getChildren();
		} catch (\Exception $e) {
			return "Exception: " . $e->getMessage();
		}

		$notes = [];

		foreach($files as $file)  {
			if ($file->data['filetype'] !== 'application/json') {
				continue;
			}

			$filename = $file->data['filename'];
			$stream = $this->dir->getChild($filename)->get();
			$note = json_decode(stream_get_contents($stream), true);

			if (!$note) {
				continue;
			}

			$note_object = SimpleNote::fromArray($note);

			$prepared['id'] = escape_tags($note_object->id);
			$prepared['title']['parsed'] = escape_tags($note_object->title);
			$prepared['title']['encoded'] = base64_encode($note_object->title);
			$prepared['content']['parsed'] = bbcode(($note_object->noteType === 'CHECKLIST') ? '[checklist]' . str_replace('[ ]', '[]', $note_object->content) . '[/checklist]' : $note_object->content);
			$prepared['content']['encoded'] = base64_encode($note_object->content);
			$prepared['updated']['timestamp'] = $note_object->createdAt;
			$prepared['updated']['date'] = date('Y-m-d H:i:s', $note_object->updatedAt/1000);
			$prepared['created']['timestamp'] = $note_object->createdAt;
			$prepared['created']['date'] = date('Y-m-d H:i:s', $note_object->createdAt/1000);
			$prepared['type'] = escape_tags($note_object->noteType);

			$items[] = $prepared;
			fclose($stream);
		}

		return replace_macros(get_markup_template('notes.tpl', 'addon/simplenotes'), [
			'$items' => $items,
			'$strings' => [
				'modal' => [
					'title' => t('Simple Notes Editor'),
					'options' => [
						'label' => t('Sort checklist by'),
						'alpha_asc' => t('Alphabetical ascending'),
						'alpha_desc' => t('Alphabetical descending'),
						'unchecked_first' => t('Unchecked first'),
						'checked_first' => t('Checked first')
					],
					'delete' => t('Delete note'),
					'submit' => t('Submit'),
					'note' => [
						'title' => t('Title'),
						'content' => t('Content')
					]
				]
			]
		]);
	}

	public function post(): void {
		if (!local_channel()) {
			return;
		}

		if (!Apps::addon_app_installed(local_channel(), 'simplenotes')) {
			return;
		}

		$data = json_decode(file_get_contents('php://input'), true);

		if (!$data) {
			return;
		}

		$note_object = SimpleNote::fromArray($data);

		$filename = $note_object->id . '.json';

		if ($this->dir->childExists($filename)) {
			$this->dir->getChild($filename)->delete();
		}

		if ($data['delete'] === true) {
			json_return_and_die(['success' => true, 'message' => 'Note deleted!']);
		}

		$this->dir->createFile($filename, json_encode($note_object->toArray()));

		json_return_and_die(['success' => true, 'message' => 'Note saved!']);
	}

}
