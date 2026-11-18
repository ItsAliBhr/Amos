@extends('layouts.app')
@section('title', 'صف تأییدها')
@section('content')
<div class="card mb-3"><div class="card-header">واریزی‌های در انتظار <span class="badge text-bg-warning text-dark">{{ $contributions->count() }}</span></div>
<div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>#</th><th>عضو</th><th>نوع / سبد</th><th>مبلغ</th><th>تاریخ</th><th>حساب مقصد</th><th>رسید</th><th class="text-nowrap">عملیات</th></tr></thead>
    <tbody>
    @forelse ($contributions as $c)
        <tr>
            <td><a href="{{ route('contributions.show', $c) }}">{{ $c->id }}</a></td>
            <td>{{ $c->member?->name ?? '—' }}</td>
            <td>{{ $c->type->name }} <span class="small text-muted">({{ $c->ownershipAccount?->isPool() ? 'Pool' : 'اختصاصی' }})</span> @if($c->exceeds_cap)<span class="badge text-bg-warning text-dark">عبور از سقف</span>@endif</td>
            <td class="money">{{ money($c->amount_irr) }}</td>
            <td>{{ jdate($c->contribution_date) }}</td>
            <td>{{ $c->destinationAccount?->name ?? '—' }}</td>
            <td>@if ($c->receipt_attachment_id)<a href="{{ route('attachments.show', $c->receipt_attachment_id) }}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i></a>@endif</td>
            <td class="text-nowrap">
                @if ($c->destination_account_id && ! $c->exceeds_cap)
                    <form method="POST" action="{{ route('contributions.approve', $c) }}" class="d-inline" data-confirm="تأیید و صدور واحد؟">@csrf<button class="btn btn-sm btn-success" type="submit">تأیید</button></form>
                @endif
                <a href="{{ route('contributions.show', $c) }}" class="btn btn-sm btn-light">بررسی</a>
            </td>
        </tr>
    @empty <tr><td colspan="8" class="empty-state">موردی در انتظار نیست.</td></tr>
    @endforelse
    </tbody></table></div></div>

<div class="card mb-3"><div class="card-header">عملیات سرمایه‌گذاری در انتظار <span class="badge text-bg-warning text-dark">{{ $investments->count() }}</span></div>
<div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>#</th><th>نوع</th><th>دارایی</th><th>مبلغ</th><th>تاریخ</th><th>حساب</th><th></th></tr></thead>
    <tbody>
    @forelse ($investments as $t)
        <tr><td>{{ $t->id }}</td><td>{{ \App\Models\InvestmentTransaction::TYPES[$t->type] }}</td><td>{{ $t->asset?->name ?? '—' }}</td><td class="money">{{ money($t->net_amount_irr) }}</td><td>{{ jdate($t->transaction_date) }}</td><td>{{ $t->cashAccount?->name }}</td>
            <td class="text-nowrap"><form method="POST" action="{{ route('investments.approve', $t) }}" class="d-inline" data-confirm="تأیید نهایی؟">@csrf<button class="btn btn-sm btn-success" type="submit">تأیید</button></form> <a href="{{ route('investments.show', $t) }}" class="btn btn-sm btn-light">بررسی</a></td></tr>
    @empty <tr><td colspan="7" class="empty-state">موردی در انتظار نیست.</td></tr>
    @endforelse
    </tbody></table></div></div>

<div class="card mb-3"><div class="card-header">ارزش‌گذاری‌های در انتظار <span class="badge text-bg-warning text-dark">{{ $valuations->count() }}</span></div>
<div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>دارایی</th><th>تاریخ</th><th>قیمت واحد</th><th>ارزش کل</th><th></th></tr></thead>
    <tbody>
    @forelse ($valuations as $v)
        <tr><td>{{ $v->asset->name }}</td><td>{{ jdate($v->valuation_date) }}</td><td class="money">{{ money($v->unit_price_irr) }}</td><td class="money">{{ money($v->total_value_irr) }}</td>
            <td><form method="POST" action="{{ route('valuations.approve', $v) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success" type="submit">تأیید</button></form> <a href="{{ route('valuations.index', ['status' => 'pending']) }}" class="btn btn-sm btn-light">بررسی</a></td></tr>
    @empty <tr><td colspan="5" class="empty-state">موردی در انتظار نیست.</td></tr>
    @endforelse
    </tbody></table></div></div>

<div class="card"><div class="card-header">درخواست‌های برداشت باز <span class="badge text-bg-warning text-dark">{{ $withdrawals->count() }}</span></div>
<div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>#</th><th>سبد</th><th>مبلغ</th><th>تاریخ</th><th>وضعیت</th><th></th></tr></thead>
    <tbody>
    @forelse ($withdrawals as $w)
        <tr><td>{{ $w->id }}</td><td>{{ $w->ownershipAccount->displayName() }}</td><td class="money">{{ money($w->approved_amount_irr ?? $w->requested_amount_irr) }}</td><td>{{ jdate($w->requested_at) }}</td><td>{!! status_badge($w->status) !!}</td><td><a href="{{ route('withdrawals.show', $w) }}" class="btn btn-sm btn-light">بررسی</a></td></tr>
    @empty <tr><td colspan="6" class="empty-state">موردی وجود ندارد.</td></tr>
    @endforelse
    </tbody></table></div></div>
@endsection
