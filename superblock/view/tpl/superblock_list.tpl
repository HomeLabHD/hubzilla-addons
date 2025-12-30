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
	font-size: 220%;
}
.superblock-entry-body {
	flex: 2;
}
</style>
<h3>{{$title}}</h3>

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
					<div class="superblock-channel-addr">
						<a href="{{$e.xchan_url|escape:'url'}}">{{$e.xchan_addr}}</a>
					</div>
				</div>
				<div class="superblock-entry-actions">
					<a class="pull-right"
						href="superblock?f=&unblock={{$e.xchan_hash|escape:'url'}}&sectok={{$token}}"
						title="{{$remove}}"><i class="bi bi-trash"></i></a>
				</div>
			</div>
		</li>
	{{/foreach}}
	</ul>
{{/if}}

