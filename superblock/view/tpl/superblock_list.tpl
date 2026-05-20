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
</style>
<h3>{{$title}}</h3>

{{if $addBlockForm}}
	{{$addBlockForm}}
{{/if}}

{{if $nothing}}
	<div class="descriptive-text">{{$nothing}}</div>
{{elseif $entries}}
	<ul class="superblock-blocklist">
	{{foreach $entries as $e}}
		<li>
			<div class="superblock-blocked-entry">
				<a class="superblock-entry-avatar zid" href="{{$e.xchan_url}}">
					<img src="{{$e.xchan_photo_s}}" alt="{{$e.xchan_addr|escape}}">
				</a>
				<div class="superblock-entry-body">
					<div class="superblock-channel-name">{{$e.xchan_name}}</div>
					<div class="superblock-channel-meta">
						<span class="superblock-channel-addr">
							{{$e.xchan_addr}}
						</span>
					</div>
				</div>
				<div class="superblock-entry-actions">
					<a href="{{$e.xchan_url}}" target="_blank">
						<span class="bi bi-box-arrow-up-right"
							title="Go to {{$e.xchan_name}}'s channel (Opens in new tab)">
						</span>
					</a>
					<form action="superblock" method="POST">
						<input type="hidden" name="action" value="unblock">
						<input type="hidden" name="author" value="{{$e.xchan_hash|escape}}">
						<input type="hidden" name="form_security_token" value="{{$token}}">
						<button type="submit" class="bi bi-trash" title="{{$remove}}"></button>
					</form>
				</div>
			</div>
		</li>
	{{/foreach}}
	</ul>
{{/if}}

