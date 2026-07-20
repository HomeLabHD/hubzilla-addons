{{if $active_folder}}
<div class="mb-4">
	<h3>
		<a href="simplenotes"><i class="bi bi-arrow-left"></i></a> {{$active_folder}}{{if $trash_view}}/{{$strings.trash}}{{/if}}
	</h3>
</div>
{{/if}}

{{if $trash_view && !$active_folder}}
<div class="mb-4">
	<h3>
		<a href="simplenotes"><i class="bi bi-arrow-left"></i></a> {{$strings.trash}}
	</h3>
</div>
{{/if}}

{{if $archive_view && !$active_folder}}
<div class="mb-4">
	<h3>
		<a href="simplenotes"><i class="bi bi-arrow-left"></i></a> {{$strings.archive}}
	</h3>
</div>
{{/if}}

{{if $trash_view}}
<div class="alert alert-danger mb-4">
	{{$strings.trash_alert}}
</div>
{{/if}}

{{if !$trash_view && !$archive_view && $folders}}
<div class="mb-4">
	{{foreach $folders as $f}}
	<a href="simplenotes/{{$f}}" class="btn btn-outline-primary"><i class="bi bi-folder"></i> {{$f}}</a>
	{{/foreach}}
</div>
{{/if}}

<div class="simplenotes_notes_container row">
	{{foreach $items as $i}}
	<div class="col-md-6 mb-4 simplenotes_note" data-id="{{$i.id}}" data-created="{{$i.created.timestamp}}" data-updated="{{$i.updated.timestamp}}" data-title="{{$i.title.encoded}}" data-content="{{$i.content.encoded}}" data-type="{{$i.type}}" data-pinned="{{$i.pinned}}" data-color="{{$i.color}}">
		<div class="card{{if !$i.color}} {{if $i.type === 'TEXT'}}bg-warning-subtle text-warning-emphasis{{else}}bg-info-subtle text-info-emphasis{{/if}}{{/if}}"{{if $i.color}} style="background-color: {{$i.color}};"{{/if}}>
			<div class="card-body">
				<div class="note-title h4">
					{{if $i.trashed}}<i class="bi bi-trash pe-2"></i>{{/if}}
					{{if $i.archived}}<i class="bi bi-archive pe-2"></i>{{/if}}
					<i class="bi bi-card-{{$i.type|lower}} pe-2"></i>
					{{if $i.pinned}}<i class="bi bi-pin pe-2"></i>{{/if}}
					{{$i.title.parsed}}
				</div>
				<hr>
				<div class="note-content">
					{{$i.content.parsed}}
				</div>
			</div>
			<div class="card-footer d-flex justify-content-between">
				<div class="autotime" title="{{$i.updated.date}}"></div>
				<div>
					<i class="bi bi-pencil cursor-pointer simplenotes_note_edit"></i>
				</div>
			</div>
		</div>
	</div>
	{{/foreach}}
</div>

<!-- Modal -->
<div class="modal" id="simplenotes-modal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title">{{$strings.modal.title}}</h3>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<input type="text" id="simplenotes-note-title" class="form-control mb-3" placeholder="{{$strings.modal.note.title}}">
				<textarea id="simplenotes-note-content" class="form-control mb-3" placeholder="{{$strings.modal.note.content}}" rows="7"></textarea>
				<div id="simplenotes-checklist-sort" style="display: none;">
					<select class="form-select" id="simplenotes-checklist-sort-select">
						<option selected>{{$strings.modal.options.label}}</option>
						<option value="ALPHABETICAL_ASC">{{$strings.modal.options.alpha_asc}}</option>
						<option value="ALPHABETICAL_DESC">{{$strings.modal.options.alpha_desc}}</option>
						<option value="UNCHECKED_FIRST">{{$strings.modal.options.unchecked_first}}</option>
						<option value="CHECKED_FIRST">{{$strings.modal.options.checked_first}}</option>
					</select>
				</div>
			</div>
			<div class="modal-footer d-flex justify-content-between">
				<div class="form-check">
					<input class="form-check-input" type="checkbox" name="simplenotes-note-delete" id="simplenotes-note-delete" disabled>
					<label class="form-check-label" for="simplenotes-note-delete">
						{{$strings.modal.delete}}
					</label>
				</div>
				<div class="form-check">
					<input class="form-check-input" type="checkbox" name="simplenotes-note-delete" id="simplenotes-note-archive" disabled>
					<label class="form-check-label" for="simplenotes-note-archive">
						{{$strings.modal.archive}}
					</label>
				</div>
				<div class="form-check">
					<input class="form-check-input" type="checkbox" name="simplenotes-note-pin" id="simplenotes-note-pinned">
					<label class="form-check-label" for="simplenotes-note-pinned">
						{{$strings.modal.pinned}}
					</label>
				</div>
				<button id="simplenotes-note-save" type="button" class="btn btn-primary">{{$strings.modal.submit}}</button>
			</div>
		</div>
	</div>
