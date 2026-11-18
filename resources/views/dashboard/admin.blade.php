@extends('layouts.app')
@section('title', 'داشبورد مدیریت')
@section('content')
@php $div = \App\Support\Money::displayFactor(); $unit = currency_label(); @endphp
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'ارزش خالص صندوق (NAV)', 'value' => money($state->nav), 'icon' => 'bank', 'hint' => 'هر واحد: '.num($state->navPerUnit, 4).' ریال'])</div>
    <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'نقدینگی', 'value' => money($state->cash), 'icon' => 'cash'])</div>
    <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'ارزش دارایی‌های غیرنقدی', 'value' => money($state->assetsMarket), 'icon' => 'gem'])</div>
    <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'سرمایه اختصاصی اعضا (ارزش روز)', 'value' => money($memberValue), 'icon' => 'people'])</div>
    <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'ارزش Pool', 'value' => money($poolValue), 'icon' => 'piggy-bank'])</div>
    <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'سود/زیان تحقق‌یافته', 'value' => money($state->realizedPnl), 'icon' => 'check2-circle', 'class' => $state->realizedPnl->isNegative() ? 'negative' : 'positive'])</div>
    <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'سود/زیان تحقق‌نیافته', 'value' => money($state->unrealizedPnl()), 'icon' => 'hourglass-split', 'class' => $state->unrealizedPnl()->isNegative() ? 'negative' : 'positive'])</div>
    <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'اعضای فعال', 'value' => num($activeMembers), 'icon' => 'person-check'])</div>
    <div class="col-6 col-xl-3"><a href="{{ route('admin.approvals') }}" class="text-decoration-none text-reset">@include('partials.stat', ['label' => 'عملیات در انتظار تأیید', 'value' => num($pendingTotal), 'icon' => 'hourglass', 'class' => $pendingTotal ? 'text-warning' : ''])</a></div>
    <div class="col-6 col-xl-3"><a href="{{ route('ideas.index') }}" class="text-decoration-none text-reset">@include('partials.stat', ['label' => 'ایده‌های در حال بررسی', 'value' => num($ideasInReview), 'icon' => 'lightbulb'])</a></div>
    <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'هزینه‌ها و کارمزدها', 'value' => money($state->expenses), 'icon' => 'receipt'])</div>
    <div class="col-6 col-xl-3">@include('partials.stat', ['label' => 'سایر درآمدها', 'value' => money($state->otherIncome), 'icon' => 'plus-circle'])</div>
</div>

