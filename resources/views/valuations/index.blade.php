@extends('layouts.app')
@section('title', 'ارزش‌گذاری‌ها')
@section('content')
<div class="d-flex flex-wrap gap-2 mb-3 justify-content-between">
    <a href="{{ route('valuations.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> ثبت ارزش‌گذاری</a>
    <form class="d-flex gap-2" method="GET">
        <select name="asset_id" class="form-select form-select-sm"><option value="">همه دارایی‌ها</option>@foreach ($assets as $a)<option value="{{ $a->id }}" @selected(request('asset_id') == $a->id)>{{ $a->name }}</option>@endforeach</select>
        <select name="status" class="form-select form-select-sm"><option value="">همه وضعیت‌ها</option>@foreach (['pending' => 'در انتظار', 'approved' => 'تأییدشده', 'rejected' => 'ردشده'] as $k => $v)<option value="{{ $k }}" @selected(request('status') == $k)>{{ $v }}</option>@endforeach</select>
        <button class="btn btn-sm btn-outline-primary">فیلتر</button>
    </form>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>#</th><th>دارایی</th><th>تاریخ</th><th>قیمت واحد</th><th>مقدار مبنا</th><th>ارزش کل</th><th>منبع</th><th>ثبت‌کننده</th><th>وضعیت</th><th></th></tr></thead>
    <tbody>
    @forelse ($valuations as $v)
        <tr>
            <td class="num">{{ $v->id }}</td><td><a href="{{ route('assets.show', $v->asset) }}">{{ $v->asset->name }}</a></td><td>{{ jdate($v->valuation_date) }}</td>
            <td class="money">{{ money($v->unit_price_irr) }}</td><td class="num">{{ num($v->total_quantity, $v->asset->decimal_places) }}</td><td class="money">{{ money($v->total_value_irr) }}</td>
            <td class="small">{{ $v->source }}</td><td class="small">{{ $v->creator?->name }}</td><td>{!! status_badge($v->status) !!}</td>
            <td class="text-nowrap">
                @if ($v->status === 'pending')
                    <form method="POST" action="{{ route('valuations.approve', $v) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success" type="submit">تأیید</button></form>
                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rej{{ $v->id }}">رد</button>
                    @include('partials.reason-modal', ['id' => 'rej'.$v->id, 'action' => route('valuations.reject', $v), 'title' => 'رد ارزش‌گذاری', 'submit' => 'رد'])
                @endif
            </td>
        </tr>
    @empty <tr><td colspan="10" class="empty-state">ارزش‌گذاری ثبت نشده است.</td></tr>
    @endforelse
    </tbody></table></div>
    <div class="card-footer bg-white">{{ $valuations->links() }}</div>
</div>
@endsection
