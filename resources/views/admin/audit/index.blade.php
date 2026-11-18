@extends('layouts.app')
@section('title', 'Audit Log')
@section('content')
<form class="d-flex flex-wrap gap-2 mb-3" method="GET">
    <input name="action" class="form-control form-control-sm w-auto ltr" placeholder="action (e.g. contribution.)" value="{{ request('action') }}">
    <input name="entity" class="form-control form-control-sm w-auto ltr" placeholder="Entity (e.g. Contribution)" value="{{ request('entity') }}">
    <select name="user_id" class="form-select form-select-sm w-auto"><option value="">همه کاربران</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>@endforeach</select>
    <button class="btn btn-sm btn-outline-primary">فیلتر</button>
</form>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>#</th><th>زمان</th><th>کاربر</th><th>رویداد</th><th>موجودیت</th><th>IP</th><th></th></tr></thead>
    <tbody>@foreach ($logs as $l)<tr><td>{{ $l->id }}</td><td class="text-nowrap">{{ jdate($l->created_at, true) }}</td><td>{{ $l->user?->name ?? 'سیستم' }}</td><td class="ltr text-end small">{{ $l->action }}</td><td class="small">{{ $l->entity_type }} {{ $l->entity_id ? '#'.$l->entity_id : '' }}</td><td class="small ltr text-end">{{ $l->ip_address }}</td><td><a href="{{ route('admin.audit.show', $l) }}" class="btn btn-sm btn-light">جزئیات</a></td></tr>@endforeach</tbody>
</table></div><div class="card-footer bg-white">{{ $logs->links() }}</div></div>
<div class="small text-muted mt-2">Audit Log فقط‌خواندنی است و از رابط کاربری قابل ویرایش یا حذف نیست.</div>
@endsection
