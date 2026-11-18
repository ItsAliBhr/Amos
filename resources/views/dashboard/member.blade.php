@extends('layouts.app')
@section('title', 'داشبورد من')
@section('content')
@include('dashboard._personal')

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">وضعیت تعهد ماه جاری — {{ $myCommitment['label'] }}</div>
            <div class="card-body">
                <div class="mb-2">{!! status_badge($myCommitment['status']) !!}</div>
                <dl class="kv mb-0">
                    <dt>مبلغ تعهد</dt><dd class="money">{{ money($myCommitment['due']) }}</dd>
                    <dt>پرداخت تأییدشده</dt><dd class="money">{{ money($myCommitment['paid']) }}</dd>
                </dl>
                <a href="{{ route('contributions.create') }}" class="btn btn-primary btn-sm mt-2"><i class="bi bi-plus-lg"></i> ثبت واریزی</a>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>واریزی‌های اخیر من</span><a href="{{ route('contributions.index') }}" class="small">همه</a></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>تاریخ</th><th>نوع</th><th>مبلغ</th><th>وضعیت</th></tr></thead>
                    <tbody>
                    @forelse ($myRecent as $c)
                        <tr><td>{{ jdate($c->contribution_date) }}</td><td>{{ $c->type->name }}</td><td class="money">{{ money($c->amount_irr) }}</td><td><a href="{{ route('contributions.show', $c) }}">{!! status_badge($c->status) !!}</a></td></tr>
                    @empty
                        <tr><td colspan="4" class="empty-state">هنوز واریزی ثبت نکرده‌اید.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">درخواست‌های در انتظار من <span class="badge text-bg-warning">{{ $myPending }}</span></div>
            <ul class="list-group list-group-flush">
                @forelse ($myWithdrawals as $w)
                    <li class="list-group-item d-flex justify-content-between"><a href="{{ route('withdrawals.show', $w) }}">برداشت {{ money($w->requested_amount_irr) }}</a>{!! status_badge($w->status) !!}</li>
                @empty
                    <li class="list-group-item text-muted small">درخواست برداشت بازی ندارید.</li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>ایده‌های در حال بررسی</span><a class="small" href="{{ route('ideas.index') }}">همه</a></div>
            <ul class="list-group list-group-flush">
                @forelse ($ideas as $idea)
                    <li class="list-group-item d-flex justify-content-between"><a href="{{ route('ideas.show', $idea) }}">{{ $idea->title }}</a>{!! status_badge($idea->status) !!}</li>
                @empty
                    <li class="list-group-item text-muted small">ایده فعالی برای بررسی وجود ندارد.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

@if ($visibility !== 'none')
<div class="row g-3">
    <div class="col-md-4">@include('partials.stat', ['label' => 'ارزش هر واحد (NAV/Unit)', 'value' => num($state->navPerUnit, 4).' ریال', 'icon' => 'calculator'])</div>
    @if ($visibility === 'full')
        <div class="col-md-4">@include('partials.stat', ['label' => 'ارزش خالص صندوق', 'value' => money($state->nav), 'icon' => 'bank'])</div>
        <div class="col-md-4">@include('partials.stat', ['label' => 'نقدینگی صندوق', 'value' => money($state->cash), 'icon' => 'cash'])</div>
    @endif
    @if (count($navHistory) > 1)
        <div class="col-lg-{{ $visibility === 'full' ? 8 : 12 }}">
            <div class="card"><div class="card-header">روند ارزش هر واحد</div><div class="card-body"><div class="chart-box"><canvas data-chart="line" data-source="navpu-data"></canvas></div></div></div>
            <script type="application/json" id="navpu-data">{!! js_json(['labels' => array_column($navHistory, 'date'), 'datasets' => [['label' => 'ارزش هر واحد (ریال)', 'data' => array_column($navHistory, 'navPerUnit')]]]) !!}</script>
        </div>
    @endif
    @if ($visibility === 'full' && count($composition))
        <div class="col-lg-4">
            <div class="card"><div class="card-header">ترکیب دارایی‌های صندوق</div><div class="card-body"><div class="chart-box"><canvas data-chart="doughnut" data-source="comp-data"></canvas></div></div></div>
            <script type="application/json" id="comp-data">{!! js_json(['labels' => array_column($composition, 'label'), 'datasets' => [['data' => array_column($composition, 'value')]], 'divisor' => \App\Support\Money::displayFactor(), 'unit' => currency_label()]) !!}</script>
        </div>
    @endif
</div>
@endif
@endsection
@push('scripts')<script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>@endpush
