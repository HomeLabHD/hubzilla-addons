/*
 * SPDX-FileCopyrightText: 2026 The Hubzilla Community
 * SPDX-FileContributor: Harald Eilertsen <haraldei@anduin.net>
 * SPDX-FileContributor: Zotlabs
 *
 * SPDX-License-Identifier: MIT
 */

async function superblockAjax(action, author, item, security) {
	let response = await fetch("superblock", {
		method: "POST",
		headers: {
			"Content-Type": "application/json",
			"Accept": "application/json",
		},
		body: JSON.stringify({
			action: action,
			author: author,
			item: item,
			form_security_token: security,
		}),
	});
	body = await response.text();
}

async function superblockSendFormData(form) {
	const formData = new FormData(form);

	try {
		const response = await fetch('superblock', {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		});
	} catch (e) {
		console.error(e);
		return false;
	}

	return true;
}

function superblockFilterThread(element, hash) {
	for (const child of element.children) {
		if (child.classList.contains('wall-item-outside-wrapper')) {
			let nameLinkElement = child.querySelector('.wall-item-name-link');
			let link = new URL(nameLinkElement.getAttribute('href'));
			let nameHash = link.searchParams.get('hash');
			if (nameHash == hash) {
				// We hide the element instead of removing it from the DOM,
				// to avoid modifying the NodeList
				element.hidden = true;
			}
		}
	}
}

async function superblockPopupSubmitForm(author) {
	let dialog = document.getElementById('superblockSubmitDialog');
	fetch(`superblock/submit?author=${author}`, { credentials: 'same-origin' })
		.then(async (response) => {
			dialog.innerHTML = await response.text();

			let btn_close = dialog.getElementsByClassName('btn-close')[0];
			btn_close.addEventListener('click', () => {
				dialog.close();
			});

			const form = dialog.getElementsByTagName('form')[0];
			form.addEventListener('submit', (event) => {
				event.preventDefault();
				if (superblockSendFormData(form)) {
					const threads = Array.from(document.querySelectorAll('.thread-wrapper'));
					for (const thread of threads) {
						superblockFilterThread(thread, author);
					}
				}
				dialog.close();
			});

			dialog.showModal();
		});
}
