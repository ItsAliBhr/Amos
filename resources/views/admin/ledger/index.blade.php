@extends('layouts.app')
@section('title', 'دفتر کل')
@section('content')
@if (count($unbalanced))
    <div class="alert alert-danger">اسناد نامتوازن: {{ implode(', ', $unbalanced) }}</div>
@else
    <div class="alert alert-success py-2 small"><i class="bi bi-check2-circle"></i> همه اسناد متوازن هستند. جمع بدهکار: <span class="money">{{ money($totals->d ?? 0) }}</span> — جمع بستانکار: <span class="money">{{ money($totals->c ?? 0) }}</span></div>
@endif
<div class="card mb-3"><div class="card-header">مانده حساب‌های منطقی</div><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>کد</th><th>حساب</th><th>جمع بدهکار</th><th>جمع بستانکار</th><th>مانده (بدهکار − بستانکار)</th></tr></thead>
    <tbody>@foreach ($balances as $b)<tr><td class="num">{{ $b->account_code }}</td><td>{{ \App\Domain\Accounting\AccountCode::label($b->account_code) }}</td><td class="money">{{ money($b->d) }}</td><td class="money">{{ money($b->c) }}</td><td class="money">{{ money(\App\Support\Money::of($b->d)->minus(\App\Support\Money::of($b->c))) }}</td></tr>@endforeach</tbody>
</table></div></div>
<form class="d-flex gap-2 mb-2" method="GET"><select name="type" class="form-select form-select-sm w-auto"><option value="">همه انواع</option>@foreach (\App\Models\LedgerTransaction::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') == $k)>{{ $v }}</option>@endforeach</select><button class="btn btn-sm btn-outline-primary">فیلتر</button></form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>#</th><th>تاریخ</th><th>نوع</th><th>شرح</th><th>ثبت‌کننده</th><th>وضعیت</th></tr></thead>
    <tbody>@forelse ($transactions as $t)<tr><td><a href="{{ route('admin.ledger.show', $t) }}">{{ $t->id }}</a></td><td>{{ jdate($t->transaction_date) }}</td><td>{{ \App\Models\LedgerTransaction::TYPES[$t->type] ?? $t->type }}</td><td class="small">{{ $t->description }}</td><td class="small">{{ $t->creator?->name }}</td><td>{!! status_badge($t->status) !!}</td></tr>@empty<tr><td colspan="6" class="empty-state">سندی ثبت نشده است.</td></tr>@endforelse</tbody>
</table></div><div class="card-footer bg-white">{{ $transactions->links() }}</div></div>
@endsection
