{{*
SPDX-FileCopyrightText: 2026 The Hubzilla Community

SPDX-License-Identifier: MIT
*}}
<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<h2>{{$title}}</h2>
	</div>

	<div id="superblock-settings-content-container" class="section-content-wrapper">
		<form id="superblock-settings-form" action="settings/superblock" method="POST">
			<input type="hidden" name="form_security_token" value="{{$securityToken}}">
			{{include file="field_checkbox.tpl" field=$blockResharesField}}

			<div class="superblock-form-actions">
				<input type="submit" value="{{$submitLabel}}" class="btn btn-primary">
			</div>
		</form>
	</div>
</div>
