@extends('layouts.app')
@section('title', 'گزارش کل صندوق')
@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">@include('partials.stat', ['label' => 'ارزش خالص (NAV)', 'value' => money($state->nav), 'icon' => 'bank'])</div>
    <div class="col-6 col-lg-3">@include('partials.stat', ['label' => 'کل واحدها', 'value' => num($state->totalUnits, 4), 'icon' => 'stack'])</div>
    <div class="col-6 col-lg-3">@include('partials.stat', ['label' => 'ارزش هر واحد', 'value' => num($state->navPerUnit, 6).' ریال', 'icon' => 'calculator'])</div>
    <div class="col-6 col-lg-3">@include('partials.stat', ['label' => 'عملکرد از ابتدا (NAV/Unit)', 'value' => $performance !== null ? \App\Support\Money::percent($performance) : '—', 'icon' => 'graph-up', 'class' => $performance && $performance->isNegative() ? 'negative' : 'positive', 'hint' => 'نسبت به قیمت اولیه '.num($initialPrice, 4).' ریال'])</div>
</div>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-header">ترکیب ارزش خالص</div><table class="table mb-0">
            <tr><td>نقدینگی تأییدشده</td><td class="money text-start">{{ money($state->cash) }}</td></tr>
            <tr><td>ارزش روز دارایی‌های غیرنقدی</td><td class="money text-start">{{ money($state->assetsMarket) }}</td></tr>
            <tr><td>مطالبات</td><td class="money text-start">{{ money($state->receivables) }}</td></tr>
            <tr><td>بدهی‌ها و هزینه‌های پرداخت‌نشده</td><td class="money text-start">({{ money($state->payables) }})</td></tr>
            <tr class="fw-bold"><td>ارزش خالص صندوق</td><td class="money text-start">{{ money($state->nav) }}</td></tr>
        </table></div>
        <div class="card mb-3"><div class="card-header">سود و زیان</div><table class="table mb-0">
            <tr><td>سود/زیان تحقق‌یافته (فروش)</td><td class="money text-start">{{ money($state->realizedPnl) }}</td></tr>
            <tr><td>سود/زیان تحقق‌نیافته (ارزش‌گذاری)</td><td class="money text-start">{{ money($state->unrealizedPnl()) }}</td></tr>
            <tr><td>سایر درآمدها</td><td class="money text-start">{{ money($state->otherIncome) }}</td></tr>
            <tr><td>هزینه‌ها و کارمزدها</td><td class="money text-start">({{ money($state->expenses) }})</td></tr>
        </table></div>
        <div class="card"><div class="card-header">موجودی حساب‌های نقدی</div><table class="table mb-0">
            @forelse ($state->cashByAccount as $id => $bal)<tr><td>{{ $cashAccounts[$id]->name ?? '#'.$id }}</td><td class="money text-start">{{ money($bal) }}</td></tr>
            @empty <tr><td class="text-muted">—</td></tr> @endforelse
        </table></div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-header">سبدهای مالکیتی</div><div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>سبد</th><th>واحد</th><th>ارزش روز</th><th>سهم</th></tr></thead>
            <tbody>
            @foreach ($accounts as $row)
                @continue($row['units']->isZero())
                <tr><td>{{ $row['account']->displayName() }}</td><td class="num">{{ num($row['units'], 4) }}</td><td class="money">{{ money($row['value']) }}</td>
                    <td class="num">{{ $state->totalUnits->isPositive() ? \App\Support\Money::percent($row['units']->multipliedBy(100)->dividedBy($state->totalUnits, 4, \Brick\Math\RoundingMode::HalfUp)) : '—' }}</td></tr>
            @endforeach
            </tbody></table></div></div>
        <div class="card"><div class="card-header">ترکیب دارایی‌ها</div><div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>دارایی</th><th>مقدار</th><th>بهای تمام‌شده</th><th>ارزش روز</th></tr></thead>
            <tbody>
            @forelse ($state->holdings as $id => $h)
                <tr><td>{{ $assets[$id]->name ?? '#'.$id }}</td><td class="num">{{ num($h['quantity'], $assets[$id]->decimal_places ?? 8) }}</td><td class="money">{{ money($h['cost']) }}</td><td class="money">{{ money($h['market']) }}</td></tr>
            @empty <tr><td colspan="4" class="empty-state">—</td></tr> @endforelse
            </tbody></table></div></div>
    </div>
</div>
<div class="card mt-3"><div class="card-header">تاریخچه ارزش روز (۳۰ ثبت آخر)</div><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>تاریخ</th><th>نقدینگی</th><th>ارزش دارایی‌ها</th><th>NAV</th><th>کل واحدها</th><th>ارزش هر واحد</th></tr></thead>
    <tbody>@foreach ($snapshots as $s)<tr><td>{{ jdate($s->snapshot_date) }}</td><td class="money">{{ money($s->cash_irr) }}</td><td class="money">{{ money($s->assets_market_irr) }}</td><td class="money">{{ money($s->nav_irr) }}</td><td class="num">{{ num($s->total_units, 4) }}</td><td class="num">{{ num($s->nav_per_unit_irr, 6) }}</td></tr>@endforeach</tbody>
</table></div></div>
@endsection
