<details>
	<summary>{{$addNewEntryText}}</summary>
	<form name="superblock-add-channel-block" action="superblock" method="POST">
		<input name="form_security_token" type="hidden" value="{{$token}}">
		<input name="action" type="hidden" value="block">
		{{include file="field_input.tpl" field=$authorInputField}}
		<input type="submit" value="{{$blockChannelButtonText}}" class="btn btn-primary">
	</form>
</details>
