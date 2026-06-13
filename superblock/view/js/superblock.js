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
