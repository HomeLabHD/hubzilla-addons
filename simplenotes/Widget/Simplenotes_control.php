<?php

/**
 *   * Name: Simple Notes Control
 *   * Description: Display create, filter and sort options for the Simple Notes App
 *   * Requires: simplenotes
 */

namespace Zotlabs\Widget;

use Zotlabs\Extend\Widget;

class Simplenotes_control {

	function widget($arr) {
		if (!local_channel()) {
			return;
		}

		return replace_macros(get_markup_template('control_widget.tpl', 'addon/simplenotes'), [
			'$folder' => argv(1) ?? '',
			'$strings' => [
				'view_trash' => t('View trash'),
				'label' => t('Simple Notes'),
				'new' => t('Add new note'),
				'order' => [
					'label' => t('Order'),
					'updated' => t('Updated date'),
					'created' => t('Created date'),
					'title' => t('Title'),
					'type' => t('Type')
				],
				'filter' => [
					'label' => t('Filter'),
					'all' => t('All'),
					'notes' => t('Notes'),
					'checklists' => t('Checklists'),
					'text' => t('Text')
				]
			]
		]);
	}
}
