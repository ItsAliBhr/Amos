@extends('layouts.app')
@section('title', 'دارایی‌ها و ارزش‌گذاری')
@section('content')
@php $admin = auth()->user()->isAdmin(); @endphp
@if ($admin)
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('admin.assets.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> تعریف دارایی</a>
    <a href="{{ route('valuations.create') }}" class="btn btn-outline-primary"><i class="bi bi-tag"></i> ثبت ارزش‌گذاری</a>
    <a href="{{ route('valuations.index') }}" class="btn btn-outline-secondary">تاریخچه ارزش‌گذاری‌ها</a>
    <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">دسته‌بندی‌ها</a>
</div>
@endif
<div class="row g-3 mb-3">
    <div class="col-md-3">@include('partials.stat', ['label' => 'ارزش روز دارایی‌های غیرنقدی', 'value' => money($state->assetsMarket), 'icon' => 'gem'])</div>
    <div class="col-md-3">@include('partials.stat', ['label' => 'بهای تمام‌شده', 'value' => money($state->assetsCost), 'icon' => 'receipt'])</div>
    <div class="col-md-3">@include('partials.stat', ['label' => 'سود/زیان تحقق‌نیافته', 'value' => money($state->unrealizedPnl()), 'icon' => 'hourglass-split', 'class' => $state->unrealizedPnl()->isNegative() ? 'negative' : 'positive'])</div>
    <div class="col-md-3">@include('partials.stat', ['label' => 'نقدینگی', 'value' => money($state->cash), 'icon' => 'cash'])</div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>دارایی</th><th>دسته</th><th>موجودی</th><th>بهای تمام‌شده</th><th>آخرین قیمت واحد</th><th>ارزش روز</th><th>سود/زیان تحقق‌نیافته</th><th>آخرین ارزش‌گذاری</th><th>وضعیت</th></tr></thead>
            <tbody>
            @forelse ($assets as $a)
                @php $h = $state->holdings[$a->id] ?? null; $days = $h && $h['valuation_date'] ? now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($h['valuation_date']), true) : null; @endphp
                <tr>
                    <td><a href="{{ route('assets.show', $a) }}">{{ $a->name }}</a> <span class="text-muted small ltr">{{ $a->symbol }}</span></td>
                    <td>{{ $a->category->name }}</td>
                    <td class="num">{{ $h ? num($h['quantity'], $a->decimal_places).' '.$a->unit_name : '—' }}</td>
                    <td class="money">{{ $h ? money($h['cost']) : '—' }}</td>
                    <td class="money">{{ $h && $h['unit_price'] ? money($h['unit_price']) : '—' }}</td>
                    <td class="money">{{ $h ? money($h['market']) : '—' }}</td>
                    <td class="money {{ $h && $h['market']->minus($h['cost'])->isNegative() ? 'negative' : 'positive' }}">{{ $h ? money($h['market']->minus($h['cost'])) : '—' }}</td>
                    <td>
                        @if ($h && $h['valuation_date'])
                            {{ jdate($h['valuation_date']) }}
                            @if ($days > $staleDays)<span class="badge text-bg-warning text-dark">قدیمی ({{ $days }} روز)</span>@endif
                        @elseif ($h && ! $h['quantity']->isZero())
                            <span class="badge text-bg-warning text-dark">بدون ارزش‌گذاری (به بهای تمام‌شده)</span>
                        @else — @endif
                    </td>
                    <td>{!! status_badge($a->is_active ? 'active' : 'inactive') !!}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="empty-state">دارایی تعریف نشده است.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
