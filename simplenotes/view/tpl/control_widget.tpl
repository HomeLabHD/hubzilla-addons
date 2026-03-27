<div class="widget">
	<h3>{{$strings.label}}</h3>
	<div class="d-grid gap-2">
		<button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#simplenotes-modal">+ {{$strings.new}}</button>
	</div>
</div>

<div class="widget">
	<h3 class="d-flex justify-content-between">
		{{$strings.order.label}}
		<div>
			<i id="simplenotes-order" class="bi bi-arrow-down text-muted cursor-pointer" data-direction="desc"></i>
		</div>
	</h3>
	<div class="d-grid gap-2">
		<div class="form-check">
			<input class="form-check-input" type="radio" name="noteOrder" value="updated" id="radio_updated" checked>
			<label class="form-check-label" for="radio_updated">
				{{$strings.order.updated}}
			</label>
		</div>
		<div class="form-check">
			<input class="form-check-input" type="radio" name="noteOrder" value="created" id="radio_created">
			<label class="form-check-label" for="radio_created">
				{{$strings.order.created}}
			</label>
		</div>
		<div class="form-check">
			<input class="form-check-input" type="radio" name="noteOrder" value="title" id="radio_title">
			<label class="form-check-label" for="radio_title">
				{{$strings.order.title}}
			</label>
		</div>
		<div class="form-check">
			<input class="form-check-input" type="radio" name="noteOrder" value="type" id="radio_type">
			<label class="form-check-label" for="radio_type">
				{{$strings.order.type}}
			</label>
		</div>
	</div>
</div>

<div class="widget">
	<h3>{{$strings.filter.label}}</h3>
	<div class="d-grid gap-2 mb-3">
		<div class="form-check">
			<input class="form-check-input" type="radio" name="noteFilter" value="all" id="radio_all" checked>
			<label class="form-check-label" for="radio_all">
				{{$strings.filter.all}}
			</label>
		</div>
		<div class="form-check">
			<input class="form-check-input" type="radio" name="noteFilter" value="text" id="radio_note">
			<label class="form-check-label" for="radio_note">
				{{$strings.filter.notes}}
			</label>
		</div>
		<div class="form-check">
			<input class="form-check-input" type="radio" name="noteFilter" value="checklist" id="radio_checklist">
			<label class="form-check-label" for="radio_checklist">
				{{$strings.filter.checklists}}
			</label>
		</div>
	</div>
	<input type="text" id="textSearch" class="form-control" placeholder="{{$strings.filter.text}}">
</div>
