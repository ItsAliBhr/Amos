@extends('layouts.app')
@section('title', 'واریزی‌ها')
@section('content')
@php $admin = auth()->user()->isAdmin(); @endphp
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2">
        <a href="{{ route('contributions.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> ثبت واریزی</a>
        <a href="{{ route('contributions.export', request()->query()) }}" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv"></i> خروجی CSV (Excel)</a>
    </div>
    <div class="small text-muted">
        مجموع تأییدشده اختصاصی: <span class="money fw-semibold">{{ money($totals['member']) }}</span> ·
        کمک‌های Pool: <span class="money fw-semibold">{{ money($totals['pool']) }}</span> ·
        در انتظار: <span class="money fw-semibold">{{ money($totals['pending']) }}</span>
    </div>
</div>

<form class="card card-body mb-3 filter-bar" method="GET">
    <div class="row g-2 align-items-end">
        @if ($admin)
        <div class="col-6 col-md-2"><label class="form-label small">عضو</label>
            <select name="member_id" class="form-select form-select-sm"><option value="">همه</option>
                @foreach ($members as $m)<option value="{{ $m->id }}" @selected(request('member_id') == $m->id)>{{ $m->name }}</option>@endforeach
            </select></div>
        @endif
        <div class="col-6 col-md-2"><label class="form-label small">نوع</label>
            <select name="type" class="form-select form-select-sm"><option value="">همه</option>
                @foreach ($types as $t)<option value="{{ $t->code }}" @selected(request('type') == $t->code)>{{ $t->name }}</option>@endforeach
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small">وضعیت</label>
            <select name="status" class="form-select form-select-sm"><option value="">همه</option>
                @foreach (['draft' => 'پیش‌نویس', 'pending' => 'در انتظار', 'approved' => 'تأییدشده', 'rejected' => 'ردشده', 'reversed' => 'برگشت‌خورده'] as $k => $v)<option value="{{ $k }}" @selected(request('status') == $k)>{{ $v }}</option>@endforeach
            </select></div>
        <div class="col-6 col-md-1"><label class="form-label small">از تاریخ</label><input name="from" class="form-control form-control-sm ltr" placeholder="1404/01/01" value="{{ request('from') }}"></div>
        <div class="col-6 col-md-1"><label class="form-label small">تا تاریخ</label><input name="to" class="form-control form-control-sm ltr" placeholder="1404/12/29" value="{{ request('to') }}"></div>
        <div class="col-3 col-md-1"><label class="form-label small">سال تعهد</label><input name="cy" type="number" class="form-control form-control-sm" value="{{ request('cy') }}"></div>
        <div class="col-3 col-md-1"><label class="form-label small">ماه</label><input name="cm" type="number" min="1" max="12" class="form-control form-control-sm" value="{{ request('cm') }}"></div>
        <div class="col-6 col-md-1"><label class="form-label small">جست‌وجو</label><input name="q" class="form-control form-control-sm" value="{{ request('q') }}" placeholder="کد پیگیری"></div>
        <div class="col-6 col-md-1"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i> فیلتر</button></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>#</th>
                @if ($admin)<th>عضو</th>@endif
                <th>نوع</th><th>سبد</th>
                <th><a href="{{ request()->fullUrlWithQuery(['sort' => 'amount_irr', 'dir' => request('dir') === 'asc' ? 'desc' : 'asc']) }}">مبلغ <i class="bi bi-arrow-down-up small"></i></a></th>
                <th><a href="{{ request()->fullUrlWithQuery(['sort' => 'contribution_date', 'dir' => request('dir') === 'asc' ? 'desc' : 'asc']) }}">تاریخ پرداخت <i class="bi bi-arrow-down-up small"></i></a></th>
                <th>ماه تعهد</th><th>وضعیت</th><th></th>
            </tr></thead>
            <tbody>
            @forelse ($contributions as $c)
                <tr>
                    <td class="num">{{ $c->id }}</td>
                    @if ($admin)<td>{{ $c->member?->name ?? '—' }}</td>@endif
                    <td>{{ $c->type->name }}</td>
                    <td>{{ $c->ownershipAccount?->isPool() ? 'Pool' : 'اختصاصی' }}</td>
                    <td class="money">{{ money($c->amount_irr) }} @if($c->exceeds_cap)<i class="bi bi-exclamation-triangle text-warning" title="عبور از سقف ماهانه"></i>@endif</td>
                    <td>{{ jdate($c->contribution_date) }}</td>
                    <td>{{ $c->commitment_year ? \App\Support\Jalali::monthLabel($c->commitment_year, $c->commitment_month) : '—' }}</td>
                    <td>{!! status_badge($c->status) !!}</td>
                    <td><a href="{{ route('contributions.show', $c) }}" class="btn btn-sm btn-light">جزئیات</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="empty-state">موردی یافت نشد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $contributions->links() }}</div>
</div>
@endsection
