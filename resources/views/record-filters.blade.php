<form method="get" action="{{ url()->current() }}" class="record-filters">
@foreach(($fixedFilters??[]) as $name=>$value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
@foreach($filterFields as $name=>$label)<label class="field">{{ $label }}<input type="{{ str_ends_with($name,'_from')||str_ends_with($name,'_to')?'date':'text' }}" name="{{ $name }}" value="{{ request($name) }}" maxlength="100"></label>@endforeach
@if(!empty($statusOptions))<label class="field">Status<select name="status">@foreach($statusOptions as $value=>$label)<option value="{{ $value }}" @selected(request('status','all')===$value)>{{ $label }}</option>@endforeach</select></label>@endif
<label class="field">Rows per page<select name="per_page" aria-label="Rows per page" onchange="this.form.requestSubmit()">@foreach(\App\Http\Requests\RecordListRequest::PAGE_SIZES as $size)<option value="{{ $size }}" @selected((int)request('per_page',10)===$size)>{{ $size }}</option>@endforeach</select></label>
<div class="filter-actions"><button class="button secondary">Search / filter</button><a class="link" href="{{ url()->current() }}{{ empty($fixedFilters)?'':'?'.http_build_query($fixedFilters) }}">Reset</a></div>
</form>
