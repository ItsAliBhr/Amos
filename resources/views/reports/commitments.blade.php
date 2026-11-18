@extends('layouts.app')
@section('title', 'تعهد ماهانه')
@section('content')
@php $admin = auth()->user()->isAdmin(); @endphp
<form class="d-flex flex-wrap gap-2 mb-3" method="GET">
    <select name="month" class="form-select form-select-sm w-auto">@foreach (\App\Support\Jalali::MONTHS as $i => $n)<option value="{{ $i }}" @selected($month == $i)>{{ $n }}</option>@endforeach</select>
    <input name="year" type="number" class="form-control form-control-sm w-auto" value="{{ $year }}">
    <button class="btn btn-sm btn-outline-primary">نمایش</button>
    @if ($admin)<a class="btn btn-sm btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['export' => 'csv', 'year' => $year, 'month' => $month]) }}"><i class="bi bi-filetype-csv"></i> CSV</a>@endif
</form>
<div class="card mb-3"><div class="card-header">وضعیت تعهد {{ \App\Support\Jalali::monthLabel($year, $month) }}</div><div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>عضو</th><th>تعهد</th><th>معافیت</th><th>پرداخت تأییدشده</th><th>وضعیت</th><th>یادداشت</th></tr></thead>
    <tbody>@foreach ($rows as $r)<tr><td>{{ $r['user']->name }}</td><td class="money">{{ money($r['s']['commitment']) }}</td><td class="money">{{ money($r['s']['exemption']) }}</td><td class="money">{{ money($r['s']['paid']) }}</td><td>{!! status_badge($r['s']['status']) !!}</td><td class="small">{{ $r['s']['note'] }}</td></tr>@endforeach</tbody>
</table></div></div>
@if ($admin)
<div class="card"><div class="card-header">پرداخت‌های معوق (۶ ماه گذشته)</div><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>عضو</th><th>ماه</th><th>تعهد</th><th>پرداخت‌شده</th><th>وضعیت</th></tr></thead>
    <tbody>@forelse ($overdue as $r)<tr><td>{{ $r['user']->name }}</td><td>{{ $r['s']['label'] }}</td><td class="money">{{ money($r['s']['due']) }}</td><td class="money">{{ money($r['s']['paid']) }}</td><td>{!! status_badge($r['s']['status']) !!}</td></tr>@empty<tr><td colspan="5" class="empty-state">معوقه‌ای وجود ندارد.</td></tr>@endforelse</tbody>
</table></div></div>
@endif
@endsection
