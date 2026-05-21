<div class="superblock-blocked-entry">
	<a class="superblock-entry-avatar zid" href="{{$entry->url}}">
		<img src="{{$entry->photo_url}}" alt="{{$entry->addr|escape}}">
	</a>
	<div class="superblock-entry-body">
		<div class="superblock-channel-name">{{$entry->name}}</div>
		<div class="superblock-channel-meta">
			<span class="superblock-channel-addr">
				{{$entry->addr}}
			</span>
			{{if $entry->expire}}
			<span class="superblock-channel-expiration-date">
				({{$until}}:
				<time datetime="{{$entry->expire|date_format:"%Y-%m-%d"}}">
					{{$entry->expire|date_format:"%Y-%m-%d %H:%M"}}
				</time>)
			</span>
			{{/if}}
		</div>
	</div>
	<div class="superblock-entry-actions">
		<a href="{{$entry->url}}" target="_blank">
			<span class="bi bi-box-arrow-up-right"
				title="{{$link_alt_text}}">
			</span>
		</a>
		<form action="superblock" method="POST">
			<input type="hidden" name="action" value="unblock">
			<input type="hidden" name="author" value="{{$entry->hash|escape}}">
			<input type="hidden" name="form_security_token" value="{{$entry->token}}">
			<button type="submit" class="bi bi-trash" title="{{$remove}}"></button>
		</form>
	</div>
</div>