</div>

<script src="addon/simplenotes/view/js/masonry/masonry.pkgd.min.js"></script>
<script>
	function utf8ToBase64(str) {
		const bytes = new TextEncoder().encode(str);
		let binary = '';
		bytes.forEach(b => binary += String.fromCharCode(b));
		return btoa(binary);
	}

	function base64ToUtf8(base64) {
		const binary = atob(base64);
		const bytes = Uint8Array.from(binary, c => c.charCodeAt(0));
		return new TextDecoder().decode(bytes);
	}

	function sortNotes(direction = 'desc') {
		const container = document.querySelector('.simplenotes_notes_container');
		if (!container) return;

		const field = document.querySelector(
			'input[name="noteOrder"]:checked'
		).value;

		const items = Array.from(container.querySelectorAll('.simplenotes_note'));
		const multiplier = direction === 'asc' ? 1 : -1;

		items.sort((a, b) => {

			// Always keep pinned notes first
			const aPinned = a.dataset.pinned === '1';
			const bPinned = b.dataset.pinned === '1';

			if (aPinned !== bPinned) {
				return aPinned ? -1 : 1;
			}

			const aRaw = a.dataset[field] ?? '';
			const bRaw = b.dataset[field] ?? '';

			// Numeric sort (for created/updated as Unix timestamps)
			if (field === 'created' || field === 'updated') {
				const aNum = Number(aRaw);
				const bNum = Number(bRaw);

				if (!Number.isNaN(aNum) && !Number.isNaN(bNum)) {
					return (aNum - bNum) * multiplier;
				}
			}

			// Fallback: string comparison (for title/type or bad numbers)
			return aRaw.localeCompare(bRaw) * multiplier;
		});

		items.forEach(el => container.appendChild(el));
	}

	function filterNotes() {
		const searchText = document
			.getElementById('textSearch')
			.value.trim()
			.toLowerCase();

		const selectedType = document.querySelector('input[name="noteFilter"]:checked').value;

		const notes = document.querySelectorAll('.simplenotes_note');

		notes.forEach(card => {
			const type = (card.dataset.type || '').toLowerCase();

			const bodyText = (
				card.querySelector('.card-body')?.innerText || ''
			).toLowerCase();

			const matchesType =
				selectedType === 'all' || type === selectedType;

			const matchesText =
				searchText === '' || bodyText.includes(searchText);

			card.style.display =
				matchesType && matchesText ? '' : 'none';
		});
	}

	function contentIsChecklist(textarea) {
		return textarea.value.split(/\r\n|\r|\n/).every(line => /^\[(x)?\]/.test(line))
	}

	document.addEventListener('DOMContentLoaded', () => {
		let direction = 'desc';
		let activeNoteId = null;
		let activeNoteCreated = null;
		let activeNoteType = null;
		let activeNoteColor = null;
		let activeChecklistSortOption = null;

		const simplenotesNoteTitle = document.getElementById('simplenotes-note-title');
		const simplenotesNoteContent = document.getElementById('simplenotes-note-content');
		const simplenotesNoteSave = document.getElementById('simplenotes-note-save');
		const simplenotesNoteDelete = document.getElementById('simplenotes-note-delete');
		const simplenotesNoteArchive = document.getElementById('simplenotes-note-archive');
		const simplenotesNotePinned = document.getElementById('simplenotes-note-pinned');
		const checklistSort = document.getElementById('simplenotes-checklist-sort');

		const modal = document.getElementById('simplenotes-modal')
		const simplenotesModal = new bootstrap.Modal(modal);


		// Reset the modal
		modal.addEventListener('hide.bs.modal', () => {
			simplenotesNoteDelete.disabled = true;
			simplenotesNoteDelete.checked = false;
			simplenotesNoteArchive.checked = false;
			simplenotesNotePinned.checked = false;
			activeNoteId = null;
			activeNoteCreated = null;
			activeNoteType = null;
			checklistSort.style.display = 'none';
			simplenotesNoteTitle.value = '';
			simplenotesNoteContent.value = '';
		});

		sortNotes(direction);
		updateRelativeTime('.autotime');

		let msnry_options = {
			itemSelector: '.simplenotes_note:not([style*="display: none"])',
			percentPosition: true,
			horizontalOrder: true
		};

		let msnry = new Masonry('.simplenotes_notes_container', msnry_options);

		const radiosOrder = document.querySelectorAll('input[name="noteOrder"]');
		radiosOrder.forEach(radio =>
			radio.addEventListener('change', () => {
				msnry.destroy();
				sortNotes(direction);
				msnry = new Masonry('.simplenotes_notes_container', msnry_options);
			})
		);

		const order = document.getElementById('simplenotes-order');
		order.addEventListener('click', (e) => {
			msnry.destroy();
			direction = direction === 'asc' ? 'desc' : 'asc';
			sortNotes(direction);
			e.target.classList.toggle('bi-arrow-up');
			e.target.classList.toggle('bi-arrow-down');
			msnry = new Masonry('.simplenotes_notes_container', msnry_options);
		});

		const searchInput = document.getElementById('textSearch');
		searchInput.addEventListener('input', () => {
			msnry.destroy();
			filterNotes();
			msnry = new Masonry('.simplenotes_notes_container', msnry_options);
		});

		const filterRadios = document.querySelectorAll('input[name="noteFilter"]');

		filterRadios.forEach(filterRadio =>
			filterRadio.addEventListener('change', () => {
				msnry.destroy();
				filterNotes();
				msnry = new Masonry('.simplenotes_notes_container', msnry_options);
			})
		);

		simplenotesNoteSave.addEventListener('click', () => {
			if (contentIsChecklist(simplenotesNoteContent)) {
				activeNoteType = 'CHECKLIST';
				activeChecklistSortOption = document.getElementById('simplenotes-checklist-sort-select').value;
			}

			// Prevent double click
			simplenotesNoteSave.disabled = true;

			let path = '/simplenotes';
			let folder = window.location.pathname.split('/')[2];

			if (folder !== undefined) {
				path = '/simplenotes/' + folder;
			}

			fetch(path, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify({
					title: simplenotesNoteTitle.value,
					content: simplenotesNoteContent.value.replaceAll('[]', '[ ]'),
					id: activeNoteId,
					createdAt: activeNoteCreated,
					isPinned: simplenotesNotePinned.checked,
					color: activeNoteColor,
					noteType: activeNoteType,
					checklistSortOption: activeChecklistSortOption,
					delete: {{if $trash_view}}simplenotesNoteDelete.checked ? 'hard' : 0{{else}}simplenotesNoteDelete.checked ? 'soft' : 0{{/if}},
					archive: simplenotesNoteArchive.checked,
				})
			})
			.then(response => response.json())  // Parse the JSON response
			.then(response => {
				console.log(response);
				if (response.success) {
					toast(response.message, 'info');
					// Get rid of arguments if there are any (e.g. editing a note in trash view)
					window.location.search = '';
					setTimeout(() => window.location.reload(), 700);
				}

			})
			.catch(error => {
				console.error('Error:', error);
			});
		});

		const simplenotesNoteEditButtons = document.querySelectorAll('.simplenotes_note_edit');
		simplenotesNoteEditButtons.forEach(simplenotesNoteEditButton =>
			simplenotesNoteEditButton.addEventListener('click', (e) => {
				const note = e.target.closest('.simplenotes_note');
				activeNoteId = note.dataset.id;
				activeNoteCreated = note.dataset.created;
				activeNoteColor= note.dataset.color;
				simplenotesNotePinned.checked = note.dataset.pinned;
				simplenotesNoteTitle.value = base64ToUtf8(note.dataset.title);
				simplenotesNoteContent.value = base64ToUtf8(note.dataset.content).replaceAll('[ ]', '[]');
				simplenotesModal.show();
				simplenotesNoteDelete.disabled = false;
				simplenotesNoteArchive.disabled = false;

				if (note.dataset.type === 'CHECKLIST') {
					checklistSort.style.display = '';
				}
			})
		);

		simplenotesNoteContent.addEventListener('keydown', function(e) {
			if (e.key === 'Enter') {
				if (contentIsChecklist(this)) {
					e.preventDefault();

					// Make sort options visible
					checklistSort.style.display = '';

					const { selectionStart, selectionEnd, value } = this;

					// Insert a new line with []
					const before = value.slice(0, selectionStart);
					const after = value.slice(selectionEnd);
					const newText = before + '\n[] ' + after;

					this.value = newText;

					// Move cursor after the new [ ]
					const cursorPos = selectionStart + 5; // '\n[ ] ' is 5 chars
					this.setSelectionRange(cursorPos, cursorPos);
				}
				else {
					checklistSort.style.display = 'none';
				}
			}
		});
	});
</script>
