<h3>{{$title}}</h3>

{{if $addBlockForm}}
<div class="add-entry-form">
	{{$addBlockForm}}
</div>
{{/if}}

{{if empty($entries)}}
	<div class="descriptive-text">{{$nothing}}</div>
{{elseif $entries}}
	<ul class="superblock-blocklist">
	{{foreach $entries as $e}}
		<li>{{$e}}</li>
	{{/foreach}}
	</ul>
{{/if}}

