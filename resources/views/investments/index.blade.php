@extends('layouts.app')
@section('title', 'سرمایه‌گذاری‌ها')
@section('content')
@php $admin = auth()->user()->isAdmin(); $types = \App\Models\InvestmentTransaction::TYPES; @endphp
@if ($admin)
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('investments.create', ['type' => 'buy']) }}" class="btn btn-primary"><i class="bi bi-cart-plus"></i> خرید</a>
    <a href="{{ route('investments.create', ['type' => 'sell']) }}" class="btn btn-outline-primary"><i class="bi bi-cart-dash"></i> فروش</a>
    <a href="{{ route('investments.create', ['type' => 'expense']) }}" class="btn btn-outline-secondary"><i class="bi bi-receipt"></i> هزینه</a>
    <a href="{{ route('investments.create', ['type' => 'income']) }}" class="btn btn-outline-secondary"><i class="bi bi-plus-circle"></i> درآمد</a>
    <a href="{{ route('investments.create', ['type' => 'transfer']) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left-right"></i> انتقال وجه</a>
</div>
@endif
<form class="card card-body mb-3 filter-bar" method="GET">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label small">نوع</label><select name="type" class="form-select form-select-sm"><option value="">همه</option>@foreach ($types as $k => $v)<option value="{{ $k }}" @selected(request('type') == $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><label class="form-label small">دارایی</label><select name="asset_id" class="form-select form-select-sm"><option value="">همه</option>@foreach ($assets as $a)<option value="{{ $a->id }}" @selected(request('asset_id') == $a->id)>{{ $a->name }}</option>@endforeach</select></div>
        @if ($admin)<div class="col-6 col-md-2"><label class="form-label small">وضعیت</label><select name="status" class="form-select form-select-sm"><option value="">همه</option>@foreach (['pending' => 'در انتظار', 'approved' => 'تأییدشده', 'rejected' => 'ردشده', 'reversed' => 'برگشت‌خورده'] as $k => $v)<option value="{{ $k }}" @selected(request('status') == $k)>{{ $v }}</option>@endforeach</select></div>@endif
        <div class="col-6 col-md-2"><label class="form-label small">از تاریخ</label><input name="from" class="form-control form-control-sm ltr" value="{{ request('from') }}" placeholder="1404/01/01"></div>
        <div class="col-6 col-md-2"><label class="form-label small">تا تاریخ</label><input name="to" class="form-control form-control-sm ltr" value="{{ request('to') }}" placeholder="1404/12/29"></div>
        <div class="col-6 col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i> فیلتر</button></div>
    </div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>تاریخ</th><th>نوع</th><th>دارایی</th><th>مقدار</th><th>قیمت واحد</th><th>مبلغ کل/خالص</th><th>سود/زیان تحقق‌یافته</th><th>وضعیت</th><th></th></tr></thead>
            <tbody>
            @forelse ($transactions as $t)
                <tr>
                    <td class="num">{{ $t->id }}</td>
                    <td>{{ jdate($t->transaction_date) }}</td>
                    <td>{{ $types[$t->type] }}</td>
                    <td>{{ $t->asset?->name ?? ($t->type === 'transfer' ? $t->cashAccount?->name.' ← '.$t->toCashAccount?->name : 'کل پرتفوی') }}</td>
                    <td class="num">{{ (float) $t->quantity ? num($t->quantity, $t->asset?->decimal_places ?? 8).' '.$t->asset?->unit_name : '—' }}</td>
                    <td class="money">{{ (float) $t->unit_price_irr ? money($t->unit_price_irr) : '—' }}</td>
                    <td class="money">{{ money($t->net_amount_irr) }}</td>
                    <td class="money {{ $t->realized_pnl_irr < 0 ? 'negative' : 'positive' }}">{{ $t->realized_pnl_irr !== null ? money($t->realized_pnl_irr) : '—' }}</td>
                    <td>{!! status_badge($t->status) !!}</td>
                    <td><a href="{{ route('investments.show', $t) }}" class="btn btn-sm btn-light">جزئیات</a></td>
                </tr>
            @empty
                <tr><td colspan="10" class="empty-state">عملیاتی ثبت نشده است.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $transactions->links() }}</div>
</div>
@endsection
