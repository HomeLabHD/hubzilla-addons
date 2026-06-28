<dialog id="superblockSubmitDialog" closedby="any"></dialog>
<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<div class="float-end">
			<button class="btn btn-sm btn-success" onclick="superblockPopupSubmitForm('', false)">
				<i class="bi bi-plus-lg"></i>
				{{$newEntry}}
			</button>
		</div>
		<h2>{{$title}}</h2>
	</div>

	{{if empty($entries)}}
		<div class="descriptive-text">{{$nothing}}</div>
	{{elseif $entries}}
		<ul class="superblock-blocklist">
		{{foreach $entries as $e}}
			<li>{{$e}}</li>
		{{/foreach}}
		</ul>
	{{/if}}
</div>
