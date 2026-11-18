@extends('layouts.app')
@section('title', 'گزارش سبد مشترک Pool')
@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">@include('partials.stat', ['label' => 'ارزش روز Pool', 'value' => money($value), 'icon' => 'piggy-bank'])</div>
    <div class="col-6 col-lg-3">@include('partials.stat', ['label' => 'واحدهای Pool', 'value' => num($units, 4), 'icon' => 'stack'])</div>
    <div class="col-6 col-lg-3">@include('partials.stat', ['label' => 'کمک‌های خالص', 'value' => money($net), 'icon' => 'gift'])</div>
    <div class="col-6 col-lg-3">@include('partials.stat', ['label' => 'سود/زیان Pool', 'value' => money($pnl), 'icon' => 'graph-up', 'class' => $pnl->isNegative() ? 'negative' : 'positive', 'hint' => 'سود Pool در Pool باقی می‌ماند'])</div>
</div>
<div class="d-flex gap-2 mb-3"><a href="{{ route('withdrawals.pool.create') }}" class="btn btn-outline-secondary btn-sm">ثبت برداشت از Pool</a></div>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card"><div class="card-header">کمک‌ها و ورودی‌های Pool</div><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>تاریخ</th><th>اهداکننده</th><th>نوع</th><th>مبلغ</th><th>وضعیت</th></tr></thead>
            <tbody>@forelse ($donations as $c)<tr><td><a href="{{ route('contributions.show', $c) }}">{{ jdate($c->contribution_date) }}</a></td><td>{{ $c->member?->name ?? '—' }}</td><td>{{ $c->type->name }}</td><td class="money">{{ money($c->amount_irr) }}</td><td>{!! status_badge($c->status) !!}</td></tr>@empty<tr><td colspan="5" class="empty-state">—</td></tr>@endforelse</tbody>
        </table></div><div class="card-footer bg-white">{{ $donations->links() }}</div></div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3"><div class="card-header">جمع کمک‌ها به تفکیک اهداکننده</div><ul class="list-group list-group-flush small">
            @forelse ($byDonor as $d)<li class="list-group-item d-flex justify-content-between"><span>{{ $d->member?->name ?? 'نامشخص' }} ({{ $d->cnt }})</span><span class="money">{{ money($d->total) }}</span></li>@empty<li class="list-group-item text-muted">—</li>@endforelse
        </ul><div class="card-footer small text-muted bg-white">کمک اهدایی برای اهداکننده سهم اختصاصی ایجاد نمی‌کند.</div></div>
        <div class="card mb-3"><div class="card-header">برداشت‌های Pool</div><ul class="list-group list-group-flush small">
            @forelse ($withdrawals as $w)<li class="list-group-item d-flex justify-content-between"><a href="{{ route('withdrawals.show', $w) }}">{{ jdate($w->requested_at) }} — {{ \Illuminate\Support\Str::limit($w->request_note, 40) }}</a><span>{{ money($w->approved_amount_irr ?? $w->requested_amount_irr) }} {!! status_badge($w->status) !!}</span></li>@empty<li class="list-group-item text-muted">—</li>@endforelse
        </ul></div>
        <div class="card"><div class="card-header">گردش واحدهای Pool</div><div class="table-responsive"><table class="table table-sm mb-0">
            <tbody>@forelse ($unitHistory as $u)<tr><td>{{ jdate($u->effective_at) }}</td><td class="num {{ $u->units < 0 ? 'negative' : 'positive' }}">{{ num($u->units, 4) }}</td><td class="money">{{ money($u->amount_irr) }}</td></tr>@empty<tr><td class="empty-state">—</td></tr>@endforelse</tbody>
        </table></div></div>
    </div>
</div>
@endsection
