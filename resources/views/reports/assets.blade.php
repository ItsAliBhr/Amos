@extends('layouts.app')
@section('title', 'گزارش دارایی‌ها')
@section('content')
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>دارایی</th><th>دسته</th><th>مقدار</th><th>بهای تمام‌شده</th><th>ارزش روز</th><th>تحقق‌نیافته</th><th>تحقق‌یافته</th><th>آخرین ارزش‌گذاری</th></tr></thead>
    <tbody>
    @forelse ($assets as $a)
        @php $h = $state->holdings[$a->id] ?? null; $r = $realized[$a->id] ?? 0; @endphp
        <tr>
            <td><a href="{{ route('assets.show', $a) }}">{{ $a->name }}</a></td><td>{{ $a->category->name }}</td>
            <td class="num">{{ $h ? num($h['quantity'], $a->decimal_places) : 0 }} {{ $a->unit_name }}</td>
            <td class="money">{{ money($h['cost'] ?? 0) }}</td><td class="money">{{ money($h['market'] ?? 0) }}</td>
            <td class="money">{{ $h ? money($h['market']->minus($h['cost'])) : money(0) }}</td>
            <td class="money {{ $r < 0 ? 'negative' : 'positive' }}">{{ money($r) }}</td>
            <td>{{ $h && $h['valuation_date'] ? jdate($h['valuation_date']) : '—' }}</td>
        </tr>
    @empty <tr><td colspan="8" class="empty-state">—</td></tr>
    @endforelse
    </tbody>
    <tfoot class="fw-bold"><tr><td colspan="3">جمع</td><td class="money">{{ money($state->assetsCost) }}</td><td class="money">{{ money($state->assetsMarket) }}</td><td class="money">{{ money($state->unrealizedPnl()) }}</td><td class="money">{{ money($state->realizedPnl) }}</td><td></td></tr></tfoot>
</table></div></div>
@endsection