@if (count($stale))
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i> <strong>هشدار ارزش‌گذاری قدیمی:</strong>
    @foreach ($stale as $s)
        <a href="{{ route('valuations.create', ['asset_id' => $s['asset']->id]) }}" class="badge text-bg-warning text-dark text-decoration-none">{{ $s['asset']->name }} — {{ $s['date'] ? jdate($s['date']).' ('.$s['days'].' روز پیش)' : 'بدون ارزش‌گذاری' }}</a>
    @endforeach
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100"><div class="card-header">روند ارزش خالص صندوق</div><div class="card-body">
            @if (count($navHistory) > 0)
                <div class="chart-box"><canvas data-chart="line" data-source="nav-data"></canvas></div>
            @else <div class="empty-state">پس از اولین عملیات مالی نمایش داده می‌شود.</div> @endif
        </div></div>
        <script type="application/json" id="nav-data">{!! js_json(['labels' => array_column($navHistory, 'date'), 'datasets' => [['label' => 'ارزش خالص', 'data' => array_column($navHistory, 'nav')]], 'divisor' => $div, 'unit' => $unit]) !!}</script>
    </div>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-header">ترکیب دارایی‌ها</div><div class="card-body">
            @if (count($composition))<div class="chart-box"><canvas data-chart="doughnut" data-source="comp-data"></canvas></div>
            @else <div class="empty-state">دارایی ثبت نشده است.</div> @endif
        </div></div>
        <script type="application/json" id="comp-data">{!! js_json(['labels' => array_column($composition, 'label'), 'datasets' => [['data' => array_column($composition, 'value')]], 'divisor' => $div, 'unit' => $unit]) !!}</script>
    </div>
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-header">روند واریزی ماهانه (۱۲ ماه)</div><div class="card-body"><div class="chart-box"><canvas data-chart="bar" data-source="monthly-data"></canvas></div></div></div>
        <script type="application/json" id="monthly-data">{!! js_json(['labels' => array_column($monthly, 'label'), 'datasets' => [['label' => 'اختصاصی اعضا', 'data' => array_column($monthly, 'member')], ['label' => 'اهدایی Pool', 'data' => array_column($monthly, 'pool')]], 'divisor' => $div, 'unit' => $unit]) !!}</script>
    </div>
    <div class="col-lg-3">
        <div class="card h-100"><div class="card-header">روند سود و زیان</div><div class="card-body">
            @if (count($navHistory))<div class="chart-box"><canvas data-chart="line" data-source="pnl-data"></canvas></div>@else<div class="empty-state">—</div>@endif
        </div></div>
        <script type="application/json" id="pnl-data">{!! js_json(['labels' => array_column($navHistory, 'date'), 'datasets' => [['label' => 'سود/زیان کل', 'data' => array_column($navHistory, 'pnl'), 'color' => '#e0a526']], 'divisor' => $div, 'unit' => $unit]) !!}</script>
    </div>
    <div class="col-lg-3">
        <div class="card h-100"><div class="card-header">توزیع ارزش سبدهای مالکیتی</div><div class="card-body">
            @if (count($distribution))<div class="chart-box"><canvas data-chart="doughnut" data-source="dist-data"></canvas></div>@else<div class="empty-state">—</div>@endif
        </div></div>
        <script type="application/json" id="dist-data">{!! js_json(['labels' => array_column($distribution, 'label'), 'datasets' => [['data' => array_column($distribution, 'value')]], 'divisor' => $div, 'unit' => $unit]) !!}</script>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100"><div class="card-header d-flex justify-content-between"><span>آخرین تراکنش‌های دفتر کل</span><a class="small" href="{{ route('admin.ledger.index') }}">همه</a></div>
            <div class="table-responsive"><table class="table mb-0">
                <thead><tr><th>#</th><th>تاریخ</th><th>نوع</th><th>شرح</th></tr></thead>
                <tbody>
                @forelse ($recentLedger as $t)
                    <tr><td><a href="{{ route('admin.ledger.show', $t) }}">{{ $t->id }}</a></td><td>{{ jdate($t->transaction_date) }}</td><td>{{ \App\Models\LedgerTransaction::TYPES[$t->type] ?? $t->type }}</td><td class="small">{{ $t->description }}</td></tr>
                @empty <tr><td colspan="4" class="empty-state">هنوز سندی ثبت نشده است.</td></tr>
                @endforelse
                </tbody></table></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100"><div class="card-header d-flex justify-content-between"><span>واریزی‌های در انتظار</span><a class="small" href="{{ route('admin.approvals') }}">صف تأییدها</a></div>
            <ul class="list-group list-group-flush">
                @forelse ($pendingContributions as $c)
                    <li class="list-group-item d-flex justify-content-between align-items-center"><a href="{{ route('contributions.show', $c) }}">{{ $c->member?->name ?? 'Pool' }} — {{ $c->type->name }}</a><span class="money small">{{ money($c->amount_irr) }}</span></li>
                @empty <li class="list-group-item text-muted small">موردی در انتظار نیست.</li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-header d-flex justify-content-between"><span>ارزش‌گذاری‌های اخیر</span><a class="small" href="{{ route('valuations.index') }}">همه</a></div>
            <ul class="list-group list-group-flush">
                @forelse ($recentValuations as $v)
                    <li class="list-group-item d-flex justify-content-between"><span>{{ $v->asset->name }} — {{ jdate($v->valuation_date) }}</span><span><span class="money small">{{ money($v->unit_price_irr) }}</span> {!! status_badge($v->status) !!}</span></li>
                @empty <li class="list-group-item text-muted small">ارزش‌گذاری ثبت نشده است.</li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-header d-flex justify-content-between"><span>ایده‌های جدید</span><a class="small" href="{{ route('ideas.index') }}">همه</a></div>
            <ul class="list-group list-group-flush">
                @forelse ($newIdeas as $idea)
                    <li class="list-group-item d-flex justify-content-between"><a href="{{ route('ideas.show', $idea) }}">{{ $idea->title }}</a>{!! status_badge($idea->status) !!}</li>
                @empty <li class="list-group-item text-muted small">ایده‌ای ثبت نشده است.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

<h2 class="h6 fw-bold mt-4 mb-3">سبد اختصاصی من (مدیر به‌عنوان عضو)</h2>
@include('dashboard._personal')
@endsection
@push('scripts')<script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>@endpush
