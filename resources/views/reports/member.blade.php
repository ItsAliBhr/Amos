@extends('layouts.app')
@section('title', 'گزارش عضو: '.$user->name)
@section('content')
@if ($members->count())
<form method="GET" class="mb-3 d-flex gap-2" onsubmit="window.location='{{ url('reports/member') }}/'+this.u.value; return false;">
    <select name="u" class="form-select form-select-sm w-auto">@foreach ($members as $m)<option value="{{ $m->id }}" @selected($m->id === $user->id)>{{ $m->name }}</option>@endforeach</select>
    <button class="btn btn-sm btn-outline-primary">نمایش</button>
</form>
@endif
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">@include('partials.stat', ['label' => 'ارزش روز سبد', 'value' => money($value), 'icon' => 'wallet2'])</div>
    <div class="col-6 col-lg-3">@include('partials.stat', ['label' => 'واحدها', 'value' => num($units, 4), 'icon' => 'stack', 'hint' => 'هر واحد '.num($state->navPerUnit, 4).' ریال'])</div>
    <div class="col-6 col-lg-3">@include('partials.stat', ['label' => 'سرمایه خالص (واریز − برداشت)', 'value' => money($net), 'icon' => 'cash-stack'])</div>
    <div class="col-6 col-lg-3">@include('partials.stat', ['label' => 'سود/زیان', 'value' => money($pnl), 'icon' => 'graph-up', 'class' => $pnl->isNegative() ? 'negative' : 'positive'])</div>
</div>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-header">واریزی‌ها</div><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>تاریخ</th><th>نوع</th><th>مبلغ</th><th>وضعیت</th></tr></thead>
            <tbody>@forelse ($contributions as $c)<tr><td><a href="{{ route('contributions.show', $c) }}">{{ jdate($c->contribution_date) }}</a></td><td>{{ $c->type->name }}</td><td class="money">{{ money($c->amount_irr) }}</td><td>{!! status_badge($c->status) !!}</td></tr>@empty<tr><td colspan="4" class="empty-state">—</td></tr>@endforelse</tbody>
        </table></div></div>
        <div class="card"><div class="card-header">برداشت‌ها</div><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>تاریخ</th><th>مبلغ</th><th>وضعیت</th></tr></thead>
            <tbody>@forelse ($withdrawals as $w)<tr><td><a href="{{ route('withdrawals.show', $w) }}">{{ jdate($w->requested_at) }}</a></td><td class="money">{{ money($w->approved_amount_irr ?? $w->requested_amount_irr) }}</td><td>{!! status_badge($w->status) !!}</td></tr>@empty<tr><td colspan="3" class="empty-state">—</td></tr>@endforelse</tbody>
        </table></div></div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-header">تعهد ماهانه</div><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>ماه</th><th>تعهد</th><th>پرداخت‌شده</th><th>وضعیت</th></tr></thead>
            <tbody>@foreach ($commitments as $s)<tr><td>{{ $s['label'] }}</td><td class="money">{{ money($s['due']) }}</td><td class="money">{{ money($s['paid']) }}</td><td>{!! status_badge($s['status']) !!}</td></tr>@endforeach</tbody>
        </table></div></div>
        <div class="card"><div class="card-header">گردش واحدها</div><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>زمان</th><th>واحد</th><th>ارزش هر واحد</th><th>مبلغ</th></tr></thead>
            <tbody>@forelse ($unitHistory as $u)<tr><td>{{ jdate($u->effective_at, true) }}</td><td class="num {{ $u->units < 0 ? 'negative' : 'positive' }}">{{ num($u->units, 8) }}</td><td class="num">{{ num($u->nav_per_unit_irr, 4) }}</td><td class="money">{{ money($u->amount_irr) }}</td></tr>@empty<tr><td colspan="4" class="empty-state">—</td></tr>@endforelse</tbody>
        </table></div></div>
    </div>
</div>
@endsection
