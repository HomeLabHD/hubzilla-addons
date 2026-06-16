<style>
.superblock-blocklist {
	list-style-type: none;
	padding: 0;
}
.superblock-blocked-entry {
	display: flex;
	gap: 1rem;
	margin-bottom: 1rem;
	background-color: var(--bs-secondary-bg);
	padding: 0.5rem;
}
.superblock-entry-actions {
	display: flex;
	gap: 0.3rem;
	font-size: 150%;
	align-items: center;
}
.superblock-entry-actions button {
	border: none;
	background-color: var(--bs-secondary-bg);
	color: var(--bs-link-color);
}
.superblock-entry-actions button:hover {
	color: var(--bs-link-hover-color);
}
.superblock-entry-body {
	flex: 2;
}
.superblock-channel-name {
	font-weight: bold;
}
.superblock-form {
	border: 1px solid var(--bs-secondary-color);
	padding: 1em;
}
</style>
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

