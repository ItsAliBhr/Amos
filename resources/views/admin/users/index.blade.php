@extends('layouts.app')
@section('title', 'مدیریت کاربران')
@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> ایجاد کاربر</a>
    <form class="d-flex gap-2" method="GET">
        <input name="q" class="form-control form-control-sm" placeholder="نام یا نام کاربری" value="{{ request('q') }}">
        <select name="status" class="form-select form-select-sm"><option value="">همه وضعیت‌ها</option>@foreach (\App\Models\User::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') == $k)>{{ $v }}</option>@endforeach</select>
        <select name="role" class="form-select form-select-sm"><option value="">همه نقش‌ها</option>@foreach (\App\Models\User::ROLES as $k => $v)<option value="{{ $k }}" @selected(request('role') == $k)>{{ $v }}</option>@endforeach</select>
        <button class="btn btn-sm btn-outline-primary">فیلتر</button>
    </form>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>نام</th><th>نام کاربری</th><th>نقش</th><th>وضعیت</th><th>تعهد ماهانه</th><th>عضویت</th><th>آخرین ورود</th><th></th></tr></thead>
    <tbody>
    @foreach ($users as $u)
        <tr class="{{ $u->trashed() ? 'text-muted' : '' }}">
            <td>{{ $u->name }} @if($u->trashed())<span class="badge text-bg-secondary">حذف‌شده</span>@endif</td>
            <td class="ltr text-end">{{ $u->username }}</td>
            <td>{{ \App\Models\User::ROLES[$u->role] }}</td>
            <td>{!! status_badge($u->status) !!}</td>
            <td class="money">{{ money($u->effectiveMonthlyCommitment()) }}</td>
            <td>{{ jdate($u->joined_at) }}</td>
            <td>{{ jdate($u->last_login_at, true) }}</td>
            <td>@unless($u->trashed())<a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-light">ویرایش</a>@endunless</td>
        </tr>
    @endforeach
    </tbody></table></div>
    <div class="card-footer bg-white">{{ $users->links() }}</div>
</div>
@endsection
