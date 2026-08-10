<div class="superblock-dialog-header">
	<h4 class="superblock-dialog-title">{{$addonTitle}}: {{$title}}</h4>
	<button type="button" class="btn-close"></button>
</div>
<form class="superblock-form" name="superblock-add-channel-block" action="superblock" method="POST">
	<input name="form_security_token" type="hidden" value="{{$token}}">
	<input name="action" type="hidden" value="block">
	{{include file="field_input.tpl" field=$authorInputField}}
	{{$expireInputField}}
	<hr>
	<div class="superblock-form-actions">
		<input type="submit" value="{{$blockChannelButtonText}}" class="btn btn-primary">
	</div>
</form>
